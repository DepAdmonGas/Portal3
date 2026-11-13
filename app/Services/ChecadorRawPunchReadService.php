<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Throwable;

/**
 * Development-only raw PunchEvent reader for the Checador MVP report.
 *
 * This repository contains SELECT statements only. The caller must pass the
 * locality ID returned by ChecadorAccessService after checking the current
 * user and requested station. This reader is restricted to the approved local
 * MVP report and the verified Portal3 development database.
 */
final class ChecadorRawPunchReadService
{
    public const DEFAULT_PAGE_SIZE = 50;
    public const MAX_PAGE_SIZE = 200;
    public const DATATABLE_PAGE_SIZES = [10, 25, 50, 100];
    public const MAX_EXPORT_RECORDS = 100000;
    public const CAPTURE_METHOD_FILTERS = [
        'BIOMETRIC', 'PIN', 'CARD', 'NFC', 'MOBILE', 'WEB_TERMINAL',
        'TEMPORARY_CODE', 'WEB_CAPTURE', 'PORTAL_TELEGRAM_VERIFIED',
    ];
    public const PROCESSING_STATUS_FILTERS = ['RECEIVED', 'PENDING_MATCH', 'REJECTED'];
    private const MAX_RANGE_SECONDS = 2678400;
    private const MAX_OFFSET = 100000;
    private const MAX_SEARCH_CHARACTERS = 100;
    private const MAX_LOCALITY_NAME_CHARACTERS = 160;
    private const TABLE = 'op_rh_v2_marcaciones_crudas';

    /**
     * Read one DataTables server-side page from the already-authorized station
     * and UTC window. Global search is applied in SQL before LIMIT/OFFSET.
     *
     * @return array{ok:bool,events?:array<int,array<string,mixed>>,records_total?:int,records_filtered?:int,start?:int,length?:int,error?:array{kind:string}}
     */
    public function readDataTablePage(
        int $authorizedLocalityId,
        string $fromUtc,
        string $toUtc,
        ?int $canonicalWorkerId,
        int $start,
        int $length,
        string $search,
        int $orderColumn,
        string $direction,
        string $authorizedLocalityName = '',
        string $captureMethod = '',
        string $processingStatus = ''
    ): array {
        $search = trim($search);
        $authorizedLocalityName = trim($authorizedLocalityName);
        $searchCharacters = preg_match_all('/./us', $search, $unused);
        if ($authorizedLocalityId < 1
            || ($canonicalWorkerId !== null && $canonicalWorkerId < 1)
            || !self::isUtcTimestamp($fromUtc)
            || !self::isUtcTimestamp($toUtc)
            || $start < 0
            || $start > self::MAX_OFFSET
            || !in_array($length, self::DATATABLE_PAGE_SIZES, true)
            || $searchCharacters === false
            || $searchCharacters > self::MAX_SEARCH_CHARACTERS
            || preg_match_all('/./us', $authorizedLocalityName, $unusedLocalityName) > self::MAX_LOCALITY_NAME_CHARACTERS
            || !in_array($orderColumn, [1, 2, 3, 4, 5, 6], true)
            || ($captureMethod !== '' && !in_array($captureMethod, self::CAPTURE_METHOD_FILTERS, true))
            || ($processingStatus !== '' && !in_array($processingStatus, self::PROCESSING_STATUS_FILTERS, true))
            || !in_array($direction, ['asc', 'desc'], true)) {
            return self::failure('bad_request');
        }

        $from = self::parseUtcTimestamp($fromUtc);
        $to = self::parseUtcTimestamp($toUtc);
        if ($from === null || $to === null) {
            return self::failure('bad_request');
        }
        $rangeSeconds = $to->getTimestamp() - $from->getTimestamp();
        if ($rangeSeconds <= 0 || $rangeSeconds > self::MAX_RANGE_SECONDS) {
            return self::failure('bad_request');
        }

        try {
            $connection = $this->authorizedReadConnection();
            $canonicalLocalityId = self::resolveCanonicalLocalityId($connection, $authorizedLocalityId);
            if ($canonicalLocalityId === null) {
                return self::failure('station_mapping_unavailable');
            }
            [$baseWhere, $baseBindings] = self::whereClause(
                $canonicalLocalityId,
                $fromUtc,
                $toUtc,
                $canonicalWorkerId,
                $captureMethod,
                $processingStatus
            );
            $totalRow = $connection->selectOne(
                'SELECT COUNT(*) AS total_records FROM ' . self::TABLE . ' AS punches WHERE ' . $baseWhere,
                $baseBindings
            );
            $recordsTotal = (int) ($totalRow->total_records ?? -1);
            if ($recordsTotal < 0) {
                return self::failure('database_unavailable');
            }

            [$searchWhere, $searchBindings] = self::searchClause($search, $authorizedLocalityName);
            $filteredWhere = $baseWhere . $searchWhere;
            $filteredBindings = array_merge($baseBindings, $searchBindings);
            $filteredRow = $search === ''
                ? $totalRow
                : $connection->selectOne(
                    'SELECT COUNT(*) AS total_records FROM ' . self::TABLE . ' AS punches WHERE ' . $filteredWhere,
                    $filteredBindings
                );
            $recordsFiltered = (int) ($filteredRow->total_records ?? -1);
            if ($recordsFiltered < 0) {
                return self::failure('database_unavailable');
            }

            $orderBy = [
                1 => 'COALESCE(punches.ocurrido_at_utc, punches.recibido_at_utc)',
                2 => 'COALESCE(punches.ocurrido_at_utc, punches.recibido_at_utc)',
                3 => 'punches.id_personal_resuelto',
                4 => 'punches.metodo_captura',
                5 => 'punches.id_dispositivo',
                6 => 'punches.processing_status',
            ][$orderColumn];
            $sql = 'SELECT punches.id, punches.event_uuid, punches.id_dispositivo, punches.id_estacion_resuelta, '
                . 'punches.id_personal_resuelto, punches.source_timestamp_raw, punches.source_timezone_reported, '
                . 'punches.ocurrido_at_utc, punches.recibido_at_utc, '
                . 'punches.metodo_captura, punches.processing_status '
                . 'FROM ' . self::TABLE . ' AS punches WHERE ' . $filteredWhere
                . ' ORDER BY ' . $orderBy . ' ' . strtoupper($direction)
                . ', punches.recibido_at_utc ' . strtoupper($direction)
                . ', punches.id ' . strtoupper($direction)
                . ' LIMIT ? OFFSET ?';
            $rows = $connection->select($sql, array_merge($filteredBindings, [$length, $start]));
            $events = array_map(static fn(object $row): array => (array) $row, $rows);

            foreach ($events as $event) {
                if ((int) ($event['id_estacion_resuelta'] ?? 0) !== $canonicalLocalityId) {
                    return self::failure('station_scope_mismatch');
                }
            }
            foreach ($events as &$event) {
                $event['integrity_status'] = 'NOT_VERIFIED_BY_PORTAL3';
                $event['occurrence_status'] = $event['ocurrido_at_utc'] === null
                    ? 'OCCURRENCE_TIME_UNRESOLVED'
                    : 'OCCURRENCE_TIME_AVAILABLE_UTC';
            }
            unset($event);

            return [
                'ok' => true,
                'events' => $events,
                'records_total' => $recordsTotal,
                'records_filtered' => $recordsFiltered,
                'start' => $start,
                'length' => $length,
                'canonical_locality_id' => $canonicalLocalityId,
                'timezone_status' => 'UNRESOLVED; UTC ONLY',
                'consistency' => 'LIVE_NON_SNAPSHOT',
            ];
        } catch (Throwable) {
            return self::failure('database_unavailable');
        }
    }

    /**
     * Search workers whose persisted raw-punch attribution belongs to the
     * already-authorized canonical locality. This intentionally includes
     * inactive historical workers and does not infer scope from current HR
     * membership.
     *
     * @return array{ok:bool,workers?:list<array{id:int,text:string}>,more?:bool,error?:array{kind:string}}
     */
    public function searchAttributedWorkers(int $authorizedLocalityId, string $term, int $page = 1): array
    {
        $term = trim($term);
        $termLength = preg_match_all('/./us', $term, $unused);
        if ($authorizedLocalityId < 1 || $termLength === false || $termLength < 2 || $termLength > 100
            || $page < 1 || $page > 1000) {
            return self::failure('bad_request');
        }

        try {
            $connection = $this->authorizedReadConnection();
            $localityId = self::resolveCanonicalLocalityId($connection, $authorizedLocalityId);
            if ($localityId === null) {
                return self::failure('station_mapping_unavailable');
            }

            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
            $pattern = '%' . $escaped . '%';
            $from = self::attributedWorkerFrom();
            $scope = 'punches.id_estacion_resuelta = ? AND punches.id_personal_resuelto IS NOT NULL '
                . "AND TRIM(COALESCE(person.nombre_completo, '')) <> '' "
                . "AND TRIM(person.nombre_completo) <> 'Nombre no disponible' ";
            $count = $connection->selectOne(
                'SELECT COUNT(DISTINCT punches.id_personal_resuelto) AS total ' . $from
                    . 'WHERE ' . $scope . "AND TRIM(person.nombre_completo) LIKE ? ESCAPE '!'",
                [$localityId, $pattern]
            );
            $total = (int) ($count->total ?? -1);
            if ($total < 0) {
                return self::failure('database_unavailable');
            }

            $pageSize = 20;
            $offset = ($page - 1) * $pageSize;
            $rows = $connection->select(
                'SELECT punches.id_personal_resuelto AS id, TRIM(person.nombre_completo) AS text ' . $from
                    . 'WHERE ' . $scope . "AND TRIM(person.nombre_completo) LIKE ? ESCAPE '!'
                    GROUP BY punches.id_personal_resuelto, TRIM(person.nombre_completo)
                    ORDER BY TRIM(person.nombre_completo), punches.id_personal_resuelto LIMIT ? OFFSET ?",
                [$localityId, $pattern, $pageSize + 1, $offset]
            );
            $more = count($rows) > $pageSize;
            if ($more) {
                array_pop($rows);
            }
            $workers = array_map(static fn(object $row): array => [
                'id' => (int) $row->id,
                'text' => (string) $row->text,
            ], $rows);

            return ['ok' => true, 'workers' => $workers, 'more' => $more || ($page * $pageSize) < $total];
        } catch (Throwable) {
            return self::failure('database_unavailable');
        }
    }

    /**
     * Search the currently authorized attendance roster plus workers with
     * persisted historical punch attribution for that locality. The roster
     * is supplied by ChecadorAttendanceRosterService and contains canonical
     * op_rh_personal.id values; historical workers remain selectable even
     * after leaving the active roster.
     *
     * @param list<int> $authorizedRosterIds
     * @return array{ok:bool,workers?:list<array{id:int,text:string}>,more?:bool,error?:array{kind:string}}
     */
    public function searchReportWorkers(
        int $authorizedLocalityId,
        array $authorizedRosterIds,
        string $term = '',
        int $page = 1
    ): array {
        $term = trim($term);
        $termLength = $term === '' ? 0 : preg_match_all('/./us', $term, $unused);
        $rosterIds = self::canonicalWorkerIds($authorizedRosterIds);
        if ($authorizedLocalityId < 1 || $termLength === false || $termLength > 100
            || $page < 1 || $page > 1000
            || count($rosterIds) !== count($authorizedRosterIds)) {
            return self::failure('bad_request');
        }

        try {
            $connection = $this->authorizedReadConnection();
            $localityId = self::resolveCanonicalLocalityId($connection, $authorizedLocalityId);
            if ($localityId === null) {
                return self::failure('station_mapping_unavailable');
            }
            [$visibleWorkers, $visibilityBindings] = self::reportWorkerVisibilityPredicate($rosterIds, $localityId);
            $nameFilter = '';
            $nameBindings = [];
            if ($term !== '') {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
                $nameFilter = "AND TRIM(person.nombre_completo) LIKE ? ESCAPE '!' ";
                $nameBindings[] = '%' . $escaped . '%';
            }
            $base = 'FROM op_rh_personal AS person WHERE TRIM(COALESCE(person.nombre_completo, \'\')) <> \'\' '
                . "AND TRIM(person.nombre_completo) <> 'Nombre no disponible' AND " . $visibleWorkers . ' ' . $nameFilter;
            $count = $connection->selectOne('SELECT COUNT(*) AS total ' . $base, array_merge($visibilityBindings, $nameBindings));
            $total = (int) ($count->total ?? -1);
            if ($total < 0) {
                return self::failure('database_unavailable');
            }

            $pageSize = 20;
            $rows = $connection->select(
                'SELECT person.id AS id, TRIM(person.nombre_completo) AS text ' . $base
                    . 'ORDER BY TRIM(person.nombre_completo), person.id LIMIT ? OFFSET ?',
                array_merge($visibilityBindings, $nameBindings, [$pageSize + 1, ($page - 1) * $pageSize])
            );
            $more = count($rows) > $pageSize;
            if ($more) {
                array_pop($rows);
            }
            return [
                'ok' => true,
                'workers' => array_map(static fn(object $row): array => [
                    'id' => (int) $row->id,
                    'text' => (string) $row->text,
                ], $rows),
                'more' => $more || ($page * $pageSize) < $total,
            ];
        } catch (Throwable) {
            return self::failure('database_unavailable');
        }
    }

    /** Validate a selected report identity against today's authorized roster or persisted locality attribution. */
    public function findReportWorker(int $authorizedLocalityId, array $authorizedRosterIds, int $workerId): array
    {
        $rosterIds = self::canonicalWorkerIds($authorizedRosterIds);
        if ($authorizedLocalityId < 1 || $workerId < 1 || count($rosterIds) !== count($authorizedRosterIds)) {
            return self::failure('bad_request');
        }
        try {
            $connection = $this->authorizedReadConnection();
            $localityId = self::resolveCanonicalLocalityId($connection, $authorizedLocalityId);
            if ($localityId === null) {
                return self::failure('station_mapping_unavailable');
            }
            [$visibleWorkers, $visibilityBindings] = self::reportWorkerVisibilityPredicate($rosterIds, $localityId);
            $row = $connection->selectOne(
                'SELECT person.id AS id, TRIM(person.nombre_completo) AS text FROM op_rh_personal AS person '
                    . "WHERE person.id = ? AND TRIM(COALESCE(person.nombre_completo, '')) <> '' "
                    . "AND TRIM(person.nombre_completo) <> 'Nombre no disponible' AND " . $visibleWorkers . ' LIMIT 1',
                array_merge([$workerId], $visibilityBindings)
            );
            if ($row === null) {
                return self::failure('worker_scope_mismatch');
            }
            return ['ok' => true, 'worker' => ['id' => (int) $row->id, 'text' => (string) $row->text]];
        } catch (Throwable) {
            return self::failure('database_unavailable');
        }
    }

    /** @param list<int> $ids @return list<int> */
    private static function canonicalWorkerIds(array $ids): array
    {
        $canonical = [];
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) {
                return [];
            }
            $canonical[] = $id;
        }
        return array_values(array_unique($canonical));
    }

    /** @param list<int> $rosterIds @return array{string,list<int>} */
    private static function reportWorkerVisibilityPredicate(array $rosterIds, int $localityId): array
    {
        $rosterPredicate = $rosterIds === [] ? '1 = 0' : 'person.id IN (' . implode(',', array_fill(0, count($rosterIds), '?')) . ')';
        return [
            '(' . $rosterPredicate . ' OR EXISTS (SELECT 1 FROM ' . self::TABLE . ' AS punches '
                . 'WHERE punches.id_estacion_resuelta = ? AND punches.id_personal_resuelto = person.id))',
            array_merge($rosterIds, [$localityId]),
        ];
    }

    /** Resolve a selected historical worker only when the exact punch identity
     * mapping and persisted locality attribution are present.
     *
     * @return array{ok:bool,worker?:array{id:int,text:string},error?:array{kind:string}}
     */
    public function findAttributedWorker(int $authorizedLocalityId, int $workerId): array
    {
        if ($authorizedLocalityId < 1 || $workerId < 1) {
            return self::failure('bad_request');
        }
        try {
            $connection = $this->authorizedReadConnection();
            $localityId = self::resolveCanonicalLocalityId($connection, $authorizedLocalityId);
            if ($localityId === null) {
                return self::failure('station_mapping_unavailable');
            }
            $row = $connection->selectOne(
                'SELECT punches.id_personal_resuelto AS id, TRIM(person.nombre_completo) AS text '
                    . self::attributedWorkerFrom()
                    . "WHERE punches.id_estacion_resuelta = ? AND punches.id_personal_resuelto = ?
                    AND TRIM(COALESCE(person.nombre_completo, '')) <> ''
                    AND TRIM(person.nombre_completo) <> 'Nombre no disponible'
                    LIMIT 1",
                [$localityId, $workerId]
            );
            if ($row === null) {
                return self::failure('worker_scope_mismatch');
            }
            return ['ok' => true, 'worker' => ['id' => (int) $row->id, 'text' => (string) $row->text]];
        } catch (Throwable) {
            return self::failure('database_unavailable');
        }
    }

    private static function attributedWorkerFrom(): string
    {
        return 'FROM ' . self::TABLE . ' AS punches '
            . 'INNER JOIN op_rh_personal AS person ON person.id = punches.id_personal_resuelto ';
    }

    /**
     * Read events for a station already authorized by the Portal3 boundary.
     * UTC windows are half-open. Rows without occurred_at_utc are included
     * only when their receipt timestamp falls in the same UTC window and are
     * explicitly returned as occurrence-unresolved records.
     *
     * @return array{ok:bool,events?:array<int,array<string,mixed>>,pagination?:array{current_page:int,per_page:int,total_records:int,total_pages:int},error?:array{kind:string}}
     */
    public function readPage(
        int $authorizedLocalityId,
        string $fromUtc,
        string $toUtc,
        ?int $canonicalWorkerId = null,
        int $page = 1,
        int $perPage = self::DEFAULT_PAGE_SIZE,
        string $sort = 'asc'
    ): array {
        if ($authorizedLocalityId < 1
            || ($canonicalWorkerId !== null && $canonicalWorkerId < 1)
            || !self::isUtcTimestamp($fromUtc)
            || !self::isUtcTimestamp($toUtc)
            || $page < 1
            || $perPage < 1
            || $perPage > self::MAX_PAGE_SIZE
            || !in_array($sort, ['asc', 'desc'], true)) {
            return self::failure('bad_request');
        }

        $from = self::parseUtcTimestamp($fromUtc);
        $to = self::parseUtcTimestamp($toUtc);
        if ($from === null || $to === null) {
            return self::failure('bad_request');
        }
        $rangeSeconds = $to->getTimestamp() - $from->getTimestamp();
        if ($rangeSeconds <= 0 || $rangeSeconds > self::MAX_RANGE_SECONDS) {
            return self::failure('bad_request');
        }

        if (($page - 1) > intdiv(self::MAX_OFFSET, $perPage)) {
            return self::failure('bad_request');
        }
        $offset = ($page - 1) * $perPage;

        try {
            $connection = $this->authorizedReadConnection();
            $canonicalLocalityId = self::resolveCanonicalLocalityId($connection, $authorizedLocalityId);
            if ($canonicalLocalityId === null) {
                return self::failure('station_mapping_unavailable');
            }
            [$where, $bindings] = self::whereClause($canonicalLocalityId, $fromUtc, $toUtc, $canonicalWorkerId);
            $count = $connection->selectOne(
                'SELECT COUNT(*) AS total_records FROM ' . self::TABLE . ' AS punches WHERE ' . $where,
                $bindings
            );
            $totalRecords = (int) ($count->total_records ?? -1);
            if ($totalRecords < 0) {
                return self::failure('database_unavailable');
            }
            $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $perPage) : 0;
            $currentPage = $totalPages > 0 ? min($page, $totalPages) : 1;
            $offset = ($currentPage - 1) * $perPage;
            $direction = strtoupper($sort);

            // Keep this allowlisted SELECT aligned with the MVP's presentation
            // contract. In particular, integrity keys/seals and metadata are
            // never selected or exposed to Portal3.
            $sql = 'SELECT punches.id, punches.event_uuid, punches.id_dispositivo, punches.id_estacion_resuelta, '
                . 'punches.id_personal_resuelto, punches.source_timestamp_raw, punches.source_timezone_reported, '
                . 'punches.ocurrido_at_utc, punches.recibido_at_utc, '
                . 'punches.metodo_captura, punches.processing_status '
                . 'FROM ' . self::TABLE . ' AS punches WHERE ' . $where
                . ' ORDER BY (punches.ocurrido_at_utc IS NULL) ASC, punches.ocurrido_at_utc ' . $direction
                . ', punches.recibido_at_utc ' . $direction . ', punches.id ' . $direction
                . ' LIMIT ? OFFSET ?';
            $rows = $connection->select($sql, array_merge($bindings, [$perPage, $offset]));
            $events = array_map(static fn(object $row): array => (array) $row, $rows);

            // Database filtering is authoritative; this second check prevents
            // a malformed/cross-station row from reaching either UI or CSV.
            foreach ($events as $event) {
                if ((int) ($event['id_estacion_resuelta'] ?? 0) !== $canonicalLocalityId) {
                    return self::failure('station_scope_mismatch');
                }
            }
            foreach ($events as &$event) {
                $event['integrity_status'] = 'NOT_VERIFIED_BY_PORTAL3';
                $event['occurrence_status'] = $event['ocurrido_at_utc'] === null
                    ? 'OCCURRENCE_TIME_UNRESOLVED'
                    : 'OCCURRENCE_TIME_AVAILABLE_UTC';
            }
            unset($event);

            return [
                'ok' => true,
                'events' => $events,
                'pagination' => [
                    'current_page' => $currentPage,
                    'per_page' => $perPage,
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages,
                ],
                'canonical_locality_id' => $canonicalLocalityId,
                'timezone_status' => 'UNRESOLVED; UTC ONLY',
                'consistency' => 'LIVE_NON_SNAPSHOT',
            ];
        } catch (Throwable) {
            // Do not leak connection details, DSNs, credentials, or SQL errors.
            return self::failure('database_unavailable');
        }
    }

    /** Select the default Portal3 connection only for local development checks. */
    private function authorizedReadConnection(): Connection
    {
        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        if (!in_array($environment, ['dev', 'development', 'local'], true)) {
            throw new \RuntimeException('Raw SQL reads are restricted to local development checks.');
        }

        $connection = Capsule::connection();
        $config = $connection->getConfig();
        $host = strtolower(trim((string) ($config['host'] ?? '')));
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || ($config['database'] ?? null) !== 'bd_admongas'
            || (int) ($config['port'] ?? 3306) !== 3306) {
            throw new \RuntimeException('Raw read target is not the approved local development database.');
        }

        $identity = $connection->selectOne(
            'SELECT DATABASE() AS schema_name, @@hostname AS server_hostname, @@port AS server_port'
        );
        if (($identity->schema_name ?? null) !== 'bd_admongas'
            || strtolower((string) ($identity->server_hostname ?? '')) !== 'fedora'
            || (int) ($identity->server_port ?? 0) !== 3306) {
            throw new \RuntimeException('Raw read server identity did not match the approved local target.');
        }

        return $connection;
    }

    /** @return array{0:string,1:array<int,int|string>} */
    private static function whereClause(
        int $canonicalLocalityId,
        string $fromUtc,
        string $toUtc,
        ?int $workerId,
        string $captureMethod = '',
        string $processingStatus = ''
    ): array
    {
        $where = '(punches.id_estacion_resuelta = ? AND '
            . '((punches.ocurrido_at_utc >= ? AND punches.ocurrido_at_utc < ?) '
            . 'OR (punches.ocurrido_at_utc IS NULL AND punches.recibido_at_utc >= ? AND punches.recibido_at_utc < ?)) '
            . 'AND punches.id = (SELECT MIN(duplicate.id) FROM ' . self::TABLE . ' AS duplicate '
            . 'WHERE duplicate.event_uuid = punches.event_uuid '
            . 'AND duplicate.id_estacion_resuelta = punches.id_estacion_resuelta))';
        $bindings = [$canonicalLocalityId, $fromUtc, $toUtc, $fromUtc, $toUtc];
        if ($workerId !== null) {
            $where .= ' AND punches.id_personal_resuelto = ?';
            $bindings[] = $workerId;
        }
        if ($captureMethod !== '') {
            $where .= ' AND punches.metodo_captura = ?';
            $bindings[] = $captureMethod;
        }
        if ($processingStatus !== '') {
            $where .= ' AND punches.processing_status = ?';
            $bindings[] = $processingStatus;
        }
        return [$where, $bindings];
    }

    /** @return array{0:string,1:array<int,string>} */
    private static function searchClause(string $search, string $authorizedLocalityName): array
    {
        if ($search === '') {
            return ['', []];
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
        $pattern = '%' . $escaped . '%';
        $visibleCollaboratorLabel = "CASE WHEN punches.id_personal_resuelto IS NULL THEN 'Colaborador no identificado' ELSE COALESCE((SELECT CASE WHEN TRIM(COALESCE(person.nombre_completo, '')) <> '' AND TRIM(person.nombre_completo) <> 'Nombre no disponible' THEN CONCAT(TRIM(person.nombre_completo), ' · ID interno ', person.id) ELSE CONCAT('Colaborador identificado · ID interno ', person.id) END FROM op_rh_personal AS person WHERE person.id = punches.id_personal_resuelto LIMIT 1), CONCAT('Colaborador identificado · ID interno ', punches.id_personal_resuelto)) END";
        $columns = [
            'punches.event_uuid',
            'CAST(punches.id_dispositivo AS CHAR)',
            'CAST(punches.id_personal_resuelto AS CHAR)',
            'punches.source_timestamp_raw',
            'punches.source_timezone_reported',
            'punches.ocurrido_at_utc',
            'punches.recibido_at_utc',
            $visibleCollaboratorLabel,
            "CASE punches.metodo_captura WHEN 'BIOMETRIC' THEN 'Registro biométrico' WHEN 'PIN' THEN 'Captura con PIN' WHEN 'CARD' THEN 'Captura con tarjeta' WHEN 'NFC' THEN 'Captura mediante NFC' WHEN 'MOBILE' THEN 'Captura móvil' WHEN 'WEB_TERMINAL' THEN 'Terminal web' WHEN 'TEMPORARY_CODE' THEN 'Código temporal' WHEN 'WEB_CAPTURE' THEN 'Captura web' WHEN 'PORTAL_TELEGRAM_VERIFIED' THEN 'Captura en Portal mediante Telegram' ELSE 'No disponible' END",
            "CASE punches.processing_status WHEN 'RECEIVED' THEN 'Marcación recibida' WHEN 'PENDING_MATCH' THEN 'Identificación pendiente' WHEN 'REJECTED' THEN 'Marcación rechazada' ELSE 'Estado no disponible' END",
        ];
        $predicates = array_map(static fn(string $column): string => $column . " LIKE ? ESCAPE '!'", $columns);
        $bindings = array_fill(0, count($predicates), $pattern);
        $predicates[] = '? LIKE ? ESCAPE \'!\'';
        $bindings[] = $authorizedLocalityName;
        $bindings[] = $pattern;
        return [' AND (' . implode(' OR ', $predicates) . ')', $bindings];
    }

    private static function isUtcTimestamp(string $value): bool
    {
        return self::parseUtcTimestamp($value) !== null;
    }

    private static function parseUtcTimestamp(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value)) {
            return null;
        }
        $utc = new \DateTimeZone('UTC');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $utc);
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false
            || $parsed->format('Y-m-d H:i:s') !== $value
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }
        return $parsed;
    }

    /** @return array{ok:false,error:array{kind:string}} */
    private static function failure(string $kind): array
    {
        return ['ok' => false, 'error' => ['kind' => $kind]];
    }

    /** Validate the canonical locality ID; raw PunchEvents persist this same locality namespace. */
    private static function resolveCanonicalLocalityId(Connection $connection, int $workplaceId): ?int
    {
        if ($workplaceId < 1) {
            return null;
        }
        $localities = $connection->select(
            'SELECT id FROM op_rh_localidades WHERE id = ? LIMIT 2',
            [$workplaceId]
        );
        if (count($localities) !== 1 || (int) ($localities[0]->id ?? 0) !== $workplaceId) {
            return null;
        }
        return $workplaceId;
    }
}

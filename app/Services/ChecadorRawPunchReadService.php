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
 * user and requested station. Production use is deliberately disabled until
 * a separately configured, least-privilege read-only DB connection exists.
 */
final class ChecadorRawPunchReadService
{
    public const DEFAULT_PAGE_SIZE = 50;
    public const MAX_PAGE_SIZE = 200;
    public const DATATABLE_PAGE_SIZES = [10, 25, 50, 100];
    public const MAX_EXPORT_RECORDS = 100000;
    private const MAX_RANGE_SECONDS = 2678400;
    private const MAX_OFFSET = 100000;
    private const MAX_SEARCH_CHARACTERS = 100;
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
        string $direction
    ): array {
        $search = trim($search);
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
            || !in_array($orderColumn, [1, 2, 3, 4], true)
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
            $connection = $this->developmentConnection();
            [$baseWhere, $baseBindings] = self::whereClause(
                $authorizedLocalityId,
                $fromUtc,
                $toUtc,
                $canonicalWorkerId
            );
            $totalRow = $connection->selectOne(
                'SELECT COUNT(*) AS total_records FROM ' . self::TABLE . ' WHERE ' . $baseWhere,
                $baseBindings
            );
            $recordsTotal = (int) ($totalRow->total_records ?? -1);
            if ($recordsTotal < 0) {
                return self::failure('database_unavailable');
            }

            [$searchWhere, $searchBindings] = self::searchClause($search);
            $filteredWhere = $baseWhere . $searchWhere;
            $filteredBindings = array_merge($baseBindings, $searchBindings);
            $filteredRow = $search === ''
                ? $totalRow
                : $connection->selectOne(
                    'SELECT COUNT(*) AS total_records FROM ' . self::TABLE . ' WHERE ' . $filteredWhere,
                    $filteredBindings
                );
            $recordsFiltered = (int) ($filteredRow->total_records ?? -1);
            if ($recordsFiltered < 0) {
                return self::failure('database_unavailable');
            }

            $orderBy = [
                1 => 'recibido_at_utc',
                2 => 'id_personal_resuelto',
                3 => 'metodo_captura',
                4 => 'processing_status',
            ][$orderColumn];
            $sql = 'SELECT id, event_uuid, id_dispositivo, id_estacion_resuelta, '
                . 'id_personal_resuelto, source_timestamp_raw, source_timezone_reported, '
                . 'ocurrido_at_utc, recibido_at_utc, metodo_captura, processing_status '
                . 'FROM ' . self::TABLE . ' WHERE ' . $filteredWhere
                . ' ORDER BY ' . $orderBy . ' ' . strtoupper($direction)
                . ', recibido_at_utc ' . strtoupper($direction)
                . ', id ' . strtoupper($direction)
                . ' LIMIT ? OFFSET ?';
            $rows = $connection->select($sql, array_merge($filteredBindings, [$length, $start]));
            $events = array_map(static fn(object $row): array => (array) $row, $rows);

            foreach ($events as $event) {
                if ((int) ($event['id_estacion_resuelta'] ?? 0) !== $authorizedLocalityId) {
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
                'timezone_status' => 'UNRESOLVED; UTC ONLY',
                'consistency' => 'LIVE_NON_SNAPSHOT',
            ];
        } catch (Throwable) {
            return self::failure('database_unavailable');
        }
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
            $connection = $this->developmentConnection();
            [$where, $bindings] = self::whereClause($authorizedLocalityId, $fromUtc, $toUtc, $canonicalWorkerId);
            $count = $connection->selectOne(
                'SELECT COUNT(*) AS total_records FROM ' . self::TABLE . ' WHERE ' . $where,
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
            $sql = 'SELECT id, event_uuid, id_dispositivo, id_estacion_resuelta, '
                . 'id_personal_resuelto, source_timestamp_raw, source_timezone_reported, '
                . 'ocurrido_at_utc, recibido_at_utc, metodo_captura, processing_status '
                . 'FROM ' . self::TABLE . ' WHERE ' . $where
                . ' ORDER BY (ocurrido_at_utc IS NULL) ASC, ocurrido_at_utc ' . $direction
                . ', recibido_at_utc ' . $direction . ', id ' . $direction
                . ' LIMIT ? OFFSET ?';
            $rows = $connection->select($sql, array_merge($bindings, [$perPage, $offset]));
            $events = array_map(static fn(object $row): array => (array) $row, $rows);

            // Database filtering is authoritative; this second check prevents
            // a malformed/cross-station row from reaching either UI or CSV.
            foreach ($events as $event) {
                if ((int) ($event['id_estacion_resuelta'] ?? 0) !== $authorizedLocalityId) {
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
                'timezone_status' => 'UNRESOLVED; UTC ONLY',
                'consistency' => 'LIVE_NON_SNAPSHOT',
            ];
        } catch (Throwable) {
            // Do not leak connection details, DSNs, credentials, or SQL errors.
            return self::failure('database_unavailable');
        }
    }

    /**
     * A read through Portal3's general connection is allowed only for the
     * previously approved local development schema on the Fedora host.
     * Production/preproduction require a dedicated read-only connection.
     */
    private function developmentConnection(): Connection
    {
        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        if (!in_array($environment, ['dev', 'development', 'local'], true)) {
            throw new \RuntimeException('Development-only raw read is disabled.');
        }

        $connection = Capsule::connection();
        $config = $connection->getConfig();
        $host = strtolower(trim((string) ($config['host'] ?? '')));
        $database = (string) ($config['database'] ?? '');
        $port = (int) ($config['port'] ?? 3306);
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || $database !== 'bd_admongas'
            || $port !== 3306) {
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
    private static function whereClause(int $localityId, string $fromUtc, string $toUtc, ?int $workerId): array
    {
        $where = '(id_estacion_resuelta = ? AND '
            . '((ocurrido_at_utc >= ? AND ocurrido_at_utc < ?) '
            . 'OR (ocurrido_at_utc IS NULL AND recibido_at_utc >= ? AND recibido_at_utc < ?)))';
        $bindings = [$localityId, $fromUtc, $toUtc, $fromUtc, $toUtc];
        if ($workerId !== null) {
            $where .= ' AND id_personal_resuelto = ?';
            $bindings[] = $workerId;
        }
        return [$where, $bindings];
    }

    /** @return array{0:string,1:array<int,string>} */
    private static function searchClause(string $search): array
    {
        if ($search === '') {
            return ['', []];
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
        $pattern = '%' . $escaped . '%';
        $columns = [
            'event_uuid',
            'CAST(id_dispositivo AS CHAR)',
            'CAST(id_personal_resuelto AS CHAR)',
            'source_timestamp_raw',
            'source_timezone_reported',
            'ocurrido_at_utc',
            'recibido_at_utc',
            "CASE WHEN id_personal_resuelto IS NULL THEN 'Colaborador no identificado' ELSE 'Colaborador identificado' END",
            "CASE metodo_captura WHEN 'BIOMETRIC' THEN 'Registro biométrico' WHEN 'PIN' THEN 'Captura con PIN' WHEN 'CARD' THEN 'Captura con tarjeta' WHEN 'NFC' THEN 'Captura mediante NFC' WHEN 'MOBILE' THEN 'Captura móvil' WHEN 'WEB_TERMINAL' THEN 'Terminal web' WHEN 'TEMPORARY_CODE' THEN 'Código temporal' WHEN 'WEB_CAPTURE' THEN 'Captura web' WHEN 'PORTAL_TELEGRAM_VERIFIED' THEN 'Captura en Portal mediante Telegram' ELSE 'No disponible' END",
            "CASE processing_status WHEN 'RECEIVED' THEN 'Marcación recibida' WHEN 'PENDING_MATCH' THEN 'Identificación pendiente' ELSE 'Estado no disponible' END",
        ];
        $predicates = array_map(static fn(string $column): string => $column . " LIKE ? ESCAPE '!'", $columns);
        return [' AND (' . implode(' OR ', $predicates) . ')', array_fill(0, count($predicates), $pattern)];
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
}

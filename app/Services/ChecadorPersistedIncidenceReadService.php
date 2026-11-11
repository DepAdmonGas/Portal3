<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

final class ChecadorPersistedIncidenceReadService
{
    private const MAX_DATE_RANGE_DAYS = 93;

    private ChecadorDailyReadService $dailyReader;

    public function __construct(?ChecadorDailyReadService $dailyReader = null)
    {
        $this->dailyReader = $dailyReader ?? new ChecadorDailyReadService();
    }

    /**
     * Contractual incidence types in Checador V2.
     *
     * @return array<string, string>
     */
    public static function getContractualTypes(): array
    {
        return [
            'VACATION' => 'Vacaciones',
            'PERMISSION' => 'Permiso',
            'DISABILITY' => 'Incapacidad',
            'AUTHORIZED_LATENESS' => 'Retardo autorizado',
            'AUTHORIZED_EARLY_DEPARTURE' => 'Salida anticipada autorizada',
            'OFFICIAL_MISSION' => 'Comisión oficial',
            'PUNCH_ATTENDANCE' => 'Incidencia de marcación',
            'JUSTIFICATION' => 'Justificación',
        ];
    }

    /**
     * Contractual incidence statuses in Checador V2.
     *
     * @return array<string, string>
     */
    public static function getContractualStatuses(): array
    {
        return [
            'ACTIVE' => 'Activa',
            'RESOLVED' => 'Resuelta',
            'AUTHORIZED' => 'Autorizada',
            'REJECTED' => 'Rechazada',
            'CANCELLED' => 'Cancelada',
            'REVOKED' => 'Revocada',
        ];
    }

    /**
     * Returns visual badge metadata (label and CSS classes) for a given status.
     *
     * @return array{label: string, class: string}
     */
    public static function getStatusBadge(string $status): array
    {
        return match (strtoupper(trim($status))) {
            'ACTIVE' => [
                'label' => 'Activa',
                'class' => 'badge bg-success-subtle text-success',
            ],
            'AUTHORIZED' => [
                'label' => 'Autorizada',
                'class' => 'badge bg-success-subtle text-success',
            ],
            'RESOLVED' => [
                'label' => 'Resuelta',
                'class' => 'badge bg-info-subtle text-info',
            ],
            'PENDING', 'PENDING_REVIEW', 'PENDING_APPROVAL', 'REVIEW_REQUIRED' => [
                'label' => 'Pendiente de revisión',
                'class' => 'badge bg-warning-subtle text-warning',
            ],
            'REJECTED' => [
                'label' => 'Rechazada',
                'class' => 'badge bg-danger-subtle text-danger',
            ],
            'CANCELLED' => [
                'label' => 'Cancelada',
                'class' => 'badge bg-danger-subtle text-danger',
            ],
            'REVOKED' => [
                'label' => 'Revocada',
                'class' => 'badge bg-danger-subtle text-danger',
            ],
            'SUPERSEDED' => [
                'label' => 'Sustituida',
                'class' => 'badge bg-secondary-subtle text-secondary',
            ],
            default => [
                'label' => $status !== '' ? $status : 'No disponible',
                'class' => 'badge bg-secondary-subtle text-secondary',
            ],
        };
    }

    /**
     * Returns human-readable localized label for an incidence type.
     */
    public static function getTypeLabel(string $type): string
    {
        return match (strtoupper(trim($type))) {
            'VACATION' => 'Vacaciones',
            'PERMISSION' => 'Permiso',
            'DISABILITY', 'INCAPACITY' => 'Incapacidad',
            'AUTHORIZED_LATENESS' => 'Retardo autorizado',
            'AUTHORIZED_EARLY_DEPARTURE' => 'Salida anticipada autorizada',
            'OFFICIAL_MISSION' => 'Comisión oficial',
            'PUNCH_ATTENDANCE' => 'Incidencia de marcación',
            'JUSTIFICATION' => 'Justificación',
            default => $type !== '' ? $type : 'Tipo no disponible',
        };
    }

    /**
     * Format a UTC timestamp into 12-hour local time: "07:00 am", "04:00 pm".
     */
    public static function format12hTime(?string $utcTimestamp, ?string $timezone = 'America/Mexico_City'): string
    {
        if ($utcTimestamp === null || trim($utcTimestamp) === '') {
            return '--';
        }
        try {
            $tz = new DateTimeZone($timezone ?: 'America/Mexico_City');
            $dt = new DateTimeImmutable($utcTimestamp, new DateTimeZone('UTC'));
            return strtolower($dt->setTimezone($tz)->format('h:i a'));
        } catch (Throwable) {
            return '--';
        }
    }

    /**
     * Format a time string ("07:00:00" or "16:30") into 12-hour: "07:00 am", "04:30 pm".
     */
    public static function format12hTimeString(?string $timeString): string
    {
        if ($timeString === null || trim($timeString) === '') {
            return '--';
        }
        try {
            $dt = DateTimeImmutable::createFromFormat('!H:i:s', $timeString)
                ?: DateTimeImmutable::createFromFormat('!H:i', $timeString);
            if ($dt !== false) {
                return strtolower($dt->format('h:i a'));
            }
        } catch (Throwable) {
        }
        return '--';
    }

    /**
     * Format a date string "YYYY-MM-DD" into "DD/MM/YYYY".
     */
    public static function formatCivilDate(?string $dateString): string
    {
        if ($dateString === null || trim($dateString) === '') {
            return '--';
        }
        try {
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', trim($dateString));
            if ($dt !== false) {
                return $dt->format('d/m/Y');
            }
        } catch (Throwable) {
        }
        return (string) $dateString;
    }

    /**
     * Read persisted administrative incidences through the API-owned C02 boundary.
     * This read never evaluates or mutates an incidence.
     *
     * @return array{
     *     ok: bool,
     *     incidences: array<int, array<string, mixed>>,
     *     total: int,
     *     error?: string
     * }
     */
    public function getPersistedIncidences(
        int $workplaceId,
        string $dateFrom,
        string $dateTo,
        ?int $workerId = null,
        ?string $type = null,
        ?string $status = null,
        ?\App\Models\Usuario $user = null
    ): array {
        if ($workplaceId < 1) return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'workplace_invalid'];
        if (!self::isValidDate($dateFrom) || !self::isValidDate($dateTo)) return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'dates_invalid'];
        if ($user === null) return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'authenticated_user_required'];
        $page = 1; $all = []; $expectedTotal = null; $projectionStatus = 'COMPLETE'; $punchCache = [];
        do {
            $result = $this->dailyReader->readIncidenceCollectionPage($user, [
                'workplace_id' => $workplaceId, 'from' => $dateFrom, 'to' => $dateTo,
                'worker_id' => $workerId, 'category' => $type, 'status' => $status,
                'page' => $page, 'per_page' => 200,
            ]);
            if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
                return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => $result['error']['kind'] ?? 'api_unavailable'];
            }
            $data = $result['data']; $pagination = $data['pagination'];
            $total = (int)$pagination['total_records'];
            if ($expectedTotal !== null && $expectedTotal !== $total) return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'partial_result'];
            $expectedTotal = $total;
            if (($data['projection_status'] ?? '') === 'PARTIAL') $projectionStatus = 'PARTIAL';
            foreach ($data['incidences'] as $item) {
                $start = (string)$item['date_range']['start_date']; $end = (string)$item['date_range']['end_date'];
                if (($item['detail_source'] ?? null) !== 'P3G_INCIDENCE_DETAIL_ENDPOINT') {
                    return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'api_unavailable'];
                }
                $detailResult = $this->dailyReader->readIncidence($user, (string)$item['uuid']);
                if (!($detailResult['ok'] ?? false) || !is_array($detailResult['data'] ?? null)) {
                    return ['ok' => false, 'incidences' => [], 'total' => 0,
                        'error' => $detailResult['error']['kind'] ?? 'api_unavailable'];
                }
                $detail = $detailResult['data'];
                $detailRange = $detail['date_range'] ?? null;
                $detailRevisions = $detail['revisions'] ?? null;
                $currentRevision = (int)($item['current_revision'] ?? 0);
                if (($detail['uuid_incidencia'] ?? null) !== $item['uuid']
                    || (int)($detail['worker_id'] ?? 0) !== (int)$item['worker_id']
                    || (int)($detail['workplace_id'] ?? 0) !== $workplaceId
                    || !is_array($detailRange)
                    || ($detailRange['start_date'] ?? null) !== $start || ($detailRange['end_date'] ?? null) !== $end
                    || (int)($detail['current_revision'] ?? 0) !== $currentRevision
                    || ($detail['current_status'] ?? null) !== ($item['current_status'] ?? null)
                    || !is_array($detailRevisions) || count($detailRevisions) !== $currentRevision
                    || !is_array($detail['check_reference'] ?? null) && (string)($item['category'] ?? '') === 'PUNCH_ATTENDANCE') {
                    return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'partial_result'];
                }
                foreach ($detailRevisions as $index => $revision) {
                    if (!is_array($revision) || (int)($revision['revision_number'] ?? 0) !== $index + 1) {
                        return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'partial_result'];
                    }
                }
                $currentRevisionDetail = $detailRevisions[$currentRevision - 1] ?? null;
                if (!is_array($currentRevisionDetail)) {
                    return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'partial_result'];
                }
                $punchResult = $this->readContextualPunches(
                    $user, $workplaceId, (int)$item['worker_id'], max($start, $dateFrom), min($end, $dateTo), $punchCache
                );
                $punchProjectionState = ($punchResult['ok'] ?? false)
                    ? (($punchResult['punches'] ?? []) === [] ? 'EMPTY' : 'COMPLETE')
                    : 'UNAVAILABLE';
                if ($punchProjectionState === 'UNAVAILABLE') $projectionStatus = 'PARTIAL';
                $timezone = is_string($item['timezone'] ?? null) && trim($item['timezone']) !== '' ? $item['timezone'] : null;
                $localTimestamp = static function (mixed $utc) use ($timezone): string {
                    if (!is_string($utc) || trim($utc) === '') return '--';
                    try {
                        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
                        if ($timezone === null) return $date->format('d/m/Y H:i') . ' UTC';
                        $date = $date->setTimezone(new DateTimeZone($timezone));
                        return $date->format('d/m/Y') . ' ' . strtolower($date->format('h:i a'));
                    } catch (Throwable) { return '--'; }
                };
                $revisions = [];
                foreach ($detailRevisions as $revision) {
                    if (!is_array($revision)) continue;
                    $revisionStatus = (string)($revision['status'] ?? '');
                    $revisions[] = [
                        'revision_numero' => (int)($revision['revision_number'] ?? 0),
                        'estado_revision' => $revisionStatus, 'status_badge' => self::getStatusBadge($revisionStatus),
                        'motivo' => $revision['reason'] ?? null, 'folio_evidencia' => $revision['evidence_reference'] ?? null,
                        'creado_por_usuario_id' => $revision['created_by_user_id'] ?? null,
                        'aprobado_por_usuario_id' => $revision['approved_by_user_id'] ?? null,
                        'creado_at_utc' => $revision['created_at_utc'] ?? '',
                        'creado_at_local' => $localTimestamp($revision['created_at_utc'] ?? null),
                    ];
                }
                $applications = [];
                foreach ($item['applications'] as $application) {
                    if (!is_array($application)) continue;
                    $applications[] = [
                        'fecha_jornada_local' => (string)($application['work_date'] ?? ''),
                        'fecha_display' => self::formatCivilDate((string)($application['work_date'] ?? '')),
                        'estado_aplicacion' => (string)($application['application_status'] ?? ''),
                    ];
                }
                $evaluations = [];
                foreach ($item['evaluations'] as $evaluation) {
                    if (!is_array($evaluation)) continue;
                    $evaluations[] = [
                        'revision_numero' => (int)($evaluation['revision_number'] ?? 0),
                        'version_algoritmo' => (string)($evaluation['algorithm_version'] ?? ''),
                        'version_politica' => (string)($evaluation['policy_version'] ?? ''),
                        'estado_evaluacion' => (string)($evaluation['evaluation_status'] ?? ''),
                        'estado_revision_humana' => (string)($evaluation['human_review_status'] ?? ''),
                        'fingerprint' => (string)($evaluation['fingerprint'] ?? ''),
                        'creado_at_local' => $localTimestamp($evaluation['created_at_utc'] ?? null),
                    ];
                }
                $punches = [];
                foreach (($punchResult['punches'] ?? []) as $punch) {
                    if (!is_array($punch)) continue;
                    $punches[] = [
                        'event_uuid' => (string)($punch['event_uuid'] ?? ''),
                        'time_12h' => (string)($punch['time_local'] ?? '--'),
                        'fecha_local' => (string)($punch['local_date'] ?? ''),
                        'dispositivo_id' => $punch['device_id'] ?? null,
                        'metodo_captura' => (string)($punch['capture_method'] ?? ''),
                        'source_raw' => (string)($punch['source_timestamp_raw'] ?? ''),
                        'relation' => (string)($punch['relation'] ?? 'CONTEXTUAL_DATE_OVERLAP'),
                    ];
                }
                $currentStatus = (string)$item['current_status'];
                $all[] = [
                    'uuid' => (string)$item['uuid'], 'worker_id' => (int)$item['worker_id'],
                    'worker_name' => (string)$item['worker_name'], 'employee_number' => $item['employee_number'] ?? null,
                    'workplace_id' => (int)$item['workplace_id'], 'workplace_name' => (string)$item['workplace_name'],
                    'type' => (string)$item['category'], 'type_label' => self::getTypeLabel((string)$item['category']),
                    'scope' => (string)$item['scope'], 'date_start' => $start, 'date_end' => $end,
                    'date_display' => $start === $end ? self::formatCivilDate($start) : self::formatCivilDate($start) . ' – ' . self::formatCivilDate($end),
                    'status' => $currentStatus, 'status_badge' => self::getStatusBadge($currentStatus),
                    'current_revision' => $currentRevision, 'motivo' => $currentRevisionDetail['reason'] ?? null,
                    'folio_evidencia' => $currentRevisionDetail['evidence_reference'] ?? null,
                    'shift_name' => (string)($item['shift_name'] ?? 'Sin turno vinculado'),
                    'shift_hours' => (string)($item['shift_hours'] ?? '--'),
                    'created_by_user_id' => $currentRevisionDetail['created_by_user_id'] ?? null,
                    'approved_by_user_id' => $currentRevisionDetail['approved_by_user_id'] ?? null,
                    'created_at_utc' => (string)($item['created_at_utc'] ?? ''),
                    'created_at_local' => $localTimestamp($item['created_at_utc'] ?? null),
                    'revisions' => $revisions, 'applications' => $applications, 'evaluations' => $evaluations,
                    'punches' => $punches, 'check_reference' => $detail['check_reference'] ?? null,
                    'punch_projection_state' => $punchProjectionState,
                    'timezone' => $timezone, 'projection_state' => $item['projection_state'] ?? 'PARTIAL',
                    'modal_id' => 'checador-incidence-detail-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$item['uuid']),
                ];
                if (($item['projection_state'] ?? '') !== 'COMPLETE') $projectionStatus = 'PARTIAL';
            }
            $hasMore = (bool)$pagination['has_more']; $page++;
            if ($page > 500) return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'pagination_limit'];
        } while ($hasMore);
        if ($expectedTotal !== count($all)) return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'partial_result'];
        return ['ok' => true, 'incidences' => $all, 'total' => count($all), 'projection_status' => $all === [] ? 'EMPTY' : $projectionStatus];
    }

    public function readPersistedIncidences(
        int $workplaceId, string $dateFrom, string $dateTo, ?int $workerId = null,
        ?string $type = null, ?string $status = null, ?\App\Models\Usuario $user = null
    ): array {
        return $this->getPersistedIncidences($workplaceId, $dateFrom, $dateTo, $workerId, $type, $status, $user);
    }

    /** Read contextual raw events through the existing authorized M05 API boundary. */
    private function readContextualPunches(
        \App\Models\Usuario $user,
        int $workplaceId,
        int $workerId,
        string $from,
        string $to,
        array &$cache
    ): array {
        if ($from > $to) return ['ok' => true, 'punches' => []];
        $reader = $this->dailyReader;
        $punches = [];
        for ($date = new DateTimeImmutable($from); $date <= new DateTimeImmutable($to); $date = $date->modify('+1 day')) {
            $localDate = $date->format('Y-m-d');
            $cacheKey = $workplaceId . ':' . $workerId . ':' . $localDate;
            if (!array_key_exists($cacheKey, $cache)) {
                $timezone = $reader->resolveWorkplaceLocalDay($user, $workplaceId, $localDate);
                if (!($timezone['ok'] ?? false) || !is_array($timezone['data'] ?? null)) {
                    $cache[$cacheKey] = ['ok' => false, 'punches' => []];
                } else {
                    $datePunches = [];
                    $page = 1;
                    $expectedTotal = null;
                    $expectedPages = null;
                    do {
                        $read = $reader->readObservedPunches($user, $workplaceId, $timezone['data'], $page, 200);
                        if (!($read['ok'] ?? false) || !is_array($read['pagination'] ?? null)) {
                            $datePunches = null;
                            break;
                        }
                        $pagination = $read['pagination'];
                        $total = (int)($pagination['total_records'] ?? -1);
                        $totalPages = (int)($pagination['total_pages'] ?? -1);
                        if ($total < 0 || $totalPages < 0
                            || ($expectedTotal !== null && $expectedTotal !== $total)
                            || ($expectedPages !== null && $expectedPages !== $totalPages)
                            || (int)($pagination['current_page'] ?? 0) !== $page) {
                            $datePunches = null;
                            break;
                        }
                        $expectedTotal = $total;
                        $expectedPages = $totalPages;
                        foreach (($read['events'] ?? []) as $event) {
                            if (!is_array($event) || (int)($event['id_personal_resuelto'] ?? 0) !== $workerId) continue;
                            $utc = $event['ocurrido_at_utc'] ?? null;
                            if (!is_string($utc) || trim($utc) === '') continue;
                            try {
                                $local = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
                                    ->setTimezone(new DateTimeZone((string)$timezone['data']['timezone']));
                            } catch (Throwable) { continue; }
                            if ($local->format('Y-m-d') !== $localDate) continue;
                            $datePunches[] = [
                                'event_uuid' => (string)($event['event_uuid'] ?? ''),
                                'occurred_at_utc' => $utc,
                                'time_local' => $local->format('h:i a'),
                                'local_date' => $localDate,
                                'device_id' => $event['id_dispositivo'] ?? null,
                                'capture_method' => (string)($event['metodo_captura'] ?? ''),
                                'source_timestamp_raw' => (string)($event['source_timestamp_raw'] ?? ''),
                                'relation' => 'CONTEXTUAL_DATE_OVERLAP',
                            ];
                        }
                        $page++;
                        if ($page > 501) { $datePunches = null; break; }
                    } while ($expectedPages !== null && $page <= $expectedPages);
                    $cache[$cacheKey] = $datePunches === null
                        ? ['ok' => false, 'punches' => []]
                        : ['ok' => true, 'punches' => $datePunches];
                }
            }
            if (!($cache[$cacheKey]['ok'] ?? false)) return ['ok' => false, 'punches' => []];
            foreach (($cache[$cacheKey]['punches'] ?? []) as $punch) $punches[] = $punch;
        }
        usort($punches, static fn(array $left, array $right): int => strcmp((string)$left['occurred_at_utc'], (string)$right['occurred_at_utc']));
        return ['ok' => true, 'punches' => $punches];
    }

    /** Preserved SQL implementation as a non-routed reference; active callers use API V2 above. */
    private function getPersistedIncidencesSqlReference(
        int $workplaceId,
        string $dateFrom,
        string $dateTo,
        ?int $workerId = null,
        ?string $type = null,
        ?string $status = null
    ): array {
        if ($workplaceId < 1) {
            return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'workplace_invalid'];
        }

        if (!self::isValidDate($dateFrom) || !self::isValidDate($dateTo)) {
            return ['ok' => false, 'incidences' => [], 'total' => 0, 'error' => 'dates_invalid'];
        }

        if ($dateTo < $dateFrom) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $fromDt = new DateTimeImmutable($dateFrom);
        $toDt = new DateTimeImmutable($dateTo);
        $daysDiff = (int) $fromDt->diff($toDt)->days;
        if ($daysDiff > self::MAX_DATE_RANGE_DAYS) {
            $toDt = $fromDt->modify('+' . self::MAX_DATE_RANGE_DAYS . ' days');
            $dateTo = $toDt->format('Y-m-d');
        }

        try {
            $pdo = Capsule::connection()->getPdo();

            $sql = "
                SELECT
                    m.id AS master_id,
                    m.uuid_incidencia,
                    m.id_personal,
                    m.id_estacion,
                    m.tipo_incidencia,
                    m.alcance_incidencia,
                    m.fecha_inicio_local,
                    m.fecha_fin_local,
                    m.estado_actual,
                    m.revision_actual,
                    m.creado_at_utc AS master_creado_at_utc,
                    m.actualizado_at_utc AS master_actualizado_at_utc,
                    p.nombre_completo AS worker_name,
                    p.no_colaborador AS employee_number,
                    l.localidad AS station_name,
                    r.id AS revision_id,
                    r.revision_numero,
                    r.estado_revision,
                    r.motivo,
                    r.folio_evidencia,
                    r.creado_por_usuario_id,
                    r.aprobado_por_usuario_id,
                    r.creado_at_utc AS revision_creado_at_utc
                FROM op_rh_v2_incidencias_maestro m
                LEFT JOIN op_rh_v2_incidencias_revisiones r
                    ON r.id_incidencia_maestro = m.id AND r.revision_numero = m.revision_actual
                LEFT JOIN op_rh_personal p ON p.id = m.id_personal
                LEFT JOIN op_rh_localidades l ON l.id = m.id_estacion
                WHERE m.id_estacion = :workplace_id
                  AND m.fecha_inicio_local <= :date_to
                  AND m.fecha_fin_local >= :date_from
            ";

            $params = [
                ':workplace_id' => $workplaceId,
                ':date_from' => $dateFrom,
                ':date_to' => $dateTo,
            ];

            if ($workerId !== null && $workerId > 0) {
                $sql .= " AND m.id_personal = :worker_id";
                $params[':worker_id'] = $workerId;
            }

            if ($type !== null && trim($type) !== '') {
                $sql .= " AND m.tipo_incidencia = :type";
                $params[':type'] = trim($type);
            }

            if ($status !== null && trim($status) !== '') {
                $sql .= " AND m.estado_actual = :status";
                $params[':status'] = trim($status);
            }

            $sql .= " ORDER BY m.fecha_inicio_local DESC, p.nombre_completo ASC, m.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if ($rawRows === []) {
                return ['ok' => true, 'incidences' => [], 'total' => 0];
            }

            // Extract IDs for fetching details
            $masterIds = [];
            $revisionIds = [];
            $workerIds = [];
            $minDate = $dateFrom;
            $maxDate = $dateTo;

            foreach ($rawRows as $row) {
                $masterIds[] = (int) $row['master_id'];
                if (!empty($row['revision_id'])) {
                    $revisionIds[] = (int) $row['revision_id'];
                }
                $wId = (int) $row['id_personal'];
                if ($wId > 0 && !in_array($wId, $workerIds, true)) {
                    $workerIds[] = $wId;
                }
                if (!empty($row['fecha_inicio_local']) && $row['fecha_inicio_local'] < $minDate) {
                    $minDate = (string) $row['fecha_inicio_local'];
                }
                if (!empty($row['fecha_fin_local']) && $row['fecha_fin_local'] > $maxDate) {
                    $maxDate = (string) $row['fecha_fin_local'];
                }
            }

            $revisionsByMaster = $this->fetchRevisionsByMasterIds($pdo, $masterIds);
            $applicationsByRevision = $this->fetchApplicationsByRevisionIds($pdo, $revisionIds);

            // Collect all application IDs and programada IDs
            $appIds = [];
            $progIds = [];
            foreach ($applicationsByRevision as $apps) {
                foreach ($apps as $app) {
                    $appIds[] = (int) $app['id'];
                    if (!empty($app['id_jornada_programada'])) {
                        $progIds[] = (int) $app['id_jornada_programada'];
                    }
                }
            }

            $shiftsByProgId = $this->fetchShiftsByProgramadaIds($pdo, array_values(array_unique($progIds)));
            $evaluationsByAppId = $this->fetchEvaluationsByAppIds($pdo, array_values(array_unique($appIds)));
            $punchesByWorkerDate = $this->fetchPunchesByWorkersAndDates($pdo, $workerIds, $minDate, $maxDate);

            $formatted = [];
            foreach ($rawRows as $row) {
                $mId = (int) $row['master_id'];
                $rId = (int) ($row['revision_id'] ?? 0);
                $wId = (int) $row['id_personal'];

                $allRevisions = $revisionsByMaster[$mId] ?? [];
                $applications = $rId > 0 ? ($applicationsByRevision[$rId] ?? []) : [];

                // Resolve shift info from applications if linked
                $shiftInfo = null;
                $evaluationsList = [];
                foreach ($applications as $app) {
                    $jpId = (int) ($app['id_jornada_programada'] ?? 0);
                    if ($jpId > 0 && isset($shiftsByProgId[$jpId])) {
                        $shiftInfo = $shiftsByProgId[$jpId];
                    }
                    $apId = (int) ($app['id'] ?? 0);
                    if ($apId > 0 && isset($evaluationsByAppId[$apId])) {
                        foreach ($evaluationsByAppId[$apId] as $eval) {
                            $evaluationsList[] = $eval;
                        }
                    }
                }

                // Associate punches for this worker across the incidence dates
                $associatedPunches = [];
                $startDate = (string) ($row['fecha_inicio_local'] ?? '');
                $endDate = (string) ($row['fecha_fin_local'] ?? '');
                if ($wId > 0 && isset($punchesByWorkerDate[$wId])) {
                    foreach ($punchesByWorkerDate[$wId] as $punch) {
                        $pDate = (string) ($punch['fecha_local'] ?? '');
                        if ($pDate >= $startDate && $pDate <= $endDate) {
                            $associatedPunches[] = $punch;
                        }
                    }
                }

                // Date display: single date or date range
                $startDisplay = self::formatCivilDate($startDate);
                $endDisplay = self::formatCivilDate($endDate);
                $dateDisplay = ($startDate === $endDate || $endDate === '')
                    ? $startDisplay
                    : ($startDisplay . ' – ' . $endDisplay);

                // Clean motivo
                $rawMotivo = $row['motivo'] ?? null;
                $cleanMotivo = $rawMotivo !== null
                    ? preg_replace('/\n<!-- IDEMP:.*? -->$/s', '', (string) $rawMotivo)
                    : null;

                // Creation timestamp converted to local
                $createdUtc = $row['master_creado_at_utc'] ?? null;
                $createdAtLocal = '--';
                if (!empty($createdUtc)) {
                    try {
                        $dt = new DateTimeImmutable((string) $createdUtc, new DateTimeZone('UTC'));
                        $dtLocal = $dt->setTimezone(new DateTimeZone('America/Mexico_City'));
                        $createdAtLocal = $dtLocal->format('d/m/Y') . ' ' . strtolower($dtLocal->format('h:i a'));
                    } catch (Throwable) {
                        $createdAtLocal = (string) $createdUtc;
                    }
                }

                $formatted[] = [
                    'id' => $mId,
                    'uuid' => (string) $row['uuid_incidencia'],
                    'worker_id' => $wId,
                    'worker_name' => (string) ($row['worker_name'] ?? ('Personal ' . $wId)),
                    'employee_number' => !empty($row['employee_number']) ? (string) $row['employee_number'] : null,
                    'workplace_id' => (int) $row['id_estacion'],
                    'workplace_name' => (string) ($row['station_name'] ?? ('Localidad ' . $row['id_estacion'])),
                    'type' => (string) $row['tipo_incidencia'],
                    'type_label' => self::getTypeLabel((string) $row['tipo_incidencia']),
                    'scope' => (string) ($row['alcance_incidencia'] ?? 'WORKER'),
                    'date_start' => $startDate,
                    'date_end' => $endDate,
                    'date_display' => $dateDisplay,
                    'status' => (string) $row['estado_actual'],
                    'status_badge' => self::getStatusBadge((string) $row['estado_actual']),
                    'current_revision' => (int) ($row['revision_actual'] ?? 1),
                    'motivo' => $cleanMotivo,
                    'folio_evidencia' => !empty($row['folio_evidencia']) ? (string) $row['folio_evidencia'] : null,
                    'shift_name' => $shiftInfo['shift_name'] ?? 'Sin turno vinculado',
                    'shift_hours' => $shiftInfo['shift_hours'] ?? '--',
                    'created_by_user_id' => !empty($row['creado_por_usuario_id']) ? (int) $row['creado_por_usuario_id'] : null,
                    'approved_by_user_id' => !empty($row['aprobado_por_usuario_id']) ? (int) $row['aprobado_por_usuario_id'] : null,
                    'created_at_utc' => (string) ($row['master_creado_at_utc'] ?? ''),
                    'created_at_local' => $createdAtLocal,
                    'revisions' => $allRevisions,
                    'applications' => $applications,
                    'evaluations' => $evaluationsList,
                    'punches' => $associatedPunches,
                    'modal_id' => 'checador-incidence-detail-' . $mId,
                ];
            }

            return [
                'ok' => true,
                'incidences' => $formatted,
                'total' => count($formatted),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'incidences' => [],
                'total' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private static function isValidDate(string $date): bool
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', trim($date));
        return $d !== false && $d->format('Y-m-d') === trim($date);
    }

    /**
     * @param int[] $masterIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchRevisionsByMasterIds(\PDO $pdo, array $masterIds): array
    {
        if ($masterIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($masterIds), '?'));
        $sql = "
            SELECT
                r.id,
                r.id_incidencia_maestro,
                r.revision_numero,
                r.estado_revision,
                r.motivo,
                r.folio_evidencia,
                r.creado_por_usuario_id,
                r.aprobado_por_usuario_id,
                r.creado_at_utc
            FROM op_rh_v2_incidencias_revisiones r
            WHERE r.id_incidencia_maestro IN ($placeholders)
            ORDER BY r.id_incidencia_maestro ASC, r.revision_numero ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($masterIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $mId = (int) $row['id_incidencia_maestro'];
            $cleanMotivo = $row['motivo'] !== null
                ? preg_replace('/\n<!-- IDEMP:.*? -->$/s', '', (string) $row['motivo'])
                : null;

            $createdUtc = $row['creado_at_utc'] ?? null;
            $createdLocal = '--';
            if (!empty($createdUtc)) {
                try {
                    $dt = new DateTimeImmutable((string) $createdUtc, new DateTimeZone('UTC'));
                    $dtLocal = $dt->setTimezone(new DateTimeZone('America/Mexico_City'));
                    $createdLocal = $dtLocal->format('d/m/Y') . ' ' . strtolower($dtLocal->format('h:i a'));
                } catch (Throwable) {
                    $createdLocal = (string) $createdUtc;
                }
            }

            $grouped[$mId][] = [
                'id' => (int) $row['id'],
                'revision_numero' => (int) $row['revision_numero'],
                'estado_revision' => (string) $row['estado_revision'],
                'status_badge' => self::getStatusBadge((string) $row['estado_revision']),
                'motivo' => $cleanMotivo,
                'folio_evidencia' => !empty($row['folio_evidencia']) ? (string) $row['folio_evidencia'] : null,
                'creado_por_usuario_id' => !empty($row['creado_por_usuario_id']) ? (int) $row['creado_por_usuario_id'] : null,
                'aprobado_por_usuario_id' => !empty($row['aprobado_por_usuario_id']) ? (int) $row['aprobado_por_usuario_id'] : null,
                'creado_at_utc' => (string) ($row['creado_at_utc'] ?? ''),
                'creado_at_local' => $createdLocal,
            ];
        }

        return $grouped;
    }

    /**
     * @param int[] $revisionIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchApplicationsByRevisionIds(\PDO $pdo, array $revisionIds): array
    {
        if ($revisionIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($revisionIds), '?'));
        $sql = "
            SELECT
                a.id,
                a.id_incidencia_revision,
                a.id_jornada_programada,
                a.fecha_jornada_local,
                a.estado_aplicacion,
                a.creado_at_utc
            FROM op_rh_v2_incidencias_aplicaciones a
            WHERE a.id_incidencia_revision IN ($placeholders)
            ORDER BY a.id_incidencia_revision ASC, a.fecha_jornada_local ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($revisionIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $rId = (int) $row['id_incidencia_revision'];
            $grouped[$rId][] = [
                'id' => (int) $row['id'],
                'id_jornada_programada' => !empty($row['id_jornada_programada']) ? (int) $row['id_jornada_programada'] : null,
                'fecha_jornada_local' => (string) $row['fecha_jornada_local'],
                'fecha_display' => self::formatCivilDate((string) $row['fecha_jornada_local']),
                'estado_aplicacion' => (string) $row['estado_aplicacion'],
            ];
        }

        return $grouped;
    }

    /**
     * @param int[] $progIds
     * @return array<int, array{shift_name: string, shift_hours: string, tipo_jornada: string}>
     */
    private function fetchShiftsByProgramadaIds(\PDO $pdo, array $progIds): array
    {
        if ($progIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($progIds), '?'));
        $sql = "
            SELECT
                jp.id,
                jp.tipo_jornada,
                jp.scheduled_start_utc,
                jp.scheduled_end_utc,
                jp.timezone_local,
                tp.nombre AS shift_name
            FROM op_rh_v2_jornadas_programadas jp
            LEFT JOIN op_rh_v2_turnos_plantillas tp ON tp.id = jp.id_plantilla_origen
            WHERE jp.id IN ($placeholders)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($progIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $jpId = (int) $row['id'];
            $name = trim((string) ($row['shift_name'] ?? ''));
            $tz = (string) ($row['timezone_local'] ?: 'America/Mexico_City');
            $start12h = self::format12hTime($row['scheduled_start_utc'] ?? null, $tz);
            $end12h = self::format12hTime($row['scheduled_end_utc'] ?? null, $tz);

            $hours = ($start12h !== '--' && $end12h !== '--') ? ($start12h . ' – ' . $end12h) : '--';
            $fullShiftName = $name !== '' ? ($hours !== '--' ? "$name ($hours)" : $name) : ($hours !== '--' ? $hours : 'Turno sin nombre');

            $result[$jpId] = [
                'shift_name' => $fullShiftName,
                'shift_hours' => $hours,
                'tipo_jornada' => (string) ($row['tipo_jornada'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param int[] $appIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchEvaluationsByAppIds(\PDO $pdo, array $appIds): array
    {
        if ($appIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($appIds), '?'));
        $sql = "
            SELECT
                ei.id_incidencia_aplicacion,
                ea.id,
                ea.id_jornada_programada,
                ea.id_calculo_jornada,
                ea.revision_numero,
                ea.version_algoritmo_admin,
                ea.version_politica,
                ea.estado_evaluacion,
                ea.estado_revision_humana,
                ea.fingerprint_ejecucion,
                ea.creado_at_utc
            FROM op_rh_v2_evaluaciones_incidencias ei
            JOIN op_rh_v2_evaluaciones_administrativas ea ON ea.id = ei.id_evaluacion_administrativa
            WHERE ei.id_incidencia_aplicacion IN ($placeholders)
            ORDER BY ea.revision_numero DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($appIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $appId = (int) $row['id_incidencia_aplicacion'];
            $createdUtc = $row['creado_at_utc'] ?? null;
            $createdLocal = '--';
            if (!empty($createdUtc)) {
                try {
                    $dt = new DateTimeImmutable((string) $createdUtc, new DateTimeZone('UTC'));
                    $dtLocal = $dt->setTimezone(new DateTimeZone('America/Mexico_City'));
                    $createdLocal = $dtLocal->format('d/m/Y') . ' ' . strtolower($dtLocal->format('h:i a'));
                } catch (Throwable) {
                    $createdLocal = (string) $createdUtc;
                }
            }

            $grouped[$appId][] = [
                'id' => (int) $row['id'],
                'revision_numero' => (int) $row['revision_numero'],
                'version_algoritmo' => (string) $row['version_algoritmo_admin'],
                'version_politica' => (string) $row['version_politica'],
                'estado_evaluacion' => (string) $row['estado_evaluacion'],
                'estado_revision_humana' => (string) $row['estado_revision_humana'],
                'fingerprint' => (string) $row['fingerprint_ejecucion'],
                'creado_at_local' => $createdLocal,
            ];
        }

        return $grouped;
    }

    /**
     * @param int[] $workerIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function fetchPunchesByWorkersAndDates(\PDO $pdo, array $workerIds, string $minDate, string $maxDate): array
    {
        if ($workerIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($workerIds), '?'));
        // Include a 1-day safety margin in UTC for local date matching
        $utcStart = $minDate . ' 00:00:00';
        $utcEnd = $maxDate . ' 23:59:59';

        $sql = "
            SELECT
                mc.id,
                mc.id_personal_resuelto,
                mc.id_estacion_resuelta,
                mc.id_dispositivo,
                mc.event_uuid,
                mc.ocurrido_at_utc,
                mc.source_timestamp_raw,
                mc.metodo_captura
            FROM op_rh_v2_marcaciones_crudas mc
            WHERE mc.id_personal_resuelto IN ($placeholders)
              AND mc.ocurrido_at_utc BETWEEN ? AND ?
            ORDER BY mc.id_personal_resuelto ASC, mc.ocurrido_at_utc ASC
        ";

        $binds = array_merge($workerIds, [$utcStart, $utcEnd]);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($binds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        $tz = new DateTimeZone('America/Mexico_City');
        foreach ($rows as $row) {
            $wId = (int) $row['id_personal_resuelto'];
            $utc = (string) $row['ocurrido_at_utc'];
            $time12h = self::format12hTime($utc);
            $fechaLocal = '';
            try {
                $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
                $fechaLocal = $dt->setTimezone($tz)->format('Y-m-d');
            } catch (Throwable) {
            }

            $grouped[$wId][] = [
                'id' => (int) $row['id'],
                'dispositivo_id' => (int) $row['id_dispositivo'],
                'event_uuid' => (string) $row['event_uuid'],
                'time_12h' => $time12h,
                'fecha_local' => $fechaLocal,
                'metodo_captura' => (string) ($row['metodo_captura'] ?? 'BIO'),
                'source_raw' => (string) ($row['source_timestamp_raw'] ?? ''),
            ];
        }

        return $grouped;
    }
}

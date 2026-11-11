<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

final class ChecadorPersistedJourneyReadService
{
    private const MAX_DATE_RANGE_DAYS = 62;

    /**
     * Read already persisted Journey evaluations through the API-owned C01 boundary.
     * This method never evaluates attendance or writes a snapshot.
     *
     * @return array{
     *     ok: bool,
     *     journeys: array<int, array<string, mixed>>,
     *     total: int,
     *     error?: string
     * }
     */
    public function getPersistedJourneys(
        int $workplaceId,
        string $dateFrom,
        string $dateTo,
        ?int $workerId = null,
        ?string $status = null,
        ?\App\Models\Usuario $user = null
    ): array {
        if ($workplaceId < 1) return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => 'workplace_invalid'];
        if (!self::isValidDate($dateFrom) || !self::isValidDate($dateTo)) return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => 'dates_invalid'];
        if ($user === null) {
            return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => 'authenticated_user_required'];
        }
        $page = 1;
        $all = [];
        $expectedTotal = null;
        $projectionStatus = 'COMPLETE';
        $evaluationCoverage = null;
        do {
            $result = (new ChecadorDailyReadService())->readPersistedJourneyPage($user, [
                'workplace_id' => $workplaceId, 'from' => $dateFrom, 'to' => $dateTo,
                'worker_id' => $workerId, 'status' => $status, 'page' => $page, 'per_page' => 200,
            ]);
            if (!($result['ok'] ?? false) || !is_array($result['data'] ?? null)) {
                return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => $result['error']['kind'] ?? 'api_unavailable'];
            }
            $data = $result['data'];
            $pagination = $data['pagination'];
            $pageCoverage = $data['evaluation_coverage'];
            if ($evaluationCoverage !== null && $evaluationCoverage !== $pageCoverage) {
                return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => 'partial_result'];
            }
            $evaluationCoverage = $pageCoverage;
            if (($data['projection_status'] ?? '') === 'PARTIAL') $projectionStatus = 'PARTIAL';
            $total = (int)$pagination['total_records'];
            if ($expectedTotal !== null && $expectedTotal !== $total) {
                return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => 'partial_result'];
            }
            $expectedTotal = $total;
            foreach ($data['journeys'] as $item) {
                $timezone = is_string($item['timezone'] ?? null) && trim($item['timezone']) !== '' ? $item['timezone'] : null;
                $statusValue = (string)$item['status'];
                $segments = [];
                foreach ($item['segments'] as $segment) {
                    if (!is_array($segment)) continue;
                    $segments[] = $segment;
                }
                $punches = [];
                foreach ($item['raw_punches'] as $punch) {
                    if (!is_array($punch)) continue;
                    $punches[] = [
                        'rol_marcacion' => $punch['rol_marcacion'] ?? null,
                        'event_uuid' => $punch['event_uuid'] ?? null,
                        'ocurrido_at_utc' => $punch['ocurrido_at_utc'] ?? null,
                        'source_timestamp_raw' => $punch['source_timestamp_raw'] ?? null,
                        'id_dispositivo' => $punch['id_dispositivo'] ?? null,
                        'metodo_captura' => $punch['metodo_captura'] ?? null,
                        'processing_status' => $punch['processing_status'] ?? null,
                    ];
                }
                $scheduledSeconds = is_int($item['scheduled_seconds'] ?? null) ? $item['scheduled_seconds'] : null;
                $workedSeconds = is_int($item['worked_seconds'] ?? null) ? $item['worked_seconds'] : null;
                $timezoneAvailable = $timezone !== null;
                $localTime = static function (mixed $utc, mixed $raw = null) use ($timezone): string {
                    if ($timezone === null || !is_string($utc) || trim($utc) === '') return '--';
                    try {
                        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
                        return strtolower($date->setTimezone(new DateTimeZone($timezone))->format('h:i a'));
                    } catch (Throwable) { return '--'; }
                };
                $scheduleType = strtoupper((string)($item['schedule_type'] ?? ''));
                $shift = trim((string)($item['shift_name'] ?? ''));
                $start = $localTime($item['scheduled_start_utc'] ?? null);
                $end = $localTime($item['scheduled_end_utc'] ?? null);
                if ($scheduleType === 'DAY_OFF') $shiftDisplay = 'Descanso programado';
                elseif ($scheduleType === 'UNSCHEDULED') $shiftDisplay = 'Sin horario programado';
                elseif ($timezoneAvailable && $start !== '--' && $end !== '--') $shiftDisplay = $shift !== '' ? $shift . ' (' . $start . ' – ' . $end . ')' : $start . ' – ' . $end;
                else $shiftDisplay = $timezoneAvailable && $shift !== '' ? $shift : '--';
                $projectionState = (string)($item['projection_state'] ?? ($timezoneAvailable ? 'COMPLETE' : 'PARTIAL'));
                if ($projectionState !== 'COMPLETE') $projectionStatus = 'PARTIAL';
                $all[] = [
                    'id' => (string)$item['evaluation_ref'], 'worker_id' => (int)$item['worker_id'],
                    'worker_name' => (string)$item['worker_name'], 'employee_number' => $item['employee_number'] ?? null,
                    'workplace_id' => (int)$item['workplace_id'], 'workplace_name' => (string)$item['workplace_name'],
                    'date' => (string)$item['work_date'], 'status' => $statusValue, 'status_badge' => self::statusBadge($statusValue),
                    'shift_name' => $shiftDisplay, 'scheduled_type' => (string)($item['schedule_type'] ?? ''),
                    'entry_display' => $localTime($item['entry_utc'] ?? null, $item['entry_raw'] ?? null),
                    'exit_display' => $localTime($item['exit_utc'] ?? null, $item['exit_raw'] ?? null),
                    'entry_raw' => $item['entry_raw'] ?? null, 'exit_raw' => $item['exit_raw'] ?? null,
                    'duration_display' => $timezoneAvailable ? self::formatDuration($workedSeconds) : '--',
                    'scheduled_duration_display' => $timezoneAvailable ? self::formatDuration($scheduledSeconds) : '--',
                    'revision_number' => (int)$item['revision_number'], 'algorithm_version' => (string)$item['algorithm_version'],
                    'execution_fingerprint' => (string)$item['evaluation_ref'], 'calculated_at_utc' => (string)$item['calculated_at_utc'],
                    'timezone' => $timezone ?? '', 'segments' => $segments, 'raw_punches' => $punches,
                    'incidences' => is_array($item['incidences'] ?? null) ? $item['incidences'] : [],
                    'projection_state' => $projectionState,
                ];
            }
            $hasMore = (bool)$pagination['has_more'];
            $page++;
            if ($page > 500) return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => 'pagination_limit'];
        } while ($hasMore);

        if ($expectedTotal !== count($all)) return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => 'partial_result'];
        return ['ok' => true, 'journeys' => $all, 'total' => count($all),
            'projection_status' => $all === [] ? 'EMPTY' : $projectionStatus,
            'evaluation_coverage' => $evaluationCoverage];
    }

    public function readPersistedJourneys(
        int $workplaceId, string $dateFrom, string $dateTo, ?int $workerId = null,
        ?string $status = null, ?\App\Models\Usuario $user = null
    ): array {
        return $this->getPersistedJourneys($workplaceId, $dateFrom, $dateTo, $workerId, $status, $user);
    }

    /** Preserved read-only SQL reference; active callers use the API-owned boundary above. */
    private function getPersistedJourneysSqlReference(
        int $workplaceId,
        string $dateFrom,
        string $dateTo,
        ?int $workerId = null,
        ?string $status = null
    ): array {
        if ($workplaceId < 1) {
            return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => 'workplace_invalid'];
        }

        if (!self::isValidDate($dateFrom) || !self::isValidDate($dateTo)) {
            return ['ok' => false, 'journeys' => [], 'total' => 0, 'error' => 'dates_invalid'];
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
                    c.id AS calc_id,
                    c.id_jornada_programada,
                    c.id_pareo_jornada,
                    c.id_personal,
                    p.nombre_completo AS worker_name,
                    p.no_colaborador AS employee_number,
                    c.id_estacion,
                    l.localidad AS station_name,
                    c.fecha_jornada_local,
                    c.estado_calculo,
                    c.revision_numero,
                    c.version_algoritmo_calculo,
                    c.segundos_programados_totales,
                    c.segundos_trabajados_totales,
                    c.segundos_candidatos_fuera_horario,
                    c.fingerprint_ejecucion,
                    c.creado_at_utc AS calc_creado_at_utc,
                    jp.tipo_jornada,
                    jp.scheduled_start_utc,
                    jp.scheduled_end_utc,
                    jp.timezone_local,
                    tp.nombre AS shift_name,
                    ps.id_marcacion_entrada,
                    ps.id_marcacion_salida,
                    ps.estado_segmento,
                    me.ocurrido_at_utc AS entry_utc,
                    me.source_timestamp_raw AS entry_raw,
                    me.metodo_captura AS entry_method,
                    me.id_dispositivo AS entry_device_id,
                    me.event_uuid AS entry_uuid,
                    ms.ocurrido_at_utc AS exit_utc,
                    ms.source_timestamp_raw AS exit_raw,
                    ms.metodo_captura AS exit_method,
                    ms.id_dispositivo AS exit_device_id,
                    ms.event_uuid AS exit_uuid
                FROM op_rh_v2_calculos_jornada c
                INNER JOIN (
                    SELECT MAX(id) AS latest_id
                    FROM op_rh_v2_calculos_jornada
                    WHERE id_estacion = :workplace_id
                      AND fecha_jornada_local BETWEEN :date_from AND :date_to
                    GROUP BY id_jornada_programada
                ) latest ON latest.latest_id = c.id
                LEFT JOIN op_rh_v2_jornadas_programadas jp ON jp.id = c.id_jornada_programada
                LEFT JOIN op_rh_personal p ON p.id = c.id_personal
                LEFT JOIN op_rh_localidades l ON l.id = c.id_estacion
                LEFT JOIN op_rh_v2_turnos_plantillas tp ON tp.id = jp.id_plantilla_origen
                LEFT JOIN op_rh_v2_pareos_segmentos ps ON ps.id_pareo_jornada = c.id_pareo_jornada AND ps.ordinal_segmento = 1
                LEFT JOIN op_rh_v2_marcaciones_crudas me ON me.id = ps.id_marcacion_entrada
                LEFT JOIN op_rh_v2_marcaciones_crudas ms ON ms.id = ps.id_marcacion_salida
                WHERE c.id_estacion = :workplace_filter
            ";

            $params = [
                ':workplace_id' => $workplaceId,
                ':date_from' => $dateFrom,
                ':date_to' => $dateTo,
                ':workplace_filter' => $workplaceId,
            ];

            if ($workerId !== null && $workerId > 0) {
                $sql .= " AND c.id_personal = :worker_id";
                $params[':worker_id'] = $workerId;
            }

            if ($status !== null && trim($status) !== '') {
                $sql .= " AND c.estado_calculo = :status";
                $params[':status'] = trim($status);
            }

            $sql .= " ORDER BY c.fecha_jornada_local DESC, p.nombre_completo ASC, c.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rawRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if ($rawRows === []) {
                return ['ok' => true, 'journeys' => [], 'total' => 0];
            }

            // Extract IDs for fetching details
            $calcIds = [];
            $pareoIds = [];
            $progIds = [];
            foreach ($rawRows as $row) {
                $calcIds[] = (int) $row['calc_id'];
                if (!empty($row['id_pareo_jornada'])) {
                    $pareoIds[] = (int) $row['id_pareo_jornada'];
                }
                if (!empty($row['id_jornada_programada'])) {
                    $progIds[] = (int) $row['id_jornada_programada'];
                }
            }
            $calcIds = array_values(array_unique($calcIds));
            $pareoIds = array_values(array_unique($pareoIds));
            $progIds = array_values(array_unique($progIds));

            $segmentsByCalc = $this->fetchSegmentsByCalculoIds($pdo, $calcIds);
            $punchesByPareo = $this->fetchPunchesByPareoIds($pdo, $pareoIds);
            $incidencesByProg = $this->fetchIncidencesByProgramadaIds($pdo, $progIds);

            $journeys = [];
            foreach ($rawRows as $row) {
                $calcId = (int) $row['calc_id'];
                $pareoId = (int) ($row['id_pareo_jornada'] ?? 0);
                $progId = (int) ($row['id_jornada_programada'] ?? 0);
                $timezone = !empty($row['timezone_local']) ? (string) $row['timezone_local'] : 'America/Mexico_City';

                // Shift description
                $shiftDisplay = $this->resolveShiftDisplay($row, $timezone);

                // Entry / exit times in 12h format
                $entryDisplay = self::format12hTime(
                    $row['entry_utc'] ?? null,
                    $timezone,
                    $row['entry_raw'] ?? null
                );
                $exitDisplay = self::format12hTime(
                    $row['exit_utc'] ?? null,
                    $timezone,
                    $row['exit_raw'] ?? null
                );

                // Status badge
                $statusBadge = self::statusBadge((string) ($row['estado_calculo'] ?? ''));

                // Worked duration
                $durationDisplay = self::formatDuration(
                    isset($row['segundos_trabajados_totales']) && is_numeric($row['segundos_trabajados_totales'])
                        ? (int) $row['segundos_trabajados_totales']
                        : null
                );
                $scheduledDurationDisplay = self::formatDuration(
                    isset($row['segundos_programados_totales']) && is_numeric($row['segundos_programados_totales'])
                        ? (int) $row['segundos_programados_totales']
                        : null
                );

                $workerName = trim((string) ($row['worker_name'] ?? ''));
                if ($workerName === '') {
                    $workerName = 'Personal ' . ((int) $row['id_personal']);
                }

                $journeys[] = [
                    'id' => $calcId,
                    'worker_id' => (int) $row['id_personal'],
                    'worker_name' => $workerName,
                    'employee_number' => !empty($row['employee_number']) ? (string) $row['employee_number'] : null,
                    'workplace_id' => (int) $row['id_estacion'],
                    'workplace_name' => (string) ($row['station_name'] ?? ('Estación ' . $row['id_estacion'])),
                    'date' => (string) $row['fecha_jornada_local'],
                    'status' => (string) ($row['estado_calculo'] ?? ''),
                    'status_badge' => $statusBadge,
                    'shift_name' => $shiftDisplay,
                    'scheduled_type' => (string) ($row['tipo_jornada'] ?? ''),
                    'entry_display' => $entryDisplay,
                    'exit_display' => $exitDisplay,
                    'entry_raw' => $row['entry_raw'] ?? null,
                    'exit_raw' => $row['exit_raw'] ?? null,
                    'duration_display' => $durationDisplay,
                    'scheduled_duration_display' => $scheduledDurationDisplay,
                    'revision_number' => (int) ($row['revision_numero'] ?? 1),
                    'algorithm_version' => (string) ($row['version_algoritmo_calculo'] ?? 'v2.4-p2'),
                    'execution_fingerprint' => (string) ($row['fingerprint_ejecucion'] ?? ''),
                    'calculated_at_utc' => (string) ($row['calc_creado_at_utc'] ?? ''),
                    'timezone' => $timezone,
                    'segments' => $segmentsByCalc[$calcId] ?? [],
                    'raw_punches' => $punchesByPareo[$pareoId] ?? [],
                    'incidences' => $incidencesByProg[$progId] ?? [],
                ];
            }

            return [
                'ok' => true,
                'journeys' => $journeys,
                'total' => count($journeys),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'journeys' => [],
                'total' => 0,
                'error' => 'query_failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Format a UTC or raw datetime into 12-hour format: `07:00 am`, `11:00 am`, `12:00 pm`, `04:00 pm`.
     */
    public static function format12hTime(?string $datetimeUtc, ?string $timezone = 'America/Mexico_City', ?string $rawFallback = null): string
    {
        if (is_string($datetimeUtc) && trim($datetimeUtc) !== '') {
            try {
                $tz = !empty($timezone) ? new DateTimeZone($timezone) : new DateTimeZone('America/Mexico_City');
                $dt = new DateTimeImmutable($datetimeUtc, new DateTimeZone('UTC'));
                return $dt->setTimezone($tz)->format('h:i a');
            } catch (Throwable) {
                // fall through
            }
        }

        if (is_string($rawFallback) && trim($rawFallback) !== '') {
            try {
                $dt = new DateTimeImmutable($rawFallback);
                return $dt->format('h:i a');
            } catch (Throwable) {
                // fall through
            }
        }

        return '--';
    }

    /**
     * Map contractual calculation status to localized badge label and CSS class.
     */
    public static function statusBadge(string $status): array
    {
        return match (strtoupper(trim($status))) {
            'CALCULATED' => ['label' => 'Calculada', 'class' => 'badge text-bg-success'],
            'PARTIAL_DATA' => ['label' => 'Datos incompletos', 'class' => 'badge text-bg-warning'],
            'PENDING_SYNC' => ['label' => 'Sincronización pendiente', 'class' => 'badge text-bg-info text-dark'],
            'AMBIGUOUS' => ['label' => 'Ambigua', 'class' => 'badge text-bg-warning'],
            'WORKPLACE_MISMATCH' => ['label' => 'Discrepancia de estación', 'class' => 'badge text-bg-danger'],
            'UNMATCHED_NO_PUNCHES' => ['label' => 'Sin marcaciones', 'class' => 'badge text-bg-secondary'],
            'INVALID_INTERVAL' => ['label' => 'Intervalo inválido', 'class' => 'badge text-bg-danger'],
            'DAY_OFF_RECORDED' => ['label' => 'Descanso registrado', 'class' => 'badge text-bg-info text-dark'],
            'UNSCHEDULED_RECORDED' => ['label' => 'Sin horario programado', 'class' => 'badge text-bg-secondary'],
            'UNKNOWN_SCHEDULE' => ['label' => 'Horario desconocido', 'class' => 'badge text-bg-light border text-dark'],
            default => ['label' => $status !== '' ? $status : 'No evaluada', 'class' => 'badge text-bg-light border text-dark'],
        };
    }

    /**
     * Format duration seconds as `HH:MM` or `--`.
     */
    public static function formatDuration(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '--';
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return sprintf('%02d:%02d hrs', $hours, $minutes);
    }

    /**
     * List of all valid contractual statuses for filter dropdown.
     */
    public static function getContractualStatuses(): array
    {
        return [
            'CALCULATED' => 'Calculada',
            'PARTIAL_DATA' => 'Datos incompletos',
            'PENDING_SYNC' => 'Sincronización pendiente',
            'AMBIGUOUS' => 'Ambigua',
            'WORKPLACE_MISMATCH' => 'Discrepancia de estación',
            'UNMATCHED_NO_PUNCHES' => 'Sin marcaciones',
            'INVALID_INTERVAL' => 'Intervalo inválido',
            'DAY_OFF_RECORDED' => 'Descanso registrado',
            'UNSCHEDULED_RECORDED' => 'Sin horario programado',
            'UNKNOWN_SCHEDULE' => 'Horario desconocido',
        ];
    }

    private static function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        $parts = explode('-', $date);
        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    private function resolveShiftDisplay(array $row, string $timezone): string
    {
        $tipo = strtoupper(trim((string) ($row['tipo_jornada'] ?? '')));
        $calcStatus = strtoupper(trim((string) ($row['estado_calculo'] ?? '')));

        if ($tipo === 'DAY_OFF' || $calcStatus === 'DAY_OFF_RECORDED') {
            return 'Descanso programado';
        }

        if ($tipo === 'UNSCHEDULED' || $calcStatus === 'UNSCHEDULED_RECORDED') {
            return 'Sin horario programado';
        }

        $shiftName = trim((string) ($row['shift_name'] ?? ''));

        $startUtc = $row['scheduled_start_utc'] ?? null;
        $endUtc = $row['scheduled_end_utc'] ?? null;
        $start12h = self::format12hTime($startUtc, $timezone);
        $end12h = self::format12hTime($endUtc, $timezone);

        if ($start12h !== '--' && $end12h !== '--') {
            $hours = $start12h . ' – ' . $end12h;
            return $shiftName !== '' ? $shiftName . ' (' . $hours . ')' : $hours;
        }

        return $shiftName !== '' ? $shiftName : '--';
    }

    private function fetchSegmentsByCalculoIds(\PDO $pdo, array $calcIds): array
    {
        if ($calcIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($calcIds), '?'));
        $sql = "
            SELECT
                cs.id,
                cs.id_calculo_jornada,
                cs.id_pareo_segmento,
                cs.ordinal_segmento,
                cs.tipo_segmento,
                cs.estado_segmento,
                cs.segundos_programados,
                cs.segundos_trabajados,
                cs.delta_llegada_segundos,
                cs.delta_salida_segundos,
                cs.segundos_candidatos_fuera_horario,
                ps.id_marcacion_entrada,
                ps.id_marcacion_salida,
                ps.estado_segmento AS pareo_estado_segmento,
                me.ocurrido_at_utc AS seg_entry_utc,
                me.source_timestamp_raw AS seg_entry_raw,
                me.event_uuid AS seg_entry_uuid,
                ms.ocurrido_at_utc AS seg_exit_utc,
                ms.source_timestamp_raw AS seg_exit_raw,
                ms.event_uuid AS seg_exit_uuid
            FROM op_rh_v2_calculos_segmentos cs
            LEFT JOIN op_rh_v2_pareos_segmentos ps ON ps.id = cs.id_pareo_segmento
            LEFT JOIN op_rh_v2_marcaciones_crudas me ON me.id = ps.id_marcacion_entrada
            LEFT JOIN op_rh_v2_marcaciones_crudas ms ON ms.id = ps.id_marcacion_salida
            WHERE cs.id_calculo_jornada IN ($placeholders)
            ORDER BY cs.id_calculo_jornada ASC, cs.ordinal_segmento ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($calcIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $calcId = (int) $row['id_calculo_jornada'];
            $grouped[$calcId][] = $row;
        }
        return $grouped;
    }

    private function fetchPunchesByPareoIds(\PDO $pdo, array $pareoIds): array
    {
        if ($pareoIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pareoIds), '?'));
        $sql = "
            SELECT
                pma.id_pareo_jornada,
                pma.id_pareo_segmento,
                pma.rol_marcacion,
                mc.*
            FROM op_rh_v2_pareos_marcaciones_asociadas pma
            JOIN op_rh_v2_marcaciones_crudas mc ON mc.id = pma.id_marcacion_cruda
            WHERE pma.id_pareo_jornada IN ($placeholders)
            ORDER BY pma.id_pareo_jornada ASC, mc.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($pareoIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $pareoId = (int) $row['id_pareo_jornada'];
            $grouped[$pareoId][] = $row;
        }
        return $grouped;
    }

    private function fetchIncidencesByProgramadaIds(\PDO $pdo, array $progIds): array
    {
        if ($progIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($progIds), '?'));
        $sql = "
            SELECT
                ia.id_jornada_programada,
                ia.fecha_jornada_local,
                ia.estado_aplicacion,
                im.uuid_incidencia,
                im.tipo_incidencia,
                im.estado_actual
            FROM op_rh_v2_incidencias_aplicaciones ia
            LEFT JOIN op_rh_v2_incidencias_revisiones ir ON ir.id = ia.id_incidencia_revision
            LEFT JOIN op_rh_v2_incidencias_maestro im ON im.id = ir.id_incidencia_maestro
            WHERE ia.id_jornada_programada IN ($placeholders)
            ORDER BY ia.id_jornada_programada ASC, ia.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($progIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $progId = (int) $row['id_jornada_programada'];
            $grouped[$progId][] = $row;
        }
        return $grouped;
    }
}

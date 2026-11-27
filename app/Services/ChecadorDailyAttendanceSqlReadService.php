<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Throwable;

/** M01-only, read-only access to persisted raw events and historical time zones. */
final class ChecadorDailyAttendanceSqlReadService
{
    private const RAW_TABLE = 'op_rh_v2_marcaciones_crudas';
    private const MAX_EVENTS = 4000;
    private const MAX_COVERAGE_REVISIONS = 1000;
    private const READ_TABLES = [
        'op_rh_v2_marcaciones_crudas',
        'op_rh_localidades',
        'tb_estaciones',
        'op_rh_v2_localidades_zonas_horarias',
        'op_rh_personal',
        'op_rh_personal_localidad_version',
    ];

    private ?Connection $testConnection;
    /** @var callable(int):bool|null */
    private $authorizeLocality;

    /** Test seams accept only an in-memory SQLite connection while APP_ENV=test. */
    public function __construct(?Connection $testConnection = null, ?callable $authorizeLocality = null)
    {
        if ($testConnection !== null
            && (strtolower(self::environment()) !== 'test'
                || $testConnection->getDriverName() !== 'sqlite'
                || $testConnection->getConfig('database') !== ':memory:')) {
            throw new \InvalidArgumentException('M01 test connections are restricted to in-memory SQLite tests.');
        }
        if ($authorizeLocality !== null && $testConnection === null) {
            throw new \InvalidArgumentException('M01 authorization overrides are restricted to isolated tests.');
        }
        $this->testConnection = $testConnection;
        $this->authorizeLocality = $authorizeLocality;
    }

    /** @param list<int> $authorizedLocalityIds */
    public function resolveCurrentLocalDay(int $localityId, array $authorizedLocalityIds): array
    {
        if (!$this->isAuthorized($localityId, $authorizedLocalityIds)) {
            return self::failure('locality_not_authorized');
        }
        try {
            $connection = $this->readConnection();
            $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $candidateDate = $nowUtc->format('Y-m-d');
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $window = $this->resolveLocalDay($connection, $localityId, $candidateDate);
                if (!($window['ok'] ?? false)) {
                    return $window;
                }
                $resolvedDate = $nowUtc->setTimezone(new \DateTimeZone($window['data']['timezone']))->format('Y-m-d');
                if ($resolvedDate === $candidateDate) {
                    $window['data']['local_date'] = $resolvedDate;
                    return $window;
                }
                $candidateDate = $resolvedDate;
            }
            return self::failure('timezone_unresolved');
        } catch (Throwable $exception) {
            $this->logFailure('current_local_day', $localityId, null, $exception);
            return self::failure('database_unavailable');
        }
    }

    /** @param list<int> $authorizedLocalityIds */
    public function readDay(
        int $localityId,
        string $localDate,
        array $authorizedLocalityIds,
        ?int $workerId = null
    ): array {
        if (!$this->isAuthorized($localityId, $authorizedLocalityIds)) {
            return self::failure('locality_not_authorized');
        }
        if (!self::isCivilDate($localDate) || ($workerId !== null && $workerId < 1)) {
            return self::failure('bad_request');
        }

        try {
            $connection = $this->readConnection();
            $window = $this->resolveLocalDay($connection, $localityId, $localDate);
            if (!($window['ok'] ?? false)) {
                return $window;
            }
            $query = $connection->table(self::RAW_TABLE)
                ->where('id_estacion_resuelta', '=', $window['data']['physical_station_id'])
                ->whereNotNull('ocurrido_at_utc')
                ->where('ocurrido_at_utc', '>=', $window['data']['from_utc'])
                ->where('ocurrido_at_utc', '<', $window['data']['to_utc']);
            if ($workerId !== null) {
                $query->where('id_personal_resuelto', '=', $workerId);
            }
            $rows = $query->select([
                'id', 'event_uuid', 'id_dispositivo', 'id_estacion_resuelta', 'id_personal_resuelto',
                'source_timestamp_raw', 'source_timezone_reported', 'effective_timezone',
                'timezone_fallback_applied', 'ocurrido_at_utc', 'recibido_at_utc', 'metodo_captura',
                'external_event_id', 'secuencia_dispositivo', 'processing_status',
                'integrity_version', 'integrity_key_id',
            ])->selectRaw(
                "CASE WHEN integrity_seal IS NULL OR LENGTH(integrity_seal) = 0 THEN 0 ELSE 1 END AS integrity_seal_present"
            )->orderBy('ocurrido_at_utc')->orderBy('id')->limit(self::MAX_EVENTS + 1)->get();
            if ($rows->count() > self::MAX_EVENTS) {
                return self::failure('read_limit_exceeded');
            }

            $events = [];
            foreach ($rows as $row) {
                $event = (array) $row;
                if ((int) ($event['id_estacion_resuelta'] ?? 0) !== (int) $window['data']['physical_station_id']) {
                    return self::failure('locality_scope_mismatch');
                }
                $event['integrity_seal_present'] = (int) ($event['integrity_seal_present'] ?? 0) === 1;
                $event['integrity_format_status'] = 'NOT_VERIFIED_BY_PORTAL3';
                $event['integrity_verification_status'] = 'NOT_VERIFIED_BY_PORTAL3';
                $event['local_time'] = self::formatLocalTime(
                    (string) ($event['ocurrido_at_utc'] ?? ''),
                    (string) $window['data']['timezone']
                );
                $events[] = $event;
            }

            return [
                'ok' => true,
                'locality_id' => $localityId,
                'physical_station_id' => $window['data']['physical_station_id'],
                'local_date' => $localDate,
                'timezone' => $window['data']['timezone'],
                'from_utc' => $window['data']['from_utc'],
                'to_utc' => $window['data']['to_utc'],
                'events' => $events,
                'consistency' => 'LIVE_NON_SNAPSHOT',
                'source_completeness' => 'UNKNOWN',
            ];
        } catch (Throwable $exception) {
            $this->logFailure('read_day', $localityId, $localDate, $exception);
            return self::failure('database_unavailable');
        }
    }

    /**
     * Read only persisted, explicitly authorized Comodines coverage for M01.
     * This is a display projection, not the API WORKER_EFFECTIVE resolver.
     *
     * @param list<int> $authorizedLocalityIds
     * @return array{ok:bool,data?:array{by_worker:array<int,array<string,mixed>>,status:string},error?:array{kind:string}}
     */
    public function readPersistedCoverageForDate(
        int $localityId,
        string $localDate,
        array $authorizedLocalityIds,
        ?int $workerId = null
    ): array {
        if (!$this->isAuthorized($localityId, $authorizedLocalityIds)) {
            return self::failure('locality_not_authorized');
        }
        if (!self::isCivilDate($localDate) || ($workerId !== null && $workerId < 1)) {
            return self::failure('bad_request');
        }

        try {
            $connection = $this->readConnection();
            if (!$this->coverageSchemaReady($connection)) {
                return self::failure('coverage_schema_unavailable');
            }
            foreach ([
                'op_rh_v2_worker_workplace_coverage_versions', 'op_rh_v2_turnos_asignaciones',
                'op_rh_v2_turnos_audit_log', 'op_rh_v2_turnos_plantillas', 'op_rh_v2_turnos_segmentos',
                'op_rh_personal_localidad_version', 'op_rh_personal', 'op_rh_localidades',
                'tb_estaciones', 'op_rh_v2_localidades_zonas_horarias',
            ] as $table) {
                $connection->table($table)->selectRaw('1')->limit(0)->get();
            }

            $window = $this->resolveLocalDay($connection, $localityId, $localDate);
            if (($window['ok'] ?? false) !== true) return $window;
            $timezone = (string) ($window['data']['timezone'] ?? '');
            $weekday = ChecadorWeeklyScheduleService::weekdayFor($localDate);
            if ($weekday === null || $timezone === '') return self::failure('coverage_context_unavailable');

            $candidateRows = $connection->table('op_rh_v2_worker_workplace_coverage_versions')
                ->where('id_coverage_workplace', $localityId)
                ->where('fecha_desde_local', '<=', $localDate)
                ->where('fecha_hasta_local_exclusiva', '>', $localDate)
                ->when($workerId !== null, static fn($query) => $query->where('id_personal', $workerId))
                ->orderBy('coverage_uuid')->orderBy('revision_no')
                ->limit(self::MAX_COVERAGE_REVISIONS + 1)
                ->get(['coverage_uuid']);
            if ($candidateRows->count() > self::MAX_COVERAGE_REVISIONS) {
                return self::failure('coverage_read_limit_exceeded');
            }
            $coverageUuids = array_values(array_unique(array_map(
                static fn(object $row): string => trim((string) $row->coverage_uuid),
                $candidateRows->all()
            )));
            if ($coverageUuids === []) {
                return ['ok' => true, 'data' => ['by_worker' => [], 'status' => 'none']];
            }

            $byWorkerCandidates = [];
            foreach ($coverageUuids as $coverageUuid) {
                if ($coverageUuid === '') return self::failure('coverage_data_inconsistent');
                $revisions = $connection->table('op_rh_v2_worker_workplace_coverage_versions')
                    ->where('coverage_uuid', $coverageUuid)->orderBy('revision_no')->limit(101)->get([
                        'id', 'coverage_uuid', 'revision_no', 'supersedes_id', 'id_personal',
                        'id_home_workplace', 'id_home_membership_version', 'id_coverage_workplace',
                        'fecha_desde_local', 'fecha_hasta_local_exclusiva', 'timezone_iana',
                        'proposed_schedule_json', 'status', 'requested_at_utc',
                    ])->map(
                        static fn(object $row): array => (array) $row
                    )->all();
                if (count($revisions) > 100 || !self::coverageRevisionChainIsValid($revisions)) {
                    $candidateWorker = self::positiveInteger($revisions[0]['id_personal'] ?? null);
                    if ($candidateWorker !== null) $byWorkerCandidates[$candidateWorker][] = ['status' => 'conflict'];
                    continue;
                }

                $latest = $revisions[array_key_last($revisions)];
                $candidateWorker = self::positiveInteger($latest['id_personal'] ?? null);
                if ($candidateWorker === null) return self::failure('coverage_data_inconsistent');
                $status = strtoupper(trim((string) ($latest['status'] ?? '')));
                if ($status !== 'AUTHORIZED') {
                    $linked = $connection->table('op_rh_v2_turnos_asignaciones')
                        ->whereIn('id_cobertura_version', array_values(array_map(
                            static fn(array $revision): int => (int) $revision['id'], $revisions
                        )))
                        ->where('es_activo', 1)->limit(1)->exists();
                    $byWorkerCandidates[$candidateWorker][] = [
                        'status' => $linked ? 'conflict' : ($status === 'CANCELLED' ? 'cancelled' : 'not_effective'),
                    ];
                    continue;
                }
                $latestFrom = (string) ($latest['fecha_desde_local'] ?? '');
                $latestTo = (string) ($latest['fecha_hasta_local_exclusiva'] ?? '');
                if (!self::isCivilDate($latestFrom) || !self::isCivilDate($latestTo) || $latestTo <= $latestFrom) {
                    $byWorkerCandidates[$candidateWorker][] = ['status' => 'conflict'];
                    continue;
                }
                // An older revision can cover this date while its current head
                // has moved the interval. That is not effective for this day.
                if ($localDate < $latestFrom || $localDate >= $latestTo) {
                    $byWorkerCandidates[$candidateWorker][] = ['status' => 'not_effective'];
                    continue;
                }

                $resolved = $this->readAuthorizedCoverageRevision(
                    $connection,
                    $latest,
                    $localityId,
                    $localDate,
                    $weekday,
                    $timezone
                );
                $byWorkerCandidates[$candidateWorker][] = $resolved;
            }

            $byWorker = [];
            foreach ($byWorkerCandidates as $candidateWorker => $candidates) {
                $authorized = array_values(array_filter($candidates, static fn(array $item): bool => ($item['status'] ?? '') === 'authorized'));
                if (count($authorized) === 1 && count($candidates) === 1) {
                    $byWorker[(int) $candidateWorker] = $authorized[0];
                    continue;
                }
                $statuses = array_column($candidates, 'status');
                $byWorker[(int) $candidateWorker] = [
                    'status' => count($candidates) > 1 || in_array('conflict', $statuses, true) || count($authorized) > 1
                        ? 'conflict'
                        : (in_array('cancelled', $statuses, true) ? 'cancelled' : 'not_effective'),
                ];
            }
            return ['ok' => true, 'data' => ['by_worker' => $byWorker, 'status' => 'ready']];
        } catch (Throwable $exception) {
            $this->logFailure('read_persisted_coverage', $localityId, $localDate, $exception);
            return self::failure('coverage_database_unavailable');
        }
    }

    /** @param list<array<string,mixed>> $revisions */
    private static function coverageRevisionChainIsValid(array $revisions): bool
    {
        if ($revisions === [] || count($revisions) > 100) return false;
        foreach ($revisions as $index => $revision) {
            $id = self::positiveInteger($revision['id'] ?? null);
            $number = self::positiveInteger($revision['revision_no'] ?? null);
            $supersedes = $revision['supersedes_id'] ?? null;
            if ($id === null || $number !== $index + 1) return false;
            if ($index === 0 ? $supersedes !== null : (int) $supersedes !== (int) $revisions[$index - 1]['id']) return false;
            if ((string) ($revision['coverage_uuid'] ?? '') !== (string) ($revisions[0]['coverage_uuid'] ?? '')
                || (int) ($revision['id_personal'] ?? 0) !== (int) ($revisions[0]['id_personal'] ?? 0)
                || (int) ($revision['id_home_workplace'] ?? 0) !== (int) ($revisions[0]['id_home_workplace'] ?? 0)
                || (int) ($revision['id_home_membership_version'] ?? 0) !== (int) ($revisions[0]['id_home_membership_version'] ?? 0)
                || (int) ($revision['id_coverage_workplace'] ?? 0) !== (int) ($revisions[0]['id_coverage_workplace'] ?? 0)) return false;
        }
        return true;
    }

    /** @param array<string,mixed> $revision @return array<string,mixed> */
    private function readAuthorizedCoverageRevision(
        Connection $connection,
        array $revision,
        int $localityId,
        string $localDate,
        string $weekday,
        string $timezone
    ): array {
        $conflict = ['status' => 'conflict'];
        $workerId = self::positiveInteger($revision['id_personal'] ?? null);
        $revisionId = self::positiveInteger($revision['id'] ?? null);
        $homeId = self::positiveInteger($revision['id_home_workplace'] ?? null);
        $membershipId = self::positiveInteger($revision['id_home_membership_version'] ?? null);
        $from = (string) ($revision['fecha_desde_local'] ?? '');
        $to = (string) ($revision['fecha_hasta_local_exclusiva'] ?? '');
        if ($workerId === null || $revisionId === null || $homeId === null || $membershipId === null
            || (int) ($revision['id_coverage_workplace'] ?? 0) !== $localityId
            || !self::isCivilDate($from) || !self::isCivilDate($to) || $to <= $from
            || $localDate < $from || $localDate >= $to
            || strtoupper((string) ($revision['status'] ?? '')) !== 'AUTHORIZED'
            || trim((string) ($revision['timezone_iana'] ?? '')) !== $timezone) return $conflict;

        $home = $connection->table('op_rh_localidades')->where('id', $homeId)->first(['id']);
        $person = $connection->table('op_rh_personal')
            ->where('id', $workerId)->first(['id', 'nombre_completo', 'no_colaborador']);
        if ($home === null || $person === null) return $conflict;

        try {
            $zone = new \DateTimeZone($timezone);
            $fromUtc = (new \DateTimeImmutable($from . ' 00:00:00', $zone))
                ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $toUtc = (new \DateTimeImmutable($to . ' 00:00:00', $zone))
                ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return $conflict;
        }
        $membership = $connection->table('op_rh_personal_localidad_version')
            ->where('id', $membershipId)->where('worker_id', $workerId)->where('workplace_id', $homeId)
            ->where('valid_from_utc', '<=', $fromUtc)
            ->where(static function ($query) use ($toUtc): void {
                $query->whereNull('valid_to_utc')->orWhere('valid_to_utc', '>=', $toUtc);
            })
            ->where('recorded_at_utc', '<=', (string) ($revision['requested_at_utc'] ?? ''))
            ->first(['id']);
        if ($membership === null) return $conflict;

        $schedule = json_decode((string) ($revision['proposed_schedule_json'] ?? ''), true);
        if (!is_array($schedule) || !array_is_list($schedule) || count($schedule) !== 7) return $conflict;
        $byDay = [];
        $weekStart = ChecadorWeeklyScheduleService::monday($localDate);
        $expectedDays = $weekStart === null ? [] : array_column(ChecadorWeeklyScheduleService::dates($weekStart), 'weekday');
        if (count($expectedDays) !== 7) return $conflict;
        foreach ($schedule as $entry) {
            if (!is_array($entry)) return $conflict;
            $day = strtoupper(trim((string) ($entry['dia_semana'] ?? '')));
            $type = strtoupper(trim((string) ($entry['tipo'] ?? '')));
            if (!in_array($day, $expectedDays, true) || isset($byDay[$day]) || !in_array($type, ['WORK', 'DAY_OFF'], true)) return $conflict;
            $byDay[$day] = $entry;
        }
        if (count($byDay) !== 7 || array_diff($expectedDays, array_keys($byDay)) !== []) return $conflict;
        $scheduleEntry = $byDay[$weekday] ?? null;
        if (!is_array($scheduleEntry)) return $conflict;

        $assignments = $connection->table('op_rh_v2_turnos_asignaciones')
            ->where('id_cobertura_version', $revisionId)->orderBy('dia_semana')->limit(8)
            ->get(['id', 'id_personal', 'id_estacion', 'id_plantilla', 'tipo_asignacion', 'dia_semana',
                'valido_desde', 'valido_hasta', 'es_activo', 'creado_por_usuario_id', 'id_cobertura_version'])
            ->map(static fn(object $row): array => (array) $row)->all();
        if (count($assignments) !== 7) return $conflict;
        $assignmentByDay = [];
        foreach ($assignments as $assignment) {
            $day = strtoupper(trim((string) ($assignment['dia_semana'] ?? '')));
            $daySchedule = $byDay[$day] ?? null;
            $dayType = is_array($daySchedule) ? strtoupper(trim((string) ($daySchedule['tipo'] ?? ''))) : '';
            $dayTemplateId = $dayType === 'WORK' ? self::positiveInteger($daySchedule['id_plantilla'] ?? null) : null;
            if ($day === '' || isset($assignmentByDay[$day])
                || !is_array($daySchedule)
                || (int) ($assignment['id_personal'] ?? 0) !== $workerId
                || (int) ($assignment['id_estacion'] ?? 0) !== $localityId
                || strtoupper((string) ($assignment['tipo_asignacion'] ?? '')) !== 'TEMPORAL'
                || (string) ($assignment['valido_desde'] ?? '') !== $from
                || (string) ($assignment['valido_hasta'] ?? '') !== $to
                || (int) ($assignment['es_activo'] ?? 0) !== 1
                || (int) ($assignment['id_cobertura_version'] ?? 0) !== $revisionId
                || (($assignment['id_plantilla'] === null ? null : (int) $assignment['id_plantilla']) !== $dayTemplateId)) return $conflict;
            $assignmentByDay[$day] = $assignment;
        }
        $assignment = $assignmentByDay[$weekday] ?? null;
        if (!is_array($assignment)) return $conflict;
        $effectiveTemporalAssignments = $connection->table('op_rh_v2_turnos_asignaciones')
            ->where('id_personal', $workerId)->where('id_estacion', $localityId)
            ->where('dia_semana', $weekday)->where('tipo_asignacion', 'TEMPORAL')->where('es_activo', 1)
            ->where('valido_desde', '<=', $localDate)
            ->where(static function ($query) use ($localDate): void {
                $query->whereNull('valido_hasta')->orWhere('valido_hasta', '>', $localDate);
            })
            ->orderBy('id')->limit(3)->get(['id']);
        if ($effectiveTemporalAssignments->count() !== 1
            || (int) $effectiveTemporalAssignments[0]->id !== (int) ($assignment['id'] ?? 0)) return $conflict;

        $entryType = strtoupper(trim((string) ($scheduleEntry['tipo'] ?? '')));
        $templateId = null;
        $templateVersion = null;
        $template = null;
        if ($entryType === 'WORK') {
            $templateId = self::positiveInteger($scheduleEntry['id_plantilla'] ?? null);
            $templateVersion = self::positiveInteger($scheduleEntry['version_plantilla'] ?? null);
            if ($templateId === null || $templateVersion === null
                || (int) ($assignment['id_plantilla'] ?? 0) !== $templateId) return $conflict;
            $templateRows = $connection->table('op_rh_v2_turnos_plantillas')
                ->where('id', $templateId)->where('version', $templateVersion)->limit(2)
                ->get(['id', 'version', 'nombre', 'scope', 'id_estacion']);
            if ($templateRows->count() !== 1) return $conflict;
            $templateRow = $templateRows[0];
            $scope = strtoupper(trim((string) ($templateRow->scope ?? '')));
            $station = $templateRow->id_estacion === null ? null : (int) $templateRow->id_estacion;
            if (!(($scope === 'GLOBAL' && $station === null)
                || ($scope === 'STATION' && $station === $localityId))) return $conflict;
            $segmentRows = $connection->table('op_rh_v2_turnos_segmentos')
                ->where('id_plantilla', $templateId)->orderBy('ordinal')->get([
                    'ordinal', 'tipo_segmento', 'hora_inicio_local', 'hora_fin_local', 'dia_offset_inicio', 'dia_offset_fin',
                ]);
            $segments = [];
            foreach ($segmentRows as $index => $segmentRow) {
                $segment = (array) $segmentRow;
                if ((int) ($segment['ordinal'] ?? 0) !== $index + 1
                    || !in_array(strtoupper((string) ($segment['tipo_segmento'] ?? '')), ['WORK', 'BREAK'], true)
                    || !preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/', (string) ($segment['hora_inicio_local'] ?? ''))
                    || !preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/', (string) ($segment['hora_fin_local'] ?? ''))
                    || !in_array((int) ($segment['dia_offset_inicio'] ?? -1), [0, 1], true)
                    || !in_array((int) ($segment['dia_offset_fin'] ?? -1), [0, 1], true)) return $conflict;
                $segments[] = [
                    'ordinal' => (int) $segment['ordinal'], 'tipo_segmento' => (string) $segment['tipo_segmento'],
                    'hora_inicio_local' => (string) $segment['hora_inicio_local'], 'hora_fin_local' => (string) $segment['hora_fin_local'],
                    'dia_offset_inicio' => (int) $segment['dia_offset_inicio'], 'dia_offset_fin' => (int) $segment['dia_offset_fin'],
                ];
            }
            if ($segments === [] || !in_array('WORK', array_column($segments, 'tipo_segmento'), true)) return $conflict;
            $template = [
                'id' => $templateId, 'version' => $templateVersion,
                'nombre' => trim((string) ($templateRow->nombre ?? '')) ?: 'Turno', 'segmentos' => $segments,
            ];
        } elseif ($entryType !== 'DAY_OFF' || ($assignment['id_plantilla'] ?? null) !== null) {
            return $conflict;
        }

        $audits = $connection->table('op_rh_v2_turnos_audit_log')
            ->where('entity_type', 'WORKPLACE_COVERAGE')->where('entity_id', $revisionId)
            ->where('operacion', 'COVERAGE_ASSIGN')->orderBy('id')->limit(8)->get([
                'id_estacion', 'id_personal', 'tipo_asignacion', 'valido_desde', 'valido_hasta',
                'id_plantilla', 'version_plantilla', 'detalle_json',
            ]);
        if ($audits->count() !== 7) return $conflict;
        $auditByAssignment = [];
        foreach ($audits as $auditRow) {
            $audit = (array) $auditRow;
            $details = json_decode((string) ($audit['detalle_json'] ?? ''), true);
            $auditAssignmentId = self::positiveInteger($details['assignment_id'] ?? null);
            $auditAssignment = $auditAssignmentId === null ? null : $this->findCoverageAssignmentById($assignmentByDay, $auditAssignmentId);
            $auditDay = strtoupper(trim((string) ($auditAssignment['dia_semana'] ?? '')));
            $auditSchedule = $byDay[$auditDay] ?? null;
            $auditType = is_array($auditSchedule) ? strtoupper(trim((string) ($auditSchedule['tipo'] ?? ''))) : '';
            $auditTemplateId = $auditType === 'WORK' ? self::positiveInteger($auditSchedule['id_plantilla'] ?? null) : null;
            $auditTemplateVersion = $auditType === 'WORK' ? self::positiveInteger($auditSchedule['version_plantilla'] ?? null) : null;
            if ($auditAssignmentId === null || isset($auditByAssignment[$auditAssignmentId])
                || $auditAssignment === null || $auditTemplateId === null && $auditType === 'WORK'
                || (int) ($audit['id_estacion'] ?? 0) !== $localityId
                || (int) ($audit['id_personal'] ?? 0) !== $workerId
                || strtoupper((string) ($audit['tipo_asignacion'] ?? '')) !== 'TEMPORAL'
                || (string) ($audit['valido_desde'] ?? '') !== $from
                || (string) ($audit['valido_hasta'] ?? '') !== $to
                || (($audit['id_plantilla'] === null ? null : (int) $audit['id_plantilla']) !== $auditTemplateId)
                || (($audit['version_plantilla'] === null ? null : (int) $audit['version_plantilla']) !== $auditTemplateVersion)
                || (int) ($details['coverage_revision_id'] ?? 0) !== $revisionId
                || (string) ($details['coverage_uuid'] ?? '') !== (string) ($revision['coverage_uuid'] ?? '')
                || (string) ($details['source'] ?? '') !== 'WORKPLACE_COVERAGE') return $conflict;
            $auditByAssignment[$auditAssignmentId] = true;
        }
        foreach ($assignments as $dayAssignment) {
            if (!isset($auditByAssignment[(int) ($dayAssignment['id'] ?? 0)])) return $conflict;
        }
        $assignmentId = (int) ($assignment['id'] ?? 0);
        if ($assignmentId < 1 || !isset($auditByAssignment[$assignmentId])) return $conflict;

        return [
            'status' => 'authorized', 'worker_id' => $workerId,
            'worker_profile' => [
                'worker_id' => $workerId,
                'full_name' => trim((string) ($person->nombre_completo ?? '')) ?: 'Nombre no disponible',
                'employee_number' => $person->no_colaborador === null ? null : (string) $person->no_colaborador,
            ],
            // M01 needs only the effective locality and rendered schedule.
            // Source identifiers stay internal to this verification.
            'effective_workplace_id' => $localityId,
            'schedule_kind' => $entryType, 'template' => $template,
        ];
    }

    private function coverageSchemaReady(Connection $connection): bool
    {
        $required = [
            'op_rh_v2_worker_workplace_coverage_versions' => [
                'id','coverage_uuid','revision_no','supersedes_id','id_personal','id_home_workplace',
                'id_home_membership_version','id_coverage_workplace','fecha_desde_local','fecha_hasta_local_exclusiva',
                'timezone_iana','proposed_schedule_json','status','requested_at_utc',
            ],
            'op_rh_v2_turnos_asignaciones' => [
                'id','id_personal','id_estacion','id_plantilla','tipo_asignacion','dia_semana','valido_desde',
                'valido_hasta','es_activo','id_cobertura_version',
                'creado_por_usuario_id',
            ],
            'op_rh_v2_turnos_audit_log' => [
                'id','entity_type','entity_id','operacion','id_estacion','id_personal','tipo_asignacion',
                'valido_desde','valido_hasta','id_plantilla','version_plantilla','detalle_json',
            ],
            'op_rh_v2_turnos_plantillas' => ['id','version','nombre','scope','id_estacion'],
            'op_rh_v2_turnos_segmentos' => [
                'id_plantilla','ordinal','tipo_segmento','hora_inicio_local','hora_fin_local','dia_offset_inicio','dia_offset_fin',
            ],
            'op_rh_personal_localidad_version' => [
                'id','worker_id','workplace_id','valid_from_utc','valid_to_utc','recorded_at_utc',
            ],
            'op_rh_personal' => ['id','nombre_completo','no_colaborador'],
            'op_rh_localidades' => ['id','numlista'],
            'tb_estaciones' => ['id','numlista','estatus'],
            'op_rh_v2_localidades_zonas_horarias' => ['id_localidad','timezone_iana','valido_desde','valido_hasta'],
        ];
        $schema = $connection->getSchemaBuilder();
        foreach ($required as $table => $columns) {
            if (!$schema->hasTable($table) || !$schema->hasColumns($table, $columns)) return false;
        }
        return true;
    }

    /** @param array<string,array<string,mixed>> $assignmentsByDay */
    private function findCoverageAssignmentById(array $assignmentsByDay, int $assignmentId): ?array
    {
        foreach ($assignmentsByDay as $assignment) {
            if ((int) ($assignment['id'] ?? 0) === $assignmentId) return $assignment;
        }
        return null;
    }

    /** @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string}} */
    private function resolveLocalDay(Connection $connection, int $localityId, string $localDate): array
    {
        if (!$this->schemaReady($connection)) {
            return self::failure('schema_unavailable');
        }
        $mapping = $connection->select(
            'SELECT locality.id AS locality_id, locality.numlista, station.id AS physical_station_id, station.estatus '
            . 'FROM op_rh_localidades AS locality '
            . 'INNER JOIN tb_estaciones AS station ON station.numlista = locality.numlista '
            . 'WHERE locality.id = ? ORDER BY station.id LIMIT 2',
            [$localityId]
        );
        if (count($mapping) !== 1
            || (int) ($mapping[0]->locality_id ?? 0) !== $localityId
            || (int) ($mapping[0]->numlista ?? 0) < 1
            || (int) ($mapping[0]->estatus ?? -1) !== 0) {
            return self::failure('station_mapping_unavailable');
        }
        $sameNumber = $connection->select(
            'SELECT id FROM op_rh_localidades WHERE numlista = ? ORDER BY id LIMIT 2',
            [(int) $mapping[0]->numlista]
        );
        if (count($sameNumber) !== 1 || (int) ($sameNumber[0]->id ?? 0) !== $localityId) {
            return self::failure('station_mapping_unavailable');
        }

        $zones = $connection->select(
            'SELECT timezone_iana FROM op_rh_v2_localidades_zonas_horarias '
            . 'WHERE id_localidad = ? AND valido_desde <= ? '
            . 'AND (valido_hasta IS NULL OR valido_hasta > ?) ORDER BY valido_desde DESC LIMIT 2',
            [$localityId, $localDate, $localDate]
        );
        if (count($zones) !== 1) {
            return self::failure('timezone_unresolved');
        }
        $timezone = trim((string) ($zones[0]->timezone_iana ?? ''));
        if (!ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
            return self::failure('timezone_unresolved');
        }
        try {
            $zone = new \DateTimeZone($timezone);
            $fromLocal = new \DateTimeImmutable($localDate . ' 00:00:00', $zone);
            if ($fromLocal->format('Y-m-d') !== $localDate) {
                return self::failure('timezone_unresolved');
            }
            $toLocal = $fromLocal->modify('+1 day');
            return [
                'ok' => true,
                'data' => [
                    'locality_id' => $localityId,
                    'physical_station_id' => (int) $mapping[0]->physical_station_id,
                    'timezone' => $timezone,
                    'from_utc' => $fromLocal->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'to_utc' => $toLocal->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ],
            ];
        } catch (Throwable) {
            return self::failure('timezone_unresolved');
        }
    }

    private function schemaReady(Connection $connection): bool
    {
        $required = [
            'op_rh_localidades' => ['id', 'numlista'],
            'tb_estaciones' => ['id', 'numlista', 'estatus'],
            'op_rh_v2_localidades_zonas_horarias' => ['id_localidad', 'timezone_iana', 'valido_desde', 'valido_hasta'],
            'op_rh_v2_marcaciones_crudas' => [
                'id', 'event_uuid', 'id_dispositivo', 'id_estacion_resuelta', 'id_personal_resuelto',
                'source_timestamp_raw', 'source_timezone_reported', 'effective_timezone',
                'timezone_fallback_applied', 'ocurrido_at_utc', 'recibido_at_utc', 'metodo_captura',
                'external_event_id', 'secuencia_dispositivo', 'processing_status',
                'integrity_version', 'integrity_key_id', 'integrity_seal',
            ],
            'op_rh_personal' => ['id', 'id_estacion', 'nombre_completo', 'no_colaborador', 'estado'],
            'op_rh_personal_localidad_version' => [
                'id', 'worker_id', 'workplace_id', 'valid_from_utc', 'valid_to_utc', 'recorded_at_utc',
            ],
        ];
        $schema = $connection->getSchemaBuilder();
        foreach ($required as $table => $columns) {
            if (!$schema->hasTable($table) || !$schema->hasColumns($table, $columns)) {
                return false;
            }
        }
        return true;
    }

    /** @param list<int> $authorizedLocalityIds */
    private function isAuthorized(int $localityId, array $authorizedLocalityIds): bool
    {
        if ($localityId < 1 || !in_array($localityId, $authorizedLocalityIds, true)) {
            return false;
        }
        if ($this->authorizeLocality !== null) {
            return (bool) ($this->authorizeLocality)($localityId);
        }
        return Auth::user() !== null
            && (ChecadorAccessService::authorize($localityId)['allowed'] ?? false) === true;
    }

    private function readConnection(): Connection
    {
        if ($this->testConnection !== null) {
            return $this->testConnection;
        }
        $connection = Capsule::connection('default');
        $config = $connection->getConfig();
        $identity = $connection->selectOne(
            'SELECT DATABASE() AS schema_name, @@hostname AS server_hostname, @@port AS server_port, CURRENT_USER() AS principal'
        );
        if (self::approvedRuntimeProfile(self::environment(), $config, $identity) === null) {
            throw new \RuntimeException('M01 SQL connection identity is outside the approved Fedora/preproduction targets.');
        }
        self::verifySelectPermissions($connection);
        return $connection;
    }

    private static function verifySelectPermissions(Connection $connection): void
    {
        foreach (self::READ_TABLES as $table) {
            // A zero-row SELECT confirms effective table-level SELECT without reading business data.
            $connection->table($table)->selectRaw('1')->limit(0)->get();
        }
    }

    /**
     * Validate the configured and effective identity for one of the two approved destinations.
     * Preproduction is bound to its exact schema and approved runtime environment, not to a host name.
     *
     * @param array<string,mixed> $config
     */
    private static function approvedRuntimeProfile(string $environment, array $config, object $identity): ?string
    {
        $environment = strtolower(trim($environment));
        $driver = strtolower(trim((string) ($config['driver'] ?? '')));
        $configuredDatabase = trim((string) ($config['database'] ?? ''));
        $effectiveDatabase = trim((string) ($identity->schema_name ?? ''));
        $configuredPort = (int) ($config['port'] ?? 3306);
        if ($configuredPort < 1) $configuredPort = 3306;
        $effectivePort = (int) ($identity->server_port ?? 0);
        $serverHostname = strtolower(trim((string) ($identity->server_hostname ?? '')));
        $principal = trim((string) ($identity->principal ?? ''));
        if ($driver !== 'mysql'
            || $configuredDatabase === ''
            || $effectiveDatabase !== $configuredDatabase
            || $serverHostname === ''
            || $principal === ''
            || $effectivePort !== $configuredPort) {
            return null;
        }

        $configuredHost = strtolower(trim((string) ($config['host'] ?? '')));
        if (in_array($environment, ['dev', 'development', 'local'], true)
            && $configuredDatabase === 'bd_admongas'
            && in_array($configuredHost, ['localhost', '127.0.0.1', '::1'], true)
            && $serverHostname === 'fedora'
            && $configuredPort === 3306
            && $effectivePort === 3306) {
            return 'fedora';
        }

        if (in_array($environment, ['preprod', 'preproduction', 'staging', 'prod', 'production'], true)
            && $configuredDatabase === 'bd_admongas_v2_preprod'
            && $effectiveDatabase === 'bd_admongas_v2_preprod') {
            return 'preproduction';
        }

        return null;
    }

    private function logFailure(string $stage, int $localityId, ?string $date, Throwable $exception): void
    {
        try {
            $sqlState = $exception instanceof \Illuminate\Database\QueryException
                ? (string) ($exception->errorInfo[0] ?? '')
                : '';
            Logger::error('Checador M01 SQL read failed', [
                'stage' => $stage,
                'reason' => $exception instanceof \Illuminate\Database\QueryException ? 'sql_read_failed' : 'read_unavailable',
                'exception_class' => $exception::class,
                'sqlstate' => preg_match('/\A[A-Z0-9]{5}\z/', $sqlState) === 1 ? $sqlState : null,
                'locality_id' => $localityId,
                'work_date' => $date,
            ]);
        } catch (Throwable) {
            // Diagnostics must not alter the safe read result.
        }
    }

    private static function formatLocalTime(string $utcValue, string $timezone): ?array
    {
        if ($utcValue === '') {
            return null;
        }
        try {
            $local = (new \DateTimeImmutable($utcValue, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone($timezone));
            return ['date' => $local->format('Y-m-d'), 'time' => $local->format('H:i'), 'utc' => $utcValue];
        } catch (Throwable) {
            return null;
        }
    }

    private static function isCivilDate(string $date): bool
    {
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches) !== 1) {
            return false;
        }
        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private static function environment(): string
    {
        return (string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '');
    }

    private static function positiveInteger(mixed $value): ?int
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $validated === false ? null : (int) $validated;
    }

    /** @return array{ok:false,error:array{kind:string}} */
    private static function failure(string $kind): array
    {
        return ['ok' => false, 'error' => ['kind' => $kind]];
    }
}

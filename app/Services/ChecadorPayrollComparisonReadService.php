<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\ModuloDptoOperativo;
use App\Models\Operativo\OpReciboNominaV2;
use App\Models\Usuario;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Read-only, single-worker comparison of canonical Checador evidence and
 * non-monetary payroll-period document references. This intentionally does
 * not call RecibosNominaService::getNominaData(), whose legacy path can create
 * missing payroll rows while reading.
 */
final class ChecadorPayrollComparisonReadService
{
    public const MAX_PERIOD_DAYS = 16;
    public const MAX_EVENTS = 5000;
    private const PAYROLL_PARENT_KEY = 'recursos-humanos';

    /**
     * Check the effective read boundaries for both modules and their locality
     * intersection. A requested locality is a filter only; both ACLs are
     * re-read on every request.
     *
     * @return array{allowed:bool,reason:string,workplaces?:array<int,array{id:int,nombre:string}>}
     */
    public function authorization(?int $requestedLocalityId = null): array
    {
        $deny = static fn(string $reason): array => ['allowed' => false, 'reason' => $reason];

        if (!self::isApprovedLocalDevelopmentDatabase()) {
            return $deny('development_database_unavailable');
        }
        if (!Auth::check() || Auth::user() === null) {
            return $deny('unauthenticated');
        }

        try {
            $sessionUser = Auth::user();
            $user = Usuario::query()
                ->where('id', (int) $sessionUser->id)
                ->where('estatus', 0)
                ->first();
            if (!$user) {
                return $deny('inactive_user');
            }

            $checador = ChecadorAccessService::authorize();
            if (empty($checador['allowed'])) {
                return $deny('checador_read_denied');
            }

            // Match RecibosNominaController's real read ACL: fresh effective
            // parent-module grant plus the existing payroll locality boundary.
            $payrollParent = ModuloDptoOperativoService::getPermiso(
                (int) $user->id,
                self::PAYROLL_PARENT_KEY
            );
            if (empty($payrollParent['leer'])
                || !ModuloDptoOperativo::query()
                    ->where('id', (int) ($payrollParent['id_modulo'] ?? 0))
                    ->where('clave', self::PAYROLL_PARENT_KEY)
                    ->where('activo', 1)
                    ->exists()) {
                return $deny('payroll_read_denied');
            }

            $checadorIds = array_map('intval', array_column($checador['workplaces'] ?? [], 'id'));
            $payrollIds = array_values(array_unique(array_map(
                'intval',
                RecibosNominaService::getIdsLocalidadPermitidos()
            )));
            if (!ModuleStationService::isAvailable(RecibosNominaService::KEY_MODULO)) {
                return $deny('payroll_locality_configuration_unavailable');
            }

            $allowedIds = self::combinedLocalityIds(
                true,
                !empty($payrollParent['leer']),
                $checadorIds,
                $payrollIds
            );
            if ($requestedLocalityId !== null) {
                if ($requestedLocalityId < 1 || !in_array($requestedLocalityId, $allowedIds, true)) {
                    return $deny('locality_out_of_combined_scope');
                }
                $allowedIds = [$requestedLocalityId];
            }

            $workplaceById = [];
            foreach ($checador['workplaces'] ?? [] as $workplace) {
                $id = (int) ($workplace['id'] ?? 0);
                if ($id > 0 && in_array($id, $allowedIds, true)) {
                    $workplaceById[$id] = [
                        'id' => $id,
                        'nombre' => (string) ($workplace['nombre'] ?? 'Localidad autorizada'),
                    ];
                }
            }

            return [
                'allowed' => $workplaceById !== [],
                'reason' => $workplaceById !== [] ? 'authorized' : 'empty_combined_scope',
                'workplaces' => array_values($workplaceById),
            ];
        } catch (Throwable) {
            return $deny('authorization_lookup_failed');
        }
    }

    /**
     * Execute a single canonical worker + payroll period comparison.
     *
     * @param array<string,mixed> $input Untrusted GET filters.
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string}}
     */
    public function compare(array $input): array
    {
        $localityId = self::positiveInt($input['workplace_id'] ?? null);
        $workerId = self::positiveInt($input['worker_id'] ?? null);
        $year = self::positiveInt($input['year'] ?? null);
        $periodNumber = self::positiveInt($input['period_number'] ?? null);
        if ($localityId === null || $workerId === null || $year === null || $periodNumber === null) {
            return self::failure('filters_required');
        }

        $authorization = $this->authorization($localityId);
        if (empty($authorization['allowed'])) {
            return self::failure('forbidden');
        }

        $periodType = is_string($input['period_type'] ?? null) ? trim($input['period_type']) : '';
        $period = self::resolvePeriod(
            $localityId,
            $year,
            $periodType,
            $periodNumber
        );
        if ($period === null) {
            return self::failure('invalid_period');
        }

        $roster = $this->workerOptions($localityId, $period['start_date'], $period['end_date']);
        $worker = $roster['workers'][$workerId] ?? null;
        if (($roster['status'] ?? '') === 'unavailable' || !is_array($worker)
            || !self::workerIdentityMatches($workerId, (int) ($worker['worker_id'] ?? 0), $localityId, (int) ($roster['workplace_id'] ?? 0))) {
            return self::failure('worker_not_available');
        }

        try {
            $payroll = $this->readPayrollReference($localityId, $workerId, $period);
        } catch (Throwable) {
            $payroll = ['status' => 'unavailable', 'references' => []];
        }

        $workplace = $authorization['workplaces'][0] ?? ['id' => $localityId, 'nombre' => 'Localidad autorizada'];
        $data = [
            'worker' => [
                'id' => (int) $worker['worker_id'],
                'employee_number' => $worker['employee_number'] ?? null,
                'name' => (string) $worker['full_name'],
                'workplace_id' => $localityId,
            ],
            'workplace' => $workplace,
            'period' => $period,
            'payroll' => $payroll,
            'attendance' => self::unavailableAttendance('contract_pending'),
            'workplace_timezones' => ['status' => 'contract_pending', 'days' => []],
            'schedules' => ['status' => 'contract_pending', 'days' => []],
            'journeys' => ['status' => 'unavailable', 'journeys' => [], 'message' => 'La lectura persistida de jornadas no está disponible para este periodo.'],
            'incidences' => ['status' => 'contract_pending', 'items' => []],
            'evaluations' => ['status' => 'contract_pending', 'days' => []],
            'adjustments' => ['status' => 'contract_pending', 'items' => []],
            'comparison_limit' => 'La consulta es informativa. No prueba cobertura completa, no crea un periodo común y no produce conclusiones de asistencia o nómina.',
        ];
        $data['attendance']['message'] = 'La consulta muestra eventos observados; no clasifica marcaciones ni determina cobertura del periodo.';

        // Do not mix synthetic API fixtures with real local SQL or payroll
        // records. The mode remains untouched; this page fails closed while it
        // is active and can be used only with real development sources.
        if (ChecadorDailyReadService::demoModeEnabled()) {
            $data['attendance'] = self::unavailableAttendance('demo_mode_active');
            $data['workplace_timezones']['status'] = 'demo_mode_active';
            $data['schedules']['status'] = 'demo_mode_active';
            $data['journeys']['status'] = 'demo_mode_active';
            $data['incidences']['status'] = 'demo_mode_active';
            $data['evaluations']['status'] = 'demo_mode_active';
            $data['adjustments']['status'] = 'demo_mode_active';
            return ['ok' => true, 'data' => $data];
        }

        // C01 already approves this bounded, read-only SQL projection of
        // persisted P1/P2 results. It never evaluates or recalculates journeys.
        $journeyResult = (new ChecadorJourneySqlReadService())->read(
            $localityId,
            $period['start_date'],
            $period['end_date'],
            $workerId,
            1,
            100
        );
        if (!empty($journeyResult['ok'])) {
            $journeys = array_values($journeyResult['data']['journeys'] ?? []);
            $journeysByDate = [];
            foreach ($journeys as $journey) {
                if (!is_array($journey)) continue;
                $date = (string) ($journey['context']['fecha_local'] ?? '');
                if ($date !== '') $journeysByDate[$date] = $journey;
            }
            $periodDates = self::dateRange($period['start_date'], $period['end_date']);
            $timezoneDays = [];
            $scheduleDays = [];
            foreach ($periodDates as $date) {
                $journey = $journeysByDate[$date] ?? null;
                $context = is_array($journey['context'] ?? null) ? $journey['context'] : [];
                $schedule = is_array($journey['schedule'] ?? null) ? $journey['schedule'] : [];
                $timezone = trim((string) ($context['timezone'] ?? ''));
                if ($journey !== null && $timezone !== '') {
                    $timezoneDays[] = ['date' => $date, 'timezone' => $timezone];
                }
                $scheduleDays[] = [
                    'date' => $date,
                    'status' => $journey === null ? 'no_persisted_journey_snapshot' : 'persisted_journey_snapshot',
                    'timezone' => $timezone,
                    'segments_snapshot_json' => $schedule['payload_segmentos_json'] ?? null,
                ];
            }
            $snapshotCount = count($timezoneDays);
            $snapshotStatus = $journeys === []
                ? 'no_persisted_journeys'
                : ($snapshotCount === count($periodDates) ? 'persisted_journey_snapshot' : 'partial_persisted_journey_snapshots');
            $data['journeys'] = [
                'status' => $journeys === [] ? 'no_persisted_journeys' : 'available',
                'journeys' => $journeys,
                'message' => $journeys === [] ? 'No existen jornadas procesadas persistidas para este trabajador y periodo.' : '',
                'pagination' => $journeyResult['data']['pagination'] ?? [],
            ];
            $data['workplace_timezones'] = [
                'status' => $snapshotStatus,
                'days' => $timezoneDays,
            ];
            $data['schedules'] = [
                'status' => $snapshotStatus,
                'days' => $scheduleDays,
            ];
        } else {
            $journeyError = (string) ($journeyResult['error'] ?? 'sql_unavailable');
            $data['workplace_timezones'] = ['status' => $journeyError, 'days' => []];
            $data['schedules'] = ['status' => $journeyError, 'days' => []];
            $data['journeys'] = [
                'status' => $journeyError,
                'journeys' => [],
                'message' => 'No fue posible consultar las jornadas persistidas. No se ejecutó una evaluación nueva.',
            ];
        }

        $dates = self::dateRange($period['start_date'], $period['end_date']);
        $timezoneData = $this->readHistoricalTimezones($localityId, $dates);
        $data['workplace_timezones'] = [
            'status' => $timezoneData['status'],
            'source' => 'op_rh_v2_localidades_zonas_horarias',
            'days' => $timezoneData['days'],
        ];
        $recordedAsOf = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $data['attendance'] = $this->readObservedPunches(
            $localityId, $workerId, $dates, $timezoneData['zones'], $recordedAsOf
        );

        $actor = Auth::user();
        if (!$actor instanceof Usuario) {
            $data['incidences'] = ['status' => 'permission_denied', 'items' => [], 'excluded_unverified_count' => 0];
            $data['evaluations'] = ['status' => 'permission_denied', 'days' => [], 'excluded_unverified_count' => 0];
            $data['adjustments'] = ['status' => 'permission_denied', 'items' => []];
        } else {
            $data['incidences'] = $this->readAuthorizedIncidences(
                $actor, $localityId, $workerId, $period, $timezoneData['zones'], $recordedAsOf
            );
            $evaluationReader = new ChecadorAdjustmentSqlReadService();
            $evaluationResult = $evaluationReader->evaluationsForWorkerPeriod(
                $actor, $localityId, $workerId, $period['start_date'], $period['end_date']
            );
            if (!empty($evaluationResult['ok'])) {
                $evaluationItems = $evaluationResult['data']['items'] ?? [];
                $data['evaluations'] = [
                    'status' => !empty($evaluationResult['data']['excluded_unverified_count']) ? 'partial' : 'available',
                    'source' => 'op_rh_v2_evaluaciones_administrativas',
                    'days' => $evaluationItems,
                    'excluded_unverified_count' => (int) ($evaluationResult['data']['excluded_unverified_count'] ?? 0),
                ];
            } else {
                $data['evaluations'] = ['status' => (string) ($evaluationResult['error'] ?? 'sql_read_failed'), 'days' => [], 'excluded_unverified_count' => 0];
            }
            $data['adjustments'] = $this->readLinkedAdjustments(
                $evaluationReader, $actor, $localityId, $workerId, $period
            );
        }

        return ['ok' => true, 'data' => $data];
    }

    /**
     * Real worker options from the canonical roster, independent of journey rows.
     * Historical periods include only workers with confirmed membership on at
     * least one queried day. No physical-station ID is compared to locality ID.
     *
     * @return array{status:string,workplace_id:int,workers:array<int,array{worker_id:int,full_name:string,employee_number:?string}>,message:string}
     */
    public function workerOptions(int $localityId, ?string $startDate = null, ?string $endDate = null): array
    {
        $empty = static fn(string $status, string $message): array => [
            'status' => $status, 'workplace_id' => $localityId, 'workers' => [], 'message' => $message,
        ];
        if (empty($this->authorization($localityId)['allowed'])) {
            return $empty('forbidden', 'No se pudo autorizar el padrón solicitado.');
        }

        $dates = [];
        if ($startDate !== null || $endDate !== null) {
            $start = $startDate === null ? null : self::civilDate($startDate);
            $end = $endDate === null ? null : self::civilDate($endDate);
            if ($start === null || $end === null || $end < $start
                || (int) $start->diff($end)->format('%a') + 1 > self::MAX_PERIOD_DAYS) {
                return $empty('invalid_period', 'No se pudo resolver el periodo para validar adscripciones históricas.');
            }
            for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) $dates[] = $day->format('Y-m-d');
        } else {
            $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $timezone = (new ChecadorWeeklyScheduleSqlReadService())->timezoneForDate($localityId, $nowUtc->format('Y-m-d'));
            if ($timezone === null || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                return $empty('unavailable', 'No se pudo validar la zona horaria para cargar el padrón autorizado.');
            }
            $dates[] = $nowUtc->setTimezone(new \DateTimeZone($timezone))->format('Y-m-d');
        }

        $timezoneReader = new ChecadorWeeklyScheduleSqlReadService();
        $rosterReader = new ChecadorAttendanceRosterService();
        $workers = [];
        $incomplete = false;
        $usableDates = 0;
        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        foreach ($dates as $date) {
            $timezone = $timezoneReader->timezoneForDate($localityId, $date);
            if ($timezone === null || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                $incomplete = true;
                continue;
            }
            $roster = $rosterReader->workersForAttendanceFilter($localityId, $date, $timezone, false);
            $coverage = (string) ($roster['coverage'] ?? 'unknown');
            $currentDay = ChecadorOperationalPeriod::currentLocalDate($timezone, $nowUtc) === $date;
            $coverageUsable = in_array($coverage, ['historical_versioned_membership', 'incomplete'], true)
                || ($coverage === 'current_active_roster' && $currentDay);
            if (!in_array((string) ($roster['status'] ?? ''), ['ready', 'partial'], true) || !$coverageUsable) {
                $incomplete = true;
                continue;
            }
            $usableDates++;
            foreach ($roster['workers'] ?? [] as $worker) {
                if (!is_array($worker)) continue;
                $workerId = (int) ($worker['worker_id'] ?? 0);
                $name = trim((string) ($worker['full_name'] ?? ''));
                if ($workerId < 1 || $name === '' || $name === 'Nombre no disponible') continue;
                $number = trim((string) ($worker['employee_number'] ?? ''));
                $workers[$workerId] = [
                    'worker_id' => $workerId,
                    'full_name' => $name,
                    'employee_number' => $number !== '' && $number !== '0' ? $number : null,
                ];
            }
            if (($roster['status'] ?? '') !== 'ready') $incomplete = true;
        }
        uasort($workers, static fn(array $a, array $b): int =>
            strcasecmp($a['full_name'], $b['full_name']) ?: ($a['worker_id'] <=> $b['worker_id'])
        );
        $status = $usableDates === 0 ? 'unavailable' : ($incomplete ? 'partial' : 'ready');
        $message = $status === 'unavailable'
            ? 'No se confirmó el contexto local del periodo; no se cargó el padrón.'
            : ($incomplete
                ? 'El padrón puede estar incompleto; sólo se incluyen adscripciones verificadas.'
                : ($workers === [] ? 'No se encontraron trabajadores con adscripción canónica confirmada.' : 'Trabajadores autorizados para el contexto seleccionado.'));
        return ['status' => $status, 'workplace_id' => $localityId, 'workers' => $workers, 'message' => $message];
    }

    /** @return array{type:string,label:string,number:int,month:int,start_date:string,end_date:string,year:int} */
    public static function resolvePeriod(int $localityId, int $year, string $periodType, int $periodNumber): ?array
    {
        if ($localityId < 1 || $year < 2024 || $year > (int) date('Y') || $periodNumber < 1) {
            return null;
        }

        $isWeekly = RecibosNominaService::esSemanal($localityId);
        $expectedType = $isWeekly ? 'week' : 'fortnight';
        if ($periodType !== $expectedType) {
            return null;
        }
        if (($isWeekly && $periodNumber > RecibosNominaService::getTotalSemanasPeriodo($year))
            || (!$isWeekly && $periodNumber > 24)) {
            return null;
        }

        $range = $isWeekly
            ? RecibosNominaService::getRangoFechasSemana($year, $periodNumber)
            : RecibosNominaService::getRangoFechasQuincena($year, $periodNumber);
        $start = (string) ($range['inicio'] ?? '');
        $end = (string) ($range['fin'] ?? '');
        $startDate = self::civilDate($start);
        $endDate = self::civilDate($end);
        if ($startDate === null || $endDate === null || $endDate < $startDate) {
            return null;
        }
        $days = (int) $startDate->diff($endDate)->format('%a') + 1;
        if ($days < 1 || $days > self::MAX_PERIOD_DAYS) {
            return null;
        }

        return [
            'type' => $expectedType,
            'label' => $isWeekly ? 'Semana' : 'Quincena',
            'number' => $periodNumber,
            'month' => (int) ($range['mes'] ?? 0),
            'start_date' => $start,
            'end_date' => $end,
            'year' => $year,
        ];
    }

    /**
     * Return only canonical localities authorized by both read boundaries.
     * These lists come from server-side ACL/configuration services, never the
     * request. Keeping the intersection pure also makes its denial cases
     * directly testable without a database connection.
     *
     * @param array<int,mixed> $checadorIds
     * @param array<int,mixed> $payrollIds
     * @return array<int,int>
     */
    public static function combinedLocalityIds(
        bool $checadorRead,
        bool $payrollRead,
        array $checadorIds,
        array $payrollIds
    ): array {
        if (!$checadorRead || !$payrollRead) {
            return [];
        }

        $normalize = static function (array $ids): array {
            $normalized = [];
            foreach ($ids as $id) {
                if (is_int($id) || (is_string($id) && preg_match('/\\A[1-9][0-9]{0,8}\\z/', $id))) {
                    $value = (int) $id;
                    if ($value > 0) {
                        $normalized[] = $value;
                    }
                }
            }
            return array_values(array_unique($normalized));
        };

        return array_values(array_intersect($normalize($checadorIds), $normalize($payrollIds)));
    }

    public static function workerIdentityMatches(
        int $requestedWorkerId,
        int $canonicalWorkerId,
        int $requestedLocalityId,
        int $canonicalWorkerLocalityId
    ): bool {
        return $requestedWorkerId > 0
            && $requestedLocalityId > 0
            && $requestedWorkerId === $canonicalWorkerId
            && $requestedLocalityId === $canonicalWorkerLocalityId;
    }

    /** @return array{status:string,references:array<int,array<string,mixed>>} */
    private function readPayrollReference(int $localityId, int $workerId, array $period): array
    {
        try {
            $rows = OpReciboNominaV2::query()
                ->select([
                    'id',
                    'year',
                    'mes',
                    'no_semana_quincena',
                    'descripcion',
                    'id_estacion',
                    'id_usuario',
                    'doc_nomina',
                    'doc_nomina_firma',
                    'doc_nomina_aguinaldo',
                    'nomina_original',
                ])
                ->where('id_estacion', $localityId)
                ->where('id_usuario', $workerId)
                ->where('year', $period['year'])
                ->where('mes', $period['month'])
                ->where('no_semana_quincena', $period['number'])
                ->where('descripcion', $period['label'])
                ->limit(2)
                ->get();

            if ($rows->count() > 1) {
                return ['status' => 'ambiguous', 'references' => []];
            }
            if ($rows->isEmpty()) {
                return ['status' => 'no_reference_found', 'references' => []];
            }

            $row = $rows->first();
            return [
                'status' => 'reference_found',
                'references' => [[
                    'record_id' => (int) $row->id,
                    'period_year' => (int) $row->year,
                    'period_month' => (int) $row->mes,
                    'period_number' => (int) $row->no_semana_quincena,
                    'period_type' => (string) $row->descripcion,
                    'workplace_id' => (int) $row->id_estacion,
                    'worker_id' => (int) $row->id_usuario,
                    'documents' => [
                        'payroll_document' => trim((string) ($row->doc_nomina ?? '')) !== '',
                        'signature_document' => trim((string) ($row->doc_nomina_firma ?? '')) !== '',
                        'bonus_document' => trim((string) ($row->doc_nomina_aguinaldo ?? '')) !== '',
                        'original_reference' => trim((string) ($row->nomina_original ?? '')) !== '',
                    ],
                ]],
            ];
        } catch (Throwable) {
            return ['status' => 'unavailable', 'references' => []];
        }
    }

    /** @return array{status:string,days:array,zones:array<string,string>} */
    private function readHistoricalTimezones(int $localityId, array $dates): array
    {
        $reader = new ChecadorWeeklyScheduleSqlReadService();
        $days = [];
        $zones = [];
        foreach ($dates as $date) {
            $timezone = $reader->timezoneForDate($localityId, $date);
            $valid = is_string($timezone) && ChecadorOperationalPeriod::isIanaTimezone($timezone);
            $days[] = ['date' => $date, 'timezone' => $valid ? $timezone : null, 'status' => $valid ? 'available' : 'unavailable'];
            if ($valid) $zones[$date] = $timezone;
        }
        return [
            'status' => $zones === [] ? 'unavailable' : (count($zones) === count($dates) ? 'available' : 'partial'),
            'days' => $days,
            'zones' => $zones,
        ];
    }

    /** Read raw observed events as events only; never classify or aggregate them into work time. */
    private function readObservedPunches(int $localityId, int $workerId, array $dates, array $zones, string $recordedAsOf): array
    {
        $events = [];
        $failed = false;
        $successfulDays = 0;
        $skippedDays = count($dates) - count($zones);
        $unverifiedMembershipDays = 0;
        $reader = new ChecadorRawPunchReadService();
        $windows = [];
        $totalRecords = 0;
        foreach ($dates as $date) {
            $timezoneName = $zones[$date] ?? null;
            if (!is_string($timezoneName)) continue;
            try {
                $timezone = new DateTimeZone($timezoneName);
                $localStart = new DateTimeImmutable($date . ' 00:00:00', $timezone);
                $localEnd = $localStart->modify('+1 day');
                $fromUtc = $localStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $toUtc = $localEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } catch (Throwable) {
                $failed = true;
                continue;
            }

            if (!$this->membershipMatchesLocalDate($workerId, $localityId, $date, $timezoneName, $recordedAsOf)) {
                $unverifiedMembershipDays++;
                continue;
            }

            $first = $reader->readPage($localityId, $fromUtc, $toUtc, $workerId, 1, ChecadorRawPunchReadService::MAX_PAGE_SIZE);
            if (empty($first['ok'])) {
                $failed = true;
                continue;
            }
            $pagination = $first['pagination'] ?? [];
            $total = (int) ($pagination['total_records'] ?? 0);
            $pages = (int) ($pagination['total_pages'] ?? 0);
            if ($total < 0 || $pages < 0) {
                $failed = true;
                continue;
            }
            $successfulDays++;
            $totalRecords += $total;
            if ($totalRecords > self::MAX_EVENTS) {
                return [
                    'status' => 'result_limit_exceeded',
                    'events' => [],
                    'message' => 'La consulta de eventos observados supera el límite permitido; no se muestran resultados parciales.',
                    'consistency' => 'LIVE_NON_SNAPSHOT',
                    'excluded_unresolved_timezone_days' => $skippedDays,
                ];
            }
            $windows[] = ['date' => $date, 'from_utc' => $fromUtc, 'to_utc' => $toUtc, 'first' => $first, 'pages' => $pages];
        }

        foreach ($windows as $window) {
            $date = $window['date'];
            $pageCount = (int) $window['pages'];
            for ($page = 1; $page <= $pageCount; $page++) {
                $result = $page === 1 ? $window['first'] : $reader->readPage(
                    $localityId, $window['from_utc'], $window['to_utc'], $workerId, $page, ChecadorRawPunchReadService::MAX_PAGE_SIZE
                );
                if (empty($result['ok'])) {
                    $failed = true;
                    break;
                }
                foreach ($result['events'] ?? [] as $event) {
                    $eventId = (string) ($event['event_uuid'] ?? '');
                    if ($eventId === '') continue;
                    $events[$eventId] = [
                        'query_date_local' => $date,
                        'event_uuid' => $eventId,
                        'id_dispositivo' => isset($event['id_dispositivo']) ? (int) $event['id_dispositivo'] : null,
                        'ocurrido_at_utc' => $event['ocurrido_at_utc'] === null ? null : (string) $event['ocurrido_at_utc'],
                        'recibido_at_utc' => (string) ($event['recibido_at_utc'] ?? ''),
                        'metodo_captura' => (string) ($event['metodo_captura'] ?? ''),
                        'processing_status' => (string) ($event['processing_status'] ?? ''),
                        'occurrence_status' => (string) ($event['occurrence_status'] ?? 'NOT_AVAILABLE'),
                        'integrity_status' => 'NOT_VERIFIED_BY_PORTAL3',
                        'source' => 'op_rh_v2_marcaciones_crudas',
                    ];
                }
            }
        }
        $status = $failed || $skippedDays > 0 || $unverifiedMembershipDays > 0
            ? ($successfulDays === 0 ? 'unavailable' : 'partial')
            : 'available';
        return [
            'status' => $status,
            'events' => array_values($events),
            'message' => $status === 'available' ? '' : 'La consulta de eventos observados quedó incompleta o no disponible; no se infiere asistencia.',
            'consistency' => 'LIVE_NON_SNAPSHOT',
            'excluded_unresolved_timezone_days' => $skippedDays,
            'excluded_unverified_membership_days' => $unverifiedMembershipDays,
        ];
    }

    /** @return array{status:string,items:array,excluded_unverified_count:int} */
    private function readAuthorizedIncidences(Usuario $actor, int $localityId, int $workerId, array $period, array $zones, string $recordedAsOf): array
    {
        $reader = new ChecadorAdministrativeIncidenceSqlService();
        $filters = ['worker_id' => $workerId, 'start_date' => $period['start_date'], 'end_date' => $period['end_date']];
        $items = [];
        $excluded = 0;
        $first = $reader->listForWorkplace($actor, $localityId, $filters, 1, 100);
        if (empty($first['ok'])) return ['status' => (string) ($first['error'] ?? 'sql_read_failed'), 'items' => [], 'excluded_unverified_count' => 0];
        $pagination = $first['data']['pagination'] ?? [];
        $total = (int) ($pagination['total_records'] ?? 0);
        $pages = (int) ($pagination['total_pages'] ?? 0);
        if ($total > self::MAX_EVENTS) return ['status' => 'result_limit_exceeded', 'items' => [], 'excluded_unverified_count' => 0];
        for ($page = 1; $page <= max(1, $pages); $page++) {
            $result = $page === 1 ? $first : $reader->listForWorkplace($actor, $localityId, $filters, $page, 100);
            if (empty($result['ok'])) return ['status' => (string) ($result['error'] ?? 'sql_read_failed'), 'items' => [], 'excluded_unverified_count' => $excluded];
            foreach ($result['data']['items'] ?? [] as $item) {
                if ((int) ($item['worker_id'] ?? 0) !== $workerId || (int) ($item['workplace_id'] ?? 0) !== $localityId) {
                    $excluded++;
                    continue;
                }
                $from = max((string) ($item['start_date'] ?? ''), $period['start_date']);
                $to = min((string) ($item['end_date'] ?? ''), $period['end_date']);
                $fromDate = self::civilDate($from);
                $toDate = self::civilDate($to);
                $valid = $fromDate !== null && $toDate !== null && $toDate >= $fromDate;
                for ($date = $fromDate; $valid && $date !== null && $date <= $toDate; $date = $date->modify('+1 day')) {
                    $localDate = $date->format('Y-m-d');
                    $timezone = $zones[$localDate] ?? null;
                    if (!is_string($timezone) || !$this->membershipMatchesLocalDate($workerId, $localityId, $localDate, $timezone, $recordedAsOf)) {
                        $valid = false;
                    }
                }
                if (!$valid) { $excluded++; continue; }
                $items[] = $item;
            }
        }
        return [
            'status' => $excluded > 0 ? 'partial' : 'available',
            'items' => $items,
            'excluded_unverified_count' => $excluded,
            'source' => ['op_rh_v2_incidencias_maestro', 'op_rh_v2_incidencias_revisiones'],
        ];
    }

    /** @return array{status:string,items:array,excluded_unverified_count:int} */
    private function readLinkedAdjustments(ChecadorAdjustmentSqlReadService $reader, Usuario $actor, int $localityId, int $workerId, array $period): array
    {
        $filters = ['worker_id' => $workerId, 'start_date' => $period['start_date'], 'end_date' => $period['end_date']];
        $result = $reader->listForWorkplace($actor, $localityId, $filters, 1, 100);
        if (empty($result['ok'])) return ['status' => (string) ($result['error'] ?? 'sql_read_failed'), 'items' => []];
        $pagination = $result['data']['pagination'] ?? [];
        $total = (int) ($pagination['total_records'] ?? 0);
        $pages = (int) ($pagination['total_pages'] ?? 0);
        if ($total > self::MAX_EVENTS) return ['status' => 'result_limit_exceeded', 'items' => []];
        $linked = [];
        for ($page = 1; $page <= max(1, $pages); $page++) {
            $pageResult = $page === 1 ? $result : $reader->listForWorkplace($actor, $localityId, $filters, $page, 100);
            if (empty($pageResult['ok'])) return ['status' => (string) ($pageResult['error'] ?? 'sql_read_failed'), 'items' => []];
            foreach ($pageResult['data']['items'] ?? [] as $group) {
                foreach ($group['revisions'] ?? [] as $revision) {
                    $evaluationIds = array_values(array_unique(array_map(
                        static fn(array $link): int => (int) ($link['evaluation_id'] ?? 0),
                        array_filter($revision['evaluation_links'] ?? [], 'is_array')
                    )));
                    $evaluationIds = array_values(array_filter($evaluationIds, static fn(int $id): bool => $id > 0));
                    if ($evaluationIds === []) continue;
                    $uuid = (string) ($revision['uuid'] ?? '');
                    if ($uuid === '') continue;
                    $linked[$uuid] = [
                        'uuid' => $uuid,
                        'type' => (string) ($revision['type'] ?? ''),
                        'status' => (string) ($revision['status'] ?? ''),
                        'revision' => (int) ($revision['revision'] ?? 0),
                        'work_date' => (string) ($group['current']['work_date'] ?? ''),
                        'evaluation_ids' => $evaluationIds,
                        'source' => ['op_rh_v2_ajustes_administrativos', 'op_rh_v2_evaluaciones_ajustes'],
                    ];
                }
            }
        }
        return ['status' => 'available', 'items' => array_values($linked)];
    }

    private function membershipMatchesLocalDate(int $workerId, int $localityId, string $date, string $timezone, string $recordedAsOf): bool
    {
        $effectiveAt = self::localDayStartUtc($date, $timezone);
        if ($effectiveAt === null) return false;
        $membership = (new WorkerWorkplaceAssignmentResolver())->resolveAssignments($workerId, $effectiveAt, $recordedAsOf);
        $assignments = $membership['assignments'] ?? [];
        return ($membership['status'] ?? '') === 'RESOLVED_SINGLE' && count($assignments) === 1
            && (int) ($assignments[0]['workplace_id'] ?? 0) === $localityId;
    }

    private static function localDayStartUtc(string $date, string $timezone): ?string
    {
        if (!self::civilDate($date) || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) return null;
        try {
            return (new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone($timezone)))
                ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        } catch (Throwable) { return null; }
    }

    private static function unavailableAttendance(string $status): array
    {
        return [
            'status' => $status,
            'events' => [],
            'integrity' => 'NOT_VERIFIED_BY_PORTAL3',
            'snapshot' => 'NOT_QUERIED',
            'completeness' => 'NOT_ESTABLISHED',
            'message' => '',
        ];
    }

    /** @return array<int,string> */
    private static function dateRange(string $start, string $end): array
    {
        $from = self::civilDate($start);
        $to = self::civilDate($end);
        if ($from === null || $to === null || $to < $from) {
            return [];
        }
        $dates = [];
        for ($date = $from; $date <= $to; $date = $date->modify('+1 day')) {
            $dates[] = $date->format('Y-m-d');
        }
        return $dates;
    }

    private static function civilDate(string $value): ?\DateTimeImmutable
    {
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
            return null;
        }
        $utc = new \DateTimeZone('UTC');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $utc);
        $errors = \DateTimeImmutable::getLastErrors();
        return $date instanceof \DateTimeImmutable
            && $date->format('Y-m-d') === $value
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ? $date
            : null;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || !preg_match('/\A[1-9][0-9]{0,8}\z/', $value)) {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        return $parsed === false ? null : (int) $parsed;
    }

    /** Do not let the V2/payroll reader reach preproduction or production. */
    private static function isApprovedLocalDevelopmentDatabase(): bool
    {
        try {
            $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
            if (!in_array($environment, ['dev', 'development', 'local'], true)) {
                return false;
            }
            $connection = Capsule::connection();
            $config = $connection->getConfig();
            $host = strtolower(trim((string) ($config['host'] ?? '')));
            if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                || (string) ($config['database'] ?? '') !== 'bd_admongas'
                || (int) ($config['port'] ?? 3306) !== 3306) {
                return false;
            }

            $identity = $connection->selectOne(
                'SELECT DATABASE() AS schema_name, @@hostname AS server_hostname, @@port AS server_port'
            );
            return ($identity->schema_name ?? null) === 'bd_admongas'
                && strtolower((string) ($identity->server_hostname ?? '')) === 'fedora'
                && (int) ($identity->server_port ?? 0) === 3306;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array{ok:false,error:array{kind:string}} */
    private static function failure(string $kind): array
    {
        return ['ok' => false, 'error' => ['kind' => $kind]];
    }
}

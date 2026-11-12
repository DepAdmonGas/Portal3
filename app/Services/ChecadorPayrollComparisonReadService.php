<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\ModuloDptoOperativo;
use App\Models\Operativo\OpReciboNominaV2;
use App\Models\Operativo\RhPersonal;
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

        $workplace = $authorization['workplaces'][0] ?? ['id' => $localityId, 'nombre' => 'Localidad autorizada'];
        if (ChecadorDailyReadService::demoModeEnabled()) {
            return ['ok' => true, 'data' => [
                'worker' => [
                    'id' => $workerId,
                    'employee_number' => null,
                    'name' => '',
                    'workplace_id' => $localityId,
                ],
                'workplace' => $workplace,
                'period' => $period,
                'payroll' => ['status' => 'demo_mode_active', 'references' => []],
                'attendance' => self::unavailableAttendance('demo_mode_active'),
                'workplace_timezones' => ['status' => 'demo_mode_active', 'days' => []],
                'schedules' => ['status' => 'demo_mode_active', 'days' => []],
                'journeys' => [
                    'status' => 'not_queried_read_only_contract',
                    'message' => 'No se consultaron fuentes operativas mientras está activo el modo de demostración.',
                ],
                'incidences' => ['status' => 'demo_mode_active', 'items' => []],
                'evaluations' => ['status' => 'demo_mode_active', 'days' => []],
                'adjustments' => ['status' => 'demo_mode_active', 'items' => []],
                'comparison_limit' => 'La consulta es informativa. No prueba cobertura completa, no crea un periodo común y no produce conclusiones de asistencia o nómina.',
            ]];
        }

        $sessionUser = Auth::user();
        if (!$sessionUser) {
            return self::failure('unauthenticated');
        }
        try {
            $user = Usuario::query()
                ->where('id', (int) $sessionUser->id)
                ->where('estatus', 0)
                ->first();
            if (!$user) {
                return self::failure('unauthenticated');
            }
            $reader = new ChecadorDailyReadService();
        } catch (Throwable) {
            return self::failure('service_unavailable');
        }

        // Prove canonical versioned membership across the complete local-day
        // windows before selecting the worker's name or employee number.
        $periodScope = $this->resolveHistoricalPeriodScope(
            $reader,
            $user,
            $workerId,
            $localityId,
            $period
        );
        if ($periodScope === null) {
            return self::failure('historical_period_access_unavailable');
        }

        try {
            // RH stores the worker's current base in the canonical
            // op_rh_localidades ID space. Physical station IDs are resolved
            // only by consumers of physical-station-valued legacy columns.
            $workerBase = RhPersonal::query()
                ->select(['id', 'id_estacion'])
                ->where('id', $workerId)
                ->first();
            if (!$workerBase || (int) $workerBase->id !== $workerId) {
                return self::failure('worker_not_available');
            }
            $currentBaseLocalityId = (int) ($workerBase->id_estacion ?? 0);
            if ($currentBaseLocalityId <= 0
                || !Capsule::table('op_rh_localidades')->where('id', $currentBaseLocalityId)->exists()) {
                return self::failure('worker_not_available');
            }

            // Recheck the canonical locality while retrieving PII so a
            // concurrent RH transfer cannot bypass the validated source.
            $worker = RhPersonal::query()
                ->select(['id', 'id_estacion', 'nombre_completo', 'no_colaborador'])
                ->where('id', $workerId)
                ->where('id_estacion', $currentBaseLocalityId)
                ->first();
            if (!$worker || (int) $worker->id !== $workerId) {
                return self::failure('worker_not_available');
            }
        } catch (Throwable) {
            return self::failure('worker_not_available');
        }

        try {
            $payroll = $this->readPayrollReference($localityId, $workerId, $period);
        } catch (Throwable) {
            $payroll = ['status' => 'unavailable', 'references' => []];
        }

        $data = [
            'worker' => [
                'id' => (int) $worker->id,
                'employee_number' => $worker->no_colaborador === null ? null : (int) $worker->no_colaborador,
                'name' => (string) ($worker->nombre_completo ?? ''),
                // This is the authorized locality for the selected historical
                // period; the current base locality is a distinct fact.
                'workplace_id' => $localityId,
                'current_base_workplace_id' => $currentBaseLocalityId,
            ],
            'workplace' => $workplace,
            'period' => $period,
            'payroll' => $payroll,
            'attendance' => self::unavailableAttendance('not_queried'),
            'workplace_timezones' => ['status' => 'not_queried', 'days' => []],
            'schedules' => ['status' => 'not_queried', 'days' => []],
            'journeys' => [
                'status' => 'not_queried_read_only_contract',
                'message' => 'La consulta disponible de Jornada es una operación POST de evaluación. No se ejecutó para mantener este flujo estrictamente de solo lectura.',
            ],
            'incidences' => ['status' => 'not_queried', 'items' => []],
            'evaluations' => ['status' => 'not_queried', 'days' => []],
            'adjustments' => ['status' => 'not_queried', 'items' => []],
            'comparison_limit' => 'La consulta es informativa. No prueba cobertura completa, no crea un periodo común y no produce conclusiones de asistencia o nómina.',
        ];

        [$dates, $rawAttendance, $timezoneByDate] = $this->readObservedPeriod(
            $localityId,
            $workerId,
            $period,
            $periodScope['timezone_by_date']
        );
        $data['attendance'] = $rawAttendance;
        $data['workplace_timezones'] = [
            'status' => count($timezoneByDate) === count($dates) ? 'available' : ($timezoneByDate === [] ? 'unavailable' : 'partial'),
            'days' => array_map(
                static fn(array $context): array => [
                    'date' => (string) ($context['local_date'] ?? ''),
                    'timezone' => (string) ($context['timezone'] ?? ''),
                ],
                array_values($timezoneByDate)
            ),
        ];

        $scheduleDays = [];
        $evaluationDays = [];
        $adjustments = [];
        $scheduleErrors = 0;
        $evaluationErrors = 0;
        foreach ($dates as $date) {
            $schedule = $reader->getEffectiveSchedule($user, $workerId, $localityId, $date);
            if (!empty($schedule['ok'])) {
                $scheduleDays[] = self::scheduleProjection($date, $schedule['data']);
            } else {
                $scheduleErrors++;
                $scheduleDays[] = ['date' => $date, 'status' => 'unavailable'];
            }

            $evaluation = $reader->readAdministrativeEvaluation($user, $workerId, $localityId, $date);
            if (!empty($evaluation['ok'])) {
                $projected = self::evaluationProjection($date, $evaluation['data']);
                $evaluationDays[] = $projected;
                foreach ($projected['adjustments'] as $adjustment) {
                    $adjustments[$adjustment['uuid']] = $adjustment;
                }
            } else {
                $evaluationErrors++;
                $evaluationDays[] = ['date' => $date, 'status' => 'unavailable', 'adjustments' => []];
            }
        }

        $data['schedules'] = [
            'status' => $scheduleErrors === 0 ? 'available' : ($scheduleErrors === count($dates) ? 'unavailable' : 'partial'),
            'days' => $scheduleDays,
        ];
        $data['evaluations'] = [
            'status' => $evaluationErrors === 0 ? 'available' : ($evaluationErrors === count($dates) ? 'unavailable' : 'partial'),
            'days' => $evaluationDays,
        ];
        $data['adjustments'] = [
            'status' => $evaluationErrors === 0 ? 'available' : ($adjustments !== [] ? 'partial' : 'unavailable'),
            'items' => array_values($adjustments),
        ];

        $incidences = $reader->readApplicableIncidences(
            $user,
            $workerId,
            $localityId,
            $period['start_date'],
            $period['end_date']
        );
        if (!empty($incidences['ok'])) {
            $data['incidences'] = [
                'status' => 'available',
                'items' => array_map([self::class, 'incidenceProjection'], $incidences['data']['incidences']),
            ];
        } else {
            $data['incidences'] = ['status' => 'unavailable', 'items' => []];
        }

        return ['ok' => true, 'data' => $data];
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

    /**
     * Prove that one versioned canonical assignment covers an entire local-day
     * UTC window. Concurrent assignments at other Workplaces remain valid.
     * This is an access guard only; it does not establish attendance or payroll
     * completeness.
     *
     * @param array<string,mixed> $membership WorkerWorkplaceAssignmentResolver result
     */
    public static function assignmentCoversUtcWindow(
        array $membership,
        int $workerId,
        int $workplaceId,
        string $fromUtc,
        string $toUtc
    ): bool {
        if ($workerId < 1
            || $workplaceId < 1
            || (int) ($membership['worker_id'] ?? 0) !== $workerId
            || !in_array($membership['status'] ?? null, ['RESOLVED_SINGLE', 'RESOLVED_MULTIPLE'], true)
            || !is_array($membership['assignments'] ?? null)) {
            return false;
        }

        try {
            $windowStart = WorkplaceTimezoneTimestamp::normalize($fromUtc);
            $windowEnd = WorkplaceTimezoneTimestamp::normalize($toUtc);
        } catch (Throwable) {
            return false;
        }
        if ($windowEnd <= $windowStart) {
            return false;
        }

        $matching = [];
        foreach ($membership['assignments'] as $assignment) {
            if (!is_array($assignment)) {
                return false;
            }
            if ((int) ($assignment['workplace_id'] ?? 0) !== $workplaceId) {
                continue;
            }
            if ((int) ($assignment['worker_id'] ?? 0) !== $workerId) {
                return false;
            }
            $matching[] = $assignment;
        }
        if (count($matching) !== 1) {
            return false;
        }

        $assignment = $matching[0];
        $validFrom = self::normalizeResolvedAssignmentTimestamp($assignment['valid_from_utc'] ?? null);
        $validTo = ($assignment['valid_to_utc'] ?? null) === null
            ? null
            : self::normalizeResolvedAssignmentTimestamp($assignment['valid_to_utc'] ?? null);
        if ($validFrom === null || (($assignment['valid_to_utc'] ?? null) !== null && $validTo === null)) {
            return false;
        }

        return $validFrom <= $windowStart
            && ($validTo === null || $validTo >= $windowEnd);
    }

    private static function normalizeResolvedAssignmentTimestamp(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value, new DateTimeZone('UTC'));
        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d H:i:s.u') === $value
            ? $value
            : null;
    }

    /**
     * Resolve the API-owned UTC window and bitemporal assignment for every
     * local date in the payroll period before any personal identity is read.
     *
     * @return array{timezone_by_date:array<string,array<string,mixed>>}|null
     */
    private function resolveHistoricalPeriodScope(
        ChecadorDailyReadService $reader,
        Usuario $user,
        int $workerId,
        int $workplaceId,
        array $period
    ): ?array {
        $dates = self::dateRange($period['start_date'], $period['end_date']);
        if ($dates === [] || count($dates) > self::MAX_PERIOD_DAYS) {
            return null;
        }

        $recordedAsOf = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->format('Y-m-d\\TH:i:s.u\\Z');
        $resolver = new WorkerWorkplaceAssignmentResolver();
        $timezoneByDate = [];

        foreach ($dates as $date) {
            $timezone = $reader->resolveWorkplaceLocalDay($user, $workplaceId, $date);
            if (empty($timezone['ok']) || !is_array($timezone['data'] ?? null)) {
                return null;
            }

            $fromUtc = $timezone['data']['from_utc'] ?? null;
            $toUtc = $timezone['data']['to_utc'] ?? null;
            if (!is_string($fromUtc) || !is_string($toUtc)) {
                return null;
            }
            try {
                $normalizedFrom = WorkplaceTimezoneTimestamp::normalize($fromUtc);
                $normalizedTo = WorkplaceTimezoneTimestamp::normalize($toUtc);
            } catch (Throwable) {
                return null;
            }
            if ($normalizedTo <= $normalizedFrom) {
                return null;
            }

            $from = new DateTimeImmutable($normalizedFrom, new DateTimeZone('UTC'));
            $to = new DateTimeImmutable($normalizedTo, new DateTimeZone('UTC'));
            $seconds = $to->getTimestamp() - $from->getTimestamp();
            if ($seconds < 1) {
                return null;
            }
            $anchor = $from->modify('+' . intdiv($seconds, 2) . ' seconds')
                ->format('Y-m-d\\TH:i:s.u\\Z');

            $membership = $resolver->resolveAssignmentsAsRecordedAt($workerId, $anchor, $recordedAsOf);
            if (!self::assignmentCoversUtcWindow(
                $membership,
                $workerId,
                $workplaceId,
                $fromUtc,
                $toUtc
            )) {
                return null;
            }

            $timezoneByDate[$date] = $timezone['data'];
        }

        return ['timezone_by_date' => $timezoneByDate];
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

    /**
     * Multi-worker comparison summary for a locality and payroll period.
     * Strictly read-only; does not modify records or expose monetary data.
     *
     * @param array<string,mixed> $input
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string}}
     */
    public function comparePeriodSummary(array $input): array
    {
        $localityId = self::positiveInt($input['workplace_id'] ?? null);
        $year = self::positiveInt($input['year'] ?? null);
        $periodNumber = self::positiveInt($input['period_number'] ?? null);
        if ($localityId === null || $year === null || $periodNumber === null) {
            return self::failure('filters_required');
        }

        $authorization = $this->authorization($localityId);
        if (empty($authorization['allowed'])) {
            return self::failure('forbidden');
        }

        $periodType = is_string($input['period_type'] ?? null) ? trim($input['period_type']) : '';
        if ($periodType === '') {
            $periodType = RecibosNominaService::esSemanal($localityId) ? 'week' : 'fortnight';
        }

        $period = self::resolvePeriod($localityId, $year, $periodType, $periodNumber);
        if ($period === null) {
            return self::failure('invalid_period');
        }

        $workplace = $authorization['workplaces'][0] ?? ['id' => $localityId, 'nombre' => 'Localidad autorizada'];
        $requestedWorkerId = self::positiveInt($input['worker_id'] ?? null);
        $statusFilter = is_string($input['status'] ?? null) ? trim($input['status']) : '';

        // 1. Query payroll references for this locality and period (strictly no monetary columns)
        $payrollRows = [];
        try {
            $payrollQuery = OpReciboNominaV2::query()
                ->select([
                    'id',
                    'year',
                    'mes',
                    'no_semana_quincena',
                    'descripcion',
                    'id_estacion',
                    'id_usuario',
                    'id_puesto',
                    'doc_nomina',
                    'doc_nomina_firma',
                    'doc_nomina_aguinaldo',
                    'nomina_original',
                ])
                ->where('id_estacion', $localityId)
                ->where('year', $period['year'])
                ->where('no_semana_quincena', $period['number'])
                ->where('descripcion', $period['label']);

            if ($requestedWorkerId !== null) {
                $payrollQuery->where('id_usuario', $requestedWorkerId);
            }

            $payrollRecords = $payrollQuery->get();
            foreach ($payrollRecords as $pr) {
                $payrollRows[(int) $pr->id_usuario] = $pr;
            }
        } catch (Throwable) {
            $payrollRows = [];
        }

        // 2. Fetch workers in scope: active in locality + any worker with payroll receipt
        $workersList = [];
        try {
            $personalQuery = RhPersonal::query()
                ->select(['id', 'id_estacion', 'nombre_completo', 'no_colaborador', 'puesto'])
                ->where(function ($q) use ($localityId, $payrollRows) {
                    $q->where('id_estacion', $localityId)
                      ->where('estado', 1);
                    $payrollWorkerIds = array_keys($payrollRows);
                    if ($payrollWorkerIds !== []) {
                        $q->orWhereIn('id', $payrollWorkerIds);
                    }
                });

            if ($requestedWorkerId !== null) {
                $personalQuery->where('id', $requestedWorkerId);
            }

            $workersCollection = $personalQuery->orderBy('nombre_completo', 'asc')->get();
            foreach ($workersCollection as $w) {
                $workersList[(int) $w->id] = $w;
            }
        } catch (Throwable) {
            $workersList = [];
        }

        $workerIds = array_keys($workersList);
        if ($workerIds === []) {
            return [
                'ok' => true,
                'data' => [
                    'workplace' => $workplace,
                    'period' => $period,
                    'rows' => [],
                    'summary' => [
                        'total_workers' => 0,
                        'matches' => 0,
                        'differences' => 0,
                        'pending' => 0,
                        'insufficient' => 0,
                        'no_payroll' => 0,
                    ],
                ],
            ];
        }

        // 3. Batch query raw punches in the period date range
        $startUtc = $period['start_date'] . ' 06:00:00';
        $endPlus1 = (new DateTimeImmutable($period['end_date']))->modify('+1 day')->format('Y-m-d');
        $endUtc = $endPlus1 . ' 06:00:00';

        $rawPunchesByWorker = [];
        try {
            $punches = Capsule::table('op_rh_v2_marcaciones_crudas')
                ->select([
                    'id',
                    'id_personal_resuelto',
                    'ocurrido_at_utc',
                    'recibido_at_utc',
                    'id_dispositivo',
                    'metodo_captura',
                    'processing_status',
                    'event_uuid',
                ])
                ->where('id_estacion_resuelta', $localityId)
                ->whereIn('id_personal_resuelto', $workerIds)
                ->whereBetween('ocurrido_at_utc', [$startUtc, $endUtc])
                ->orderBy('ocurrido_at_utc', 'asc')
                ->get();

            foreach ($punches as $p) {
                $wId = (int) $p->id_personal_resuelto;
                $rawPunchesByWorker[$wId][] = [
                    'id' => (int) $p->id,
                    'ocurrido_at_utc' => (string) $p->ocurrido_at_utc,
                    'recibido_at_utc' => (string) $p->recibido_at_utc,
                    'id_dispositivo' => (int) ($p->id_dispositivo ?? 0),
                    'metodo_captura' => (string) ($p->metodo_captura ?? ''),
                    'processing_status' => (string) ($p->processing_status ?? ''),
                    'event_uuid' => (string) ($p->event_uuid ?? ''),
                ];
            }
        } catch (Throwable) {
            $rawPunchesByWorker = [];
        }

        // 4. Batch query shift assignments
        $shiftsByWorker = [];
        try {
            $shifts = Capsule::table('op_rh_v2_turnos_asignaciones as a')
                ->leftJoin('op_rh_v2_turnos_plantillas as p', 'p.id', '=', 'a.id_plantilla')
                ->select([
                    'a.id_personal',
                    'a.dia_semana',
                    'a.tipo_asignacion',
                    'a.valido_desde',
                    'a.valido_hasta',
                    'p.nombre as plantilla_nombre',
                ])
                ->where('a.id_estacion', $localityId)
                ->whereIn('a.id_personal', $workerIds)
                ->where('a.es_activo', 1)
                ->get();

            foreach ($shifts as $s) {
                $wId = (int) $s->id_personal;
                $shiftsByWorker[$wId][] = [
                    'dia_semana' => (string) $s->dia_semana,
                    'tipo_asignacion' => (string) $s->tipo_asignacion,
                    'plantilla' => (string) ($s->plantilla_nombre ?? 'Turno estándar'),
                ];
            }
        } catch (Throwable) {
            $shiftsByWorker = [];
        }

        // 5. Batch query persisted incidences
        $incidencesByWorker = [];
        try {
            $incidenceReader = new ChecadorPersistedIncidenceReadService();
            $incResult = $incidenceReader->readPersistedIncidences(
                $localityId,
                $period['start_date'],
                $period['end_date'],
                $requestedWorkerId,
                null,
                null,
                $user
            );
            if (!empty($incResult['ok']) && is_array($incResult['incidences'] ?? null)) {
                foreach ($incResult['incidences'] as $inc) {
                    $wId = (int) ($inc['worker_id'] ?? 0);
                    if ($wId > 0) {
                        $incidencesByWorker[$wId][] = $inc;
                    }
                }
            }
        } catch (Throwable) {
            $incidencesByWorker = [];
        }

        // 6. Batch query persisted journeys
        $journeysByWorker = [];
        try {
            $journeyReader = new ChecadorPersistedJourneyReadService();
            $jrnResult = $journeyReader->readPersistedJourneys(
                $localityId,
                $period['start_date'],
                $period['end_date'],
                $requestedWorkerId,
                null,
                $user
            );
            if (!empty($jrnResult['ok']) && is_array($jrnResult['journeys'] ?? null)) {
                foreach ($jrnResult['journeys'] as $jrn) {
                    $wId = (int) ($jrn['worker_id'] ?? 0);
                    if ($wId > 0) {
                        $journeysByWorker[$wId][] = $jrn;
                    }
                }
            }
        } catch (Throwable) {
            $journeysByWorker = [];
        }

        // 7. Assemble comparison rows
        $rows = [];
        $summary = [
            'total_workers' => count($workersList),
            'matches' => 0,
            'differences' => 0,
            'pending' => 0,
            'insufficient' => 0,
            'no_payroll' => 0,
        ];

        foreach ($workersList as $wId => $worker) {
            $receipt = $payrollRows[$wId] ?? null;
            $punches = $rawPunchesByWorker[$wId] ?? [];
            $shifts = $shiftsByWorker[$wId] ?? [];
            $incidences = $incidencesByWorker[$wId] ?? [];
            $journeys = $journeysByWorker[$wId] ?? [];

            $hasReceipt = $receipt !== null;
            $hasPunches = $punches !== [];
            $hasJourneys = $journeys !== [];
            $hasShifts = $shifts !== [];
            $hasIncidences = $incidences !== [];

            // Checador summary
            if ($hasJourneys) {
                $checadorSummary = count($journeys) . ' jornada' . (count($journeys) > 1 ? 's' : '') . ' evaluada' . (count($journeys) > 1 ? 's' : '');
            } elseif ($hasPunches) {
                $checadorSummary = count($punches) . ' marcación' . (count($punches) > 1 ? 'es' : '') . ' observada' . (count($punches) > 1 ? 's' : '');
            } elseif ($hasShifts) {
                $checadorSummary = 'Horario programado (' . count($shifts) . ' días); sin marcaciones';
            } else {
                $checadorSummary = 'Sin registros de asistencia';
            }

            // Nómina summary
            if ($hasReceipt) {
                $hasDoc = trim((string) ($receipt->doc_nomina ?? '')) !== '';
                $hasFirma = trim((string) ($receipt->doc_nomina_firma ?? '')) !== '';
                $isOrig = (int) ($receipt->nomina_original ?? 0) === 1;
                $payrollSummary = 'Recibo registrado · ' . ($hasDoc ? 'Documento disponible' : 'Pendiente de archivo') . ($hasFirma ? ' (Firmado)' : '');
            } else {
                $hasDoc = false;
                $hasFirma = false;
                $isOrig = false;
                $payrollSummary = 'Sin registro de recibo en periodo';
            }

            // Status and difference
            if ($hasReceipt && ($hasPunches || $hasJourneys)) {
                $status = 'COINCIDENCIA';
                $statusLabel = 'Coincidencia verificada';
                $statusBadge = 'bg-success-subtle text-success border border-success-subtle';
                $diffSummary = 'Recibo de nómina y marcaciones observadas en periodo';
                $summary['matches']++;
            } elseif ($hasReceipt && !$hasPunches && !$hasJourneys) {
                $status = 'DIFERENCIA';
                $statusLabel = 'Diferencia comprobada';
                $statusBadge = 'bg-danger-subtle text-danger border border-danger-subtle';
                $diffSummary = 'Recibo registrado en nómina sin marcaciones en Checador';
                $summary['differences']++;
            } elseif (!$hasReceipt && ($hasPunches || $hasJourneys)) {
                $status = 'DIFERENCIA';
                $statusLabel = 'Diferencia comprobada';
                $statusBadge = 'bg-danger-subtle text-danger border border-danger-subtle';
                $diffSummary = 'Asistencia registrada en Checador sin recibo correspondiente en nómina';
                $summary['differences']++;
            } elseif (!$hasReceipt && $hasShifts) {
                $status = 'SIN_NOMINA';
                $statusLabel = 'Sin correspondencia de nómina';
                $statusBadge = 'bg-info-subtle text-info border border-info-subtle';
                $diffSummary = 'Horario asignado en Checador pero sin recibo emitido en nómina';
                $summary['no_payroll']++;
            } else {
                $status = 'INSUFICIENTE';
                $statusLabel = 'Datos insuficientes';
                $statusBadge = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                $diffSummary = 'Sin actividad en Checador ni recibo en nómina';
                $summary['insufficient']++;
            }

            if ($hasIncidences) {
                $diffSummary .= ' (' . count($incidences) . ' incidencia' . (count($incidences) > 1 ? 's' : '') . ' registrada' . (count($incidences) > 1 ? 's' : '') . ')';
            }

            if ($statusFilter !== '' && $statusFilter !== 'all' && $statusFilter !== $status) {
                continue;
            }

            $rows[] = [
                'worker_id' => $wId,
                'worker_name' => (string) ($worker->nombre_completo ?? ('Personal ' . $wId)),
                'employee_number' => $worker->no_colaborador !== null ? (string) $worker->no_colaborador : null,
                'puesto' => (string) ($worker->puesto ?? ''),
                'workplace_id' => $localityId,
                'workplace_name' => (string) ($workplace['nombre'] ?? 'Localidad autorizada'),
                'period_label' => $period['label'] . ' ' . $period['number'] . ' · ' . $period['year'],
                'period_dates' => $period['start_date'] . ' a ' . $period['end_date'],
                'checador_summary' => $checadorSummary,
                'payroll_summary' => $payrollSummary,
                'difference_summary' => $diffSummary,
                'comparison_status' => $status,
                'comparison_status_label' => $statusLabel,
                'comparison_status_badge' => $statusBadge,
                'has_payroll_record' => $hasReceipt,
                'payroll_record' => $hasReceipt ? [
                    'record_id' => (int) $receipt->id,
                    'period_year' => (int) $receipt->year,
                    'period_month' => (int) $receipt->mes,
                    'period_number' => (int) $receipt->no_semana_quincena,
                    'period_type' => (string) $receipt->descripcion,
                    'has_doc_nomina' => $hasDoc,
                    'has_firma' => $hasFirma,
                    'has_aguinaldo' => trim((string) ($receipt->doc_nomina_aguinaldo ?? '')) !== '',
                    'is_original' => $isOrig,
                ] : null,
                'raw_punches' => $punches,
                'shifts' => $shifts,
                'incidences' => $incidences,
                'journeys' => $journeys,
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'workplace' => $workplace,
                'period' => $period,
                'rows' => $rows,
                'summary' => $summary,
            ],
        ];
    }

    /** @return array{0:array<int,string>,1:array<string,mixed>,2:array<string,array<string,mixed>>} */
    private function readObservedPeriod(
        int $localityId,
        int $workerId,
        array $period,
        array $resolvedTimezoneByDate
    ): array {
        $dates = self::dateRange($period['start_date'], $period['end_date']);
        $events = [];
        $timezoneByDate = $resolvedTimezoneByDate;
        $status = 'available';
        $message = '';
        foreach ($dates as $date) {
            $timezoneData = $timezoneByDate[$date] ?? null;
            if (!is_array($timezoneData)) {
                $status = $events === [] ? 'unavailable' : 'partial';
                $message = 'No se pudo establecer la ventana de zona horaria laboral para todas las fechas.';
                break;
            }

            $page = 1;
            $totalPages = 1;
            do {
                $result = (new ChecadorRawPunchReadService())->readPage(
                    $localityId,
                    (string) $timezoneData['from_utc'],
                    (string) $timezoneData['to_utc'],
                    $workerId,
                    $page,
                    200,
                    'desc'
                );
                if (empty($result['ok'])) {
                    $status = $events === [] ? 'unavailable' : 'partial';
                    $message = 'La lectura de marcaciones observadas no está disponible para todas las fechas.';
                    break 2;
                }
                foreach ($result['events'] as $event) {
                    $eventId = (int) ($event['id'] ?? 0);
                    if ($eventId > 0) {
                        $events[$eventId] = $event;
                    }
                    if (count($events) > self::MAX_EVENTS) {
                        $status = 'partial';
                        $message = 'Se alcanzó el límite seguro de presentación; el conjunto observado no se considera completo.';
                        break 3;
                    }
                }
                $totalPages = max(1, (int) ($result['pagination']['total_pages'] ?? 1));
                $page++;
            } while ($page <= $totalPages);
        }

        $items = array_values($events);
        usort($items, static function (array $left, array $right): int {
            $leftTime = (string) ($left['ocurrido_at_utc'] ?? $left['recibido_at_utc'] ?? '');
            $rightTime = (string) ($right['ocurrido_at_utc'] ?? $right['recibido_at_utc'] ?? '');
            return [$rightTime, (int) ($right['id'] ?? 0)] <=> [$leftTime, (int) ($left['id'] ?? 0)];
        });

        return [$dates, [
            'status' => $status,
            'events' => $items,
            'integrity' => 'NOT_VERIFIED_BY_PORTAL3',
            'snapshot' => 'LIVE_NON_SNAPSHOT',
            'completeness' => 'NOT_ESTABLISHED',
            'message' => $message,
        ], $timezoneByDate];
    }

    private static function scheduleProjection(string $date, array $data): array
    {
        $segments = [];
        foreach (($data['horario_local']['segmentos'] ?? []) as $segment) {
            $segments[] = [
                'ordinal' => (int) ($segment['ordinal'] ?? 0),
                'type' => (string) ($segment['tipo'] ?? ''),
                'start_local' => (string) ($segment['hora_inicio_local'] ?? ''),
                'end_local' => (string) ($segment['hora_fin_local'] ?? ''),
                'end_day_offset' => (int) ($segment['dia_offset_fin'] ?? 0),
            ];
        }
        return [
            'date' => $date,
            'status' => (string) ($data['estado_jornada'] ?? 'UNKNOWN'),
            'timezone' => (string) ($data['horario_local']['timezone'] ?? ''),
            'segments' => $segments,
        ];
    }

    private static function evaluationProjection(string $date, array $data): array
    {
        if (empty($data['evaluation_exists'])) {
            return ['date' => $date, 'status' => 'no_existing_evaluation', 'adjustments' => []];
        }
        $evaluation = $data['evaluation'] ?? [];
        $adjustments = [];
        foreach (($evaluation['linked_adjustments'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $uuid = (string) ($item['uuid_ajuste'] ?? '');
            if ($uuid === '') {
                continue;
            }
            $adjustments[] = [
                'uuid' => $uuid,
                'type' => (string) ($item['adjustment_type'] ?? ''),
                'status' => (string) ($item['adjustment_status'] ?? ''),
            ];
        }
        return [
            'date' => $date,
            'status' => 'existing_evaluation',
            'revision' => (int) ($evaluation['revision_number'] ?? 0),
            'administrative_status' => (string) ($evaluation['administrative_status'] ?? ''),
            'human_review_status' => (string) ($evaluation['human_review_status'] ?? ''),
            'created_at_utc' => (string) ($evaluation['created_at_utc'] ?? ''),
            'adjustments' => $adjustments,
        ];
    }

    private static function incidenceProjection(array $incidence): array
    {
        return [
            'uuid' => (string) ($incidence['uuid_incidencia'] ?? ''),
            'category' => (string) ($incidence['category'] ?? ''),
            'start_date' => (string) ($incidence['date_range']['start_date'] ?? ''),
            'end_date' => (string) ($incidence['date_range']['end_date'] ?? ''),
            'status' => (string) ($incidence['current_status'] ?? ''),
            'revision' => (int) ($incidence['current_revision'] ?? 0),
        ];
    }

    private static function unavailableAttendance(string $status): array
    {
        return [
            'status' => $status,
            'events' => [],
            'integrity' => 'NOT_VERIFIED_BY_PORTAL3',
            'snapshot' => 'LIVE_NON_SNAPSHOT',
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

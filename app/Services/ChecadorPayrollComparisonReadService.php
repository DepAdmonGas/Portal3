<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Models\ModuloDptoOperativo;
use App\Models\Operativo\OpReciboNominaV2;
use App\Models\Operativo\RhPersonal;
use App\Models\Usuario;
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

        try {
            // Canonical identity is op_rh_personal.id. Locality equality is
            // checked before any payroll or attendance details are returned.
            $worker = RhPersonal::query()
                ->select(['id', 'id_estacion', 'nombre_completo', 'no_colaborador'])
                ->where('id', $workerId)
                ->where('id_estacion', $localityId)
                ->first();
            if (!$worker || !self::workerIdentityMatches(
                $workerId,
                (int) $worker->id,
                $localityId,
                (int) $worker->id_estacion
            )) {
                return self::failure('worker_not_available');
            }

            $payroll = $this->readPayrollReference($localityId, $workerId, $period);
        } catch (Throwable) {
            $payroll = ['status' => 'unavailable', 'references' => []];
        }

        $workplace = $authorization['workplaces'][0] ?? ['id' => $localityId, 'nombre' => 'Localidad autorizada'];
        $data = [
            'worker' => [
                'id' => (int) $worker->id,
                'employee_number' => $worker->no_colaborador === null ? null : (int) $worker->no_colaborador,
                'name' => (string) ($worker->nombre_completo ?? ''),
                'workplace_id' => (int) $worker->id_estacion,
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

        // Do not mix synthetic API fixtures with real local SQL or payroll
        // records. The mode remains untouched; this page fails closed while it
        // is active and can be used only with real development sources.
        if (ChecadorDailyReadService::demoModeEnabled()) {
            $data['attendance'] = self::unavailableAttendance('demo_mode_active');
            $data['workplace_timezones']['status'] = 'demo_mode_active';
            $data['schedules']['status'] = 'demo_mode_active';
            $data['incidences']['status'] = 'demo_mode_active';
            $data['evaluations']['status'] = 'demo_mode_active';
            $data['adjustments']['status'] = 'demo_mode_active';
            return ['ok' => true, 'data' => $data];
        }

        try {
            $user = Usuario::query()->where('id', (int) Auth::user()->id)->where('estatus', 0)->first();
            if (!$user) {
                return self::failure('unauthenticated');
            }
            $reader = new ChecadorDailyReadService();
        } catch (Throwable) {
            $data['attendance'] = self::unavailableAttendance('service_unavailable');
            $data['workplace_timezones']['status'] = 'service_unavailable';
            $data['schedules']['status'] = 'service_unavailable';
            $data['incidences']['status'] = 'service_unavailable';
            $data['evaluations']['status'] = 'service_unavailable';
            $data['adjustments']['status'] = 'service_unavailable';
            return ['ok' => true, 'data' => $data];
        }

        [$dates, $rawAttendance, $timezoneByDate] = $this->readObservedPeriod(
            $reader,
            $user,
            $localityId,
            $workerId,
            $period
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

    /** @return array{0:array<int,string>,1:array<string,mixed>,2:array<string,array<string,mixed>>} */
    private function readObservedPeriod(
        ChecadorDailyReadService $reader,
        Usuario $user,
        int $localityId,
        int $workerId,
        array $period
    ): array {
        $dates = self::dateRange($period['start_date'], $period['end_date']);
        $events = [];
        $timezoneByDate = [];
        $status = 'available';
        $message = '';
        foreach ($dates as $date) {
            $timezone = $reader->resolveWorkplaceLocalDay($user, $localityId, $date);
            if (empty($timezone['ok'])) {
                $status = $events === [] ? 'unavailable' : 'partial';
                $message = 'No se pudo establecer la ventana de zona horaria laboral para todas las fechas.';
                break;
            }
            $timezoneByDate[$date] = $timezone['data'];

            $page = 1;
            $totalPages = 1;
            do {
                $result = (new ChecadorRawPunchReadService())->readPage(
                    $localityId,
                    (string) $timezone['data']['from_utc'],
                    (string) $timezone['data']['to_utc'],
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

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorPayrollComparisonReadService;
use App\Services\RecibosNominaService;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    if ($condition) {
        echo "PASS {$label}\n";
        $passes++;
    } else {
        echo "FAIL {$label}\n";
        $fails++;
    }
};

$servicePath = __DIR__ . '/../app/Services/ChecadorPayrollComparisonReadService.php';
$controllerPath = __DIR__ . '/../app/Controllers/ChecadorController.php';
$routePath = __DIR__ . '/../routes/web.php';
$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/payroll-comparison.php';
$indexViewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
$rawReaderPath = __DIR__ . '/../app/Services/ChecadorRawPunchReadService.php';
$adjustmentReaderPath = __DIR__ . '/../app/Services/ChecadorAdjustmentSqlReadService.php';
$dailyReaderPath = __DIR__ . '/../app/Services/ChecadorDailyReadService.php';
$moduleStationJsPath = __DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js';
$service = (string) file_get_contents($servicePath);
$controller = (string) file_get_contents($controllerPath);
$routes = (string) file_get_contents($routePath);
$view = (string) file_get_contents($viewPath);
$indexView = (string) file_get_contents($indexViewPath);
$rawReader = (string) file_get_contents($rawReaderPath);
$adjustmentReader = (string) file_get_contents($adjustmentReaderPath);
$dailyReader = (string) file_get_contents($dailyReaderPath);
$moduleStationJsSource = (string) file_get_contents($moduleStationJsPath);

$weekly = ChecadorPayrollComparisonReadService::resolvePeriod(1, (int) date('Y'), 'week', 1);
$fortnight = ChecadorPayrollComparisonReadService::resolvePeriod(6, (int) date('Y'), 'fortnight', 1);
$assert($weekly !== null && $weekly['type'] === 'week' && $weekly['number'] === 1, 'weekly locality resolves a period through the existing payroll rules');
$assert($fortnight !== null && $fortnight['type'] === 'fortnight' && $fortnight['number'] === 1, 'fortnight locality resolves a period through the existing payroll rules');
$assert($weekly !== null && $weekly['month'] === (int) RecibosNominaService::getRangoFechasSemana((int) date('Y'), 1)['mes']
    && $fortnight !== null && $fortnight['month'] === 1, 'resolved month follows the existing weekly/quincenal period helpers');
$assert(ChecadorPayrollComparisonReadService::resolvePeriod(1, (int) date('Y'), 'fortnight', 1) === null, 'client cannot change the period cadence for a weekly locality');
$assert(ChecadorPayrollComparisonReadService::resolvePeriod(6, (int) date('Y'), 'week', 1) === null, 'client cannot change the period cadence for a fortnight locality');
$assert(ChecadorPayrollComparisonReadService::resolvePeriod(6, (int) date('Y'), 'fortnight', 25) === null, 'fortnight number outside the existing 1-24 domain is rejected');
$assert(ChecadorPayrollComparisonReadService::resolvePeriod(1, (int) date('Y'), 'week', 999) === null, 'weekly number outside the existing yearly domain is rejected');
$assert(ChecadorPayrollComparisonReadService::resolvePeriod(1, (int) date('Y') + 1, 'week', 1) === null, 'future payroll year is rejected');
$assert($weekly !== null && (strtotime($weekly['end_date']) - strtotime($weekly['start_date'])) / 86400 + 1 <= ChecadorPayrollComparisonReadService::MAX_PERIOD_DAYS, 'resolved period remains within the bounded historical query window');

$assert(str_contains($service, 'ChecadorAccessService::authorize()')
    && str_contains($service, 'ModuloDptoOperativoService::getPermiso(')
    && str_contains($service, 'RecibosNominaService::getIdsLocalidadPermitidos()')
    && str_contains($service, 'combinedLocalityIds('), 'read requires both effective ACLs and their canonical-locality intersection');
$assert(ChecadorPayrollComparisonReadService::combinedLocalityIds(true, true, [1, 2], [2, 3]) === [2], 'user authorized in both modules is limited to the canonical locality intersection');
$assert(ChecadorPayrollComparisonReadService::combinedLocalityIds(true, false, [1, 2], [2]) === [], 'Checador-only grant cannot query payroll');
$assert(ChecadorPayrollComparisonReadService::combinedLocalityIds(false, true, [1, 2], [2]) === [], 'Nómina-only grant cannot query Checador');
$assert(ChecadorPayrollComparisonReadService::combinedLocalityIds(false, false, [1], [1]) === [], 'user without either read grant is denied');
$assert(ChecadorPayrollComparisonReadService::combinedLocalityIds(true, true, [1, '2', 0, '3x'], ['2', 3]) === [2], 'scope intersection discards malformed/nonpositive identifiers');
$assert(ChecadorPayrollComparisonReadService::workerIdentityMatches(558, 558, 1, 1), 'canonical worker identity and locality match is accepted');
$assert(!ChecadorPayrollComparisonReadService::workerIdentityMatches(559, 558, 1, 1)
    && !ChecadorPayrollComparisonReadService::workerIdentityMatches(558, 558, 1, 2), 'worker or locality substitution is rejected');
$assert(str_contains($service, '$this->workerOptions($localityId, $period[\'start_date\'], $period[\'end_date\'])')
    && str_contains($service, 'workersForAttendanceFilter($localityId, $date, $timezone, false)')
    && str_contains($service, 'workerIdentityMatches($workerId'), 'canonical worker is validated against the authorized historical roster rather than physical station id equality');
$assert(str_contains($service, "->select([\n                    'id',\n                    'year'")
    && !str_contains($service, 'importe_total')
    && !str_contains($service, 'prima_vacacional'), 'projection allowlists identity and documentary references without monetary columns');
$assert(str_contains($service, "'doc_nomina'")
    && str_contains($service, "'doc_nomina_firma'")
    && str_contains($service, "'doc_nomina_aguinaldo'")
    && str_contains($service, "'nomina_original'")
    && !str_contains($service, "'doc_nomina' => (string)"), 'payroll file contents are reduced to non-monetary presence flags');
$executableService = preg_replace('~/\*.*?\*/|//[^\n]*~s', '', $service) ?? $service;
$assert(!str_contains($executableService, 'getNominaData(')
    && !str_contains($executableService, 'sincronizarPersonal(')
    && !preg_match('/->(?:create|insert|update|delete|save|destroy|increment|decrement)\s*\(/i', $executableService), 'payroll read path avoids legacy synchronization and model write calls');
$assert(str_contains($service, 'OpReciboNominaV2::query()')
    && str_contains($service, "->where('year', \$period['year'])")
    && str_contains($service, "->where('mes', \$period['month'])")
    && str_contains($service, '->where(\'no_semana_quincena\', $period[\'number\'])')
    && str_contains($service, '->where(\'descripcion\', $period[\'label\'])')
    && str_contains($service, '->limit(2)'), 'payroll query is bounded to the explicit worker, locality and period, with duplicate detection');
$assert(!str_contains($service, 'api/v2/')
    && !str_contains($service, 'resolveWorkplaceLocalDay(')
    && !str_contains($service, 'readApplicableIncidences(')
    && !str_contains($service, 'readAdministrativeEvaluation(')
    && str_contains($service, 'readHistoricalTimezones(')
    && str_contains($service, 'readAuthorizedIncidences(')
    && str_contains($service, 'evaluationsForWorkerPeriod(')
    && str_contains($service, 'readLinkedAdjustments(')
    && str_contains($service, 'readObservedPunches('), 'comparison reads authorized M02 sources through Portal3 SQL readers and has no HTTP fallback');
$assert(!str_contains($service, 'evaluateJourney(')
    && !str_contains($service, 'getEffectiveSchedule(')
    && str_contains($service, "'segments_snapshot_json' => \$schedule['payload_segmentos_json'] ?? null"), 'comparison does not evaluate or resolve a fresh schedule and only passes through the approved C01 snapshot');
$assert(str_contains($service, 'ChecadorDailyReadService::demoModeEnabled()')
    && str_contains($service, "'completeness' => 'NOT_ESTABLISHED'")
    && str_contains($service, "'snapshot' => 'NOT_QUERIED'"), 'demo mode remains isolated and unqueried sources are not described as live evidence');
$assert(str_contains($service, 'MAX_PERIOD_DAYS = 16')
    && str_contains($service, "'no_persisted_journey_snapshot'")
    && str_contains($view, 'Snapshot de segmentos'), 'period remains bounded and dates without C01 rows are not misreported as unscheduled');
$assert(str_contains($service, 'ChecadorJourneySqlReadService())->read(')
    && str_contains($service, "'no_persisted_journeys'")
    && str_contains($view, 'segundos_trabajados_totales'), 'persisted P1/P2 journey read is SQL-backed and displayed without evaluation');

$routeDispatcher = \FastRoute\simpleDispatcher(require $routePath);
$resolved = $routeDispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/comparacion-nomina');
$handler = $resolved[1] ?? null;
$config = $handler instanceof Closure ? (new ReflectionFunction($handler))->getStaticVariables() : [];
$assert(($resolved[0] ?? null) === \FastRoute\Dispatcher::FOUND
    && ($config['middlewares'] ?? null) === ['auth', 'csrf', 'dpto-operativo-activo']
    && ($config['handler'] ?? null) === ['ChecadorController', 'payrollComparison'], 'comparison route retains existing authenticated middleware and is GET-only');
$assert(str_contains($controller, 'ChecadorPayrollComparisonReadService')
    && str_contains($controller, 'comparisonReader->authorization($workplaceId)')
    && str_contains($controller, 'View::renderPartial(\'errors/403\')'), 'controller independently revalidates combined locality access before rendering');
$assert(str_contains($controller, '$this->renderModuleDirectory();')
    && str_contains($controller, "'/comparacion-nomina'")
    && str_contains($indexView, 'data-checador-module-card'), 'comparison is reachable from the existing Checador directory without a parallel module menu');
$assert(str_contains($view, 'method="get"')
    && str_contains($view, 'no se interpreta como ausencia laboral')
    && str_contains($view, 'No verificada por Portal3')
    && str_contains($service, 'No se ejecutó una evaluación nueva')
    && str_contains($service, 'ChecadorJourneySqlReadService())->read('), 'UI presents read-only evidence and reads only persisted journeys without running an evaluation');
$assert(!str_contains(strtolower($view), 'importe_total')
    && !str_contains(strtolower($view), 'prima_vacacional')
    && !str_contains(strtolower($view), 'cuenta bancaria'), 'view contains no monetary, tax or bank-account data');
$assert(str_contains($controller, "(\$query['compare'] ?? null) === '1'")
    && str_contains($controller, 'preg_match(\'/\\\\A20[0-9]{2}\\\\z/\', $query[\'year\'])'), 'comparison runs only through an explicit submit intent and year input is type checked');
$assert(str_contains($service, "'segments_snapshot_json' => \$schedule['payload_segmentos_json'] ?? null")
    && str_contains($view, 'segments_snapshot_json'), 'persisted segment snapshot is passed through without reinterpretation');
$assert(!str_contains($service, 'evaluateJourney(')
    && str_contains($service, 'No se ejecutó una evaluación nueva.'), 'journey evaluation is never invoked by this read-only comparison');
$assert(str_contains($service, "'status' => 'no_reference_found'")
    && str_contains($view, 'Esto no demuestra ausencia de asistencia'), 'missing payroll reference is not converted into an attendance conclusion');
$assert(str_contains($view, 'action="/departamento-operativo/recursos-humanos/checador/comparacion-nomina"')
    && str_contains($view, 'table-responsive'), 'view uses native GET and responsive table conventions');
$assert(str_contains($view, '$recordedStatusLabel')
    && str_contains($view, 'no interpretado por Portal3'), 'administrative status is not interpreted as a payroll conclusion');
$assert(!str_contains($view, 'Evaluación existente consultada mediante GET')
    && str_contains($view, 'Se presentan únicamente evaluaciones y ajustes persistidos')
    && str_contains($view, 'no se resuelve ni calcula un horario nuevo')
    && str_contains($view, 'Día local consultado'), 'view labels persisted evidence and observed-event query dates without inferring outcomes');
$assert(str_contains($service, 'membershipMatchesLocalDate(')
    && str_contains($service, 'WorkerWorkplaceAssignmentResolver())->resolveAssignments')
    && str_contains($service, 'MAX_EVENTS = 5000'), 'incidences, evaluations and raw events remain bounded and historical membership is validated');
$assert(str_contains($service, '$this->membershipMatchesLocalDate($workerId, $localityId, $date, $timezoneName, $recordedAsOf)')
    && str_contains($service, "'excluded_unverified_membership_days'"), 'raw observed events are withheld when historical membership is not confirmed for the local date');
$assert(str_contains($adjustmentReader, 'public function evaluationsForWorkerPeriod(')
    && str_contains($adjustmentReader, "->where('e.id_estacion', \$workplaceId)")
    && str_contains($adjustmentReader, "->where('e.id_personal', \$workerId)")
    && str_contains($adjustmentReader, 'membershipMatches($membership, $workplaceId)')
    && !preg_match('/->(?:insert|update|delete|save|destroy)\s*\(/i', $adjustmentReader), 'persisted evaluation reader enforces worker/locality/history scope using SELECT-only access');
$assert(str_contains($service, '$totalRecords += $total;')
    && str_contains($service, "'status' => 'result_limit_exceeded'")
    && str_contains($service, 'no se muestran resultados parciales'), 'raw observed events enforce one aggregate cap across the full period');
$assert(str_contains($service, '$fromDate = self::civilDate($from);')
    && str_contains($service, '$toDate = self::civilDate($to);')
    && str_contains($service, '$toDate >= $fromDate'), 'incidence date projections fail closed when persisted date values are malformed');
$assert(str_contains($service, "'development_database_unavailable'")
    && str_contains($service, "'bd_admongas'")
    && str_contains($service, "'fedora'"), 'reader fails closed unless the local Fedora development database is verified');
$assert(str_contains($controller, 'payrollComparisonAvailable')
    && str_contains($controller, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY")
    && str_contains($controller, 'comparisonReader->authorization($workplaceId)'), 'navigation keeps the existing Checador locality context and revalidates it server-side');
$assert(str_contains($view, 'data-payroll-worker-select')
    && str_contains($view, 'Cargar personal del periodo')
    && str_contains($view, 'data-payroll-comparison-table')
    && str_contains($view, 'card-header bg-primary text-white'), 'view uses the institutional Select2, period roster refresh, tabular results and blue module card');
$assert(str_contains($controller, '$comparisonReader->workerOptions(')
    && str_contains($service, 'public function workerOptions(')
    && str_contains($service, 'historical_versioned_membership'), 'worker catalog is sourced from the authorized roster and historical membership resolver');
$assert(str_contains($moduleStationJsSource, "var payrollComparisonPath = checadorPath + '/comparacion-nomina'")
    && str_contains($moduleStationJsSource, "['worker_id', 'year', 'period_type', 'period_number', 'compare', 'load_roster']"), 'station switching clears comparison filters and retains canonical locality context');

printf("CHECADOR_PAYROLL_COMPARISON_FOCUSED_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);

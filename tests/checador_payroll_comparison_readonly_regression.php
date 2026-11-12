<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorPayrollComparisonReadService;
use App\Services\ChecadorAccessService;
use App\Services\RecibosNominaService;
use App\Services\WorkerWorkplaceAssignmentResolver;
use Illuminate\Database\Capsule\Manager as Capsule;

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
$dailyReaderPath = __DIR__ . '/../app/Services/ChecadorDailyReadService.php';
$service = (string) file_get_contents($servicePath);
$controller = (string) file_get_contents($controllerPath);
$routes = (string) file_get_contents($routePath);
$view = (string) file_get_contents($viewPath);
$indexView = (string) file_get_contents($indexViewPath);
$rawReader = (string) file_get_contents($rawReaderPath);
$dailyReader = (string) file_get_contents($dailyReaderPath);

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
$compareStart = strpos($service, 'public function compare(');
$payrollReadStart = strpos($service, 'private function readPayrollReference(');
$comparisonBody = $compareStart !== false && $payrollReadStart !== false && $payrollReadStart > $compareStart
    ? substr($service, $compareStart, $payrollReadStart - $compareStart)
    : '';
$assert(str_contains($service, 'ChecadorAccessService::resolveCanonicalLocalityIdForStation($physicalStationId)')
    && str_contains($service, '$this->resolveHistoricalPeriodScope(')
    && str_contains($service, 'assignmentCoversUtcWindow(')
    && !str_contains($comparisonBody, "->where('id_estacion', \$localityId)"), 'physical station IDs are mapped and historical locality is not checked by numeric equality');
$historyGatePosition = strpos($service, '$periodScope = $this->resolveHistoricalPeriodScope(');
$workerStationPosition = strpos($service, "->select(['id', 'id_estacion'])");
$workerIdentityPosition = strpos($service, "->select(['id', 'id_estacion', 'nombre_completo', 'no_colaborador'])");
$assert($historyGatePosition !== false
    && $workerStationPosition !== false
    && $workerIdentityPosition !== false
    && $historyGatePosition < $workerStationPosition
    && $workerStationPosition < $workerIdentityPosition,
    'period membership is validated before name and employee number are selected');
$assert(str_contains($service, "->select(['id', 'id_estacion', 'nombre_completo', 'no_colaborador'])")
    && str_contains($service, "->select([\n                    'id',\n                    'year'")
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
$assert(str_contains($rawReader, 'SELECT punches.id, punches.event_uuid')
    && str_contains($rawReader, 'punches.id_personal_resuelto = ?')
    && str_contains($dailyReader, "'GET', 'api/v2/workplaces/'")
    && str_contains($dailyReader, "'GET', 'api/v2/workers/'")
    && str_contains($service, "'LIVE_NON_SNAPSHOT'"), 'attendance uses canonical raw reader and existing GET/timezone boundaries without claiming snapshot completeness');
$assert(str_contains($dailyReader, "'GET', 'api/v2/incidences'")
    && str_contains($dailyReader, "'GET', 'api/v2/administrative-evaluations'")
    && !str_contains($service, 'evaluateJourney('), 'incidence and existing-evaluation reads stay GET-only; new Journey evaluation is not invoked');
$assert(str_contains($service, 'ChecadorDailyReadService::demoModeEnabled()')
    && str_contains($service, "'completeness' => 'NOT_ESTABLISHED'")
    && str_contains($service, "'snapshot' => 'LIVE_NON_SNAPSHOT'"), 'demo and incomplete-source boundaries prevent synthetic/operational mixing or completeness claims');
$assert(str_contains($service, 'MAX_EVENTS = 5000')
    && str_contains($service, 'array_values($timezoneByDate)')
    && str_contains($service, 'MAX_PERIOD_DAYS = 16'), 'historical date and presentation bounds are explicit');

$routeDispatcher = \FastRoute\simpleDispatcher(require $routePath);
$resolved = $routeDispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/comparacion-nomina');
$handler = $resolved[1] ?? null;
$config = $handler instanceof Closure ? (new ReflectionFunction($handler))->getStaticVariables() : [];
$assert(($resolved[0] ?? null) === \FastRoute\Dispatcher::FOUND
    && ($config['middlewares'] ?? null) === ['auth', 'csrf']
    && ($config['handler'] ?? null) === ['ChecadorController', 'payrollComparison'], 'comparison route retains existing authenticated middleware and is GET-only');
$assert(str_contains($controller, 'ChecadorPayrollComparisonReadService')
    && str_contains($controller, 'comparisonReader->authorization($workplaceId)')
    && str_contains($controller, 'View::renderPartial(\'errors/403\')'), 'controller independently revalidates combined locality access before rendering');
$assert(str_contains($controller, "'label' => 'Comparación con Nómina'")
    && str_contains($controller, "'href' => \$base . '/comparacion-nomina'")
    && str_contains($controller, 'ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY)')
    && str_contains($controller, "'available' => \$payrollRead"), 'comparison is reachable from the existing Checador module directory without a parallel menu');
$assert(str_contains($controller, '$this->renderModuleDirectory();')
    && str_contains($controller, "'/comparacion-nomina'")
    && str_contains($indexView, 'data-checador-module-card'), 'comparison is reachable from the existing Checador directory without a parallel module menu');
$assert(str_contains($view, 'method="get"')
    && str_contains($view, 'No se interpreta como ausencia')
    && str_contains($view, 'No verificada por Portal3')
    && str_contains($view, 'No se ejecutó una evaluación de jornada')
    && str_contains($service, 'No se ejecutó para mantener este flujo estrictamente de solo lectura'), 'UI presents read-only evidence and avoids unsupported attendance conclusions');
$assert(!str_contains(strtolower($view), 'importe_total')
    && !str_contains(strtolower($view), 'prima_vacacional')
    && !str_contains(strtolower($view), 'cuenta bancaria'), 'view contains no monetary, tax or bank-account data');
$assert(str_contains($controller, 'is_array($filterValue)')
    && !str_contains($controller, 'trim((string) $query[$filterName])'), 'malformed array/object filter parameters fail safely without coercion warnings');
$assert(str_contains($service, "'end_day_offset' => (int) (\$segment['dia_offset_fin'] ?? 0)")
    && str_contains($view, "\$segment['end_day_offset'] ?? 0"), 'historical schedule projection preserves overnight segment day offset');
$assert(!str_contains($service, 'evaluateJourney(')
    && str_contains($service, 'No se ejecutó para mantener este flujo estrictamente de solo lectura'), 'journey evaluation is not invoked because the existing evaluation route is not read-only');
$assert(str_contains($service, "'status' => 'no_reference_found'")
    && str_contains($view, 'Esto no demuestra ausencia de asistencia'), 'missing payroll reference is not converted into an attendance conclusion');
$assert(str_contains($view, 'action="/departamento-operativo/recursos-humanos/checador/comparacion-nomina"')
    && str_contains($view, 'table-responsive'), 'view uses native GET and responsive table conventions');
$assert(str_contains($view, '$recordedStatusLabel')
    && str_contains($view, 'no interpretado por Portal3'), 'administrative status is not interpreted as a payroll conclusion');
$assert(str_contains($service, "'development_database_unavailable'")
    && str_contains($service, "'bd_admongas'")
    && str_contains($service, "'fedora'"), 'reader fails closed unless the local Fedora development database is verified');
$assert(str_contains($controller, 'payrollComparisonAvailable')
    && str_contains($controller, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY")
    && str_contains($controller, 'comparisonReader->authorization($workplaceId)')
    && str_contains($controller, 'sessionWorkplaceId($checadorAccess)'), 'navigation keeps the session locality context and revalidates it server-side');

// Isolated identity/locality fixtures; this suite never connects to MariaDB or API V2.
$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = $capsule->schema();
$schema->create('tb_estaciones', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('nombre')->nullable();
    $table->integer('estatus')->default(0);
});
$schema->create('op_rh_localidades', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('localidad')->nullable();
});
$schema->create('op_rh_personal', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('id_estacion')->nullable();
    $table->string('nombre_completo')->nullable();
    $table->integer('no_colaborador')->nullable();
    $table->integer('estado')->default(1);
});
$schema->create('op_rh_personal_localidad_version', static function ($table): void {
    $table->increments('id');
    $table->integer('worker_id');
    $table->integer('workplace_id');
    $table->dateTime('valid_from_utc');
    $table->dateTime('valid_to_utc')->nullable();
    $table->dateTime('recorded_at_utc');
    $table->integer('recorded_by')->nullable();
    $table->string('reason')->nullable();
    $table->integer('supersedes_id')->nullable();
});
$db = $capsule->getConnection();
$db->table('op_rh_localidades')->insert([
    ['id' => 1, 'numlista' => 10, 'localidad' => 'Localidad uno'],
    ['id' => 2, 'numlista' => 20, 'localidad' => 'Localidad dos'],
    ['id' => 3, 'numlista' => 30, 'localidad' => 'Localidad tres'],
    ['id' => 4, 'numlista' => 40, 'localidad' => 'Localidad cuatro'],
    ['id' => 5, 'numlista' => 50, 'localidad' => 'Localidad ambigua'],
    ['id' => 6, 'numlista' => 60, 'localidad' => 'Localidad inactiva'],
    ['id' => 101, 'numlista' => 101, 'localidad' => 'Localidad de colisión'],
]);
$db->table('tb_estaciones')->insert([
    ['id' => 101, 'numlista' => 10, 'nombre' => 'Estación 101', 'estatus' => 0],
    ['id' => 1, 'numlista' => 20, 'nombre' => 'Estación con colisión', 'estatus' => 0],
    ['id' => 3, 'numlista' => 30, 'nombre' => 'Estación coincidente', 'estatus' => 0],
    ['id' => 4, 'numlista' => 40, 'nombre' => 'Estación 4', 'estatus' => 0],
    ['id' => 51, 'numlista' => 50, 'nombre' => 'Mapeo ambiguo A', 'estatus' => 0],
    ['id' => 52, 'numlista' => 50, 'nombre' => 'Mapeo ambiguo B', 'estatus' => 0],
    ['id' => 6, 'numlista' => 60, 'nombre' => 'Estación inactiva', 'estatus' => 1],
    ['id' => 71, 'numlista' => 80, 'nombre' => 'Sin localidad', 'estatus' => 0],
]);
$db->table('op_rh_personal')->insert([
    ['id' => 701, 'id_estacion' => 1, 'nombre_completo' => 'Fixture 701', 'no_colaborador' => 701, 'estado' => 1],
    ['id' => 702, 'id_estacion' => 101, 'nombre_completo' => 'Fixture 702', 'no_colaborador' => 702, 'estado' => 1],
    ['id' => 703, 'id_estacion' => 101, 'nombre_completo' => 'Fixture 703', 'no_colaborador' => 703, 'estado' => 1],
    ['id' => 704, 'id_estacion' => 101, 'nombre_completo' => 'Fixture 704', 'no_colaborador' => 704, 'estado' => 1],
    ['id' => 705, 'id_estacion' => 101, 'nombre_completo' => 'Fixture 705', 'no_colaborador' => 705, 'estado' => 1],
]);
$db->table('op_rh_personal_localidad_version')->insert([
    ['worker_id' => 701, 'workplace_id' => 1, 'valid_from_utc' => '2026-10-27 00:00:00', 'valid_to_utc' => '2026-10-30 00:00:00', 'recorded_at_utc' => '2026-01-01 00:00:00', 'recorded_by' => 1, 'reason' => 'Fixture', 'supersedes_id' => null],
    ['worker_id' => 702, 'workplace_id' => 2, 'valid_from_utc' => '2026-10-27 00:00:00', 'valid_to_utc' => '2026-10-30 00:00:00', 'recorded_at_utc' => '2026-01-01 00:00:00', 'recorded_by' => 1, 'reason' => 'Fixture', 'supersedes_id' => null],
    ['worker_id' => 704, 'workplace_id' => 1, 'valid_from_utc' => '2026-10-28 12:00:00', 'valid_to_utc' => '2026-10-30 00:00:00', 'recorded_at_utc' => '2026-01-01 00:00:00', 'recorded_by' => 1, 'reason' => 'Fixture', 'supersedes_id' => null],
    ['worker_id' => 705, 'workplace_id' => 1, 'valid_from_utc' => '2026-10-27 00:00:00', 'valid_to_utc' => '2026-10-30 00:00:00', 'recorded_at_utc' => '2026-01-01 00:00:00', 'recorded_by' => 1, 'reason' => 'Fixture', 'supersedes_id' => null],
    ['worker_id' => 705, 'workplace_id' => 2, 'valid_from_utc' => '2026-10-27 00:00:00', 'valid_to_utc' => '2026-10-30 00:00:00', 'recorded_at_utc' => '2026-01-01 00:00:00', 'recorded_by' => 1, 'reason' => 'Fixture', 'supersedes_id' => null],
]);
$assert(ChecadorAccessService::resolveCanonicalLocalityIdForStation(101) === 1,
    'physical station 101 maps to canonical locality 1 when numeric IDs differ');
$assert(ChecadorAccessService::resolveCanonicalLocalityIdForStation(3) === 3,
    'coincident physical and canonical IDs are accepted only through the unique numlista bridge');
$assert(ChecadorAccessService::resolveCanonicalLocalityIdForStation(1) === 2,
    'numeric collision maps physical station 1 to canonical locality 2, never locality 1');
$assert(ChecadorAccessService::resolveCanonicalLocalityIdForStation(51) === null
    && ChecadorAccessService::resolveCanonicalLocalityIdForStation(6) === null
    && ChecadorAccessService::resolveCanonicalLocalityIdForStation(71) === null
    && ChecadorAccessService::resolveCanonicalLocalityIdForStation(999) === null,
    'ambiguous, inactive, unmatched and nonexistent physical stations fail closed');

$assignmentResolver = new WorkerWorkplaceAssignmentResolver();
$recordedAsOf = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\\TH:i:s.u\\Z');
$resolveFixture = static fn(int $workerId): array => $assignmentResolver->resolveAssignmentsAsRecordedAt(
    $workerId,
    '2026-10-28T12:00:00.000000Z',
    $recordedAsOf
);
$dayStart = '2026-10-28T00:00:00.000000Z';
$dayEnd = '2026-10-29T00:00:00.000000Z';
$historicalAtOne = $resolveFixture(701);
$notAuthorizedAtOne = $resolveFixture(702);
$noHistory = $resolveFixture(703);
$partialDay = $resolveFixture(704);
$multipleWorkplaces = $resolveFixture(705);
$assert(ChecadorAccessService::resolveCanonicalLocalityIdForStation(1) === 2
    && ChecadorPayrollComparisonReadService::assignmentCoversUtcWindow($historicalAtOne, 701, 1, $dayStart, $dayEnd),
    'versioned period locality authorizes worker 701 independently of a different current base locality');
$assert(!ChecadorPayrollComparisonReadService::assignmentCoversUtcWindow($notAuthorizedAtOne, 702, 1, $dayStart, $dayEnd),
    'worker assigned to another canonical locality is rejected before identity details');
$assert(($noHistory['status'] ?? '') === 'RESOLVED_NONE'
    && !ChecadorPayrollComparisonReadService::assignmentCoversUtcWindow($noHistory, 703, 1, $dayStart, $dayEnd),
    'a period without versioned membership remains blocked instead of falling back to current station');
$assert(!ChecadorPayrollComparisonReadService::assignmentCoversUtcWindow($partialDay, 704, 1, $dayStart, $dayEnd),
    'an assignment that covers only part of a local day cannot authorize the full-day query');
$assert(($multipleWorkplaces['status'] ?? '') === 'RESOLVED_MULTIPLE'
    && ChecadorPayrollComparisonReadService::assignmentCoversUtcWindow($multipleWorkplaces, 705, 1, $dayStart, $dayEnd),
    'a requested locality assignment remains valid alongside a concurrent assignment elsewhere');
$assert(!ChecadorPayrollComparisonReadService::assignmentCoversUtcWindow($historicalAtOne, 701, 2, $dayStart, $dayEnd)
    && !ChecadorPayrollComparisonReadService::assignmentCoversUtcWindow($historicalAtOne, 701, 1, $dayEnd, $dayStart),
    'wrong locality and invalid UTC windows are rejected');

printf("CHECADOR_PAYROLL_COMPARISON_FOCUSED_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Session;
use App\Services\ModuleStationService;
use Illuminate\Database\Capsule\Manager as Capsule;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$dispatcher = FastRoute\simpleDispatcher(require __DIR__ . '/../routes/web.php');
$routeCases = [
    ['GET', '/departamento-operativo/recursos-humanos/checador', 'index'],
    ['GET', '/departamento-operativo/recursos-humanos/checador/asistencia', 'dailyAttendance'],
    ['GET', '/departamento-operativo/recursos-humanos/checador/jornadas', 'journeys'],
    ['GET', '/departamento-operativo/recursos-humanos/checador/incidencias', 'incidences'],
    ['GET', '/departamento-operativo/recursos-humanos/checador/ajustes', 'adjustments'],
    ['GET', '/departamento-operativo/recursos-humanos/checador/acceso-temporal', 'temporaryAccess'],
    ['GET', '/departamento-operativo/recursos-humanos/checador/configuracion', 'configuration'],
    ['GET', '/departamento-operativo/recursos-humanos/checador/reporte', 'punchReport'],
    ['GET', '/departamento-operativo/recursos-humanos/checador/reporte/data', 'punchReportData'],
    ['GET', '/departamento-operativo/recursos-humanos/checador/comparacion-nomina', 'payrollComparison'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignar', 'createScheduleAssignment'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/incidencias/crear', 'createIncidence'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/incidencias/demo-uuid/resolve', 'lifecycleResolve'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/incidencias/demo-uuid/authorize', 'lifecycleAuthorize'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/incidencias/demo-uuid/reject', 'lifecycleReject'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/incidencias/demo-uuid/revoke', 'lifecycleRevoke'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/evaluations/evaluate', 'administrativeEvaluation'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/acceso-temporal/demo-request/authorize', 'temporaryAccessAuthorize'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/acceso-temporal/demo-request/reject', 'temporaryAccessReject'],
    ['POST', '/departamento-operativo/recursos-humanos/checador/acceso-temporal/demo-request/cancel', 'temporaryAccessCancel'],
];

foreach ($routeCases as [$method, $path, $expectedAction]) {
    $resolved = $dispatcher->dispatch($method, $path);
    $handler = $resolved[1] ?? null;
    $wrapper = $handler instanceof Closure ? (new ReflectionFunction($handler))->getStaticVariables() : [];
    $assert(($resolved[0] ?? null) === FastRoute\Dispatcher::FOUND
        && ($wrapper['handler'] ?? null) === ['ChecadorController', $expectedAction]
        && ($wrapper['middlewares'] ?? []) === ['auth', 'csrf'],
        "{$method} {$path} dispatches to {$expectedAction} with auth + CSRF");
}

$assert($dispatcher->dispatch('POST', '/departamento-operativo/departamento-operativo/recursos-humanos/checador/incidencias/crear')[0]
    === FastRoute\Dispatcher::NOT_FOUND,
    'duplicated departamento-operativo prefix is not registered');

$mainRouteCases = [
    ['GET', '/departamento-operativo/almacen/mantenimiento', 'DptoOperativoController', 'almacenMantenimientoIndex'],
    ['GET', '/departamento-operativo/almacen/mantenimiento-preventivo', 'AlmacenMantenimientoPreventivoController', 'index'],
    ['POST', '/departamento-operativo/almacen/mantenimiento-preventivo/store', 'AlmacenMantenimientoPreventivoController', 'store'],
    ['GET', '/departamento-operativo/almacen/terminales-punto-venta', 'AlmacenTerminalesPuntoVentaController', 'index'],
    ['POST', '/departamento-operativo/almacen/terminales-punto-venta/store', 'AlmacenTerminalesPuntoVentaController', 'store'],
    ['GET', '/departamento-operativo/almacen/terminales-punto-venta-fallas', 'AlmacenTerminalesPuntoVentaFallasController', 'index'],
    ['POST', '/departamento-operativo/almacen/terminales-punto-venta-fallas/store', 'AlmacenTerminalesPuntoVentaFallasController', 'store'],
    ['GET', '/departamento-operativo/almacen/orden-compra/2026/10', 'OrdenCompraController', 'index'],
];

foreach ($mainRouteCases as [$method, $path, $expectedController, $expectedAction]) {
    $resolved = $dispatcher->dispatch($method, $path);
    $handler = $resolved[1] ?? null;
    $wrapper = $handler instanceof Closure ? (new ReflectionFunction($handler))->getStaticVariables() : [];
    $assert(($resolved[0] ?? null) === FastRoute\Dispatcher::FOUND
        && ($wrapper['handler'] ?? null) === [$expectedController, $expectedAction]
        && ($wrapper['middlewares'] ?? []) === ['auth', 'csrf'],
        "main route {$method} {$path} remains registered and protected");
}

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = $capsule->schema();
$schema->create('tb_usuarios', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('id_gas')->nullable();
    $table->integer('id_puesto')->nullable();
    $table->integer('estatus')->default(0);
});
$schema->create('tb_modulos_config', static function ($table): void {
    $table->increments('id');
    $table->string('modulo_key');
    $table->string('tipo');
    $table->text('estaciones_soportadas')->nullable();
    $table->text('departamentos_soportados')->nullable();
    $table->string('tipo_departamento')->nullable();
    $table->boolean('allow_all')->default(false);
    $table->string('placeholder')->nullable();
    $table->boolean('activo')->default(true);
});
$schema->create('tb_multiestacion_usuario', static function ($table): void {
    $table->increments('id');
    $table->integer('id_usuario');
    $table->text('estaciones')->nullable();
    $table->text('departamentos_puestos')->nullable();
    $table->text('departamentos_localidades')->nullable();
    $table->boolean('activo')->default(true);
});
$schema->create('tb_multiestacion_puesto', static function ($table): void {
    $table->increments('id');
    $table->integer('id_puesto');
    $table->text('estaciones')->nullable();
    $table->text('departamentos_puestos')->nullable();
    $table->text('departamentos_localidades')->nullable();
    $table->boolean('activo')->default(true);
});
$schema->create('tb_estaciones', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('nombre');
    $table->integer('estatus')->default(0);
});
$schema->create('op_rh_localidades', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('localidad');
});

$db = $capsule->getConnection();
$db->table('tb_usuarios')->insert([
    ['id' => 100, 'id_gas' => 2, 'id_puesto' => 13, 'estatus' => 0],
    ['id' => 101, 'id_gas' => 2, 'id_puesto' => 99, 'estatus' => 0],
]);
$db->table('tb_estaciones')->insert([
    ['id' => 501, 'numlista' => 1, 'nombre' => 'Estación autorizada 1', 'estatus' => 0],
    ['id' => 502, 'numlista' => 2, 'nombre' => 'Estación autorizada 2', 'estatus' => 0],
    ['id' => 503, 'numlista' => 3, 'nombre' => 'Estación no autorizada', 'estatus' => 0],
]);
$db->table('op_rh_localidades')->insert([
    ['id' => 9, 'numlista' => null, 'localidad' => 'Autolavado'],
    ['id' => 601, 'numlista' => 1, 'localidad' => 'Localidad autorizada 1'],
    ['id' => 602, 'numlista' => 2, 'localidad' => 'Localidad autorizada 2'],
]);
$db->table('tb_modulos_config')->insert([
    ['modulo_key' => 'gestoria', 'tipo' => 'stations_and_departments', 'estaciones_soportadas' => '[501,502,503]', 'departamentos_soportados' => '[9]', 'tipo_departamento' => 'localidades', 'allow_all' => 0, 'placeholder' => 'Elige Gestoría', 'activo' => 1],
    ['modulo_key' => 'puestos-test', 'tipo' => 'stations_and_departments', 'estaciones_soportadas' => '[]', 'departamentos_soportados' => '[3,13,14]', 'tipo_departamento' => 'puestos', 'allow_all' => 0, 'placeholder' => null, 'activo' => 1],
    ['modulo_key' => 'organigrama', 'tipo' => 'stations_and_departments', 'estaciones_soportadas' => '[]', 'departamentos_soportados' => '[9]', 'tipo_departamento' => 'localidades', 'allow_all' => 0, 'placeholder' => null, 'activo' => 1],
]);
$db->table('tb_multiestacion_usuario')->insert([
    ['id_usuario' => 100, 'estaciones' => '[501,502]', 'departamentos_puestos' => '[3,13]', 'departamentos_localidades' => '[9]', 'activo' => 1],
]);

$_SESSION = [];
Session::set('usuario', ['id' => 100]);
Auth::forget();
$reportConfig = ModuleStationService::getConfig('reportes');
$reportStations = ModuleStationService::getAvailableStations('reportes');
$assert(($reportConfig['placeholder'] ?? '') === 'Elige Gestoría'
    && ($reportConfig['estaciones_soportadas'] ?? []) === [501, 502, 503],
    'main Reportes configuration aliases Gestoría while retaining its own module key');
$assert(array_column($reportStations, 'id') === [601, 602]
    && !in_array(503, array_column($reportStations, 'id'), true),
    'Reportes reuses Gestoría catalog, maps station numbers to canonical localities, and applies the user allowlist');
$puestos = ModuleStationService::getAvailableDepartments('puestos-test');
$assert(array_column($puestos, 'id') === [3, 13]
    && ($puestos[0]['nombre'] ?? '') === 'Dirección'
    && ($puestos[1]['nombre'] ?? '') === 'Dirección de operaciones',
    'recent main department names are available without exposing unsupported puesto 14');

Session::set('usuario', ['id' => 101]);
Auth::forget();
$autolavadoLegacy = ModuleStationService::getAvailableDepartments('gestoria');
$assert(array_column($autolavadoLegacy, 'id') === [9],
    'legacy Autolavado remains available to unconfigured modules before selective exclusion');
ModuleStationService::excluirAutoLavado('gestoria');
$assert(ModuleStationService::getAvailableDepartments('gestoria') === []
    && array_column(ModuleStationService::getAvailableDepartments('organigrama'), 'id') === [9],
    'Autolavado exclusion remains scoped to Gestoría and preserves another module’s legacy behavior');

printf("PR243_CONFLICT_RESOLUTION_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

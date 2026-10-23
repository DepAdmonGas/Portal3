<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Session;
use App\Services\ChecadorAccessService;
use App\Services\ModuleStationService;
use App\Services\ModuloDptoOperativoService;
use Illuminate\Database\Capsule\Manager as Capsule;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) {
        echo "PASS {$message}\n";
        $passes++;
    } else {
        echo "FAIL {$message}\n";
        $fails++;
    }
};

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
$schema->create('tb_puestos', static function ($table): void { $table->integer('id')->primary(); });
$schema->create('modulos_dpto_operativo', static function ($table): void {
    $table->increments('id');
    $table->string('clave');
    $table->string('nombre')->nullable();
    $table->string('ruta')->nullable();
    $table->string('icono')->nullable();
    $table->integer('activo')->default(1);
});
$createParentPivot = static function ($table, string $identity): void {
    $table->increments('id');
    $table->integer('id_modulo');
    $table->integer($identity);
    foreach (['leer', 'crear', 'editar', 'eliminar', 'descargar'] as $field) {
        $table->integer($field)->default(0);
    }
};
$schema->create('modulos_puestos_do', static fn($table) => $createParentPivot($table, 'id_puesto'));
$schema->create('modulos_usuarios_do', static fn($table) => $createParentPivot($table, 'id_usuario'));
$schema->create('modulos_sub_dpto_operativo', static function ($table): void {
    $table->increments('id');
    $table->integer('id_modulo');
    $table->string('nombre');
    $table->string('clave');
    $table->string('ruta');
    $table->string('icono')->nullable();
    $table->integer('activo')->default(1);
});
$schema->create('modulos_sub_puestos_do', static function ($table): void {
    $table->increments('id'); $table->integer('id_sub_modulo'); $table->integer('id_puesto');
});
$schema->create('modulos_sub_usuarios_do', static function ($table): void {
    $table->increments('id'); $table->integer('id_sub_modulo'); $table->integer('id_usuario');
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
$schema->create('tb_estaciones', static function ($table): void {
    $table->integer('id')->primary(); $table->integer('numlista')->nullable(); $table->string('nombre'); $table->integer('estatus')->default(0);
});
$schema->create('op_rh_localidades', static function ($table): void {
    $table->integer('id')->primary(); $table->integer('numlista')->nullable(); $table->string('localidad');
});
$schema->create('tb_multiestacion_usuario', static function ($table): void {
    $table->increments('id'); $table->integer('id_usuario'); $table->text('estaciones')->nullable();
    $table->text('departamentos_puestos')->nullable(); $table->text('departamentos_localidades')->nullable();
    $table->boolean('activo')->default(true);
});
$schema->create('tb_multiestacion_puesto', static function ($table): void {
    $table->increments('id'); $table->integer('id_puesto'); $table->text('estaciones')->nullable();
    $table->text('departamentos_puestos')->nullable(); $table->text('departamentos_localidades')->nullable();
    $table->boolean('activo')->default(true);
});

$db = $capsule->getConnection();
$_SESSION = [];
$db->table('tb_puestos')->insert([['id' => 3], ['id' => 6], ['id' => 13], ['id' => 14], ['id' => 99]]);
$db->table('tb_usuarios')->insert([
    ['id' => 600, 'id_gas' => 101, 'id_puesto' => 6, 'estatus' => 0],
    ['id' => 1300, 'id_gas' => 101, 'id_puesto' => 13, 'estatus' => 0],
    ['id' => 300, 'id_gas' => 101, 'id_puesto' => 3, 'estatus' => 0],
    ['id' => 1400, 'id_gas' => 101, 'id_puesto' => 14, 'estatus' => 0],
    ['id' => 470, 'id_gas' => 104, 'id_puesto' => 6, 'estatus' => 0],
    ['id' => 602, 'id_gas' => 103, 'id_puesto' => 6, 'estatus' => 0],
    ['id' => 900, 'id_gas' => 101, 'id_puesto' => 99, 'estatus' => 0],
    ['id' => 901, 'id_gas' => 101, 'id_puesto' => 99, 'estatus' => 0],
]);
$db->table('modulos_dpto_operativo')->insert(['id' => 2, 'clave' => 'recursos-humanos', 'nombre' => 'Recursos Humanos', 'activo' => 1]);
$db->table('modulos_puestos_do')->insert([
    ['id_modulo' => 2, 'id_puesto' => 6, 'leer' => 1],
    ['id_modulo' => 2, 'id_puesto' => 13, 'leer' => 1],
]);
$legacy = [
    15 => ['organigrama', 'Organigrama', '/recursos-humanos/organigrama'],
    16 => ['documentos-personal', 'Control de Documentos del Personal', '/recursos-humanos/control-documentos-personal'],
    17 => ['formatos', 'Formatos', '/recursos-humanos/formatos'],
    18 => ['horario-personal', 'Horario del Personal', '/recursos-humanos/horario-personal'],
    19 => ['biometricos', 'Biometricos', '/recursos-humanos/biometricos'],
    20 => ['recibos-nomina', 'Recibos de Nomina', '/recursos-humanos/recibos-nomina'],
    21 => ['permisos', 'Permisos', '/recursos-humanos/permisos'],
    22 => ['incidencias-nomina', 'Incidencias de Nomina', '/recursos-humanos/incidencias-nomina'],
    23 => ['bitacora-rrhh', 'Bitacora RRHH', '/recursos-humanos/bitacora-rrhh'],
    27 => ['programar-horario', 'Programar Horario', '/recursos-humanos/programar-horario'],
    28 => ['lista-negra', 'Lista Negra', '/recursos-humanos/lista-negra'],
];
foreach ($legacy as $id => [$key, $name, $route]) {
    $db->table('modulos_sub_dpto_operativo')->insert(['id' => $id, 'id_modulo' => 2, 'nombre' => $name, 'clave' => $key, 'ruta' => $route, 'activo' => 1]);
}
$db->table('modulos_sub_dpto_operativo')->insert(['id' => 29, 'id_modulo' => 2, 'nombre' => 'Checador', 'clave' => 'checador', 'ruta' => '/departamento-operativo/recursos-humanos/checador', 'activo' => 1]);
$encargado = [15, 16, 17, 18, 19, 20, 21, 27, 28, 29];
$direccion = [15, 16, 17, 18, 19, 20, 21, 22, 23, 29];
foreach ($encargado as $id) { $db->table('modulos_sub_puestos_do')->insert(['id_sub_modulo' => $id, 'id_puesto' => 6]); }
foreach ($direccion as $id) { $db->table('modulos_sub_puestos_do')->insert(['id_sub_modulo' => $id, 'id_puesto' => 13]); }
$db->table('tb_estaciones')->insert([
    ['id' => 101, 'numlista' => 1, 'nombre' => 'Estacion uno', 'estatus' => 0],
    ['id' => 102, 'numlista' => 2, 'nombre' => 'Estacion dos', 'estatus' => 0],
    ['id' => 103, 'numlista' => 3, 'nombre' => 'Estacion historica', 'estatus' => 1],
    ['id' => 104, 'numlista' => 4, 'nombre' => 'Estacion cuatro', 'estatus' => 0],
    ['id' => 106, 'numlista' => 6, 'nombre' => 'Estacion fuera de Checador', 'estatus' => 0],
]);
$db->table('op_rh_localidades')->insert([
    ['id' => 201, 'numlista' => 1, 'localidad' => 'Localidad uno'],
    ['id' => 202, 'numlista' => 2, 'localidad' => 'Localidad dos'],
    ['id' => 203, 'numlista' => 3, 'localidad' => 'Localidad historica'],
    ['id' => 204, 'numlista' => 4, 'localidad' => 'Localidad cuatro'],
    ['id' => 206, 'numlista' => 6, 'localidad' => 'Localidad fuera de Checador'],
]);
$db->table('tb_multiestacion_puesto')->insert([
    ['id_puesto' => 6, 'estaciones' => '[101,102]', 'activo' => 1],
    ['id_puesto' => 13, 'estaciones' => '[101,102,103]', 'activo' => 1],
]);
$db->table('tb_modulos_config')->insert([
    'modulo_key' => 'checador', 'tipo' => 'stations_only', 'estaciones_soportadas' => '[101,102,103,104]',
    'departamentos_soportados' => '[]', 'tipo_departamento' => 'puestos', 'allow_all' => 0,
    'placeholder' => null, 'activo' => 1,
]);

$login = static function (int $userId): void {
    Session::set('usuario', ['id' => $userId]);
    Auth::forget();
};
$keys = static function (int $userId): array {
    $login = Session::get('usuario')['id'] ?? 0;
    if ((int) $login !== $userId) { Session::set('usuario', ['id' => $userId]); Auth::forget(); }
    $parent = ModuloDptoOperativoService::getPermiso($userId, 'recursos-humanos');
    return array_values(array_map(static fn(array $row): string => $row['clave'], $parent['submenus'] ?? []));
};

// C01: authenticated identity alone does not grant the parent module.
$login(900);
$assert(ChecadorAccessService::authorize()['reason'] === 'parent_module_denied', 'C01 denies authenticated user without Recursos Humanos');

// C02: parent grant without the Checador assignment does not authorize direct access.
$db->table('modulos_sub_puestos_do')->where('id_puesto', 99)->delete();
$db->table('modulos_puestos_do')->insert(['id_modulo' => 2, 'id_puesto' => 99, 'leer' => 1]);
$assert(ChecadorAccessService::authorize()['reason'] === 'submodule_denied', 'C02 denies direct URL without Checador assignment');

// C03: post membership drives the menu; Encargado is bound to current id_gas.
$login(600);
$postMenu = $keys(600);
$postAccess = ChecadorAccessService::authorize();
$assert(in_array('checador', $postMenu, true) && $postAccess['allowed'] && array_column($postAccess['workplaces'], 'id') === [201], 'C03 Encargado role assignment authorizes only current home station');
$encargadoSelectorHtml = ModuleStationService::render('checador');
$assert(array_column(ModuleStationService::getAvailableStations('checador'), 'id') === [201]
    && str_contains($encargadoSelectorHtml, 'Localidad uno')
    && !str_contains($encargadoSelectorHtml, 'id="module-station-selector-checador"'),
    'V6B1 single-workplace selector shows the authorized canonical locality without a redundant control');
$assert(ChecadorAccessService::authorize(202)['reason'] === 'requested_station_out_of_scope', 'C03 Encargado cannot cross to a second supported station');
$login(1300);
$directionAccess = ChecadorAccessService::authorize();
$assert($directionAccess['allowed'] && array_column($directionAccess['workplaces'], 'id') === [201, 202, 204], 'C03 Dirección receives all active, uniquely mapped Checador stations, including one omitted from global multi-station scope');
$previousModuleContext = ['corte-diario' => ['id_estacion' => 101, 'id_depto' => null]];
Session::set('module_context', $previousModuleContext);
ModuleStationService::setContext('checador', 202, null);
$checadorContext = ModuleStationService::getContext('checador');
$directionSelectorHtml = ModuleStationService::render('checador');
$assert(array_column(ModuleStationService::getAvailableStations('checador'), 'id') === [201, 202, 204]
    && $checadorContext['id_estacion'] === 202
    && $checadorContext['nombre'] === 'Localidad dos'
    && (Session::get('module_context')['corte-diario']['id_estacion'] ?? null) === 101
    && str_contains($directionSelectorHtml, 'id="module-station-selector-checador"')
    && str_contains($directionSelectorHtml, 'value="estacion_201"')
    && str_contains($directionSelectorHtml, 'value="estacion_202" selected'),
    'V6B1 multi-workplace selector uses only authorized canonical localities and keeps Corte Diario context independent');
$assert(ChecadorAccessService::authorize(201)['allowed'], 'C03 Dirección may request station 1');
$assert(ChecadorAccessService::authorize(202)['allowed'], 'C03 Dirección may request a second authorized station');
$assert(ChecadorAccessService::authorize(204)['allowed'], 'C03 Dirección may request station 8 equivalent outside global multi-station scope');
$assert(ChecadorAccessService::authorize(206)['reason'] === 'requested_station_out_of_scope', 'C03 Dirección cannot request a station outside Checador supported allowlist');
$assert(!in_array(203, array_column($directionAccess['workplaces'], 'id'), true), 'C03 inactive historical station is excluded from current report scope');
$db->table('tb_multiestacion_usuario')->insert(['id_usuario' => 470, 'estaciones' => '[101,102,103]', 'activo' => 1]);
$login(470);
$user470Access = ChecadorAccessService::authorize();
$assert($user470Access['allowed'] && array_column($user470Access['workplaces'], 'id') === [204], 'C03 user 470 multi-station exception does not widen Encargado Checador scope');
$user470SelectorHtml = ModuleStationService::render('checador');
$assert(array_column(ModuleStationService::getAvailableStations('checador'), 'id') === [204]
    && str_contains($user470SelectorHtml, 'Localidad cuatro')
    && !str_contains($user470SelectorHtml, 'value="estacion_201"')
    && !str_contains($user470SelectorHtml, 'id="module-station-selector-checador"'),
    'V6B1 user 470 sees only the primary authorized locality and no cross-station selector');
$assert(ChecadorAccessService::authorize(201)['reason'] === 'requested_station_out_of_scope', 'C03 user 470 URL cannot select another station');
$db->table('tb_multiestacion_puesto')->insert([
    ['id_puesto' => 3, 'estaciones' => '[101,102,104]', 'activo' => 1],
    ['id_puesto' => 14, 'estaciones' => '[101,102,104]', 'activo' => 1],
]);
$login(300);
$assert(ChecadorAccessService::authorize()['reason'] === 'parent_module_denied', 'V7C12 position 3 has no Checador access without an explicit functional grant');
$login(1400);
$assert(ChecadorAccessService::authorize()['reason'] === 'parent_module_denied', 'V7C12 position 14 has no Checador access without an explicit functional grant');
$db->table('modulos_usuarios_do')->insert([
    ['id_modulo' => 2, 'id_usuario' => 300, 'leer' => 1],
    ['id_modulo' => 2, 'id_usuario' => 1400, 'leer' => 1],
]);
$db->table('modulos_sub_usuarios_do')->insert([
    ['id_sub_modulo' => 29, 'id_usuario' => 300],
    ['id_sub_modulo' => 29, 'id_usuario' => 1400],
]);
$login(300);
$position3Access = ChecadorAccessService::authorize();
$assert($position3Access['allowed'] && array_column($position3Access['workplaces'], 'id') === [201], 'V7C12 explicitly granted position 3 remains limited to its own station');
$login(1400);
$position14Access = ChecadorAccessService::authorize();
$assert($position14Access['allowed'] && array_column($position14Access['workplaces'], 'id') === [201], 'V7C12 explicitly granted position 14 does not inherit global multi-station scope');
$login(602);
$assert(ChecadorAccessService::authorize()['reason'] === 'station_mapping_unavailable', 'C03 Encargado assigned to an inactive historical station is denied');
$login(600);
$db->table('modulos_sub_dpto_operativo')->where('id', 29)->update(['activo' => 0]);
$assert(ChecadorAccessService::authorize()['reason'] === 'submodule_denied', 'inactive Checador catalog entry denies direct access');
$db->table('modulos_sub_dpto_operativo')->where('id', 29)->update(['activo' => 1]);

// C04: a direct user parent grant activates the existing user-over-post semantics.
$db->table('modulos_sub_puestos_do')->where('id_puesto', 99)->delete();
$db->table('modulos_usuarios_do')->insert(['id_modulo' => 2, 'id_usuario' => 900, 'leer' => 1]);
$db->table('modulos_sub_puestos_do')->insert(['id_sub_modulo' => 29, 'id_puesto' => 99]);
$login(900);
$assert(ChecadorAccessService::authorize()['reason'] === 'submodule_denied', 'C04 direct-user precedence does not inherit a post Checador assignment');
$db->table('modulos_sub_usuarios_do')->insert(['id_sub_modulo' => 29, 'id_usuario' => 900]);
$assert(in_array('checador', $keys(900), true) && ChecadorAccessService::authorize()['allowed'], 'C04 individual Checador assignment works under user-over-post precedence');

// C05-C10: fail-closed authorized scope, request narrowing, multi-station and numlista.
$login(600);
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[]']);
$assert(ChecadorAccessService::authorize()['reason'] === 'empty_station_scope', 'C05 empty station scope denies');
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[101,102,103,104]', 'allow_all' => 1]);
$assert(ChecadorAccessService::authorize()['reason'] === 'station_configuration_invalid', 'C05 allow_all module config is not accepted as Checador scope');
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['allow_all' => 0]);
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[101,102,103,104]']);
$assert(ChecadorAccessService::authorize(202)['reason'] === 'requested_station_out_of_scope', 'C06 client-supplied unauthorized locality denies');
$allowed = ChecadorAccessService::authorize(201);
$assert($allowed['allowed'] && array_column($allowed['workplaces'], 'id') === [201], 'C07 authorized locality succeeds and narrows scope');
$db->table('tb_multiestacion_usuario')->insert(['id_usuario' => 600, 'estaciones' => '[102]', 'activo' => 1]);
$multi = ChecadorAccessService::authorize();
$assert($multi['allowed'] && array_column($multi['workplaces'], 'id') === [201], 'C08 Encargado Checador ignores broad global multi-station override and stays at id_gas');
$db->table('tb_multiestacion_usuario')->delete();
$assert($allowed['workplaces'][0]['id'] === 201 && $allowed['workplaces'][0]['id'] !== 101, 'C09 maps station to locality by numlista, not equal numeric IDs');
$login(1300);
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[]']);
$assert(ChecadorAccessService::authorize()['reason'] === 'empty_station_scope', 'C09 empty Checador allowlist denies Dirección');
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '*']);
$assert(ChecadorAccessService::authorize()['reason'] === 'empty_station_scope', 'C09 wildcard Checador allowlist does not become unrestricted Dirección access');
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[101,102,103,104]']);
$login(600);
$db->table('op_rh_localidades')->insert(['id' => 205, 'numlista' => 1, 'localidad' => 'Duplicada']);
$assert(ChecadorAccessService::authorize()['reason'] === 'station_mapping_unavailable', 'C10 duplicate numlista mapping fails closed');
$db->table('op_rh_localidades')->where('id', 205)->delete();
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[105]']);
$db->table('tb_estaciones')->insert(['id' => 105, 'numlista' => 5, 'nombre' => 'Sin localidad', 'estatus' => 0]);
$db->table('tb_usuarios')->where('id', 600)->update(['id_gas' => 105]);
Auth::forget();
$assert(ChecadorAccessService::authorize()['reason'] === 'station_mapping_unavailable', 'C10 missing numlista counterpart fails closed');

// C11-C14: Checador membership alone does not create unrelated legacy grants.
$db->table('modulos_usuarios_do')->insert(['id_modulo' => 2, 'id_usuario' => 901, 'leer' => 1]);
$db->table('modulos_sub_usuarios_do')->insert(['id_sub_modulo' => 29, 'id_usuario' => 901]);
$login(901);
$checadorOnly = $keys(901);
$assert($checadorOnly === ['checador'], 'C11 Checador-only user has no Programar Horario grant');
$assert(!in_array('incidencias-nomina', $checadorOnly, true), 'C12 Checador-only user has no Incidencias de Nomina grant');
$assert(!in_array('recibos-nomina', $checadorOnly, true), 'C13 Checador-only user has no Recibos de Nomina grant');
$assert(!in_array('documentos-personal', $checadorOnly, true), 'C14 Checador-only user has no Control Documentos grant');

// Existing role-specific assignments remain as before, plus Checador only.
$encargadoKeys = $keys(600);
$direccionKeys = $keys(1300);
$assert(in_array('recibos-nomina', $encargadoKeys, true), 'C16 existing Encargado Recibos grant remains assigned');
$assert(in_array('documentos-personal', $direccionKeys, true), 'C16 existing Dirección Control Documentos grant remains assigned');

// C15: the shell contains no raw-punch mutation route or write API.
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAccessService.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$checadorDispatcher = \FastRoute\simpleDispatcher(require __DIR__ . '/../routes/web.php');
$checadorIndexRoute = $checadorDispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador');
$checadorIndexHandler = $checadorIndexRoute[1] ?? null;
$checadorIndexConfig = $checadorIndexHandler instanceof Closure
    ? (new ReflectionFunction($checadorIndexHandler))->getStaticVariables()
    : [];
$assert(($checadorIndexRoute[0] ?? null) === \FastRoute\Dispatcher::FOUND
    && ($checadorIndexConfig['handler'] ?? null) === ['ChecadorController', 'index']
    && !preg_match('/PunchEvent::.*(save|update|delete)|insertRawPunch|updateRawPunch|deleteRawPunch/i', $serviceSource . $controllerSource), 'C15 grouped Checador route resolves to read shell without raw-punch mutation action');
$reportStart = strpos($controllerSource, 'public function punchReport(): void');
$crossStationDenial = is_int($reportStart)
    ? strpos($controllerSource, "renderAccessDenied('requested_station_out_of_scope'", $reportStart)
    : false;
$csvExecution = is_int($reportStart)
    ? strpos($controllerSource, 'if ($exportCsv) {', $reportStart)
    : false;
$assert(is_int($crossStationDenial) && is_int($csvExecution) && $crossStationDenial < $csvExecution, 'C15 requested station is denied before CSV export branch');

// C16-C17: original role-specific submenu sets stay intact, plus only Checador.
$expectedEncargado = ['organigrama','documentos-personal','formatos','horario-personal','biometricos','recibos-nomina','permisos','programar-horario','lista-negra','checador'];
$expectedDireccion = ['organigrama','documentos-personal','formatos','horario-personal','biometricos','recibos-nomina','permisos','incidencias-nomina','bitacora-rrhh','checador'];
$assert($encargadoKeys === $expectedEncargado, 'C16 Encargado RH submenu set is preserved with only Checador added');
$assert($direccionKeys === $expectedDireccion, 'C17 Dirección RH submenu set is preserved with only Checador added');

// V6B1 mapping case supplied for development: station 1 -> numlista 2 ->
// canonical locality 1. The synthetic locality 14 is deliberately unrelated.
$db->table('tb_estaciones')->where('id', 102)->update(['numlista' => 22]);
$db->table('op_rh_localidades')->where('id', 202)->update(['numlista' => 22]);
$db->table('tb_estaciones')->insert(['id' => 1, 'numlista' => 2, 'nombre' => 'Interlomas', 'estatus' => 0]);
$db->table('op_rh_localidades')->insert(['id' => 1, 'numlista' => 2, 'localidad' => 'Interlomas']);
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[1]']);
$login(1300);
$interlomas = ChecadorAccessService::authorize();
$interlomasSelector = ModuleStationService::render('checador');
$assert($interlomas['allowed']
    && $interlomas['workplaces'] === [['id' => 1, 'nombre' => 'Interlomas']]
    && array_column(ModuleStationService::getAvailableStations('checador'), 'id') === [1]
    && str_contains($interlomasSelector, 'Interlomas')
    && !str_contains($interlomasSelector, 'id="module-station-selector-checador"')
    && ChecadorAccessService::authorize(14)['reason'] === 'requested_station_out_of_scope',
    'V6B1 Interlomas station 1 maps through unique numlista 2 to locality 1 and does not inherit synthetic locality 14');

// V6B4 keeps Autolavado (canonical locality 9) in a separately authorized
// department group; it is not inferred from Palo Solo or a physical station.
$db->table('op_rh_localidades')->insert(['id' => 9, 'numlista' => null, 'localidad' => 'Autolavado']);
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update([
    'tipo' => 'stations_and_departments',
    'tipo_departamento' => 'localidades',
    'estaciones_soportadas' => '[101,102,104]',
    'departamentos_soportados' => '[9]',
]);
$db->table('tb_multiestacion_puesto')->where('id_puesto', 13)->update(['departamentos_localidades' => '[9]']);
$login(1300);
$directionWithDepartment = ChecadorAccessService::authorize();
$mixedSelector = ModuleStationService::render('checador');
$assert($directionWithDepartment['allowed']
    && array_column($directionWithDepartment['station_workplaces'], 'id') === [201, 202, 204]
    && array_column($directionWithDepartment['department_workplaces'], 'id') === [9]
    && array_column($directionWithDepartment['workplaces'], 'id') === [201, 202, 204, 9]
    && str_contains($mixedSelector, '<optgroup label="Estaciones">')
    && str_contains($mixedSelector, '<optgroup label="Departamentos">')
    && str_contains($mixedSelector, 'value="depto_9"')
    && !str_contains($mixedSelector, 'value="estacion_9"'),
    'V6B4 Dirección sees its supported stations plus explicitly granted Autolavado in separate selector groups');
$assert(ModuleStationService::setChecadorWorkplaceContext(9)
    && ModuleStationService::getContext('checador')['id_depto'] === 9
    && ModuleStationService::getContext('checador')['id_estacion'] === null,
    'V6B4 canonical locality 9 is persisted as a department context, not a gas station');
$db->table('tb_estaciones')->insert(['id' => 111, 'numlista' => 7, 'nombre' => 'Palo Solo', 'estatus' => 0]);
$db->table('op_rh_localidades')->insert(['id' => 2, 'numlista' => 7, 'localidad' => 'Palo Solo']);
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[101,102,104,111]']);
$db->table('tb_multiestacion_puesto')->where('id_puesto', 6)->update(['departamentos_localidades' => '[9]']);
$db->table('tb_usuarios')->insert(['id' => 650, 'id_gas' => 111, 'id_puesto' => 6, 'estatus' => 0]);
$login(650);
$paloSolo = ChecadorAccessService::authorize();
$assert($paloSolo['allowed']
    && array_column($paloSolo['station_workplaces'], 'id') === [2]
    && ($paloSolo['department_workplaces'] ?? []) === []
    && ChecadorAccessService::authorize(9)['reason'] === 'requested_station_out_of_scope',
    'V7C12 position-level Autolavado grant is not inherited by ordinary Encargados');
$db->table('tb_usuarios')->insert(['id' => 651, 'id_gas' => 111, 'id_puesto' => 6, 'estatus' => 0]);
$db->table('tb_multiestacion_usuario')->insert([
    'id_usuario' => 651,
    'estaciones' => '[101,104]',
    'departamentos_localidades' => '[9]',
    'activo' => 1,
]);
$login(651);
$authorizedPaloSolo = ChecadorAccessService::authorize();
$assert($authorizedPaloSolo['allowed']
    && array_column($authorizedPaloSolo['station_workplaces'], 'id') === [2]
    && array_column($authorizedPaloSolo['department_workplaces'], 'id') === [9]
    && array_column($authorizedPaloSolo['workplaces'], 'id') === [2, 9]
    && ChecadorAccessService::authorize(9)['allowed']
    && ChecadorAccessService::authorize(1)['reason'] === 'requested_station_out_of_scope',
    'V7C12 individually authorized Palo Solo Encargado gets only Palo Solo 2 and Autolavado 9 despite a broad global station row');
$db->table('tb_multiestacion_usuario')->where('id_usuario', 470)->update(['departamentos_localidades' => '[9]']);
$login(470);
$assert(array_column(ChecadorAccessService::authorize()['station_workplaces'], 'id') === [204]
    && (ChecadorAccessService::authorize()['department_workplaces'] ?? []) === []
    && ChecadorAccessService::authorize(9)['reason'] === 'requested_station_out_of_scope',
    'V7C12 non-Palo Solo Encargado 470 cannot use an individual department scope to access Autolavado');

// V7C12 writes remain separate from read access: an authorized Checador
// reader cannot create an incidence until the dedicated `crear` capability
// is granted on the incidence module itself.
$db->table('modulos_dpto_operativo')->insert([
    'id' => 64,
    'clave' => ChecadorAccessService::INCIDENCE_MODULE_KEY,
    'nombre' => 'Incidencias Checador V2',
    'activo' => 1,
]);
$db->table('modulos_puestos_do')->insert([
    'id_modulo' => 64,
    'id_puesto' => 13,
    'leer' => 1,
    'crear' => 0,
]);
$login(1300);
$createDenied = ChecadorAccessService::authorizeIncidenceCreate(201);
$assert(!$createDenied['allowed'] && $createDenied['reason'] === 'incidence_create_permission_denied',
    'V7C12 Checador read permission does not imply incidence-create permission');
$db->table('modulos_puestos_do')->where('id_modulo', 64)->where('id_puesto', 13)->update(['crear' => 1]);
$createAllowed = ChecadorAccessService::authorizeIncidenceCreate(201);
$assert($createAllowed['allowed']
    && $createAllowed['namespace'] === ChecadorAccessService::INCIDENCE_MODULE_KEY
    && array_column($createAllowed['workplaces'], 'id') === [201],
    'V7C12 incidence creation requires its dedicated create grant and retains canonical station scope');

printf("CHECADOR_ACCESS_FOUNDATION_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

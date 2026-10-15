<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Auth;
use App\Core\Session;
use App\Services\ChecadorAccessService;
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
    $table->integer('id')->primary(); $table->integer('numlista')->nullable(); $table->string('nombre');
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
$db->table('tb_puestos')->insert([['id' => 6], ['id' => 13], ['id' => 99]]);
$db->table('tb_usuarios')->insert([
    ['id' => 600, 'id_gas' => 101, 'id_puesto' => 6, 'estatus' => 0],
    ['id' => 1300, 'id_gas' => 101, 'id_puesto' => 13, 'estatus' => 0],
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
    ['id' => 101, 'numlista' => 1, 'nombre' => 'Estacion uno'],
    ['id' => 102, 'numlista' => 2, 'nombre' => 'Estacion dos'],
]);
$db->table('op_rh_localidades')->insert([
    ['id' => 201, 'numlista' => 1, 'localidad' => 'Localidad uno'],
    ['id' => 202, 'numlista' => 2, 'localidad' => 'Localidad dos'],
]);
$db->table('tb_modulos_config')->insert([
    'modulo_key' => 'checador', 'tipo' => 'stations', 'estaciones_soportadas' => '[101,102]',
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

// C03: post membership drives both resolved menu and server authorization.
$login(600);
$postMenu = $keys(600);
$postAccess = ChecadorAccessService::authorize();
$assert(in_array('checador', $postMenu, true) && $postAccess['allowed'], 'C03 post assignment resolves menu and authorizes with valid scope');
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
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[101,102]']);
$assert(ChecadorAccessService::authorize(202)['reason'] === 'requested_station_out_of_scope', 'C06 client-supplied unauthorized locality denies');
$allowed = ChecadorAccessService::authorize(201);
$assert($allowed['allowed'] && array_column($allowed['workplaces'], 'id') === [201], 'C07 authorized locality succeeds and narrows scope');
$db->table('tb_multiestacion_usuario')->insert(['id_usuario' => 600, 'estaciones' => '[102]', 'activo' => 1]);
$multi = ChecadorAccessService::authorize();
$assert($multi['allowed'] && array_column($multi['workplaces'], 'id') === [202], 'C08 multi-station user sees only effective authorized locality');
$db->table('tb_multiestacion_usuario')->delete();
$assert($allowed['workplaces'][0]['id'] === 201 && $allowed['workplaces'][0]['id'] !== 101, 'C09 maps station to locality by numlista, not equal numeric IDs');
$db->table('op_rh_localidades')->insert(['id' => 203, 'numlista' => 1, 'localidad' => 'Duplicada']);
$assert(ChecadorAccessService::authorize()['reason'] === 'station_mapping_unavailable', 'C10 duplicate numlista mapping fails closed');
$db->table('op_rh_localidades')->where('id', 203)->delete();
$db->table('tb_modulos_config')->where('modulo_key', 'checador')->update(['estaciones_soportadas' => '[103]']);
$db->table('tb_estaciones')->insert(['id' => 103, 'numlista' => 3, 'nombre' => 'Sin localidad']);
$db->table('tb_usuarios')->where('id', 600)->update(['id_gas' => 103]);
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
$routeSource = file_get_contents(__DIR__ . '/../routes/web.php');
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAccessService.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$assert(str_contains($routeSource, "'/departamento-operativo/recursos-humanos/checador'")
    && !preg_match('/PunchEvent::.*(save|update|delete)|addRoute\(\s*[\'\"]POST[\'\"].*checador.*(punch|event)/i', $routeSource . $serviceSource . $controllerSource), 'C15 shell has no raw PunchEvent mutation action');

// C16-C17: original role-specific submenu sets stay intact, plus only Checador.
$expectedEncargado = ['organigrama','documentos-personal','formatos','horario-personal','biometricos','recibos-nomina','permisos','programar-horario','lista-negra','checador'];
$expectedDireccion = ['organigrama','documentos-personal','formatos','horario-personal','biometricos','recibos-nomina','permisos','incidencias-nomina','bitacora-rrhh','checador'];
$assert($encargadoKeys === $expectedEncargado, 'C16 Encargado RH submenu set is preserved with only Checador added');
$assert($direccionKeys === $expectedDireccion, 'C17 Dirección RH submenu set is preserved with only Checador added');

printf("CHECADOR_ACCESS_FOUNDATION_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorPermissionGrantAdminService;
use App\Core\Auth;
use App\Core\Session;
use Illuminate\Database\Capsule\Manager as Capsule;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { echo "PASS {$message}\n"; $passes++; }
    else { echo "FAIL {$message}\n"; $fails++; }
};

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::connection('default')->getSchemaBuilder();

$schema->create('tb_usuarios', static function ($table): void {
    $table->increments('id'); $table->integer('id_gas')->nullable(); $table->integer('id_puesto');
    $table->string('nombre')->nullable(); $table->integer('estatus')->default(0);
});
$schema->create('tb_puestos', static function ($table): void {
    $table->increments('id'); $table->string('tipo_puesto')->nullable(); $table->integer('estatus')->default(0);
});
$schema->create('modulos_dpto_operativo', static function ($table): void {
    $table->increments('id'); $table->string('nombre'); $table->string('clave'); $table->string('ruta')->nullable();
    $table->string('icono')->nullable(); $table->integer('activo')->default(1);
});
$createParentPivot = static function ($table, string $identity): void {
    $table->increments('id'); $table->integer('id_modulo'); $table->integer($identity);
    foreach (['leer','crear','editar','eliminar','descargar'] as $field) $table->integer($field)->default(0);
};
$schema->create('modulos_puestos_do', static fn($table) => $createParentPivot($table, 'id_puesto'));
$schema->create('modulos_usuarios_do', static fn($table) => $createParentPivot($table, 'id_usuario'));
$schema->create('modulos_sub_dpto_operativo', static function ($table): void {
    $table->increments('id'); $table->integer('id_modulo'); $table->string('nombre'); $table->string('clave');
    $table->string('ruta')->nullable(); $table->string('icono')->nullable(); $table->integer('activo')->default(1);
});
$schema->create('modulos_sub_puestos_do', static function ($table): void {
    $table->increments('id'); $table->integer('id_sub_modulo'); $table->integer('id_puesto');
});
$schema->create('modulos_sub_usuarios_do', static function ($table): void {
    $table->increments('id'); $table->integer('id_sub_modulo'); $table->integer('id_usuario');
});
$schema->create('tb_modulos_config', static function ($table): void {
    $table->increments('id'); $table->string('modulo_key'); $table->string('tipo');
    $table->text('estaciones_soportadas')->nullable(); $table->text('departamentos_soportados')->nullable();
    $table->string('tipo_departamento')->nullable(); $table->boolean('allow_all')->default(false);
    $table->string('placeholder')->nullable(); $table->boolean('activo')->default(true);
});
$schema->create('tb_estaciones', static function ($table): void {
    $table->integer('id')->primary(); $table->integer('numlista')->nullable(); $table->string('nombre')->nullable();
    $table->integer('estatus')->default(0);
});
$schema->create('op_rh_localidades', static function ($table): void {
    $table->integer('id')->primary(); $table->integer('numlista')->nullable(); $table->string('localidad')->nullable();
});
$schema->create('tb_multiestacion_puesto', static function ($table): void {
    $table->increments('id'); $table->integer('id_puesto'); $table->text('estaciones')->nullable();
    $table->text('departamentos_puestos')->nullable(); $table->text('departamentos_localidades')->nullable();
    $table->boolean('activo')->default(true);
});
$schema->create('op_rh_v2_permission_grants', static function ($table): void {
    $table->increments('id'); $table->string('permission_key'); $table->string('principal_type'); $table->integer('principal_id');
    $table->integer('granted'); $table->integer('active'); $table->string('reason'); $table->string('source_task');
    $table->integer('updated_by_user_id')->nullable(); $table->string('created_at_utc'); $table->string('updated_at_utc');
    $table->unique(['principal_id','permission_key','principal_type'], 'permission_grant_unique');
});
$createAudit = static function (bool $withChangeColumn) use ($schema): void {
    $schema->create('op_rh_v2_m11_configuration_audit', static function ($table) use ($withChangeColumn): void {
        $table->increments('id'); $table->integer('actor_portal_user_id'); $table->string('permission_key');
        $table->integer('workplace_id')->nullable(); $table->string('entity_type'); $table->integer('entity_id');
        $table->string('action');
        if ($withChangeColumn) $table->text('permission_change_json')->nullable();
        $table->string('created_at_utc');
    });
};
$createAudit(false);

$db = Capsule::connection('default');
$db->table('tb_puestos')->insert([
    ['id' => 6, 'tipo_puesto' => 'Encargado', 'estatus' => 0],
    ['id' => 13, 'tipo_puesto' => 'Dirección de operaciones', 'estatus' => 0],
    ['id' => 99, 'tipo_puesto' => 'Otro', 'estatus' => 0],
]);
$db->table('tb_usuarios')->insert([
    ['id' => 100, 'id_gas' => 101, 'id_puesto' => 13, 'nombre' => 'Administrador global', 'estatus' => 0],
    ['id' => 101, 'id_gas' => 101, 'id_puesto' => 13, 'nombre' => 'Solo lectura', 'estatus' => 0],
    ['id' => 102, 'id_gas' => 101, 'id_puesto' => 13, 'nombre' => 'Denegado individualmente', 'estatus' => 0],
    ['id' => 103, 'id_gas' => 101, 'id_puesto' => 6, 'nombre' => 'Encargado local', 'estatus' => 0],
    ['id' => 105, 'id_gas' => 101, 'id_puesto' => 13, 'nombre' => 'Sin opción de capacidades', 'estatus' => 0],
    ['id' => 200, 'id_gas' => 101, 'id_puesto' => 6, 'nombre' => 'Objetivo', 'estatus' => 0],
    ['id' => 201, 'id_gas' => 101, 'id_puesto' => 6, 'nombre' => 'Objetivo dos', 'estatus' => 0],
    ['id' => 202, 'id_gas' => 101, 'id_puesto' => 13, 'nombre' => 'Objetivo Dirección', 'estatus' => 0],
    ['id' => 203, 'id_gas' => 101, 'id_puesto' => 6, 'nombre' => 'Sin grant individual', 'estatus' => 0],
    ['id' => 300, 'id_gas' => 101, 'id_puesto' => 6, 'nombre' => 'Inactivo', 'estatus' => 1],
]);
$db->table('modulos_dpto_operativo')->insert(['id' => 2, 'nombre' => 'Recursos Humanos', 'clave' => 'recursos-humanos', 'ruta' => '/recursos-humanos', 'activo' => 1]);
$db->table('modulos_sub_dpto_operativo')->insert(['id' => 63, 'id_modulo' => 2, 'nombre' => 'Checador', 'clave' => 'checador', 'ruta' => '/checador', 'activo' => 1]);
$db->table('modulos_puestos_do')->insert(['id_modulo' => 2, 'id_puesto' => 13, 'leer' => 1, 'editar' => 1]);
$db->table('modulos_sub_puestos_do')->insert(['id_sub_modulo' => 63, 'id_puesto' => 13]);
$db->table('modulos_usuarios_do')->insert([
    ['id_modulo' => 2, 'id_usuario' => 101, 'leer' => 1, 'editar' => 0],
    ['id_modulo' => 2, 'id_usuario' => 102, 'leer' => 0, 'editar' => 0],
    ['id_modulo' => 2, 'id_usuario' => 103, 'leer' => 1, 'editar' => 1],
    ['id_modulo' => 2, 'id_usuario' => 105, 'leer' => 1, 'editar' => 1],
]);
$db->table('modulos_sub_usuarios_do')->insert([
    ['id_sub_modulo' => 63, 'id_usuario' => 101],
    ['id_sub_modulo' => 63, 'id_usuario' => 102],
    ['id_sub_modulo' => 63, 'id_usuario' => 103],
    ['id_sub_modulo' => 63, 'id_usuario' => 105],
]);
$db->table('tb_modulos_config')->insert([
    'modulo_key' => 'checador', 'tipo' => 'stations_only', 'estaciones_soportadas' => '[101,102]',
    'departamentos_soportados' => '[]', 'tipo_departamento' => 'puestos', 'allow_all' => 0,
    'placeholder' => null, 'activo' => 1,
]);
$db->table('tb_estaciones')->insert([
    ['id' => 101, 'numlista' => 1, 'nombre' => 'Interlomas', 'estatus' => 0],
    ['id' => 102, 'numlista' => 2, 'nombre' => 'Palo Solo', 'estatus' => 0],
]);
$db->table('op_rh_localidades')->insert([
    ['id' => 1, 'numlista' => 1, 'localidad' => 'Interlomas'],
    ['id' => 2, 'numlista' => 2, 'localidad' => 'Palo Solo'],
]);
$db->table('tb_multiestacion_puesto')->insert(['id_puesto' => 13, 'estaciones' => '[101,102]', 'activo' => 1]);
$_SESSION = [];
Session::set('usuario', ['id' => 100]);
Auth::forget();
$db->table('op_rh_v2_permission_grants')->insert([
    ['permission_key' => 'checador.admin_pin.emitir', 'principal_type' => 'POSITION', 'principal_id' => 6, 'granted' => 1, 'active' => 1, 'reason' => 'Autorización inicial', 'source_task' => 'fixture', 'updated_by_user_id' => 100, 'created_at_utc' => '2026-11-25 12:00:00', 'updated_at_utc' => '2026-11-25 12:00:00'],
    ['permission_key' => 'checador.admin_pin.emitir', 'principal_type' => 'USER', 'principal_id' => 200, 'granted' => 0, 'active' => 1, 'reason' => 'Denegación explícita', 'source_task' => 'fixture', 'updated_by_user_id' => 100, 'created_at_utc' => '2026-11-25 12:00:00', 'updated_at_utc' => '2026-11-25 12:00:00'],
    ['permission_key' => 'checador.admin_pin.emitir', 'principal_type' => 'USER', 'principal_id' => 201, 'granted' => 1, 'active' => 0, 'reason' => 'Decisión inactiva', 'source_task' => 'fixture', 'updated_by_user_id' => 100, 'created_at_utc' => '2026-11-25 12:00:00', 'updated_at_utc' => '2026-11-25 12:00:00'],
]);

$service = new ChecadorPermissionGrantAdminService();
$loginAs = static function (int $id): void {
    Session::set('usuario', ['id' => $id]);
    Auth::forget();
};
$assert($service->actorAccess(100)['can_read'] && $service->actorAccess(100)['can_write'], 'effective RH edit ACL, Checador membership, and full scope authorize without a capability self-grant');
$assert((int) $db->table('op_rh_v2_permission_grants')->where('principal_type', 'USER')->where('principal_id', 100)->count() === 0,
    'initial administrator access is independent of grants being administered');
$grantServiceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorPermissionGrantAdminService.php');
$assert(str_contains($grantServiceSource, "'recursos-humanos'") && !str_contains($grantServiceSource, "'configuracion'"),
    'administration checks effective Recursos Humanos permissions without requiring General Configuration access');
$userPanel = $service->userPanel(100, 200);
$emit = array_values(array_filter($userPanel['items'], static fn(array $item): bool => $item['key'] === 'checador.admin_pin.emitir'))[0] ?? null;
$assert($userPanel['visible'] && $emit !== null && $emit['effective_state'] === 'Denegado' && $emit['effective_source'] === 'Individual', 'individual explicit denial overrides an allowing position grant');
$inactivePanel = $service->userPanel(100, 201);
$inactiveEmit = array_values(array_filter($inactivePanel['items'], static fn(array $item): bool => $item['key'] === 'checador.admin_pin.emitir'))[0] ?? null;
$assert($inactiveEmit !== null && $inactiveEmit['effective_state'] === 'Denegado (inactivo)' && $inactiveEmit['effective_source'] === 'Individual', 'inactive individual row remains an explicit denial instead of falling back to position');
$assert($userPanel['can_write'] === false && $userPanel['blocker'] !== null, 'missing durable audit extension keeps writes fail-closed while allowing inspection');
$assert($service->update(100, 'USER', 200, 'checador.admin_pin.emitir', 'allow', 'Cambio aprobado por RH', (string) ($emit['expected_state'] ?? str_repeat('a', 64)))['code'] === 'audit_schema_pending', 'no capability grant changes before the audit schema is ready');
$assert((int) $db->table('op_rh_v2_permission_grants')->where('principal_type', 'USER')->where('principal_id', 200)->value('granted') === 0, 'closed audit gate leaves the existing denial unchanged');

$userWithoutOverride = $service->userPanel(100, 203);
$inheritedEmit = array_values(array_filter($userWithoutOverride['items'], static fn(array $item): bool => $item['key'] === 'checador.admin_pin.emitir'))[0] ?? null;
$assert($inheritedEmit !== null && $inheritedEmit['effective_state'] === 'Permitido' && $inheritedEmit['effective_source'] === 'Puesto', 'effective permission is inherited from the position when no individual row exists');
$position13 = $service->positionPanel(100, 13);
$assert(!$position13['visible'], 'global administrator cannot edit their own position through indirect grants');
$user13Panel = $service->userPanel(100, 202);
$adminPinAt13 = array_values(array_filter($user13Panel['items'], static fn(array $item): bool => $item['key'] === 'checador.admin_pin.emitir'))[0] ?? null;
$deviceReadAt13 = array_values(array_filter($user13Panel['items'], static fn(array $item): bool => $item['key'] === 'checador-v2-dispositivos.leer'))[0] ?? null;
$assert($adminPinAt13 !== null && !$adminPinAt13['applicable'] && $deviceReadAt13 !== null && $deviceReadAt13['applicable'], 'Dirección user target can receive device read only, never administrative PIN capabilities');
$selfUserPanel = $service->userPanel(100, 100);
$selfPositionPanel = $service->positionPanel(100, 13);
$assert(!$selfUserPanel['visible'] && !$selfPositionPanel['visible'], 'administrator cannot assign capabilities to their own user or position');
$assert($service->update(100, 'USER', 100, 'checador.admin_pin.emitir', 'allow', 'Cambio aprobado por RH', str_repeat('a', 64))['code'] === 'principal_out_of_scope'
    && $service->update(100, 'POSITION', 13, 'checador-v2-dispositivos.leer', 'allow', 'Cambio aprobado por RH', str_repeat('a', 64))['code'] === 'principal_out_of_scope', 'direct and position-mediated self-grants are rejected server-side');
$loginAs(101);
$readOnlyPanel = $service->userPanel(101, 201);
$assert($readOnlyPanel['visible'] && !$readOnlyPanel['can_write'], 'read-only administrator can inspect grants but cannot manage them');
$loginAs(102);
$assert(!$service->actorAccess(102)['can_read'] && !$service->userPanel(102, 201)['visible'], 'explicit individual denial blocks the capability administration UI');
$loginAs(105);
$assert($service->actorAccess(105)['can_read'] && $service->actorAccess(105)['can_write'],
    'authorized administrator does not need a separate, unregistered capability submodule to reach the panel');
$loginAs(103);
$localAdminAccess = $service->actorAccess(103);
$assert(!$localAdminAccess['can_read'] && $localAdminAccess['show_entry'] === true
    && $localAdminAccess['reason'] === 'global_scope_required', 'station-scoped Encargado cannot open global grants');
$loginAs(100);
$targetDirectory = $service->targetDirectory(100);
$assert($targetDirectory['ok'] && in_array(6, array_column($targetDirectory['positions'], 'id'), true)
    && !in_array(13, array_column($targetDirectory['positions'], 'id'), true)
    && !in_array(100, array_column($targetDirectory['users'], 'id'), true)
    && in_array(202, array_column($targetDirectory['users'], 'id'), true)
    && !in_array(99, array_column($targetDirectory['positions'], 'id'), true), 'directory excludes self-grants and out-of-catalog positions while allowing eligible user grants');
$assert($service->userPanel(100, 300)['visible'] === false, 'inactive target users cannot be administered');

$schema->table('op_rh_v2_m11_configuration_audit', static function ($table): void {
    $table->text('permission_change_json')->nullable();
});
$readyPanel = $service->userPanel(100, 200);
$readyEmit = array_values(array_filter($readyPanel['items'], static fn(array $item): bool => $item['key'] === 'checador.admin_pin.emitir'))[0] ?? null;
$assert($readyPanel['can_write'] && $readyEmit !== null, 'audit extension and required unique grant index enable reviewed writes');
$changed = $service->update(100, 'USER', 200, 'checador.admin_pin.emitir', 'allow', 'Cambio aprobado por RH', $readyEmit['expected_state']);
$row = $db->table('op_rh_v2_permission_grants')->where('permission_key', 'checador.admin_pin.emitir')->where('principal_type', 'USER')->where('principal_id', 200)->first();
$audit = $db->table('op_rh_v2_m11_configuration_audit')->where('entity_type', 'CHECADOR_PERMISSION')->first();
$assert($changed['ok'] && $row !== null && (int) $row->granted === 1 && (int) $row->active === 1, 'user allow decision is persisted and read back');
$auditChange = $audit === null ? null : json_decode((string) $audit->permission_change_json, true);
$assert($audit !== null && (int) $audit->actor_portal_user_id === 100 && $audit->created_at_utc !== ''
    && ($auditChange['reason'] ?? null) === 'Cambio aprobado por RH'
    && isset($auditChange['before'], $auditChange['after']), 'permission audit stores actor, UTC timestamp, reason, and before/after projection');
$history = $service->auditHistory(100, 'USER', 200);
$assert($history['status'] === 'ready' && count($history['items']) === 1
    && $history['items'][0]['reason'] === 'Cambio aprobado por RH'
    && $history['items'][0]['principal_type'] === 'USER', 'Checador history reads back the recorded actor, principal, and reason');
$panelForDeny = $service->userPanel(100, 200);
$denyItem = array_values(array_filter($panelForDeny['items'], static fn(array $item): bool => $item['key'] === 'checador.admin_pin.emitir'))[0];
$denied = $service->update(100, 'USER', 200, 'checador.admin_pin.emitir', 'deny', 'Retiro de autorización', $denyItem['expected_state']);
$afterDeny = $service->userPanel(100, 200);
$denyEffective = array_values(array_filter($afterDeny['items'], static fn(array $item): bool => $item['key'] === 'checador.admin_pin.emitir'))[0];
$assert($denied['ok'] && $denyEffective['effective_state'] === 'Denegado' && $denyEffective['effective_source'] === 'Individual', 'explicit individual denial is persisted and takes precedence');

$panelForInherit = $service->userPanel(100, 200);
$inheritItem = array_values(array_filter($panelForInherit['items'], static fn(array $item): bool => $item['key'] === 'checador.admin_pin.emitir'))[0];
$inherited = $service->update(100, 'USER', 200, 'checador.admin_pin.emitir', 'inherit', 'Restaurar criterio del puesto', $inheritItem['expected_state']);
$afterInherit = $service->userPanel(100, 200);
$inheritEffective = array_values(array_filter($afterInherit['items'], static fn(array $item): bool => $item['key'] === 'checador.admin_pin.emitir'))[0];
$assert($inherited['ok'] && !$inheritEffective['direct_exists'] && $inheritEffective['effective_source'] === 'Puesto' && $inheritEffective['effective_state'] === 'Permitido', 'inherit deletes the individual grant and restores the post decision');
$secretReason = $service->update(100, 'USER', 200, 'checador.admin_pin.emitir', 'allow', 'PIN: 123456', $inheritItem['expected_state']);
$unknownKey = $service->update(100, 'USER', 200, 'checador.admin_pin.no_autorizada', 'allow', 'Cambio aprobado RH', $inheritItem['expected_state']);
$assert($secretReason['code'] === 'invalid_input' && $unknownKey['code'] === 'invalid_input', 'secret-like reasons and permissions outside the closed catalog are rejected');
$assert((int) $db->table('op_rh_v2_permission_grants')->where('principal_type', 'USER')->where('principal_id', 200)->count() === 0, 'rejected inputs do not create or alter permission grants');

$source = file_get_contents(__DIR__ . '/../app/Views/sistemas/partials/checador-capability-grants-panel.php');
$routes = file_get_contents(__DIR__ . '/../routes/web.php');
$puestoMenuView = file_get_contents(__DIR__ . '/../app/Views/configuracion/modulos-puestos-configuracion.php');
$usuarioMenuView = file_get_contents(__DIR__ . '/../app/Views/configuracion/modulos-usuarios-configuracion.php');
$configController = file_get_contents(__DIR__ . '/../app/Controllers/ConfiguracionController.php');
$checadorConfigurationView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/configuration.php');
$assert(!str_contains($puestoMenuView, 'checador-capability-grants-panel.php') && !str_contains($usuarioMenuView, 'checador-capability-grants-panel.php'), 'capability management is removed from General Configuration position and user screens');
$assert(str_contains($source, 'expected_state') && str_contains($source, '_csrf_token') && str_contains($source, 'disabled') && str_contains($source, 'Heredar del puesto'), 'panel includes CSRF, optimistic state, disabled gate, and explicit inheritance UI');
$assert(!str_contains($routes, 'updatePuestoChecadorCapabilities') && !str_contains($routes, 'updateUsuarioChecadorCapabilities'), 'legacy General Configuration capability POST routes are removed');
$dispatcher = FastRoute\simpleDispatcher(require __DIR__ . '/../routes/web.php');
$positionRoute = $dispatcher->dispatch('POST', '/departamento-operativo/recursos-humanos/checador/configuracion/permisos-capacidades/puestos/6');
$userRoute = $dispatcher->dispatch('POST', '/departamento-operativo/recursos-humanos/checador/configuracion/permisos-capacidades/usuarios/200');
$capabilityIndexRoute = $dispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/configuracion/permisos-capacidades');
$capabilityUserRoute = $dispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/configuracion/permisos-capacidades/usuarios/200');
$checadorConfigurationRoute = $dispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/configuracion');
$assert($positionRoute[0] === FastRoute\Dispatcher::FOUND && $userRoute[0] === FastRoute\Dispatcher::FOUND
    && $capabilityIndexRoute[0] === FastRoute\Dispatcher::FOUND && $capabilityUserRoute[0] === FastRoute\Dispatcher::FOUND,
    'Checador exposes authenticated capability read and update routes for positions and users');
$assert($checadorConfigurationRoute[0] === FastRoute\Dispatcher::FOUND
    && str_contains($checadorConfigurationView, 'id="checador-capability-grants"')
    && str_contains($checadorConfigurationView, 'Permisos y capacidades')
    && str_contains($checadorConfigurationView, "if (!empty(\$capabilityGrantAccess['can_write']))")
    && str_contains($checadorConfigurationView, 'card bg-body border rounded-3 shadow-sm card-hover h-100 mb-0')
    && str_contains($checadorConfigurationView, 'card-body d-flex align-items-center gap-3 p-3 p-lg-4')
    && !str_contains($checadorConfigurationView, 'aria-disabled="true"'),
    'the enabled capability card uses existing Checador classes and is hidden entirely when the actor cannot edit');
$assert(!str_contains($configController, 'updatePuestoChecadorCapabilities') && !str_contains($configController, 'updateUsuarioChecadorCapabilities'), 'General Configuration controller no longer exposes Checador capability mutation handlers');

$assert(!$service->userPanel(100, 100)['visible'], 'self administration stays unavailable in the rendered capability panel');

echo "SUMMARY {$passes} PASS / {$fails} FAIL (isolated SQLite only; no operational DB writes)\n";
exit($fails === 0 ? 0 : 1);

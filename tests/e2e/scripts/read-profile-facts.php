<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Core\Database;
use App\Services\ChecadorDailyReadService;

$profiles = [
    'encargado' => ['user_id' => 33, 'position_id' => 6],
    'encargado-palo-solo' => ['user_id' => 52, 'position_id' => 6],
    'direccion-operaciones' => ['user_id' => 19, 'position_id' => 13],
];
$profileKey = is_string($argv[1] ?? null) ? $argv[1] : '';
$profile = $profiles[$profileKey] ?? null;
$fail = static function (string $reason): never {
    echo json_encode(['ok' => false, 'reason' => $reason], JSON_UNESCAPED_SLASHES);
    exit(0);
};
if ($profile === null) $fail('unsupported_profile');

try {
    $root = dirname(__DIR__, 3);
    Dotenv::createImmutable($root)->safeLoad();
    if (!in_array(strtolower((string) ($_ENV['APP_ENV'] ?? '')), ['local', 'dev', 'development'], true)) {
        $fail('non_local_application_environment');
    }
    if (!in_array(strtolower((string) ($_ENV['DB_HOST'] ?? '')), ['localhost', '127.0.0.1', '::1'], true)) {
        $fail('non_local_database_host');
    }
    Database::initialize();
    $db = Capsule::connection();

    // Deliberately select no username, password, hash, email, cookie, or token.
    $user = $db->table('tb_usuarios')
        ->where('id', $profile['user_id'])
        ->select(['id', 'id_puesto', 'id_gas', 'estatus', 'nombre'])
        ->first();
    if (!$user || (int) $user->estatus !== 0 || (int) $user->id_puesto !== $profile['position_id']) {
        $fail('account_inactive_or_position_mismatch');
    }

    $hasUserOverrides = $db->table('modulos_usuarios_do')->where('id_usuario', $profile['user_id'])->exists();
    $modulePermission = static function (string $key) use ($db, $hasUserOverrides, $profile): array {
        $module = $db->table('modulos_dpto_operativo')->where('clave', $key)->where('activo', 1)->first(['id']);
        if (!$module) return ['leer' => 0, 'crear' => 0];
        $query = $hasUserOverrides
            ? $db->table('modulos_usuarios_do')->where('id_usuario', $profile['user_id'])
            : $db->table('modulos_puestos_do')->where('id_puesto', $profile['position_id']);
        $grant = $query->where('id_modulo', $module->id)->first(['leer', 'crear']);
        return ['leer' => (int) ($grant->leer ?? 0), 'crear' => (int) ($grant->crear ?? 0)];
    };

    $parent = $db->table('modulos_dpto_operativo')->where('clave', 'recursos-humanos')->where('activo', 1)->first(['id']);
    $parentPermissions = $modulePermission('recursos-humanos');
    $submenu = $parent
        ? $db->table('modulos_sub_dpto_operativo')->where('id_modulo', $parent->id)->where('clave', 'checador')->where('activo', 1)->first(['id'])
        : null;
    $submenuGranted = false;
    if ($submenu) {
        $submenuQuery = $hasUserOverrides
            ? $db->table('modulos_sub_usuarios_do')->where('id_usuario', $profile['user_id'])
            : $db->table('modulos_sub_puestos_do')->where('id_puesto', $profile['position_id']);
        $submenuGranted = $submenuQuery->where('id_sub_modulo', $submenu->id)->exists();
    }

    $config = $db->table('tb_modulos_config')->where('modulo_key', 'checador')->where('activo', 1)->first([
        'tipo', 'tipo_departamento', 'estaciones_soportadas', 'departamentos_soportados', 'allow_all',
    ]);
    if (!$config || (bool) $config->allow_all
        || !in_array((string) $config->tipo, ['stations_only', 'stations_and_departments'], true)) {
        $fail('checador_scope_configuration_invalid');
    }
    $supportedStationIds = json_decode((string) $config->estaciones_soportadas, true);
    if (!is_array($supportedStationIds) || $supportedStationIds === []) $fail('checador_scope_configuration_empty');
    $supportedStationIds = array_values(array_unique(array_map('intval', $supportedStationIds)));
    $stationIds = match ($profile['position_id']) {
        6 => in_array((int) $user->id_gas, $supportedStationIds, true) ? [(int) $user->id_gas] : [],
        13 => $supportedStationIds,
        default => [],
    };

    $localities = [];
    foreach ($stationIds as $stationId) {
        $stations = $db->table('tb_estaciones')->where('id', $stationId)->where('estatus', 0)->get(['numlista']);
        if ($stations->count() !== 1) continue;
        $numlista = (int) $stations->first()->numlista;
        if ($numlista < 1
            || $db->table('tb_estaciones')->where('numlista', $numlista)->count() !== 1
            || $db->table('op_rh_localidades')->where('numlista', $numlista)->count() !== 1) continue;
        $locality = $db->table('op_rh_localidades')->where('numlista', $numlista)->first(['id', 'localidad']);
        if ($locality) $localities[] = ['id' => (int) $locality->id, 'name' => (string) $locality->localidad];
    }

    // Mirror the approved Dirección de Operaciones department rule: the
    // module's explicit locality allowlist intersects the effective
    // multi-station department scope. Null/wildcard/empty scopes do not grant
    // a department here. This is a read-only E2E fact check, not an ACL write.
    if (($profile['position_id'] === 13 || $profileKey === 'encargado-palo-solo')
        && (string) $config->tipo === 'stations_and_departments'
        && (string) $config->tipo_departamento === 'localidades') {
        $decodeScope = static function (mixed $value): array {
            if ($value === null) return ['inherit' => true, 'ids' => null];
            if (is_array($value)) return ['inherit' => false, 'ids' => $value];
            if (!is_string($value)) return ['inherit' => false, 'ids' => null];
            $trimmed = trim($value);
            if ($trimmed === 'null') return ['inherit' => true, 'ids' => null];
            if ($trimmed === '' || $trimmed === '*') return ['inherit' => false, 'ids' => null];
            $decoded = json_decode($trimmed, true);
            return ['inherit' => false, 'ids' => is_array($decoded) ? $decoded : null];
        };
        $positionScope = $db->table('tb_multiestacion_puesto')
            ->where('id_puesto', $profile['position_id'])->where('activo', 1)
            ->first(['departamentos_localidades']);
        $userScope = $db->table('tb_multiestacion_usuario')
            ->where('id_usuario', $profile['user_id'])->where('activo', 1)
            ->first(['departamentos_localidades']);
        $positionDepartments = $decodeScope($positionScope->departamentos_localidades ?? null);
        $userDepartments = $decodeScope($userScope->departamentos_localidades ?? null);
        $effectiveDepartments = $userScope && !$userDepartments['inherit']
            ? $userDepartments['ids']
            : $positionDepartments['ids'];
        $supportedDepartments = $decodeScope($config->departamentos_soportados)['ids'];
        if (is_array($effectiveDepartments) && is_array($supportedDepartments)) {
            $stationLocalityIds = array_map(static fn(array $item): int => $item['id'], $localities);
            $departmentIds = array_values(array_diff(
                array_intersect(
                    array_values(array_unique(array_filter(array_map('intval', $effectiveDepartments), static fn(int $id): bool => $id > 0))),
                    array_values(array_unique(array_filter(array_map('intval', $supportedDepartments), static fn(int $id): bool => $id > 0)))
                ),
                $stationLocalityIds
            ));
            foreach ($db->table('op_rh_localidades')->whereIn('id', $departmentIds)->orderBy('numlista')->get(['id', 'localidad']) as $locality) {
                $localities[] = ['id' => (int) $locality->id, 'name' => (string) $locality->localidad];
            }
        }
    }

    $incidencePermissions = $modulePermission('checador-v2-incidencias');
    $specificPermission = static function (string $key) use ($db, $profile): bool {
        if (!$db->getSchemaBuilder()->hasTable('op_rh_v2_permission_grants')) return false;
        $userGrant = $db->table('op_rh_v2_permission_grants')
            ->where('permission_key', $key)->where('principal_type', 'USER')
            ->where('principal_id', $profile['user_id'])->first(['active', 'granted']);
        $grant = $userGrant ?? $db->table('op_rh_v2_permission_grants')
            ->where('permission_key', $key)->where('principal_type', 'POSITION')
            ->where('principal_id', $profile['position_id'])->first(['active', 'granted']);
        return $grant !== null && (int) $grant->active === 1 && (int) $grant->granted === 1;
    };
    $scheduleRead = $specificPermission('checador.programacion.leer');
    $scheduleEdit = $specificPermission('checador.programacion.editar');
    $templateAdmin = $specificPermission('checador.plantillas.administrar');
    $attendancePolicyRead = $specificPermission('checador.configuracion.leer');
    $attendancePolicyEdit = $specificPermission('checador.configuracion.retardo_incidencias.editar');
    $readerProfileRead = $specificPermission('checador.configuracion.lectores.leer');
    $readerProfileEdit = $specificPermission('checador.configuracion.lectores.editar');
    $positionCatalogRead = $specificPermission('checador.configuracion.puestos.leer');
    $positionCatalogEdit = $specificPermission('checador.configuracion.puestos.editar');
    $deviceInventoryRead = $specificPermission('checador-v2-dispositivos.leer');
    $nameParts = preg_split('/\s+/u', trim((string) $user->nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $displayNamePrefix = implode(' ', array_slice($nameParts, 0, 2));
    $hasExpectedLocality = match ($profileKey) {
        'encargado-palo-solo' => count(array_filter($localities, static fn(array $item): bool => $item['id'] === 2 && $item['name'] === 'Palo Solo')) === 1,
        default => count(array_filter($localities, static fn(array $item): bool => $item['id'] === 1 && $item['name'] === 'Interlomas')) === 1,
    };
    $authorizedRead = $parentPermissions['leer'] === 1 && $submenuGranted && $hasExpectedLocality;

    echo json_encode([
        'ok' => true,
        'user_id' => (int) $user->id,
        'position_id' => (int) $user->id_puesto,
        'display_name_prefix' => $displayNamePrefix,
        'checador_read' => $authorizedRead,
        'localities' => $localities,
        'incidence_read' => $incidencePermissions['leer'] === 1,
        'incidence_create' => $incidencePermissions['crear'] === 1,
        'schedule_read' => $scheduleRead,
        'schedule_edit' => $scheduleEdit,
        'template_admin' => $templateAdmin,
        'station_template_admin' => $specificPermission('checador.turnos.estacion.administrar'),
        'schedule_write_gate' => ChecadorDailyReadService::scheduleApiPostOperationsEnabled(),
        'attendance_policy_read' => $attendancePolicyRead,
        'attendance_policy_edit' => $attendancePolicyEdit,
        'attendance_policy_write_gate' => ChecadorDailyReadService::attendancePolicyPostOperationsEnabled(),
        'reader_profile_read' => $readerProfileRead,
        'reader_profile_edit' => $readerProfileEdit,
        'position_catalog_read' => $positionCatalogRead,
        'position_catalog_edit' => $positionCatalogEdit,
        'device_inventory_read' => $deviceInventoryRead,
        'm11_local_write_gate' => ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable) {
    $fail('local_profile_lookup_failed');
}

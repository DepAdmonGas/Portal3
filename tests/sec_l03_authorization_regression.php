<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\JWTService;
use App\Services\ModuloDptoOperativoService;
use Illuminate\Database\Capsule\Manager as Capsule;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) {
        echo "PASS: {$message}\n";
        $passes++;
    } else {
        echo "FAIL: {$message}\n";
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
$schema->create('tb_puestos', static function ($table): void {
    $table->integer('id')->primary();
    $table->string('tipo_puesto')->nullable();
    $table->integer('estatus')->default(0);
});
$schema->create('modulos_dpto_operativo', static function ($table): void {
    $table->increments('id');
    $table->string('clave');
    $table->string('nombre')->nullable();
    $table->string('ruta')->nullable();
    $table->string('icono')->nullable();
    $table->integer('activo')->default(1);
});
$createGrantColumns = static function ($table): void {
    $table->increments('id');
    $table->integer('id_modulo');
    $table->integer('leer')->default(0);
    $table->integer('crear')->default(0);
    $table->integer('editar')->default(0);
    $table->integer('eliminar')->default(0);
    $table->integer('descargar')->default(0);
};
$schema->create('modulos_usuarios_do', static function ($table) use ($createGrantColumns): void {
    $createGrantColumns($table);
    $table->integer('id_usuario');
});
$schema->create('modulos_puestos_do', static function ($table) use ($createGrantColumns): void {
    $createGrantColumns($table);
    $table->integer('id_puesto');
});

$db = $capsule->getConnection();
$db->table('tb_usuarios')->insert([
    ['id' => 41, 'id_gas' => 9, 'id_puesto' => 7, 'estatus' => 0],
    ['id' => 42, 'id_gas' => 9, 'id_puesto' => 7, 'estatus' => 1],
]);
$db->table('tb_puestos')->insert(['id' => 7, 'tipo_puesto' => 'test', 'estatus' => 0]);
$db->table('modulos_dpto_operativo')->insert([
    ['id' => 10, 'clave' => 'acceso-temporal', 'activo' => 1],
    ['id' => 11, 'clave' => 'otro-modulo', 'activo' => 1],
    ['id' => 12, 'clave' => 'acceso-temporal-inactivo', 'activo' => 0],
]);

$secret = 'sec-l03-test-secret-not-for-production';
$_ENV['JWT_SECRET'] = $secret;
putenv('JWT_SECRET=' . $secret);
$payloadUser = ['id' => 41, 'nombre' => 'Usuario de prueba'];
$access = JWTService::validate(JWTService::createAccessToken($payloadUser));
$refresh = JWTService::validate(JWTService::createRefreshToken($payloadUser));
$expectedClaims = ['iat', 'sub', 'nombre', 'jti', 'exp', 'type', 'iss'];
$assert(($access->iss ?? null) === 'portal3_auth', 'access token usa issuer canónico portal3_auth');
$assert(($refresh->iss ?? null) === 'portal3_auth', 'refresh token usa issuer canónico portal3_auth');
$assert((int) ($access->sub ?? 0) === 41 && (int) ($refresh->sub ?? 0) === 41, 'sub conserva tb_usuarios.id');
$assert(array_diff($expectedClaims, array_keys((array) $access)) === [], 'access token conserva los claims existentes');
$assert(array_diff($expectedClaims, array_keys((array) $refresh)) === [], 'refresh token conserva los claims existentes');
$forbiddenClaims = ['role', 'roles', 'permissions', 'station_ids'];
$assert(array_intersect($forbiddenClaims, array_keys((array) $access)) === [], 'JWT no incluye claims de permisos ni estaciones');

$db->table('modulos_puestos_do')->insert([
    'id_modulo' => 10, 'id_puesto' => 7, 'leer' => 0, 'editar' => 1,
]);
$db->table('modulos_usuarios_do')->insert([
    'id_modulo' => 10, 'id_usuario' => 41, 'leer' => 1, 'editar' => 0,
]);
$permissions = ModuloDptoOperativoService::getTemporaryAccessPermissions(41);
$assert($permissions['leer'] === 1, 'módulo activo y grant directo leer=1 permite lectura');
$assert($permissions['editar'] === 0, 'grant directo parcial no hereda editar del puesto');

$db->table('modulos_usuarios_do')->where('id_modulo', 10)->update(['leer' => 0, 'editar' => 1]);
$permissions = ModuloDptoOperativoService::getTemporaryAccessPermissions(41);
$assert($permissions['editar'] === 1, 'módulo activo y grant directo editar=1 permite mutación');
$db->table('modulos_usuarios_do')->where('id_modulo', 10)->delete();
$db->table('modulos_usuarios_do')->insert([
    'id_modulo' => 11, 'id_usuario' => 41, 'leer' => 0, 'editar' => 0,
]);
$permissions = ModuloDptoOperativoService::getTemporaryAccessPermissions(41);
$assert($permissions['leer'] === 0 && $permissions['editar'] === 0, 'grant directo global activa precedencia de usuario y no usa el grant del puesto');

$db->table('modulos_usuarios_do')->delete();
$permissions = ModuloDptoOperativoService::getTemporaryAccessPermissions(41);
$assert($permissions['leer'] === 0 && $permissions['editar'] === 1, 'sin grants directos se usa el fallback del puesto');
$assert(ModuloDptoOperativoService::getTemporaryAccessPermissions(41, 'no-existe')['leer'] === 0, 'módulo inexistente denegado');
$assert(ModuloDptoOperativoService::getTemporaryAccessPermissions(41, 'acceso-temporal-inactivo')['editar'] === 0, 'módulo inactivo denegado');
$inactive = ModuloDptoOperativoService::getTemporaryAccessPermissions(42);
$assert($inactive['leer'] === 0 && $inactive['editar'] === 0, 'usuario con estatus distinto de cero denegado');

$schema->drop('modulos_usuarios_do');
$afterLookupFailure = ModuloDptoOperativoService::getTemporaryAccessPermissions(41);
$assert($afterLookupFailure['leer'] === 0 && $afterLookupFailure['editar'] === 0, 'fallo de lookup ACL falla cerrado');

printf("SEC_L03_PORTAL3_RESULT: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

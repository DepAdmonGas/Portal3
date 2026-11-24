<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDeviceLinkSqlService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$pass = 0;
$fail = 0;
$assert = static function (bool $condition, string $message) use (&$pass, &$fail): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $pass++ : $fail++;
};

$capsule = new Capsule();
$mariaDbTest = getenv('CHECADOR_LINK_TEST_MARIADB') === '1';
if ($mariaDbTest) {
    if (!in_array((string)getenv('DB_HOST'), ['127.0.0.1', 'localhost'], true) || getenv('DB_NAME') !== 'bd_admongas_v2_biometric_roster_test_20261124') {
        fwrite(STDERR, "Refusing: exact disposable localhost database required.\n"); exit(2);
    }
    $capsule->addConnection(['driver'=>'mysql','host'=>getenv('DB_HOST'),'port'=>getenv('DB_PORT') ?: 3306,'database'=>getenv('DB_NAME'),'username'=>getenv('DB_USER'),'password'=>getenv('DB_PASS'),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>''], 'default');
} else {
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
}
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::connection('default')->getSchemaBuilder();
if ($mariaDbTest) {
    $dbIdentity = Capsule::connection('default')->selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS principal, @@hostname AS host, VERSION() AS version');
    if (($dbIdentity->db ?? '') !== 'bd_admongas_v2_biometric_roster_test_20261124') { fwrite(STDERR, "DB identity mismatch.\n"); exit(2); }
    echo 'DATABASE_IDENTITY ' . json_encode($dbIdentity) . "\n";
    $testTables = ['tb_estaciones','op_rh_v2_dispositivos','op_rh_v2_dispositivo_credenciales','op_rh_v2_device_link_tokens','op_rh_v2_device_biometric_grants','op_rh_v2_m11_configuration_audit'];
    foreach ($testTables as $table) if ($schema->hasTable($table)) { fwrite(STDERR, "Refusing: fixture table already exists.\n"); exit(2); }
    register_shutdown_function(static function () use ($schema, $testTables): void { foreach (array_reverse($testTables) as $table) $schema->dropIfExists($table); });
}
$schema->create('tb_estaciones', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('nombre');
    $table->integer('estatus');
});
$schema->create('op_rh_v2_dispositivos', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('uuid_dispositivo');
    $table->integer('id_estacion');
    $table->string('nombre');
    $table->string('timezone');
    $table->integer('estado');
});
$schema->create('op_rh_v2_dispositivo_credenciales', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_dispositivo');
    $table->string('credential_key')->unique();
    $table->string('secret_hash');
    $table->string('estado');
    $table->dateTime('valido_desde');
    $table->dateTime('valido_hasta')->nullable();
    $table->dateTime('creado_at');
    $table->dateTime('revocado_at')->nullable();
});
$schema->create('op_rh_v2_device_link_tokens', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('token_sha256', 64)->unique();
    $table->integer('id_dispositivo');
    $table->integer('id_estacion');
    $table->integer('emitido_por_usuario_id');
    $table->dateTime('emitido_at_utc', 6);
    $table->dateTime('expira_at_utc', 6);
    $table->dateTime('consumido_at_utc', 6)->nullable();
    $table->dateTime('revocado_at_utc', 6)->nullable();
});
$schema->create('op_rh_v2_device_biometric_grants', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_dispositivo');
    $table->integer('id_estacion');
    $table->integer('id_credencial');
    $table->integer('autorizado_por_usuario_id');
    $table->dateTime('valido_desde_utc', 6);
    $table->dateTime('valido_hasta_utc', 6)->nullable();
    $table->integer('activo')->default(1);
    $table->dateTime('revocado_at_utc', 6)->nullable();
});
$schema->create('op_rh_v2_m11_configuration_audit', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('actor_portal_user_id');
    $table->string('permission_key');
    $table->integer('workplace_id')->nullable();
    $table->string('entity_type');
    $table->integer('entity_id')->nullable();
    $table->string('action');
    $table->dateTime('created_at_utc', 6);
});

Capsule::connection('default')->table('tb_estaciones')->insert(['id' => 1, 'nombre' => 'Interlomas', 'estatus' => 0]);
Capsule::connection('default')->table('op_rh_v2_dispositivos')->insert([
    'id' => 2, 'uuid_dispositivo' => 'DEVICE-2', 'id_estacion' => 1,
    'nombre' => 'Lector Interlomas', 'timezone' => 'America/Mexico_City', 'estado' => 1,
]);
$actor = new Usuario();
$actor->id = 77;
$allowed = true;
$now = '2026-11-24 18:00:00.123456';
$service = new ChecadorDeviceLinkSqlService(
    static fn(Usuario $user, int $workplace, bool $manage): bool => $allowed && $user->id === 77 && in_array($workplace, [1, 2], true) && (!$manage || $user->id === 77),
    static fn(int $workplace): ?int => $workplace === 1 ? 1 : null,
    static fn(): string => $now
);

try {
    $first = $service->issueToken($actor, 1, 2);
    $token = (string) ($first['token'] ?? '');
    $tokenRow = Capsule::connection('default')->table('op_rh_v2_device_link_tokens')->first();
    $grant = Capsule::connection('default')->table('op_rh_v2_device_biometric_grants')->first();
    $audit = Capsule::connection('default')->table('op_rh_v2_m11_configuration_audit')->get();
    $assert(($first['ok'] ?? false) && preg_match('/\A[0-9a-f]{64}\z/', $token) === 1
        && hash('sha256', $token) === ($tokenRow->token_sha256 ?? '')
        && !hash_equals($token, (string) ($tokenRow->token_sha256 ?? ''))
        && ($tokenRow->emitido_at_utc ?? '') === $now
        && ($tokenRow->expira_at_utc ?? '') === '2026-11-24 18:15:00.123456',
        'issue stores only the SHA-256 digest and exact 15-minute UTC expiry');
    $assert($grant === null && count($audit) === 1
        && !str_contains(json_encode($audit), $token)
        && !str_contains(json_encode($audit), (string) $tokenRow->token_sha256),
        'first issue creates no biometric grant and audit omits token material');

    $read = $service->readForWorkplace($actor, 1);
    $readItem = $read['items'][0] ?? [];
    $assert(($read['ok'] ?? false) && ($readItem['device']['id'] ?? 0) === 2
        && ($readItem['link_status'] ?? '') === 'TOKEN_PENDING'
        && ($readItem['pending_token_expires_at_utc'] ?? '') === '2026-11-24 18:15:00.123456'
        && !array_key_exists('token', $readItem) && !array_key_exists('token_sha256', $readItem),
        'SQL readback exposes link state and expiry but cannot recover plaintext or hash');

    $second = $service->issueToken($actor, 1, 2);
    $tokens = Capsule::connection('default')->table('op_rh_v2_device_link_tokens')->orderBy('id')->get();
    $assert(($second['ok'] ?? false) && count($tokens) === 2
        && $tokens[0]->revocado_at_utc === $now && $tokens[0]->consumido_at_utc === null
        && $tokens[1]->revocado_at_utc === null
        && Capsule::connection('default')->table('op_rh_v2_device_biometric_grants')->count() === 0,
        'renewal invalidates the earlier pending token without issuing a grant');

    $revoked = $service->revokeDevice($actor, 1, 2);
    $revocationReadback = $service->readForWorkplace($actor, 1);
    $assert(($revoked['ok'] ?? false)
        && Capsule::connection('default')->table('op_rh_v2_device_biometric_grants')->where('activo', 1)->count() === 0
        && Capsule::connection('default')->table('op_rh_v2_device_link_tokens')->whereNull('revocado_at_utc')->whereNull('consumido_at_utc')->count() === 0
        && ($revocationReadback['items'][0]['biometric_grant_status'] ?? '') === 'REVOKED_OR_MISSING',
        'revocation disables the grant and invalidates pending tokens with SQL readback');

    $deniedService = new ChecadorDeviceLinkSqlService(static fn(): bool => false,
        static fn(int $workplace): ?int => 1, static fn(): string => $now);
    $denied = $deniedService->issueToken($actor, 1, 2);
    $wrongStation = $service->issueToken($actor, 2, 2);
    $assert(($denied['error'] ?? '') === 'permission_denied'
        && ($wrongStation['error'] ?? '') === 'station_mapping_unresolved'
        && Capsule::connection('default')->table('op_rh_v2_device_link_tokens')->count() === 2,
        'unauthorized scope and unresolved locality mapping are rejected without token writes');

    $directionService = new ChecadorDeviceLinkSqlService(
        static fn(Usuario $user, int $workplace, bool $manage): bool => $user->id === 77 && $workplace === 1 && !$manage,
        static fn(int $workplace): ?int => $workplace === 1 ? 1 : null,
        static fn(): string => $now
    );
    $directionRead = $directionService->readForWorkplace($actor, 1);
    $directionWrite = $directionService->issueToken($actor, 1, 2);
    $assert(($directionRead['ok'] ?? false) && ($directionRead['can_manage'] ?? true) === false
        && ($directionWrite['error'] ?? '') === 'permission_denied'
        && Capsule::connection('default')->table('op_rh_v2_device_link_tokens')->count() === 2,
        'read-only Dirección can inspect the station but cannot issue a token');

    Capsule::connection('default')->table('op_rh_v2_dispositivo_credenciales')->insert([
        'id_dispositivo' => 2, 'credential_key' => 'old-key', 'secret_hash' => 'not-real', 'estado' => 'ACTIVE',
        'valido_desde' => $now, 'valido_hasta' => null, 'creado_at' => $now, 'revocado_at' => null,
    ]);
    $renewal = $service->issueToken($actor, 1, 2);
    $assert(($renewal['ok'] ?? false)
        && Capsule::connection('default')->table('op_rh_v2_device_biometric_grants')->where('activo', 1)->count() === 0
        && Capsule::connection('default')->table('op_rh_v2_dispositivo_credenciales')->where('estado', 'ACTIVE')->count() === 1,
        'an active old credential permits renewal without gaining a biometric grant');
    $tokenOnly = $service->revokePendingTokens($actor, 1, 2);
    $assert(($tokenOnly['ok'] ?? false)
        && Capsule::connection('default')->table('op_rh_v2_dispositivo_credenciales')->where('estado', 'ACTIVE')->count() === 1,
        'revoking pending tokens preserves active device authentication');
    $grantId = Capsule::connection('default')->table('op_rh_v2_device_biometric_grants')->insertGetId([
        'id_dispositivo'=>2, 'id_estacion'=>1, 'id_credencial'=>1, 'autorizado_por_usuario_id'=>77,
        'valido_desde_utc'=>$now, 'valido_hasta_utc'=>null, 'activo'=>1, 'revocado_at_utc'=>null,
    ]);
    $biometricOnly = $service->revokeBiometricAccess($actor, 1, 2);
    $assert(($biometricOnly['ok'] ?? false)
        && Capsule::connection('default')->table('op_rh_v2_device_biometric_grants')->where('id',$grantId)->value('activo') == 0
        && Capsule::connection('default')->table('op_rh_v2_dispositivo_credenciales')->where('estado', 'ACTIVE')->count() === 1,
        'revoking biometric access preserves device authentication');
    $full = $service->revokeDevice($actor, 1, 2);
    $assert(($full['ok'] ?? false)
        && Capsule::connection('default')->table('op_rh_v2_dispositivo_credenciales')->where('estado', 'ACTIVE')->count() === 0
        && Capsule::connection('default')->table('op_rh_v2_dispositivo_credenciales')->where('estado', 'REVOKED')->count() === 1,
        'complete unlink revokes device credentials');

    $files = [
        dirname(__DIR__) . '/app/Controllers/ChecadorController.php',
        dirname(__DIR__) . '/routes/web.php',
    ];
    $controller = file_get_contents($files[0]) ?: '';
    $routes = file_get_contents($files[1]) ?: '';
    $serviceSource = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorDeviceLinkSqlService.php') ?: '';
    $configurationReadSource = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorConfigurationReadService.php') ?: '';
    $assert(str_contains($controller, 'ChecadorDeviceLinkSqlService')
        && str_contains($controller, 'Request::isSecure()')
        && str_contains($routes, 'configuracion/vinculacion-lector/generar')
        && str_contains($routes, 'configuracion/vinculacion-lector/revocar')
        && !str_contains($controller, 'api/v2/devices/link')
        && !str_contains($configurationReadSource, 'readAdministrativeDevices($user, $workplaceId)')
        && str_contains($serviceSource, "Capsule::connection('default')")
        && str_contains($serviceSource, 'ChecadorAccessService::authorizeAdministrativeDevices($workplaceId)')
        && str_contains($serviceSource, 'ChecadorV2PermissionService::allows((int)$actor->id, $position, self::TOKEN_PERMISSION)')
        && str_contains($serviceSource, 'ChecadorV2PermissionService::allows((int)$actor->id, $position, self::GRANT_PERMISSION)'),
        'Portal3 uses default SQL, server ACL, Encargado-only writes, and no API link endpoint');
} catch (Throwable $exception) {
    $fail++;
    fwrite(STDERR, 'Test exception: ' . $exception::class . ' ' . $exception->getMessage() . "\n");
}

echo "RESULT: {$pass} PASS / {$fail} FAIL\n";
exit($fail === 0 ? 0 : 1);

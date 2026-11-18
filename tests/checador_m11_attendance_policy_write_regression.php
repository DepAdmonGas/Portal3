<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Models\Operativo\AttendancePolicyVersion;
use App\Services\ChecadorAttendancePolicyStoreService;
use App\Services\ChecadorDailyReadService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$keys = ['APP_ENV', 'CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED', 'CHECADOR_M11_CONFIGURATION_POSTS_ENABLED', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_API_REAL_POSTS_ENABLED'];
$original = [];
foreach ($keys as $key) $original[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
$restore = static function () use ($keys, $original): void {
    foreach ($keys as $key) {
        [$wasSet, $value, $processValue] = $original[$key];
        if ($wasSet) $_ENV[$key] = $value; else unset($_ENV[$key]);
        if ($processValue === false) putenv($key); else putenv($key . '=' . $processValue);
    }
};

$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED'] = 'true';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::schema();
$schema->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('localidad');
    $table->unsignedInteger('numlista')->nullable();
});
$schema->create('op_rh_v2_localidades_zonas_horarias', static function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('id_localidad');
    $table->string('timezone_iana');
    $table->date('valido_desde');
    $table->date('valido_hasta')->nullable();
});
$schema->create('op_rh_v2_attendance_policy_versions', static function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('workplace_id');
    $table->unsignedInteger('policy_version');
    $table->date('effective_from_local_date');
    $table->string('timezone_id');
    $table->unsignedInteger('retardo_tolerance_minutes');
    $table->unsignedInteger('incidence_deadline_days');
    $table->unsignedInteger('actor_portal_user_id');
    $table->string('source');
    $table->string('idempotency_key');
    $table->dateTime('created_at_utc')->nullable();
    $table->unique(['workplace_id', 'policy_version']);
    $table->unique(['workplace_id', 'effective_from_local_date']);
    $table->unique(['workplace_id', 'idempotency_key']);
});
$schema->create('op_rh_v2_m11_configuration_audit', static function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('actor_portal_user_id')->nullable();
    $table->string('permission_key');
    $table->unsignedInteger('workplace_id')->nullable();
    $table->string('entity_type');
    $table->unsignedInteger('entity_id')->nullable();
    $table->string('action');
    $table->dateTime('created_at_utc');
});
Capsule::table('op_rh_localidades')->insert(['id' => 1, 'localidad' => 'Interlomas', 'numlista' => 1]);
Capsule::table('op_rh_v2_localidades_zonas_horarias')->insert([
    'id_localidad' => 1, 'timezone_iana' => 'America/Mexico_City', 'valido_desde' => '2020-01-01', 'valido_hasta' => null,
]);

$actor = new Usuario();
$actor->id = 234;
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-11-18 12:00:00', new DateTimeZone('UTC'));
$service = new ChecadorAttendancePolicyStoreService($clock, static fn(Usuario $identity, int $workplace): bool => $identity->id === 234 && $workplace === 1);
$input = ['workplace_id' => 1, 'effective_from_local_date' => '2026-11-19', 'retardo_tolerance_minutes' => '30', 'incidence_deadline_days' => '7'];
$key = 'isolated-m11-write-001';

try {
    $created = $service->create($actor, 1, '2026-11-19', '30', '7', $key);
    $readback = $service->readForEffectiveDate(1, '2026-11-19');
    $assert(($created['ok'] ?? false) && ($created['created'] ?? false)
        && ($created['policy']['policy_version'] ?? 0) === 1
        && ($readback['data']['policy']['retardo_tolerance_minutes'] ?? -1) === 30
        && ($readback['data']['policy']['incidence_deadline_days'] ?? -1) === 7,
        'isolated SQLite write is confirmed by a subsequent SQL readback');
    $assert(Capsule::table('op_rh_v2_m11_configuration_audit')->count() === 1
        && Capsule::table('op_rh_v2_m11_configuration_audit')->value('entity_type') === 'attendance_policy'
        && Capsule::table('op_rh_v2_m11_configuration_audit')->value('action') === 'CREATE_VERSION',
        'policy creation and its audit event persist atomically in the isolated schema');

    $updateBlocked = false;
    try {
        $historical = AttendancePolicyVersion::query()->firstOrFail();
        $historical->retardo_tolerance_minutes = 31;
        $historical->save();
    } catch (\LogicException) {
        $updateBlocked = true;
    }
    $deleteBlocked = false;
    try {
        AttendancePolicyVersion::query()->firstOrFail()->delete();
    } catch (\LogicException) {
        $deleteBlocked = true;
    }
    $assert($updateBlocked && $deleteBlocked && Capsule::table('op_rh_v2_attendance_policy_versions')->count() === 1
        && (int) Capsule::table('op_rh_v2_attendance_policy_versions')->value('retardo_tolerance_minutes') === 30,
        'persisted policy versions cannot be updated or deleted through the model');

    $replay = $service->create($actor, 1, '2026-11-19', '30', '7', $key);
    $assert(($replay['ok'] ?? false) && !($replay['created'] ?? true)
        && Capsule::table('op_rh_v2_attendance_policy_versions')->count() === 1
        && Capsule::table('op_rh_v2_m11_configuration_audit')->count() === 1,
        'same idempotency key replays without duplicating version or audit');
    $sameDate = $service->create($actor, 1, '2026-11-19', '30', '7', 'isolated-m11-write-006');
    $assert(($sameDate['ok'] ?? false) && !($sameDate['created'] ?? true)
        && Capsule::table('op_rh_v2_attendance_policy_versions')->count() === 1,
        'matching existing effective date is returned without creating a duplicate version');
    $keyConflict = $service->create($actor, 1, '2026-11-19', '31', '7', $key);
    $dateConflict = $service->create($actor, 1, '2026-11-19', '31', '7', 'isolated-m11-write-002');
    $assert(($keyConflict['error'] ?? '') === 'idempotency_conflict'
        && ($dateConflict['error'] ?? '') === 'policy_date_conflict',
        'idempotency and same-date payload conflicts are rejected');

    $past = $service->create($actor, 1, '2026-11-18', '20', '5', 'isolated-m11-write-003');
    $badRange = $service->create($actor, 1, '2026-11-20', '121', '5', 'isolated-m11-write-004');
    $deniedService = new ChecadorAttendancePolicyStoreService($clock, static fn(): bool => false);
    $denied = $deniedService->create($actor, 1, '2026-11-20', '20', '5', 'isolated-m11-write-005');
    $assert(($past['error'] ?? '') === 'policy_must_be_prospective'
        && ($badRange['error'] ?? '') === 'invalid_policy_input'
        && ($denied['error'] ?? '') === 'permission_denied'
        && Capsule::table('op_rh_v2_attendance_policy_versions')->count() === 1,
        'past dates, out-of-range values, and denied scope fail closed without writes');

    $current = $service->readCurrent(1);
    $assert(($current['ok'] ?? false) && ($current['data']['status'] ?? '') === 'LEGACY_UNVERSIONED'
        && ($current['data']['next_scheduled_policy']['effective_from_local_date'] ?? '') === '2026-11-19'
        && ($current['data']['timezone_id'] ?? '') === 'America/Mexico_City',
        'current read resolves workplace timezone and returns the next persisted version');

    $_ENV['APP_ENV'] = 'prod';
    $_ENV['CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED'] = 'true';
    $_ENV['CHECADOR_M11_CONFIGURATION_POSTS_ENABLED'] = 'true';
    $_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
    $assert(ChecadorDailyReadService::attendancePolicyPostOperationsEnabled()
        && ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled()
        && !ChecadorDailyReadService::realApiPostOperationsEnabled(),
        'production dedicated M11 gates remain independent from the global API POST gate');
    $_ENV['CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED'] = 'false';
    $_ENV['CHECADOR_M11_CONFIGURATION_POSTS_ENABLED'] = 'false';
    $assert(!ChecadorDailyReadService::attendancePolicyPostOperationsEnabled()
        && !ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled(),
        'production write gates fail closed when dedicated flags are absent');
} finally {
    $restore();
}

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$controllerStart = strpos($controller, 'public function saveAttendancePolicy(): void');
$controllerEnd = strpos($controller, 'private function renderScheduleHistory(', $controllerStart ?: 0);
$method = $controllerStart === false ? '' : substr($controller, $controllerStart, $controllerEnd === false ? null : $controllerEnd - $controllerStart);
$configurationRead = file_get_contents(__DIR__ . '/../app/Services/ChecadorConfigurationReadService.php') ?: '';
$policyService = file_get_contents(__DIR__ . '/../app/Services/ChecadorAttendancePolicyStoreService.php') ?: '';
$assert(str_contains($method, 'ChecadorAttendancePolicyStoreService')
    && !str_contains($method, 'readAttendancePolicy(') && !str_contains($method, 'createAttendancePolicy(')
    && !str_contains($method, '/api/v2/attendance-policies'),
    'M11 controller writes through the direct SQL store and has no attendance-policy HTTP call');
$assert(str_contains($method, "authorizeAttendancePolicy(\$workplaceId, 'editar')")
    && str_contains($method, "authorizeAttendancePolicy(\$workplaceId, 'leer')")
    && str_contains($method, 'HTTP_X_CSRF_TOKEN') && str_contains($method, 'idempotency_key'),
    'controller enforces server read/edit grants, CSRF, and request idempotency');
$assert(str_contains($configurationRead, "(new ChecadorAttendancePolicyStoreService())->readCurrent(\$workplaceId)")
    && !str_contains($configurationRead, 'readCurrentAttendancePolicy($user'),
    'configuration policy read is sourced from the direct SQL store');
$assert(str_contains($policyService, "lockForUpdate()")
    && str_contains($policyService, 'Capsule::connection()->transaction')
    && str_contains($policyService, 'idempotency_key')
    && str_contains($policyService, 'policy_readback_mismatch'),
    'write service uses transactional locking, idempotency, and persisted-value readback');

printf("CHECADOR_M11_ATTENDANCE_POLICY_SQL_WRITE_RESULT: %d PASS / %d FAIL / isolated SQLite only\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

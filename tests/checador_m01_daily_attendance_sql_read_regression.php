<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorDailyAttendanceSqlReadService;
use Illuminate\Database\Capsule\Manager as Capsule;

$_ENV['APP_ENV'] = 'test';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$connection = Capsule::connection('default');
$schema = Capsule::schema();
$schema->create('op_rh_localidades', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('localidad');
});
$schema->create('tb_estaciones', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->integer('estatus');
});
$schema->create('op_rh_v2_localidades_zonas_horarias', static function ($table): void {
    $table->integer('id_localidad');
    $table->string('timezone_iana');
    $table->date('valido_desde');
    $table->date('valido_hasta')->nullable();
});
$schema->create('op_rh_v2_marcaciones_crudas', static function ($table): void {
    $table->integer('id')->primary();
    $table->string('event_uuid')->nullable();
    $table->integer('id_dispositivo')->nullable();
    $table->integer('id_estacion_resuelta')->nullable();
    $table->integer('id_personal_resuelto')->nullable();
    $table->string('source_timestamp_raw')->nullable();
    $table->string('source_timezone_reported')->nullable();
    $table->string('effective_timezone')->nullable();
    $table->integer('timezone_fallback_applied')->nullable();
    $table->dateTime('ocurrido_at_utc')->nullable();
    $table->dateTime('recibido_at_utc')->nullable();
    $table->string('metodo_captura')->nullable();
    $table->string('external_event_id')->nullable();
    $table->integer('secuencia_dispositivo')->nullable();
    $table->string('processing_status')->nullable();
    $table->string('integrity_version')->nullable();
    $table->string('integrity_key_id')->nullable();
    $table->string('integrity_seal')->nullable();
});
$schema->create('op_rh_personal', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('id_estacion')->nullable();
    $table->string('nombre_completo')->nullable();
    $table->string('no_colaborador')->nullable();
    $table->integer('estado')->nullable();
});
$schema->create('op_rh_personal_localidad_version', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('worker_id');
    $table->integer('workplace_id');
    $table->dateTime('valid_from_utc');
    $table->dateTime('valid_to_utc')->nullable();
    $table->dateTime('recorded_at_utc');
});

Capsule::table('op_rh_localidades')->insert([
    ['id' => 1, 'numlista' => 2, 'localidad' => 'Interlomas'],
    ['id' => 2, 'numlista' => 3, 'localidad' => 'Palo Solo'],
]);
Capsule::table('tb_estaciones')->insert([
    ['id' => 10, 'numlista' => 2, 'estatus' => 0],
    ['id' => 20, 'numlista' => 3, 'estatus' => 0],
]);
Capsule::table('op_rh_v2_localidades_zonas_horarias')->insert([
    ['id_localidad' => 1, 'timezone_iana' => 'America/Mexico_City', 'valido_desde' => '2020-01-01', 'valido_hasta' => null],
    ['id_localidad' => 2, 'timezone_iana' => 'America/Mexico_City', 'valido_desde' => '2020-01-01', 'valido_hasta' => null],
]);
$raw = static fn(int $id, int $locality, ?int $worker, string $at, bool $seal): array => [
    'id' => $id,
    'event_uuid' => 'm01-fixture-' . $id,
    'id_dispositivo' => 20,
    'id_estacion_resuelta' => $locality,
    'id_personal_resuelto' => $worker,
    'source_timestamp_raw' => $at,
    'source_timezone_reported' => 'America/Mexico_City',
    'effective_timezone' => 'America/Mexico_City',
    'timezone_fallback_applied' => 0,
    'ocurrido_at_utc' => $at,
    'recibido_at_utc' => $at,
    'metodo_captura' => 'BIOMETRIC',
    'external_event_id' => 'fixture-' . $id,
    'secuencia_dispositivo' => $id,
    'processing_status' => 'RECEIVED',
    'integrity_version' => 'V1',
    'integrity_key_id' => 'fixture-key',
    'integrity_seal' => $seal ? 'fixture-seal' : null,
];
Capsule::table('op_rh_v2_marcaciones_crudas')->insert([
    $raw(901, 10, 900001, '2026-11-23 06:00:00', true),
    $raw(902, 20, 900002, '2026-11-23 12:00:00', false),
]);
Capsule::table('op_rh_personal')->insert([
    'id' => 900001,
    'id_estacion' => 10,
    'nombre_completo' => 'Fixture aislado M01',
    'no_colaborador' => 'FIX-M01',
    'estado' => 1,
]);

$selectProbeMethod = new ReflectionMethod(ChecadorDailyAttendanceSqlReadService::class, 'verifySelectPermissions');
$selectProbeMethod->setAccessible(true);
$selectProbeFails = false;
$connection->beforeExecuting(static function (string $sql) use (&$selectProbeFails): void {
    if ($selectProbeFails && stripos($sql, 'SELECT 1') !== false && stripos($sql, 'op_rh_v2_marcaciones_crudas') !== false) {
        $selectProbeFails = false;
        throw new PDOException('isolated SELECT privilege failure');
    }
});
$selectProbeMethod->invoke(null, $connection);
$selectProbeFails = true;
$selectFailureWasRejected = false;
try {
    $selectProbeMethod->invoke(null, $connection);
} catch (Throwable) {
    $selectFailureWasRejected = true;
}
$assert($selectFailureWasRejected, 'missing effective SELECT permission rejects the M01 preflight');

$authorizedReader = new ChecadorDailyAttendanceSqlReadService($connection, static fn(int $id): bool => $id === 1);
$profileMethod = new ReflectionMethod(ChecadorDailyAttendanceSqlReadService::class, 'approvedRuntimeProfile');
$profileMethod->setAccessible(true);
$fedoraIdentity = (object) ['schema_name' => 'bd_admongas', 'server_hostname' => 'fedora', 'server_port' => 3306, 'principal' => 'dev@localhost'];
$preprodIdentity = (object) ['schema_name' => 'bd_admongas_v2_preprod', 'server_hostname' => 'preprod-db', 'server_port' => 3306, 'principal' => 'portal3@localhost'];
$fedoraProfile = $profileMethod->invoke(null, 'dev', [
    'driver' => 'mysql', 'database' => 'bd_admongas', 'host' => '127.0.0.1', 'port' => 3306,
], $fedoraIdentity);
$assert($fedoraProfile === 'fedora', 'approved Fedora profile retains local schema, host and server identity checks');
$preprodProfile = $profileMethod->invoke(null, 'production', [
    'driver' => 'mysql', 'database' => 'bd_admongas_v2_preprod', 'host' => 'db.example.invalid', 'port' => 3306,
], $preprodIdentity);
$assert($preprodProfile === 'preproduction', 'approved production APP_ENV is accepted only with the exact preproduction database identity');
$assert($profileMethod->invoke(null, 'development', [
    'driver' => 'mysql', 'database' => 'bd_admongas_v2_preprod', 'host' => 'db.example.invalid', 'port' => 3306,
], $preprodIdentity) === null,
    'development APP_ENV cannot use the preproduction database profile');
$assert($profileMethod->invoke(null, 'production', [
    'driver' => 'mysql', 'database' => 'unexpected_database', 'host' => 'db.example.invalid', 'port' => 3306,
], $preprodIdentity) === null,
    'unexpected configured database fails closed');
$assert($profileMethod->invoke(null, 'production', [
    'driver' => 'mysql', 'database' => 'bd_admongas_v2_preprod', 'host' => 'db.example.invalid', 'port' => 3306,
], (object) ['schema_name' => 'other_database', 'server_hostname' => 'preprod-db', 'server_port' => 3306, 'principal' => 'portal3@localhost']) === null,
    'effective DATABASE mismatch fails closed');
$assert($profileMethod->invoke(null, 'production', [
    'driver' => 'mysql', 'database' => 'bd_admongas_v2_preprod', 'host' => 'db.example.invalid', 'port' => 3306,
], (object) ['schema_name' => 'bd_admongas_v2_preprod', 'server_hostname' => 'preprod-db', 'server_port' => 3307, 'principal' => 'portal3@localhost']) === null,
    'effective SQL port mismatch fails closed');
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyAttendanceSqlReadService.php') ?: '';
$assert(str_contains($serviceSource, "'bd_admongas_v2_preprod'")
    && str_contains($serviceSource, 'self::verifySelectPermissions($connection)')
    && str_contains($serviceSource, "selectRaw('1')->limit(0)->get()")
    && str_contains($serviceSource, 'Capsule::connection(\'default\')'),
    'preproduction profile probes effective SELECT on each required table through the existing default connection');
$positive = $authorizedReader->readDay(1, '2026-11-23', [1]);
$assert(($positive['ok'] ?? false) === true && count($positive['events'] ?? []) === 1,
    'positive fixture reads only persisted events for the authorized canonical locality');
$assert(($positive['events'][0]['id_personal_resuelto'] ?? null) === 900001
    && ($positive['events'][0]['integrity_seal_present'] ?? false) === true
    && ($positive['events'][0]['integrity_verification_status'] ?? '') === 'NOT_VERIFIED_BY_PORTAL3',
    'resolved identity and seal presence are shown as stored without Portal3 integrity verification');
$projection = App\Services\ChecadorAttendanceProjection::workerRow(
    ['worker_id' => 900001, 'full_name' => 'Fixture aislado M01', 'employee_number' => 'FIX-M01'],
    $positive['events'],
    null,
    'America/Mexico_City'
);
$assert(count(array_filter($projection['slots'], static fn(array $slot): bool => $slot['event'] !== null)) === 0
    && count($projection['additional_events'] ?? []) === 1,
    'raw event remains separately observed and does not fill an attendance slot without persisted projection');
$assert(($positive['from_utc'] ?? '') === '2026-11-23 06:00:00'
    && ($positive['to_utc'] ?? '') === '2026-11-24 06:00:00'
    && ($positive['timezone'] ?? '') === 'America/Mexico_City'
    && ($positive['physical_station_id'] ?? 0) === 10,
    'canonical locality maps to a distinct physical station and historical IANA timezone produces the UTC window');
$assert(Capsule::table('op_rh_personal_localidad_version')->where('worker_id', 900001)->count() === 0
    && ($positive['events'][0]['id_personal_resuelto'] ?? null) === 900001,
    'event remains readable when the isolated worker fixture has no historical membership row');
$membership = (new App\Services\WorkerWorkplaceAssignmentResolver())->resolveAssignments(
    900001,
    '2026-11-23T06:00:00Z'
);
$assert(($membership['status'] ?? '') === 'RESOLVED_NONE',
    'an existing isolated HR worker without canonical assignment is not treated as a locality member');

$empty = $authorizedReader->readDay(1, '2026-11-24', [1]);
$assert(($empty['ok'] ?? false) === true && ($empty['events'] ?? null) === [],
    'zero matching SQL rows are a successful empty observed-event result');
$assert(($positive['source_completeness'] ?? '') === 'UNKNOWN'
    && ($positive['consistency'] ?? '') === 'LIVE_NON_SNAPSHOT',
    'successful SQL read keeps completeness unknown and does not upgrade coverage');
$unauthorized = $authorizedReader->readDay(2, '2026-11-23', [1]);
$assert(($unauthorized['error']['kind'] ?? '') === 'locality_not_authorized',
    'a locality outside the server-derived authorized allowlist is rejected');
$unauthorizedReader = new ChecadorDailyAttendanceSqlReadService($connection, static fn(int $id): bool => false);
$deniedByAuthorization = $unauthorizedReader->readDay(1, '2026-11-23', [1]);
$assert(($deniedByAuthorization['error']['kind'] ?? '') === 'locality_not_authorized',
    'a server authorization denial rejects an otherwise listed locality');
$isolated = $authorizedReader->readDay(1, '2026-11-23', [1], 900002);
$assert(($isolated['ok'] ?? false) === true && ($isolated['events'] ?? null) === [],
    'worker and locality filters cannot expose an event persisted for another locality');
$secondLocalityReader = new ChecadorDailyAttendanceSqlReadService($connection, static fn(int $id): bool => $id === 2);
$secondLocalityRead = $secondLocalityReader->readDay(2, '2026-11-23', [2]);
$assert(($secondLocalityRead['ok'] ?? false) === true
    && ($secondLocalityRead['physical_station_id'] ?? 0) === 20
    && array_column($secondLocalityRead['events'] ?? [], 'id') === [902],
    'second authorized locality reads its distinct physical station events only');

Capsule::table('tb_estaciones')->insert(['id' => 11, 'numlista' => 2, 'estatus' => 0]);
$ambiguous = $authorizedReader->readDay(1, '2026-11-23', [1]);
$assert(($ambiguous['error']['kind'] ?? '') === 'station_mapping_unavailable',
    'ambiguous physical station mapping fails closed');
Capsule::table('tb_estaciones')->where('id', 11)->delete();

$failSql = true;
$connection->beforeExecuting(static function (string $sql) use (&$failSql): void {
    if ($failSql && stripos($sql, 'FROM "op_rh_v2_marcaciones_crudas"') !== false) {
        $failSql = false;
        throw new PDOException('isolated fixture SQL failure');
    }
});
$sqlError = $authorizedReader->readDay(1, '2026-11-23', [1]);
$assert(($sqlError['error']['kind'] ?? '') === 'database_unavailable',
    'SQL exception is reported as unavailable, never as a successful empty result');

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$service = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyAttendanceSqlReadService.php') ?: '';
$view = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php') ?: '';
$dailyReaderStart = strpos($controller, 'private static function readDailySqlAttendance(');
$dailyReaderEnd = $dailyReaderStart === false ? false : strpos($controller, 'private static function m06AdministrativeIncidenceSqlService(', $dailyReaderStart);
$dailyReaderMethod = $dailyReaderStart !== false && $dailyReaderEnd !== false
    ? substr($controller, $dailyReaderStart, $dailyReaderEnd - $dailyReaderStart)
    : '';
$assert(str_contains($controller, 'self::readDailySqlAttendance(')
    && !str_contains(substr($controller, strpos($controller, 'private static function readDailySqlAttendance(')), 'readObservedPunches(')
    && str_contains($controller, "'persisted_schedule_projection_unavailable'"),
    'operational M01 path uses SQL and leaves schedule/journey projections unavailable instead of inferring them');
$assert(str_contains($controller, "\$empty['observed_only_events'] = \$unrosteredEvents;")
    && str_contains($controller, 'if (isset($rosterById[$workerId]) && $belongsToLocalityAtEvent)')
    && str_contains($controller, 'WorkerWorkplaceAssignmentResolver')
    && str_contains($controller, '($membership[\'status\'] ?? \'\') === \'RESOLVED_SINGLE\'')
    && str_contains($controller, '$unrosteredEvents[] = [')
    && str_contains($view, 'Eventos observados fuera del padrón acreditado')
    && str_contains($view, 'No agregan trabajadores al padrón')
    && !str_contains($dailyReaderMethod, 'ChecadorDailyReadService')
    && !str_contains($dailyReaderMethod, 'ChecadorRawPunchReadService'),
    'unrostered events are presented separately without routing M01 through the shared M02 reader');
$assert(str_contains($service, 'Auth::user() !== null')
    && str_contains($service, 'ChecadorAccessService::authorize($localityId)')
    && str_contains($service, 'in_array($localityId, $authorizedLocalityIds, true)'),
    'operational M01 reads require an authenticated user and server-authorized canonical locality');
$assert(str_contains($view, 'formato y verificación criptográfica no confirmados por Portal3')
    && str_contains($view, 'La verificación criptográfica corresponde a API V2'),
    'view distinguishes stored seal presence from format and cryptographic verification');
$assert(str_contains($view, '$successfulM01SqlRead = $state === \'success\' && ($m01SqlReadSuccessful ?? false) === true;')
    && str_contains($view, 'elseif (!$successfulM01SqlRead && ($attendanceCoverage[\'complete\'] ?? false) !== true)')
    && str_contains($view, 'if (!$successfulM01SqlRead):')
    && str_contains($view, 'Sin marcación observada'),
    'only successful SQL reads suppress repetitive coverage notices while preserving neutral empty status');

echo "RESULT {$passes} PASS {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

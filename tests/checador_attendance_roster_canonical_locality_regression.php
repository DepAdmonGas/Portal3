<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorAccessService;
use App\Services\ChecadorAttendanceRosterService;
use App\Services\WorkerWorkplaceAssignmentResolver;
use Illuminate\Database\Capsule\Manager as Capsule;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = $capsule->schema();
$schema->create('op_rh_localidades', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('localidad');
});
$schema->create('tb_estaciones', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('nombre');
    $table->integer('estatus')->default(0);
});
$schema->create('op_rh_personal', static function ($table): void {
    $table->integer('id')->primary();
    $table->integer('id_estacion')->nullable();
    $table->string('nombre_completo');
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
$today = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
$yesterday = (new DateTimeImmutable($today . ' 00:00:00', new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');
$tomorrow = (new DateTimeImmutable($today . ' 00:00:00', new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
$recordedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

$db->table('op_rh_localidades')->insert([
    ['id' => 901, 'numlista' => 41, 'localidad' => 'Localidad canónica'],
    ['id' => 902, 'numlista' => 42, 'localidad' => 'Sin estación'],
    ['id' => 903, 'numlista' => 43, 'localidad' => 'Mapeo ambiguo'],
    ['id' => 904, 'numlista' => 44, 'localidad' => 'Estación inactiva'],
    ['id' => 905, 'numlista' => null, 'localidad' => 'Localidad sin estación física'],
]);
$db->table('tb_estaciones')->insert([
    ['id' => 501, 'numlista' => 41, 'nombre' => 'Estación física', 'estatus' => 0],
    ['id' => 503, 'numlista' => 43, 'nombre' => 'Mapeo A', 'estatus' => 0],
    ['id' => 504, 'numlista' => 43, 'nombre' => 'Mapeo B', 'estatus' => 0],
    ['id' => 505, 'numlista' => 44, 'nombre' => 'Inactiva', 'estatus' => 1],
]);
$db->table('op_rh_personal')->insert([
    ['id' => 101, 'id_estacion' => 501, 'nombre_completo' => 'Legacy actual', 'no_colaborador' => 101, 'estado' => 1],
    ['id' => 102, 'id_estacion' => 501, 'nombre_completo' => 'Versionada en otra localidad', 'no_colaborador' => 102, 'estado' => 1],
    ['id' => 103, 'id_estacion' => 501, 'nombre_completo' => 'Membresía futura', 'no_colaborador' => 103, 'estado' => 1],
    ['id' => 202, 'id_estacion' => 902, 'nombre_completo' => 'ID coincidente sin puente', 'no_colaborador' => 202, 'estado' => 1],
    ['id' => 203, 'id_estacion' => 503, 'nombre_completo' => 'Estación ambigua', 'no_colaborador' => 203, 'estado' => 1],
    ['id' => 204, 'id_estacion' => 505, 'nombre_completo' => 'Estación inactiva', 'no_colaborador' => 204, 'estado' => 1],
]);
$db->table('op_rh_personal_localidad_version')->insert([
    [
        'worker_id' => 102, 'workplace_id' => 902, 'valid_from_utc' => $yesterday . ' 00:00:00',
        'valid_to_utc' => null, 'recorded_at_utc' => $recordedAt, 'recorded_by' => 1,
        'reason' => 'Prueba aislada', 'supersedes_id' => null,
    ],
    [
        'worker_id' => 103, 'workplace_id' => 901, 'valid_from_utc' => $tomorrow . ' 00:00:00',
        'valid_to_utc' => null, 'recorded_at_utc' => $recordedAt, 'recorded_by' => 1,
        'reason' => 'Prueba aislada', 'supersedes_id' => null,
    ],
]);

$assert(ChecadorAccessService::resolveActiveStationIdForLocality(901) === 501,
    'canonical locality resolves to its physical station when the IDs differ');
$assert(ChecadorAccessService::resolveActiveStationIdForLocality(902) === null,
    'a missing station/locality bridge fails closed');
$assert(ChecadorAccessService::resolveActiveStationIdForLocality(903) === null,
    'an ambiguous numlista fails closed');
$assert(ChecadorAccessService::resolveActiveStationIdForLocality(904) === null,
    'an inactive physical station is not used as a current mapping');
$assert(ChecadorAccessService::resolveActiveStationIdForLocality(905) === null,
    'a locality without a physical station has no legacy station mapping');

$service = new ChecadorAttendanceRosterService();
$todayRoster = $service->workersForAttendanceFilter(901, $today, 'UTC');
$todayIds = array_map(static fn(array $worker): int => (int) $worker['worker_id'], $todayRoster['workers'] ?? []);
$assert(($todayRoster['status'] ?? '') === 'ready' && in_array(101, $todayIds, true),
    'attendance roster follows the numlista bridge for a nonmatching station and locality ID');
$assert(!in_array(102, $todayIds, true),
    'a versioned membership in another locality overrides the current station field');
$assert(!in_array(103, $todayIds, true),
    'a future-dated version does not create a current membership before valid_from');

$missingRoster = $service->workersForAttendanceFilter(902, $today, 'UTC');
$ambiguousRoster = $service->workersForAttendanceFilter(903, $today, 'UTC');
$inactiveRoster = $service->workersForAttendanceFilter(904, $today, 'UTC');
$missingRosterIds = array_map(static fn(array $worker): int => (int) $worker['worker_id'], $missingRoster['workers'] ?? []);
$assert(($missingRoster['status'] ?? '') === 'partial'
    && in_array(102, $missingRosterIds, true)
    && !in_array(202, $missingRosterIds, true),
    'a missing bridge preserves valid versioned membership but rejects accidental numeric equality');
$assert(($ambiguousRoster['status'] ?? '') === 'partial'
    && ($ambiguousRoster['workers'] ?? []) === [],
    'an ambiguous bridge does not expose a legacy roster as complete');
$assert(($inactiveRoster['status'] ?? '') === 'partial'
    && ($inactiveRoster['workers'] ?? []) === [],
    'an inactive station does not expose a legacy roster as complete');

$historicalResolver = new WorkerWorkplaceAssignmentResolver();
$historicalMembership = $historicalResolver->resolveAssignments(103, $yesterday . 'T12:00:00Z');
$historicalRoster = $service->workersForAttendanceFilter(901, $yesterday, 'UTC');
$assert(($historicalMembership['status'] ?? '') === 'RESOLVED_NONE'
    && ($historicalRoster['coverage'] ?? '') === 'current_roster_historical_unverified'
    && !in_array(103, array_map(static fn(array $worker): int => (int) $worker['worker_id'], $historicalRoster['workers'] ?? []), true),
    'a version effective after the historical date is not retroactively assigned and current roster is marked unverified');

echo "CHECADOR_ATTENDANCE_ROSTER_CANONICAL_LOCALITY: {$passes} PASS / {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

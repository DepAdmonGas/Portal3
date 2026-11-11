<?php
declare(strict_types=1);

use App\Services\ChecadorAttendanceRosterService;
use App\Services\RolComodinesService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../vendor/autoload.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

// This fixture lives only in an isolated in-memory SQLite connection. It cannot reach bd_admongas.
$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::schema();
$schema->create('tb_estaciones', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('nombre');
    $table->integer('estatus')->default(0);
});
$schema->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('localidad');
});
$schema->create('op_rh_rol_comodines', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->date('fecha_inicio');
    $table->date('fecha_fin');
    $table->integer('status');
});
$schema->create('op_rh_comodines_dia', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_reporte');
    $table->integer('id_usuario');
    $table->integer('id_estacion');
    $table->string('dia');
});

$db = Capsule::connection();
$db->table('tb_estaciones')->insert([
    ['id' => 201, 'numlista' => 1, 'nombre' => 'Estación Uno', 'estatus' => 1],
    ['id' => 202, 'numlista' => 2, 'nombre' => 'Estación Dos', 'estatus' => 1],
]);
$db->table('op_rh_localidades')->insert([
    ['id' => 301, 'numlista' => 1, 'localidad' => 'Localidad Uno'],
    ['id' => 302, 'numlista' => 2, 'localidad' => 'Localidad Dos'],
]);
$db->table('op_rh_rol_comodines')->insert([
    ['id' => 1, 'id_estacion' => 8, 'fecha_inicio' => '0000-00-00', 'fecha_fin' => '0000-00-00', 'status' => 0],
    ['id' => 2, 'id_estacion' => 0, 'fecha_inicio' => '2026-10-26', 'fecha_fin' => '2026-11-01', 'status' => 1],
    ['id' => 3, 'id_estacion' => 0, 'fecha_inicio' => '2026-10-26', 'fecha_fin' => '2026-11-01', 'status' => 0],
    ['id' => 4, 'id_estacion' => 0, 'fecha_inicio' => '0000-00-00', 'fecha_fin' => '0000-00-00', 'status' => 0],
    ['id' => 5, 'id_estacion' => 0, 'fecha_inicio' => '2026-10-26', 'fecha_fin' => '2026-11-01', 'status' => 1],
    ['id' => 6, 'id_estacion' => 0, 'fecha_inicio' => '2026-10-26', 'fecha_fin' => '2026-11-01', 'status' => 0],
    ['id' => 7, 'id_estacion' => 0, 'fecha_inicio' => '2026-10-26', 'fecha_fin' => '2026-11-01', 'status' => 0],
]);
$db->table('op_rh_comodines_dia')->insert([
    ['id_reporte' => 2, 'id_usuario' => 8801, 'id_estacion' => 201, 'dia' => 'Lunes'],
    ['id_reporte' => 2, 'id_usuario' => 8801, 'id_estacion' => 201, 'dia' => 'Lunes'],
    ['id_reporte' => 2, 'id_usuario' => 8802, 'id_estacion' => 202, 'dia' => 'Martes'],
    ['id_reporte' => 3, 'id_usuario' => 8803, 'id_estacion' => 202, 'dia' => 'Lunes'],
    ['id_reporte' => 4, 'id_usuario' => 8804, 'id_estacion' => 202, 'dia' => 'Lunes'],
    ['id_reporte' => 5, 'id_usuario' => 8805, 'id_estacion' => 201, 'dia' => 'Lunes'],
    ['id_reporte' => 5, 'id_usuario' => 8805, 'id_estacion' => 201, 'dia' => 'Lunes'],
    ['id_reporte' => 6, 'id_usuario' => 8806, 'id_estacion' => 201, 'dia' => 'Lunes'],
    ['id_reporte' => 6, 'id_usuario' => 8806, 'id_estacion' => 999, 'dia' => 'Martes'],
    ['id_reporte' => 7, 'id_usuario' => 8807, 'id_estacion' => 201, 'dia' => 'Lunes'],
    ['id_reporte' => 7, 'id_usuario' => 8807, 'id_estacion' => 0, 'dia' => 'Martes'],
]);

$model = RolComodinesService::getChecadorReadModel(301, [301]);
$assert(($model['ok'] ?? false) === true && count($model['roles'] ?? []) === 1,
    'read model returns only a fully verifiable program header linked to the authorized station');
$assigned = array_values(array_filter($model['roles'] ?? [], static fn(array $row): bool => $row['status_label'] === 'Finalizado'));
$assert(count($assigned) === 1
    && $assigned[0]['station_label'] === 'Estación Uno'
    && $assigned[0]['days_label'] === 'Lunes'
    && $assigned[0]['period_label'] === '26/10/2026 al 01/11/2026',
    'station bridge uses unique numlista and repeated day rows are deduplicated');
$assert(($assigned[0]['coverage_authorized'] ?? true) === false
    && str_contains((string) $assigned[0]['identity_label'], 'sin vínculo canónico')
    && !array_key_exists('id_usuario', $assigned[0])
    && !array_key_exists('worker_id', $assigned[0]),
    'legacy user IDs are not exposed or represented as canonical worker identities');
$assert(count(array_filter($model['roles'] ?? [], static fn(array $row): bool => $row['status_label'] === 'Finalizado')) === 1,
    'a header with assignments at both an authorized and unauthorized station is omitted in full');
$assert(array_column($model['roles'] ?? [], 'status_label') === ['Finalizado'],
    'headers with an unknown or empty child station are omitted even when another child row is authorized');
$pending = array_values(array_filter($model['roles'] ?? [], static fn(array $row): bool => $row['status_label'] === 'Pendiente'));
$assert($pending === []
    && !in_array('Sin estación verificable', array_column($model['roles'] ?? [], 'station_label'), true),
    'the global incomplete pending header is omitted from station scope instead of being treated as coverage');
$assert(($model['roles'][0]['station_label'] ?? '') !== 'Estación Dos',
    'assignments from a different workplace are excluded from the selected locality');

$denied = RolComodinesService::getChecadorReadModel(302, [301]);
$assert(($denied['ok'] ?? true) === false && ($denied['error'] ?? '') === 'requested_station_out_of_scope',
    'service denies a manipulated workplace outside the server-provided allowlist');
$db->table('tb_estaciones')->insert(['id' => 203, 'numlista' => 1, 'nombre' => 'Duplicate mapping', 'estatus' => 1]);
$ambiguous = RolComodinesService::getChecadorReadModel(301, [301]);
$assert(($ambiguous['ok'] ?? true) === false && ($ambiguous['error'] ?? '') === 'station_mapping_unavailable',
    'ambiguous station mapping fails closed');
$db->table('tb_estaciones')->where('id', 203)->delete();
$db->table('tb_estaciones')->where('id', 201)->update(['estatus' => 0]);
$cancelled = RolComodinesService::getChecadorReadModel(301, [301]);
$assert(($cancelled['ok'] ?? true) === false && ($cancelled['error'] ?? '') === 'station_mapping_unavailable',
    'canceled station status 0 is denied by the Comodines read model');
$db->table('tb_estaciones')->where('id', 201)->update(['estatus' => 1]);

$roster = [
    'status' => 'ready',
    'coverage' => 'current_active_roster',
    'workers' => [['worker_id' => 41], ['worker_id' => 42]],
];
$assert(ChecadorAttendanceRosterService::observedWorkerRelationship(41, $roster) === 'habitual',
    'daily view can label a worker as habitual only when current canonical membership resolves');
$assert(ChecadorAttendanceRosterService::observedWorkerRelationship(43, $roster) === 'observed_outside_roster',
    'a resolved API observation outside a complete current roster remains visible without becoming scheduled coverage');
$assert(ChecadorAttendanceRosterService::observedWorkerRelationship(43, $roster, [['worker_id' => 43]]) === 'scheduled_comodine'
    && ChecadorAttendanceRosterService::observedWorkerRelationship(44, $roster, [['worker_id' => 43]]) === 'observed_outside_roster',
    'scheduled coverage is shown only for an identity present in the separately supplied schedule evidence');
$assert(ChecadorAttendanceRosterService::observedWorkerRelationship(43, [
    'status' => 'historical_roster_unavailable',
    'coverage' => 'observed_workers_only',
    'workers' => [],
]) === 'observed'
    && ChecadorAttendanceRosterService::observedWorkerRelationship(0, $roster) === 'unresolved',
    'historical or unresolved identities are not mislabeled as external or habitual');

printf("CHECADOR_V2_COMODINES_READONLY: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

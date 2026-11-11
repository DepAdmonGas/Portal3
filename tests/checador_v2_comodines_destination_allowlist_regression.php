<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\RolComodinesService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

// All test writes are confined to an in-memory SQLite fixture.
$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::schema();
$schema->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista');
    $table->string('localidad');
});
$schema->create('tb_estaciones', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista');
    $table->string('nombre');
    $table->integer('estatus')->default(0);
});

$approved = [
    1 => ['numlista' => 2, 'station_id' => 1, 'name' => 'Interlomas'],
    2 => ['numlista' => 1, 'station_id' => 2, 'name' => 'Palo Solo'],
    3 => ['numlista' => 3, 'station_id' => 3, 'name' => 'San Agustin'],
    4 => ['numlista' => 4, 'station_id' => 4, 'name' => 'Gasomira'],
    5 => ['numlista' => 5, 'station_id' => 5, 'name' => 'Valle de Guadalupe'],
    6 => ['numlista' => 6, 'station_id' => 6, 'name' => 'Esmegas'],
    7 => ['numlista' => 7, 'station_id' => 7, 'name' => 'Xochimilco'],
    14 => ['numlista' => 8, 'station_id' => 14, 'name' => 'Bosque Real'],
];
$excluded = [
    8 => ['numlista' => 9, 'station_id' => 8, 'name' => 'Comodines'],
    9 => ['numlista' => 10, 'station_id' => 11, 'name' => 'Autolavado'],
    10 => ['numlista' => 11, 'station_id' => 12, 'name' => 'Directivos'],
    11 => ['numlista' => 12, 'station_id' => 13, 'name' => 'Dirección de Operaciones'],
    12 => ['numlista' => 13, 'station_id' => 9, 'name' => 'Servicio Profesionales'],
    13 => ['numlista' => 14, 'station_id' => 10, 'name' => 'Departamento Gestión'],
];
$localityRows = [];
$stationRows = [];
foreach ($approved as $id => $row) {
    $localityRows[] = ['id' => $id, 'numlista' => $row['numlista'], 'localidad' => $row['name']];
    // The owner-approved destination list is the Comodines classification;
    // status 0 is deliberately retained for these eight verified stations.
    $stationRows[] = ['id' => $row['station_id'], 'numlista' => $row['numlista'], 'nombre' => $row['name'], 'estatus' => 0];
}
foreach ($excluded as $id => $row) {
    $localityRows[] = ['id' => $id, 'numlista' => $row['numlista'], 'localidad' => $row['name']];
    // An active station status does not add a destination to the approved list.
    $stationRows[] = ['id' => $row['station_id'], 'numlista' => $row['numlista'], 'nombre' => $row['name'], 'estatus' => 1];
}
foreach ([15 => 15, 16 => 16, 17 => 17, 18 => 18, 19 => 19, 20 => 20, 21 => 21, 22 => 22, 23 => 23, 999 => 999] as $id => $numlista) {
    $localityRows[] = ['id' => $id, 'numlista' => $numlista, 'localidad' => 'No operativa ' . $id];
}
Capsule::table('op_rh_localidades')->insert($localityRows);
Capsule::table('tb_estaciones')->insert($stationRows);

$actualIds = array_map('intval', array_column(RolComodinesService::coverageWorkplaces(0), 'id'));
sort($actualIds, SORT_NUMERIC);
$assert(RolComodinesService::ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS === [1, 2, 3, 4, 5, 6, 7, 14]
    && $actualIds === [1, 2, 3, 4, 5, 6, 7, 14],
    'the selector returns only the eight owner-approved canonical destinations');
$assert(RolComodinesService::resolveCoverageDestination(1) !== null
    && RolComodinesService::resolveCoverageDestination(14) !== null,
    'the reviewed unique physical crosswalk admits approved destinations despite the known status=0 inconsistency');
$excludedIds = [8, 9, 10, 11, 12, 13, 15, 16, 17, 18, 19, 20, 21, 22, 23, 999];
$assert(count(array_filter($excludedIds, static fn(int $id): bool => RolComodinesService::resolveCoverageDestination($id) !== null)) === 0,
    'Comodines, Autolavado, administrative, DEMO, unapproved, and unmapped localities are rejected');
$assert(RolComodinesService::coverageWorkplaces(2) !== []
    && !in_array(2, array_column(RolComodinesService::coverageWorkplaces(2), 'id'), true),
    'the existing exclude-workplace argument remains effective for the selector projection');

$db = Capsule::connection();
$db->table('tb_estaciones')->insert(['id' => 101, 'numlista' => 2, 'nombre' => 'Duplicate Interlomas', 'estatus' => 1]);
$assert(RolComodinesService::resolveCoverageDestination(1) === null
    && !in_array(1, array_column(RolComodinesService::coverageWorkplaces(0), 'id'), true),
    'a duplicated physical numlista mapping fails closed for that approved destination');
$db->table('tb_estaciones')->where('id', 101)->delete();

$db->table('tb_estaciones')->where('id', 14)->delete();
$db->table('tb_estaciones')->insert(['id' => 114, 'numlista' => 8, 'nombre' => 'Changed Bosque Real mapping', 'estatus' => 0]);
$assert(RolComodinesService::resolveCoverageDestination(14) === null,
    'a changed physical station identity fails closed until the reviewed mapping is updated');
$db->table('tb_estaciones')->where('id', 114)->delete();
$db->table('tb_estaciones')->insert(['id' => 14, 'numlista' => 8, 'nombre' => 'Bosque Real', 'estatus' => 0]);

$db->table('op_rh_localidades')->insert(['id' => 101, 'numlista' => 2, 'localidad' => 'Duplicate locality mapping']);
$assert(RolComodinesService::resolveCoverageDestination(1) === null,
    'a duplicated canonical numlista mapping fails closed');
$db->table('op_rh_localidades')->where('id', 101)->delete();
$db->table('op_rh_localidades')->where('id', 14)->update(['numlista' => 888]);
$assert(RolComodinesService::resolveCoverageDestination(14) === null,
    'a changed canonical numlista fails closed until reviewed');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
$writer = file_get_contents($root . '/app/Services/RolComodinesPortalWriterService.php') ?: '';
$service = file_get_contents($root . '/app/Services/RolComodinesService.php') ?: '';
$roleController = file_get_contents($root . '/app/Controllers/RolComodinesController.php') ?: '';
$createStart = strpos($writer, 'public function createRequest(');
$transitionStart = strpos($writer, 'public function transition(', max(0, $createStart));
$createCode = $createStart !== false && $transitionStart !== false ? substr($writer, $createStart, $transitionStart - $createStart) : '';
$transitionEnd = strpos($writer, 'private function lockWorkerAndWorkplaces(', max(0, $transitionStart));
$transitionCode = $transitionStart !== false && $transitionEnd !== false ? substr($writer, $transitionStart, $transitionEnd - $transitionStart) : '';
$authorizeStart = strpos($transitionCode, "if (\$action === 'AUTHORIZE')");
$cancelStart = $authorizeStart === false ? false : strpos($transitionCode, '} else {', $authorizeStart);
$authorizeCode = $authorizeStart !== false && $cancelStart !== false ? substr($transitionCode, $authorizeStart, $cancelStart - $authorizeStart) : '';
$cancelCode = $cancelStart !== false ? substr($transitionCode, $cancelStart) : '';
$assert($createCode !== ''
    && str_contains($createCode, '$this->assertCoverageDestination($request[\'id_coverage_workplace\'])')
    && strpos($createCode, '$this->assertCoverageDestination(') < strpos($createCode, 'INSERT INTO op_rh_v2_worker_workplace_coverage_versions')
    && str_contains($authorizeCode, '$this->assertCoverageDestination((int) $current[\'id_coverage_workplace\'])')
    && !str_contains($cancelCode, '$this->assertCoverageDestination(')
    && str_contains($writer, 'resolveCoverageDestination($workplaceId, true)'),
    'the request and approval transactions revalidate the same mapping while cancellation is not blocked by list removal');
$assert(str_contains($controller, 'RolComodinesService::coverageWorkplaces(0)')
    && str_contains($controller, 'in_array((int) ($workplace[\'id\'] ?? 0), $authorizedWorkplaceIds, true)')
    && str_contains($service, 'public const AUTHORIZED_COVERAGE_WORKER_IDS = [96, 100, 102, 310, 432, 452, 472, 491, 500, 512]'),
    'effective Direction scope and the approved worker roster remain independent and unchanged');
$assert(str_contains($writer, "'coverage_destination_not_authorized'")
    && str_contains($writer, "'coverage_destination_mapping_unavailable'")
    && str_contains($roleController, 'coverage_destination_mapping_unavailable'),
    'rejected or unverifiable destinations have explicit server errors and a clear Portal3 message');

printf("CHECADOR_V2_COMODINES_DESTINATION_ALLOWLIST: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

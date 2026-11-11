<?php

declare(strict_types=1);

use App\Services\WorkerWorkplaceAssignmentBaselinePreviewService;
use App\Services\WorkerWorkplaceAssignmentBaselineImportService;
use App\Services\RolComodinesService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../vendor/autoload.php';

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::schema();
$schema->create('tb_modulos_config', static function (Blueprint $table): void {
    $table->string('modulo_key')->primary();
    $table->string('tipo_departamento');
    $table->integer('activo');
});
$schema->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('localidad')->nullable();
});
$schema->create('tb_estaciones', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('nombre')->nullable();
    $table->integer('estatus')->default(0);
});
$schema->create('op_rh_personal', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('id_estacion')->nullable();
    $table->integer('estado')->default(1);
    $table->string('nombre_completo')->nullable();
    $table->string('no_colaborador')->nullable();
});
$schema->create('op_rh_personal_localidad_version', static function (Blueprint $table): void {
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
$schema->create('op_rh_comodines_dia', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_reporte');
    $table->integer('id_estacion');
    $table->string('dia');
});

$db = Capsule::connection();
$db->table('tb_modulos_config')->insert([
    'modulo_key' => 'control-documentos-personal',
    'tipo_departamento' => 'localidades',
    'activo' => 1,
]);
$db->table('op_rh_localidades')->insert([
    ['id' => 9, 'numlista' => 10, 'localidad' => 'Autolavado'],
    ['id' => 3, 'numlista' => 33, 'localidad' => 'Colisión numérica'],
]);
$db->table('tb_estaciones')->insert([
    ['id' => 11, 'numlista' => 10, 'nombre' => 'Autolavado físico', 'estatus' => 1],
    // A different physical station deliberately has ID 9; numeric equality does not map it to Autolavado.
    ['id' => 9, 'numlista' => 33, 'nombre' => 'Estación de colisión', 'estatus' => 1],
]);
$db->table('op_rh_personal')->insert([
    ['id' => 310, 'id_estacion' => 11, 'estado' => 1, 'nombre_completo' => 'MARIA JOSE ARELLANO GUTIEREZ', 'no_colaborador' => '0'],
    ['id' => 512, 'id_estacion' => 11, 'estado' => 1, 'nombre_completo' => 'SHEILY DE LOS SANTOS VARGAS', 'no_colaborador' => '512'],
    ['id' => 901, 'id_estacion' => 9, 'estado' => 1, 'nombre_completo' => 'WRONG STATION', 'no_colaborador' => '901'],
    ['id' => 902, 'id_estacion' => 11, 'estado' => 0, 'nombre_completo' => 'INACTIVE', 'no_colaborador' => '902'],
]);

$scope = static fn(): array => ['actor_id' => 77, 'workplace_ids' => [9], 'can_edit' => true];
$service = new WorkerWorkplaceAssignmentBaselinePreviewService($scope);
$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passed++ : $failed++;
};
$throws = static function (callable $callback, string $fragment): bool {
    try {
        $callback();
        return false;
    } catch (Throwable $exception) {
        return str_contains($exception->getMessage(), $fragment);
    }
};

$mapping = $service->resolvePhysicalStationMapping(9);
$snapshot = $service->readScopedSnapshot(9);
$assert($mapping === [
    'workplace_id' => 9,
    'locality' => 'Autolavado',
    'numlista' => 10,
    'physical_station_id' => 11,
    'station_status' => 1,
], 'unique active station 11 maps to canonical locality 9 through numlista 10');
$assert($snapshot['counts']['active_workers'] === 2
    && array_column($snapshot['candidate_rows']->all(), 'worker_id') === [310, 512]
    && array_column($snapshot['candidate_rows']->all(), 'workplace_id') === [9, 9]
    && array_column($snapshot['candidate_rows']->all(), 'full_name') === ['MARIA JOSE ARELLANO GUTIEREZ', 'SHEILY DE LOS SANTOS VARGAS']
    && $snapshot['history_rows']->isEmpty()
    && $snapshot['locality_reference']['physical_station_id'] === 11,
    'read-only baseline preview confirms candidate names, missing membership, physical source station, and canonical workplace IDs');
$assert($snapshot['source_rows']->every(static fn(object $row): bool => (int) $row->physical_station_status === 1
    && (int) $row->physical_numlista === 10
    && (int) $row->canonical_workplace_id === 9
    && (int) $row->mapping_unique === 1),
    'source fingerprint inputs retain the validated physical and canonical mapping');
$availableForSelection = [
    ['worker_id' => 310, 'workplace_id' => 9, 'full_name' => 'Maria'],
    ['worker_id' => 512, 'workplace_id' => 9, 'full_name' => 'Sheily'],
    ['worker_id' => 514, 'workplace_id' => 9, 'full_name' => 'Other candidate'],
];
$selectedWorkers = WorkerWorkplaceAssignmentBaselinePreviewService::selectCandidateRows($availableForSelection, [512, 310]);
$assert(array_column($selectedWorkers, 'worker_id') === [310, 512]
    && array_column($selectedWorkers, 'workplace_id') === [9, 9]
    && !in_array(514, array_column($selectedWorkers, 'worker_id'), true),
    'selective preview keeps the explicit worker subset canonical and leaves unselected candidates out');
$assert($throws(static fn() => WorkerWorkplaceAssignmentBaselinePreviewService::selectCandidateRows($availableForSelection, [310, 999]), 'not an eligible candidate')
    && $throws(static fn() => WorkerWorkplaceAssignmentBaselinePreviewService::normalizeSelectedWorkerIds([310, 310]), 'unique positive integers')
    && $throws(static fn() => WorkerWorkplaceAssignmentBaselinePreviewService::normalizeSelectedWorkerIds([]), 'at least one worker'),
    'selective membership input rejects outside IDs, duplicates, and empty imports');
$comodinesRead = RolComodinesService::getChecadorReadModel(9, [9]);
$assert(($comodinesRead['ok'] ?? false) === true
    && ($comodinesRead['station_name'] ?? '') !== '',
    'Comodines read path accepts active station status 1 through the canonical mapping');
$lockedSnapshot = $db->transaction(static fn(): array => $service->readScopedSnapshot(9, forUpdate: true, actorId: 77, requireWrite: true));
$candidate = $db->transaction(static fn(): array => $service->lockAndRevalidateCandidate(310, 9, 77));
$assert(array_column($lockedSnapshot['candidate_rows']->all(), 'worker_id') === [310, 512]
    && $candidate === ['worker_id' => 310, 'workplace_id' => 9],
    'locked snapshot and candidate revalidation reuse the same canonical mapping');
$db->table('op_rh_personal')->where('id', 310)->update(['id_estacion' => 9]);
$assert($throws(static fn() => $db->transaction(static fn() => $service->lockAndRevalidateCandidate(310, 9, 77)), 'Candidate locality changed'),
    'locked revalidation rejects a worker moved to a different physical station even when its ID is canonical-looking');
$db->table('op_rh_personal')->where('id', 310)->update(['id_estacion' => 11]);

$cutoff = '2026-11-12T06:00:00.000000Z';
$reason = 'fixture retry readback';
$db->table('op_rh_personal_localidad_version')->insert([
    'worker_id' => 310,
    'workplace_id' => 9,
    'valid_from_utc' => '2026-11-12 06:00:00.000000',
    'valid_to_utc' => null,
    'recorded_at_utc' => '2026-11-10 12:00:00.000000',
    'recorded_by' => 77,
    'reason' => $reason,
    'supersedes_id' => null,
]);
$importer = new WorkerWorkplaceAssignmentBaselineImportService($service);
$retryReadback = new ReflectionMethod($importer, 'matchingAlreadyAppliedRows');
$retryReadback->setAccessible(true);
$alreadyApplied = $db->transaction(static fn(): ?array => $retryReadback->invoke(
    $importer,
    9,
    1,
    WorkerWorkplaceAssignmentBaselinePreviewService::candidateFingerprint([
        ['worker_id' => 310, 'workplace_id' => 9],
    ]),
    $cutoff,
    77,
    $reason
));
$assert($alreadyApplied === [['worker_id' => 310, 'workplace_id' => 9]],
    'idempotent import readback filters by physical station 11 and canonical membership locality 9');

$db->table('tb_estaciones')->where('id', 11)->update(['estatus' => 0]);
$assert($throws(static fn() => $service->readScopedSnapshot(9), 'not active')
    && $throws(static fn() => $db->transaction(static fn() => $service->lockAndRevalidateCandidate(310, 9, 77)), 'not active')
    && (RolComodinesService::getChecadorReadModel(9, [9])['ok'] ?? true) === false,
    'canceled status 0 is denied in baseline preview, locked revalidation, and Comodines read path');
$db->table('tb_estaciones')->where('id', 11)->update(['estatus' => 1]);

$db->table('tb_estaciones')->insert(['id' => 12, 'numlista' => 10, 'nombre' => 'Duplicada', 'estatus' => 1]);
$assert($throws(static fn() => $service->readScopedSnapshot(9), 'physical station mapping is missing or ambiguous'),
    'duplicate physical station numlista mapping is rejected');
$db->table('tb_estaciones')->where('id', 12)->delete();

$db->table('op_rh_localidades')->insert(['id' => 10, 'numlista' => 10, 'localidad' => 'Duplicate locality mapping']);
$assert($throws(static fn() => $service->readScopedSnapshot(9), 'canonical locality numlista mapping is missing or ambiguous'),
    'duplicate canonical locality numlista mapping is rejected');
$db->table('op_rh_localidades')->where('id', 10)->delete();

$db->table('tb_estaciones')->where('id', 11)->update(['numlista' => 33]);
$assert($throws(static fn() => $service->readScopedSnapshot(9), 'physical station mapping is missing or ambiguous'),
    'a missing physical-to-canonical bridge fails closed even when numeric IDs collide');

printf("CHECADOR_V2_BASELINE_PHYSICAL_CANONICAL_MAPPING: %d PASS / %d FAIL\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);

<?php

declare(strict_types=1);

use App\Services\RolComodinesService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

require_once __DIR__ . '/../vendor/autoload.php';

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::schema();
$schema->create('op_rh_personal', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('id_estacion');
    $table->integer('puesto');
    $table->string('nombre_completo');
    $table->integer('no_colaborador')->nullable();
    $table->integer('estado')->default(1);
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

$db = Capsule::connection();
$db->table('op_rh_personal')->insert([
    ['id' => 310, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Maria autorizada', 'no_colaborador' => 0, 'estado' => 1],
    ['id' => 512, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Encargada desde estación 11', 'no_colaborador' => 915, 'estado' => 1],
    ['id' => 96, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Autorizada inactiva 96', 'no_colaborador' => 96, 'estado' => 0],
    ['id' => 100, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Autorizada inactiva 100', 'no_colaborador' => 100, 'estado' => 0],
    ['id' => 102, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Autorizada inactiva 102', 'no_colaborador' => 102, 'estado' => 0],
    ['id' => 432, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Autorizada inactiva 432', 'no_colaborador' => 432, 'estado' => 0],
    ['id' => 452, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Autorizada inactiva 452', 'no_colaborador' => 452, 'estado' => 0],
    ['id' => 472, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Autorizada inactiva 472', 'no_colaborador' => 472, 'estado' => 0],
    ['id' => 491, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Autorizada inactiva 491', 'no_colaborador' => 491, 'estado' => 0],
    ['id' => 500, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Autorizada inactiva 500', 'no_colaborador' => 500, 'estado' => 0],
    ['id' => 701, 'id_estacion' => 77, 'puesto' => 14, 'nombre_completo' => 'Puesto catorce en otra estación', 'no_colaborador' => 701, 'estado' => 1],
    ['id' => 702, 'id_estacion' => 88, 'puesto' => 7, 'nombre_completo' => 'Puesto distinto número cero', 'no_colaborador' => 0, 'estado' => 1],
    ['id' => 703, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Inactivo', 'no_colaborador' => 703, 'estado' => 0],
    ['id' => 704, 'id_estacion' => 91, 'puesto' => 99, 'nombre_completo' => 'Membresía vence durante el periodo', 'no_colaborador' => 704, 'estado' => 1],
    ['id' => 705, 'id_estacion' => 92, 'puesto' => 99, 'nombre_completo' => 'Membresía inicia después del comienzo', 'no_colaborador' => 705, 'estado' => 1],
    ['id' => 706, 'id_estacion' => 93, 'puesto' => 99, 'nombre_completo' => 'Fuera de alcance', 'no_colaborador' => 706, 'estado' => 1],
    ['id' => 707, 'id_estacion' => 94, 'puesto' => 99, 'nombre_completo' => 'Membresía ambigua', 'no_colaborador' => 707, 'estado' => 1],
    ['id' => 326, 'id_estacion' => 1, 'puesto' => 6, 'nombre_completo' => 'Maria Jose Arellano Gutierez', 'no_colaborador' => 326, 'estado' => 1],
    ['id' => 1, 'id_estacion' => 2, 'puesto' => 6, 'nombre_completo' => 'Encargado fuera del padrón', 'no_colaborador' => 1, 'estado' => 1],
    ['id' => 799, 'id_estacion' => 11, 'puesto' => 6, 'nombre_completo' => 'Nuevo puesto seis fuera del padrón', 'no_colaborador' => 799, 'estado' => 1],
    ['id' => 708, 'id_estacion' => 95, 'puesto' => 99, 'nombre_completo' => 'Busqueda por número', 'no_colaborador' => 987, 'estado' => 1],
]);
$db->table('op_rh_personal_localidad_version')->insert([
    ['id' => 1, 'worker_id' => 512, 'workplace_id' => 9, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 12, 'worker_id' => 310, 'workplace_id' => 2, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 2, 'worker_id' => 701, 'workplace_id' => 2, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 3, 'worker_id' => 702, 'workplace_id' => 2, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 4, 'worker_id' => 703, 'workplace_id' => 9, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 5, 'worker_id' => 704, 'workplace_id' => 2, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => '2027-03-01 18:00:00.000000', 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 6, 'worker_id' => 705, 'workplace_id' => 2, 'valid_from_utc' => '2027-03-01 10:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2027-03-01 10:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 7, 'worker_id' => 706, 'workplace_id' => 3, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 8, 'worker_id' => 707, 'workplace_id' => 2, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 9, 'worker_id' => 707, 'workplace_id' => 3, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 10, 'worker_id' => 326, 'workplace_id' => 1, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 11, 'worker_id' => 708, 'workplace_id' => 2, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 13, 'worker_id' => 1, 'workplace_id' => 1, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
    ['id' => 14, 'worker_id' => 799, 'workplace_id' => 9, 'valid_from_utc' => '2026-01-01 00:00:00.000000', 'valid_to_utc' => null, 'recorded_at_utc' => '2026-01-01 00:00:00.000000', 'recorded_by' => null, 'reason' => 'fixture-only', 'supersedes_id' => null],
]);

for ($id = 800; $id < 830; $id++) {
    $db->table('op_rh_personal')->insert([
        'id' => $id,
        'id_estacion' => 200 + $id,
        'puesto' => 99,
        'nombre_completo' => 'MassCandidate ' . $id,
        'no_colaborador' => $id,
        'estado' => 1,
    ]);
    $db->table('op_rh_personal_localidad_version')->insert([
        'id' => $id - 780,
        'worker_id' => $id,
        'workplace_id' => 3,
        'valid_from_utc' => '2026-01-01 00:00:00.000000',
        'valid_to_utc' => null,
        'recorded_at_utc' => '2026-01-01 00:00:00.000000',
        'recorded_by' => null,
        'reason' => 'fixture-only',
        'supersedes_id' => null,
    ]);
}

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};
$search = static fn(string $term = '', array $scope = [2, 9]): array =>
    RolComodinesService::searchCoverageWorkers($scope, $term, '2027-03-01', '2027-03-02');

$result = $search();
$workers = $result['workers'] ?? [];
$byId = [];
foreach ($workers as $worker) $byId[(int) $worker['worker_id']] = $worker;
ksort($byId);
$assert(RolComodinesService::AUTHORIZED_COVERAGE_WORKER_IDS === [96, 100, 102, 310, 432, 452, 472, 491, 500, 512],
    'the ten business-authorized RH identities are maintained in one server-side roster');
$assert(($result['ok'] ?? false) === true && array_keys($byId) === [310, 512],
    'search returns only active allowlisted workers with a canonical membership valid throughout the interval and scope');
$assert(($byId[512]['home_workplace_id'] ?? null) === 9
    && ($byId[512]['eligible'] ?? false) === true
    && ($byId[512]['id_home_membership_version'] ?? 0) === 1,
    'home locality 9 is read from canonical membership, not physical station 11');
$assert(($byId[310]['home_workplace_id'] ?? null) === 2 && ($byId[310]['eligible'] ?? false) === true,
    'canonical origin is resolved from membership independently of physical station and employee number zero');
$searchById = $search('512');
$searchByNumber = $search('0');
$searchByName = $search('encargada');
$assert(array_column($searchById['workers'] ?? [], 'worker_id') === [512]
    && array_column($searchByNumber['workers'] ?? [], 'worker_id') === [310]
    && array_column($search('915')['workers'] ?? [], 'worker_id') === [512]
    && array_column($searchByName['workers'] ?? [], 'worker_id') === [512],
    'search supports authorized RH ID, employee number including zero, and name');
$scopeWorkers = array_map('intval', array_column($search('', [2])['workers'] ?? [], 'worker_id'));
sort($scopeWorkers, SORT_NUMERIC);
$assert($scopeWorkers === [310],
    'canonical origin scope is applied independently from physical station and coverage destination');
$massSearch = $search('MassCandidate', [3]);
$assert(($massSearch['workers'] ?? []) === [] && ($massSearch['has_more'] ?? false) === false,
    'a broad group of active workers cannot expand the explicitly approved roster');
$identitySearch = $search('326', [1, 2, 9]);
$assert(($identitySearch['workers'] ?? []) === []
    && ($search('Encargado fuera del padrón', [1, 2, 9])['workers'] ?? []) === []
    && ($search('799', [1, 2, 9])['workers'] ?? []) === [],
    'workers 326, 1, and a new station-11 worker remain excluded even with canonical memberships');
$assert(($search('', [])['workers'] ?? []) === [], 'empty server-side scope returns no candidates');
$inactiveIds = [96, 100, 102, 432, 452, 472, 491, 500];
$assert((RolComodinesService::resolveCoverageCandidateSource(310)['eligible'] ?? false) === true
    && (RolComodinesService::resolveCoverageCandidateSource(512)['eligible'] ?? false) === true
    && count(array_filter($inactiveIds, static fn(int $id): bool => empty(RolComodinesService::resolveCoverageCandidateSource($id)['eligible']))) === 8,
    'active approved IDs pass the source rule while all eight inactive approved IDs remain ineligible');
$assert(empty(RolComodinesService::resolveCoverageCandidateSource(326)['eligible'])
    && empty(RolComodinesService::resolveCoverageCandidateSource(1)['eligible'])
    && empty(RolComodinesService::resolveCoverageCandidateSource(799)['eligible'])
    && empty(RolComodinesService::resolveCoverageCandidateSource(701)['eligible']),
    'transactional candidate recheck rejects 326, 1, unlisted station-11 IDs, and other active RH workers');
$db->table('op_rh_personal_localidad_version')->where('worker_id', 310)->delete();
$missingMembership = $search('310');
$assert(($missingMembership['workers'] ?? []) === []
    && ($missingMembership['eligibility_notice'] ?? '') === 'No hay comodines elegibles. Verifica la adscripción habitual registrada por RH, el periodo y las localidades autorizadas.',
    'missing canonical membership is explained without exposing an ineligible selectable option');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/RolComodinesController.php') ?: '';
$writer = file_get_contents($root . '/app/Services/RolComodinesPortalWriterService.php') ?: '';
$service = file_get_contents($root . '/app/Services/RolComodinesService.php') ?: '';
$controller = file_get_contents($root . '/app/Controllers/RolComodinesController.php') ?: '';
$js = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.role-comodines.programming.init.js') ?: '';
$assert(!str_contains($controller, 'in_array(9, $authorizedWorkplaceIds, true)')
    && !str_contains($service, 'COVERAGE_CANDIDATE_STATION_ID')
    && !str_contains($service, 'COVERAGE_CANDIDATE_POSITION_ID')
    && !str_contains($service, 'COVERAGE_CANDIDATE_WORKPLACE_ID'),
    'the candidate search and endpoint no longer hard-code Autolavado, station 11, or position 6');
$assert(str_contains($writer, 'resolveCoverageCandidateSource($request[\'id_personal\'], true)')
    && str_contains($writer, '$this->assertHomeMembership($request)')
    && str_contains($writer, 'self::assertScope($scope, $request[\'id_home_workplace\'], $request[\'id_coverage_workplace\'])'),
    'the write transaction rechecks active worker, canonical membership, and both locality scopes');
$requestStart = strpos($writer, 'public function createRequest(');
$transitionStart = strpos($writer, 'public function transition(', max(0, $requestStart));
$requestCode = $requestStart !== false && $transitionStart !== false
    ? substr($writer, $requestStart, $transitionStart - $requestStart)
    : '';
$requestCandidateCheck = strpos($requestCode, 'resolveCoverageCandidateSource($request[\'id_personal\'], true)');
$requestPersistence = strpos($requestCode, 'INSERT INTO op_rh_v2_worker_workplace_coverage_versions');
$assert($requestCode !== '' && str_contains($requestCode, "'REQUESTED'")
    && !str_contains($requestCode, 'createLinkedAssignments')
    && $requestCandidateCheck !== false && $requestPersistence !== false && $requestCandidateCheck < $requestPersistence
    && str_contains($writer, '($action===\'AUTHORIZE\' && $sourceStatus!==\'REQUESTED\')'),
    'request recording stays separate from explicit authorization and cannot create approved coverage implicitly');
$transitionEnd = strpos($writer, 'private function lockWorkerAndWorkplaces(', max(0, $transitionStart));
$transitionCode = $transitionStart !== false && $transitionEnd !== false
    ? substr($writer, $transitionStart, $transitionEnd - $transitionStart)
    : '';
$assert($transitionCode !== ''
    && str_contains($transitionCode, 'if ($action === \'AUTHORIZE\')')
    && str_contains($transitionCode, 'resolveCoverageCandidateSource((int) $current[\'id_personal\'], true)')
    && str_contains($transitionCode, "throw new RuntimeException('worker_candidate_not_eligible')")
    && strpos($transitionCode, 'resolveCoverageCandidateSource((int) $current[\'id_personal\'], true)')
        < strpos($transitionCode, '$this->insertRevision('),
    'approval revalidates the allowlist and active status inside the writer transaction before creating authorized assignments');
$assert(str_contains($controller, 'worker_search_too_broad')
    && str_contains($controller, 'Afínala por nombre, ID RH o número de colaborador.'),
    'broad searches fail with a clear message instead of returning an incomplete eligibility list');
$assert(str_contains($js, "worker.eligible === true")
    && str_contains($js, 'No hay comodines elegibles. Verifica la adscripción habitual registrada por RH, el periodo y las localidades autorizadas.')
    && str_contains($controller, '\'eligibility_notice\' => $result[\'eligibility_notice\'] ?? null')
    && !str_contains($js, 'disabled: !eligible'),
    'the Select2 exposes only eligible workers and explains the empty result without disabled entries');

printf("CHECADOR_V2_COMODINES_CANDIDATE_SOURCE: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

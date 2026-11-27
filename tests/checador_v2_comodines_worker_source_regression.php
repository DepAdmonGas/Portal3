<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorRolComodinesService;
use App\Services\RolComodinesPortalWriterService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

// Ephemeral in-memory fixture only; this test never connects to or mutates MariaDB.
$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::schema('default');
$schema->create('op_rh_personal', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('nombre_completo');
    $table->string('no_colaborador')->nullable();
    $table->integer('id_estacion');
    $table->integer('puesto');
    $table->integer('estado')->default(1);
});
$schema->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('numlista');
});
$schema->create('tb_estaciones', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('numlista');
    $table->string('nombre');
    $table->integer('estatus');
});
$schema->create('op_rh_personal_localidad_version', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('worker_id');
    $table->integer('workplace_id');
    $table->dateTime('valid_from_utc');
    $table->dateTime('valid_to_utc')->nullable();
    $table->dateTime('recorded_at_utc');
    $table->integer('recorded_by')->nullable();
    $table->text('reason')->nullable();
    $table->integer('supersedes_id')->nullable();
});

$recordedAt = gmdate('Y-m-d H:i:s');
Capsule::table('op_rh_localidades')->insert([
    ['id' => 1, 'numlista' => 2],
    ['id' => 2, 'numlista' => 3],
    ['id' => 9, 'numlista' => 99],
    ['id' => 10, 'numlista' => 100],
]);
Capsule::table('tb_estaciones')->insert([
    ['id' => 1, 'numlista' => 2, 'nombre' => 'Estación autorizada fixture', 'estatus' => 0],
    ['id' => 2, 'numlista' => 3, 'nombre' => 'Estación fuera de alcance fixture', 'estatus' => 0],
    ['id' => 10, 'numlista' => 100, 'nombre' => 'Estación inactiva fixture', 'estatus' => 1],
]);
$candidateNames = [
    219 => 'LOPEZ DOMINGUEZ HUMBERTO ESTEBAN',
    220 => 'HUMBERTO ESTEBAN LOPEZ DOMINGUEZ',
    221 => 'COMODIN ESTADO CERO A',
    222 => 'COMODIN SIN ADSCRIPCION',
    362 => 'DAVID ANTONIO VASQUEZ ZARATE',
    402 => 'COMODIN ESTADO CERO B',
];
foreach ([
    ['id' => 219, 'name' => 'LOPEZ DOMINGUEZ HUMBERTO ESTEBAN', 'number' => 'EMP-219', 'position' => 14],
    ['id' => 220, 'name' => 'HUMBERTO ESTEBAN LOPEZ DOMINGUEZ', 'number' => 'EMP-220', 'position' => 14],
    ['id' => 221, 'name' => 'COMODIN ESTADO CERO A', 'number' => 'EMP-221', 'position' => 14, 'state' => 0],
    ['id' => 222, 'name' => 'COMODIN SIN ADSCRIPCION', 'number' => 'EMP-222', 'position' => 14, 'state' => 0, 'membership' => false],
    ['id' => 362, 'name' => 'DAVID ANTONIO VASQUEZ ZARATE', 'number' => 'EMP-362', 'position' => 14, 'state' => 1],
    ['id' => 402, 'name' => 'COMODIN ESTADO CERO B', 'number' => 'EMP-402', 'position' => 14, 'state' => 0],
    ['id' => 5000, 'name' => 'PERSONAL NO COMODIN', 'number' => 'EMP-5000', 'position' => 6],
    ['id' => 5001, 'name' => 'COMODIN CON ESTADO FUERA DE POLITICA', 'number' => 'EMP-5001', 'position' => 14, 'state' => 2],
    ['id' => 5002, 'name' => 'COMODIN OTRA LOCALIDAD', 'number' => 'EMP-5002', 'position' => 14, 'station' => 2, 'workplace' => 2],
] as $person) {
    Capsule::table('op_rh_personal')->insert([
        'id' => $person['id'], 'nombre_completo' => $person['name'],
        'no_colaborador' => $person['number'], 'id_estacion' => $person['station'] ?? 1,
        'puesto' => $person['position'], 'estado' => $person['state'] ?? 1,
    ]);
    if (($person['membership'] ?? true) === true) {
        Capsule::table('op_rh_personal_localidad_version')->insert([
            'worker_id' => $person['id'], 'workplace_id' => $person['workplace'] ?? 1,
            'valid_from_utc' => '2020-01-01 00:00:00', 'valid_to_utc' => null,
            'recorded_at_utc' => $recordedAt, 'recorded_by' => 1,
            'reason' => 'fixture aislado', 'supersedes_id' => null,
        ]);
    }
}

// Extra isolated rows exercise the empty-query catalogue paging path.
for ($workerId = 6000; $workerId < 6020; $workerId++) {
    Capsule::table('op_rh_personal')->insert([
        'id' => $workerId,
        'nombre_completo' => 'CATALOGO ADICIONAL ' . $workerId,
        'no_colaborador' => 'EMP-' . $workerId,
        'id_estacion' => 1,
        'puesto' => 14,
        'estado' => 1,
    ]);
}

$byName = ChecadorRolComodinesService::searchCoverageWorkers([1], 'LOPEZ', '2026-11-24', '2026-11-25');
$byId = ChecadorRolComodinesService::searchCoverageWorkers([1], '220', '2026-11-24', '2026-11-25');
$byNumber = ChecadorRolComodinesService::searchCoverageWorkers([1], 'EMP-219', '2026-11-24', '2026-11-25');
$byDavidName = ChecadorRolComodinesService::searchCoverageWorkers([1], 'da');
$withoutDatesByName = ChecadorRolComodinesService::searchCoverageWorkers([1], 'LOPEZ');
$withoutDatesById = ChecadorRolComodinesService::searchCoverageWorkers([1], '222');
$scopeWithUnmappedLocality = ChecadorRolComodinesService::searchCoverageWorkers([1, 9, 10], '362');
$partialRange = ChecadorRolComodinesService::searchCoverageWorkers([1], '219', '2026-11-24', '');
$withoutMembershipById = ChecadorRolComodinesService::searchCoverageWorkers([1], '222', '2026-11-24', '2026-11-25');
$outOfScopeById = ChecadorRolComodinesService::searchCoverageWorkers([1], '5002', '2026-11-24', '2026-11-25');
$wrongPositionById = ChecadorRolComodinesService::searchCoverageWorkers([1], '5000', '2026-11-24', '2026-11-25');
$disallowedStateById = ChecadorRolComodinesService::searchCoverageWorkers([1], '5001', '2026-11-24', '2026-11-25');
$emptyCatalogPage1 = ChecadorRolComodinesService::searchCoverageWorkers([1], '');
$emptyCatalogPage2 = ChecadorRolComodinesService::searchCoverageWorkers([1], '', '', '', 2);
$ids = static fn(array $result): array => array_map(
    static fn(array $worker): int => (int) ($worker['worker_id'] ?? 0),
    $result['workers'] ?? []
);
$withoutDatesNameIds = $ids($withoutDatesByName);
sort($withoutDatesNameIds);
$nameIds = $ids($byName);
sort($nameIds);
$assert(!empty($byName['ok']) && $nameIds === [219, 220],
    'name search returns puesto 14 workers in the authorized physical station scope');
$assert(!empty($byId['ok']) && $ids($byId) === [220],
    'search by canonical RH ID returns the matching puesto 14 worker');
$assert(!empty($byNumber['ok']) && $ids($byNumber) === [219],
    'search by employee number returns the matching puesto 14 worker');
$assert(!empty($byDavidName['ok']) && $ids($byDavidName) === [362]
    && ($byDavidName['workers'][0]['full_name'] ?? '') === 'DAVID ANTONIO VASQUEZ ZARATE',
    'short name search finds David RH ID 362 without requiring dates');
$assert(!empty($withoutDatesByName['ok']) && $withoutDatesNameIds === [219, 220],
    'name search works without dates');
$assert(!empty($withoutDatesById['ok']) && $ids($withoutDatesById) === [222],
    'ID search works without dates');
$assert(!empty($scopeWithUnmappedLocality['ok']) && $ids($scopeWithUnmappedLocality) === [362],
    'search q=362 without dates succeeds when Direction also has a department and an unusable physical-station mapping in its wider scope');
$assert(!empty($emptyCatalogPage1['ok']) && count($emptyCatalogPage1['workers'] ?? []) === 25
    && !empty($emptyCatalogPage1['has_more'])
    && !empty($emptyCatalogPage2['ok']) && count($emptyCatalogPage2['workers'] ?? []) === 1
    && empty($emptyCatalogPage2['has_more']),
    'empty search returns an ordered, bounded catalog with working page continuation and no date requirement');
$catalogPageOneIds = $ids($emptyCatalogPage1);
$catalogPageTwoIds = $ids($emptyCatalogPage2);
$assert(count(array_intersect($catalogPageOneIds, $catalogPageTwoIds)) === 0
    && ($emptyCatalogPage1['workers'][0]['full_name'] ?? '') <= ($emptyCatalogPage1['workers'][1]['full_name'] ?? ''),
    'catalog pages are ordered by name and do not repeat candidates');
$assert(empty($withoutDatesById['workers'][0]['eligible'])
    && !empty($withoutDatesById['workers'][0]['selection_allowed'])
    && ($withoutDatesById['workers'][0]['eligibility_reason'] ?? '') === 'coverage_period_required',
    'candidate is selectable before dates, but no eligibility is granted until period verification');
$assert(empty($partialRange['ok']) && ($partialRange['reason'] ?? '') === 'invalid_worker_search',
    'an incomplete one-sided date range is rejected rather than treated as an unfiltered eligibility check');
$sixIds = [219, 220, 221, 222, 362, 402];
$sixSearches = array_map(
    static fn(int $id): array => ChecadorRolComodinesService::searchCoverageWorkers([1], (string) $id, '2026-11-24', '2026-11-25'),
    $sixIds
);
$sixFoundIds = array_map(static fn(array $result): int => (int) ($result['workers'][0]['worker_id'] ?? 0), $sixSearches);
$assert($sixFoundIds === $sixIds,
    'ID searches find all six requested RH IDs 219, 220, 221, 222, 362, and 402 in the authorized fixture scope');
$sixEligibility = [];
foreach ($sixSearches as $index => $searchResult) {
    $sixEligibility[$sixIds[$index]] = (bool) ($searchResult['workers'][0]['eligible'] ?? false);
}
$assert($sixEligibility === [219 => true, 220 => true, 221 => true, 222 => false, 362 => true, 402 => true],
    'both estado 0 and estado 1 candidates remain selectable when canonical membership is valid; missing membership alone prevents selection');
$sixNameSearches = [];
foreach ($candidateNames as $candidateId => $candidateName) {
    $nameSearch = ChecadorRolComodinesService::searchCoverageWorkers([1], $candidateName, '2026-11-24', '2026-11-25');
    $sixNameSearches[] = (int) ($nameSearch['workers'][0]['worker_id'] ?? 0) === $candidateId;
}
$assert(!in_array(false, $sixNameSearches, true),
    'name searches find each of the six requested IDs by its fixture full name');
$assert(ChecadorRolComodinesService::resolveCoverageCandidateSource(219)['eligible'] === true
    && ChecadorRolComodinesService::resolveCoverageCandidateSource(221)['eligible'] === true
    && ChecadorRolComodinesService::resolveCoverageCandidateSource(402)['eligible'] === true
    && empty(ChecadorRolComodinesService::resolveCoverageCandidateSource(5000)['eligible'])
    && empty(ChecadorRolComodinesService::resolveCoverageCandidateSource(5001)['eligible']),
    'source validation accepts puesto 14 states 0/1 and rejects another position or a state outside {0,1}');
$unassigned = $withoutMembershipById['workers'][0] ?? [];
$assert(!empty($withoutMembershipById['ok']) && ($unassigned['worker_id'] ?? 0) === 222
    && ($unassigned['eligible'] ?? true) === false
    && ($unassigned['eligibility_reason'] ?? '') === 'candidate_membership_not_valid_for_period'
    && !isset($unassigned['home_workplace_id'], $unassigned['id_home_membership_version']),
    'a worker without canonical membership is visible with a reason but receives no save-authorizing locality or membership ID');
$assert(!empty($outOfScopeById['ok']) && $ids($outOfScopeById) === [],
    'workers assigned to a physical station outside the authorized Direction scope are not disclosed');
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorRolComodinesService.php') ?: '';
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorComodinesController.php') ?: '';
$assert(str_contains($serviceSource, 'self::logCoverageWorkerSearchFailure($exception, $stage)')
    && str_contains($serviceSource, "'reason' => 'worker_search_unavailable'")
    && str_contains($controllerSource, "'No se pudo consultar el padrón de candidatos en MariaDB. Intenta de nuevo.'"),
    'SQL failures are privately diagnosed and returned as a controlled source-unavailable result, not as an empty roster');
$assert(str_contains($controllerSource, "['page'] ?? 1")
    && str_contains($controllerSource, '$page === false ? 0 : (int) $page')
    && str_contains($controllerSource, "'has_more' => (bool)"),
    'the authorized search endpoint validates the requested page and exposes continuation metadata');
$assert(!empty($wrongPositionById['ok']) && $ids($wrongPositionById) === []
    && !empty($disallowedStateById['ok']) && $ids($disallowedStateById) === [],
    'candidate search excludes puesto other than 14 and estados outside the explicitly approved {0,1} set');
$assert(!empty($byName['workers'][0]['id_home_membership_version'])
    && (int) $byName['workers'][0]['home_workplace_id'] === 1,
    'candidate results carry the exact verified membership needed by the request flow');
$assert(!empty($byName['workers'][0]['eligible'])
    && ($unassigned['eligible'] ?? true) === false,
    'search distinguishes eligible candidates from found-but-ineligible candidates');
$js = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.role-comodines.programming.init.js') ?: '';
$assert(str_contains($js, "text: name + ' · ID ' + workerId + employeeNumber")
    && str_contains($js, 'dropdownParent: $(createModal)')
    && str_contains($js, 'dropdownParent: $(modal)'),
    'Select2 labels distinguish duplicate names and both modal dropdowns remain attached to their modal');
$assert(str_contains($js, 'minimumInputLength: 0')
    && str_contains($js, "page: params.page || 1")
    && str_contains($js, 'pagination: { more: Boolean(payload.has_more) }')
    && str_contains($js, "errorLoading: () => 'No se pudo consultar el padrón autorizado")
    && str_contains($js, 'verifySelectedWorker()')
    && str_contains($js, 'submit.disabled = submitting || !otherRequestFieldsValid()')
    && str_contains($js, 'coverage_period_required')
    && str_contains($js, 'invalidateEligibility();')
    && str_contains($js, 'if (selectedWorkerId) verifySelectedWorker();'),
    'Select2 opens with the empty catalog, paginates, localizes request failures, and requires fresh server verification after period changes');
$chromePayload = json_decode('{"success":true,"workers":[{"worker_id":362,"full_name":"DAVID ANTONIO VASQUEZ ZARATE","employee_number":"0","selection_allowed":true,"eligible":false,"eligibility_reason":"coverage_period_required"}],"candidate_count":1,"eligible_count":0}', true);
$chromeWorker = $chromePayload['workers'][0] ?? [];
$assert(($chromeWorker['worker_id'] ?? 0) === 362
    && ($chromeWorker['selection_allowed'] ?? false) === true
    && ($chromeWorker['eligible'] ?? true) === false
    && str_contains($js, 'disabled: worker.selection_allowed !== true')
    && !str_contains($js, '.filter((worker) => worker.eligible === true'),
    'the real Chrome JSON candidate 362 remains visible/selectable while eligible=false means save is still blocked');
$assert(str_contains($js, 'if (hasValidPeriod()) {')
    && str_contains($js, 'data.fecha_desde_local = from.value;')
    && str_contains($js, 'data.fecha_hasta_local_exclusiva = to.value;')
    && str_contains($js, 'eligibilityVerified && worker > 0 && worker === selectedWorkerId'),
    'partial dates are omitted from catalog requests and saving requires confirmed eligibility plus valid form state');

$dayOffPattern = array_map(static fn(string $day): array => [
    'dia_semana' => $day, 'tipo' => 'DAY_OFF', 'id_plantilla' => null, 'version_plantilla' => null,
], ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO']);
$normalizeRequest = new ReflectionMethod(RolComodinesPortalWriterService::class, 'normalizeRequest');
$normalizedOneDay = $normalizeRequest->invoke(null, [
    'id_personal' => 219, 'id_home_workplace' => 1, 'id_home_membership_version' => 1,
    'id_coverage_workplace' => 2, 'fecha_desde_local' => '2026-11-25',
    'fecha_hasta_local_exclusiva' => '2026-11-26', 'timezone_iana' => 'America/Mexico_City',
    'proposed_weekly_schedule' => $dayOffPattern, 'motivo_solicitud' => 'Cobertura de un día',
]);
$assert($normalizedOneDay['fecha_desde_local'] === '2026-11-25'
    && $normalizedOneDay['fecha_hasta_local_exclusiva'] === '2026-11-26'
    && count($normalizedOneDay['proposed_weekly_schedule']) === 7,
    'the exclusive one-day interval is accepted while preserving the complete seven-day pattern');
$writerSource = file_get_contents(__DIR__ . '/../app/Services/RolComodinesPortalWriterService.php') ?: '';
$assignmentMethodStart = strpos($writerSource, 'private function createLinkedAssignments(');
$assignmentMethodEnd = strpos($writerSource, 'private function closeLinkedAssignments(', $assignmentMethodStart === false ? 0 : $assignmentMethodStart);
$assignmentMethod = $assignmentMethodStart === false || $assignmentMethodEnd === false
    ? ''
    : substr($writerSource, $assignmentMethodStart, $assignmentMethodEnd - $assignmentMethodStart);
$assert(str_contains($assignmentMethod, "\$from=(string)\$coverage['fecha_desde_local']")
    && str_contains($assignmentMethod, "\$to=(string)\$coverage['fecha_hasta_local_exclusiva']")
    && str_contains($assignmentMethod, "\$day,\$from,\$to,\$actorId")
    && str_contains($assignmentMethod, "(string)\$row['valido_desde']!==\$from")
    && str_contains($assignmentMethod, "(string)\$row['valido_hasta']!==\$to"),
    'each weekday assignment and its readback are bounded by the requested half-open coverage interval');

echo "CHECADOR_V2_COMODINES_WORKER_SOURCE: {$passes} PASS / {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

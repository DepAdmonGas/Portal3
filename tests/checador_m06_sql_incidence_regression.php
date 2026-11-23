<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorAdministrativeIncidenceSqlService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passes++ : $fails++;
};

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::schema('default');
$schema->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('localidad');
    $table->integer('numlista')->nullable();
});
$schema->create('op_rh_personal', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('nombre_completo');
    $table->string('no_colaborador')->nullable();
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
    $table->text('reason')->nullable();
    $table->integer('supersedes_id')->nullable();
});
$schema->create('op_rh_v2_incidencias_maestro', static function (Blueprint $table): void {
    $table->bigIncrements('id');
    $table->string('uuid_incidencia', 64)->unique();
    $table->integer('id_personal');
    $table->integer('id_estacion')->nullable();
    $table->integer('id_localidad')->nullable();
    $table->string('tipo_incidencia', 32);
    $table->string('alcance_incidencia', 32)->default('WORKER');
    $table->date('fecha_inicio_local');
    $table->date('fecha_fin_local');
    $table->string('estado_actual', 32);
    $table->unsignedInteger('revision_actual')->default(1);
    $table->dateTime('creado_at_utc');
    $table->dateTime('actualizado_at_utc');
});
$schema->create('op_rh_v2_incidencias_revisiones', static function (Blueprint $table): void {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('id_incidencia_maestro');
    $table->unsignedInteger('revision_numero');
    $table->string('estado_revision', 32);
    $table->text('motivo')->nullable();
    $table->string('folio_evidencia', 100)->nullable();
    $table->integer('creado_por_usuario_id');
    $table->integer('aprobado_por_usuario_id')->nullable();
    $table->dateTime('creado_at_utc');
    $table->unique(['id_incidencia_maestro', 'revision_numero']);
});

Capsule::table('op_rh_localidades')->insert([
    ['id' => 1, 'localidad' => 'Interlomas', 'numlista' => 1],
    ['id' => 2, 'localidad' => 'Palo Solo', 'numlista' => 2],
]);
Capsule::table('op_rh_personal')->insert([
    ['id' => 23, 'nombre_completo' => 'Trabajador Uno', 'no_colaborador' => 'A23', 'estado' => 1],
    ['id' => 41, 'nombre_completo' => 'Trabajador Dos', 'no_colaborador' => 'B41', 'estado' => 1],
]);
$actor = new Usuario();
$actor->id = 7;
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-11-20 18:00:00', new DateTimeZone('UTC'));
$policy = static fn(int $workplaceId, string $date): array => ['ok' => true, 'data' => [
    'status' => 'VERSIONED',
    'timezone_id' => 'America/Mexico_City',
    'policy' => ['incidence_deadline_days' => 7],
]];
$service = new ChecadorAdministrativeIncidenceSqlService(
    authorizeRead: static fn(Usuario $user, int $workplace): bool => (int) $user->id === 7 && $workplace === 1,
    authorizeCreate: static fn(Usuario $user, int $workplace): bool => (int) $user->id === 7 && $workplace === 1,
    workerCoversPeriod: static fn(int $worker, int $workplace, string $start, string $end, string $timezone): bool => $worker === 23 && $workplace === 1,
    readPolicy: $policy,
    schemaPreflight: static fn(): bool => true,
    clock: $clock,
    authorizeRevision: static fn(Usuario $user, int $workplace): bool => (int) $user->id === 7 && $workplace === 1,
    authorizeLifecycle: static fn(Usuario $user, int $workplace, string $action): bool => (int) $user->id === 7 && $workplace === 1
        && in_array($action, ['resolve', 'authorize', 'reject', 'revoke'], true)
);

$assert($service->writeReadiness()['ready'] === true, 'isolated test writer can exercise the reviewed preflight path');
$closedByDefault = (new ChecadorAdministrativeIncidenceSqlService())->writeReadiness();
$assert(($closedByDefault['ready'] ?? true) === false
    && ($closedByDefault['reason'] ?? '') === 'schema_preflight_failed',
    'writer readiness depends on the real SQL schema preflight, not a separate API or environment flag');
$schemaFailure = new ChecadorAdministrativeIncidenceSqlService(
    schemaPreflight: static fn(): bool => false
);
$assert(($schemaFailure->writeReadiness()['reason'] ?? '') === 'schema_preflight_failed',
    'required schema preflight still blocks an incomplete destination');

$uuid = ChecadorAdministrativeIncidenceSqlService::uuidV5ForIdempotencyKey('m06-test-retry-01');
$assert($uuid === ChecadorAdministrativeIncidenceSqlService::uuidV5ForIdempotencyKey('m06-test-retry-01')
    && $uuid !== ChecadorAdministrativeIncidenceSqlService::uuidV5ForIdempotencyKey('m06-test-retry-02')
    && preg_match('/\A[0-9a-f-]{36}\z/', $uuid) === 1,
    'UUIDv5 identity is stable for a retry and differs for a new idempotency key');
$assert(ChecadorAdministrativeIncidenceSqlService::CATEGORIES === [
    'VACATION', 'PERMISSION', 'DISABILITY', 'AUTHORIZED_LATENESS', 'AUTHORIZED_EARLY_DEPARTURE', 'OFFICIAL_MISSION',
], 'only the six canonical categories are accepted');

$one = $service->create($actor, 1, 23, 'VACATION', '2026-11-19', '2026-11-19', 'Solicitud de vacaciones', 'FOLIO-23', 'm06-test-retry-01');
$assert(($one['ok'] ?? false) === true
    && ($one['data']['incidence']['current_status'] ?? '') === 'PENDING_APPROVAL'
    && ($one['data']['incidence']['workplace_id'] ?? 0) === 1
    && ($one['data']['incidence']['revisions'][0]['revision_number'] ?? 0) === 1
    && ($one['data']['incidence']['revisions'][0]['created_by_user_id'] ?? 0) === 7,
    'isolated transaction inserts master plus pending revision and confirms canonical readback');
$replay = $service->create($actor, 1, 23, 'VACATION', '2026-11-19', '2026-11-19', 'Solicitud de vacaciones', 'FOLIO-23', 'm06-test-retry-01');
$assert(($replay['ok'] ?? false) === true && ($replay['data']['replayed'] ?? false) === true
    && Capsule::table('op_rh_v2_incidencias_maestro')->count() === 1
    && Capsule::table('op_rh_v2_incidencias_revisiones')->count() === 1,
    'identical retry returns the original record without duplicate master or revision');
$conflict = $service->create($actor, 1, 23, 'VACATION', '2026-11-19', '2026-11-19', 'Contenido distinto', 'FOLIO-23', 'm06-test-retry-01');
$assert(($conflict['error'] ?? '') === 'idempotency_conflict'
    && Capsule::table('op_rh_v2_incidencias_maestro')->count() === 1,
    'replay with the same UUID but different original content is rejected without mutation');
$pdo = Capsule::connection('default')->getPdo();
$pdo->exec("CREATE TRIGGER m06_force_revision_failure BEFORE INSERT ON op_rh_v2_incidencias_revisiones BEGIN SELECT RAISE(ABORT, 'isolated failure'); END");
$failedPair = $service->create($actor, 1, 23, 'PERMISSION', '2026-11-20', '2026-11-20', 'Prueba rollback', null, 'm06-test-rollback');
$pdo->exec('DROP TRIGGER m06_force_revision_failure');
$assert(($failedPair['ok'] ?? false) === false
    && Capsule::table('op_rh_v2_incidencias_maestro')->count() === 1
    && Capsule::table('op_rh_v2_incidencias_revisiones')->count() === 1,
    'a failed initial revision rolls back the inserted master in the isolated transaction');
$wrongScope = $service->create($actor, 2, 41, 'PERMISSION', '2026-11-19', '2026-11-19', 'Permiso', null, 'm06-test-other-scope');
$assert(($wrongScope['error'] ?? '') === 'permission_denied'
    && Capsule::table('op_rh_v2_incidencias_maestro')->count() === 1,
    'a locality outside the actor allowlist is rejected before SQL writes');
$invalidCategory = $service->create($actor, 1, 23, 'FALTA', '2026-11-19', '2026-11-19', 'Falta', null, 'm06-test-falta');
$assert(($invalidCategory['error'] ?? '') === 'invalid_request', 'FALTA is not accepted as an incidence category');

$read = $service->listForWorkplace($actor, 1, [], 1, 25);
$otherRead = $service->listForWorkplace(new Usuario(), 2, [], 1, 25);
$assert(($read['ok'] ?? false) === true && count($read['data']['items'] ?? []) === 1
    && ($read['data']['items'][0]['revisions'][0]['reason'] ?? '') === 'Solicitud de vacaciones'
    && ($read['data']['pagination']['records_total'] ?? 0) === 1,
    'SQL list returns the selected locality and complete revision history');
$searchRead = $service->listForWorkplace($actor, 1, ['search' => 'Trabajador Uno'], 1, 10);
$searchCategory = $service->listForWorkplace($actor, 1, ['search' => 'Vacaciones'], 1, 10);
$searchStatus = $service->listForWorkplace($actor, 1, ['search' => 'Pendiente de autorización'], 1, 10);
$searchMiss = $service->listForWorkplace($actor, 1, ['search' => 'No existe'], 1, 10);
$rawAxiosEmpty = $service->listForWorkplace($actor, 1, [
    'worker_id' => '', 'category' => '', 'status' => '', 'start_date' => '', 'end_date' => '', 'search' => '',
], 1, 25);
$rawAxiosWorker = $service->listForWorkplace($actor, 1, ['worker_id' => '1'], 1, 25);
$invalidSort = $service->listForWorkplace($actor, 1, [], 1, 10, 'uuid_incidencia', 'desc');
$assert(($searchRead['ok'] ?? false) === true && count($searchRead['data']['items'] ?? []) === 1
    && ($searchRead['data']['pagination']['records_total'] ?? 0) === 1
    && ($searchRead['data']['pagination']['total_records'] ?? 0) === 1
    && ($searchCategory['data']['pagination']['total_records'] ?? 0) === 1
    && ($searchStatus['data']['pagination']['total_records'] ?? 0) === 1
    && ($rawAxiosEmpty['ok'] ?? false) === true
    && ($rawAxiosWorker['ok'] ?? false) === true
    && ($searchMiss['ok'] ?? false) === true && ($searchMiss['data']['pagination']['records_total'] ?? 0) === 1
    && ($searchMiss['data']['pagination']['total_records'] ?? 0) === 0
    && ($invalidSort['error'] ?? '') === 'invalid_request',
    'SQL global search is parameterized and returns scoped total plus filtered count for DataTables');
$m06PayloadNormalizer = new ReflectionMethod(\App\Controllers\ChecadorController::class, 'normalizeM06IncidenceDataPayload');
$m06PayloadNormalizer->setAccessible(true);
$baseDataTablePayload = [
    'draw' => 1, 'workplace_id' => 1, 'start' => 0, 'length' => 25,
    'worker_id' => '', 'category' => '', 'status' => '', 'start_date' => '', 'end_date' => '',
    'search' => ['value' => '', 'regex' => false], 'order' => [['column' => 4, 'dir' => 'desc']],
];
$payloadCases = [
    'initial' => $baseDataTablePayload,
    'worker string' => array_replace($baseDataTablePayload, ['worker_id' => '1']),
    'all category' => array_replace($baseDataTablePayload, ['category' => '']),
    'all status' => array_replace($baseDataTablePayload, ['status' => '']),
    'date range' => array_replace($baseDataTablePayload, ['start_date' => '2026-11-01', 'end_date' => '2026-11-30']),
    'combined' => array_replace($baseDataTablePayload, [
        'worker_id' => '23', 'category' => 'VACATION', 'status' => 'PENDING_APPROVAL',
        'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
    ]),
    'clear' => $baseDataTablePayload,
    'page and order' => array_replace($baseDataTablePayload, [
        'start' => 25, 'order' => [['column' => 2, 'dir' => 'asc']],
    ]),
];
$payloadCasesOk = true;
foreach ($payloadCases as $payload) {
    $normalizedPayload = $m06PayloadNormalizer->invoke(null, $payload);
    if (!($normalizedPayload['ok'] ?? false)) { $payloadCasesOk = false; break; }
    $contractRead = $service->listForWorkplace(
        $actor,
        (int) $normalizedPayload['workplace_id'],
        $normalizedPayload['filters'],
        (int) $normalizedPayload['page'],
        (int) $normalizedPayload['length'],
        $normalizedPayload['sort_by'],
        $normalizedPayload['sort_direction']
    );
    if (!($contractRead['ok'] ?? false)) { $payloadCasesOk = false; break; }
}
$normalizedBlankPayload = $m06PayloadNormalizer->invoke(null, $baseDataTablePayload);
$normalizedWorkerString = $m06PayloadNormalizer->invoke(null, $payloadCases['worker string']);
$normalizedCombined = $m06PayloadNormalizer->invoke(null, $payloadCases['combined']);
$normalizedPageOrder = $m06PayloadNormalizer->invoke(null, $payloadCases['page and order']);
$combinedSqlRead = $normalizedCombined['ok'] ?? false
    ? $service->listForWorkplace(
        $actor, (int) $normalizedCombined['workplace_id'], $normalizedCombined['filters'],
        (int) $normalizedCombined['page'], (int) $normalizedCombined['length'],
        $normalizedCombined['sort_by'], $normalizedCombined['sort_direction']
    )
    : ['ok' => false];
$invalidWorkerPayload = $m06PayloadNormalizer->invoke(null, array_replace($baseDataTablePayload, ['worker_id' => 'worker-x']));
$invalidDatePayload = $m06PayloadNormalizer->invoke(null, array_replace($baseDataTablePayload, ['start_date' => '2026-02-30']));
$assert($payloadCasesOk
    && array_key_exists('category', $normalizedBlankPayload['filters'] ?? []) && $normalizedBlankPayload['filters']['category'] === null
    && array_key_exists('status', $normalizedBlankPayload['filters'] ?? []) && $normalizedBlankPayload['filters']['status'] === null
    && array_key_exists('start_date', $normalizedBlankPayload['filters'] ?? []) && $normalizedBlankPayload['filters']['start_date'] === null
    && array_key_exists('end_date', $normalizedBlankPayload['filters'] ?? []) && $normalizedBlankPayload['filters']['end_date'] === null
    && ($normalizedWorkerString['filters']['worker_id'] ?? null) === 1
    && ($normalizedCombined['filters']['worker_id'] ?? null) === 23
    && ($normalizedCombined['sort_by'] ?? '') === 'created_at'
    && ($normalizedPageOrder['page'] ?? 0) === 2
    && ($normalizedPageOrder['sort_by'] ?? '') === 'start_date'
    && ($normalizedPageOrder['sort_direction'] ?? '') === 'asc'
    && ($combinedSqlRead['ok'] ?? false) === true
    && count($combinedSqlRead['data']['items'] ?? []) === 1
    && ($combinedSqlRead['data']['items'][0]['worker_id'] ?? 0) === 23
    && ($invalidWorkerPayload['error'] ?? '') === 'invalid_filters'
    && ($invalidDatePayload['ok'] ?? false) === true
    && ($service->listForWorkplace($actor, 1, $invalidDatePayload['filters'], 1, 25)['error'] ?? '') === 'invalid_filter',
    'Axios/DataTables request contract normalizes empty optional values and string IDs, reaches SQL reader for initial/filter/page/sort cases, and still rejects invalid worker/date values');
$assert(($otherRead['error'] ?? '') === 'permission_denied', 'cross-locality reads are denied server-side');
$detail = $service->detail($actor, 1, $uuid);
$assert(($detail['ok'] ?? false) === true && count($detail['data']['revisions'] ?? []) === 1
    && ($detail['data']['revisions'][0]['evidence_reference'] ?? '') === 'FOLIO-23',
    'detail read returns persisted evidence and revision history after reload-equivalent query');

$pdo->exec("CREATE TRIGGER m06_revision_no_update BEFORE UPDATE ON op_rh_v2_incidencias_revisiones BEGIN SELECT RAISE(ABORT, 'revision append-only'); END");
$pdo->exec("CREATE TRIGGER m06_revision_no_delete BEFORE DELETE ON op_rh_v2_incidencias_revisiones BEGIN SELECT RAISE(ABORT, 'revision append-only'); END");
$revision = $service->appendRevision($actor, 1, $uuid, 1, 'Ajuste de periodo solicitado', 'FOLIO-REV-1');
$assert(($revision['ok'] ?? false) === true
    && ($revision['data']['incidence']['current_revision'] ?? 0) === 2
    && ($revision['data']['incidence']['revisions'][1]['reason'] ?? '') === 'Ajuste de periodo solicitado',
    'revision operation appends the next audit row, updates only the master projection, and confirms readback');
$revisionReplay = $service->appendRevision($actor, 1, $uuid, 1, 'Ajuste de periodo solicitado', 'FOLIO-REV-1');
$revisionConflict = $service->appendRevision($actor, 1, $uuid, 1, 'Contenido diferente', 'FOLIO-REV-1');
$assert(($revisionReplay['ok'] ?? false) === true && ($revisionReplay['data']['replayed'] ?? false) === true
    && Capsule::table('op_rh_v2_incidencias_revisiones')->count() === 2,
    'semantic retry of the same expected revision returns the existing persisted revision');
$assert(($revisionConflict['error'] ?? '') === 'stale_revision_conflict'
    && Capsule::table('op_rh_v2_incidencias_revisiones')->count() === 2,
    'a stale expected revision with a different payload is rejected without mutation');

$resolved = $service->transition($actor, 1, $uuid, 'resolve', 2, 'Revisión de resolución', 'FOLIO-RES-1');
$resolveReplay = $service->transition($actor, 1, $uuid, 'resolve', 2, 'Revisión de resolución', 'FOLIO-RES-1');
$authorized = $service->transition($actor, 1, $uuid, 'authorize', 3, 'Autorización revisada', 'FOLIO-AUTH-1');
$rejected = $service->transition($actor, 1, $uuid, 'reject', 4, 'Rechazo documentado', 'FOLIO-REJ-1');
$revoked = $service->transition($actor, 1, $uuid, 'revoke', 5, 'Revocación documentada', 'FOLIO-REV-2');
$assert(($resolved['ok'] ?? false) === true
    && ($resolved['data']['incidence']['current_status'] ?? '') === 'RESOLVED_PENDING_AUTHORIZATION'
    && ($resolveReplay['data']['replayed'] ?? false) === true,
    'resolve appends its pending-authorization state and semantically replays without duplication');
$assert(($authorized['ok'] ?? false) === true
    && ($authorized['data']['incidence']['current_status'] ?? '') === 'ACTIVE'
    && ($authorized['data']['incidence']['revisions'][3]['approved_by_user_id'] ?? 0) === 7,
    'authorize persists ACTIVE with the authenticated actor in the approval audit field');
$assert(($rejected['ok'] ?? false) === true
    && ($rejected['data']['incidence']['current_status'] ?? '') === 'REJECTED'
    && ($revoked['ok'] ?? false) === true
    && ($revoked['data']['incidence']['current_status'] ?? '') === 'CANCELLED',
    'reject and revoke append their canonical terminal statuses and read back from SQL');
$beforeInvalidTransition = Capsule::table('op_rh_v2_incidencias_revisiones')->count();
$invalidTransition = $service->transition($actor, 1, $uuid, 'authorize', 6, 'No debe autorizarse', null);
$assert(($invalidTransition['error'] ?? '') === 'invalid_lifecycle_transition'
    && Capsule::table('op_rh_v2_incidencias_revisiones')->count() === $beforeInvalidTransition,
    'a lifecycle transition from CANCELLED is rejected without adding a revision');
$noRevisionPermission = new ChecadorAdministrativeIncidenceSqlService(
    authorizeRead: static fn(Usuario $user, int $workplace): bool => true,
    schemaPreflight: static fn(): bool => true,
    clock: $clock,
    authorizeRevision: static fn(Usuario $user, int $workplace): bool => false
);
$deniedRevision = $noRevisionPermission->appendRevision($actor, 1, $uuid, 6, 'No autorizado');
$assert(($deniedRevision['error'] ?? '') === 'permission_denied'
    && Capsule::table('op_rh_v2_incidencias_revisiones')->count() === $beforeInvalidTransition,
    'revision ACL denial blocks before persistence');
$closedLifecycle = new ChecadorAdministrativeIncidenceSqlService(
    authorizeLifecycle: static fn(Usuario $user, int $workplace, string $action): bool => true,
    schemaPreflight: static fn(): bool => false
);
$closedAction = $closedLifecycle->transition($actor, 1, $uuid, 'revoke', 6, 'Corte API aún no acreditado');
$assert(($closedAction['error'] ?? '') === 'schema_preflight_failed'
    && Capsule::table('op_rh_v2_incidencias_revisiones')->count() === $beforeInvalidTransition,
    'an incomplete SQL schema still blocks the lifecycle operation without mutating history');
$pdo->exec('DROP TRIGGER m06_revision_no_update');
$pdo->exec('DROP TRIGGER m06_revision_no_delete');

$controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '';
$routeSource = file_get_contents(dirname(__DIR__) . '/routes/web.php') ?: '';
$view = file_get_contents(dirname(__DIR__) . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php') ?: '';
$incidenceItems = $read['data']['items'] ?? [];
$incidenceFilters = [];
$incidencePagination = $read['data']['pagination'] ?? [];
$incidenceWorkers = [];
$incidenceCategories = [
    'VACATION' => 'Vacaciones',
    'PERMISSION' => 'Permiso',
    'DISABILITY' => 'Incapacidad',
    'AUTHORIZED_LATENESS' => 'Retardo autorizado',
    'AUTHORIZED_EARLY_DEPARTURE' => 'Salida anticipada autorizada',
    'OFFICIAL_MISSION' => 'Misión oficial',
];
$incidenceFeedback = null;
$incidenceFormValues = [];
$incidenceState = 'ready';
$incidenceReadError = '';
$selectedWorkplaceId = 1;
$selectedWorkplaceName = 'Interlomas';
$incidenceCanCreate = false;
$incidenceCanRevise = true;
$incidenceLifecyclePermissions = ['resolve' => true, 'authorize' => true, 'reject' => true, 'revoke' => true];
$incidenceWriterReady = false;
$incidenceRosterAvailable = false;
$_SESSION = [];
ob_start();
require dirname(__DIR__) . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php';
$renderedIncidenceView = (string) ob_get_clean();
$detailModalPosition = strpos($renderedIncidenceView, 'id="m06-incidence-detail-modal"');
$tableEndPosition = strpos($renderedIncidenceView, '</table>');
$assert($detailModalPosition !== false && $tableEndPosition !== false && $detailModalPosition > $tableEndPosition
    && str_contains($renderedIncidenceView, 'data-m06-history-list')
    && str_contains($renderedIncidenceView, 'class="tab-pane fade show active"')
    && str_contains($renderedIncidenceView, 'class="tab-pane fade"'),
    'shared detail/history modal is outside DataTables and retains valid Bootstrap tab panes');
$moduleJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.incidences.datatable.init.js') ?: '';
$assert(str_contains($moduleJs, 'showIncidence(row,')
    && str_contains($moduleJs, 'renderHistory(row)')
    && str_contains($moduleJs, 'Solicitud de vacaciones') === false
    && str_contains($moduleJs, 'revision.created_by_user_id')
    && str_contains($moduleJs, 'row.uuid_incidencia'),
    'detail and persisted revision data are populated from the authorized SQL response after each asynchronous draw');
$assert(str_contains($renderedIncidenceView, '<div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"')
    && str_contains($renderedIncidenceView, '>Cerrar</button></div>'),
    'shared detail/history modal uses the right-aligned Portal3 red-soft close button');
$assert(str_contains($controller, 'm06AdministrativeIncidenceSqlService()')
    && !str_contains($controller, '$reader->createIncidence($user')
    && !str_contains($controller, '$reader->readApplicableIncidences($user, $workerId, $requestedWorkplace, $date)'),
    'M06 create/list controller flow no longer calls API or requires an attendance snapshot');
$closedUntilVerified = new ChecadorAdministrativeIncidenceSqlService(
    schemaPreflight: static fn(): bool => true
);
$readiness = $closedUntilVerified->writeReadiness();
$serviceSource = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAdministrativeIncidenceSqlService.php') ?: '';
$cutoverReady = new ChecadorAdministrativeIncidenceSqlService(
    schemaPreflight: static fn(): bool => true
);
$cutoverReadiness = $cutoverReady->writeReadiness();
$factoryStart = strpos($controller, 'private static function m06AdministrativeIncidenceSqlService');
$factoryEnd = $factoryStart === false ? false : strpos($controller, 'private static function loadSnapshotState', $factoryStart);
$factorySource = $factoryStart !== false && $factoryEnd !== false
    ? substr($controller, $factoryStart, $factoryEnd - $factoryStart)
    : '';
$assert($factorySource !== ''
    && !str_contains($factorySource, 'Database::isLocalDevelopmentDatabaseConnection()')
    && !str_contains($factorySource, 'apiWriterCutoverVerified')
    && !str_contains($factorySource, 'historyProtectionVerified:')
    && ($closedByDefault['ready'] ?? true) === false
    && ($closedByDefault['reason'] ?? '') === 'schema_preflight_failed',
    'M06 factory uses the existing SQL writer without Fedora or API cutover gates');
$preflightStart = strpos($serviceSource, 'public function mariadbSchemaReady(');
$preflightEnd = strpos($serviceSource, 'private function policyForPeriod(', $preflightStart ?: 0);
$preflightSource = $preflightStart !== false && $preflightEnd !== false
    ? substr($serviceSource, $preflightStart, $preflightEnd - $preflightStart)
    : '';
$assert(($cutoverReadiness['ready'] ?? false) === true
    && $preflightSource !== ''
    && str_contains($preflightSource, 'information_schema.STATISTICS')
    && str_contains($preflightSource, 'SUB_PART')
    && !str_contains($preflightSource, 'information_schema.KEY_COLUMN_USAGE')
    && !str_contains($preflightSource, 'uk_v2_incidencia_uuid')
    && !str_contains($preflightSource, 'fk_v2_incid_maestro_localidad')
    && !str_contains($preflightSource, 'hasEffectiveTablePrivilege'),
    'M06 writer validates unique keys without requiring physical FK metadata or Fedora/API cutover gates');
$assert(str_contains($view, 'm06-incidence-search-modal')
    && str_contains($view, 'm06-incidence-create-modal')
    && str_contains($view, 'data-checador-m06-incidences')
    && str_contains($moduleJs, 'Ver historial')
    && !str_contains($view, 'INCAPACITY') && !str_contains($view, 'JUSTIFICATION'),
    'Portal3-native search, new-incidence modal, table, detail/history and canonical categories are rendered');
$assert(str_contains($moduleJs, "lifecycle('revision'")
    && str_contains($moduleJs, "lifecycle('resolve'")
    && str_contains($moduleJs, "lifecycle('authorize'")
    && str_contains($moduleJs, "lifecycle('reject'")
    && str_contains($moduleJs, "lifecycle('revoke'")
    && str_contains($renderedIncidenceView, 'id="m06-incidence-action-modal"')
    && str_contains($renderedIncidenceView, 'data-m06-writer-ready="0"'),
    'existing M06 UI exposes ACL-controlled revision/lifecycle forms and keeps submit disabled when server readiness is false');
$assert(str_contains($view, '<i class="ti ti-search me-1"')
    && str_contains($view, '<i class="ti ti-plus me-1"')
    && str_contains($view, '<i class="ti ti-eye me-1"')
    && str_contains($view, '<i class="ti ti-history me-1"')
    && str_contains($view, '<i class="ti ti-x me-1"'),
    'M06 dropdown actions and modal close control include the requested Tabler icons');
$searchOption = strpos($view, 'Buscar incidencias</button>');
$createOption = strpos($view, 'Nueva incidencia</button>');
$createPermissionGuard = strpos($view, "if ((\$incidenceCanCreate ?? false) === true):", $searchOption ?: 0);
$assert(str_contains($view, 'id="m06-incidence-options" data-bs-toggle="dropdown"')
    && str_contains($view, 'data-bs-target="#m06-incidence-search-modal"')
    && str_contains($view, 'data-bs-target="#m06-incidence-create-modal"')
    && $searchOption !== false && $createOption !== false && $createPermissionGuard !== false && $createPermissionGuard < $createOption,
    'Options dropdown targets both Bootstrap modals and keeps the create option inside the server permission guard');
$assert(str_contains($moduleJs, 'No hay incidencias registradas para los filtros seleccionados')
    && str_contains($moduleJs, 'No fue posible consultar las incidencias')
    && str_contains($moduleJs, "setListState(message + (preserveRows")
    && str_contains($moduleJs, 'lastSuccessfulResponse ||')
    && !str_contains($view, 'alert alert-light border mb-0')
    && str_contains($view, 'if ($feedbackMessage !== null):')
    && substr_count($view, '<?= $esc($writerBlockMessage) ?>') === 1,
    'empty state is legible and exact, SQL failure remains distinct, and the blocked-write notice is not duplicated');
$assert(str_contains($view, 'card-header bg-primary text-white')
    && str_contains($view, 'id="incidence-workspace-heading">Incidencias del personal</h2>')
    && str_contains($view, 'ti ti-alert-triangle fs-5')
    && str_contains($view, 'btn btn-light dropdown-toggle text-dark')
    && str_contains($view, 'modal-header modal-colored-header bg-primary text-white')
    && str_contains($view, 'ti ti-plus me-1')
    && str_contains($view, 'btn btn-outline-danger')
    && str_contains($view, 'btn btn-success')
    && str_contains($view, 'btn btn-primary'),
    'card, icon dropdown, modal headers and modal footer buttons reuse the referenced Portal3 components');
$select2MethodStart = strpos($controller, 'public function incidences(): void');
$select2MethodEnd = strpos($controller, 'public function adjustments(): void', $select2MethodStart ?: 0);
$incidencesMethod = $select2MethodStart !== false && $select2MethodEnd !== false
    ? substr($controller, $select2MethodStart, $select2MethodEnd - $select2MethodStart)
    : '';
$moduleJs = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.incidences.datatable.init.js') ?: '';
$assert(str_contains($view, 'data-m06-worker-select="create"')
    && str_contains($view, 'data-m06-worker-select="filter"')
    && str_contains($view, '<option value="">Todos</option>')
    && !str_contains($view, "require __DIR__ . '/_workspace-context.php'")
    && str_contains($incidencesMethod, '/assets/libs/select2/dist/js/select2.full.min.js')
    && str_contains($incidencesMethod, '/assets/libs/select2/dist/js/i18n/es.js')
    && str_contains($incidencesMethod, '/assets/css/select2-modal.css'),
    'both authorized worker selectors use the existing Select2 assets, keep Todos, and omit only the M06 context bar');
$assert(str_contains($moduleJs, "width: '100%'")
    && str_contains($moduleJs, "language: 'es'")
    && str_contains($moduleJs, 'dropdownParent: $(modal)')
    && str_contains($moduleJs, "addEventListener('shown.bs.modal'")
    && str_contains($moduleJs, "addEventListener('hidden.bs.modal'")
    && str_contains($moduleJs, "select2('destroy')")
    && str_contains($moduleJs, 'select2-hidden-accessible'),
    'Select2 initializes once inside each modal on open and is destroyed before reopening');
$assert(str_contains($view, 'x-data="m06IncidenceFilters"')
    && str_contains($view, 'method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/data"')
    && str_contains($view, 'name="_csrf_token"')
    && str_contains($view, 'x-model="filters.worker_id"')
    && str_contains($view, '@submit.prevent="buscar"')
    && str_contains($moduleJs, 'axios.post(table.dataset.m06DataUrl')
    && str_contains($moduleJs, "'X-CSRF-TOKEN'")
    && str_contains($moduleJs, 'serverSide: true')
    && str_contains($moduleJs, 'order: request.order || []')
    && str_contains($moduleJs, 'requestId !== latestRequest')
    && str_contains($moduleJs, 'lastSuccessfulResponse')
    && str_contains($moduleJs, 'setListState(message + (preserveRows')
    && !str_contains($view, 'method="get" action="/departamento-operativo/recursos-humanos/checador/incidencias"')
    && !str_contains($view, '?workplace_id='),
    'M06 filters use Alpine state and Axios JSON POST with CSRF, server-side DataTables, stale-response protection, and no query-string filters');
$incidenceGetStart = strpos($controller, 'public function incidences(): void');
$incidenceDataStart = strpos($controller, 'public function incidencesData(): void', $incidenceGetStart ?: 0);
$incidenceCreateStart = strpos($controller, 'public function createIncidence(): void');
$incidenceCreateEnd = $incidenceCreateStart === false ? false : strpos($controller, 'public function ', $incidenceCreateStart + 20);
$incidenceGetSource = $incidenceGetStart !== false && $incidenceDataStart !== false
    ? substr($controller, $incidenceGetStart, $incidenceDataStart - $incidenceGetStart)
    : '';
$incidenceCreateSource = $incidenceCreateStart !== false && $incidenceCreateEnd !== false
    ? substr($controller, $incidenceCreateStart, $incidenceCreateEnd - $incidenceCreateStart)
    : '';
$assert(str_contains($incidenceGetSource, "if (\$query !== [])")
    && str_contains($incidenceGetSource, "self::redirect('/departamento-operativo/recursos-humanos/checador/incidencias')")
    && str_contains($incidenceCreateSource, "\$redirect = '/departamento-operativo/recursos-humanos/checador/incidencias';")
    && !str_contains($incidenceCreateSource, "'workplace_id' => \$workplaceId"),
    'legacy query strings and create redirect now settle on the clean M06 URL without workplace/filter query parameters');
$assert(str_contains($controller, 'public function incidencesData(): void')
    && str_contains($controller, 'normalizeM06IncidenceDataPayload($payload)')
    && str_contains($controller, 'authorizeIncidenceRead($workplaceId)')
    && str_contains($controller, 'selected_station_context_mismatch')
    && str_contains($routeSource, "'/recursos-humanos/checador/incidencias/data'")
    && str_contains($routeSource, "Route::auth(['ChecadorController', 'incidencesData'])"),
    'async M06 read route rechecks authenticated read ACL and selected session-locality context');
$assert(str_contains($routeSource, "'/recursos-humanos/checador/incidencias/crear'")
    && str_contains($routeSource, "'/recursos-humanos/checador/incidencias/{uuidIncidencia:[0-9a-fA-F-]{36}}/revisiones'")
    && str_contains($routeSource, "/resolver'") && str_contains($routeSource, "/autorizar'")
    && str_contains($routeSource, "/rechazar'") && str_contains($routeSource, "/revocar'"),
    'six authenticated Portal3 write routes cover creation, revision, resolve, authorize, reject, and revoke');
$lifecycleStart = strpos($controller, 'private function handleLifecycle(string $uuidIncidencia, string $action): void');
$lifecycleEnd = $lifecycleStart === false ? false : strpos($controller, 'private static function preparePunchReportRows', $lifecycleStart);
$lifecycleControllerSource = $lifecycleStart !== false && $lifecycleEnd !== false
    ? substr($controller, $lifecycleStart, $lifecycleEnd - $lifecycleStart)
    : '';
$assert($lifecycleControllerSource !== ''
    && str_contains($controller, '->appendRevision(')
    && str_contains($lifecycleControllerSource, '->transition(')
    && !str_contains($lifecycleControllerSource, 'ChecadorDailyReadService')
    && !str_contains($lifecycleControllerSource, 'lifecycleAction('),
    'M06 revision and lifecycle controller paths use the Portal3 SQL writer without HTTP API calls');
$access = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAccessService.php') ?: '';
$readAuthStart = strpos($access, 'public static function authorizeIncidenceRead(');
$revisionAuthStart = strpos($access, 'public static function authorizeIncidenceRevision(');
$evaluationAuthStart = strpos($access, 'public static function authorizeEvaluation(');
$readCreateSource = $readAuthStart !== false && $evaluationAuthStart !== false
    ? substr($access, $readAuthStart, $evaluationAuthStart - $readAuthStart)
    : '';
$assert(str_contains($readCreateSource, '[self::ENCARGADO_PUESTO_ID, self::DIRECCION_OPERACIONES_PUESTO_ID]')
    && str_contains($readCreateSource, 'self::ENCARGADO_PUESTO_ID')
    && !str_contains($readCreateSource, 'ModuloDptoOperativoService::getPermiso')
    && str_contains($readCreateSource, "'capability' => 'M06_CREATE'")
    && $revisionAuthStart !== false
    && str_contains(substr($access, $revisionAuthStart, $evaluationAuthStart - $revisionAuthStart), 'return self::authorizeIncidenceCreate('),
    'M06 read is Checador-scope plus active roles 6/13; create and revision are Encargado-only without physical M06 ACL rows');
$assert(str_contains($access, 'ModuloDptoOperativoService::getPermiso((int) $user->id, $namespace)')
    && str_contains($access, 'authorizeEvaluation(')
    && str_contains($access, 'authorizeFalta('),
    'resolution/approval, evaluation and FALTA retain their separate authorization checks');
$assert(str_contains($controller, 'CsrfToken::validate($csrf)')
    && str_contains($controller, 'authorizeIncidenceCreate($workplaceId)')
    && str_contains($controller, '->create('),
    'POST validates CSRF, rechecks locality permission, then delegates to the SQL writer');
$listReadStart = strpos($serviceSource, 'public function listForWorkplace(');
$detailReadStart = strpos($serviceSource, 'public function detail(', $listReadStart ?: 0);
$listReadSource = $listReadStart !== false && $detailReadStart !== false
    ? substr($serviceSource, $listReadStart, $detailReadStart - $listReadStart)
    : '';
$assert(str_contains($listReadSource, "'exception_class' => \$exception::class")
    && str_contains($listReadSource, "'sqlstate' => \$sqlState")
    && str_contains($listReadSource, "'stage' => \$readStage")
    && str_contains($listReadSource, "Logger::error('M06 incidence SQL list read failed'")
    && !str_contains($listReadSource, 'getSql()')
    && !str_contains($listReadSource, 'toSql()'),
    'M06 list SQL failure logs exception class, SQLSTATE and fixed query stage without SQL or bindings');
$assert(str_contains($serviceSource, "connection('default')")
    && str_contains($serviceSource, '->transaction(')
    && str_contains($serviceSource, "'id_localidad' => \$workplaceId")
    && str_contains($serviceSource, "nullableMap['op_rh_v2_incidencias_maestro.id_estacion']")
    && str_contains($serviceSource, "nullableMap['op_rh_v2_incidencias_revisiones.aprobado_por_usuario_id']")
    && str_contains($serviceSource, 'readback_unconfirmed'),
    'writer uses default SQL connection, transaction, canonical locality and readback');

printf("\nM06_SQL_INCIDENCE_ISOLATED: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

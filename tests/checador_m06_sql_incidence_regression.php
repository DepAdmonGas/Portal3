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
    apiWriterCutoverVerified: true,
    clock: $clock,
    authorizeRevision: static fn(Usuario $user, int $workplace): bool => (int) $user->id === 7 && $workplace === 1,
    authorizeLifecycle: static fn(Usuario $user, int $workplace, string $action): bool => (int) $user->id === 7 && $workplace === 1
        && in_array($action, ['resolve', 'authorize', 'reject', 'revoke'], true)
);

$assert($service->writeReadiness()['ready'] === true, 'isolated test writer can exercise the reviewed preflight path');
$closedByDefault = (new ChecadorAdministrativeIncidenceSqlService())->writeReadiness();
$assert(($closedByDefault['ready'] ?? true) === false
    && ($closedByDefault['reason'] ?? '') === 'api_writer_cutover_unverified',
    'operational writer is closed by default until API cutover is independently verified');
$schemaFailure = new ChecadorAdministrativeIncidenceSqlService(
    schemaPreflight: static fn(): bool => false,
    apiWriterCutoverVerified: true
);
$assert(($schemaFailure->writeReadiness()['reason'] ?? '') === 'schema_preflight_failed',
    'required schema and effective SQL-operation preflight still blocks an incomplete destination');

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
    && ($read['data']['items'][0]['revisions'][0]['reason'] ?? '') === 'Solicitud de vacaciones',
    'SQL list returns the selected locality and complete revision history');
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
    apiWriterCutoverVerified: true,
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
    schemaPreflight: static fn(): bool => true
);
$closedAction = $closedLifecycle->transition($actor, 1, $uuid, 'revoke', 6, 'Corte API aún no acreditado');
$assert(($closedAction['error'] ?? '') === 'api_writer_cutover_unverified'
    && Capsule::table('op_rh_v2_incidencias_revisiones')->count() === $beforeInvalidTransition,
    'all operational SQL lifecycle writes remain fail-closed until the API writer cut is verified');
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
$detailModalPosition = strpos($renderedIncidenceView, 'id="m06-incidence-detail-0"');
$tableEndPosition = strpos($renderedIncidenceView, '</table>');
$assert($detailModalPosition !== false && $tableEndPosition !== false && $detailModalPosition > $tableEndPosition
    && str_contains($renderedIncidenceView, 'data-bs-target="#m06-incidence-detail-0"')
    && str_contains($renderedIncidenceView, 'class="tab-pane fade show active"')
    && str_contains($renderedIncidenceView, 'class="tab-pane fade"'),
    'rendered detail/history modal is outside DataTables and has valid Bootstrap tab panes');
$assert(str_contains($renderedIncidenceView, 'Trabajador Uno')
    && str_contains($renderedIncidenceView, 'Vacaciones')
    && str_contains($renderedIncidenceView, 'Pendiente de autorización')
    && str_contains($renderedIncidenceView, 'Solicitud de vacaciones')
    && str_contains($renderedIncidenceView, 'FOLIO-23')
    && str_contains($renderedIncidenceView, $uuid)
    && str_contains($renderedIncidenceView, 'Revisión 1')
    && str_contains($renderedIncidenceView, 'Usuario 7'),
    'rendered detail and persisted revision show their real fixture values and references');
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
$factoryStart = strpos($controller, 'private static function m06AdministrativeIncidenceSqlService');
$factoryEnd = $factoryStart === false ? false : strpos($controller, 'private static function loadSnapshotState', $factoryStart);
$factorySource = $factoryStart !== false && $factoryEnd !== false
    ? substr($controller, $factoryStart, $factoryEnd - $factoryStart)
    : '';
$assert($factorySource !== ''
    && !str_contains($factorySource, 'Database::isLocalDevelopmentDatabaseConnection()')
    && !str_contains($factorySource, 'apiWriterCutoverVerified:')
    && !str_contains($factorySource, 'historyProtectionVerified:')
    && ($readiness['ready'] ?? true) === false
    && ($readiness['reason'] ?? '') === 'api_writer_cutover_unverified',
    'M06 writer remains fail-closed only until API cutoff is independently verified; history protection is an application rule');
$assert(str_contains($view, 'm06-incidence-search-modal')
    && str_contains($view, 'm06-incidence-create-modal')
    && str_contains($view, 'data-checador-m06-incidences')
    && str_contains($view, 'Ver historial')
    && !str_contains($view, 'INCAPACITY') && !str_contains($view, 'JUSTIFICATION'),
    'Portal3-native search, new-incidence modal, table, detail/history and canonical categories are rendered');
$assert(str_contains($renderedIncidenceView, 'data-m06-action="revision"')
    && str_contains($renderedIncidenceView, 'data-m06-action="resolve"')
    && str_contains($renderedIncidenceView, 'data-m06-action="authorize"')
    && str_contains($renderedIncidenceView, 'data-m06-action="reject"')
    && str_contains($renderedIncidenceView, 'data-m06-action="revoke"')
    && str_contains($renderedIncidenceView, 'id="m06-incidence-action-modal"')
    && str_contains($renderedIncidenceView, 'data-m06-writer-ready="0"'),
    'existing M06 UI exposes ACL-controlled revision/lifecycle forms while keeping submit disabled behind the API cutoff');
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
$assert(str_contains($view, 'No hay incidencias registradas para los filtros seleccionados')
    && str_contains($view, 'text-body-secondary text-center py-3')
    && str_contains($view, 'Ocurrió un error SQL al consultar las incidencias')
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
$assert(str_contains($access, 'id_puesto ?? 0), [self::ENCARGADO_PUESTO_ID, self::DIRECCION_OPERACIONES_PUESTO_ID]')
    && str_contains($access, "empty(\$permissions['leer'])"),
    'read ACL is limited to active Encargado/Direction roles and effective read permission');
$assert(str_contains($access, 'id_puesto ?? 0) !== self::ENCARGADO_PUESTO_ID')
    && str_contains($access, "empty(\$permissions['crear'])"),
    'create ACL is Encargado-only and requires the effective create permission');
$assert(str_contains($controller, 'CsrfToken::validate($csrf)')
    && str_contains($controller, 'authorizeIncidenceCreate($workplaceId)')
    && str_contains($controller, '->create('),
    'POST validates CSRF, rechecks locality permission, then delegates to the SQL writer');
$serviceSource = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAdministrativeIncidenceSqlService.php') ?: '';
$assert(str_contains($serviceSource, "connection('default')")
    && str_contains($serviceSource, '->transaction(')
    && str_contains($serviceSource, "'id_localidad' => \$workplaceId")
    && str_contains($serviceSource, "nullableMap['op_rh_v2_incidencias_maestro.id_estacion']")
    && str_contains($serviceSource, "nullableMap['op_rh_v2_incidencias_revisiones.aprobado_por_usuario_id']")
    && str_contains($serviceSource, 'readback_unconfirmed'),
    'writer uses default SQL connection, transaction, canonical locality and readback');

printf("\nM06_SQL_INCIDENCE_ISOLATED: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

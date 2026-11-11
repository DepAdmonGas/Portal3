<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$domTestSessionStarted = session_status() !== PHP_SESSION_ACTIVE;
if ($domTestSessionStarted) session_start();

use App\Services\ChecadorPersistentQaFixtureService;
use App\Services\ChecadorPersistentQaIncidenceStore;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$environmentKeys = [
    'APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE',
    'CHECADOR_M02_PERSISTENT_QA_FIXTURES_ENABLED', 'CHECADOR_M02_QA_INCIDENCE_LOCAL_WRITES_ENABLED',
    'CHECADOR_API_REAL_POSTS_ENABLED',
];
$environmentBefore = [];
foreach ($environmentKeys as $key) {
    $environmentBefore[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
}
$hostBefore = [array_key_exists('HTTP_HOST', $_SERVER), $_SERVER['HTTP_HOST'] ?? null];
$_ENV['APP_ENV'] = 'dev';
$_ENV['DB_CONNECTION'] = 'mysql';
$_ENV['DB_HOST'] = '127.0.0.1';
$_ENV['DB_DATABASE'] = 'bd_admongas';
$_ENV['CHECADOR_M02_PERSISTENT_QA_FIXTURES_ENABLED'] = 'true';
$_ENV['CHECADOR_M02_QA_INCIDENCE_LOCAL_WRITES_ENABLED'] = 'true';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_SERVER['HTTP_HOST'] = 'portal3.test';

$tempRoot = sys_get_temp_dir() . '/portal3-checador-m03-' . bin2hex(random_bytes(8));
$store = new ChecadorPersistentQaIncidenceStore($tempRoot);
$payload = [
    'worker_id' => 1004,
    'workplace_id' => 1,
    'return_date' => ChecadorPersistentQaIncidenceStore::WORK_DATE,
    'category' => 'INCAPACITY',
    'start_date' => ChecadorPersistentQaIncidenceStore::WORK_DATE,
    'end_date' => ChecadorPersistentQaIncidenceStore::WORK_DATE,
    'reason' => 'Comprobante recibido para revisión.',
    'evidence_reference' => 'QA-FOLIO-01',
    'idempotency_key' => 'checador-m03-qa-create-001',
];
$first = $store->create($payload, 19, [1004, 1005]);
$uuid = (string) ($first['data']['uuid_incidencia'] ?? '');
$readback = $uuid !== '' ? $store->readBack($uuid, 1, 1004) : null;
$assert(($first['ok'] ?? false) === true
    && ($first['created'] ?? false) === true
    && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $uuid) === 1,
    'local QA creation persists a stable UUID in a scoped file store');
$assert(($readback['current_status'] ?? '') === 'PENDING_REVIEW'
    && (int) ($readback['current_revision'] ?? 0) === 1
    && ($readback['revisions'][0]['status'] ?? '') === 'PENDING_REVIEW'
    && (int) ($readback['revisions'][0]['created_by_user_id'] ?? 0) === 19
    && ($readback['source_classification'] ?? '') === 'LOCAL_QA_INCIDENCE'
    && ($readback['source_fixture_version'] ?? '') === ChecadorPersistentQaFixtureService::LOCAL_INCIDENCE_WRITE_FIXTURE_VERSION,
    'readback contains the pending state, creator, fixture provenance and initial append-only history');

$missingExit = $store->create([
    'worker_id' => 1008,
    'workplace_id' => 1,
    'return_date' => ChecadorPersistentQaIncidenceStore::WORK_DATE,
    'category' => 'JUSTIFICATION',
    'start_date' => ChecadorPersistentQaIncidenceStore::WORK_DATE,
    'end_date' => ChecadorPersistentQaIncidenceStore::WORK_DATE,
    'reason' => 'Salida no registrada; se solicita revisar la justificación.',
    'evidence_reference' => '',
    'idempotency_key' => 'checador-m03-qa-justification-001',
], 19, [1004, 1008]);
$missingExitReadback = is_string($missingExit['data']['uuid_incidencia'] ?? null)
    ? $store->readBack((string) $missingExit['data']['uuid_incidencia'], 1, 1008)
    : null;
$assert(($missingExit['ok'] ?? false) === true
    && ($missingExit['created'] ?? false) === true
    && ($missingExitReadback['category'] ?? '') === 'JUSTIFICATION'
    && ($missingExitReadback['current_status'] ?? '') === 'PENDING_REVIEW'
    && !array_key_exists('events', $missingExitReadback ?? []),
    'a missing-exit justification persists separately and adds no punch event');

$duplicate = $store->create($payload, 19, [1004, 1005]);
$changedPayload = array_merge($payload, ['reason' => 'Otro motivo']);
$conflict = $store->create($changedPayload, 19, [1004, 1005]);
$otherKeyDuplicate = $store->create(array_merge($payload, ['idempotency_key' => 'checador-m03-qa-create-002']), 19, [1004, 1005]);
$assert(($duplicate['ok'] ?? false) === true
    && ($duplicate['created'] ?? true) === false
    && ($duplicate['data']['uuid_incidencia'] ?? '') === $uuid
    && ($otherKeyDuplicate['data']['uuid_incidencia'] ?? '') === $uuid,
    'same and exact duplicate requests return the original record without another write');
$assert(($conflict['ok'] ?? true) === false && ($conflict['error'] ?? '') === 'idempotency_conflict',
    'reusing one idempotency key for changed data is rejected');
$assert(($store->create(array_merge($payload, ['worker_id' => 1006]), 19, [1004, 1005])['error'] ?? '') === 'worker_out_of_scope'
    && ($store->create(array_merge($payload, ['workplace_id' => 2]), 19, [1004, 1005])['error'] ?? '') === 'unsupported_scope'
    && ($store->create(array_merge($payload, ['start_date' => '2026-10-29', 'end_date' => '2026-10-29']), 19, [1004, 1005])['error'] ?? '') === 'period_must_include_work_date',
    'worker allowlist and fixed locality are enforced by the storage service');
$assert(($store->create(array_merge($payload, ['worker_id' => 1010]), 19, [1004, 1010])['error'] ?? '') === 'worker_not_in_fixture',
    'a roster-authorized worker outside the fixed QA fixture cannot be persisted');

$reloaded = new ChecadorPersistentQaIncidenceStore($tempRoot);
$read = $reloaded->readForScope(1, ChecadorPersistentQaIncidenceStore::WORK_DATE, [1004, 1005, 1008]);
$outOfScopeRead = $reloaded->readForScope(1, ChecadorPersistentQaIncidenceStore::WORK_DATE, [1005]);
$records = $read['records'] ?? [];
$categories = array_column($records, 'category');
$assert(($read['ok'] ?? false) === true
    && count($records) === 2
    && in_array('INCAPACITY', $categories, true)
    && in_array('JUSTIFICATION', $categories, true)
    && in_array($uuid, array_column($records, 'uuid_incidencia'), true),
    'records persist across service instances and can be read back by scope');
$assert(($outOfScopeRead['ok'] ?? false) === true && ($outOfScopeRead['records'] ?? []) === [],
    'readback excludes records outside the active authorized worker set');
$directoryPermissions = fileperms($tempRoot);
$dataPermissions = fileperms($tempRoot . '/records.json');
$assert(($directoryPermissions & 0777) === 0700 && ($dataPermissions & 0777) === 0600,
    'QA records and directory use private permissions');

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path) || is_link($path)) {
        if (file_exists($path) || is_link($path)) @unlink($path);
        return;
    }
    foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) $removeTree($path . '/' . $entry);
    @rmdir($path);
};
$removeTree($tempRoot);

$root = dirname(__DIR__);
$defaultStore = new ChecadorPersistentQaIncidenceStore();
$defaultStoreDirectory = new ReflectionProperty($defaultStore, 'directory');
$assert($defaultStoreDirectory->getValue($defaultStore) === $root . '/storage/checador-qa-incidences'
    && !str_contains((string) $defaultStoreDirectory->getValue($defaultStore), '/public/')
    && $defaultStore->usesDefaultIsolatedDestination()
    && !(new ChecadorPersistentQaIncidenceStore($tempRoot))->usesDefaultIsolatedDestination(),
    'production QA writes require the fixed private destination; test stores cannot masquerade as it');
$fixtureService = new ChecadorPersistentQaFixtureService();
$qaRoster = ['status' => 'partial', 'coverage' => 'current_roster_historical_unverified', 'workers' => [
    ['worker_id' => 1005, 'full_name' => 'Bruno Cervantes'],
]];
$assert($fixtureService->localIncidenceWriteEnvironmentEnabled()
    && $fixtureService->isEligibleLocalIncidenceWorker(1, '2026-10-28', 1005)
    && !$fixtureService->isEligibleLocalIncidenceWorker(2, '2026-10-28', 1005)
    && !$fixtureService->isEligibleLocalIncidenceWorker(1, '2026-10-29', 1005)
    && !$fixtureService->isEligibleLocalIncidenceWorker(1, '2026-10-28', 1010)
    && $fixtureService->allowsHistoricalQaIncidenceRoster($qaRoster, 1, '2026-10-28', 1005)
    && !$fixtureService->allowsHistoricalQaIncidenceRoster(['status' => 'ready', 'coverage' => 'complete', 'workers' => $qaRoster['workers']], 1, '2026-10-28', 1005)
    && !$fixtureService->allowsHistoricalQaIncidenceRoster(['status' => 'partial', 'coverage' => 'unknown', 'workers' => $qaRoster['workers']], 1, '2026-10-28', 1005)
    && !$fixtureService->allowsHistoricalQaIncidenceRoster($qaRoster, 1, '2026-10-28', 1004),
    'historical QA exception requires exact fixture scope and an already-present worker profile without upgrading coverage');
$hostForPortTest = $_SERVER['HTTP_HOST'];
$_SERVER['HTTP_HOST'] = 'portal3.test:8080';
$rejectsPortHost = !$fixtureService->localIncidenceWriteEnvironmentEnabled();
$_SERVER['HTTP_HOST'] = $hostForPortTest;
$assert($rejectsPortHost, 'local incidence writes reject host headers with an unapproved port');
$flagBeforeDisabledStorage = $_ENV['CHECADOR_M02_QA_INCIDENCE_LOCAL_WRITES_ENABLED'];
$_ENV['CHECADOR_M02_QA_INCIDENCE_LOCAL_WRITES_ENABLED'] = 'false';
$assert(!$store->localQaWritesExplicitlyEnabled()
    && ($store->create($payload, 19, [1004, 1005])['error'] ?? '') === 'disabled',
    'QA writes fail closed when the separate local storage switch is disabled');
$_ENV['CHECADOR_M02_QA_INCIDENCE_LOCAL_WRITES_ENABLED'] = $flagBeforeDisabledStorage;
$apiPostsBefore = $_ENV['CHECADOR_API_REAL_POSTS_ENABLED'];
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'true';
$assert(!$store->localQaWritesExplicitlyEnabled()
    && ($store->create($payload, 19, [1004, 1005])['error'] ?? '') === 'disabled',
    'QA storage rejects requests while canonical API writes are enabled');
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = $apiPostsBefore;
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
$view = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php') ?: '';
$journeyView = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php') ?: '';
$temporaryAccessView = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php') ?: '';
$scheduleWeekView = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php') ?: '';
$attendanceView = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php') ?: '';
$routes = file_get_contents($root . '/routes/web.php') ?: '';
$script = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.incidences.init.js') ?: '';
$reportScript = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.datatable.init.js') ?: '';
$tableActionCss = file_get_contents($root . '/public/assets/css/table-actions.css') ?: '';
$routeDispatcher = \FastRoute\simpleDispatcher(require $root . '/routes/web.php');
$createPostRoute = $routeDispatcher->dispatch('POST', '/departamento-operativo/recursos-humanos/checador/incidencias/crear');
$createGetRoute = $routeDispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/incidencias/crear');
$duplicatedPrefixRoute = $routeDispatcher->dispatch('POST', '/departamento-operativo/departamento-operativo/recursos-humanos/checador/incidencias/crear');
$loopPosition = strpos($view, '<?php foreach ($incidencesByWorker as $workerId => $incidences): ?>');
$buttonPosition = strpos($view, 'Nueva incidencia');
$modalPosition = strpos($view, 'id="checador-create-incidence-modal"');
$createButtonPattern = preg_match('/<button\s+type="button"[^>]*data-bs-target="#checador-create-incidence-modal"[^>]*>/s', $view) === 1;
$createButtonHasHref = preg_match('/<button\s+type="button"[^>]*data-bs-target="#checador-create-incidence-modal"[^>]*href=/s', $view) === 1;
$createFormPattern = preg_match('/<form\s+method="post"\s+action="\/departamento-operativo\/recursos-humanos\/checador\/incidencias\/crear"[^>]*>/s', $view) === 1;
$getCreateRoutePattern = preg_match('/\$r->addRoute\(\s*[\'\"]GET[\'\"]\s*,\s*[\'\"]\/departamento-operativo\/recursos-humanos\/checador\/incidencias\/crear[\'\"]/', $routes) === 1;
$assert(str_contains($routes, "'/departamento-operativo/recursos-humanos/checador/incidencias'")
    && str_contains($controller, "in_array(\$workspace, ['journeys', 'adjustments'], true)")
    && str_contains($controller, "readIncidenceWorkspace(")
    && str_contains($controller, "->readIncidences("),
    'direct incidence route and no-snapshot read path are wired');
$assert(is_int($buttonPosition) && is_int($loopPosition) && $buttonPosition < $loopPosition
    && is_int($modalPosition) && $modalPosition > $loopPosition,
    'create button and modal are independent from existing incidence rows');
$assert(str_contains($view, 'name="worker_id"')
    && str_contains($view, 'name="_csrf_token"')
    && str_contains($script, "dropdownParent: window.jQuery('#checador-create-incidence-modal')")
    && str_contains($view, 'name="confirm_create"'),
    'Bootstrap modal includes the authorized worker select, CSRF, Select2 binding and confirmation');
$assert($createButtonPattern && !$createButtonHasHref
    && substr_count($view, 'id="checador-create-incidence-modal"') === 1,
    'new-incidence action is a non-navigating button targeting the one existing modal');
$assert($createFormPattern
    && str_contains($view, 'action="/departamento-operativo/recursos-humanos/checador/incidencias/crear" class="modal-content"')
    && !$getCreateRoutePattern,
    'creation endpoint is submitted only by POST and has no GET route');
$assert(($createPostRoute[0] ?? null) === \FastRoute\Dispatcher::FOUND
    && ($createGetRoute[0] ?? null) === \FastRoute\Dispatcher::METHOD_NOT_ALLOWED
    && ($duplicatedPrefixRoute[0] ?? null) === \FastRoute\Dispatcher::NOT_FOUND,
    'FastRoute resolves the real POST URL, rejects GET, and no longer registers a doubled group prefix');
$assert(str_contains($controller, "'/departamento-operativo/recursos-humanos/checador/incidencias?' . http_build_query")
    && str_contains($controller, "header('Location: ' . \$location, true, 303)")
    && !str_contains($controller, "self::redirect('/departamento-operativo/recursos-humanos/checador/incidencias/crear"),
    'creation redirects with 303 to the incidence list, never to the POST endpoint');

$savedSession = $_SESSION;
$_SESSION = [];
$administrativeIncidences = [];
$workerDisplayNames = [];
$workerSelectOptions = [
    ['worker_id' => 1004, 'full_name' => 'QA Alicia', 'employee_number' => 'QA-1004'],
    ['worker_id' => 1005, 'full_name' => 'QA Bruno', 'employee_number' => 'QA-1005'],
    ['worker_id' => 1010, 'full_name' => 'No fixture', 'employee_number' => 'REAL-1010'],
];
$administrativeErrors = [];
$incidenceCreateFeedback = null;
$incidenceCreateAllowed = true;
$localQaIncidenceStoreAvailable = true;
$realApiPostOperationsEnabled = false;
$selectedDate = '2026-10-28';
$selectedWorkplaceName = 'Interlomas';
$selectedWorkplaceId = 1;
$selectedWorkerId = null;
$workerSelectStatus = 'ready';
$incidenceCreateKey = 'dom-regression-key';
$state = 'success';
$stateMessage = '';
$snapshotRef = '';
$incidenceCreateFeedback = [
    'type' => 'error',
    'message' => 'Validación de recuperación.',
    'worker_id' => 1004,
    'workplace_id' => 1,
    'return_date' => '2026-10-28',
    'retry_idempotency_key' => 'dom-retry-key',
    'form_values' => [
        'category' => 'JUSTIFICATION',
        'start_date' => '2026-10-28',
        'end_date' => '2026-10-28',
        'reason' => 'Motivo QA conservado.',
        'evidence_reference' => '',
        'confirm_create' => true,
    ],
];
$csrfTokenIssuedForLayout = \App\Core\CsrfToken::token();
$renderBufferLevel = ob_get_level();
try {
    ob_start();
    require $root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php';
    $renderedView = ob_get_clean();
} finally {
    while (ob_get_level() > $renderBufferLevel) ob_end_clean();
    $_SESSION = $savedSession;
    if ($domTestSessionStarted) session_destroy();
}
$dom = new \DOMDocument();
@$dom->loadHTML((string) $renderedView);
$xpath = new \DOMXPath($dom);
$renderedCreateButtons = $xpath->query('//*[@data-bs-target="#checador-create-incidence-modal"]');
$renderedCreateForms = $xpath->query('//*[@id="checador-create-incidence-modal"]//form');
$renderedCreateWorkerOptions = $xpath->query('//*[@id="checador-create-incidence-modal"]//select[@name="worker_id"]/option');
$renderedCreateWorkerOptionValues = [];
foreach ($renderedCreateWorkerOptions as $option) $renderedCreateWorkerOptionValues[] = $option->getAttribute('value');
$renderedSaveButtons = $xpath->query('//*[@id="checador-create-incidence-modal"]//button[@type="submit"]');
$renderedCsrfField = $xpath->query('//*[@id="checador-create-incidence-modal"]//input[@name="_csrf_token"]')->item(0);
$renderedOwner = $renderedSaveButtons->item(0) === null
    ? null
    : $xpath->query('ancestor::form[1]', $renderedSaveButtons->item(0))->item(0);
$renderedNestedForms = $xpath->query('//*[@id="checador-create-incidence-modal"]//form//form');
$renderedConfirm = $xpath->query('//*[@id="checador-create-incidence-modal"]//input[@name="confirm_create"]')->item(0);
$assert($renderedCreateButtons->length === 1
    && $renderedCreateButtons->item(0)?->nodeName === 'button'
    && $renderedCreateButtons->item(0)?->getAttribute('type') === 'button'
    && !$renderedCreateButtons->item(0)?->hasAttribute('href')
    && $renderedCreateForms->length === 1
    && $renderedCreateForms->item(0)?->getAttribute('method') === 'post'
    && $renderedCreateForms->item(0)?->getAttribute('action') === '/departamento-operativo/recursos-humanos/checador/incidencias/crear'
    && in_array('1004', $renderedCreateWorkerOptionValues, true)
    && in_array('1005', $renderedCreateWorkerOptionValues, true)
    && !in_array('1010', $renderedCreateWorkerOptionValues, true)
    && $renderedSaveButtons->length === 1
    && $renderedOwner instanceof \DOMElement
    && $renderedOwner->getAttribute('action') === '/departamento-operativo/recursos-humanos/checador/incidencias/crear'
    && $renderedCsrfField instanceof \DOMElement
    && hash_equals($csrfTokenIssuedForLayout, $renderedCsrfField->getAttribute('value'))
    && $renderedConfirm instanceof \DOMElement
    && $renderedConfirm->hasAttribute('checked')
    && $xpath->query('//*[@id="checador-create-incidence-modal"]//textarea[@name="reason"]')->item(0)?->textContent === 'Motivo QA conservado.'
    && $renderedNestedForms->length === 0,
    'parsed rendered HTML preserves the QA-only worker choices, save button, CSRF token, fields and confirmation in the single POST form');

$administrativeIncidences = [1004 => [[
    'uuid_incidencia' => 'de12bfa2-20db-4fee-9ba7-4c0bb8ff9da0',
    'worker_id' => 1004,
    'workplace_id' => 1,
    'category' => 'JUSTIFICATION',
    'date_range' => ['start_date' => '2026-10-28', 'end_date' => '2026-10-28'],
    'current_status' => 'PENDING_REVIEW',
    'current_revision' => 1,
    'summary_reason' => 'La entrada requiere revisión.',
    'revisions' => [[
        'revision_number' => 1,
        'status' => 'PENDING_REVIEW',
        'reason' => 'La entrada requiere revisión.',
        'evidence_reference' => null,
        'created_by_user_id' => 33,
        'created_at_utc' => '2026-10-28T12:26:31Z',
    ]],
]]];
$workerDisplayNames = [1004 => 'Alicia Montalvo'];
$workerSelectOptions = [['worker_id' => 1004, 'full_name' => 'Alicia Montalvo', 'employee_number' => '990101']];
$selectedWorkerId = 1004;
$timezone = ['timezone' => 'America/Mexico_City'];
$incidenceCreateAllowed = false;
$localQaIncidenceStoreAvailable = false;
$state = 'success';
$renderBufferLevel = ob_get_level();
try {
    ob_start();
    require $root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php';
    $renderedDetailView = ob_get_clean();
} finally {
    while (ob_get_level() > $renderBufferLevel) ob_end_clean();
}
$detailDom = new \DOMDocument();
@$detailDom->loadHTML((string) $renderedDetailView);
$detailXpath = new \DOMXPath($detailDom);
$detailAction = $detailXpath->query('//td[contains(concat(" ", normalize-space(@class), " "), " table-actions-cell ")]//button[contains(concat(" ", normalize-space(@class), " "), " btn-outline-primary ")]')->item(0);
$uuidPresentation = $detailXpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " user-select-all ")]')->item(0);
$assert(!str_contains($renderedDetailView, '_workspace-context.php')
    && !str_contains($renderedDetailView, 'Volver a asistencia diaria')
    && str_contains($renderedDetailView, 'Localidad 1')
    && str_contains($renderedDetailView, 'Alicia Montalvo')
    && str_contains($renderedDetailView, 'N.º 990101')
    && str_contains($renderedDetailView, '28/10/2026'),
    'incidence screen replaces the bordered context panel and return button with the selected worker/date context');
$assert($detailAction instanceof \DOMElement
    && $detailAction->textContent === 'Ver detalle'
    && str_contains($detailAction->getAttribute('class'), 'btn ')
    && str_contains($detailAction->getAttribute('class'), 'btn-sm')
    && str_contains($detailAction->getAttribute('class'), 'btn-outline-primary')
    && !str_contains($detailAction->getAttribute('class'), 'btn-table-action')
    && $detailXpath->query('.//i[contains(concat(" ", normalize-space(@class), " "), " ti-eye ")]', $detailAction)->length === 1
    && $detailAction->getAttribute('data-bs-target') === '#incidence-history-1004-0'
    && $uuidPresentation instanceof \DOMElement
    && $uuidPresentation->textContent === 'de12bfa2-20db-4fee-9ba7-4c0bb8ff9da0',
    'incidence row action uses the native Modernize button and Tabler eye icon while its modal and original reference stay intact');
$assert(str_contains($renderedDetailView, 'Usuario ID 33')
    && str_contains($renderedDetailView, '28/10/2026 06:26')
    && str_contains($renderedDetailView, 'America/Mexico_City')
    && str_contains($renderedDetailView, 'La entrada requiere revisión.'),
    'revision history keeps its source fields and displays the user ID and original UTC instant in local time');
$assert(str_contains($controller, "'incidences' => 'Incidencias'")
    && str_contains($controller, "'/departamento-operativo/recursos-humanos/checador/asistencia'")
    && str_contains($controller, "['worker_id'] = \$requestedWorker"),
    'incidence breadcrumb links back to daily attendance and preserves the selected date/worker context');
$fullLayouts = ['main.php', 'departamento-operativo.php', 'configuracion.php', 'sasisopa.php', 'sgm.php'];
$assert($tableActionCss !== ''
    && str_contains($tableActionCss, '.btn-table-action--primary')
    && str_contains($tableActionCss, '.btn-table-action--secondary')
    && str_contains($tableActionCss, '.btn-table-action--warning')
    && str_contains($tableActionCss, '.btn-table-action--danger')
    && str_contains($tableActionCss, '.table-actions-cell')
    && str_contains($tableActionCss, '.table-actions-group')
    && array_reduce($fullLayouts, static fn(bool $all, string $layout): bool => $all
        && str_contains(file_get_contents($root . '/app/Views/layouts/' . $layout) ?: '', 'css/table-actions.css?v=20261028-1'), true),
    'shared action-button variants are loaded from all full Portal3 layouts without changing default buttons');
$assert(str_contains($journeyView, 'btn-table-action--secondary')
    && str_contains($scheduleWeekView, 'btn-table-action--secondary')
    && str_contains($temporaryAccessView, 'btn-table-action--danger')
    && str_contains($reportScript, 'btn-table-action--primary')
    && str_contains($reportScript, 'data-checador-detail')
    && str_contains($reportScript, "\$tableElement.on('click'"),
    'journey, temporary-access and report table actions opt into the shared standard while retaining their existing handlers');
$assert(str_contains($attendanceView, 'data-bs-toggle="dropdown"')
    && str_contains($attendanceView, 'dropdown-item')
    && !str_contains($attendanceView, 'btn-table-action'),
    'the existing attendance action dropdown is intentionally unchanged');

$assert(str_contains($controller, '$localQaIncidenceWrite')
    && str_contains($controller, '->localQaWritesExplicitlyEnabled()')
    && str_contains($controller, '->usesDefaultIsolatedDestination()')
    && str_contains($controller, '->canWriteDefaultIsolatedDestination()')
    && str_contains($controller, '->allowsHistoricalQaIncidenceRoster(')
    && str_contains($controller, "(int) (\$activeActor->id_puesto ?? 0) === 6")
    && str_contains($controller, "source_fixture_version")
    && str_contains($controller, "summary_reason'] ?? '') === \$reason")
    && str_contains($controller, 'if (!$realApiPostOperationsEnabled)')
    && str_contains($controller, 'readBack(')
    && str_contains($controller, 'authorizeIncidenceCreate($workplaceId)'),
    'QA creation requires Encargado, explicit isolated storage, the historical-roster exception and exact provenance readback');
$assert(strpos($controller, 'authorizeIncidenceCreate($workplaceId)') < strpos($controller, '$fixtureWorkerEligible =')
    && strpos($controller, '$fixtureWorkerEligible =') < strpos($controller, 'if ($localQaIncidenceWrite)')
    && strpos($controller, "if (\$fixtureWorkerEligible && \$realApiPostOperationsEnabled)") !== false,
    'ACL runs before fixture eligibility, and API-enabled QA scenarios are rejected before either storage path');

foreach ($environmentBefore as $key => [$wasInEnv, $oldValue, $processValue]) {
    if ($wasInEnv) $_ENV[$key] = $oldValue; else unset($_ENV[$key]);
    if ($processValue === false) putenv($key); else putenv($key . '=' . $processValue);
}
if ($hostBefore[0]) $_SERVER['HTTP_HOST'] = $hostBefore[1]; else unset($_SERVER['HTTP_HOST']);

echo "\nRESULT: {$passes} PASS / {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$controller = $read('app/Controllers/ChecadorController.php');
$route = $read('routes/web.php');
$view = $read('app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php');
$filters = $read('public/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js');
$table = $read('public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js');
$rosterService = $read('app/Services/ChecadorAttendanceRosterService.php');
$handlerStart = strpos($controller, 'public function dailyAttendance(): void');
$handlerEnd = strpos($controller, 'public function rolComodines(): void', $handlerStart ?: 0);
$dailyHandler = $handlerStart !== false && $handlerEnd !== false ? substr($controller, $handlerStart, $handlerEnd - $handlerStart) : '';
$dashboardStart = strpos($controller, 'function renderDashboardWorkspace(');
$dashboard = $dashboardStart === false ? '' : substr($controller, $dashboardStart);
$dailyRosterLookup = strpos($dashboard, '->workersForAttendanceFilter(');
$dailyReaderInitialization = strpos($dashboard, '$reader = new ChecadorDailyReadService()');
$fallbackPolicyStart = strpos($controller, 'private static function canDisplayAttendanceRosterFallback');
$fallbackPolicyEnd = $fallbackPolicyStart === false
    ? false
    : strpos($controller, 'private static function readDailyRawAttendance', $fallbackPolicyStart);
$fallbackPolicy = $fallbackPolicyStart !== false && $fallbackPolicyEnd !== false
    ? substr($controller, $fallbackPolicyStart, $fallbackPolicyEnd - $fallbackPolicyStart)
    : '';
$fallbackPolicyMethod = new ReflectionMethod(\App\Controllers\ChecadorController::class, 'canDisplayAttendanceRosterFallback');
$fallbackPolicyMethod->setAccessible(true);
$currentVerifiedRoster = [
    'status' => 'ready',
    'coverage' => 'current_active_roster',
    'workers' => array_map(
        static fn(int $id): array => ['worker_id' => $id, 'full_name' => 'Interlomas ' . $id],
        range(1, 36)
    ),
];
$simulatedPunchRead = ['ok' => false, 'error' => ['kind' => 'api_unavailable']];
$simulatedState = ($simulatedPunchRead['ok'] ?? false) ? 'success' : 'unavailable';
$currentRosterFallbackAllowed = $fallbackPolicyMethod->invoke(null, $currentVerifiedRoster);
$state = $simulatedState;
$selectedWorkplaceId = 1;
$selectedWorkplaceName = 'Interlomas';
$selectedWorkplaceKind = 'Estación';
$selectedDate = (new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City')))->format('Y-m-d');
$selectedWorkerId = null;
$attendanceRosterFallbackAllowed = $currentRosterFallbackAllowed;
$attendanceRoster = $currentVerifiedRoster + ['message' => 'Personal activo con membresía vigente.'];
$attendanceRows = array_map(static fn(array $worker): array => $worker + [
    'schedule' => null,
    'schedule_error' => 'schedule_not_loaded',
    'slots' => [],
    'additional_events' => [],
    'status' => 'punches_unavailable',
    'worked_seconds' => null,
    'worked_time_source' => null,
    'incidence_count' => null,
    'incidences' => [],
], $currentVerifiedRoster['workers']);
$attendanceCoverage = ['status' => 'not_available', 'complete' => false];
$administrativeIncidences = [];
$administrativeErrors = [];
$workerSelectOptions = [];
$snapshotRef = null;
$demoMode = false;
$realApiPostOperationsEnabled = false;
$dashboardHref = '/departamento-operativo/recursos-humanos/checador';
ob_start();
require $root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php';
$simulatedAttendanceHtml = (string) ob_get_clean();
$renderedCurrentRosterCount = count(array_filter(
    $currentVerifiedRoster['workers'],
    static fn(array $worker): bool => str_contains($simulatedAttendanceHtml, (string) $worker['full_name'])
));
$historicalUnverifiedRoster = [
    'status' => 'partial',
    'coverage' => 'current_roster_historical_unverified',
    'workers' => $currentVerifiedRoster['workers'],
];
$incompleteRoster = [
    'status' => 'partial',
    'coverage' => 'incomplete',
    'workers' => $currentVerifiedRoster['workers'],
];
$verifiedHistoricalRoster = [
    'status' => 'ready',
    'coverage' => 'historical_versioned_membership',
    'workers' => $currentVerifiedRoster['workers'],
];

$checks = [
    'GET and POST use the existing authenticated attendance route' =>
        str_contains($route, "addRoute('GET', '/recursos-humanos/checador/asistencia', Route::auth(['ChecadorController', 'dailyAttendance']))")
        && str_contains($route, "addRoute('POST', '/recursos-humanos/checador/asistencia', Route::auth(['ChecadorController', 'dailyAttendance']))"),
    'POST accepts only JSON date and worker filters and rejects invalid values' =>
        str_contains($controller, "\$contentType !== 'application/json'")
        && str_contains($controller, "\$requestedWith !== 'XMLHTTPREQUEST'")
        && str_contains($controller, "\$payloadKeys !== ['date', 'worker_id']")
        && str_contains($controller, 'self::isCivilDate($date)')
        && str_contains($controller, 'Request::json()'),
    'POST renders through the existing read workspace using session-derived locality scope' =>
        str_contains($controller, "\$workspace === 'daily-attendance' && Request::method() === 'POST'")
        && str_contains($controller, "\$this->renderDashboardWorkspace('daily-attendance')")
        && str_contains($controller, "'attendance_context_unavailable'")
        && str_contains($controller, 'ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY)'),
    'client sends no workplace scope and response updates the existing results fragment' =>
        str_contains($view, 'data-checador-attendance-results')
        && str_contains($view, 'data-attendance-state="<?= $esc($state) ?>"')
        && str_contains($view, 'data-checador-search-action="search"')
        && str_contains($view, 'data-checador-search-action="clear"')
        && str_contains($view, 'data-workplace-id="<?= $selectedWorkplaceId ?? \'\' ?>"')
        && !str_contains($view, 'name="workplace_id"')
        && str_contains($filters, 'currentResults.replaceWith(nextResults)')
        && str_contains($filters, "nextResults.dataset.attendanceState !== 'success'")
        && str_contains($filters, 'nextResults.dataset.workplaceId !== currentResults.dataset.workplaceId')
        && str_contains($filters, 'clearResultsForUnverifiedContext')
        && str_contains($filters, 'status === 401 || status === 403 || status === 419')
        && str_contains($filters, '\'X-CSRF-TOKEN\': token'),
    'worker options come from the existing authorized roster and observed in-scope profiles' =>
        str_contains($controller, 'ChecadorAttendanceRosterService())->currentRoster(')
        && str_contains($controller, 'observedProfiles(')
        && str_contains($controller, '$profiles = array_intersect_key($profiles, [$requestedWorker => true])')
        && str_contains($controller, "\$workerSelectOptions = array_values(array_filter(")
        && str_contains($view, 'foreach ($workerSelectOptions as $workerOption)')
        && str_contains($filters, 'workerInput.replaceChildren.apply'),
    'a successful empty API snapshot still renders the active authorized roster, while failed reads remain errors' =>
        str_contains($dashboard, "if (!\$readResult['ok'])")
        && str_contains($dashboard, "\$state = 'success';")
        && strpos($dashboard, "\$state = 'success';") < strpos($dashboard, 'ChecadorAttendanceRosterService())->currentRoster(')
        && str_contains($dashboard, "\$eventsByWorker[(int) \$workerId] ?? []")
        && str_contains($rosterService, "where('estado', 1)")
        && str_contains($view, "if (\$state === 'success' || \$rosterFallbackDisplayed)")
        && str_contains($view, 'No hay colaboradores atribuibles dentro de la información disponible')
        && str_contains($view, 'El padrón no está disponible y no se muestran personas sin una membresía autorizada verificable.'),
    'verified roster is read before API initialization and can survive a read-client configuration failure' =>
        $dailyRosterLookup !== false
        && $dailyReaderInitialization !== false
        && $dailyRosterLookup < $dailyReaderInitialization
        && str_contains($dashboard, '$reader = null;')
        && str_contains($controller, '?ChecadorDailyReadService $reader')
        && str_contains($view, '$attendanceRosterFallbackAllowed')
        && str_contains($fallbackPolicy, 'current_active_roster')
        && str_contains($fallbackPolicy, 'historical_versioned_membership')
        && !str_contains($fallbackPolicy, "'incomplete'"),
    '36-worker current Interlomas roster survives a failed punch read without reporting success' =>
        $simulatedPunchRead['ok'] === false
        && $simulatedState === 'unavailable'
        && $currentRosterFallbackAllowed === true
        && $renderedCurrentRosterCount === 36
        && str_contains($simulatedAttendanceHtml, 'Marcaciones no disponibles')
        && str_contains($simulatedAttendanceHtml, 'Padrón autorizado de referencia')
        && str_contains($view, '$state !== \'success\'')
        && str_contains($view, '$attendanceRosterFallbackAllowed'),
    'unverified historical and incomplete rosters remain fail-closed while verified history remains available' =>
        $fallbackPolicyMethod->invoke(null, $historicalUnverifiedRoster) === false
        && $fallbackPolicyMethod->invoke(null, $incompleteRoster) === false
        && $fallbackPolicyMethod->invoke(null, $verifiedHistoricalRoster) === true,
    'errors preserve visible results and the DataTable can be reinitialized after replacement' =>
        str_contains($filters, 'currentResults.replaceWith(nextResults)')
        && str_contains($filters, "catch (error)")
        && str_contains($table, 'window.initChecadorAttendanceDataTable = function (root)')
        && str_contains($table, "resultsRoot.dataset.attendanceState !== 'success'")
        && str_contains($table, 'body.removeChild(body.rows[0])')
        && str_contains($table, 'return true;'),
    'filter flow does not navigate or mutate attendance data' =>
        !str_contains($filters, 'window.location.assign')
        && !str_contains($filters, 'window.location.reload')
        && !str_contains($dailyHandler, 'WORKER_EFFECTIVE')
        && !str_contains($dailyHandler, 'INSERT INTO')
        && !str_contains($dailyHandler, 'UPDATE '),
];

$failures = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$passed) $failures++;
}

printf("CHECADOR_M01_DAILY_ATTENDANCE_AJAX_READ: %d PASS / %d FAIL (static source checks; no HTTP or database execution)\n", count($checks) - $failures, $failures);
exit($failures === 0 ? 0 : 1);

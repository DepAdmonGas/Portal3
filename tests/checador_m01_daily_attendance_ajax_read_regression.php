<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => file_get_contents($root . '/' . $path) ?: '';
$controller = $read('app/Controllers/ChecadorController.php');
$route = $read('routes/web.php');
$view = $read('app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php');
$filters = $read('public/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js');
$table = $read('public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js');
$rosterService = $read('app/Services/ChecadorAttendanceRosterService.php');
$accessService = $read('app/Services/ChecadorAccessService.php');
$handlerStart = strpos($controller, 'public function dailyAttendance(): void');
$handlerEnd = strpos($controller, 'public function rolComodines(): void', $handlerStart ?: 0);
$dailyHandler = $handlerStart !== false && $handlerEnd !== false ? substr($controller, $handlerStart, $handlerEnd - $handlerStart) : '';
$dashboardStart = strpos($controller, 'function renderDashboardWorkspace(');
$dashboard = $dashboardStart === false ? '' : substr($controller, $dashboardStart);
$dailyRosterLookup = strpos($dashboard, '->workersForAttendanceFilter(');
$dailyReaderInitialization = strpos($dashboard, '$rawAttendance = self::readDailySqlAttendance(');
$fallbackPolicyStart = strpos($controller, 'private static function canDisplayAttendanceRosterFallback');
$fallbackPolicyEnd = $fallbackPolicyStart === false
    ? false
    : strpos($controller, 'private static function readDailySqlAttendance', $fallbackPolicyStart);
$fallbackPolicy = $fallbackPolicyStart !== false && $fallbackPolicyEnd !== false
    ? substr($controller, $fallbackPolicyStart, $fallbackPolicyEnd - $fallbackPolicyStart)
    : '';

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
        && str_contains($view, 'data-bs-dismiss="modal"><i class="ti ti-x')
        && !str_contains($view, 'Limpiar filtros')
        && str_contains($view, 'data-workplace-id="<?= $selectedWorkplaceId ?? \'\' ?>"')
        && !str_contains($view, 'name="workplace_id"')
        && str_contains($filters, 'currentResults.replaceWith(nextResults)')
        && str_contains($view, 'data-attendance-roster-fallback=')
        && str_contains($view, 'data-checador-filter-controls')
        && str_contains($view, 'data-checador-context-date')
        && str_contains($filters, "currentContextDate.textContent = nextContextDate.textContent")
        && str_contains($filters, "nextResults.dataset.attendanceRosterFallback === 'true'")
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
    'department roster fallback requires an explicit active Checador locality allowlist' =>
        str_contains($accessService, 'function isDepartmentLocality(int $localityId): bool')
        && str_contains($accessService, "->where('modulo_key', self::MODULE_KEY)")
        && str_contains($accessService, "'stations_and_departments'")
        && str_contains($accessService, "'localidades'")
        && str_contains($accessService, 'positiveIdList($moduleConfig->departamentos_soportados)')
        && str_contains($accessService, 'RhLocalidad::query()->whereKey($localityId)->exists()'),
    'a successful empty SQL event read still renders the active authorized roster, while failed reads remain errors' =>
        str_contains($dashboard, "if (!\$readResult['ok'])")
        && str_contains($dashboard, "\$state = 'success';")
        && strpos($dashboard, "\$state = 'success';") < strpos($dashboard, 'ChecadorAttendanceRosterService())->currentRoster(')
        && str_contains($dashboard, "\$eventsByWorker[(int) \$workerId] ?? []")
        && str_contains($rosterService, "where('estado', 1)")
        && str_contains($view, 'data-attendance-roster-fallback=')
        && str_contains($view, 'if ($viewRows === [])')
        && str_contains($view, 'No hay colaboradores atribuibles dentro de la información disponible')
        && str_contains($view, 'El padrón no está disponible y no se muestran personas sin una membresía autorizada verificable.'),
    'verified roster and M01 SQL reader are independent from the shared API read client' =>
        $dailyRosterLookup !== false
        && $dailyReaderInitialization !== false
        && $dailyRosterLookup < $dailyReaderInitialization
        && str_contains($dashboard, 'self::readDailySqlAttendance(')
        && !str_contains($controller, 'private static function readDailyRawAttendance(')
        && str_contains($view, '$attendanceRosterFallbackAllowed')
        && str_contains($fallbackPolicy, 'current_active_roster')
        && str_contains($fallbackPolicy, "in_array(\$status, ['current_roster_only', 'ready'], true)")
        && str_contains($fallbackPolicy, 'historical_versioned_membership')
        && !str_contains($fallbackPolicy, "'incomplete'"),
    'unexpected daily-read exceptions are logged safely and preserve only the verified roster reference' =>
        str_contains($dashboard, 'catch (\\Throwable $exception)')
        && str_contains($dashboard, "Logger::error('Checador dashboard workspace read failed'")
        && str_contains($dashboard, 'self::readDailySqlAttendance(')
        && str_contains($dashboard, "Logger::error('Checador attendance roster fallback failed'")
        && str_contains($dashboard, '$attendanceRosterFallbackAllowed = true;')
        && str_contains($view, 'no confirma pertenencia histórica, asistencia ni horario'),
    'errors preserve visible results and the DataTable can be reinitialized after replacement' =>
        str_contains($filters, 'currentResults.replaceWith(nextResults)')
        && str_contains($filters, "catch (error)")
        && str_contains($table, 'window.initChecadorAttendanceDataTable = function (root)')
        && str_contains($table, "resultsRoot.dataset.attendanceRosterFallback === 'true'")
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

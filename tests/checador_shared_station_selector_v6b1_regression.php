<?php

declare(strict_types=1);

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    if ($condition) {
        echo "PASS {$label}\n";
        $passes++;
    } else {
        echo "FAIL {$label}\n";
        $fails++;
    }
};

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/app/Controllers/ChecadorController.php');
$moduleStations = (string) file_get_contents($root . '/app/Services/ModuleStationService.php');
$access = (string) file_get_contents($root . '/app/Services/ChecadorAccessService.php');
$moduleContext = (string) file_get_contents($root . '/app/Controllers/ModuleContextController.php');
$view = (string) file_get_contents($root . '/app/Core/View.php');
$layout = (string) file_get_contents($root . '/app/Views/layouts/departamento-operativo.php');
$dailyView = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php');
$reportView = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php');
$navigation = (string) file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js');

$indexStart = strpos($controller, 'public function index(): void');
$reportStart = strpos($controller, 'public function punchReport(): void');
$dataStart = strpos($controller, 'public function punchReportData(): void');
$index = $indexStart !== false && $reportStart !== false ? substr($controller, $indexStart, $reportStart - $indexStart) : '';
$report = $reportStart !== false && $dataStart !== false ? substr($controller, $reportStart, $dataStart - $reportStart) : '';
$moduleNavigationStart = strpos($controller, 'private function buildModuleNavigation(');
$workspaceStart = strpos($controller, 'private function renderDashboardWorkspace(');
$moduleNavigation = $moduleNavigationStart !== false && $workspaceStart !== false
    ? substr($controller, $moduleNavigationStart, $workspaceStart - $moduleNavigationStart)
    : '';

$assert($index !== '' && $report !== ''
    && str_contains($controller, "public function dailyAttendance(): void")
    && str_contains($controller, '$this->renderDashboardWorkspace(\'daily-attendance\');')
    && substr_count($controller, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY") >= 5
    && substr_count($report, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY") === 1
    && preg_match('/ModuleStationService::render\(\s*\$moduleKey/s', $view) === 1
    && str_contains($layout, 'if (!empty($moduleStationSelector))')
    && str_contains($layout, '<?= $moduleStationSelector ?>'),
    'V6B1 Checador workspaces and raw report reuse the shared selector with the independent module key');
$assert($moduleNavigation !== ''
    && str_contains($moduleNavigation, "'href' => \$base . '/asistencia'")
    && !str_contains($moduleNavigation, "\$queryString(\$base . '/asistencia', \$context)"),
    'M01 directory card uses the clean attendance route while other Checador cards retain their existing context links');
$assert(str_contains($moduleStations, 'ChecadorAccessService::authorize()')
    && str_contains($moduleStations, 'getAvailableStations(string $moduleKey)')
    && str_contains($access, 'MultiestacionService::getConfig($user)')
    && !str_contains($access, 'getAvailableStations(self::MODULE_KEY'),
    'V6B1 selector options use Checador authorization without recursive scope expansion or public bypass');
$assert(str_contains($moduleStations, 'count($stations) + count($depts) > 1')
    && str_contains($moduleStations, 'array_key_first($stationById)')
    && str_contains($moduleStations, 'array_key_first($departmentById)'),
    'V6B1 a single authorized canonical workplace is shown as context without an unnecessary selector');
$assert(str_contains($access, 'where(\'numlista\', $numlista)')
    && str_contains($access, 'RhLocalidad::query()->where(\'numlista\', $numlista)->count() !== 1'),
    'V6B1 station/locality mapping remains one-to-one through numlista');
$assert(str_contains($moduleContext, 'ChecadorAccessService::authorize($selectedId)')
    && str_contains($moduleContext, '$departmentIds')
    && str_contains($moduleContext, 'ModuleStationService::setContext($moduleKey, $hasStation ? $idEstacion : null, $hasDepartment ? $idDepto : null)'),
    'V6B1/V6B4 context POST revalidates the selected canonical locality and its group server-side');
$assert(str_contains($index, 'ChecadorAccessService::authorize(null)')
    && str_contains($index, 'ChecadorAccessService::authorize($requestedWorkplace)')
    && str_contains($report, 'ChecadorAccessService::authorize($selectedWorkplaceForView)')
    && str_contains($report, '$requestedWorkplace = $selectedWorkplaceForView;'),
    'V6B1 both screens revalidate selection against the current authorized scope');
$assert(!preg_match('/<select\b[^>]*\bname="workplace_id"/i', $dailyView . $reportView)
    && str_contains($layout, 'if (!empty($moduleStationSelector))')
    && str_contains($dailyView, 'method="post" action="<?= $esc($baseUrl) ?>" data-checador-filter-form data-checador-ajax')
    && !preg_match('/<input\b(?=[^>]*\btype="hidden")(?=[^>]*\bname="workplace_id")[^>]*>/i', $dailyView)
    && str_contains($controller, '$moduleContext[\'id_depto\'] ?? $moduleContext[\'id_estacion\'] ?? null'),
    'V6B1 the shared layout owns station selection while daily AJAX filters use the server-validated session context');
$assert(str_contains($navigation, "url.searchParams.delete('workplace_id')")
    && !str_contains($navigation, 'url.searchParams.set(\'workplace_id\', nextStationId)')
    && str_contains($navigation, "url.searchParams.delete('source_workplace_id')")
    && str_contains($navigation, "['date', 'worker_id', 'snapshot_ref', 'page', 'per_page']")
    && str_contains($navigation, "window.location.assign(url)")
    && str_contains($controller, "ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY)")
    && str_contains($moduleContext, 'ModuleStationService::setContext($moduleKey'),
    'V6B1 persists authorized station in session, removes visible context and filter queries, and reloads');
$assert(str_contains($navigation, 'function isCivilDate')
    && str_contains($navigation, 'validRange')
    && str_contains($navigation, "['date', 'worker_id', 'snapshot_ref', 'page', 'per_page']"),
    'V6B1 report date validation remains intact while M01 filters stay out of the ordinary URL');
$reportNavigationStart = strpos($navigation, 'var fromDate = url.searchParams.get');
$reportNavigationEnd = strpos($navigation, "url.searchParams.delete('export')", $reportNavigationStart ?: 0);
$reportNavigation = $reportNavigationStart !== false && $reportNavigationEnd !== false
    ? substr($navigation, $reportNavigationStart, $reportNavigationEnd - $reportNavigationStart)
    : '';
$dailyNavigationStart = strpos($navigation, 'if (isDaily) {');
$dailyNavigationEnd = strpos($navigation, '} else {', $dailyNavigationStart ?: 0);
$dailyNavigation = $dailyNavigationStart !== false && $dailyNavigationEnd !== false
    ? substr($navigation, $dailyNavigationStart, $dailyNavigationEnd - $dailyNavigationStart)
    : '';
$assert($reportNavigation !== ''
    && str_contains($reportNavigation, "if (nextStationId === null) {\n                url.searchParams.delete('worker_id');")
    && !str_contains($reportNavigation, "if (nextStationId === null || stationChanged) {\n                url.searchParams.delete('worker_id');")
    && str_contains($reportNavigation, "url.searchParams.delete('source_workplace_id')")
    && str_contains($dailyNavigation, "['date', 'worker_id', 'snapshot_ref', 'page', 'per_page']")
    && str_contains($dailyNavigation, "url.searchParams.delete(key)")
    && str_contains($index, 'checador.module-station.init.js?v=20261116-1')
    && str_contains($report, 'checador.module-station.init.js?v=20261113-2'),
    'V6B6 report preserves its valid worker filter while M01 clears filters from normal URL navigation');
$assert(str_contains($controller, '$isDailyAttendanceGet = $workspace === \'daily-attendance\' && Request::method() === \'GET\';')
    && str_contains($controller, 'if ($isDailyAttendanceGet && $hasExplicitWorkplace)')
    && str_contains($controller, 'ChecadorAccessService::authorize($requestedWorkplace)')
    && str_contains($controller, '$requestedWorkplace = $sessionWorkplace;')
    && str_contains($controller, "self::redirect('/departamento-operativo/recursos-humanos/checador/asistencia');"),
    'M01 validates legacy workplace IDs, gives an existing authorized session selection priority, and normalizes the GET URL after saving context');
$assert(str_contains($navigation, "typeof ModuleStationSelector !== 'undefined'")
    && str_contains($navigation, "var dailyPath = checadorPath + '/asistencia';")
    && str_contains($navigation, 'currentPath !== dailyPath && currentPath !== reportPath')
    && str_contains($navigation, "selectorApi.init('checador'")
    && str_contains($controller, 'checador.module-station.init.js?v=20261116-1')
    && str_contains($report, 'checador.module-station.init.js?v=20261113-2'),
    'M01 installs custom URL navigation on daily attendance and report while the saved canonical station stays in session context');
$assert(str_contains($navigation, '(?:estacion|depto)_([1-9][0-9]*)')
    && str_contains($moduleStations, 'value="depto_')
    && str_contains($access, "'departamentos_localidades'"),
    'V6B4 department option retains its group prefix while navigation uses its canonical locality ID');
$assert(str_contains($index, "'/assets/js/core/module-station-selector.js'")
    && str_contains($report, "'/assets/js/core/module-station-selector.js'")
    && str_contains($index, 'checador.module-station.init.js')
    && str_contains($report, 'checador.module-station.init.js'),
    'V6B1 both screens load the existing selector asset plus Checador-only URL synchronization');
$assert(str_contains($index, 'ChecadorDailyReadService::demoModeEnabled()')
    && strpos($index, 'ChecadorAccessService::authorize(null)') < strpos($index, 'ChecadorDailyReadService::demoModeEnabled()')
    && str_contains($navigation, 'stationChanged'),
    'V6B1 synthetic fixtures remain behind real authorization and station changes trigger a fresh request');

printf("CHECADOR_SHARED_STATION_SELECTOR_V6B1_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

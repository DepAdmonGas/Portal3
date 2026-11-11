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
$comodinesController = (string) file_get_contents($root . '/app/Controllers/RolComodinesController.php');
$sharedSelector = (string) file_get_contents($root . '/public/assets/js/core/module-station-selector.js');
$dailyReadService = (string) file_get_contents($root . '/app/Services/ChecadorDailyReadService.php');
$view = (string) file_get_contents($root . '/app/Core/View.php');
$layout = (string) file_get_contents($root . '/app/Views/layouts/departamento-operativo.php');
$dailyView = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$reportView = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php');
$navigation = (string) file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js');

$indexStart = strpos($controller, 'public function index(): void');
$reportStart = strpos($controller, 'public function punchReport(): void');
$dataStart = strpos($controller, 'public function punchReportData(): void');
$index = $indexStart !== false && $reportStart !== false ? substr($controller, $indexStart, $reportStart - $indexStart) : '';
$report = $reportStart !== false && $dataStart !== false ? substr($controller, $reportStart, $dataStart - $reportStart) : '';
$workspaceStart = strpos($controller, 'private function renderDashboardWorkspace(');
$workspaceEnd = strpos($controller, 'public function configuration(): void', $workspaceStart ?: 0);
$workspace = $workspaceStart !== false && $workspaceEnd !== false
    ? substr($controller, $workspaceStart, $workspaceEnd - $workspaceStart)
    : '';
$workspaceAuthorizePosition = strpos($workspace, 'ChecadorAccessService::authorize(null)');
$workspaceDemoPosition = strpos($workspace, 'ChecadorDailyReadService::demoModeEnabled()');

$assert($index !== '' && $report !== ''
    && substr_count($index, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY") >= 4
    && substr_count($report, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY") === 1
    && str_contains($view, 'ModuleStationService::render($moduleKey')
    && str_contains($layout, '<?= $moduleStationSelector ?? \'\' ?>'),
    'V6B1 Checador daily, weekly, historical and configuration views plus raw report reuse the existing selector with the independent module key');
$assert(str_contains($moduleStations, 'ChecadorAccessService::authorize()')
    && str_contains($moduleStations, 'getAvailableStations(string $moduleKey)')
    && str_contains($access, 'MultiestacionService::getConfig($user)')
    && !str_contains($access, 'getAvailableStations(self::MODULE_KEY'),
    'V6B1 selector options use Checador authorization without recursive scope expansion or public bypass');
$assert(str_contains($moduleStations, "(int) (\$access['position_id'] ?? 0) !== 13")
    && str_contains($moduleStations, 'count($stations) + count($depts) > 1')
    && str_contains($moduleStations, 'array_key_first($stationById)')
    && str_contains($moduleStations, 'array_key_first($departmentById)'),
    'P13 keeps a sole authorized workplace unselected while other roles retain institutional inference');
$assert(str_contains($access, 'where(\'numlista\', $numlista)')
    && str_contains($access, 'RhLocalidad::query()->where(\'numlista\', $numlista)->count() !== 1'),
    'V6B1 station/locality mapping remains one-to-one through numlista');
$assert(str_contains($moduleContext, 'ChecadorAccessService::authorize($selectedId)')
    && str_contains($moduleContext, '$departmentIds')
    && str_contains($moduleContext, 'ModuleStationService::setContext($moduleKey, $hasStation ? $idEstacion : null, $hasDepartment ? $idDepto : null)'),
    'V6B1/V6B4 context POST revalidates the selected canonical locality and its group server-side');
$assert(str_contains($index, 'ChecadorAccessService::authorize(null)')
    && str_contains($index, 'sessionWorkplaceId($access)')
    && str_contains($report, 'ChecadorAccessService::authorize($selectedWorkplaceForView)')
    && str_contains($report, '$requestedWorkplace = $selectedWorkplaceForView;')
    && str_contains($controller, 'normalizeLegacyWorkplaceUrl')
    && str_contains($controller, 'requestWorkplaceId($input)'),
    'V6B1 both screens revalidate selection against the current authorized scope');
$assert(str_contains($controller, "unset(\$params['workplace_id'])")
    && str_contains($controller, '$legacyId === $sessionId')
    && str_contains($controller, "'schedule_week', 'schedule_history'")
    && str_contains($controller, "array_key_exists('workplace_id', \$query) && \$submittedWorkplace !== \$requestedWorkplace")
    && str_contains($comodinesController, 'validatedSessionWorkplace'),
    'Legacy locality URLs are normalized without changing session, and AJAX endpoints reject locality/context mismatches');
$assert(!preg_match('/<select\b[^>]*\bname="workplace_id"/i', $dailyView . $reportView)
    && !preg_match('/name="workplace_id"/i', $dailyView . $reportView),
    'Navigation and report filters do not carry workplace IDs in GET links or forms');
$assert(str_contains($navigation, "url.searchParams.delete('workplace_id')")
    && str_contains($navigation, "'source_workplace_id', 'source_work_date'")
    && str_contains($navigation, "'worker_id', 'snapshot_ref'")
    && !str_contains($navigation, "'attendance_demo'")
    && str_contains($navigation, 'url.searchParams.delete(key)')
    && str_contains($navigation, "window.location.assign(url)"),
    'Changing station removes legacy locality and stale source/snapshot state before reloading the screen');
$assert(str_contains($navigation, 'var stationOnlyRoutes = [')
    && str_contains($navigation, "rootPath + '/rol-comodines'")
    && str_contains($navigation, 'stationOnlyRoutes.indexOf(pathname) !== -1')
    && str_contains($navigation, "selectorValue.indexOf('depto_') === 0")
    && str_contains($navigation, "return new URL(rootPath, window.location.origin).toString()"),
    'A department selected from station-only Rol de comodines returns to the directory with the confirmed session context');
$assert(str_contains($navigation, 'function isCivilDate')
    && str_contains($navigation, "'worker_id', 'snapshot_ref'")
    && str_contains($navigation, 'validRange')
    && str_contains($navigation, "return new URL(rootPath, window.location.origin).toString()"),
    'Date filters are retained only when valid, worker/snapshot data is cleared on locality changes, and clear returns to index');
$assert(str_contains($navigation, "typeof ModuleStationSelector !== 'undefined'")
    && str_contains($navigation, "document.getElementById('module-station-selector-checador')")
    && str_contains($navigation, "selectorApi.init('checador'")
    && str_contains($index, 'checador.module-station.init.js?v=20261027-1')
    && str_contains($report, 'checador.module-station.init.js?v=20261027-1'),
    'All Checador views install clean URL reload through the existing shared selector');
$assert(str_contains($navigation, '(?:estacion|depto)_([1-9][0-9]*)')
    && str_contains($moduleStations, 'value="depto_')
    && str_contains($access, "'departamentos_localidades'"),
    'V6B4 department option retains its group prefix while navigation uses its canonical locality ID');
$assert(str_contains($index, "'/assets/js/core/module-station-selector.js'")
    && str_contains($report, "'/assets/js/core/module-station-selector.js'")
    && str_contains($index, 'checador.module-station.init.js')
    && str_contains($report, 'checador.module-station.init.js'),
    'V6B1 both screens load the existing selector asset plus Checador-only URL synchronization');
$assert($workspace !== ''
    && $workspaceAuthorizePosition !== false
    && $workspaceDemoPosition !== false
    && $workspaceAuthorizePosition < $workspaceDemoPosition
    && str_contains($dailyReadService, "if (PHP_SAPI !== 'cli')")
    && str_contains($dailyReadService, "in_array(\$environment, ['dev', 'development', 'local'], true)")
    && str_contains($dailyReadService, "in_array(\$flag, ['1', 'true', 'yes', 'on'], true)")
    && str_contains($navigation, 'stationChanged')
    && str_contains($sharedSelector, 'response.data.ok !== true')
    && str_contains($sharedSelector, 'this.selector.value = this._confirmedValue;'),
    'V6B1 synthetic fixtures remain behind real authorization and station changes trigger a fresh request');

printf("CHECADOR_SHARED_STATION_SELECTOR_V6B1_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

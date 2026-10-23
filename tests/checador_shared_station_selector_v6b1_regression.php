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
$dailyView = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$reportView = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php');
$navigation = (string) file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.module-station.init.js');

$indexStart = strpos($controller, 'public function index(): void');
$reportStart = strpos($controller, 'public function punchReport(): void');
$dataStart = strpos($controller, 'public function punchReportData(): void');
$index = $indexStart !== false && $reportStart !== false ? substr($controller, $indexStart, $reportStart - $indexStart) : '';
$report = $reportStart !== false && $dataStart !== false ? substr($controller, $reportStart, $dataStart - $reportStart) : '';

$assert($index !== '' && $report !== ''
    && substr_count($index, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY") === 4
    && substr_count($report, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY") === 1
    && str_contains($view, 'ModuleStationService::render($moduleKey')
    && str_contains($layout, '<?= $moduleStationSelector ?? \'\' ?>'),
    'V6B1 Checador daily, weekly, historical and configuration views plus raw report reuse the existing selector with the independent module key');
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
    && substr_count($dailyView . $reportView, 'type="hidden" id="workplace_id" name="workplace_id"') === 2,
    'V6B1 duplicate content-level station selectors are replaced with synchronized hidden form context');
$assert(str_contains($navigation, 'url.searchParams.set(\'workplace_id\', nextStationId)')
    && str_contains($navigation, "url.searchParams.delete('source_workplace_id')")
    && str_contains($navigation, "url.searchParams.delete('snapshot_ref')")
    && str_contains($navigation, "window.location.assign(url)"),
    'V6B1 changing station replaces URL context, drops stale source/snapshot state, and reloads the screen');
$assert(str_contains($navigation, 'function isCivilDate')
    && str_contains($navigation, "url.searchParams.delete('worker_id')")
    && str_contains($navigation, 'validRange'),
    'V6B1 date context is retained only when valid and daily worker context is cleared on station changes');
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
    && str_contains($dailyNavigation, 'nextStationId === null || stationChanged')
    && str_contains($index, 'checador.module-station.init.js?v=20261021-1')
    && str_contains($report, 'checador.module-station.init.js?v=20261022-2'),
    'V6B6 report preserves a valid worker filter across locality changes while daily-screen behavior stays unchanged');
$assert(str_contains($navigation, "typeof ModuleStationSelector !== 'undefined'")
    && str_contains($navigation, 'currentPath !== reportPath')
    && str_contains($navigation, "selectorApi.init('checador'")
    && str_contains($index, 'checador.module-station.init.js?v=20261021-1')
    && str_contains($report, 'checador.module-station.init.js?v=20261022-2'),
    'V6B7 report installs the custom URL reload through the shared lexical class while the advanced screen keeps its prior reload path');
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

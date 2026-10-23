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
$daily = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php');
$report = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php');
$controller = (string) file_get_contents($root . '/app/Controllers/ChecadorController.php');
$dataTable = (string) file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.datatable.init.js');
$filtersJs = (string) file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js');

$assert(str_contains($daily, 'id="checador-filter-modal"')
    && str_contains($daily, 'id="date" name="date" type="date"')
    && str_contains($daily, 'id="worker_id" name="worker_id" type="number"')
    && str_contains($daily, 'name="workplace_id"'), 'advanced screen modal retains date, optional worker and authorized station context');
$assert(str_contains($report, 'id="punch-report-filter-modal"')
    && str_contains($report, 'id="from_date" name="from_date" type="date"')
    && str_contains($report, 'id="to_date" name="to_date" type="date"')
    && str_contains($report, 'id="worker_id" name="worker_id" type="number"'), 'raw report modal contains the UTC range and optional collaborator filter');
$assert(substr_count($daily, 'id="checador-filter-modal"') === 1
    && substr_count($report, 'id="punch-report-filter-modal"') === 1
    && substr_count($daily, 'data-bs-target="#checador-filter-modal"') >= 1
    && substr_count($report, 'data-bs-target="#punch-report-filter-modal"') === 1
    && str_contains($daily, 'Filtros')
    && str_contains($report, 'Filtros'), 'both screens reuse one Bootstrap filter modal; dashboard navigation may open the same filter dialog');
$assert(substr_count($daily, 'data-checador-filter-form') === 1
    && substr_count($report, 'data-checador-filter-form') === 1
    && substr_count($daily, 'formnovalidate') === 1
    && substr_count($report, 'formnovalidate') === 1
    && substr_count($daily, 'data-checador-clear-filters') === 1
    && substr_count($report, 'data-checador-clear-filters') === 1
    && str_contains($daily, 'Cancelar')
    && str_contains($report, 'Cancelar'), 'Apply, clear, and cancel controls are present without duplicate filter forms');
$assert(!preg_match('/<select\b[^>]*\bname="workplace_id"/i', $daily . $report)
    && substr_count($daily . $report, 'id="workplace_id" name="workplace_id"') === 2,
    'institutional station selector remains outside both filter modals with one hidden authorized context per screen');
$dailyIds = [];
$reportIds = [];
preg_match_all('/\bid="([^"]+)"/', $daily, $dailyIdMatches);
preg_match_all('/\bid="([^"]+)"/', $report, $reportIdMatches);
$dailyIds = array_count_values($dailyIdMatches[1]);
$reportIds = array_count_values($reportIdMatches[1]);
$assert(count(array_filter($dailyIds, static fn (int $count): bool => $count > 1)) === 0
    && count(array_filter($reportIds, static fn (int $count): bool => $count > 1)) === 0,
    'both rendered view templates use unique literal control and modal identifiers');
$assert(str_contains($report, 'id="punch-report-csv-form"')
    && strpos($report, 'id="punch-report-csv-form"') < strpos($report, 'id="punch-report-filter-modal"')
    && str_contains($report, 'if (!empty($canDownload))')
    && str_contains($report, 'name="export" value="csv"')
    && str_contains($report, 'name="dt_search"')
    && str_contains($report, 'name="dt_order_column"'), 'CSV remains a permission-gated independent form carrying applied filters and table state');
$assert(str_contains($filtersJs, "modal.addEventListener('hidden.bs.modal'")
    && str_contains($filtersJs, 'filterForm.reset()')
    && str_contains($filtersJs, 'data-checador-clear-filters')
    && str_contains($filtersJs, "['date', 'from_date', 'to_date', 'worker_id']")
    && !str_contains($filtersJs, "['date', 'from_date', 'to_date', 'worker_id', 'workplace_id']")
    && str_contains($filtersJs, 'dt_search')
    && str_contains($filtersJs, 'dt_order_direction'), 'cancel restores the server-applied form state and CSV receives current DataTables search/order');
$assert(str_contains($dataTable, 'serverSide: true')
    && str_contains($dataTable, 'pageLength: 25')
    && str_contains($dataTable, "request.from_date = document.getElementById('from_date').value")
    && str_contains($dataTable, "form.addEventListener('submit', function ()"), 'DataTables server-side query and own search remain intact while GET submit synchronizes URL and resets paging');
$assert(str_contains($controller, 'checador.filters.init.js')
    && str_contains($controller, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY"), 'Checador-only modal behavior is loaded without replacing the shared station context');

printf("CHECADOR_FILTER_MODAL_V6B2_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

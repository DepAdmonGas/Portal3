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
    && str_contains($daily, 'id="worker_id" name="worker_id" class="form-select"')
    && str_contains($daily, 'data-checador-ajax')
    && !str_contains($daily, 'name="workplace_id"')
    && str_contains($controller, 'ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY)')
    && str_contains($controller, 'ChecadorAccessService::authorize($requestedWorkplace)'), 'M01 filter keeps date and roster worker controls while station scope remains server-authorized');
$assert(str_contains($report, 'id="punch-report-filter-modal"')
    && str_contains($report, 'id="from_date" name="from_date" type="date"')
    && str_contains($report, 'id="to_date" name="to_date" type="date"')
    && str_contains($report, 'type="hidden" id="worker_id" name="worker_id"')
    && str_contains($report, 'id="punch-report-worker" name="worker_id"')
    && str_contains($report, 'data-search-url="/departamento-operativo/recursos-humanos/checador/reporte/colaboradores"'), 'raw report modal and Select2 retain the UTC range and canonical collaborator filter');
$assert(substr_count($daily, 'id="checador-filter-modal"') === 1
    && substr_count($report, 'id="punch-report-filter-modal"') === 1
    && substr_count($daily, 'data-bs-target="#checador-filter-modal"') >= 1
    && substr_count($report, 'data-bs-target="#punch-report-filter-modal"') === 1
    && str_contains($daily, 'Filtros')
    && str_contains($report, 'Filtros'), 'both screens reuse one Bootstrap filter modal; dashboard navigation may open the same filter dialog');
$assert(substr_count($report, 'data-checador-filter-form') === 1
    && str_contains($report, 'type="submit"')
    && substr_count($report, 'data-checador-clear-filters') === 1
    && str_contains($report, 'Cancelar'), 'M02 modal has one filter form with apply, clear, and cancel controls');
$assert(!preg_match('/<select\b[^>]*\bname="workplace_id"/i', $report)
    && !str_contains($report, 'name="workplace_id"')
    && str_contains($controller, '$this->sessionWorkplaceId($access)'),
    'M02 locality is derived from the authorized server session rather than a browser field');
$assert(substr_count($report, 'id="punch-report-filter-modal"') === 1
    && substr_count($report, 'id="punch-report-filter-form"') === 1
    && substr_count($report, 'id="punch-report-worker"') === 1,
    'M02 filter and worker controls have unique IDs; mutually exclusive result headings may share a label ID');
$assert(str_contains($report, 'id="punch-report-csv-form"')
    && str_contains($report, 'if (!empty($canDownload))')
    && str_contains($report, 'name="export" value="csv"')
    && str_contains($report, 'name="dt_search"')
    && str_contains($report, 'name="dt_order_column"'), 'CSV remains a permission-gated independent form carrying applied filters and table state');
$assert(str_contains($filtersJs, "modal.addEventListener('hidden.bs.modal', resetDraft)"), 'M01 AJAX filter cancel restores its captured draft');
$assert(str_contains($filtersJs, "['dt_search', 'dt_order_column', 'dt_order_direction']")
    && str_contains($filtersJs, "punchReportFilterForm.addEventListener('submit'"), 'M02 CSV state synchronization remains separate from M01 AJAX filters');
$assert(str_contains($dataTable, 'serverSide: true')
    && str_contains($dataTable, 'pageLength: 25')
    && str_contains($dataTable, "request.from_date = document.getElementById('from_date').value")
    && str_contains($dataTable, "form.addEventListener('submit', function ()"), 'DataTables server-side query and own search remain intact while GET submit synchronizes URL and resets paging');
$assert(str_contains($controller, 'checador.filters.init.js')
    && str_contains($controller, "'moduleStationKey' => ChecadorAccessService::MODULE_KEY"), 'Checador-only modal behavior is loaded without replacing the shared station context');

printf("CHECADOR_FILTER_MODAL_V6B2_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

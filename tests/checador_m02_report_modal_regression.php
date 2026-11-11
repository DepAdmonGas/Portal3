<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php');
$filters = (string) file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js');
$controller = (string) file_get_contents($root . '/app/Controllers/ChecadorController.php');
$checks = [
    'report modal owns one date/worker filter form and leaves authorized locality outside it' =>
        substr_count($view, 'id="punch-report-filter-modal"') === 1
        && substr_count($view, 'id="punch-report-filter-form"') === 1
        && str_contains($view, 'name="from_date"') && str_contains($view, 'name="to_date"')
        && str_contains($view, 'name="worker_id"'),
    'cancel restores the report modal draft without resetting server-applied values or locality scope' =>
        str_contains($filters, "punchReportFilterModal.addEventListener('hidden.bs.modal'")
        && str_contains($filters, 'punchReportAppliedValues.forEach')
        && str_contains($filters, 'punchReportSubmitted'),
    'CSV receives the applied DataTables search and order state' =>
        str_contains($filters, "['dt_search', 'dt_order_column', 'dt_order_direction']")
        && str_contains($filters, 'punchReportFilterForm.querySelector'),
    'M02 derives locality from authorized session and exposes no browser-selectable workplace' =>
        !preg_match('/<select\b[^>]*\bname="workplace_id"/i', $view)
        && !str_contains($view, 'name="workplace_id"')
        && str_contains($controller, '$this->sessionWorkplaceId($access)'),
    'report remains a server-side read and exports through the existing handler' =>
        str_contains($view, 'data-endpoint="/departamento-operativo/recursos-humanos/checador/reporte/data"')
        && str_contains($controller, 'streamPunchReportDataTableCsv'),
];
$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$passed) $failed++;
}
printf("M02_REPORT_MODAL_RESULT: %d PASS / %d FAIL\n", count($checks) - $failed, $failed);
exit($failed === 0 ? 0 : 1);

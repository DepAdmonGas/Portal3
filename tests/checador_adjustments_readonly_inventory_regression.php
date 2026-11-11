<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$service = file_get_contents($root . '/app/Services/ChecadorDailyReadService.php');
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php');
$departmentController = file_get_contents($root . '/app/Controllers/DptoOperativoController.php');
$routes = file_get_contents($root . '/routes/web.php');
$view = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-adjustments.php');
$adjustmentsCss = file_get_contents($root . '/public/assets/css/checador-adjustments.css');
$js = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.adjustments.init.js');
$assertions = [];
$assertions[] = str_contains($service, "'api/v2/administrative-adjustments'")
    && str_contains($service, "rawurlencode(" . '$uuid' . ")");
$assertions[] = str_contains($routes, "addGroup('/departamento-operativo'")
    && str_contains($routes, "'/recursos-humanos/checador/ajustes/data'")
    && str_contains($routes, "'/recursos-humanos/checador/ajustes/detalle/{uuidAjuste}'")
    && !str_contains($routes, "'/departamento-operativo/recursos-humanos/checador/ajustes/data'")
    && str_contains($routes, "Route::auth(['ChecadorController', 'administrativeAdjustmentsData'])");
$assertions[] = str_contains($controller, 'ChecadorAccessService::authorize($workplaceId)')
    && str_contains($controller, 'readAdministrativeAdjustments($user')
    && str_contains($controller, 'readAdministrativeAdjustmentDetail($user');
$listSection = explode('<section class="card" aria-labelledby="adjustment-workspace-heading">', $view, 2)[0];
$assertions[] = str_contains($listSection, 'checador-adjustments-table')
    && str_contains($listSection, 'Inventario de ajustes administrativos')
    && str_contains($listSection, 'No tienes permiso de lectura')
    && !str_contains($listSection, "require __DIR__ . '/_workspace-context.php'")
    && !preg_match('/<form[^>]+method="post"/i', $listSection);
$assertions[] = !preg_match('~https?://|/api/v2/|Authorization|Bearer~i', $js)
    && str_contains($js, 'credentials: \'same-origin\'')
    && str_contains($js, 'serverSide: true');
$assertions[] = str_contains($view, '/evaluations/evaluate')
    && str_contains($view, 'Autorizar FALTA explícitamente')
    && str_contains($view, 'checador-adjustment-detail-modal');
$newController = substr($controller, strpos($controller, 'public function administrativeAdjustmentsData()'), strpos($controller, 'private static function adjustmentsDataError') - strpos($controller, 'public function administrativeAdjustmentsData()'));
$newService = substr($service, strpos($service, 'public function readAdministrativeAdjustments('), strpos($service, 'public function executeAdministrativeEvaluation(') - strpos($service, 'public function readAdministrativeAdjustments('));
$assertions[] = !preg_match('/(?:INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM|CREATE\s+TABLE|ALTER\s+TABLE)/i', $newService . $newController);
$adjustmentBranchStart = strpos($controller, "...(\$workspace === 'adjustments' ? [");
$adjustmentBranchEnd = $adjustmentBranchStart === false ? false : strpos($controller, "] : []),", $adjustmentBranchStart);
$adjustmentScriptBranch = $adjustmentBranchStart === false || $adjustmentBranchEnd === false
    ? '' : substr($controller, $adjustmentBranchStart, $adjustmentBranchEnd - $adjustmentBranchStart);
$assertions[] = !str_contains($adjustmentScriptBranch, 'module-station-selector.js')
    && str_contains($controller, "...(\$workspace === 'daily-attendance' ? [] : ['/assets/js/core/module-station-selector.js'])");
$assertions[] = str_contains($view, 'adjustments-filter-field')
    && str_contains($js, "dropdownCssClass: 'adjustments-filter-dropdown'")
    && str_contains($js, "processing: 'Consultando ajustes…'");
$assertions[] = str_contains($controller, 'workersForAttendanceFilter($requestedWorkplace, $date, null, false)')
    && str_contains($controller, "\$workspace === 'adjustments'")
    && str_contains($view, 'data-workplace-id="<?= (int) ($selectedWorkplaceId ?? 0) ?>"')
    && str_contains($view, 'data-default-date=');
$adjustmentDataMethodStart = strpos($controller, 'public function administrativeAdjustmentsData()');
$adjustmentDataMethodEnd = $adjustmentDataMethodStart === false ? false : strpos($controller, 'private static function adjustmentsDataError', $adjustmentDataMethodStart);
$adjustmentDataMethod = $adjustmentDataMethodStart === false || $adjustmentDataMethodEnd === false
    ? '' : substr($controller, $adjustmentDataMethodStart, $adjustmentDataMethodEnd - $adjustmentDataMethodStart);
$assertions[] = str_contains($adjustmentDataMethod, 'workersForAttendanceFilter($workplaceId, $dateTo)')
    && str_contains($adjustmentDataMethod, "'worker_options' => \$workerOptions")
    && str_contains($adjustmentDataMethod, "'worker_roster_status' => \$workerRosterStatus")
    && str_contains($js, 'refreshWorkerOptions(response.worker_options')
    && str_contains($js, 'minimumResultsForSearch: 0');
$assertions[] = str_contains($view, 'Buscar / Filtrar')
    && str_contains($view, 'id="checador-adjustment-search-modal"')
    && str_contains($view, 'id="checador-adjustments-filters"')
    && str_contains($view, 'name="checador-adjustment-search-modal"') === false
    && str_contains($view, 'Localidad autorizada')
    && str_contains($view, 'Consultar ajustes')
    && str_contains($view, 'Limpiar filtros')
    && substr_count($view, 'id="adjustments-worker"') === 1
    && substr_count($view, 'id="adjustments-date-from"') === 1;
$assertions[] = str_contains($js, 'dropdownParent: $searchModal')
    && str_contains($js, 'hidden.bs.modal')
    && str_contains($js, 'adjustments-reset-filters')
    && str_contains($js, 'appliedFilters = readFilters()')
    && str_contains($js, 'DataTable.isDataTable(table)');
$assertions[] = is_file($root . '/public/assets/css/checador-adjustments.css')
    && str_contains($controller, 'checador-adjustments.css?v=20261109-3')
    && str_contains($controller, 'checador.adjustments.init.js?v=20261109-4');
$assertions[] = str_contains($view, 'Evaluaciones administrativas')
    && str_contains($view, '/evaluations/evaluate');
$assertions[] = str_contains($js, 'ajax: function (request, callback)')
    && str_contains($js, "type: 'GET'")
    && str_contains($js, 'callback(emptyServerResponse(request.draw))')
    && str_contains($js, 'status === 401')
    && str_contains($js, 'status === 403')
    && str_contains($js, 'status === 404')
    && str_contains($js, 'status >= 500')
    && str_contains($js, 'enableFilterControls')
    && !preg_match('/\berror\s*:\s*function\s*\(xhr\)/', $js);
$assertions[] = str_contains($departmentController, "unset(\$elementos['acceso-temporal'], \$elementos['checador-v2-ajustes']);")
    && str_contains($departmentController, 'capacidades internas de Checador, no módulos raíz');
$assertions[] = str_contains($view, 'card-header bg-primary text-white d-flex align-items-center gap-2 gap-md-3')
    && str_contains($view, 'ti ti-adjustments fs-5')
    && str_contains($view, 'Consulta de ajustes registrados y su trazabilidad');
$assertions[] = str_contains($view, 'card-header bg-light text-primary d-flex align-items-center gap-2')
    && str_contains($view, 'ti ti-clipboard-check fs-5')
    && str_contains($view, 'Evaluaciones administrativas');
$assertions[] = str_contains($view, 'adjustments-table-wrap')
    && str_contains($view, 'class="table table-striped table-bordered align-middle mb-0 w-100"')
    && str_contains($view, '<th scope="col">Fecha</th>');
$assertions[] = str_contains($adjustmentsCss, '.adjustments-table-wrap')
    && str_contains($adjustmentsCss, 'overflow-y: hidden')
    && str_contains($adjustmentsCss, '#checador-adjustments-table_wrapper .dataTables_paginate');
$assertions[] = str_contains($view, 'adjustments-final-falta-note')
    && str_contains($view, 'La autorización final de FALTA permanece separada de la evaluación y requiere permiso específico y confirmación humana.')
    && str_contains($adjustmentsCss, '[data-bs-theme="light"] .adjustments-final-falta-note')
    && str_contains($adjustmentsCss, '--bs-alert-color: var(--bs-body-color)')
    && str_contains($adjustmentsCss, '[data-bs-theme="dark"] .adjustments-final-falta-note')
    && str_contains($adjustmentsCss, '--bs-alert-color: var(--bs-light-text-emphasis)');
$assertions[] = !str_contains($view, "require __DIR__ . '/_workspace-context.php'")
    && str_contains($view, 'id="adjustments-workplace"');
$assertions[] = str_contains($js, 'drawCallback: function ()')
    && str_contains($js, "'.dataTables_paginate'")
    && str_contains($js, "if (emptyCell) emptyCell.textContent = ''")
    && str_contains($js, 'No se encontraron ajustes administrativos para los filtros seleccionados.');

$failed = 0;
foreach ($assertions as $index => $passed) {
    if (!$passed) {
        $failed++;
        fwrite(STDERR, 'FAIL AJUSTES-READ-' . ($index + 1) . PHP_EOL);
    }
}
echo sprintf("AJUSTES_READONLY_INVENTORY_RESULT: %d/%d PASS\n", count($assertions) - $failed, count($assertions));
exit($failed === 0 ? 0 : 1);

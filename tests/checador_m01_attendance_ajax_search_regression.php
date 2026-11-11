<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
$view = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php') ?: '';
$filters = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js') ?: '';
$dataTable = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js') ?: '';
$routes = file_get_contents($root . '/routes/web.php') ?: '';

$checks = [
    'the existing attendance URI accepts authenticated GET navigation and POST search' =>
        str_contains($routes, "addRoute('GET', '/recursos-humanos/checador/asistencia', Route::auth(['ChecadorController', 'dailyAttendance']))")
        && str_contains($routes, "addRoute('POST', '/recursos-humanos/checador/asistencia', Route::auth(['ChecadorController', 'dailyAttendance']))"),
    'POST requires JSON and XMLHttpRequest and accepts only date and worker_id' =>
        str_contains($controller, "str_starts_with(\$contentType, 'application/json')")
        && str_contains($controller, "\$requestedWith !== 'XMLHTTPREQUEST'")
        && str_contains($controller, "\$payloadKeys !== ['date', 'worker_id']")
        && str_contains($controller, 'Request::json()'),
    'the POST response is JSON containing the shared server-rendered view fragment' =>
        str_contains($controller, "'success' => true")
        && str_contains($controller, "'html' => \$html")
        && str_contains($controller, "'data-checador-attendance-results'")
        && str_contains($controller, "\$workspace === 'daily-attendance' && Request::method() === 'POST'"),
    'locality remains session-derived and the search keeps the established attendance URL' =>
        str_contains($controller, '$requestedWorkplace = $this->sessionWorkplaceId($access)')
        && str_contains($filters, "var attendanceUrl = '/departamento-operativo/recursos-humanos/checador/asistencia'")
        && str_contains($routes, "Route::auth(['ChecadorController', 'dailyAttendance'])"),
    'Search and Clear use the delegated Alpine action handlers without native attendance form submission' =>
        str_contains($view, 'data-checador-search-action="search"')
        && str_contains($view, 'data-checador-search-action="clear"')
        && !str_contains($view, '@click="search()"')
        && !str_contains($view, '@click="clearFilters()"')
        && str_contains($filters, "document.querySelectorAll('[data-checador-search-action]')")
        && str_contains($filters, 'alpineState.search()')
        && str_contains($filters, 'alpineState.clearFilters()')
        && !str_contains($view, '<form')
        && !str_contains($view, '@submit')
        && str_contains($view, 'x-model="fecha"')
        && str_contains($view, 'x-model="colaborador"')
        && str_contains($view, ':disabled="cargando"')
        && str_contains($filters, 'fechaAplicada')
        && str_contains($filters, 'colaboradorAplicado'),
    'Axios sends one same-origin POST JSON request with CSRF and receives JSON' =>
        str_contains($filters, "window.axios.post(attendanceUrl, payload")
        && str_contains($filters, "'Content-Type': 'application/json'")
        && str_contains($filters, "'Accept': 'application/json'")
        && str_contains($filters, "'X-CSRF-TOKEN': csrfToken")
        && str_contains($filters, "'X-Requested-With': 'XMLHttpRequest'")
        && str_contains($filters, "responseType: 'text'")
        && str_contains($filters, "status === 419")
        && str_contains($filters, 'JSON.parse(result)')
        && str_contains($filters, 'if (this.cargando)'),
    'results, authorized selector options, details, and the nine-column DataTable update in place' =>
        str_contains($view, 'data-checador-attendance-results')
        && str_contains($filters, 'previousResults.replaceWith(nextResults)')
        && str_contains($filters, 'previousWorkerSelect.replaceChildren')
        && str_contains($filters, 'window.initChecadorAttendanceDataTable(nextResults) === true')
        && str_contains($filters, 'jQuery(previousTable).DataTable().destroy()')
        && str_contains($dataTable, 'return true;')
        && str_contains($view, '<th scope="col">Nombre completo</th>')
        && str_contains($view, 'data-bs-toggle="modal" data-bs-target="#<?= $esc($viewRow[\'modal_id\']) ?>"'),
    'errors keep previous results, show a retryable message, and temporary diagnostics are removed' =>
        str_contains($filters, 'data-checador-search-error')
        && str_contains($filters, 'attendance_result_update_failed')
        && str_contains($filters, 'this.error = errorMessage(error)')
        && str_contains($filters, 'previousResults.replaceWith(nextResults)')
        && !str_contains($filters, 'window.location.reload')
        && !preg_match('/console\.(?:log|info|warn|debug)|\[Portal3 Checador\]|alpine\.initialized|buscar\.axios\.(?:start|response)/', $filters),
    'cancel, close, and reopen discard drafts and restore only Alpine applied filters' =>
        str_contains($filters, "modal.addEventListener('hidden.bs.modal'")
        && str_contains($filters, "modal.addEventListener('show.bs.modal'")
        && str_contains($filters, 'component.discardDraft()')
        && str_contains($filters, 'component.prepareDraft()')
        && str_contains($filters, 'this.fecha = this.fechaAplicada')
        && str_contains($filters, 'this.colaborador = this.colaboradorAplicado')
        && !str_contains($filters, 'form.reset()'),
    'Clear queries the server default date and All, without navigation' =>
        str_contains($filters, 'return this.search(true)')
        && str_contains($filters, 'date: isClear ? null : this.fecha')
        && str_contains($filters, 'worker_id: isClear || this.colaborador === \'\' ? null : this.colaborador')
        && str_contains($filters, 'this.fechaAplicada = this.fecha')
        && str_contains($filters, 'this.colaboradorAplicado = this.colaborador')
        && !str_contains($filters, 'window.location.assign(attendanceUrl)'),
    'the AJAX search does not navigate or add filter values to the browser URL' =>
        str_contains($filters, "var attendanceUrl = '/departamento-operativo/recursos-humanos/checador/asistencia'")
        && !str_contains($filters, 'history.pushState')
        && !str_contains($filters, 'history.replaceState')
        && !str_contains($filters, 'window.location.search =')
        && !str_contains($filters, 'window.location.assign(attendanceUrl)')
        && str_contains($filters, "date: isClear ? null : this.fecha"),
];

$failures = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$passed) $failures++;
}

printf("CHECADOR_M01_ATTENDANCE_AJAX_SEARCH: %d PASS / %d FAIL\n", count($checks) - $failures, $failures);
exit($failures === 0 ? 0 : 1);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ChecadorController;
use App\Services\ChecadorPresentationFormatter;

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

$controllerPath = __DIR__ . '/../app/Controllers/ChecadorController.php';
$accessPath = __DIR__ . '/../app/Services/ChecadorAccessService.php';
$databasePath = __DIR__ . '/../app/Core/Database.php';
$readerPath = __DIR__ . '/../app/Services/ChecadorRawPunchReadService.php';
$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php';
$formatterPath = __DIR__ . '/../app/Services/ChecadorPresentationFormatter.php';
$legacyViewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php';
$datatablePath = __DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.datatable.init.js';
$routePath = __DIR__ . '/../routes/web.php';
$controller = (string) file_get_contents($controllerPath);
$access = (string) file_get_contents($accessPath);
$database = (string) file_get_contents($databasePath);
$reader = (string) file_get_contents($readerPath);
$view = (string) file_get_contents($viewPath);
$formatter = (string) file_get_contents($formatterPath);
$legacyView = (string) file_get_contents($legacyViewPath);
$datatableJs = (string) file_get_contents($datatablePath);
$route = (string) file_get_contents($routePath);

$start = strpos($controller, 'public function punchReport(): void');
$end = strpos($controller, 'public function punchReportData(): void', $start === false ? 0 : $start);
$reportMethod = $start !== false && $end !== false ? substr($controller, $start, $end - $start) : '';
$dataStart = strpos($controller, 'public function punchReportData(): void');
$dataEnd = strpos($controller, 'public function payrollComparison(): void', $dataStart === false ? 0 : $dataStart);
$dataMethod = $dataStart !== false && $dataEnd !== false ? substr($controller, $dataStart, $dataEnd - $dataStart) : '';

$assert($reportMethod !== '' && str_contains($reportMethod, 'new ChecadorRawPunchReadService()') && str_contains($reportMethod, 'readDataTablePage('), 'MVP report uses the approved raw SQL reader');
$assert(!str_contains($reportMethod, 'new ChecadorDailyReadService()') && !str_contains($reportMethod, 'readPunchReportDataSet(') && !str_contains($reportMethod, '/api/v2/'), 'MVP report has no API HTTP dependency');
$assert(str_contains($reportMethod, 'ChecadorAccessService::authorize(null)')
    && str_contains($reportMethod, 'sessionWorkplaceId($access)')
    && str_contains($reportMethod, 'ChecadorAccessService::authorize($selectedWorkplaceForView)'), 'server-authorized locality scope is checked from session before SQL report queries');
$assert(str_contains($reportMethod, "'title' => 'Reporte de marcaciones'") && str_contains($reportMethod, "Breadcrumb::add('Recursos Humanos'") && str_contains($reportMethod, "Breadcrumb::add('Reporte de marcaciones'"), 'report uses one specific institutional title and breadcrumb conventions');
$scopeCheckPosition = strpos($reportMethod, 'requested_station_out_of_scope');
$sqlReadPosition = strpos($reportMethod, '$reader->readDataTablePage(');
$assert($scopeCheckPosition !== false && $sqlReadPosition !== false && $scopeCheckPosition < $sqlReadPosition, 'requested URL station is denied before raw SQL reads');
$assert(str_contains($reportMethod, 'ChecadorAccessService::authorize($requestedWorkplace)') && str_contains($reportMethod, 'empty($downloadAccess[\'descargar\'])'), 'CSV rechecks current station scope and effective download permission');
$assert(str_contains($dataMethod, '(new ChecadorRawPunchReadService())->readDataTablePage(')
    && !str_contains($dataMethod, 'ChecadorDailyReadService')
    && !str_contains($dataMethod, '/api/v2/')
    && str_contains($dataMethod, 'ModuleStationService::getContext(ChecadorAccessService::MODULE_KEY)')
    && str_contains($dataMethod, '$submittedWorkplace !== $requestedWorkplace'), 'DataTables endpoint reads the local SQL model by session locality and rejects mismatched technical IDs');
$assert(str_contains($controller, 'public function punchReportWorkers(): void')
    && str_contains($route, '/reporte/colaboradores')
    && str_contains($controller, 'searchReportWorkers($localityId, $roster[\'worker_ids\'], $term, $page)')
    && str_contains($controller, 'punchReportAuthorizedRoster($localityId)')
    && str_contains($reader, 'person.id = punches.id_personal_resuelto'), 'Select2 search combines canonical active roster names with persisted historical punch identities');
$assert(str_contains($controller, 'findReportWorker(')
    && str_contains($dataMethod, 'findReportWorker(')
    && str_contains($reportMethod, 'selectedWorkerOption'), 'selected report worker is revalidated against active roster or persisted locality attribution for page, DataTables, and CSV');
$assert(str_contains($access, '\'descargar\' => !empty($parent[\'descargar\'])') && str_contains($access, 'ENCARGADO_PUESTO_ID = 6') && str_contains($access, 'DIRECCION_OPERACIONES_PUESTO_ID = 13'), 'existing role scope is preserved and effective parent download is returned');
$assert(str_contains($access, 'return $homeStationId > 0 && in_array($homeStationId, $supportedStationIds, true)') && str_contains($access, 'return $supportedStationIds;'), 'Encargado remains on primary station and Dirección stays within existing Checador allowlist');

$forbiddenSql = preg_match('/\b(INSERT\s+INTO|UPDATE\s+[a-z0-9_]+\s+SET|DELETE\s+FROM|REPLACE\s+INTO|CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE|REVOKE)\b/i', $reader) === 1;
$assert(str_contains($reader, 'SELECT COUNT(*)') && str_contains($reader, 'SELECT punches.id, punches.event_uuid') && !$forbiddenSql, 'raw SQL repository contains SELECT reads only');
$assert(str_contains($reader, 'id_estacion_resuelta = ?') && str_contains($reader, 'ocurrido_at_utc >= ?') && str_contains($reader, 'ocurrido_at_utc < ?') && str_contains($reader, 'id_personal_resuelto = ?'), 'SQL has mandatory station, half-open UTC, and canonical identity filters');
$assert(str_contains($reader, 'resolveCanonicalLocalityId') && str_contains($reader, 'id_estacion_resuelta = ?')
    && !str_contains($reader, 'resolvePhysicalStationId')
    && str_contains($reader, 'duplicate.event_uuid') && str_contains($reader, 'duplicate.id_estacion_resuelta'), 'canonical locality is validated in its own ID space and duplicate UUIDs are collapsed within locality scope');
$assert(str_contains($reader, 'CAPTURE_METHOD_FILTERS')
    && str_contains($reader, 'PROCESSING_STATUS_FILTERS')
    && str_contains($reader, 'punches.metodo_captura = ?')
    && str_contains($reader, 'punches.processing_status = ?'), 'type and processing filters are allowlisted and parameterized in SQL');
$assert(!str_contains($database, 'checador_v2_read')
    && !str_contains($database, 'CHECADOR_V2_READ_DB_')
    && !str_contains($reader, 'checador_v2_read')
    && !str_contains($reader, 'CHECADOR_V2_READ_DB_')
    && !str_contains($reader, 'bd_admongas_v2_preprod'), 'unauthorized named/preprod connection configuration is absent');
$assert(!str_contains($reader, 'effective_timezone') && str_contains($view, 'Horas en UTC')
    && str_contains($formatter, "'REJECTED' => 'Marcación rechazada'"), 'report keeps UTC-only time presentation while preserving the Spanish rejected-state label');
$assert(!str_contains($reader, 'integrity_seal') && !str_contains($reader, 'integrity_key_id') && !str_contains($reader, 'metadata_json'), 'SQL projection excludes integrity secrets and unneeded metadata');
$assert(str_contains($reader, 'LIMIT ? OFFSET ?') && str_contains($reader, 'MAX_PAGE_SIZE = 200') && str_contains($reader, 'MAX_OFFSET = 100000'), 'SQL pagination is parameterized, capped, and deterministic');
$assert(str_contains($reader, "'bd_admongas'") && str_contains($reader, "'fedora'") && str_contains($reader, "['dev', 'development', 'local']"), 'raw database connection is blocked outside approved local development target');
$assert(str_contains($reader, 'NOT_VERIFIED_BY_PORTAL3') && str_contains($reader, 'OCCURRENCE_TIME_UNRESOLVED'), 'reader marks integrity and unresolved occurrence without asserting validity');

$reflection = new ReflectionMethod(App\Services\ChecadorRawPunchReadService::class, 'readPage');
$service = new App\Services\ChecadorRawPunchReadService();
$invalidRange = $reflection->invoke($service, 1, '2026-02-01 00:00:00', '2026-03-05 00:00:00', null, 1, 50, 'asc');
$assert(($invalidRange['error']['kind'] ?? '') === 'bad_request', 'range over 31 exact UTC days fails before database access');
$invalidSort = $reflection->invoke($service, 1, '2026-02-01 00:00:00', '2026-02-02 00:00:00', null, 1, 50, 'sideways');
$assert(($invalidSort['error']['kind'] ?? '') === 'bad_request', 'sort direction is allowlisted');
$dataTableMethod = new ReflectionMethod(App\Services\ChecadorRawPunchReadService::class, 'readDataTablePage');
$invalidDataTableLength = $dataTableMethod->invoke($service, 1, '2026-02-01 00:00:00', '2026-02-02 00:00:00', null, 0, 200, '', 1, 'asc');
$invalidDataTableOrder = $dataTableMethod->invoke($service, 1, '2026-02-01 00:00:00', '2026-02-02 00:00:00', null, 0, 25, '', 7, 'asc');
$invalidDataTableSearch = $dataTableMethod->invoke($service, 1, '2026-02-01 00:00:00', '2026-02-02 00:00:00', null, 0, 25, str_repeat('a', 101), 1, 'asc');
$assert(($invalidDataTableLength['error']['kind'] ?? '') === 'bad_request'
    && ($invalidDataTableOrder['error']['kind'] ?? '') === 'bad_request'
    && ($invalidDataTableSearch['error']['kind'] ?? '') === 'bad_request', 'DataTables page size, sortable columns and search size are strictly allowlisted before SQL access');
$searchClause = new ReflectionMethod(App\Services\ChecadorRawPunchReadService::class, 'searchClause');
$escapedSearch = $searchClause->invoke(null, 'a_%!', 'Interlomas');
$assert(str_contains((string) ($escapedSearch[0] ?? ''), "LIKE ? ESCAPE '!'")
    && ($escapedSearch[1][0] ?? null) === '%a!_!%!!%'
    && ($escapedSearch[1][count($escapedSearch[1]) - 2] ?? null) === 'Interlomas'
    && ($escapedSearch[1][count($escapedSearch[1]) - 1] ?? null) === '%a!_!%!!%', 'global-search metacharacters and server-authorized locality label are parameterized as literal text');
$csvBuilder = new ReflectionMethod(ChecadorController::class, 'punchReportCsvContent');
$formulaCsv = $csvBuilder->invoke(null, [['event_uuid' => '=1+1']], 1, 'Test');
$assert(is_string($formulaCsv) && str_contains($formulaCsv, "'=1+1"), 'CSV spreadsheet formula-like values are escaped');
$spanishCsv = $csvBuilder->invoke(null, [[
    'id' => 42,
    'event_uuid' => 'event-uuid-42',
    'id_personal_resuelto' => 558,
    'id_dispositivo' => 2,
    'source_timestamp_raw' => '2026-02-01 13:14:07',
    'source_timezone_reported' => 'America/Mexico_City',
    'ocurrido_at_utc' => null,
    'recibido_at_utc' => '2026-02-01 19:14:07',
    'metodo_captura' => 'BIOMETRIC',
    'processing_status' => 'RECEIVED',
    'integrity_status' => 'VALID',
]], 1, 'Interlomas');
$assert(is_string($spanishCsv)
    && str_contains($spanishCsv, 'Identificador del registro')
    && str_contains($spanishCsv, 'Marcación recibida')
    && str_contains($spanishCsv, 'Registro biométrico')
    && str_contains($spanishCsv, 'Colaborador identificado')
    && str_contains($spanishCsv, 'Integridad no verificada por Portal3')
    && str_contains($spanishCsv, 'Hora no determinada; se muestra la fecha y hora de recepción')
    && !str_contains($spanishCsv, 'event_uuid')
    && !str_contains($spanishCsv, 'id_personal_resuelto')
    && !str_contains($spanishCsv, 'id_raw_punch')
    && !str_contains($spanishCsv, 'processing_status')
    && !str_contains($spanishCsv, 'RECEIVED')
    && !str_contains($spanishCsv, 'BIOMETRIC')
    && !str_contains($spanishCsv, 'VALID'), 'CSV uses Spanish labels and never claims cryptographic verification from a stored code');

$routeDispatcher = \FastRoute\simpleDispatcher(require $routePath);
$resolved = $routeDispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/reporte');
$handler = $resolved[1] ?? null;
$config = $handler instanceof Closure ? (new ReflectionFunction($handler))->getStaticVariables() : [];
$assert(($resolved[0] ?? null) === \FastRoute\Dispatcher::FOUND
    && $handler instanceof Closure
    && ($config['middlewares'] ?? null) === ['auth', 'csrf']
    && ($config['handler'] ?? null) === ['ChecadorController', 'punchReport'], 'report route retains existing authentication and CSRF middleware');
$dataRoute = $routeDispatcher->dispatch('GET', '/departamento-operativo/recursos-humanos/checador/reporte/data');
$dataHandler = $dataRoute[1] ?? null;
$dataConfig = $dataHandler instanceof Closure ? (new ReflectionFunction($dataHandler))->getStaticVariables() : [];
$assert(($dataRoute[0] ?? null) === \FastRoute\Dispatcher::FOUND
    && ($dataConfig['middlewares'] ?? null) === ['auth', 'csrf']
    && ($dataConfig['handler'] ?? null) === ['ChecadorController', 'punchReportData'], 'DataTables JSON route is protected by the existing authenticated middleware');
$assert(str_contains($view, 'Horas en UTC')
    && str_contains($controller, 'ChecadorPresentationFormatter::integrityLabel()')
    && str_contains($controller, "'event_time_display' =>")
    && str_contains($controller, "'ocurrido_at_utc' =>"), 'view and JSON projection show UTC timestamps and unverified integrity');
$assert(str_contains($view, 'name="worker_id"') && !str_contains($view, 'name="workplace_id"')
    && str_contains($view, 'from_date') && str_contains($view, 'to_date')
    && str_contains($view, 'name="capture_method"') && str_contains($view, 'name="processing_status"'), 'view filters by canonical worker, UTC dates, persisted capture method and processing status without a URL locality field');
$assert(str_contains($view, 'if (!empty($canDownload))') && str_contains($datatableJs, 'pageLength: 25') && str_contains($datatableJs, 'lengthMenu: [10, 25, 50, 100]'), 'CSV control follows download permission and DataTables provides the only page-size/pagination controls');
$assert(str_contains($datatableJs, "url: '/assets/libs/datatables.net/js/es-ES.json'")
    && str_contains($view, 'Sin marcaciones en el periodo consultado')
    && str_contains($view, 'Prueba con otro periodo o ajusta los filtros.')
    && str_contains($datatableJs, 'updateEmptyState'), 'empty results use the compact report empty state without hiding service errors');
$assert(str_contains($view, 'no convierte la hora del dispositivo')
    && str_contains($datatableJs, 'Hora de marcación no determinada')
    && !str_contains($controller, "'occurred_local' =>")
    && !str_contains($view, 'zona efectiva de V2'), 'report shows UTC occurrence/receipt values and does not infer a workplace-local time');
$assert(str_contains($view, 'class="datatables"')
    && str_contains($view, 'class="table-responsive overflow-x-auto pb-4"')
    && str_contains($view, 'class="table table-striped table-bordered mb-0 text-nowrap align-middle" width="100%"')
    && str_contains($datatableJs, "title: 'Estación'")
    && str_contains($datatableJs, "title: 'Fecha (UTC)'")
    && str_contains($datatableJs, "title: 'Hora (UTC)'")
    && str_contains($datatableJs, "title: 'Origen'")
    && str_contains($datatableJs, 'formatUtcTime12Hour')
    && !str_contains($view, '<thead'), 'report reuses the Día Doble table HTML structure and institutional responsive classes');
$assert(str_contains($view, 'id="checador-punch-detail-modal"') && str_contains($view, 'modal-dialog-scrollable') && str_contains($view, 'aria-labelledby') && str_contains($datatableJs, 'bootstrap.Modal.getOrCreateInstance'), 'each event detail uses the shared accessible Bootstrap modal');
$assert(str_contains($view, 'Hora original informada por el dispositivo') && str_contains($view, 'Fecha y hora de marcación (UTC)') && str_contains($view, 'Fecha y hora de recepción (UTC)') && str_contains($view, 'Dispositivo'), 'detail view distinguishes source time, UTC occurrence, receipt, and device');
$assert(str_contains($view, 'data-endpoint="/departamento-operativo/recursos-humanos/checador/reporte/data"')
    && str_contains($datatableJs, 'serverSide: true')
    && str_contains($datatableJs, "form.addEventListener('submit', function ()")
    && !str_contains($view, 'page-item'), 'server-side DataTables replaces the prior SQL paginator and filters reload one table');
$assert(str_contains($datatableJs, 'function findTableSearchInput(dataTable)')
    && str_contains($datatableJs, '(tableWrap || document)')
    && str_contains($datatableJs, 'var searchEventRoot = tableWrap || dataTable.table().container() || document')
    && str_contains($controller, 'checador.datatable.init.js?v=20261106-5'),
    'filter and CSV listeners use the current report wrapper and load the cache-busted initializer');
$assert(str_contains($controller, "'recordsTotal'") && str_contains($controller, "'recordsFiltered'")
    && str_contains($controller, 'ChecadorPresentationFormatter::processingStatusLabel')
    && str_contains($controller, 'ChecadorPresentationFormatter::captureMethodLabel')
    && str_contains($datatableJs, 'escapeHtml(row.processing_status)'), 'JSON protocol returns Spanish presentation labels rendered with HTML escaping');
$diaDobleJs = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/dia-doble.datatable.init.js');
$diaDobleController = file_get_contents(__DIR__ . '/../app/Controllers/DiaDobleController.php');
$assert(str_contains($datatableJs, "url: '/assets/libs/datatables.net/js/es-ES.json'")
    && str_contains($controller, '/assets/libs/datatables.net/js/jquery.dataTables.min.js')
    && !str_contains($controller, '/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js')
    && str_contains($diaDobleController, '/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css')
    && file_exists(__DIR__ . '/../public/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css')
    && file_exists(__DIR__ . '/../public/assets/libs/datatables.net/js/jquery.dataTables.min.js'), 'report reuses Día Doble local DataTables core, Bootstrap stylesheet, and Spanish language without a separate Bootstrap adapter');
$assert(str_contains($diaDobleJs, 'processing: false')
    && str_contains($datatableJs, 'processing: false')
    && str_contains($diaDobleJs, 'deferRender: true')
    && str_contains($datatableJs, 'deferRender: true')
    && str_contains($diaDobleJs, 'autoWidth: false')
    && str_contains($datatableJs, 'autoWidth: false')
    && str_contains($diaDobleJs, 'stateSave: false')
    && str_contains($datatableJs, 'stateSave: false')
    && str_contains($diaDobleJs, 'pageLength: 25')
    && str_contains($datatableJs, 'pageLength: 25')
    && str_contains($diaDobleJs, 'lengthMenu: [10, 25, 50, 100]')
    && str_contains($datatableJs, 'lengthMenu: [10, 25, 50, 100]')
    && str_contains($datatableJs, 'serverSide: true'), 'report matches Día Doble DataTables controls and configuration while retaining only the required server-side mode');
$assert(str_contains($datatableJs, 'showRequestError()')
    && str_contains($datatableJs, 'dataSrc: function (json)')
    && str_contains($datatableJs, 'La tabla no pudo inicializarse')
    && str_contains($controller, 'punchReportJsonError(403')
    && str_contains($controller, 'punchReportJsonError(422'), 'authorization and invalid JSON requests remain errors rather than successful empty results');
$assert(str_contains($datatableJs, 'searchField.value = searchInput ? searchInput.value : dataTable.search()')
    && str_contains($controller, "'dt_search'")
    && str_contains($controller, 'streamPunchReportDataTableCsv')
    && str_contains($controller, 'ChecadorRawPunchReadService'), 'CSV preserves the active DataTables search and streams the same authorized SQL result');
$assert(str_contains($view, 'id="punch-report-period"')
    && str_contains($view, 'Mes y año específicos')
    && str_contains($datatableJs, "periodSelect.value === 'specific'")
    && str_contains($view, 'data-bs-target="#punch-report-filter-modal"'), 'period controls are compact and retain the custom-range modal');
$assert(str_contains($view, 'card-header bg-primary text-white d-flex flex-wrap align-items-center')
    && str_contains($view, 'Marcaciones registradas')
    && str_contains($view, 'Exportar CSV')
    && !str_contains($view, 'Configuración</a>')
    && !str_contains($view, 'id="punch-report-filter-summary-title"'), 'report uses the M04 blue card header and omits redundant heading/action cards');
$assert(str_contains($view, 'id="punch-report-worker" name="worker_id"')
    && str_contains($view, 'Todos los colaboradores')
    && str_contains($view, 'Busca por nombre o apellido')
    && str_contains($view, "selectedWorkerOption['text']")
    && str_contains($datatableJs, 'minimumInputLength: 0')
    && str_contains($datatableJs, "term: params.term || ''")
    && str_contains($datatableJs, 'delay: 250')
    && str_contains($datatableJs, 'tags: false')
    && str_contains($controller, '/assets/libs/select2/dist/js/select2.full.min.js'), 'report Select2 loads the initial authorized roster without typing and preserves selected names');
$assert(str_contains($datatableJs, 'class="btn btn-sm btn-outline-primary" data-checador-detail')
    && str_contains($datatableJs, 'ti ti-eye me-1')
    && str_contains($datatableJs, "className: 'table-actions-cell'")
    && !str_contains($datatableJs, 'btn-table-action'), 'the single detail action uses the native Portal3 button pattern in its centered action cell');
$assert(str_contains($legacyView, '$journeyBaseUrl = \'/departamento-operativo/recursos-humanos/checador/jornadas\';')
    && str_contains($legacyView, '$incidenceBaseUrl = \'/departamento-operativo/recursos-humanos/checador/incidencias\';')
    && str_contains($controller, "'/reporte'")
    && !preg_match('/fetch\(|axios\.|XMLHttpRequest|Bearer /i', $view), 'daily attendance remains a separate legacy view and the report exposes no API credentials');

$assert(ChecadorPresentationFormatter::processingStatusLabel('RECEIVED') === 'Marcación recibida'
    && str_contains(ChecadorPresentationFormatter::processingStatusExplanation('RECEIVED'), 'no confirma identidad, jornada ni integridad'), 'received means receipt only and does not imply identity, journey, or integrity validation');
$assert(ChecadorPresentationFormatter::processingStatusLabel('PENDING_MATCH') === 'Identificación pendiente'
    && ChecadorPresentationFormatter::captureMethodLabel('BIOMETRIC') === 'Registro biométrico', 'verified pending-identity and biometric values have Spanish labels');
$assert(ChecadorPresentationFormatter::processingStatusLabel('REJECTED') === 'Marcación rechazada'
    && ChecadorPresentationFormatter::processingStatusBadgeClass('REJECTED') === 'text-bg-danger', 'rejected events receive an explicit Spanish error state when present in the raw source');
$eventFact = [
    'ocurrido_at_utc' => '2026-10-27 18:00:00',
    'effective_timezone' => 'America/Mexico_City',
];
$localEventTime = ChecadorPresentationFormatter::eventLocalDateTime($eventFact);
$assert($localEventTime === '2026-10-27 12:00'
    && $eventFact['ocurrido_at_utc'] === '2026-10-27 18:00:00'
    && ChecadorPresentationFormatter::eventLocalDateTime(['ocurrido_at_utc' => '2026-10-27 18:00:00']) === null,
    'local time is a display-only projection of the immutable UTC event and fails closed without V2 effective timezone');
$unknownLogPath = sys_get_temp_dir() . '/portal3-checador-presentation-' . getmypid() . '.log';
$_ENV['LOG_PATH'] = $unknownLogPath;
$unknownLabel = ChecadorPresentationFormatter::processingStatusLabel('FUTURE_STATUS_TEST');
$unknownWasLogged = is_file($unknownLogPath)
    && str_contains((string) file_get_contents($unknownLogPath), 'FUTURE_STATUS_TEST');
@unlink($unknownLogPath);
$assert($unknownLabel === 'Estado no reconocido' && $unknownWasLogged, 'unknown status stays generic in the UI and is logged internally');
$assert(ChecadorPresentationFormatter::captureMethodLabel('FUTURE_METHOD_TEST') === 'Método de captura no reconocido', 'unknown capture method is not exposed as a raw code');
$assert(str_contains($formatter, 'Logger::warning') && str_contains($formatter, 'status_code'), 'unknown presentation values are recorded with a bounded code field');

printf("CHECADOR_MVP_PUNCH_REPORT_FOCUSED_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

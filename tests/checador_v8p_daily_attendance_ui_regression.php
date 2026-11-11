<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php';
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$layoutSource = file_get_contents(__DIR__ . '/../app/Views/layouts/departamento-operativo.php') ?: '';
$projectionSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAttendanceProjection.php') ?: '';
$dailyViewSource = file_get_contents($viewPath) ?: '';
$rosterServiceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAttendanceRosterService.php') ?: '';
$persistentQaFixtureSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorPersistentQaFixtureService.php') ?: '';
$persistentQaFixtureConfigSource = file_get_contents(__DIR__ . '/../config/checador_m02_persistent_qa_fixtures.php') ?: '';
$attendanceDataTableSource = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js') ?: '';
$attendanceFilterSource = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.filters.init.js') ?: '';
$render = static function (array $vars) use ($viewPath): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require $viewPath;
    return (string) ob_get_clean();
};
$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $label) use (&$assertions, &$failures): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $assertions++;
    if (!$condition) $failures++;
};

$event = static function (string $uuid, string $time, string $role, ?int $ordinal, string $status = 'RECEIVED'): array {
    return [
        'event_uuid' => $uuid,
        'id_dispositivo' => 2,
        'metodo_captura' => 'BIOMETRIC',
        'estado_procesamiento' => $status,
        'ocurrido_at_utc' => $time,
        'pairing_role' => $role,
        'segment_ordinal' => $ordinal,
        'integrity' => ['status' => 'UNVERIFIABLE'],
    ];
};
$entry = $event('EVENT-ENTRY-1', '2026-10-23T14:00:00Z', 'ENTRY', 1);
$exit = $event('EVENT-EXIT-1', '2026-10-23T18:00:00Z', 'EXIT', 1);
$surplus = $event('EVENT-SURPLUS', '2026-10-23T19:00:00Z', 'SURPLUS', 1);
$unmatched = $event('EVENT-UNMATCHED', '2026-10-23T20:00:00Z', 'UNMATCHED', null, 'PENDING_MATCH');
$row = [
    'worker_id' => 900001,
    'full_name' => 'Ana García',
    'employee_number' => 'EMP-009',
    'attendance_relationship' => 'observed_outside_roster',
    'status' => 'incomplete',
    'schedule' => [
        'estado_jornada' => 'SCHEDULED',
        'horario_local' => ['segmentos' => [[
            'hora_inicio_local' => '08:00:00', 'hora_fin_local' => '17:00:00', 'dia_offset_fin' => 0,
        ]]],
    ],
    'slots' => [
        'ENTRY:1' => ['event' => $entry, 'time_local' => ['date' => '2026-10-23', 'time' => '08:00', 'utc' => $entry['ocurrido_at_utc']], 'ambiguous' => false],
        'EXIT:1' => ['event' => $exit, 'time_local' => ['date' => '2026-10-23', 'time' => '12:00', 'utc' => $exit['ocurrido_at_utc']], 'ambiguous' => false],
        'ENTRY:2' => ['event' => null, 'time_local' => null, 'ambiguous' => false],
        'EXIT:2' => ['event' => null, 'time_local' => null, 'ambiguous' => false],
    ],
    'additional_events' => [$surplus, $unmatched],
    'incidence_count' => 1,
];
$vars = [
    'selectedWorkplaceId' => 14,
    'selectedWorkplaceName' => 'Estación & centro',
    'selectedWorkplaceKind' => 'Estación',
    'selectedDate' => '2026-10-23',
    'selectedWorkerId' => null,
    'workerSelectOptions' => [[
        'worker_id' => 900001,
        'full_name' => 'Ana García',
        'employee_number' => 'EMP-009',
    ]],
    'workerSelectStatus' => 'ready',
    'workerSelectMessage' => '',
    'snapshotRef' => 'snapshot/ref-1',
    'dashboardHref' => '/departamento-operativo/recursos-humanos/checador',
    'demoMode' => true,
    'state' => 'success',
    'attendanceCoverage' => ['complete' => true],
    'attendanceRows' => [$row],
    'administrativeIncidences' => [900001 => [[
        'category' => 'PERMISSION',
        'date_range' => ['start_date' => '2026-10-23', 'end_date' => '2026-10-23'],
        'current_status' => 'ACTIVE',
        'current_revision' => 1,
        'uuid_incidencia' => 'INCIDENCE-001',
    ]]],
    'administrativeErrors' => [],
    'incidenceCreateAllowed' => false,
    'incidenceCreateKeys' => [],
];
$html = $render($vars);
$idMatches = [];
preg_match_all('/\bid="([^"]+)"/', $html, $idMatches);
$renderedIds = array_count_values($idMatches[1] ?? []);

preg_match('/<table class="table table-striped table-bordered text-nowrap align-middle" id="checador-daily-attendance-table"[^>]*>([\s\S]*?)<\/table>/', $html, $tableMatch);
$mainTable = $tableMatch[0] ?? '';
$visibleText = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
preg_match_all('/<th scope="col"(?:\s[^>]*)?>/', $mainTable, $columnMatches);
$assert(count($columnMatches[0] ?? []) === 9, 'daily table exposes the nine operational columns without repeating date');
$assert(!str_contains($html, '<h1')
    && str_contains($html, 'Estación:') && str_contains($html, 'Estación &amp; centro')
    && str_contains($html, 'ti ti-building-store') && str_contains($html, 'ti ti-calendar')
    && str_contains($html, '23 de octubre de 2026')
    && !str_contains($html, 'aria-label="Filtros activos"')
    && str_contains($controllerSource, "'daily-attendance' => 'Asistencia del día'")
    && str_contains($layoutSource, '<h4 class="fw-semibold mt-3"><?= $title; ?></h4>'), 'layout renders the single attendance heading and the view retains station/date context without duplicating it');
$assert(substr_count($html, 'id="checador-filter-modal"') === 1
    && str_contains($html, 'id="checador-search-modal-toggle"')
    && str_contains($html, 'data-bs-target="#checador-filter-modal"')
    && str_contains($html, 'modal-header modal-colored-header bg-primary text-white')
    && str_contains($html, 'btn-close btn-close-white')
    && str_contains($html, 'Búsqueda avanzada')
    && !str_contains($html, 'Filtros avanzados')
    && !str_contains($html, 'dropdown-menu dropdown-menu-end p-3')
    && str_contains($html, 'id="checador-filter-controls"')
    && str_contains($html, 'id="date" name="date" type="date"')
    && str_contains($html, 'id="worker_id" name="worker_id" class="form-select"')
    && str_contains($html, 'x-ref="checadorWorkerSelect"')
    && str_contains($html, 'select2-modal-field is-select2-pending')
    && str_contains($html, '<option value="" selected>Todos los colaboradores</option>')
    && !str_contains($html, 'name="workplace_id"')
    && str_contains($html, 'data-checador-search-action="clear"')
    && str_contains($html, 'data-checador-search-action="search"')
    && str_contains($html, 'x-data="{ ...actions(), ...checadorAttendanceSearch() }"')
    && !str_contains($html, '@click="search()"')
    && !str_contains($html, '@click="clearFilters()"')
    && !str_contains($html, '<form')
    && !str_contains($html, '@submit')
    && str_contains($html, 'ti ti-filter-off') && str_contains($html, 'Cancelar')
    && str_contains($html, 'ti ti-x') && str_contains($html, 'ti ti-search')
    && str_contains($html, '</i>Buscar')
    && str_contains($attendanceFilterSource, "this.bindModalSelect2({")
    && str_contains($attendanceFilterSource, "model: 'colaborador'")
    && str_contains($attendanceFilterSource, "dropdownParent: window.jQuery('#checador-filter-modal')")
    && str_contains($attendanceFilterSource, "minimumResultsForSearch: 0")
    && str_contains($controllerSource, '/assets/libs/select2/dist/js/select2.full.min.js')
    && str_contains($controllerSource, '/assets/libs/select2/dist/css/select2.min.css')
    && str_contains($controllerSource, '/assets/css/select2-modal.css')
    && !str_contains($controllerSource, 'checador-attendance-select2.css')
    && !preg_match('/console\.(?:log|info|warn|debug)|\[Portal3 Checador\]|buscar\.axios\.(?:start|response)/', $attendanceFilterSource)
    && strpos($html, 'id="checador-filter-modal"') < strpos($html, 'id="checador-filter-controls"'), 'institutional search modal uses Alpine button actions without native form submission or URL locality');
$qaSelectVars = $vars;
$qaSelectVars['workerSelectOptions'] = [
    ['worker_id' => 2001, 'full_name' => 'Alma Pérez', 'employee_number' => 'EMP-201'],
    ['worker_id' => 2002, 'full_name' => 'Carlos Ruiz', 'employee_number' => 'EMP-202'],
];
$qaSelectVars['selectedWorkerId'] = 2002;
$qaSelectHtml = $render($qaSelectVars);
preg_match('/<select id="worker_id"[\s\S]*?<\/select>/', $qaSelectHtml, $qaSelectMatch);
$qaSelectMarkup = $qaSelectMatch[0] ?? '';
$assert(str_contains($qaSelectMarkup, '<option value="2001">Alma Pérez · N.º EMP-201</option>')
    && str_contains($qaSelectMarkup, '<option value="2002" selected>Carlos Ruiz · N.º EMP-202</option>')
    && strpos($qaSelectMarkup, 'value="2001"') < strpos($qaSelectMarkup, 'value="2002"')
    && !str_contains($qaSelectMarkup, 'workplace_id'), 'worker select shows authorized canonical IDs alphabetically, employee numbers, and the selected option');
$unavailableSelectVars = $vars;
$unavailableSelectVars['workerSelectStatus'] = 'unavailable';
$unavailableSelectVars['workerSelectOptions'] = [];
$unavailableSelectVars['workerSelectMessage'] = 'No se confirmó el catálogo autorizado.';
$unavailableSelectHtml = $render($unavailableSelectVars);
$assert(str_contains($unavailableSelectHtml, 'id="worker_id" name="worker_id" class="form-select"')
    && !str_contains($unavailableSelectHtml, 'name="worker_id" class="form-select" disabled')
    && str_contains($unavailableSelectHtml, '<option value="" selected>Todos los colaboradores</option>')
    && !str_contains($unavailableSelectHtml, 'No se pudo confirmar la lista de colaboradores')
    && !str_contains($unavailableSelectHtml, 'worker-select-help')
    && !str_contains($unavailableSelectHtml, 'QA - Colaborador 01'), 'authorized locality keeps an enabled select with Todos and no under-field availability message');
$partialSelectVars = $vars;
$partialSelectVars['workerSelectStatus'] = 'partial';
$partialSelectVars['workerSelectOptions'] = [[
    'worker_id' => 2001,
    'full_name' => 'Alma Pérez',
    'employee_number' => 'EMP-201',
]];
$partialSelectVars['workerSelectMessage'] = 'El padrón está incompleto; sólo se ofrecen colaboradores con pertenencia individual confirmada.';
$partialSelectHtml = $render($partialSelectVars);
$assert(str_contains($partialSelectHtml, '<option value="2001">Alma Pérez · N.º EMP-201</option>')
    && !str_contains($partialSelectHtml, 'name="worker_id" class="form-select" disabled')
    && !str_contains($partialSelectHtml, 'El padrón está incompleto; sólo se ofrecen colaboradores con pertenencia individual confirmada.'), 'partial catalog keeps the locality select enabled without adding a message under the field');
$historicalVars = $vars;
$historicalRow = $row;
$historicalRow['attendance_relationship'] = 'current_roster_historical_unverified';
$historicalVars['attendanceRows'] = [$historicalRow];
$historicalVars['attendanceRoster'] = [
    'status' => 'partial',
    'coverage' => 'current_roster_historical_unverified',
    'workers' => [['worker_id' => 900001]],
    'message' => 'Se muestra el padrón actual como referencia, sin atribuirlo a la fecha consultada.',
];
$historicalHtml = $render($historicalVars);
preg_match('/<table class="table table-striped table-bordered text-nowrap align-middle" id="checador-daily-attendance-table"[^>]*>([\s\S]*?)<\/table>/', $historicalHtml, $historicalTableMatch);
$assert(!str_contains($historicalHtml, 'Se muestra el padrón actual como referencia, sin atribuirlo a la fecha consultada.')
    && str_contains($historicalTableMatch[0] ?? '', 'Padrón actual · pertenencia histórica no verificada'), 'historical roster context stays in the worker row without a technical notice');
$modalStart = strpos($html, '<div class="modal fade" id="checador-filter-modal"');
$modalHeader = strpos($html, '<div class="modal-header modal-colored-header bg-primary text-white">', $modalStart === false ? 0 : $modalStart);
$modalBody = strpos($html, '<div class="modal-body">', $modalHeader === false ? 0 : $modalHeader);
$modalControls = strpos($html, 'id="checador-filter-controls"', $modalBody === false ? 0 : $modalBody);
$modalFooter = strpos($html, '<div class="modal-footer">', $modalControls === false ? 0 : $modalControls);
$assert($modalStart !== false && $modalHeader !== false && $modalBody !== false && $modalControls !== false && $modalFooter !== false
    && $modalStart < $modalHeader && $modalHeader < $modalBody && $modalBody < $modalControls && $modalControls < $modalFooter
    && !str_contains(substr($html, $modalStart, $modalFooter - $modalStart), '<form')
    && !str_contains($html, 'form="checador-filter-controls"')
    && str_contains($html, 'class="modal-dialog modal-lg modal-dialog-centered"')
    && !str_contains(substr($html, $modalStart, $modalFooter - $modalStart), 'modal-dialog-scrollable')
    && str_contains($html, 'class="modal-dialog modal-dialog-scrollable modal-xl"')
    && !str_contains($html, 'data-bs-keyboard="false"'), 'attendance modal follows the Aditivo header/body/footer hierarchy and keeps Bootstrap keyboard behavior');
$assert(count(array_filter($renderedIds, static fn(int $count): bool => $count > 1)) === 0
    && !preg_match('/\sstyle="/i', $html), 'rendered daily view has unique control IDs and no inline styles');
$assert(str_contains($mainTable, 'Ana García') && str_contains($mainTable, 'EMP-009')
    && str_contains($mainTable, '08:00') && str_contains($mainTable, '1.ª entrada')
    && str_contains($mainTable, '1.ª salida') && str_contains($mainTable, '2.ª entrada') && str_contains($mainTable, '2.ª salida')
    && str_contains($mainTable, 'Información incompleta'), 'main row prioritizes full name, effective schedule, neutral P3-H positions and translated status');
$assert(str_contains($html, 'table-responsive overflow-x-auto overflow-y-hidden pb-2')
    && !str_contains($html, 'Un espacio sin dato no representa una ausencia')
    && !str_contains($html, 'Personal y registros observados')
    && !str_contains($html, '<section class="card" aria-labelledby="daily-attendance-table-title">')
    , 'table uses the institutional wrapper without the extra card and preserves neutral empty-cell interpretation');
$assert(str_contains($mainTable, 'Ver detalle')
    && str_contains($mainTable, 'Marcación observada · fuera del padrón habitual')
    && str_contains($mainTable, 'class="text-center align-middle text-nowrap"')
    && str_contains($mainTable, '>Acciones</th>')
    && str_contains($mainTable, 'class="dropdown dropstart"')
    && str_contains($mainTable, 'class="text-muted pointer border-0 bg-transparent p-0" data-bs-toggle="dropdown"')
    && str_contains($mainTable, 'ti ti-dots-vertical')
    && substr_count($mainTable, '<li>') === 3
    && str_contains($mainTable, 'ti ti-calendar-time')
    && str_contains($mainTable, 'ti ti-alert-circle')
    && !str_contains($mainTable, 'Registrar incidencia')
    && !str_contains($mainTable, 'disabled')
    && substr_count($mainTable, 'text-center') === 1
    && str_contains($html, 'id="daily-attendance-detail-900001-0"')
    && str_contains($html, 'Ver todas las marcaciones (4)')
    && str_contains($html, '2026-10-23 08:00')
    && str_contains($html, 'Registro adicional') && str_contains($html, 'Registro sin emparejar')
    && !preg_match('/HMAC|integridad|P1\/P2|completitud|trazabilidad|2026-10-23T14:00:00Z|EVENT-ENTRY-1/', $visibleText), 'dropdown restores the three original navigation actions without changing the detail modal');
$assert(substr_count($mainTable, 'text-center') === 1
    && str_contains($mainTable, 'class="text-center align-middle text-nowrap"'), 'only the action cell uses text centering; data and modal content retain their alignment');
$pendingVars = $vars;
$pendingRow = $row;
$pendingRow['status'] = 'classification_pending';
$pendingVars['attendanceRows'] = [$pendingRow];
$pendingHtml = $render($pendingVars);
preg_match('/<table class="table table-striped table-bordered text-nowrap align-middle" id="checador-daily-attendance-table"[^>]*>([\s\S]*?)<\/table>/', $pendingHtml, $pendingTableMatch);
$assert(str_contains($pendingTableMatch[0] ?? '', 'Pendiente de clasificación')
    && !str_contains($pendingTableMatch[0] ?? '', 'Registros pendientes de clasificación')
    && str_contains($pendingHtml, '<td>Pendiente de clasificación</td>'), 'unclassified attendance is labeled only Pendiente de clasificación in status fields');
$assert(str_contains($html, '<div class="datatables">')
    && str_contains($controllerSource, "\$workspace === 'daily-attendance'")
    && str_contains($controllerSource, 'jquery.dataTables.min.js')
    && str_contains($controllerSource, 'dataTables.bootstrap5.min.css')
    && !str_contains($controllerSource, 'dataTables.bootstrap5.min.js')
    && str_contains($attendanceDataTableSource, 'serverSide: false')
    && str_contains($attendanceDataTableSource, 'pageLength: 10')
    && str_contains($attendanceDataTableSource, 'lengthMenu: [10, 25, 50, 100]')
    && substr_count($attendanceDataTableSource, '.DataTable({') === 1
    && str_contains($attendanceDataTableSource, 'isDataTable(tableElement)')
    && !str_contains($attendanceDataTableSource, 'document.body.appendChild(menu)')
    && str_contains($attendanceDataTableSource, "targets: [8]")
    && str_contains($attendanceDataTableSource, "url: '/assets/libs/datatables.net/js/es-ES.json'")
    && str_contains($attendanceDataTableSource, 'pageContainer.dataset.attendanceEmptyMessage'), 'daily attendance uses the shared DataTables core, Bootstrap CSS, and Spanish language resource for the authorized result set');
$assert(str_contains($html, 'Incidencias relacionadas')
    && str_contains($html, 'Permiso') && str_contains($html, 'Activa') && str_contains($html, 'INCIDENCE-001')
    && !str_contains($mainTable, 'Registrar incidencia'), 'incidence detail is readable while the closed create gate shows no write action');
$noActionsVars = $vars;
$noActionsVars['state'] = 'error';
$noActionsHtml = $render($noActionsVars);
preg_match('/<table class="table table-striped table-bordered text-nowrap align-middle" id="checador-daily-attendance-table"[^>]*>([\\s\\S]*?)<\\/table>/', $noActionsHtml, $noActionsTableMatch);
$assert(!str_contains($noActionsTableMatch[0] ?? '', 'class="dropdown dropstart"')
    && !str_contains($noActionsTableMatch[0] ?? '', 'Acciones para'), 'rows with no available actions have no dropdown control');
$assert(!str_contains($html, 'Modo demostración')
    && !str_contains($html, '>Demo</span>')
    && str_contains($html, "snapshot_ref=snapshot%2Fref-1"), 'daily attendance omits demo labels and preserves snapshot context');

$emptyVars = $vars;
$emptyVars['attendanceRows'] = [];
$emptyHtml = $render($emptyVars);
preg_match('/<tbody>\s*<\/tbody>/', $emptyHtml, $emptyBodyMatch);
preg_match('/<table class="table table-striped table-bordered text-nowrap align-middle" id="checador-daily-attendance-table"[^>]*>([\s\S]*?)<\/table>/', $emptyHtml, $emptyTableMatch);
$assert(($emptyBodyMatch[0] ?? '') !== ''
    && !str_contains($emptyTableMatch[0] ?? '', 'Ana García'), 'a valid empty attendance result leaves DataTables without fabricated rows');
$dailyReadStart = strpos($controllerSource, 'private static function readDailyRawAttendance(');
$dailyReadEnd = strpos($controllerSource, '/** Read a bounded stable set for the worker-oriented snapshot workspace. */', $dailyReadStart ?: 0);
$dailyReadSource = $dailyReadStart !== false && $dailyReadEnd !== false
    ? substr($controllerSource, $dailyReadStart, $dailyReadEnd - $dailyReadStart)
    : '';
$assert(!str_contains($dailyReadSource, 'isset($observedIds[$profileWorkerId])')
    && str_contains($dailyReadSource, 'if ($profileWorkerId > 0)')
    && str_contains($attendanceDataTableSource, 'body.rows.length > 0')
    && str_contains($html, 'Sin marcaciones observadas para esta consulta.')
    && !str_contains($html, 'ausencia confirmada'), 'authorized roster workers remain rows without observed punches and an empty result stays neutral');

$unavailableVars = $vars;
$unavailableVars['state'] = 'timezone_unresolved';
$unavailableVars['stateMessage'] = 'La zona horaria autorizada no está disponible para la fecha seleccionada.';
$unavailableRow = \App\Services\ChecadorAttendanceProjection::workerRow([
    'worker_id' => 2001,
    'full_name' => 'Alma Pérez',
    'employee_number' => 'EMP-201',
], [], null, null);
$unavailableRow['status'] = 'punches_unavailable';
$unavailableRow['schedule'] = null;
$unavailableRow['attendance_relationship'] = 'habitual';
$unavailableVars['attendanceRows'] = [$unavailableRow];
$unavailableVars['attendanceRoster'] = [
    'status' => 'current_roster_only',
    'coverage' => 'current_active_roster',
    'workers' => [['worker_id' => 2001, 'full_name' => 'Alma Pérez', 'employee_number' => 'EMP-201']],
    'message' => 'Esta lista refleja sólo adscripciones activas actuales.',
];
$unavailableVars['attendanceCoverage'] = [
    'complete' => false,
    'message' => 'No se pudo confirmar la cobertura de la lectura.',
];
$unavailableVars['scheduledComodines'] = [
    'status' => 'unavailable',
    'workers' => [],
    'message' => 'La programación canónica no está disponible.',
];
$unavailableHtml = $render($unavailableVars);
$unavailableAlertCount = preg_match_all('/class="alert /', $unavailableHtml, $unavailableAlertMatches);
$unavailableTableMatch = [];
preg_match('/<table class="table table-striped table-bordered text-nowrap align-middle" id="checador-daily-attendance-table"[^>]*>([\s\S]*?)<\/table>/', $unavailableHtml, $unavailableTableMatch);
$assert(str_contains($unavailableHtml, 'La zona horaria no está disponible para la fecha seleccionada.')
    && $unavailableAlertCount === 1
    && !str_contains($unavailableHtml, 'La programación canónica no está disponible.')
    && !str_contains($unavailableHtml, 'No se pudo confirmar la cobertura de la lectura.')
    && !str_contains($unavailableHtml, 'Sin marcaciones observadas para esta consulta.')
    && !str_contains($unavailableHtml, 'La tabla estará disponible cuando la consulta se complete.')
    && str_contains($unavailableHtml, 'checador-daily-attendance-table')
    && str_contains($unavailableTableMatch[0] ?? '', 'Alma Pérez')
    && str_contains($unavailableTableMatch[0] ?? '', 'text-bg-light border text-body-secondary">—</span>')
    && !str_contains($unavailableTableMatch[0] ?? '', 'Marcaciones no disponibles')
    && !str_contains($unavailableTableMatch[0] ?? '', '08:00')
    && str_contains($unavailableHtml, 'data-attendance-empty-message="La zona horaria no está disponible')
    && !str_contains($unavailableHtml, '<details')
    && !str_contains($unavailableHtml, 'HMAC válido')
    && !str_contains($unavailableHtml, 'P1/P2'), 'failed reads keep the table structure, identify the failure, and show no fabricated result or success provenance');

$apiFailureVars = $vars;
$apiFailureVars['state'] = 'error';
$apiFailureVars['stateMessage'] = 'La API no está disponible; no se confirma un resultado vacío.';
$apiFailureVars['attendanceRows'] = [$unavailableRow];
$apiFailureVars['attendanceCoverage'] = ['status' => 'source_completeness_unknown', 'complete' => false];
$apiFailureVars['attendanceRoster'] = $unavailableVars['attendanceRoster'];
$apiFailureHtml = $render($apiFailureVars);
$apiFailureAlertCount = preg_match_all('/class="alert /', $apiFailureHtml, $apiFailureAlertMatches);
$apiFailureTableMatch = [];
preg_match('/<table class="table table-striped table-bordered text-nowrap align-middle" id="checador-daily-attendance-table"[^>]*>([\s\S]*?)<\/table>/', $apiFailureHtml, $apiFailureTableMatch);
$assert(str_contains($apiFailureHtml, 'No fue posible consultar la asistencia. Intenta nuevamente.')
    && $apiFailureAlertCount === 1
    && str_contains($apiFailureHtml, 'checador-daily-attendance-table')
    && str_contains($apiFailureHtml, 'data-attendance-empty-message="No fue posible consultar la asistencia. Intenta nuevamente.')
    && !str_contains($apiFailureHtml, 'La API no está disponible')
    && str_contains($apiFailureHtml, 'Alma Pérez')
    && substr_count($apiFailureHtml, 'Marcaciones no disponibles') === 1
    && !str_contains($apiFailureHtml, 'Cobertura no confirmada')
    && !str_contains($apiFailureTableMatch[0] ?? '', 'Marcaciones no disponibles')
    && str_contains($apiFailureTableMatch[0] ?? '', 'text-bg-light border text-body-secondary'), 'an API failure preserves the active roster, renders unavailable row status neutrally, and shows only one general notice');

$authorizationFailureVars = $apiFailureVars;
$authorizationFailureVars['stateMessage'] = 'La API no autoriza la consulta para este centro.';
$authorizationFailureHtml = $render($authorizationFailureVars);
$assert(str_contains($authorizationFailureHtml, 'data-attendance-empty-message="No fue posible consultar la asistencia. Intenta nuevamente.')
    && !str_contains($authorizationFailureHtml, 'La API no autoriza la consulta para este centro.')
    && preg_match('/class="alert /', $authorizationFailureHtml) === 1, 'read errors stay generic while the preserved active roster remains visible');

$partialVars = $vars;
$partialVars['attendanceReadSource'] = 'raw_punch_events';
$partialVars['journeyEvaluationState'] = 'disabled';
$partialVars['attendanceCoverage'] = ['status' => 'partial', 'complete' => false, 'message' => 'La cobertura de marcaciones es parcial.'];
$partialVars['scheduledComodines'] = [
    'status' => 'unavailable',
    'workers' => [],
    'message' => 'La programación canónica consultada no está disponible.',
];
$partialHtml = $render($partialVars);
$partialCaptionCount = preg_match_all('/<caption class="caption-top text-start small text-body-secondary py-1">([\s\S]*?)<\/caption>/', $partialHtml, $partialCaptionMatches);
$assert($partialCaptionCount === 1
    && str_contains($partialCaptionMatches[1][0] ?? '', 'Cobertura no confirmada')
    && str_contains($partialCaptionMatches[1][0] ?? '', 'data-bs-toggle="tooltip"')
    && str_contains($partialCaptionMatches[1][0] ?? '', 'data-bs-title="Esta consulta no confirma que se hayan recibido todas las marcaciones del período."')
    && str_contains($partialCaptionMatches[1][0] ?? '', 'aria-label="Ayuda sobre la cobertura de marcaciones"')
    && !preg_match('/<div class="alert alert-[^"]+ py-2" role="status">[\s\S]*?Cobertura no confirmada/', $partialHtml)
    && !str_contains($partialHtml, 'La cobertura de marcaciones es parcial.')
    && !str_contains($partialHtml, 'La programación canónica consultada no está disponible.')
    && !preg_match('/API|HMAC|integridad|P1\/P2|POST|completitud|ingesta|paginaci[oó]n|trazabilidad|fuente/', $partialHtml)
    && str_contains($partialHtml, 'checador-daily-attendance-table'), 'partial successful reads show a compact accessible coverage indicator in the existing table caption without a full-width alert');

$completeHtml = $render($vars);
$assert(!str_contains($completeHtml, 'Cobertura no confirmada')
    && !str_contains($completeHtml, 'Esta consulta no confirma que se hayan recibido todas las marcaciones del período.'), 'confirmed coverage renders no coverage warning');

$unknownCoverageVars = $vars;
$unknownCoverageVars['attendanceCoverage'] = ['status' => 'source_completeness_unknown', 'complete' => false];
$unknownCoverageHtml = $render($unknownCoverageVars);
$assert(str_contains($unknownCoverageHtml, 'Cobertura no confirmada')
    && str_contains($unknownCoverageHtml, 'data-bs-title="Esta consulta no confirma que se hayan recibido todas las marcaciones del período."'), 'unknown source completeness remains explicitly represented as unconfirmed coverage');

$notAuthorizedProgrammingVars = $vars;
$notAuthorizedProgrammingVars['scheduledComodines'] = [
    'status' => 'not_authorized',
    'workers' => [],
    'message' => 'No debe presentarse un error de programación que no se consultó.',
];
$notAuthorizedProgrammingHtml = $render($notAuthorizedProgrammingVars);
$assert(!str_contains($notAuthorizedProgrammingHtml, 'No debe presentarse un error de programación que no se consultó.')
    && !preg_match('/<div class="alert /', $notAuthorizedProgrammingHtml)
    && str_contains($notAuthorizedProgrammingHtml, 'checador-daily-attendance-table'), 'an unexecuted unauthorized programming lookup does not create an availability alert beside a successful attendance read');

$unselectedVars = $vars;
$unselectedVars['selectedWorkplaceId'] = null;
$unselectedVars['selectedWorkplaceName'] = '';
$unselectedVars['state'] = 'idle';
$unselectedVars['attendanceRows'] = [];
$unselectedHtml = $render($unselectedVars);
$assert(str_contains($unselectedHtml, 'Sin centro seleccionado')
    && str_contains($unselectedHtml, 'Selecciona un centro autorizado para consultar la asistencia.')
    && str_contains($unselectedHtml, 'checador-daily-attendance-table'), 'an unselected station is not presented as an authorized center and the table remains visible');

$loadingVars = $vars;
$loadingVars['state'] = 'loading';
$loadingVars['attendanceRows'] = [];
$loadingHtml = $render($loadingVars);
$assert(str_contains($loadingHtml, 'Cargando la asistencia')
    && str_contains($loadingHtml, 'checador-daily-attendance-table')
    && substr_count($loadingHtml, '<th scope="col"') >= 9
    && !preg_match('/<tbody>\s*<tr[^>]*>\s*<td[^>]*colspan=/i', $loadingHtml), 'loading state keeps all nine headers and uses DataTables text instead of an artificial colspan row');

$assert(!str_contains($controllerSource, 'localAttendanceTableDemoRequested')
    && !str_contains($controllerSource, 'localQaTableFixtureRows')
    && !str_contains($projectionSource, 'localQaTableFixtureRows')
    && !str_contains($dailyViewSource, 'attendance_demo')
    && !str_contains($dailyViewSource, 'demo_fixture')
    && !str_contains($attendanceDataTableSource, 'qaDemo')
    && str_contains($controllerSource, 'ChecadorPersistentQaFixtureService')
    && str_contains($controllerSource, '$qaFixtureService->shouldServe($workspace, $requestedWorkplace, $date, $query)')
    && str_contains($persistentQaFixtureSource, 'if (!self::enabled() || $workplaceId !== self::WORKPLACE_ID)')
    && str_contains($persistentQaFixtureSource, 'self::ADDITIONAL_DAILY_DATE')
    && str_contains($persistentQaFixtureConfigSource, "'2026-10-29' =>")
    && !preg_match('/\bQA\b|SYNTHETIC_QA_FIXTURE|simulad/i', $visibleText), 'attendance uses only the explicitly scoped persistent fixture and exposes no QA label');
$assert(str_contains($controllerSource, 'workersForScheduleDate(')
    && str_contains($controllerSource, 'workersForAttendanceFilter(')
    && str_contains($controllerSource, "observedProfiles(array_map('intval', array_keys(\$observedIds)), false)")
    && str_contains($controllerSource, '$eventStationId !== $workplaceId')
    && str_contains($controllerSource, "new \\Collator('es_MX')")
    && str_contains($controllerSource, '$requestedWorkerId !== null && !isset($workerOptionsById[$requestedWorkerId])')
    && !str_contains($rosterServiceSource, 'LOCAL_QA_WORKER_IDS')
    && !str_contains($rosterServiceSource, "->where('estado', 0)")
    && !str_contains(substr($rosterServiceSource, strpos($rosterServiceSource, 'public function workersForAttendanceFilter('), strpos($rosterServiceSource, 'public static function observedWorkerRelationship(') - strpos($rosterServiceSource, 'public function workersForAttendanceFilter(')), 'limit(')
    && str_contains($rosterServiceSource, "'current_roster_historical_unverified'")
    && str_contains($rosterServiceSource, 'WorkerWorkplaceAssignmentResolver')
    && str_contains($rosterServiceSource, 'resolveActiveStationIdForLocality($workplaceId)')
    && str_contains($rosterServiceSource, "->where('id_estacion', \$legacyStationId)")
    && !str_contains($rosterServiceSource, "->where('id_estacion', \$workplaceId)")
    && str_contains($attendanceFilterSource, 'workerSelect.disabled = true')
    && str_contains($attendanceFilterSource, 'dateInput.disabled = true')
    && str_contains($attendanceFilterSource, 'window.location.assign(filterForm.action)'), 'worker catalog is read independently from punches, validates canonical locality scope, and omits empty filter parameters');

$vars['incidenceCreateAllowed'] = true;
$vars['incidenceCreateKeys'] = [900001 => 'create-key'];
$authorizedHtml = $render($vars);
preg_match('/<table class="table table-striped table-bordered text-nowrap align-middle" id="checador-daily-attendance-table"[^>]*>([\\s\\S]*?)<\\/table>/', $authorizedHtml, $authorizedTableMatch);
$authorizedTable = $authorizedTableMatch[0] ?? '';
$assert(substr_count($authorizedTable, 'Registrar incidencia') === 1
    && str_contains($authorizedTable, 'href="/departamento-operativo/recursos-humanos/checador/incidencias?date=2026-10-23&amp;snapshot_ref=snapshot%2Fref-1&amp;worker_id=900001"'), 'permitted create-incidence action appears exactly once with its preserved destination');
$assert(substr_count($authorizedTable, '<li>') === 4
    && substr_count($authorizedTable, 'ti ti-dots-vertical') === 1
    && !str_contains($authorizedTable, 'disabled'), 'authorized menu contains four available actions and no disabled entries');

printf("CHECADOR_V8P_DAILY_ATTENDANCE_UI: %d PASS / %d FAIL\n", $assertions - $failures, $failures);
exit($failures === 0 ? 0 : 1);

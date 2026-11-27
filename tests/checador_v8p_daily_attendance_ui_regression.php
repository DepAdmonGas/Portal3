<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php';
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$routesSource = file_get_contents(__DIR__ . '/../routes/web.php') ?: '';
$dashboardWorkspaceStart = strpos($controllerSource, 'private function renderDashboardWorkspace');
$configurationMethodStart = strpos($controllerSource, 'public function configuration(): void');
$dashboardWorkspaceSource = $dashboardWorkspaceStart !== false && $configurationMethodStart !== false
    ? substr($controllerSource, $dashboardWorkspaceStart, $configurationMethodStart - $dashboardWorkspaceStart)
    : '';
$attendanceDataTableSource = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js') ?: '';
$portalTableStyles = file_get_contents(__DIR__ . '/../public/assets/css/portal-tables.css') ?: '';
$dataTablesRuntime = file_get_contents(__DIR__ . '/../public/assets/libs/datatables.net/js/jquery.dataTables.min.js') ?: '';
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
$surplus['local_time'] = ['date' => '2026-10-23', 'time' => '19:06', 'utc' => $surplus['ocurrido_at_utc']];
$unmatched['local_time'] = ['date' => '2026-10-23', 'time' => '20:11', 'utc' => $unmatched['ocurrido_at_utc']];
$row = [
    'worker_id' => 900001,
    'full_name' => 'Ana García',
    'employee_number' => 'EMP-009',
    'status' => 'classification_pending',
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
    'snapshotRef' => 'snapshot/ref-1',
    'dashboardHref' => '/departamento-operativo/recursos-humanos/checador?workplace_id=14',
    'demoMode' => true,
    'state' => 'success',
    'attendanceCoverage' => ['complete' => true],
    'attendanceRows' => [$row],
    'workerSelectOptions' => [[
        'worker_id' => 900001,
        'full_name' => 'Ana García',
        'employee_number' => 'EMP-009',
    ]],
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

preg_match('/<table class="table table-striped table-bordered mb-0 text-nowrap align-middle portal-table portal-table--selectable" id="checador-daily-attendance-table">([\s\S]*?)<\/table>/', $html, $tableMatch);
$mainTable = $tableMatch[0] ?? '';
preg_match_all('/<th scope="col"[^>]*>/', $mainTable, $columnMatches);
$assert(count($columnMatches[0] ?? []) === 9, 'daily table exposes the nine operational columns without repeating date');
$assert(str_contains($html, '<h1 class="visually-hidden">Asistencia del día</h1>')
    && str_contains($html, 'Estación:') && str_contains($html, 'Estación &amp; centro')
    && str_contains($html, '23 de octubre de 2026')
    && str_contains($html, 'ti-building-store') && str_contains($html, 'ti-calendar')
    && str_contains($html, 'id="checador-search-modal-toggle"')
    && !str_contains($html, 'Regresar a Checador'), 'compact header restores the prior station/date presentation and primary search action');
$assert(str_contains($html, 'data-bs-target="#checador-filter-modal"')
    && str_contains($html, 'id="checador-filter-modal"')
    && str_contains($html, 'modal-colored-header bg-primary text-white')
    && str_contains($html, 'Búsqueda avanzada')
    && str_contains($html, 'id="date" name="date" type="date"')
    && str_contains($html, 'id="worker_id" name="worker_id" class="form-select"')
    && str_contains($html, 'select2-modal-field is-select2-pending')
    && str_contains($html, '<option value="900001">Ana García · N.º EMP-009</option>')
    && str_contains($html, 'data-checador-ajax')
    && !str_contains($html, 'name="workplace_id"')
    && str_contains($html, 'data-checador-search-action="search"')
    && str_contains($html, 'row g-0 ms-auto w-100 justify-content-end')
    && str_contains($html, 'ti ti-x me-1')
    && str_contains($html, 'Cancelar') && str_contains($html, 'Buscar')
    && !str_contains($html, 'Limpiar filtros'), 'filter modal retains only Cancel and Search, with server-derived station scope');
$assert(count(array_filter($renderedIds, static fn(int $count): bool => $count > 1)) === 0
    && !preg_match('/\sstyle="/i', $html), 'rendered daily view has unique control IDs and no inline styles');
$assert(str_contains($mainTable, 'Ana García') && str_contains($mainTable, 'EMP-009')
    && str_contains($mainTable, '08:00') && str_contains($mainTable, '1.ª entrada')
    && str_contains($mainTable, '1.ª salida') && str_contains($mainTable, '2.ª entrada') && str_contains($mainTable, '2.ª salida')
    && str_contains($mainTable, 'Horario efectivo') && str_contains($mainTable, 'Estado'), 'main row retains the prior column layout with current schedule and status labels');
$assert(str_contains($mainTable, 'Pendiente de clasificación')
    && !str_contains($mainTable, 'Marcaciones observadas: 19:06, 20:11')
    && str_contains($html, '19:06') && str_contains($html, '20:11'),
    'unclassified events remain in detail without repetitive times in the main status');
$assert(str_contains($mainTable, 'badge bg-transparent border text-secondary')
    && str_contains($mainTable, 'badge bg-transparent border text-body-secondary')
    && !str_contains($mainTable, 'text-bg-light')
    && str_contains($portalTableStyles, 'tr.selected > * *:not(.dropdown-menu)')
    && str_contains($portalTableStyles, '--portal-table-selection-color: #fff'),
    'status labels and incidence counters remain legible on the shared blue selected-row background');
$noObservedVars = $vars;
$noObservedVars['attendanceRows'][0]['status'] = 'no_observed_event';
$noObservedVars['attendanceRows'][0]['journey_message'] = 'Jornada pendiente de evaluación.';
$noObservedHtml = $render($noObservedVars);
preg_match('/<table[^>]*id="checador-daily-attendance-table"[^>]*>([\s\S]*?)<\/table>/', $noObservedHtml, $noObservedTable);
$assert(str_contains($noObservedTable[0] ?? '', 'badge bg-transparent border text-body-secondary">Sin marcación')
    && !str_contains($noObservedTable[0] ?? '', 'Jornada pendiente de evaluación.'),
    'neutral attendance uses a single concise badge');
$notEvaluatedVars = $vars;
$notEvaluatedVars['attendanceRows'][0]['status'] = 'journey_not_evaluated';
$notEvaluatedHtml = $render($notEvaluatedVars);
$assert(str_contains($notEvaluatedHtml, 'badge bg-transparent border text-secondary">Jornada pendiente de evaluación'),
    'persisted journey-not-evaluated status uses the requested neutral label');
$detailFooter = '';
if (preg_match('/<div class="modal-footer">([\s\S]*?)<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/', $html, $footerMatches) === 1) {
    $detailFooter = $footerMatches[1];
}
$closePosition = strpos($detailFooter, 'Cerrar');
$incidencePosition = strpos($detailFooter, 'Ver incidencias');
$assert($detailFooter !== '' && $closePosition !== false && $incidencePosition !== false
    && $closePosition < $incidencePosition
    && str_contains($detailFooter, 'btn bg-danger-subtle text-danger')
    && str_contains($detailFooter, 'ti ti-x me-1')
    && str_contains($detailFooter, 'btn btn-primary')
    && str_contains($detailFooter, 'ti ti-alert-circle me-1')
    && str_contains($detailFooter, 'row g-0 ms-auto w-100 justify-content-end')
    && str_contains($detailFooter, 'col-12 col-md-auto d-grid'),
    'detail modal uses ordered institutional buttons and responsive footer layout');
$p1PendingVars = $vars;
$p1PendingVars['attendanceRows'][0]['status'] = 'incomplete';
$p1PendingVars['attendanceRows'][0]['journey_message'] = 'P1: completitud de sincronización pendiente · P2: cálculo persistido';
$p1PendingHtml = $render($p1PendingVars);
preg_match('/<table class="table table-striped table-bordered mb-0 text-nowrap align-middle portal-table portal-table--selectable" id="checador-daily-attendance-table">([\s\S]*?)<\/table>/', $p1PendingHtml, $p1PendingTableMatch);
$p1PendingTable = $p1PendingTableMatch[0] ?? '';
$assert(str_contains($p1PendingTable, 'Información insuficiente')
    && !str_contains($p1PendingTable, 'completitud de sincronización pendiente'),
    'partial results retain a neutral status without a redundant technical message');
$partialVars = $vars;
$partialVars['selectedDate'] = '2026-11-27';
$partialVars['attendanceRows'][0] = \App\Services\ChecadorAttendanceProjection::workerRow(
    ['worker_id' => 565, 'full_name' => 'Colaborador de prueba'], [],
    ['p1_status' => 'PENDING_SYNC_COMPLETION', 'p2_status' => 'PENDING_SYNC', 'pairing_current_status' => 'ready',
        'persisted_pairing_events' => [['event_uuid' => 'fixture-entry-22', 'pairing_role' => 'ENTRY', 'segment_ordinal' => 1,
            'ocurrido_at_utc' => '2026-11-27 16:57:04']]], 'America/Mexico_City');
$partialVars['attendanceRows'][0]['p2_status'] = 'PENDING_SYNC';
$partialVars['attendanceRows'][0]['journey_message'] = 'P1: completitud de sincronización pendiente';
$partialHtml = $render($partialVars);
preg_match('/<table[^>]*id="checador-daily-attendance-table"[^>]*>([\s\S]*?)<\/table>/', $partialHtml, $partialTable);
$assert(str_contains($partialTable[0] ?? '', '10:57') && str_contains($partialTable[0] ?? '', 'Salida pendiente')
    && !str_contains($partialTable[0] ?? '', 'Jornada pendiente de evaluación')
    && !str_contains($partialTable[0] ?? '', 'Marcación observada:'),
    'persisted partial ENTRY renders local time with a single provisional status');
$scheduleStateVars = $vars;
$scheduleStateVars['attendanceRows'][0]['schedule']['estado_jornada'] = 'UNSCHEDULED_DAY';
$scheduleStateHtml = $render($scheduleStateVars);
preg_match('/<table class="table table-striped table-bordered mb-0 text-nowrap align-middle portal-table portal-table--selectable" id="checador-daily-attendance-table">([\s\S]*?)<\/table>/', $scheduleStateHtml, $scheduleStateTableMatch);
$scheduleStateTable = $scheduleStateTableMatch[0] ?? '';
$assert(str_contains($scheduleStateTable, 'Sin horario asignado')
    && !str_contains($scheduleStateTable, 'Descanso programado'),
    'UNSCHEDULED_DAY remains distinct from explicit DAY_OFF');
$assert(str_contains($html, 'table-responsive overflow-x-auto overflow-y-hidden pb-4')
    && str_contains($mainTable, 'dropdown-menu dropdown-menu-end')
    && str_contains($mainTable, 'data-worker-id="900001"')
    && str_contains($mainTable, 'ti-dots-vertical')
    && str_contains($html, 'Una celda vacía no confirma una ausencia.')
    && !str_contains($html, 'aria-label="Filtros activos"')
    && !preg_match('/\b(?:FALTA|AUSENTE)\b/i', $html), 'flat legacy DataTables layout and three-dot actions return without classifying empty cells as absence');
$incompleteVars = $vars;
$incompleteVars['attendanceCoverage'] = ['complete' => false];
$incompleteHtml = $render($incompleteVars);
$assert(str_contains($incompleteHtml, 'Cobertura no confirmada')
    && str_contains($incompleteHtml, 'Una celda vacía no confirma una ausencia.'), 'incomplete attendance coverage remains explicitly uncertain');
$errorVars = $vars;
$errorVars['state'] = 'error';
$errorVars['attendanceCoverage'] = ['complete' => false];
$errorVars['attendanceRows'] = [];
$errorHtml = $render($errorVars);
$assert(str_contains($errorHtml, 'No fue posible cargar la asistencia')
    && str_contains($errorHtml, 'El padrón no está disponible y no se muestran personas sin una membresía autorizada verificable.'), 'failed attendance and unavailable roster remain distinct errors rather than empty or complete results');
$unavailableRosterRow = $row;
$unavailableRosterRow['status'] = 'punches_unavailable';
$unavailableRosterRow['schedule'] = null;
$unavailableRosterRow['schedule_error'] = 'schedule_not_loaded';
$unavailableRosterRow['incidence_count'] = null;
$unavailableRosterRow['additional_events'] = [];
foreach ($unavailableRosterRow['slots'] as &$unavailableSlot) {
    $unavailableSlot = ['event' => null, 'time_local' => null, 'ambiguous' => false];
}
unset($unavailableSlot);
$rosterFallbackVars = $vars;
$rosterFallbackVars['state'] = 'unavailable';
$rosterFallbackVars['attendanceRosterFallbackAllowed'] = true;
$rosterFallbackVars['attendanceRoster'] = [
    'status' => 'ready',
    'coverage' => 'current_active_roster',
    'workers' => [['worker_id' => 900001, 'full_name' => 'Ana García', 'employee_number' => 'EMP-009']],
    'message' => 'Personal activo atribuido mediante localidad canónica vigente.',
];
$rosterFallbackVars['attendanceRows'] = [$unavailableRosterRow];
$rosterFallbackVars['attendanceCoverage'] = ['complete' => false];
$rosterFallbackVars['administrativeIncidences'] = [];
$rosterFallbackVars['administrativeErrors'] = [];
$rosterFallbackVars['incidenceCreateAllowed'] = true;
$rosterFallbackHtml = $render($rosterFallbackVars);
preg_match('/<table class="table table-striped table-bordered mb-0 text-nowrap align-middle portal-table portal-table--selectable" id="checador-daily-attendance-table">([\s\S]*?)<\/table>/', $rosterFallbackHtml, $fallbackTableMatch);
$fallbackTable = $fallbackTableMatch[0] ?? '';
$assert(str_contains($fallbackTable, 'Ana García')
    && str_contains($fallbackTable, 'Marcaciones no disponibles')
    && str_contains($rosterFallbackHtml, 'padrón vigente autorizado como referencia')
    && str_contains($rosterFallbackHtml, 'no confirma pertenencia histórica, asistencia ni horario')
    && str_contains($fallbackTable, 'ti-dots-vertical')
    && str_contains($fallbackTable, 'dropdown-menu dropdown-menu-end')
    && str_contains($fallbackTable, 'aria-label="Acciones para Ana García. No disponibles: marcaciones y adscripción histórica sin confirmar"')
    && str_contains($fallbackTable, 'title="Acciones no disponibles sin marcaciones y adscripción histórica confirmadas."')
    && str_contains($fallbackTable, '<div class="dropdown-menu dropdown-menu-end')
    && !str_contains($fallbackTable, '<ul class="dropdown-menu')
    && !str_contains($fallbackTable, '<li>')
    && !str_contains($fallbackTable, 'Acciones deshabilitadas: no hay marcaciones ni adscripción histórica confirmadas.')
    && str_contains($fallbackTable, 'dropdown-item pointer disabled" aria-disabled="true" tabindex="-1"')
    && !preg_match('/<a\b[^>]*href=/', $fallbackTable)
    && !preg_match('/\b(?:FALTA|AUSENTE)\b/i', $fallbackTable), 'a verified active roster remains visible on API failure with historical limits, unavailable punches and no invented actions or absences');
$unverifiedFallbackVars = $rosterFallbackVars;
$unverifiedFallbackVars['attendanceRosterFallbackAllowed'] = false;
$unverifiedFallbackHtml = $render($unverifiedFallbackVars);
preg_match('/<table class="table table-striped table-bordered mb-0 text-nowrap align-middle portal-table portal-table--selectable" id="checador-daily-attendance-table">([\s\S]*?)<\/table>/', $unverifiedFallbackHtml, $unverifiedTableMatch);
$unverifiedTable = $unverifiedTableMatch[0] ?? '';
$assert(!str_contains($unverifiedTable, 'Ana García')
    && str_contains($unverifiedTable, 'El padrón no está disponible'), 'unverified roster rows remain hidden when the API read fails');
$assert(str_contains($mainTable, 'Ver detalle')
    && str_contains($html, 'id="daily-attendance-detail-900001-0"')
    && str_contains($html, 'Marcaciones originales y trazabilidad')
    && str_contains($html, '2026-10-23T14:00:00Z')
    && str_contains($html, 'Registro adicional') && str_contains($html, 'Registro sin emparejar')
    && str_contains($html, '2026-10-23 19:06') && str_contains($html, '2026-10-23 20:11')
    && str_contains($html, 'Integridad no verificada por Portal3'), 'detail modal preserves local/UTC event provenance and additional/unmatched events');
$temporaryScriptsStart = strpos($dashboardWorkspaceSource, "...(\$workspace === 'temporary-access' ? [");
$temporaryScriptsEnd = $temporaryScriptsStart === false ? false : strpos($dashboardWorkspaceSource, "] : []),", $temporaryScriptsStart);
$dailyWorkspaceSources = $temporaryScriptsStart !== false && $temporaryScriptsEnd !== false
    ? substr_replace($dashboardWorkspaceSource, '', $temporaryScriptsStart, $temporaryScriptsEnd + strlen('] : []),') - $temporaryScriptsStart)
    : $dashboardWorkspaceSource;
$assert(str_contains($html, '<div class="datatables">')
    && str_contains($controllerSource, "\$workspace === 'daily-attendance'")
    && str_contains($controllerSource, 'jquery.dataTables.min.js')
    && !str_contains($dailyWorkspaceSources, 'dataTables.bootstrap5.min.js')
    && str_contains($controllerSource, '/assets/libs/select2/dist/js/select2.full.min.js')
    && str_contains($controllerSource, '/assets/libs/select2/dist/css/select2.min.css')
    && str_contains($attendanceDataTableSource, 'serverSide: false')
    && str_contains($attendanceDataTableSource, 'pageLength: 15')
    && str_contains($attendanceDataTableSource, 'lengthMenu: [15, 30, 50, 100]')
    && str_contains($attendanceDataTableSource, 'searching: true')
    && str_contains($attendanceDataTableSource, 'rowCallback: function (row)')
    && str_contains($attendanceDataTableSource, 'click.portal3AttendanceSelection')
    && str_contains($attendanceDataTableSource, "popperConfig: { strategy: 'fixed' }")
    && str_contains($dataTablesRuntime, 'DataTables 1.13.5')
    && str_contains($attendanceDataTableSource, "targets: [8]")
    && str_contains($attendanceDataTableSource, "url: '/assets/libs/datatables.net/js/es-ES.json'"), 'daily attendance matches Permissions DataTables controls and dropdown handling for the authorized result set');
$assert(str_contains($html, 'Incidencias relacionadas')
    && str_contains($html, 'Permiso') && str_contains($html, 'Activa') && str_contains($html, 'INCIDENCE-001')
    && !str_contains($mainTable, 'Registrar incidencia'), 'incidence detail is readable while the closed create gate shows no write action');
$assert(str_contains($html, 'snapshot_ref=snapshot%2Fref-1'), 'detail navigation preserves encoded snapshot context in the restored presentation');

$vars['incidenceCreateAllowed'] = true;
$vars['incidenceCreateKeys'] = [900001 => 'create-key'];
$authorizedHtml = $render($vars);
$assert(!str_contains($authorizedHtml, 'Registrar incidencia')
    && !str_contains($authorizedHtml, 'Jornada · No disponible en demostración')
    && !str_contains($authorizedHtml, 'Incidencias · No disponible en demostración')
    && str_contains($authorizedHtml, 'Ver detalle')
    && !str_contains($authorizedHtml, 'idempotency_key'), 'synthetic M01 menu exposes only detail preview and no administrative actions');
$vars['demoMode'] = false;
$authorizedHtml = $render($vars);
$assert(str_contains($authorizedHtml, 'Registrar incidencia')
    && str_contains($authorizedHtml, 'href="/departamento-operativo/recursos-humanos/checador/incidencias?workplace_id=14&amp;date=2026-10-23&amp;snapshot_ref=snapshot%2Fref-1&amp;worker_id=900001"')
    && !str_contains($authorizedHtml, 'action="/departamento-operativo/recursos-humanos/checador/incidencias/crear"')
    && !str_contains($authorizedHtml, 'name="idempotency_key"'), 'incidence entry link appears only with existing server-provided permission and key and does not submit a write');

$previewMethod = new ReflectionMethod(\App\Controllers\ChecadorController::class, 'm01SyntheticDetailPreviewRows');
$previewData = $previewMethod->invoke(null, '2026-11-19');
$previewRows = $previewData['attendance_rows'] ?? [];
$assert(count($previewRows) === 6
    && count(array_filter($previewRows, static fn(array $previewRow): bool => count(array_filter($previewRow['slots'] ?? [], static fn(array $slot): bool => is_array($slot['event'] ?? null))) === 4)) === 1
    && count(array_filter($previewRows, static fn(array $previewRow): bool => ($previewRow['evaluated_raw_event_count'] ?? null) === 2)) === 1
    && count(array_filter($previewRows, static fn(array $previewRow): bool => ($previewRow['schedule']['estado_jornada'] ?? '') === 'DAY_OFF')) === 1
    && count(array_filter($previewRows, static fn(array $previewRow): bool => ($previewRow['status'] ?? '') === 'incomplete')) === 2
    && count($previewData['administrative_incidences'][900005] ?? []) === 1,
    'isolated synthetic fixture defines the six requested cases using reserved fictitious identities');
$assert(array_reduce($previewRows, static fn(bool $syntheticOnly, array $row): bool => $syntheticOnly
    && (int) ($row['worker_id'] ?? 0) >= 900000
    && str_starts_with((string) ($row['employee_number'] ?? ''), 'EMP-10'), true),
    'synthetic fixture identities remain separate from operational worker IDs');
$previewVars = $vars;
$previewVars['syntheticDetailPreviewMode'] = true;
$previewVars['demoMode'] = false;
$previewVars['state'] = 'success';
$previewVars['selectedWorkplaceId'] = 1;
$previewVars['selectedWorkplaceName'] = 'Interlomas';
$previewVars['selectedDate'] = '2026-11-19';
$previewVars['attendanceRows'] = array_values($previewRows);
$previewVars['workerSelectOptions'] = array_map(static fn(array $row): array => [
    'worker_id' => (int) $row['worker_id'],
    'full_name' => (string) $row['full_name'],
    'employee_number' => (string) $row['employee_number'],
], array_values($previewRows));
$previewVars['administrativeIncidences'] = $previewData['administrative_incidences'];
$previewVars['administrativeErrors'] = [];
$previewVars['incidenceCreateAllowed'] = false;
$previewVars['incidenceCreateKeys'] = [];
$previewVars['journeyEvaluationState'] = 'evaluated';
$previewVars['attendanceCoverage'] = ['status' => 'synthetic_qa_fixture', 'complete' => false];
$previewHtml = $render($previewVars);
$assert(!str_contains($previewHtml, 'Ejemplos ficticios exclusivamente visuales; no son asistencia real y no se guardan ni se envían')
    && !str_contains($previewHtml, 'Ejemplos visuales ficticios · sin localidad operativa')
    && !str_contains($previewHtml, 'data-checador-demo-banner')
    && str_contains($previewHtml, 'Ana Mercado') && str_contains($previewHtml, 'Luis Navarro')
    && str_contains($previewHtml, 'Carla Salas') && str_contains($previewHtml, 'Diego Molina')
    && str_contains($previewHtml, 'Elena Fuentes') && str_contains($previewHtml, 'Marcos Gil')
    && substr_count($previewHtml, 'modal-header modal-colored-header bg-primary text-white') === 7
    && substr_count($previewHtml, 'btn-close btn-close-white') === 7
    && str_contains($previewHtml, 'Entrada 1') && str_contains($previewHtml, 'Salida 2')
    && str_contains($previewHtml, 'id="checador-search-modal-toggle"')
    && str_contains($previewHtml, 'id="checador-filter-modal"')
    && !str_contains($previewHtml, 'Vista previa</a>')
    && preg_match('/demostraci[oó]n|datos\s+sint[eé]ticos|vista\s+previa|fictici[oa]s?/iu', html_entity_decode(strip_tags($previewHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== 1,
    'isolated fixture rendering retains six synthetic records and responsive detail modals without redundant warnings or subtitle');
$previewActionMenus = [];
preg_match_all('/<div class="dropdown-menu dropdown-menu-end" id="daily-attendance-actions-\d+">([\s\S]*?)<\/div>/', $previewHtml, $previewActionMenus);
$previewActionTexts = array_map(static fn(string $menu): string => trim(preg_replace('/\s+/u', ' ', strip_tags($menu)) ?? ''), $previewActionMenus[1] ?? []);
$assert(count($previewActionTexts) === 6
    && array_reduce($previewActionTexts, static fn(bool $onlyDetail, string $text): bool => $onlyDetail && $text === 'Ver detalle', true),
    'each isolated synthetic row action menu contains only Ver detalle');
$mainTableMatch = [];
preg_match('/<table[^>]+id="checador-daily-attendance-table"[^>]*>[\s\S]*?<tbody>([\s\S]*?)<\/tbody>/', $previewHtml, $mainTableMatch);
$mainTableRows = [];
preg_match_all('/<tr(?:\s|>)/', $mainTableMatch[1] ?? '', $mainTableRows);
$assert(count($mainTableRows[0] ?? []) === 6, 'isolated synthetic presentation renders only its six fixture rows');
$assert(str_contains($previewHtml, 'modal-dialog-scrollable modal-xl')
    && str_contains($previewHtml, 'modal-header modal-colored-header bg-primary text-white')
    && str_contains($previewHtml, 'row-cols-2 row-cols-lg-4')
    && str_contains($previewHtml, '<details class="mb-3" id="attendance-evaluated-events-900001">')
    && str_contains($previewHtml, 'Marcaciones evaluadas (4)')
    && str_contains($previewHtml, '<details class="mb-3" id="attendance-original-events-900001">')
    && str_contains($previewHtml, 'Marcaciones originales y trazabilidad (4 mostradas)')
    && !preg_match('/<details\b[^>]*\bopen(?:\s|>)/i', $previewHtml),
    'detail modal uses the wide scrollable layout and keeps both evidence tables collapsed with confirmed counts');
$assert(str_contains($previewHtml, 'title="Referencia abreviada"')
    && str_contains($previewHtml, 'title="Valor completo"')
    && str_contains($previewHtml, 'user-select-all')
    && str_contains($previewHtml, 'e7500000-0000-4000-8000-000000000101'),
    'long references stay abbreviated until expanded, with complete values selectable for copying');
$serverAccessCheck = strpos($dashboardWorkspaceSource, 'ChecadorAccessService::authorize(null)');
$contextWritePosition = strpos($dashboardWorkspaceSource, 'ModuleStationService::setChecadorWorkplaceContext($requestedWorkplace)');
$rosterReadPosition = strpos($dashboardWorkspaceSource, 'workersForAttendanceFilter($requestedWorkplace, $date, null, false)');
$assert(!str_contains($routesSource, 'asistencia/vista-previa')
    && !str_contains($controllerSource, 'dailyAttendancePreview')
    && $serverAccessCheck !== false
    && $contextWritePosition !== false && $rosterReadPosition !== false && $contextWritePosition < $rosterReadPosition
    && !str_contains($controllerSource, "\$query['preview']")
    && !str_contains($controllerSource, 'preview=detalle-sintetico')
    && !str_contains($dashboardWorkspaceSource, 'syntheticDetailPreviewMode')
    && !str_contains($dashboardWorkspaceSource, 'm01SyntheticDetailPreviewRows(')
    && str_contains($dashboardWorkspaceSource, 'workersForAttendanceFilter($requestedWorkplace, $date, null, false)')
    && str_contains($dashboardWorkspaceSource, "\$apiPostOperationsEnabled = \$m01DemoMode")
    && !str_contains($dashboardWorkspaceSource, 'm01SyntheticAttendanceRosterRows('),
    'normal M01 request revalidates and persists the selected locality before loading only its authorized roster; isolated fixtures cannot enter the operational path');

printf("CHECADOR_V8P_DAILY_ATTENDANCE_UI: %d PASS / %d FAIL\n", $assertions - $failures, $failures);
exit($failures === 0 ? 0 : 1);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
\App\Core\Session::init();

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$base = dirname(__DIR__);
$views = $base . '/app/Views/departamento-operativo/2-recursos-humanos/checador/';
$render = static function (string $file, array $vars) use ($views): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    include $views . $file;
    return (string) ob_get_clean();
};
$common = [
    'selectedWorkplaceName' => 'Interlomas & Centro',
    'selectedWorkplaceId' => 1,
    'selectedDate' => '2026-10-19',
    'selectedWorkerId' => 558,
    'demoMode' => false,
    'dashboardHref' => '/departamento-operativo/recursos-humanos/checador?workplace_id=1&date=2026-10-19&worker_id=558&snapshot_ref=V2REF-ABC123',
];

$controller = file_get_contents($base . '/app/Controllers/ChecadorController.php') ?: '';
$routes = file_get_contents($base . '/routes/web.php') ?: '';
$dashboard = file_get_contents($views . 'index.php') ?: '';
$dailyAttendance = file_get_contents($views . 'daily-attendance.php') ?: '';
$expectedRoutes = [
    '/recursos-humanos/checador/jornadas' => 'journeys',
    '/recursos-humanos/checador/incidencias' => 'incidences',
    '/recursos-humanos/checador/ajustes' => 'adjustments',
    '/recursos-humanos/checador/acceso-temporal' => 'temporaryAccess',
];
$routesComplete = true;
foreach ($expectedRoutes as $path => $action) {
    $routesComplete = $routesComplete
        && str_contains($routes, "GET', '{$path}'")
        && str_contains($routes, "['ChecadorController', '{$action}']")
        && str_contains($controller, "renderDashboardWorkspace('");
}
$assert($routesComplete, 'four missing modules have distinct authenticated GET routes and controller entry points');
$assert(str_contains($controller, "'journeys' => 'workspace-journeys'")
    && str_contains($controller, "'incidences' => 'workspace-incidences'")
    && str_contains($controller, "'adjustments' => 'workspace-adjustments'")
    && str_contains($controller, "'temporary-access' => 'workspace-temporary-access'"), 'controller dispatches each workspace to its own PHP view');
$assert(str_contains($routes, "'/recursos-humanos/checador'")
    && str_contains($controller, '$this->renderModuleDirectory();')
    && str_contains($routes, "'/recursos-humanos/checador/asistencia'")
    && str_contains($controller, "renderDashboardWorkspace('daily-attendance')"), 'root route is the clean module directory and daily attendance remains separately authenticated');
$assert(str_contains($controller, "'schedule-history' : (\$scheduleWeekMode ? 'schedule-week'")
    && str_contains($controller, "'journeys' => 'workspace-journeys'")
    && str_contains($controller, "'incidences' => 'workspace-incidences'")
    && str_contains($controller, "'adjustments' => 'workspace-adjustments'")
    && str_contains($controller, "'temporary-access' => 'workspace-temporary-access'"), 'daily, weekly, history and four operational modules dispatch to distinct views');
$temporaryReadCall = 'authorizeTemporaryAccess($requestedWorkplace, \'leer\')';
$temporarySqlCall = 'listForLocality($currentUser, $requestedWorkplace)';
$assert(str_contains($controller, $temporaryReadCall)
    && str_contains($controller, $temporarySqlCall)
    && strpos($controller, $temporaryReadCall) < strpos($controller, $temporarySqlCall), 'temporary-access GET rechecks its read grant before SQL queue retrieval');

$journey = $render('workspace-journeys.php', $common + [
    'state' => 'success',
    'workerSelectOptions' => [['worker_id' => 558, 'full_name' => 'Trabajadora de prueba']],
    'journeys' => [558 => [
        'id' => 558,
        'worker_id' => 558,
        'worker_name' => 'Trabajadora de prueba',
        'journey_status' => 'CALCULATED',
        'evaluated' => true,
        'context' => ['id_personal' => 558, 'id_estacion' => 1, 'fecha_local' => '2026-10-19', 'timezone' => 'America/Mexico_City'],
        'schedule' => ['estado_jornada' => 'SCHEDULED', 'scheduled_start_utc' => '2026-10-19 14:00:00', 'scheduled_end_utc' => '2026-10-19 23:00:00'],
        'pairing' => ['id' => 842, 'revision_numero' => 2, 'version_algoritmo' => 'p1-test', 'estado_pareo' => 'PAIRED'],
        'calculation' => ['estado_calculo' => 'CALCULATED', 'segundos_trabajados_totales' => 28800, 'revision_numero' => 1],
        'provenance' => ['l2_status_persisted' => 'INCOMPLETE', 'p1_algorithm_version' => 'p1-test', 'p2_algorithm_version' => 'p2-test'],
        'source_evidence' => ['level2_verification' => 'not_independently_verifiable'],
        'evaluated_raw_events' => [['pairing_role' => 'ENTRY', 'event_uuid' => 'event-1', 'ocurrido_at_utc' => '2026-10-19T14:00:00Z', 'id_dispositivo' => 2]],
    ]],
    'journeyErrors' => [],
]);
$assert(str_contains($journey, 'Jornadas laborales consultadas')
    && str_contains($journey, 'Trabajadora de prueba')
    && str_contains($journey, 'Cálculo persistido')
    && str_contains($journey, 'INCOMPLETE')
    && str_contains($journey, 'event-1')
    && str_contains($journey, '08:00')
    && !str_contains($journey, 'AUDIT-REF-1'), 'journey workspace renders the approved persisted SQL projection without legacy API-only evidence');
$tableEnd = strpos($journey, '</table>');
$detailModal = strpos($journey, 'id="journey-detail-558"');
$assert($tableEnd !== false && $detailModal !== false && $tableEnd < $detailModal,
    'journey detail modal is rendered after the table, outside DataTables rows');

$incidences = $render('workspace-incidences.php', $common + [
    'incidenceState' => 'ready', 'incidenceCanCreate' => false,
    'incidenceCanRevise' => false, 'incidenceLifecyclePermissions' => [],
]);
$assert(str_contains($incidences, 'Incidencias del personal')
    && str_contains($incidences, 'data-m06-data-url="/departamento-operativo/recursos-humanos/checador/incidencias/data"')
    && str_contains($incidences, 'm06-incidence-history-pane')
    && !str_contains($incidences, 'm06-incidence-create-modal'),
    'M06 renders the authorized read-only AJAX table and history without granting creation');

$adjustments = $render('workspace-adjustments.php', $common + [
    'adjustmentState' => 'ready',
    'adjustmentItems' => [[
        'current' => ['id'=>62, 'uuid'=>'adjustment-1', 'logical_uuid'=>'logical-1', 'worker_name'=>'Trabajadora de prueba', 'type'=>'OVERRIDE_STATUS', 'status'=>'ACTIVE', 'revision'=>2],
        'revisions' => [['revision'=>1, 'type'=>'OVERRIDE_STATUS', 'status'=>'ACTIVE', 'reason'=>'Motivo inicial']],
    ]],
    'adjustmentTypes' => ['OVERRIDE_STATUS'=>'Ajuste administrativo de estado'],
    'adjustmentStatuses' => ['ACTIVE'=>'Vigente'],
]);
$assert(str_contains($adjustments, 'Ajustes administrativos')
    && str_contains($adjustments, 'Ajuste administrativo de estado')
    && str_contains($adjustments, 'Vigente') && str_contains($adjustments, 'adjustment-1')
    && str_contains($adjustments, 'Motivo inicial')
    && !str_contains($adjustments, 'authorize_falta'),
    'M07 renders current and historical read-only SQL projections without FALTA writes');

$temporary = $render('workspace-temporary-access.php', $common + [
    'temporaryAccessPanel' => [
        'visible' => true, 'state' => 'success', 'requests' => [[
            'request_uuid' => '12345678-1234-4234-8234-123456789abc', 'id_personal_resuelto' => 558,
            'nombre_trabajador' => 'Colaborador de prueba', 'id_estacion' => 1, 'dispositivo_nombre' => 'Lector 2',
            'reason' => 'Contingencia', 'requested_at_utc' => '2026-10-19T12:00:00Z', 'request_expires_at_utc' => '2026-10-19T12:05:00Z',
        ]],
    ],
]);
$assert(str_contains($temporary, 'Solicitudes de acceso temporal')
    && str_contains($temporary, 'Colaborador de prueba')
    && str_contains($temporary, 'Vencimiento de solicitud (UTC)')
    && !str_contains($temporary, 'No incluido en esta lectura')
    && str_contains($temporary, 'PIN de configuración')
    && !str_contains($temporary, '/authorize"')
    && !str_contains($temporary, 'name="device_id"'), 'M08 retains both cards and shows no write action or PIN without the required grants');
$oneTimePin = $render('workspace-temporary-access.php', $common + [
    'canViewTemporaryPin' => true,
    'temporaryAccessFeedback' => ['type' => 'success', 'message' => 'Solicitud autorizada.', 'temporary_pin' => '482915', 'pin_expires_at_utc' => '2026-10-19T12:05:00Z'],
    'temporaryAccessPanel' => ['visible' => true, 'state' => 'success', 'requests' => []],
]);
$hiddenPin = $render('workspace-temporary-access.php', $common + [
    'canViewTemporaryPin' => false,
    'temporaryAccessFeedback' => ['type' => 'success', 'message' => 'Solicitud autorizada.', 'temporary_pin' => '482915', 'pin_expires_at_utc' => '2026-10-19T12:05:00Z'],
    'temporaryAccessPanel' => ['visible' => true, 'state' => 'success', 'requests' => []],
]);
$assert(str_contains($oneTimePin, '482915') && !str_contains($hiddenPin, '482915'), 'one-time PIN feedback is visible only after the controller rechecks the independent edit permission');

$assert(str_contains($controller, "renderDashboardWorkspace('daily-attendance')")
    && str_contains($controller, "'/jornadas'")
    && str_contains($controller, "'/incidencias'")
    && str_contains($controller, "'/ajustes'")
    && str_contains($controller, "'/acceso-temporal'")
    && str_contains($controller, "'/reporte'")
    && str_contains($controller, "'/comparacion-nomina'")
    && str_contains($controller, "'/configuracion'"), 'module directory links open their distinct functional routes');
$assert(str_contains($dailyAttendance, 'Entrada 1') && str_contains($dailyAttendance, 'Salida 1')
    && str_contains($dailyAttendance, 'Entrada 2') && str_contains($dailyAttendance, 'Salida 2'), 'daily table keeps the neutral four-position operational projection');
$assert(str_contains($controller, 'authorizeScheduling($requestedWorkplace, \'leer\')')
    && str_contains($controller, 'authorizeScheduling($workplaceId, \'leer\')'), 'weekly matrix and schedule history retain their separate read ACL gate');
$assert(str_contains($dailyAttendance, 'snapshot_ref') && str_contains($controller, '\'snapshot_ref\' => $snapshotRef'), 'daily detail navigation preserves the current snapshot and canonical station/date/worker context');
$workspaceReadGate = strpos($controller, 'if ($workspace !== \'temporary-access\' && $workspace !== \'journeys\' && !$workspaceSnapshotMissing && $requestedWorkplace !== null && $date !== \'\')');
$journeyReadGate = strpos($controller, '$workspace !== \'journeys\' && !$workspaceSnapshotMissing');
$journeyReadBlock = strpos($controller, 'persisted_read_contract_unavailable');
$snapshotCreateCall = strpos($controller, 'createDailyPunchSnapshot(');
$assert($workspaceReadGate !== false && $journeyReadGate !== false && $journeyReadBlock !== false && $snapshotCreateCall === false
    && str_contains($controller, "'daily-attendance' => 'daily-attendance'")
    && str_contains($controller, 'readDailySqlAttendance('), 'current M01 uses authorized SQL and does not create API snapshots on direct loads');
$moduleStart = strpos($controller, '$entries = [', strpos($controller, 'private function buildModuleNavigation'));
$moduleEnd = $moduleStart === false ? false : strpos($controller, '];', $moduleStart);
$moduleSource = $moduleStart !== false && $moduleEnd !== false ? substr($controller, $moduleStart, $moduleEnd - $moduleStart) : '';
$assert(!str_contains($moduleSource, '#journey-heading')
    && !str_contains($moduleSource, '#administrative-heading')
    && !str_contains($moduleSource, '#temporary-access-heading'), 'the module directory no longer points to embedded or data-dependent sections');

printf("CHECADOR_V8N_R1_FUNCTIONAL_VIEWS: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

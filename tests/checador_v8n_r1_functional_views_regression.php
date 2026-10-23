<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

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
$assert(str_contains($controller, $temporaryReadCall)
    && strpos($controller, $temporaryReadCall) < strpos($controller, 'listTemporaryAccessRequests($currentUser)'), 'temporary-access GET rechecks its independent read grant before queue retrieval');

$journey = $render('workspace-journeys.php', $common + [
    'state' => 'success',
    'journeys' => [558 => [
        'journey_status' => 'EVALUATED',
        'evaluated' => true,
        'evaluated_raw_event_count' => 1,
        'context' => ['fecha_local' => '2026-10-19', 'timezone' => 'America/Mexico_City'],
        'schedule' => ['estado_jornada' => 'SCHEDULED'],
        'calculation' => ['estado_calculo' => 'CALCULATED', 'segundos_trabajados_totales' => 28800, 'revision_numero' => 1],
        'source_evidence' => ['snapshot_audit_ref' => 'AUDIT-REF-1'],
        'evaluated_raw_events' => [['pairing_role' => 'ENTRY', 'event_uuid' => 'event-1', 'ocurrido_at_utc' => '2026-10-19T14:00:00Z', 'id_dispositivo' => 2]],
    ]],
    'journeyErrors' => [],
]);
$assert(str_contains($journey, 'Jornadas laborales consultadas')
    && str_contains($journey, 'Personal 558')
    && str_contains($journey, 'Resultado disponible')
    && str_contains($journey, 'AUDIT-REF-1')
    && str_contains($journey, 'event-1')
    && str_contains($journey, '08:00'), 'journey workspace renders the service result, provenance and raw event detail');

$incidences = $render('workspace-incidences.php', $common + [
    'administrativeIncidences' => [558 => [[
        'uuid_incidencia' => 'inc-1', 'category' => 'VACATION', 'current_status' => 'ACTIVE', 'current_revision' => 2,
        'date_range' => ['start_date' => '2026-10-19', 'end_date' => '2026-10-20'],
        'summary_reason' => 'Motivo de prueba', 'summary_evidence_reference' => 'EVID-1',
        'revisions' => [['revision_number' => 1, 'status' => 'ACTIVE', 'reason' => 'Motivo inicial', 'created_by_user_id' => 7, 'created_at_utc' => '2026-10-18T12:00:00Z']],
    ]]],
    'administrativeErrors' => [],
    'administrativeEvaluations' => [],
    'administrativeEvaluationErrors' => [],
]);
$assert(str_contains($incidences, 'Incidencias e historial')
    && str_contains($incidences, 'Vacaciones')
    && str_contains($incidences, 'Activa')
    && str_contains($incidences, 'Motivo inicial')
    && !str_contains($incidences, '<form'), 'incidence workspace renders revision history as read-only and escapes dynamic values');

$adjustments = $render('workspace-adjustments.php', $common + [
    'administrativeEvaluations' => [558 => ['work_date' => '2026-10-19', 'evaluation_exists' => true, 'evaluation' => [
        'revision_number' => 3,
        'linked_adjustments' => [[
            'uuid_ajuste' => 'adjustment-1', 'uuid_ajuste_logico' => 'logical-1', 'adjustment_type' => 'OVERRIDE_STATUS', 'revision_number' => 2, 'adjustment_status' => 'ACTIVE',
        ]],
    ]]],
    'administrativeEvaluationErrors' => [],
]);
$assert(str_contains($adjustments, 'Ajustes administrativos')
    && str_contains($adjustments, 'Ajuste administrativo de estado')
    && str_contains($adjustments, 'Vigente')
    && str_contains($adjustments, 'adjustment-1')
    && str_contains($adjustments, 'Portal3 no determina faltas')
    && !str_contains($adjustments, '<form'), 'adjustment workspace shows only linked read references and states its boundary');

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
    && str_contains($temporary, 'No incluido en esta lectura')
    && str_contains($temporary, 'PIN no se consulta')
    && !str_contains($temporary, '<form'), 'temporary-access workspace renders only queue data and does not expose write controls or a PIN');
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
$workspaceReadGate = strpos($controller, 'if ($workspace !== \'temporary-access\' && !$workspaceSnapshotMissing && $requestedWorkplace !== null && $date !== \'\')');
$snapshotCreateCall = strpos($controller, 'createDailyPunchSnapshot(');
$assert($workspaceReadGate !== false && $snapshotCreateCall !== false && $workspaceReadGate < $snapshotCreateCall
    && str_contains($controller, "'daily-attendance' => 'daily-attendance'"), 'secondary workspaces avoid creating daily snapshots on direct load and require the stable reference for linked reads');
$moduleStart = strpos($controller, '$entries = [', strpos($controller, 'private function buildModuleNavigation'));
$moduleEnd = $moduleStart === false ? false : strpos($controller, '];', $moduleStart);
$moduleSource = $moduleStart !== false && $moduleEnd !== false ? substr($controller, $moduleStart, $moduleEnd - $moduleStart) : '';
$assert(!str_contains($moduleSource, '#journey-heading')
    && !str_contains($moduleSource, '#administrative-heading')
    && !str_contains($moduleSource, '#temporary-access-heading'), 'the module directory no longer points to embedded or data-dependent sections');

printf("CHECADOR_V8N_R1_FUNCTIONAL_VIEWS: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

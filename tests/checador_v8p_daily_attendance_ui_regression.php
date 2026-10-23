<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php';
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$attendanceDataTableSource = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js') ?: '';
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
    'snapshotRef' => 'snapshot/ref-1',
    'dashboardHref' => '/departamento-operativo/recursos-humanos/checador?workplace_id=14',
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

preg_match('/<table class="table table-striped table-bordered text-nowrap align-middle mb-0" id="checador-daily-attendance-table">([\s\S]*?)<\/table>/', $html, $tableMatch);
$mainTable = $tableMatch[0] ?? '';
preg_match_all('/<th scope="col">/', $mainTable, $columnMatches);
$assert(count($columnMatches[0] ?? []) === 9, 'daily table exposes the nine operational columns without repeating date');
$assert(str_contains($html, '<h1 class="h3 mb-1">Asistencia del día</h1>')
    && str_contains($html, 'Estación:') && str_contains($html, 'Estación &amp; centro')
    && str_contains($html, '2026-10-23') && str_contains($html, 'Regresar a Checador'), 'header retains authorized station, work date and return navigation');
$assert(str_contains($html, 'data-bs-target="#checador-filter-modal"')
    && str_contains($html, 'id="checador-filter-modal"')
    && str_contains($html, 'id="date" name="date" type="date"')
    && str_contains($html, 'id="worker_id" name="worker_id" type="number"')
    && str_contains($html, 'name="workplace_id"')
    && str_contains($html, 'data-checador-clear-filters'), 'filter modal preserves required date, optional worker, station scope, apply and clear controls');
$assert(count(array_filter($renderedIds, static fn(int $count): bool => $count > 1)) === 0
    && !preg_match('/\sstyle="/i', $html), 'rendered daily view has unique control IDs and no inline styles');
$assert(str_contains($mainTable, 'Ana García') && str_contains($mainTable, 'EMP-009')
    && str_contains($mainTable, '08:00') && str_contains($mainTable, 'Entrada 1')
    && str_contains($mainTable, 'Salida 1') && str_contains($mainTable, 'Entrada 2') && str_contains($mainTable, 'Salida 2')
    && str_contains($mainTable, 'Información insuficiente'), 'main row prioritizes full name, effective schedule, neutral P3-H positions and translated status');
$assert(str_contains($mainTable, 'overflow-x-auto') === false
    && str_contains($html, 'table-responsive overflow-x-auto overflow-y-hidden pb-4')
    && str_contains($html, 'Un espacio vacío sólo indica'), 'table uses institutional horizontal responsive wrapper and avoids absence inference');
$assert(str_contains($mainTable, 'Ver detalle')
    && str_contains($html, 'id="daily-attendance-detail-900001-0"')
    && str_contains($html, 'Marcaciones originales y trazabilidad')
    && str_contains($html, '2026-10-23T14:00:00Z')
    && str_contains($html, 'Registro adicional') && str_contains($html, 'Registro sin emparejar')
    && str_contains($html, 'Integridad no verificada por Portal3'), 'detail modal preserves local/UTC event provenance and additional/unmatched events');
$assert(str_contains($html, '<div class="datatables">')
    && str_contains($controllerSource, "\$workspace === 'daily-attendance'")
    && str_contains($controllerSource, 'jquery.dataTables.min.js')
    && str_contains($attendanceDataTableSource, 'serverSide: false')
    && str_contains($attendanceDataTableSource, "targets: [8]")
    && str_contains($attendanceDataTableSource, "url: '/assets/libs/datatables.net/js/es-ES.json'"), 'daily attendance reuses the installed Bootstrap DataTables integration for the authorized result set');
$assert(str_contains($html, 'Incidencias relacionadas')
    && str_contains($html, 'Permiso') && str_contains($html, 'Activa') && str_contains($html, 'INCIDENCE-001')
    && !str_contains($mainTable, 'Registrar incidencia'), 'incidence detail is readable while the closed create gate shows no write action');
$assert(substr_count($html, '>Demo</span>') === 1
    && str_contains($html, 'snapshot_ref=snapshot%2Fref-1'), 'demo marker is discreet and detail navigation preserves encoded snapshot context');

$vars['incidenceCreateAllowed'] = true;
$vars['incidenceCreateKeys'] = [900001 => 'create-key'];
$authorizedHtml = $render($vars);
$assert(str_contains($authorizedHtml, 'Registrar incidencia')
    && str_contains($authorizedHtml, 'href="/departamento-operativo/recursos-humanos/checador/incidencias?workplace_id=14&amp;date=2026-10-23&amp;snapshot_ref=snapshot%2Fref-1&amp;worker_id=900001"')
    && !str_contains($authorizedHtml, 'method="post"'), 'incidence entry link appears only with existing server-provided permission and key and does not submit a write');

printf("CHECADOR_V8P_DAILY_ATTENDANCE_UI: %d PASS / %d FAIL\n", $assertions - $failures, $failures);
exit($failures === 0 ? 0 : 1);

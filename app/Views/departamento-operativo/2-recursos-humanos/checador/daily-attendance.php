<?php

declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$referenceDisclosure = static function (mixed $value) use ($esc): string {
    $full = trim((string) $value);
    if ($full === '') {
        return '<span class="text-muted">No disponible</span>';
    }
    $short = strlen($full) > 24 ? substr($full, 0, 12) . '…' . substr($full, -8) : $full;
    return '<details class="d-inline-block"><summary><code title="Referencia abreviada">' . $esc($short)
        . '</code></summary><code class="d-inline-block text-break user-select-all mt-1" title="Valor completo">'
        . $esc($full) . '</code></details>';
};
$selectedWorkplaceId = isset($selectedWorkplaceId) ? (int) $selectedWorkplaceId : null;
$selectedWorkplaceName = is_string($selectedWorkplaceName ?? null) ? trim($selectedWorkplaceName) : '';
$selectedWorkplaceDisplayName = $selectedWorkplaceName !== ''
    ? $selectedWorkplaceName
    : ($selectedWorkplaceId === null ? 'Sin centro seleccionado' : 'Centro autorizado');
$selectedDate = is_string($selectedDate ?? null) ? $selectedDate : '';
$selectedDateLabel = $selectedDate;
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $selectedDate, $dateParts) === 1
    && checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])) {
    $monthNames = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $selectedDateLabel = (int) $dateParts[3] . ' de ' . $monthNames[(int) $dateParts[2]] . ' de ' . $dateParts[1];
}
$selectedWorkerId = isset($selectedWorkerId) ? (int) $selectedWorkerId : null;
$workerSelectOptions = is_array($workerSelectOptions ?? null) ? $workerSelectOptions : [];
$workerSelectAvailable = $workerSelectOptions !== [];
$attendanceRows = is_array($attendanceRows ?? null) ? $attendanceRows : [];
$observedOnlyEvents = is_array($observedOnlyEvents ?? null) ? $observedOnlyEvents : [];
$attendanceCoverage = is_array($attendanceCoverage ?? null) ? $attendanceCoverage : [];
$journeyEvaluationState = is_string($journeyEvaluationState ?? null) ? $journeyEvaluationState : 'not_run';
$syntheticDetailPreviewMode = ($syntheticDetailPreviewMode ?? false) === true;
$syntheticDemoMode = ($demoMode ?? false) === true || $syntheticDetailPreviewMode;
$persistentQaFixtureMode = ($persistentQaFixtureMode ?? false) === true;
$attendanceRoster = is_array($attendanceRoster ?? null) ? $attendanceRoster : [];
$attendanceRosterFallbackAllowed = ($attendanceRosterFallbackAllowed ?? false) === true;
$administrativeIncidences = is_array($administrativeIncidences ?? null) ? $administrativeIncidences : [];
$administrativeErrors = is_array($administrativeErrors ?? null) ? $administrativeErrors : [];
$baseUrl = '/departamento-operativo/recursos-humanos/checador/asistencia';
$reportBaseUrl = '/departamento-operativo/recursos-humanos/checador/reporte';
$contextParams = [
    'workplace_id' => $selectedWorkplaceId,
    'date' => $selectedDate !== '' ? $selectedDate : null,
    'snapshot_ref' => is_string($snapshotRef ?? null) && $snapshotRef !== '' ? $snapshotRef : null,
];
$contextParams = array_filter($contextParams, static fn(mixed $value): bool => $value !== null && $value !== '');
$buildWorkspaceUrl = static function (string $path, array $params): string {
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return $path . ($query === '' ? '' : '?' . $query);
};
$dashboardHref = is_string($dashboardHref ?? null) && $dashboardHref !== ''
    ? $dashboardHref
    : '/departamento-operativo/recursos-humanos/checador';
$journeyBaseUrl = '/departamento-operativo/recursos-humanos/checador/jornadas';
$incidenceBaseUrl = '/departamento-operativo/recursos-humanos/checador/incidencias';
$state = (string) ($state ?? 'idle');
$successfulM01SqlRead = $state === 'success' && ($m01SqlReadSuccessful ?? false) === true;
$coverageStatus = (string) ($attendanceCoverage['status'] ?? 'unknown');
$showCoverageIndicator = $state === 'success'
    && ($attendanceCoverage['complete'] ?? false) !== true
    && in_array($coverageStatus, ['partial', 'source_completeness_unknown', 'synthetic_qa_fixture', 'synthetic_qa_fixture_partial'], true);
$showJourneyNotEvaluated = $state === 'success'
    && $journeyEvaluationState === 'not_evaluated'
    && !$persistentQaFixtureMode;
$attendanceTableEmptyMessage = match ($state) {
    'success' => 'Sin marcaciones observadas para esta consulta.',
    'loading' => 'Cargando la asistencia…',
    'idle' => 'Selecciona un centro autorizado para consultar la asistencia.',
    'snapshot_expired', 'snapshot_state_unavailable' => 'La consulta ya no está disponible. Vuelve a cargar la asistencia.',
    default => 'No fue posible consultar la asistencia. Intenta nuevamente.',
};
$stateMessageForAttendance = match ($state) {
    'success' => '',
    'idle' => 'Selecciona un centro autorizado para consultar la asistencia.',
    'snapshot_expired', 'snapshot_state_unavailable' => 'La consulta ya no está disponible. Vuelve a cargar la asistencia.',
    'error' => 'No fue posible cargar la asistencia. Intenta nuevamente más tarde.',
    default => 'La asistencia no está disponible para esta consulta.',
};
$slotDefinitions = [
    'ENTRY:1' => 'Entrada 1',
    'EXIT:1' => 'Salida 1',
    'ENTRY:2' => 'Entrada 2',
    'EXIT:2' => 'Salida 2',
];
$statusPresentation = static fn(string $status): array => match ($status) {
    'evaluated' => ['Evaluación disponible', 'text-bg-success'],
    'review' => ['Requiere revisión', 'text-bg-warning'],
    'ambiguous' => ['Registros por revisar', 'text-bg-warning'],
    'incomplete' => ['Información insuficiente', 'text-bg-info'],
    'classification_pending' => ['Pendiente de clasificación', 'text-bg-secondary'],
    'no_observed_event' => ['Sin marcación observada', 'text-bg-light border text-body-secondary'],
    'punches_unavailable' => ['Marcaciones no disponibles', 'text-bg-warning'],
    default => ['No determinado', 'text-bg-light border text-body-secondary'],
};
$scheduleStatusLabel = static fn(string $status): string => match ($status) {
    'DAY_OFF' => 'Descanso programado',
    'UNSCHEDULED' => 'Sin horario asignado',
    'UNKNOWN' => 'Horario no determinado',
    'SCHEDULED' => 'Horario disponible',
    default => 'Horario no disponible',
};
$incidenceCategoryLabel = static fn(mixed $category): string => match ((string) $category) {
    'VACATION' => 'Vacaciones',
    'PERMISSION' => 'Permiso',
    'INCAPACITY' => 'Incapacidad',
    'JUSTIFICATION' => 'Justificación',
    default => 'Tipo no disponible',
};

$viewRows = [];
foreach ($attendanceRows as $rowIndex => $attendanceRow) {
    if (!is_array($attendanceRow)) {
        continue;
    }
    $workerId = (int) ($attendanceRow['worker_id'] ?? 0);
    if ($workerId < 1) {
        continue;
    }

    $name = trim((string) ($attendanceRow['full_name'] ?? ''));
    if ($name === '' || preg_match('/^Trabajador\s+\d+$/u', $name) === 1) {
        $name = 'Nombre no disponible';
    }
    $schedule = is_array($attendanceRow['schedule'] ?? null) ? $attendanceRow['schedule'] : null;
    $scheduleStatus = (string) ($schedule['estado_jornada'] ?? 'UNKNOWN');
    $scheduleText = $scheduleStatusLabel($scheduleStatus);
    $segments = is_array($schedule['horario_local']['segmentos'] ?? null) ? $schedule['horario_local']['segmentos'] : [];
    if ($scheduleStatus === 'SCHEDULED' && $segments !== []) {
        $segmentLabels = [];
        foreach ($segments as $segment) {
            if (!is_array($segment)) {
                continue;
            }
            $start = substr((string) ($segment['hora_inicio_local'] ?? ''), 0, 5);
            $end = substr((string) ($segment['hora_fin_local'] ?? ''), 0, 5);
            $segmentLabels[] = ($start !== '' ? $start : '—') . '–' . ($end !== '' ? $end : '—')
                . ((int) ($segment['dia_offset_fin'] ?? 0) > 0 ? ' (día siguiente)' : '');
        }
        if ($segmentLabels !== []) {
            $scheduleText = implode('; ', $segmentLabels);
        }
    } elseif ($schedule === null && ($attendanceRow['schedule_error'] ?? null) !== null) {
        $scheduleText = 'Horario no disponible';
    }

    $slots = [];
    $ambiguousSlots = [];
    $evidence = [];
    foreach ($slotDefinitions as $slotKey => $label) {
        $slotValue = is_array($attendanceRow['slots'][$slotKey] ?? null) ? $attendanceRow['slots'][$slotKey] : [];
        $localTime = is_array($slotValue['time_local'] ?? null) ? $slotValue['time_local'] : null;
        $event = is_array($slotValue['event'] ?? null) ? $slotValue['event'] : null;
        $slots[$slotKey] = ['label' => $label, 'value' => $slotValue, 'time_local' => $localTime, 'event' => $event];
        if (($slotValue['ambiguous'] ?? false) === true) {
            $ambiguousSlots[$slotKey] = true;
        }
        if ($event !== null) {
            $evidence[] = ['label' => $label, 'event' => $event, 'local' => $localTime];
        }
    }
    foreach (is_array($attendanceRow['additional_events'] ?? null) ? $attendanceRow['additional_events'] : [] as $event) {
        if (!is_array($event)) {
            continue;
        }
        $role = (string) ($event['pairing_role'] ?? '');
        $ordinal = is_int($event['segment_ordinal'] ?? null) ? $event['segment_ordinal'] : null;
        $roleKey = in_array($role, ['ENTRY', 'EXIT'], true) && $ordinal !== null ? $role . ':' . $ordinal : '';
        $evidence[] = [
            'label' => isset($ambiguousSlots[$roleKey]) ? 'Registro con posición repetida' : match ($role) {
                'SURPLUS' => 'Registro adicional',
                'UNMATCHED' => 'Registro sin emparejar',
                'ENTRY', 'EXIT' => 'Evento adicional de jornada',
                default => 'Registro pendiente de clasificación',
            },
            'event' => $event,
            'local' => is_array($event['local_time'] ?? null) ? $event['local_time'] : null,
        ];
    }

    $observedTimeLabels = [];
    foreach (is_array($attendanceRow['additional_events'] ?? null) ? $attendanceRow['additional_events'] : [] as $event) {
        if (!is_array($event) || !is_array($event['local_time'] ?? null)) {
            continue;
        }
        $localTime = $event['local_time'];
        $time = trim((string) ($localTime['time'] ?? ''));
        if (preg_match('/\A\d{2}:\d{2}(?::\d{2})?\z/', $time) !== 1) {
            continue;
        }
        $date = trim((string) ($localTime['date'] ?? ''));
        $observedTimeLabels[] = $date !== '' && $date !== $selectedDate
            ? $date . ' ' . substr($time, 0, 5)
            : substr($time, 0, 5);
    }

    $status = $statusPresentation((string) ($attendanceRow['status'] ?? 'unknown'));
    $evaluatedRawEvents = is_array($attendanceRow['evaluated_raw_events'] ?? null)
        ? $attendanceRow['evaluated_raw_events'] : null;
    $evaluatedRawEventCount = is_int($attendanceRow['evaluated_raw_event_count'] ?? null)
        ? $attendanceRow['evaluated_raw_event_count'] : null;
    $evaluatedRawEventsComplete = ($attendanceRow['evaluated'] ?? null) === true
        && $evaluatedRawEvents !== null
        && $evaluatedRawEventCount !== null
        && $evaluatedRawEventCount === count($evaluatedRawEvents);
    $workerParams = $contextParams + ['worker_id' => $workerId];
    $journeyHref = $buildWorkspaceUrl($journeyBaseUrl, $workerParams);
    $incidenceHref = $buildWorkspaceUrl($incidenceBaseUrl, $workerParams);
    $reportHref = $selectedWorkplaceId !== null
        && $selectedWorkplaceId > 0
        && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D', $selectedDate) === 1
        ? $buildWorkspaceUrl($reportBaseUrl, [
            'source_workplace_id' => $selectedWorkplaceId,
            'source_work_date' => $selectedDate,
            'source_worker_id' => $workerId,
        ])
        : null;
    $incidenceList = is_array($administrativeIncidences[$workerId] ?? null) ? $administrativeIncidences[$workerId] : null;
    $incidenceError = array_key_exists($workerId, $administrativeErrors);
    $canCreateIncidence = ($incidenceCreateAllowed ?? false) === true
        && is_array($incidenceCreateKeys ?? null)
        && is_string($incidenceCreateKeys[$workerId] ?? null)
        && trim($incidenceCreateKeys[$workerId]) !== '';
    $viewRows[] = [
        'worker_id' => $workerId,
        'name' => $name,
        'employee_number' => trim((string) ($attendanceRow['employee_number'] ?? '')),
        'schedule' => $scheduleText,
        'slots' => $slots,
        'evidence' => $evidence,
        'observed_time_labels' => $observedTimeLabels,
        'evaluated_raw_event_count' => $evaluatedRawEventCount,
        'evaluated_raw_events' => $evaluatedRawEvents,
        'evaluated_raw_events_complete' => $evaluatedRawEventsComplete,
        'status' => $status,
        'incidence_count' => is_int($attendanceRow['incidence_count'] ?? null) ? $attendanceRow['incidence_count'] : null,
        'incidences' => $incidenceList,
        'incidence_error' => $incidenceError,
        'can_create_incidence' => $canCreateIncidence,
        'journey_href' => $journeyHref,
        'incidence_href' => $incidenceHref,
        'report_href' => $reportHref,
        'modal_id' => 'daily-attendance-detail-' . $workerId . '-' . (int) $rowIndex,
    ];
}

$rosterFallbackDisplayed = $state !== 'success'
    && $attendanceRosterFallbackAllowed
    && $viewRows !== [];
if ($rosterFallbackDisplayed) {
    $rosterMessage = trim((string) ($attendanceRoster['message'] ?? ''));
    $stateMessageForAttendance = 'Marcaciones no disponibles. Se muestra el padrón vigente autorizado como referencia; no confirma pertenencia histórica, asistencia ni horario para la fecha.'
        . ($rosterMessage !== '' ? ' ' . $rosterMessage : '');
} elseif ($state !== 'success') {
    // Only a roster explicitly marked displayable by the authenticated
    // controller may survive an unsuccessful API read.
    $viewRows = [];
}
?>

<div id="container" class="mb-4" data-attendance-state="<?= $esc($state) ?>" data-attendance-empty-message="<?= $esc($attendanceTableEmptyMessage) ?>">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="visually-hidden">Asistencia del día</h1>
            <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
                <?php if (!$syntheticDetailPreviewMode): ?>
                    <span class="d-inline-flex align-items-center gap-2">
                        <i class="ti ti-building-store" aria-hidden="true"></i>
                        <span><?= $esc(($selectedWorkplaceKind ?? 'Estación') . ':') ?> <strong class="text-body" data-checador-context-workplace><?= $esc($selectedWorkplaceDisplayName) ?></strong></span>
                    </span>
                <?php endif; ?>
                <?php if ($selectedDate !== ''): ?>
                    <span class="d-inline-flex align-items-center gap-2">
                        <i class="ti ti-calendar" aria-hidden="true"></i>
                        <span>Fecha laboral: <strong class="text-body" data-checador-context-date><?= $esc($selectedDateLabel) ?></strong></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-primary" id="checador-search-modal-toggle" data-bs-toggle="modal" data-bs-target="#checador-filter-modal" aria-controls="checador-filter-modal">
                <i class="ti ti-search me-1" aria-hidden="true"></i>Buscar
            </button>
        </div>
    </header>

    <div data-checador-attendance-results data-attendance-state="<?= $esc($state) ?>" data-attendance-roster-fallback="<?= $rosterFallbackDisplayed ? 'true' : 'false' ?>" data-workplace-id="<?= $selectedWorkplaceId ?? '' ?>">

    <?php if ($stateMessageForAttendance !== ''): ?>
        <div class="alert <?= $state === 'idle' ? 'alert-info' : 'alert-warning' ?> py-2" role="status">
            <?= $esc($stateMessageForAttendance) ?>
        </div>
    <?php elseif (!$successfulM01SqlRead && ($attendanceCoverage['complete'] ?? false) !== true): ?>
        <div class="alert alert-info py-2" role="status">Cobertura no confirmada: esta consulta no confirma que se hayan recibido todas las marcaciones del período.</div>
    <?php endif; ?>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table class="table table-striped table-bordered mb-0 text-nowrap align-middle portal-table portal-table--selectable" id="checador-daily-attendance-table">
                    <caption class="<?= ($showCoverageIndicator || $showJourneyNotEvaluated || $rosterFallbackDisplayed) ? 'caption-top text-start small text-body-secondary py-1' : 'visually-hidden' ?>">
                        <span class="visually-hidden"><?= $rosterFallbackDisplayed ? 'Padrón autorizado de referencia; no constituye evidencia de asistencia en la fecha consultada.' : 'Asistencia observada por colaborador para la fecha laboral seleccionada.' ?></span>
                        <?php if ($rosterFallbackDisplayed): ?><span class="d-inline-flex align-items-center gap-1" role="status"><i class="ti ti-info-circle text-warning-emphasis" aria-hidden="true"></i><span>PADRÓN DE REFERENCIA: las marcaciones no están disponibles.</span></span><?php endif; ?>
                        <?php if ($showCoverageIndicator && !$successfulM01SqlRead): ?>
                            <span class="d-inline-flex align-items-center gap-1" role="status"><i class="ti ti-info-circle text-warning-emphasis" aria-hidden="true"></i><span>Cobertura no confirmada</span></span>
                        <?php endif; ?>
                        <?php if ($showJourneyNotEvaluated): ?>
                            <span class="d-inline-flex align-items-center gap-1 ms-2" role="status"><i class="ti ti-info-circle" aria-hidden="true"></i><span>Journey no se evalúa al abrir Asistencia; las marcaciones quedan pendientes de clasificación.</span></span>
                        <?php endif; ?>
                        <?php if (!$successfulM01SqlRead): ?><span class="d-inline-flex align-items-center gap-1 ms-2"><i class="ti ti-info-circle" aria-hidden="true"></i><span>Una celda vacía no confirma una ausencia.</span></span><?php endif; ?>
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Nombre completo</th>
                            <th scope="col">Horario efectivo</th>
                            <th scope="col" class="text-nowrap">1.ª entrada</th>
                            <th scope="col" class="text-nowrap">1.ª salida</th>
                            <th scope="col" class="text-nowrap">2.ª entrada</th>
                            <th scope="col" class="text-nowrap">2.ª salida</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Incidencias</th>
                            <th scope="col" class="text-nowrap">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($viewRows === []): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <?php if ($state === 'success'): ?>No hay colaboradores atribuibles dentro de la información disponible para esta consulta.<?php else: ?>El padrón no está disponible y no se muestran personas sin una membresía autorizada verificable.<?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($viewRows as $viewRow): ?>
                                <tr data-worker-id="<?= (int) $viewRow['worker_id'] ?>">
                                    <th scope="row" class="text-wrap">
                                        <span class="fw-semibold"><?= $esc($viewRow['name']) ?></span>
                                        <?php if ($viewRow['employee_number'] !== ''): ?><span class="d-block small text-muted">N.º <?= $esc($viewRow['employee_number']) ?></span><?php elseif ($viewRow['name'] === 'Nombre no disponible'): ?><span class="d-block small text-muted">Referencia <?= (int) $viewRow['worker_id'] ?></span><?php endif; ?>
                                    </th>
                                    <td class="text-wrap"><?= $esc($viewRow['schedule']) ?></td>
                                    <?php foreach ($viewRow['slots'] as $slot): ?>
                                        <?php $time = $slot['time_local']; $slotValue = $slot['value']; ?>
                                        <td>
                                            <?php if (($slotValue['ambiguous'] ?? false) === true): ?>
                                                <span class="badge text-bg-warning">Requiere revisión</span>
                                            <?php elseif ($time !== null): ?>
                                                <time datetime="<?= $esc((string) ($time['utc'] ?? '')) ?>" title="Hora local<?= (($time['date'] ?? $selectedDate) !== $selectedDate) ? ' · ' . $esc((string) ($time['date'] ?? '')) : '' ?>">
                                                    <?= $esc((string) ($time['time'] ?? '—')) ?>
                                                    <?php if (($time['date'] ?? $selectedDate) !== $selectedDate): ?><span class="d-block small text-muted">Fecha local: <?= $esc((string) $time['date']) ?></span><?php endif; ?>
                                                </time>
                                            <?php elseif (is_array($slot['event'])): ?>
                                                <span class="text-muted">Hora local no disponible</span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <span class="badge <?= $esc($viewRow['status'][1]) ?>"><?= $esc($viewRow['status'][0]) ?></span>
                                        <?php if ($viewRow['observed_time_labels'] !== []): ?>
                                            <span class="d-block small text-body mt-1">
                                                <?= count($viewRow['observed_time_labels']) === 1 ? 'Marcación observada: ' : 'Marcaciones observadas: ' ?><?= $esc(implode(', ', $viewRow['observed_time_labels'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($viewRow['incidence_count'] !== null): ?>
                                            <span class="badge text-bg-light border"><?= (int) $viewRow['incidence_count'] ?></span>
                                            <span class="visually-hidden"><?= $viewRow['incidence_count'] === 1 ? 'incidencia registrada' : 'incidencias registradas' ?></span>
                                        <?php else: ?><span class="text-muted">No disponible</span><?php endif; ?>
                                    </td>
                                    <td class="text-center align-middle text-nowrap">
                                        <div class="dropdown">
                                            <a class="pointer" role="button"
                                               data-bs-toggle="dropdown" data-checador-attendance-actions
                                               aria-expanded="false" aria-haspopup="true"
                                               aria-controls="daily-attendance-actions-<?= (int) $viewRow['worker_id'] ?>"
                                               aria-label="Acciones para <?= $esc($viewRow['name']) ?><?= $rosterFallbackDisplayed ? '. No disponibles: marcaciones y adscripción histórica sin confirmar' : '' ?>"
                                               title="<?= $rosterFallbackDisplayed ? 'Acciones no disponibles sin marcaciones y adscripción histórica confirmadas.' : 'Acciones del colaborador' ?>">
                                                <i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i>
                                            </a>
                                            <div class="dropdown-menu dropdown-menu-end" id="daily-attendance-actions-<?= (int) $viewRow['worker_id'] ?>">
                                                <?php if ($rosterFallbackDisplayed): ?>
                                                    <a class="dropdown-item pointer disabled" aria-disabled="true" tabindex="-1"><i class="ti ti-eye me-1" aria-hidden="true"></i> Ver detalle</a>
                                                    <a class="dropdown-item pointer disabled" aria-disabled="true" tabindex="-1"><i class="ti ti-calendar-time me-1" aria-hidden="true"></i> Jornada</a>
                                                    <a class="dropdown-item pointer disabled" aria-disabled="true" tabindex="-1"><i class="ti ti-alert-circle me-1" aria-hidden="true"></i> Incidencias</a>
                                                    <?php if ($viewRow['can_create_incidence']): ?><a class="dropdown-item pointer disabled" aria-disabled="true" tabindex="-1"><i class="ti ti-plus me-1" aria-hidden="true"></i> Registrar incidencia</a><?php endif; ?>
                                                <?php elseif ($syntheticDetailPreviewMode): ?>
                                                    <button type="button" class="dropdown-item pointer" data-bs-toggle="modal" data-bs-target="#<?= $esc($viewRow['modal_id']) ?>"><i class="ti ti-eye me-1" aria-hidden="true"></i> Ver detalle</button>
                                                <?php elseif ($syntheticDemoMode): ?>
                                                    <button type="button" class="dropdown-item pointer" data-bs-toggle="modal" data-bs-target="#<?= $esc($viewRow['modal_id']) ?>"><i class="ti ti-eye me-1" aria-hidden="true"></i> Ver detalle</button>
                                                <?php else: ?>
                                                    <button type="button" class="dropdown-item pointer" data-bs-toggle="modal" data-bs-target="#<?= $esc($viewRow['modal_id']) ?>"><i class="ti ti-eye me-1" aria-hidden="true"></i> Ver detalle</button>
                                                    <a class="dropdown-item pointer" href="<?= $esc($viewRow['journey_href']) ?>"><i class="ti ti-calendar-time me-1" aria-hidden="true"></i> Jornada</a>
                                                    <a class="dropdown-item pointer" href="<?= $esc($viewRow['incidence_href']) ?>"><i class="ti ti-alert-circle me-1" aria-hidden="true"></i> Incidencias</a>
                                                    <?php if ($viewRow['can_create_incidence']): ?><a class="dropdown-item pointer" href="<?= $esc($viewRow['incidence_href']) ?>"><i class="ti ti-plus me-1" aria-hidden="true"></i> Registrar incidencia</a><?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    </table>
        </div>
    </div>

    <?php foreach ($rosterFallbackDisplayed ? [] : $viewRows as $viewRow): ?>
        <div class="modal fade" id="<?= $esc($viewRow['modal_id']) ?>" tabindex="-1" aria-labelledby="<?= $esc($viewRow['modal_id']) ?>-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable modal-xl">
                <div class="modal-content">
                    <div class="modal-header modal-colored-header bg-primary text-white">
                        <div>
                            <h2 class="modal-title h5 text-white mb-0" id="<?= $esc($viewRow['modal_id']) ?>-title">Detalle de asistencia · <?= $esc($viewRow['name']) ?></h2>
                            <p class="small text-white-50 mb-0"><?= $syntheticDetailPreviewMode ? $esc($selectedDate) : $esc($selectedWorkplaceName ?? 'Centro autorizado') . ' · ' . $esc($selectedDate) ?></p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar detalle de asistencia"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-6"><div class="border rounded p-2 h-100"><span class="d-block small text-muted">Horario efectivo</span><strong><?= $esc($viewRow['schedule']) ?></strong></div></div>
                            <div class="col-12 col-md-6"><div class="border rounded p-2 h-100"><span class="d-block small text-muted">Estado disponible</span><span class="badge <?= $esc($viewRow['status'][1]) ?>"><?= $esc($viewRow['status'][0]) ?></span></div></div>
                        </div>

                        <section class="mb-3" aria-labelledby="<?= $esc($viewRow['modal_id']) ?>-slots-title">
                        <h3 class="h6 mb-1" id="<?= $esc($viewRow['modal_id']) ?>-slots-title">Posiciones de marcación</h3>
                        <p class="small text-muted mb-2">Las posiciones Entrada 1, Salida 1, Entrada 2 y Salida 2 conservan la clasificación recibida; no se infieren posiciones ni ausencias a partir del orden de captura. Un espacio sin dato no representa una ausencia.</p>
                        <div class="row row-cols-2 row-cols-lg-4 g-2" aria-label="Cuatro posiciones de marcación en orden contractual">
                            <?php foreach ($viewRow['slots'] as $slot): ?>
                                <?php $slotValue = $slot['value']; $local = $slot['time_local']; $event = $slot['event']; ?>
                                <div class="col"><div class="border rounded p-2 h-100">
                                    <strong class="d-block"><?= $esc($slot['label']) ?></strong>
                                    <span class="d-block"><?php if (($slotValue['ambiguous'] ?? false) === true): ?>Requiere revisión<?php elseif ($local !== null): ?><?= $esc((string) ($local['date'] ?? '')) ?> <?= $esc((string) ($local['time'] ?? '')) ?><?php elseif ($event !== null): ?>Hora local no disponible<?php else: ?>Sin dato en esta consulta<?php endif; ?></span>
                                    <span class="small text-muted"><?= ($slotValue['ambiguous'] ?? false) === true ? 'Clasificación ambigua informada por la Jornada' : ($event !== null ? 'Registro observado' : 'Sin clasificación de ausencia') ?></span>
                                </div></div>
                            <?php endforeach; ?>
                        </div>
                        </section>

                        <?php if ($viewRow['evaluated_raw_events_complete']): ?>
                            <?php $evaluatedCount = (int) $viewRow['evaluated_raw_event_count']; ?>
                            <details class="mb-3" id="attendance-evaluated-events-<?= (int) $viewRow['worker_id'] ?>">
                                <summary><?= $evaluatedCount > 4 ? 'Ver todas las marcaciones (' . $evaluatedCount . ')' : 'Marcaciones evaluadas (' . $evaluatedCount . ')' ?></summary>
                                <p class="small text-muted mt-2">Conjunto y cantidad informados por la evaluación; no se infiere ausencia si está vacío.</p>
                                <?php if ($viewRow['evaluated_raw_events'] === []): ?>
                                    <p class="text-muted">La evaluación no incluyó marcaciones para esta consulta.</p>
                                <?php else: ?>
                                    <div class="table-responsive overflow-x-auto overflow-y-hidden mt-2">
                                        <table class="table table-sm table-striped table-bordered align-middle mb-0">
                                            <caption class="visually-hidden">Todas las marcaciones evaluadas por el servicio para personal <?= (int) $viewRow['worker_id'] ?></caption>
                                            <thead><tr><th class="text-nowrap" scope="col">Clasificación</th><th class="text-nowrap" scope="col">Evento</th><th class="text-nowrap" scope="col">Fecha y hora UTC</th><th class="text-nowrap" scope="col">Dispositivo</th><th class="text-nowrap" scope="col">Método</th></tr></thead>
                                            <tbody><?php foreach ($viewRow['evaluated_raw_events'] as $evaluatedEvent): if (!is_array($evaluatedEvent)) continue; ?>
                                                <tr>
                                                    <th class="text-nowrap" scope="row"><?= $esc(match ((string) ($evaluatedEvent['pairing_role'] ?? '')) { 'ENTRY' => 'Entrada', 'EXIT' => 'Salida', 'SURPLUS' => 'Marcación adicional', 'UNMATCHED' => 'Sin emparejar', default => 'Clasificación no disponible' }) ?></th>
                                                    <td><?= $referenceDisclosure($evaluatedEvent['event_uuid'] ?? 'No disponible') ?></td>
                                                    <td class="text-nowrap"><?= $esc($evaluatedEvent['ocurrido_at_utc'] ?? 'No disponible') ?></td>
                                                    <td class="text-nowrap"><?= is_int($evaluatedEvent['id_dispositivo'] ?? null) && $evaluatedEvent['id_dispositivo'] > 0 ? (int) $evaluatedEvent['id_dispositivo'] : 'No disponible' ?></td>
                                                    <td class="text-nowrap"><?= $esc(\App\Services\ChecadorPresentationFormatter::captureMethodLabel($evaluatedEvent['metodo_captura'] ?? null)) ?></td>
                                                </tr>
                                            <?php endforeach; ?></tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </details>
                        <?php elseif ($viewRow['evaluated_raw_event_count'] !== null || $viewRow['evaluated_raw_events'] !== null): ?>
                            <p class="alert alert-warning py-2" role="status">El conjunto de marcaciones evaluadas no pudo confirmarse; consulta la trazabilidad de la Jornada.</p>
                        <?php endif; ?>

                        <?php $traceCount = count($viewRow['evidence']); ?>
                        <details class="mb-3" id="attendance-original-events-<?= (int) $viewRow['worker_id'] ?>">
                        <summary>Marcaciones originales y trazabilidad<?= $traceCount > 0 ? ' (' . $traceCount . ' mostradas)' : '' ?></summary>
                        <div class="mt-2">
                        <?php if ($viewRow['evidence'] === []): ?>
                            <p class="text-muted mb-0">No hay marcaciones atribuibles que mostrar en el detalle de esta consulta.</p>
                        <?php else: ?>
                            <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
                                <table class="table table-sm table-striped table-bordered align-middle mb-0">
                                    <caption class="visually-hidden">Marcaciones originales, hora disponible, método y referencias del trabajador</caption>
                                    <thead><tr><th class="text-nowrap" scope="col">Posición o registro</th><th class="text-nowrap" scope="col">Hora local</th><th class="text-nowrap" scope="col">Fecha y hora UTC</th><th class="text-nowrap" scope="col">Dispositivo</th><th class="text-nowrap" scope="col">Método</th><th class="text-nowrap" scope="col">Recepción</th><th class="text-nowrap" scope="col">Integridad</th><th scope="col">Referencia</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($viewRow['evidence'] as $item): ?>
                                            <?php $event = $item['event']; $local = is_array($item['local']) ? $item['local'] : null; ?>
                                            <tr>
                                                <th class="text-nowrap" scope="row"><?= $esc($item['label']) ?></th>
                                                <td class="text-nowrap"><?= $local !== null ? $esc((string) ($local['date'] ?? '') . ' ' . (string) ($local['time'] ?? '')) : 'No disponible' ?></td>
                                                <td class="text-nowrap"><?= $esc((string) ($event['ocurrido_at_utc'] ?? $event['captured_at_utc'] ?? 'No disponible')) ?></td>
                                                <td class="text-nowrap"><?= isset($event['id_dispositivo']) ? (int) $event['id_dispositivo'] : 'No disponible' ?></td>
                                                <td class="text-nowrap"><?= $esc(\App\Services\ChecadorPresentationFormatter::captureMethodLabel($event['metodo_captura'] ?? null)) ?></td>
                                                <td class="text-nowrap"><?= $esc(\App\Services\ChecadorPresentationFormatter::processingStatusLabel($event['estado_procesamiento'] ?? $event['processing_status'] ?? null)) ?></td>
                                                <td class="text-nowrap">
                                                    <?php if (array_key_exists('integrity_seal_present', $event)): ?>
                                                        <?= !empty($event['integrity_seal_present']) ? 'Sello almacenado' : 'Sello no disponible' ?>; formato y verificación criptográfica no confirmados por Portal3
                                                    <?php else: ?><?= $esc(\App\Services\ChecadorPresentationFormatter::integrityLabel()) ?><?php endif; ?>
                                                </td>
                                                <td><?= $referenceDisclosure((string) ($event['event_uuid'] ?? $event['id_evento'] ?? $event['id'] ?? 'No disponible')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        </div>
                        </details>

                        <section class="border rounded p-3" aria-labelledby="<?= $esc($viewRow['modal_id']) ?>-incidences-title">
                        <h3 class="h6 mb-2" id="<?= $esc($viewRow['modal_id']) ?>-incidences-title">Incidencias relacionadas<?php if (!$viewRow['incidence_error'] && $viewRow['incidences'] !== null): ?> (<?= count($viewRow['incidences']) ?>)<?php endif; ?></h3>
                        <?php if ($viewRow['incidence_error']): ?>
                            <p class="alert alert-warning py-2" role="status">La consulta de incidencias no está disponible; no se confirma un resultado vacío.</p>
                        <?php elseif ($viewRow['incidences'] === null): ?>
                            <p class="text-muted">No hay una lectura de incidencias disponible para este colaborador.</p>
                        <?php elseif ($viewRow['incidences'] === []): ?>
                            <p class="text-muted">La lectura disponible no devolvió incidencias para esta consulta.</p>
                        <?php else: ?>
                            <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
                                <table class="table table-sm table-striped table-bordered align-middle mb-0">
                                    <thead><tr><th class="text-nowrap" scope="col">Tipo</th><th class="text-nowrap" scope="col">Periodo</th><th class="text-nowrap" scope="col">Estado</th><th class="text-nowrap" scope="col">Revisión</th><th scope="col">Referencia</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($viewRow['incidences'] as $incidence): ?>
                                            <tr>
                                                <th class="text-nowrap" scope="row"><?= $esc($incidenceCategoryLabel($incidence['category'] ?? null)) ?></th>
                                                <td class="text-nowrap"><?= $esc($incidence['date_range']['start_date'] ?? 'No disponible') ?> — <?= $esc($incidence['date_range']['end_date'] ?? 'No disponible') ?></td>
                                                <td class="text-nowrap"><?= $esc(\App\Services\ChecadorPresentationFormatter::incidenceStatusLabel($incidence['current_status'] ?? null)) ?></td>
                                                <td class="text-nowrap"><?= isset($incidence['current_revision']) ? (int) $incidence['current_revision'] : 'No disponible' ?></td>
                                                <td><?= $referenceDisclosure($incidence['uuid_incidencia'] ?? 'No disponible') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        </section>
                    </div>
                    <div class="modal-footer">
                        <?php if (!$syntheticDetailPreviewMode): ?><a class="btn btn-outline-secondary" href="<?= $esc($viewRow['incidence_href']) ?>">Ver incidencias</a><?php endif; ?>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($observedOnlyEvents !== []): ?>
        <section class="mt-3" aria-labelledby="m01-observed-outside-roster-title">
            <h2 class="h6" id="m01-observed-outside-roster-title">Eventos observados fuera del padrón acreditado</h2>
            <p class="small text-muted">Estos eventos se muestran aparte. No agregan trabajadores al padrón ni determinan entradas, salidas, faltas u horas trabajadas.</p>
            <div class="table-responsive overflow-x-auto overflow-y-hidden">
                <table class="table table-sm table-striped table-bordered align-middle">
                    <caption class="visually-hidden">Eventos de marcación observados sin adscripción histórica acreditada</caption>
                    <thead><tr><th scope="col">Identidad</th><th scope="col">Adscripción</th><th scope="col">Hora local</th><th scope="col">Fecha y hora UTC</th><th scope="col">Recepción</th><th scope="col">Integridad</th><th scope="col">Referencia</th></tr></thead>
                    <tbody>
                        <?php foreach ($observedOnlyEvents as $observedOnly): if (!is_array($observedOnly) || !is_array($observedOnly['event'] ?? null)) continue; $observedEvent = $observedOnly['event']; $observedLocal = is_array($observedEvent['local_time'] ?? null) ? $observedEvent['local_time'] : null; ?>
                            <tr>
                                <td><?= $esc($observedOnly['worker_label'] ?? 'Identidad no disponible') ?></td>
                                <td><?= $esc($observedOnly['membership_label'] ?? 'Adscripción no confirmada') ?></td>
                                <td class="text-nowrap"><?= $observedLocal !== null ? $esc((string) ($observedLocal['date'] ?? '') . ' ' . (string) ($observedLocal['time'] ?? '')) : 'No disponible' ?></td>
                                <td class="text-nowrap"><?= $esc($observedEvent['ocurrido_at_utc'] ?? 'No disponible') ?></td>
                                <td class="text-nowrap"><?= $esc(\App\Services\ChecadorPresentationFormatter::processingStatusLabel($observedEvent['processing_status'] ?? null)) ?></td>
                                <td><?= !empty($observedEvent['integrity_seal_present']) ? 'Sello almacenado' : 'Sello no disponible' ?>; formato y verificación criptográfica no confirmados por Portal3</td>
                                <td><?= $referenceDisclosure((string) ($observedEvent['event_uuid'] ?? $observedEvent['id'] ?? 'No disponible')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-0">El formato del sello no se valida en esta vista. La verificación criptográfica corresponde a API V2 y su resultado no se infiere de esta lectura.</p>
        </section>
    <?php endif; ?>
    </div>

<div class="modal fade" id="checador-filter-modal" tabindex="-1" aria-labelledby="checador-filter-modal-title" aria-hidden="true" data-checador-filter-modal>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="checador-filter-form" method="post" action="<?= $esc($baseUrl) ?>" data-checador-filter-form data-checador-ajax>
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h2 class="modal-title h5 text-white" id="checador-filter-modal-title"><i class="ti ti-search" aria-hidden="true"></i> Búsqueda avanzada</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar búsqueda avanzada"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" data-checador-search-error role="alert" aria-live="polite"></div>
                    <div id="checador-filter-controls" data-checador-filter-controls>
                    <div class="mb-3">
                        <label for="date" class="form-label">Fecha laboral</label>
                        <input id="date" name="date" type="date" class="form-control" value="<?= $esc($selectedDate) ?>" required>
                    </div>
                    <div id="checador-worker-select-wrapper" class="select2-modal-field is-select2-pending">
                        <label for="worker_id" class="form-label">Colaborador</label>
                        <select id="worker_id" name="worker_id" class="form-select"<?= !$workerSelectAvailable ? ' disabled' : '' ?>>
                            <option value=""<?= $selectedWorkerId === null ? ' selected' : '' ?>>Todos los colaboradores</option>
                            <?php foreach ($workerSelectOptions as $workerOption): ?>
                                <?php
                                if (!is_array($workerOption)) continue;
                                $optionWorkerId = filter_var($workerOption['worker_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                                $optionName = trim((string) ($workerOption['full_name'] ?? ''));
                                if ($optionWorkerId === false || $optionName === '' || preg_match('/^Trabajador\s+\d+$/u', $optionName) === 1) continue;
                                $optionNumber = trim((string) ($workerOption['employee_number'] ?? ''));
                                ?>
                                <option value="<?= (int) $optionWorkerId ?>"<?= $selectedWorkerId === (int) $optionWorkerId ? ' selected' : '' ?>><?= $esc($optionName) ?><?= $optionNumber !== '' ? ' · N.º ' . $esc($optionNumber) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid me-0 me-md-2 mb-1 mb-md-0">
                            <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x" aria-hidden="true"></i> Cancelar</button>
                        </div>
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn btn-primary" data-checador-search-action="search"><i class="ti ti-search" aria-hidden="true"></i> Buscar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<?php

declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$selectedWorkplaceId = isset($selectedWorkplaceId) ? (int) $selectedWorkplaceId : null;
$selectedDate = is_string($selectedDate ?? null) ? $selectedDate : '';
$selectedWorkerId = isset($selectedWorkerId) ? (int) $selectedWorkerId : null;
$attendanceRows = is_array($attendanceRows ?? null) ? $attendanceRows : [];
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
    'classification_pending' => ['Registros pendientes de clasificación', 'text-bg-secondary'],
    'no_observed_event' => ['Sin marcación observada', 'text-bg-light border text-body-secondary'],
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
            'local' => null,
        ];
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
?>

<div id="container" class="mt-4 mb-5">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1">Asistencia del día</h1>
            <div class="d-flex flex-wrap align-items-center gap-2 text-muted">
                <span>
                    <?= $esc(($selectedWorkplaceKind ?? 'Estación') . ':') ?>
                    <strong class="text-body"><?= $esc(($selectedWorkplaceName ?? '') !== '' ? $selectedWorkplaceName : 'Centro autorizado') ?></strong>
                </span>
                <?php if ($selectedDate !== ''): ?>
                    <span aria-hidden="true">·</span>
                    <span>Fecha laboral: <strong class="text-body"><?= $esc($selectedDate) ?></strong></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php if (($demoMode ?? false) === true): ?>
                <span class="badge rounded-pill text-bg-secondary" aria-label="Datos de demostración">Demo</span>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#checador-filter-modal">
                <i class="ti ti-filter me-1" aria-hidden="true"></i>Filtros avanzados
            </button>
            <a class="btn btn-outline-secondary" href="<?= $esc($dashboardHref) ?>">
                <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Regresar a Checador
            </a>
        </div>
    </header>

    <?php if (($realApiPostOperationsEnabled ?? false) !== true && ($demoMode ?? false) !== true): ?>
        <div class="alert alert-info py-2" role="status">Las operaciones POST reales de Checador están deshabilitadas mientras se valida el ambiente y la integración del servicio.</div>
    <?php endif; ?>

    <section class="card mb-3" aria-label="Filtros activos">
        <div class="card-body py-3 d-flex flex-wrap align-items-center gap-2">
            <span><strong>Fecha laboral:</strong> <?= $esc($selectedDate !== '' ? $selectedDate : 'No disponible') ?></span>
            <?php if ($selectedWorkerId !== null): ?><span><strong>Colaborador:</strong> <?= $selectedWorkerId ?></span><?php else: ?><span class="text-muted">Todos los colaboradores atribuibles</span><?php endif; ?>
            <span class="text-muted small ms-auto">Un espacio vacío sólo indica que no se mostró una marcación en esta consulta.</span>
        </div>
    </section>

    <?php if ($stateMessageForAttendance !== ''): ?>
        <div class="alert <?= $state === 'idle' ? 'alert-info' : 'alert-warning' ?> py-2" role="status">
            <?= $esc($stateMessageForAttendance) ?>
        </div>
    <?php elseif (($attendanceCoverage['complete'] ?? false) !== true): ?>
        <div class="alert alert-info py-2" role="status">La información disponible es parcial; se muestran sólo los registros que pudieron atribuirse.</div>
    <?php endif; ?>

    <section class="card" aria-labelledby="daily-attendance-table-title">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h2 class="h5 mb-0" id="daily-attendance-table-title">Personal y registros observados</h2>
            <?php if ($state === 'success'): ?>
                <span class="badge text-bg-light border"><?= count($viewRows) ?> colaborador<?= count($viewRows) === 1 ? '' : 'es' ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="datatables">
                <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
                    <table class="table table-striped table-bordered text-nowrap align-middle mb-0" id="checador-daily-attendance-table">
                    <caption class="visually-hidden">Asistencia observada por colaborador para la fecha laboral seleccionada. Las cuatro posiciones son neutrales y no clasifican automáticamente salidas a comer.</caption>
                    <thead>
                        <tr>
                            <th scope="col">Nombre completo</th>
                            <th scope="col">Horario efectivo</th>
                            <th scope="col">Entrada 1</th>
                            <th scope="col">Salida 1</th>
                            <th scope="col">Entrada 2</th>
                            <th scope="col">Salida 2</th>
                            <th scope="col">Estado</th>
                            <th scope="col">Incidencias</th>
                            <th scope="col">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($state !== 'success' || $viewRows === []): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <?php if ($state === 'success'): ?>No hay colaboradores atribuibles dentro de la información disponible para esta consulta.<?php else: ?>La tabla estará disponible cuando la consulta se complete.<?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($viewRows as $viewRow): ?>
                                <tr>
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
                                    <td><span class="badge <?= $esc($viewRow['status'][1]) ?>"><?= $esc($viewRow['status'][0]) ?></span></td>
                                    <td>
                                        <?php if ($viewRow['incidence_count'] !== null): ?>
                                            <span class="badge text-bg-light border"><?= (int) $viewRow['incidence_count'] ?></span>
                                            <span class="visually-hidden"><?= $viewRow['incidence_count'] === 1 ? 'incidencia registrada' : 'incidencias registradas' ?></span>
                                        <?php else: ?><span class="text-muted">No disponible</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $esc($viewRow['modal_id']) ?>">
                                                <i class="ti ti-eye me-1" aria-hidden="true"></i>Ver detalle
                                            </button>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= $esc($viewRow['journey_href']) ?>">Jornada</a>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= $esc($viewRow['incidence_href']) ?>">Incidencias</a>
                                            <?php if ($viewRow['report_href'] !== null): ?>
                                                <a class="btn btn-sm btn-outline-secondary" href="<?= $esc($viewRow['report_href']) ?>">Marcaciones</a>
                                            <?php endif; ?>
                                            <?php if ($viewRow['can_create_incidence']): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="<?= $esc($viewRow['incidence_href']) ?>">Registrar incidencia</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <?php foreach ($viewRows as $viewRow): ?>
        <div class="modal fade" id="<?= $esc($viewRow['modal_id']) ?>" tabindex="-1" aria-labelledby="<?= $esc($viewRow['modal_id']) ?>-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5" id="<?= $esc($viewRow['modal_id']) ?>-title">Detalle de asistencia · <?= $esc($viewRow['name']) ?></h2>
                            <p class="small text-muted mb-0"><?= $esc($selectedWorkplaceName ?? 'Centro autorizado') ?> · <?= $esc($selectedDate) ?></p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar detalle de asistencia"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><span class="d-block small text-muted">Horario efectivo</span><strong><?= $esc($viewRow['schedule']) ?></strong></div></div>
                            <div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><span class="d-block small text-muted">Estado disponible</span><span class="badge <?= $esc($viewRow['status'][1]) ?>"><?= $esc($viewRow['status'][0]) ?></span></div></div>
                        </div>

                        <h3 class="h6">Posiciones de marcación</h3>
                        <p class="small text-muted">Las posiciones Entrada 1, Salida 1, Entrada 2 y Salida 2 conservan la clasificación recibida; no se infieren posiciones ni ausencias a partir del orden de captura. Un espacio sin dato no representa una ausencia.</p>
                        <div class="row row-cols-1 row-cols-sm-2 g-2 mb-4" aria-label="Cuatro posiciones de marcación en orden contractual">
                            <?php foreach ($viewRow['slots'] as $slot): ?>
                                <?php $slotValue = $slot['value']; $local = $slot['time_local']; $event = $slot['event']; ?>
                                <div class="col"><div class="border rounded p-3 h-100">
                                    <strong class="d-block"><?= $esc($slot['label']) ?></strong>
                                    <span class="d-block"><?php if (($slotValue['ambiguous'] ?? false) === true): ?>Requiere revisión<?php elseif ($local !== null): ?><?= $esc((string) ($local['date'] ?? '')) ?> <?= $esc((string) ($local['time'] ?? '')) ?><?php elseif ($event !== null): ?>Hora local no disponible<?php else: ?>Sin dato en esta consulta<?php endif; ?></span>
                                    <span class="small text-muted"><?= ($slotValue['ambiguous'] ?? false) === true ? 'Clasificación ambigua informada por la Jornada' : ($event !== null ? 'Registro observado' : 'Sin clasificación de ausencia') ?></span>
                                </div></div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($viewRow['evaluated_raw_events_complete']): ?>
                            <?php $evaluatedCount = (int) $viewRow['evaluated_raw_event_count']; ?>
                            <details class="mb-4" id="attendance-evaluated-events-<?= (int) $viewRow['worker_id'] ?>">
                                <summary><?= $evaluatedCount > 4 ? 'Ver todas las marcaciones (' . $evaluatedCount . ')' : 'Marcaciones evaluadas (' . $evaluatedCount . ')' ?></summary>
                                <p class="small text-muted mt-2">Conjunto y cantidad informados por la evaluación; no se infiere ausencia si está vacío.</p>
                                <?php if ($viewRow['evaluated_raw_events'] === []): ?>
                                    <p class="text-muted">La evaluación no incluyó marcaciones para esta consulta.</p>
                                <?php else: ?>
                                    <div class="table-responsive overflow-x-auto overflow-y-hidden mt-2">
                                        <table class="table table-sm table-striped table-bordered align-middle mb-0">
                                            <caption class="visually-hidden">Todas las marcaciones evaluadas por el servicio para personal <?= (int) $viewRow['worker_id'] ?></caption>
                                            <thead><tr><th scope="col">Clasificación</th><th scope="col">Evento</th><th scope="col">Fecha y hora UTC</th><th scope="col">Dispositivo</th><th scope="col">Método</th></tr></thead>
                                            <tbody><?php foreach ($viewRow['evaluated_raw_events'] as $evaluatedEvent): if (!is_array($evaluatedEvent)) continue; ?>
                                                <tr>
                                                    <th scope="row"><?= $esc(match ((string) ($evaluatedEvent['pairing_role'] ?? '')) { 'ENTRY' => 'Entrada', 'EXIT' => 'Salida', 'SURPLUS' => 'Marcación adicional', 'UNMATCHED' => 'Sin emparejar', default => 'Clasificación no disponible' }) ?></th>
                                                    <td class="text-break"><code><?= $esc($evaluatedEvent['event_uuid'] ?? 'No disponible') ?></code></td>
                                                    <td><?= $esc($evaluatedEvent['ocurrido_at_utc'] ?? 'No disponible') ?></td>
                                                    <td><?= is_int($evaluatedEvent['id_dispositivo'] ?? null) && $evaluatedEvent['id_dispositivo'] > 0 ? (int) $evaluatedEvent['id_dispositivo'] : 'No disponible' ?></td>
                                                    <td><?= $esc(\App\Services\ChecadorPresentationFormatter::captureMethodLabel($evaluatedEvent['metodo_captura'] ?? null)) ?></td>
                                                </tr>
                                            <?php endforeach; ?></tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </details>
                        <?php elseif ($viewRow['evaluated_raw_event_count'] !== null || $viewRow['evaluated_raw_events'] !== null): ?>
                            <p class="alert alert-warning py-2" role="status">El conjunto de marcaciones evaluadas no pudo confirmarse; consulta la trazabilidad de la Jornada.</p>
                        <?php endif; ?>

                        <h3 class="h6">Marcaciones originales y trazabilidad</h3>
                        <?php if ($viewRow['evidence'] === []): ?>
                            <p class="text-muted">No hay marcaciones atribuibles que mostrar en el detalle de esta consulta.</p>
                        <?php else: ?>
                            <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2 mb-4">
                                <table class="table table-striped table-bordered align-middle mb-0">
                                    <caption class="visually-hidden">Marcaciones originales, hora disponible, método y referencias del trabajador</caption>
                                    <thead><tr><th scope="col">Posición o registro</th><th scope="col">Hora local</th><th scope="col">Fecha y hora UTC</th><th scope="col">Dispositivo</th><th scope="col">Método</th><th scope="col">Recepción</th><th scope="col">Integridad</th><th scope="col">Referencia</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($viewRow['evidence'] as $item): ?>
                                            <?php $event = $item['event']; $local = is_array($item['local']) ? $item['local'] : null; ?>
                                            <tr>
                                                <th scope="row"><?= $esc($item['label']) ?></th>
                                                <td><?= $local !== null ? $esc((string) ($local['date'] ?? '') . ' ' . (string) ($local['time'] ?? '')) : 'No disponible' ?></td>
                                                <td><?= $esc((string) ($event['ocurrido_at_utc'] ?? $event['captured_at_utc'] ?? 'No disponible')) ?></td>
                                                <td><?= isset($event['id_dispositivo']) ? (int) $event['id_dispositivo'] : 'No disponible' ?></td>
                                                <td><?= $esc(\App\Services\ChecadorPresentationFormatter::captureMethodLabel($event['metodo_captura'] ?? null)) ?></td>
                                                <td><?= $esc(\App\Services\ChecadorPresentationFormatter::processingStatusLabel($event['estado_procesamiento'] ?? $event['processing_status'] ?? null)) ?></td>
                                                <td><?= $esc(\App\Services\ChecadorPresentationFormatter::integrityLabel()) ?></td>
                                                <td class="text-break"><code><?= $esc((string) ($event['event_uuid'] ?? $event['id_evento'] ?? 'No disponible')) ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <h3 class="h6">Incidencias relacionadas</h3>
                        <?php if ($viewRow['incidence_error']): ?>
                            <p class="alert alert-warning py-2" role="status">La consulta de incidencias no está disponible; no se confirma un resultado vacío.</p>
                        <?php elseif ($viewRow['incidences'] === null): ?>
                            <p class="text-muted">No hay una lectura de incidencias disponible para este colaborador.</p>
                        <?php elseif ($viewRow['incidences'] === []): ?>
                            <p class="text-muted">La lectura disponible no devolvió incidencias para esta consulta.</p>
                        <?php else: ?>
                            <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
                                <table class="table table-sm table-striped table-bordered align-middle mb-0">
                                    <thead><tr><th scope="col">Tipo</th><th scope="col">Periodo</th><th scope="col">Estado</th><th scope="col">Revisión</th><th scope="col">Referencia</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($viewRow['incidences'] as $incidence): ?>
                                            <tr>
                                                <th scope="row"><?= $esc($incidenceCategoryLabel($incidence['category'] ?? null)) ?></th>
                                                <td><?= $esc($incidence['date_range']['start_date'] ?? 'No disponible') ?> — <?= $esc($incidence['date_range']['end_date'] ?? 'No disponible') ?></td>
                                                <td><?= $esc(\App\Services\ChecadorPresentationFormatter::incidenceStatusLabel($incidence['current_status'] ?? null)) ?></td>
                                                <td><?= isset($incidence['current_revision']) ? (int) $incidence['current_revision'] : 'No disponible' ?></td>
                                                <td class="text-break"><code><?= $esc($incidence['uuid_incidencia'] ?? 'No disponible') ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-outline-secondary" href="<?= $esc($viewRow['incidence_href']) ?>">Ver incidencias</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="checador-filter-modal" tabindex="-1" aria-labelledby="checador-filter-modal-title" aria-hidden="true" data-checador-filter-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="checador-filter-modal-title">Filtros de asistencia</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar filtros"></button>
            </div>
            <form id="checador-filter-form" method="get" action="<?= $esc($baseUrl) ?>" data-checador-filter-form>
                <div class="modal-body">
                    <?php if ($selectedWorkplaceId !== null): ?><input id="workplace_id" name="workplace_id" type="hidden" value="<?= $selectedWorkplaceId ?>"><?php endif; ?>
                    <div class="mb-3">
                        <label for="date" class="form-label">Fecha laboral</label>
                        <input id="date" name="date" type="date" class="form-control" value="<?= $esc($selectedDate) ?>" required>
                    </div>
                    <div>
                        <label for="worker_id" class="form-label">Colaborador (opcional)</label>
                        <input id="worker_id" name="worker_id" type="number" min="1" step="1" class="form-control" value="<?= $selectedWorkerId === null ? '' : $selectedWorkerId ?>" inputmode="numeric">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-outline-secondary" formnovalidate data-checador-clear-filters>Limpiar filtros</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                </div>
            </form>
        </div>
    </div>
</div>

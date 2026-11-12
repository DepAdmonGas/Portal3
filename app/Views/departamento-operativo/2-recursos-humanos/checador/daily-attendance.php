<?php

declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
$workerSelectStatus = is_string($workerSelectStatus ?? null) ? $workerSelectStatus : 'unavailable';
$workerSelectAvailable = $selectedWorkplaceId !== null && $selectedWorkplaceId > 0;
$attendanceRows = is_array($attendanceRows ?? null) ? $attendanceRows : [];
$attendanceCoverage = is_array($attendanceCoverage ?? null) ? $attendanceCoverage : [];
$persistentQaFixtureMode = ($persistentQaFixtureMode ?? false) === true;
$workplaceTimezone = is_array($timezone ?? null) && is_string($timezone['timezone'] ?? null) ? $timezone['timezone'] : null;
$administrativeIncidences = is_array($administrativeIncidences ?? null) ? $administrativeIncidences : [];
$administrativeErrors = is_array($administrativeErrors ?? null) ? $administrativeErrors : [];
$baseUrl = '/departamento-operativo/recursos-humanos/checador/asistencia';
$reportBaseUrl = '/departamento-operativo/recursos-humanos/checador/reporte';
$contextParams = [
    'date' => $selectedDate !== '' ? $selectedDate : null,
    'snapshot_ref' => is_string($snapshotRef ?? null) && $snapshotRef !== '' ? $snapshotRef : null,
];
$contextParams = array_filter($contextParams, static fn(mixed $value): bool => $value !== null && $value !== '');
$buildWorkspaceUrl = static function (string $path, array $params): string {
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    return $path . ($query === '' ? '' : '?' . $query);
};
$journeyBaseUrl = '/departamento-operativo/recursos-humanos/checador/jornadas';
$incidenceBaseUrl = '/departamento-operativo/recursos-humanos/checador/incidencias';
$state = (string) ($state ?? 'idle');
$journeyEvaluationState = (string) ($journeyEvaluationState ?? 'not_run');
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
    'timezone_unresolved' => 'La zona horaria no está disponible para la fecha seleccionada.',
    default => 'No fue posible consultar la asistencia. Intenta nuevamente.',
};
$slotDefinitions = [
    'ENTRY:1' => '1.ª entrada',
    'EXIT:1' => '1.ª salida',
    'ENTRY:2' => '2.ª entrada',
    'EXIT:2' => '2.ª salida',
];
$statusPresentation = static fn(string $status): array => match ($status) {
        'evaluated' => ['Clasificada', 'text-bg-success'],
        'review' => ['Requiere revisión', 'text-bg-warning'],
        'ambiguous' => ['Registros por revisar', 'text-bg-warning'],
        'incomplete' => ['Información incompleta', 'text-bg-info'],
        'classification_pending', 'evaluation_unavailable' => ['Pendiente de clasificación', 'text-bg-secondary'],
        'no_observed_event' => ['Sin marcaciones observadas', 'text-bg-light border text-body-secondary'],
        'punches_unavailable' => ['—', 'text-bg-light border text-body-secondary'],
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
    $isPersistentQaFixture = ($attendanceRow['qa_fixture'] ?? false) === true;
    $qaFixtureHasNoCanonicalEvaluation = $isPersistentQaFixture
        && ($attendanceRow['classification_available'] ?? true) === false
        && in_array((string) ($attendanceRow['journey_status'] ?? ''), ['QA_SIMULATED', 'NOT_EVALUATED_IN_QA_FIXTURE'], true)
        && in_array((string) ($attendanceRow['pairing_status'] ?? ''), ['QA_SIMULATED', 'NOT_EVALUATED_IN_QA_FIXTURE'], true);
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
    $classificationPending = in_array((string) ($attendanceRow['status'] ?? ''), ['classification_pending', 'evaluation_unavailable'], true);
        $evidence[] = [
        'label' => $classificationPending
                ? 'Pendiente de clasificación'
                : (($event['pairing_segment_status'] ?? null) === 'AMBIGUOUS_PAIRING'
                ? 'Registro de segmento por revisar'
                : (isset($ambiguousSlots[$roleKey]) ? 'Registro con posición repetida' : match ($role) {
                'SURPLUS' => 'Registro adicional',
                'UNMATCHED' => 'Registro sin emparejar',
                'ENTRY', 'EXIT' => 'Evento adicional de jornada',
                default => 'Pendiente de clasificación',
            })),
            'event' => $event,
            'local' => \App\Services\ChecadorAttendanceProjection::localEventTime($event, $workplaceTimezone),
        ];
    }

    $status = $qaFixtureHasNoCanonicalEvaluation
        ? match ((string) ($attendanceRow['qa_presentation_status'] ?? '')) {
            'complete' => ['Jornada completa', 'text-bg-success'],
            'incomplete' => ['Jornada incompleta', 'text-bg-info'],
            'review' => ['Requiere revisión', 'text-bg-warning'],
            default => ['No determinado', 'text-bg-light border text-body-secondary'],
        }
        : $statusPresentation((string) ($attendanceRow['status'] ?? 'unknown'));
    $qaInformationalLabels = [
        'positions_complete' => ['Posiciones completas', 'text-bg-success'],
        'partial_record' => ['Registro parcial', 'text-bg-info'],
        'schedule_review' => ['Horario por revisar', 'text-bg-warning'],
    ];
    $qaScenarioMetadata = is_array($attendanceRow['qa_scenario_metadata'] ?? null)
        ? $attendanceRow['qa_scenario_metadata']
        : [];
    $qaScenarioEventUuids = is_array($qaScenarioMetadata['qa_event_uuids'] ?? null)
        ? array_values(array_map('strval', $qaScenarioMetadata['qa_event_uuids']))
        : [];
    $renderedEvidenceUuids = array_values(array_filter(array_map(
        static fn(array $item): string => (string) ($item['event']['event_uuid'] ?? ''),
        $evidence
    ), static fn(string $uuid): bool => $uuid !== ''));
    sort($qaScenarioEventUuids);
    sort($renderedEvidenceUuids);
    $qaInformationalState = (string) ($attendanceRow['qa_informational_state'] ?? '');
    $qaInformationalAllowed = ($attendanceRow['qa_source_classification'] ?? null) === 'SYNTHETIC_QA_FIXTURE'
        && $persistentQaFixtureMode
        && $selectedWorkplaceId === \App\Services\ChecadorPersistentQaFixtureService::WORKPLACE_ID
        && $selectedDate === \App\Services\ChecadorPersistentQaFixtureService::ADDITIONAL_DAILY_DATE
        && isset($qaInformationalLabels[$qaInformationalState])
        && ($attendanceRow['qa_source_classification'] ?? null) === 'SYNTHETIC_QA_FIXTURE'
        && ($attendanceRow['qa_scenario_event_set_closed'] ?? false) === true
        && ($attendanceRow['journey_status'] ?? null) === 'NOT_EVALUATED_IN_QA_FIXTURE'
        && ($attendanceRow['pairing_status'] ?? null) === 'NOT_EVALUATED_IN_QA_FIXTURE'
        && ($attendanceRow['source_level2_status'] ?? null) === 'NOT_EVALUATED_IN_QA_FIXTURE'
        && ($attendanceRow['classification_available'] ?? true) === false
        && ($qaScenarioMetadata['qa_workplace_id'] ?? null) === $selectedWorkplaceId
        && ($qaScenarioMetadata['qa_local_date'] ?? null) === $selectedDate
        && ($qaScenarioMetadata['qa_worker_id'] ?? null) === $workerId
        && ($qaScenarioMetadata['qa_fixture_version'] ?? null) === ($attendanceRow['qa_fixture_version'] ?? null)
        && ($qaScenarioMetadata['qa_schedule_source_classification'] ?? null) === 'SYNTHETIC_QA_FIXTURE'
        && ($qaScenarioMetadata['qa_schedule_source_date'] ?? null) === $selectedDate
        && is_array($qaScenarioMetadata['qa_schedule_segments'] ?? null)
        && $qaScenarioMetadata['qa_schedule_segments'] !== []
        && ($qaScenarioMetadata['qa_event_set_closed'] ?? false) === true
        && ($qaScenarioMetadata['qa_informational_state'] ?? null) === $qaInformationalState
        && $qaScenarioEventUuids === $renderedEvidenceUuids;
    $tableStatus = $qaInformationalAllowed ? $qaInformationalLabels[$qaInformationalState] : $status;
    $classificationPending = in_array((string) ($attendanceRow['status'] ?? ''), ['classification_pending', 'evaluation_unavailable'], true);
    $additionalSegmentCount = ($attendanceRow['classification_available'] ?? false) === true
        && is_int($attendanceRow['additional_segment_count'] ?? null)
        ? max(0, $attendanceRow['additional_segment_count'])
        : 0;
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
    $relationship = match ($attendanceRow['attendance_relationship'] ?? 'observed') {
        'habitual' => ['label' => 'Padrón habitual actual', 'class' => 'text-bg-primary'],
        'habitual_and_scheduled' => ['label' => 'Padrón habitual · programación registrada', 'class' => 'text-bg-primary'],
        'historical_membership' => ['label' => 'Adscripción histórica verificada', 'class' => 'text-bg-primary'],
        'historical_and_scheduled' => ['label' => 'Adscripción histórica · programación registrada', 'class' => 'text-bg-primary'],
        'current_roster_historical_unverified' => ['label' => 'Padrón actual · pertenencia histórica no verificada', 'class' => 'text-bg-warning'],
        'scheduled_comodine' => ['label' => 'Comodín programado · autorización no acreditada', 'class' => 'text-bg-warning'],
        'observed_outside_roster' => ['label' => 'Marcación observada · fuera del padrón habitual', 'class' => 'text-bg-info'],
        default => ['label' => 'Marcación observada', 'class' => 'text-bg-light border'],
    };
    $viewRows[] = [
        'worker_id' => $workerId,
        'name' => $name,
        'qa_fixture' => $isPersistentQaFixture,
        'qa_incidents' => is_array($attendanceRow['qa_incidents'] ?? null) ? $attendanceRow['qa_incidents'] : [],
        'relationship' => $relationship,
        'employee_number' => trim((string) ($attendanceRow['employee_number'] ?? '')),
        'schedule' => $scheduleText,
        'slots' => $slots,
        'evidence' => $evidence,
        'evaluated_raw_event_count' => $evaluatedRawEventCount,
        'evaluated_raw_events' => $evaluatedRawEvents,
        'evaluated_raw_events_complete' => $evaluatedRawEventsComplete,
        'status' => $status,
        'table_status' => $tableStatus,
        'additional_segment_count' => $additionalSegmentCount,
        'qa_informational_state' => $qaInformationalAllowed ? $qaInformationalState : null,
        'classification_pending' => $classificationPending,
        'event_count' => (int) ($attendanceRow['observed_count'] ?? count($evidence)),
        'journey_error' => is_string($attendanceRow['journey_error'] ?? null) ? $attendanceRow['journey_error'] : null,
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

<div id="container" class="mb-4" data-attendance-state="<?= $esc($state) ?>" data-attendance-empty-message="<?= $esc($attendanceTableEmptyMessage) ?>" x-data="{ ...actions(), ...checadorAttendanceSearch() }">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
                <span class="d-inline-flex align-items-center gap-2">
                    <i class="ti ti-building-store" aria-hidden="true"></i>
                    <span><?= $esc(($selectedWorkplaceKind ?? 'Estación') . ':') ?> <strong class="text-body"><?= $esc($selectedWorkplaceDisplayName) ?></strong></span>
                </span>
                <?php if ($selectedDate !== ''): ?>
                    <span class="d-inline-flex align-items-center gap-2">
                        <i class="ti ti-calendar" aria-hidden="true"></i>
                        <span>Fecha laboral: <strong class="text-body"><?= $esc($selectedDateLabel) ?></strong></span>
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

    <div class="modal fade" id="checador-filter-modal" tabindex="-1" aria-labelledby="checador-filter-modal-title" aria-hidden="true" data-checador-filter-modal x-ref="checadorWorkerModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" id="checador-filter-modal-title">
                        <i class="ti ti-search" aria-hidden="true"></i> Búsqueda avanzada
                    </h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar búsqueda avanzada"></button>
                </div>
                <div class="modal-body">
                    <div class="d-none" data-checador-search-error role="alert" aria-live="polite" x-show="error" x-bind:class="error ? 'alert alert-danger py-2' : 'd-none'" x-text="error"></div>
                    <div id="checador-filter-controls" data-checador-filter-controls>
                        <label for="date" class="form-label">Fecha laboral</label>
                        <input id="date" name="date" type="date" class="form-control" value="<?= $esc($selectedDate) ?>" x-model="fecha" required>

                        <label for="worker_id" class="form-label mt-3">Colaborador</label>
                        <div id="checador-worker-select-wrapper" class="select2-modal-field is-select2-pending" x-ref="checadorWorkerSelectWrapper">
                            <select id="worker_id" name="worker_id" class="form-select" x-ref="checadorWorkerSelect" x-model="colaborador"<?= !$workerSelectAvailable ? ' disabled' : '' ?>>
                                <option value=""<?= $selectedWorkerId === null ? ' selected' : '' ?>>Todos los colaboradores</option>
                                <?php foreach ($workerSelectOptions as $workerOption): ?>
                                    <?php
                                    if (!is_array($workerOption)) continue;
                                    $optionWorkerId = filter_var($workerOption['worker_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                                    $optionName = trim((string) ($workerOption['full_name'] ?? ''));
                                    if ($optionWorkerId === false || $optionName === '') continue;
                                    $optionNumber = trim((string) ($workerOption['employee_number'] ?? ''));
                                    ?>
                                    <option value="<?= (int) $optionWorkerId ?>"<?= $selectedWorkerId === (int) $optionWorkerId ? ' selected' : '' ?>><?= $esc($optionName) ?><?= $optionNumber !== '' ? ' · N.º ' . $esc($optionNumber) : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary order-3" data-checador-search-action="search" :disabled="cargando">
                        <i class="ti ti-search" aria-hidden="true"></i> <span x-text="cargando ? 'Buscando…' : 'Buscar'">Buscar</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary order-1" data-checador-search-action="clear" :disabled="cargando">
                        <i class="ti ti-filter-off" aria-hidden="true"></i> Limpiar filtros
                    </button>
                    <button type="button" class="btn bg-danger-subtle text-danger order-2" data-bs-dismiss="modal">
                        <i class="ti ti-x" aria-hidden="true"></i> Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div data-checador-attendance-results>
    <?php if ($state !== 'success' && $attendanceRows !== []): ?>
        <div class="alert alert-warning py-2" role="status">
            Marcaciones no disponibles.
        </div>
    <?php endif; ?>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
            <table class="table table-striped table-bordered text-nowrap align-middle" id="checador-daily-attendance-table">
                    <caption class="<?= ($showCoverageIndicator || $showJourneyNotEvaluated) ? 'caption-top text-start small text-body-secondary py-1' : 'visually-hidden' ?>">
                        <span class="visually-hidden">Asistencia observada por colaborador para la fecha laboral seleccionada.</span>
                        <?php if ($showCoverageIndicator): ?>
                            <span class="d-inline-flex align-items-center gap-1" role="status">
                                <i class="ti ti-info-circle text-warning-emphasis" aria-hidden="true"></i>
                                <span>Cobertura no confirmada</span>
                                <button type="button" class="btn btn-sm btn-link p-0 text-warning-emphasis" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Esta consulta no confirma que se hayan recibido todas las marcaciones del período." aria-label="Ayuda sobre la cobertura de marcaciones">
                                    <i class="ti ti-help-circle" aria-hidden="true"></i>
                                </button>
                            </span>
                        <?php endif; ?>
                        <?php if ($showJourneyNotEvaluated): ?>
                            <span class="d-inline-flex align-items-center gap-1 ms-2" role="status">
                                <i class="ti ti-info-circle" aria-hidden="true"></i>
                                <span>Journey no se evalúa al abrir Asistencia; las marcaciones quedan pendientes de clasificación.</span>
                            </span>
                        <?php endif; ?>
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
                        <?php if ($viewRows !== []): ?>
                            <?php foreach ($viewRows as $viewRow): ?>
                                <tr>
                                    <th scope="row" class="text-wrap">
                                        <span class="fw-semibold"><?= $esc($viewRow['name']) ?></span>
                                        <span class="d-block mt-1"><span class="badge <?= $esc($viewRow['relationship']['class']) ?>"><?= $esc($viewRow['relationship']['label']) ?></span></span>
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
                                                    <?php if (($time['date'] ?? $selectedDate) !== $selectedDate): ?><span class="d-block small text-muted"><?= $esc((string) $time['date']) ?></span><?php endif; ?>
                                                </time>
                                            <?php elseif (is_array($slot['event'])): ?>
                                                <span class="text-muted">Hora local no disponible</span>
                                            <?php else: ?>—<?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <span class="badge <?= $esc($viewRow['table_status'][1]) ?>"><?= $esc($viewRow['table_status'][0]) ?></span>
                                        <?php if ($viewRow['additional_segment_count'] > 0): ?>
                                            <span class="d-block small text-muted">+<?= (int) $viewRow['additional_segment_count'] ?> segmentos adicionales</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($viewRow['incidence_count'] !== null): ?>
                                            <span class="badge text-bg-light border"><?= (int) $viewRow['incidence_count'] ?></span>
                                            <span class="visually-hidden"><?= $viewRow['incidence_count'] === 1 ? 'incidencia registrada' : 'incidencias registradas' ?></span>
                                        <?php else: ?><span class="text-muted">No disponible</span><?php endif; ?>
                                    </td>
                                    <td class="text-center align-middle text-nowrap">
                                        <?php if ($state === 'success'): ?>
                                        <div class="d-flex gap-1 justify-content-center">
                                            <div class="dropdown dropstart">
                                                <button type="button" class="text-muted pointer border-0 bg-transparent p-0" data-bs-toggle="dropdown" data-checador-attendance-actions aria-expanded="false" aria-haspopup="true" aria-controls="daily-attendance-actions-<?= (int) $viewRow['worker_id'] ?>" aria-label="Acciones para <?= $esc($viewRow['name']) ?>">
                                                    <i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i>
                                                </button>
                                                <ul class="dropdown-menu" id="daily-attendance-actions-<?= (int) $viewRow['worker_id'] ?>">
                                                    <li><button type="button" class="dropdown-item pointer d-flex align-items-center gap-3" data-bs-toggle="modal" data-bs-target="#<?= $esc($viewRow['modal_id']) ?>"><i class="fs-4 ti ti-eye" aria-hidden="true"></i>Ver detalle</button></li>
                                                    <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="<?= $esc($viewRow['journey_href']) ?>"><i class="fs-4 ti ti-calendar-time" aria-hidden="true"></i>Jornada</a></li>
                                                    <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="<?= $esc($viewRow['incidence_href']) ?>"><i class="fs-4 ti ti-alert-circle" aria-hidden="true"></i>Incidencias</a></li>
                                                    <?php if ($viewRow['can_create_incidence']): ?>
                                                        <li><a class="dropdown-item pointer d-flex align-items-center gap-3" href="<?= $esc($viewRow['incidence_href']) ?>"><i class="fs-4 ti ti-plus" aria-hidden="true"></i>Registrar incidencia</a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
            </table>
        </div>
    </div>

    <?php if ($state === 'success'): ?>
    <?php foreach ($viewRows as $viewRow): ?>
        <div class="modal fade" id="<?= $esc($viewRow['modal_id']) ?>" tabindex="-1" aria-labelledby="<?= $esc($viewRow['modal_id']) ?>-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-primary align-items-start">
                        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 w-100 me-2">
                            <h2 class="modal-title h5 text-white d-flex flex-wrap align-items-center gap-2 mb-0" id="<?= $esc($viewRow['modal_id']) ?>-title">
                                <i class="ti ti-eye" aria-hidden="true"></i>
                                <span>Detalle de asistencia</span>
                                <span class="fw-normal">· <?= $esc($viewRow['name']) ?></span>
                            </h2>
                            <p class="small text-white-50 text-sm-end mb-0"><?= $esc($selectedWorkplaceDisplayName) ?> · <?= $esc($selectedDate) ?></p>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><span class="d-block small text-muted">Horario efectivo</span><strong><?= $esc($viewRow['schedule']) ?></strong></div></div>
                            <div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><span class="d-block small text-muted">Estado</span><span class="badge <?= $esc($viewRow['status'][1]) ?>"><?= $esc($viewRow['status'][0]) ?></span></div></div>
                        </div>

                        <h3 class="h6 mb-2">Posiciones de marcación</h3>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead><tr><th scope="col">Posición</th><th scope="col">Fecha y hora</th><th scope="col">Estado</th></tr></thead>
                                <tbody>
                                    <?php foreach ($viewRow['slots'] as $slot): ?>
                                        <?php $slotValue = $slot['value']; $local = $slot['time_local']; $event = $slot['event']; ?>
                                        <tr>
                                            <th scope="row"><?= $esc($slot['label']) ?></th>
                                            <td><?php if ($local !== null): ?><?= $esc((string) ($local['date'] ?? '')) ?> <?= $esc((string) ($local['time'] ?? '')) ?><?php elseif ($event !== null): ?>Hora no disponible<?php else: ?>—<?php endif; ?></td>
                                            <td><?php if ($viewRow['classification_pending']): ?>Pendiente de clasificación<?php elseif (($slotValue['ambiguous'] ?? false) === true): ?>Requiere revisión<?php elseif ($event !== null): ?>Marcación observada<?php else: ?>—<?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <h3 class="h6 mb-2">Ver todas las marcaciones (<?= (int) $viewRow['event_count'] ?>)</h3>

                        <?php if ($viewRow['evidence'] === []): ?>
                            <p class="text-muted mb-3">No hay marcaciones atribuibles que mostrar en el detalle de esta consulta.</p>
                        <?php else: ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-striped table-bordered align-middle mb-0">
                                    <caption class="visually-hidden">Marcaciones observadas del colaborador</caption>
                                    <thead><tr><th scope="col">Marcación</th><th scope="col">Fecha y hora</th><th scope="col">Estado</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($viewRow['evidence'] as $item): ?>
                                            <?php $event = $item['event']; $local = is_array($item['local']) ? $item['local'] : null; ?>
                                            <tr>
                                                <th scope="row"><?= $esc($item['label']) ?></th>
                                                <td><?= $local !== null ? $esc((string) ($local['date'] ?? '') . ' ' . (string) ($local['time'] ?? '')) : 'Hora no disponible' ?></td>
                                                <td><?= $viewRow['classification_pending'] ? 'Pendiente de clasificación' : 'Marcación observada' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <h3 class="h6 mb-2">Incidencias relacionadas</h3>
                        <?php if ($viewRow['qa_fixture']): ?>
                            <?php if ($viewRow['qa_incidents'] === []): ?><p class="text-muted mb-0">No hay incidencias relacionadas para esta consulta.</p><?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered align-middle mb-0">
                                        <thead><tr><th scope="col">Incidencia</th><th scope="col">Detalle</th><th scope="col">Estado</th></tr></thead>
                                        <tbody><?php foreach ($viewRow['qa_incidents'] as $qaIncident): ?><tr><th scope="row"><?= $esc($qaIncident['qa_title'] ?? 'Incidencia') ?></th><td><?= $esc($qaIncident['qa_detail'] ?? '') ?></td><td><?= $esc($qaIncident['qa_status_label'] ?? 'Requiere revisión') ?></td></tr><?php endforeach; ?></tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                        <?php if ($viewRow['incidence_error']): ?>
                            <p class="alert alert-warning py-2 mb-0" role="status">La consulta de incidencias no está disponible; no se confirma un resultado vacío.</p>
                        <?php elseif ($viewRow['incidences'] === null): ?>
                            <p class="text-muted mb-0">No hay una lectura de incidencias disponible para este colaborador.</p>
                        <?php elseif ($viewRow['incidences'] === []): ?>
                            <p class="text-muted mb-0">La lectura disponible no devolvió incidencias para esta consulta.</p>
                        <?php else: ?>
                            <div class="table-responsive">
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
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer justify-content-end gap-2">
                        <a class="btn btn-outline-secondary" href="<?= $esc($viewRow['incidence_href']) ?>">Ver incidencias</a>
                        <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x" aria-hidden="true"></i> Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

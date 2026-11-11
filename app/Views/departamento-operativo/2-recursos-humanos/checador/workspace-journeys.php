<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$persistedJourneysList = is_array($persistedJourneys ?? null) ? $persistedJourneys : null;
$journeyRows = is_array($journeys ?? null) ? $journeys : [];
$journeyErrors = is_array($journeyErrors ?? null) ? $journeyErrors : [];
$journeyObservations = is_array($journeyObservations ?? null) ? $journeyObservations : [];
$journeyObservationCoverage = is_array($journeyObservationCoverage ?? null) ? $journeyObservationCoverage : null;
$hasLiveObservationSet = $journeyObservationCoverage !== null && $persistedJourneysList === null;
$workerSelectOptions = is_array($workerSelectOptions ?? null) ? $workerSelectOptions : [];
$selectedDate = is_string($selectedDate ?? null) ? $selectedDate : '';
$journeyDateFrom = is_string($journeyDateFrom ?? null) ? $journeyDateFrom : (is_string($dateFrom ?? null) ? $dateFrom : $selectedDate);
$journeyDateTo = is_string($journeyDateTo ?? null) ? $journeyDateTo : (is_string($dateTo ?? null) ? $dateTo : $selectedDate);
$selectedWorkerId = is_int($selectedWorkerId ?? null) ? $selectedWorkerId : (is_numeric($selectedWorkerId ?? null) ? (int) $selectedWorkerId : null);
$journeyStatus = is_string($journeyStatus ?? null) ? $journeyStatus : (is_string($statusFilter ?? null) ? $statusFilter : '');
$contractualStatuses = is_array($contractualStatuses ?? null) ? $contractualStatuses : \App\Services\ChecadorPersistedJourneyReadService::getContractualStatuses();
$journeyEvaluationState = is_string($journeyEvaluationState ?? null) ? $journeyEvaluationState : 'not_run';
$statusLabel = static fn(string $status): string => match ($status) {
    'EVALUATED' => 'Resultado disponible',
    'DATA_INCOMPLETE' => 'Información insuficiente',
    'REVIEW_REQUIRED' => 'Requiere revisión',
    'AMBIGUOUS_PAIRING' => 'Requiere revisión por ambigüedad',
    'UNSCHEDULED_DAY' => 'Sin horario asignado',
    'DAY_OFF' => 'Descanso programado',
    default => 'Resultado no disponible',
};

// QA fixture / legacy evaluated journey mapping for backwards compatibility
$journeyDetails = [];
$modalSequence = 0;
if ($persistedJourneysList === null && !$hasLiveObservationSet && $journeyRows !== []) {
    foreach ($journeyRows as $workerId => $journey) {
        if (!is_array($journey)) continue;
        $workerId = (int) $workerId;
        $context = is_array($journey['context'] ?? null) ? $journey['context'] : [];
        $schedule = is_array($journey['schedule'] ?? null) ? $journey['schedule'] : [];
        $calculation = is_array($journey['calculation'] ?? null) ? $journey['calculation'] : [];
        $source = is_array($journey['source_evidence'] ?? null) ? $journey['source_evidence'] : [];
        $provenance = is_array($journey['provenance'] ?? null) ? $journey['provenance'] : [];
        $isQaFixture = ($journey['qa_fixture'] ?? false) === true;
        $hasOfficialEvaluation = !$isQaFixture && ($journey['evaluated'] ?? null) === true;
        $events = $isQaFixture
            ? (is_array($journey['qa_events'] ?? null) ? $journey['qa_events'] : [])
            : (is_array($journey['evaluated_raw_events'] ?? null) ? $journey['evaluated_raw_events'] : []);
        $eventCount = $journey['evaluated_raw_event_count'] ?? null;
        $eventsComplete = is_int($eventCount)
            && $eventCount === count($events)
            && ($journey['evaluated'] ?? false) === true;
        $seconds = (($calculation['estado_calculo'] ?? '') === 'CALCULATED'
            && is_int($calculation['segundos_trabajados_totales'] ?? null)
            && $calculation['segundos_trabajados_totales'] >= 0)
            ? $calculation['segundos_trabajados_totales']
            : null;
        $duration = $seconds === null
            ? 'No disponible'
            : sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
        $scheduleLabel = $isQaFixture ? 'No confirmado' : match ((string) ($schedule['estado_jornada'] ?? '')) {
            'SCHEDULED' => 'Horario informado',
            'DAY_OFF' => 'Descanso informado',
            'UNSCHEDULED' => 'Sin asignación informada',
            'NEEDS_REVIEW' => 'Requiere revisión',
            default => 'No disponible',
        };
        $resultLabel = $hasOfficialEvaluation
            ? $statusLabel((string) ($journey['journey_status'] ?? ''))
            : ($isQaFixture ? 'Sin evaluación · escenario QA local' : 'Evaluación no disponible');
        $observedEventsLabel = $isQaFixture
            ? (string) count($events)
            : (is_int($eventCount) ? (string) $eventCount : 'No disponible')
                . ($eventsComplete ? '' : ' · conjunto no confirmado');

        $modalSequence++;
        $journeyDetails[] = [
            'worker_id' => $workerId,
            'worker_name' => (string) ($journey['worker_name'] ?? ('Personal ' . $workerId)),
            'date' => (string) ($context['fecha_local'] ?? $selectedDate ?? 'No disponible'),
            'timezone' => (string) ($context['timezone'] ?? 'No disponible'),
            'schedule_label' => $scheduleLabel,
            'result_label' => $resultLabel,
            'duration' => $duration,
            'seconds' => $seconds,
            'audit_ref' => $source['snapshot_audit_ref'] ?? 'No disponible',
            'policy_rule_version' => array_key_exists('policy_rule_version', $provenance)
                ? ($provenance['policy_rule_version'] === null
                    ? 'No aplica'
                    : (is_string($provenance['policy_rule_version']) ? $provenance['policy_rule_version'] : 'No disponible'))
                : 'No disponible',
            'observed_events_label' => $observedEventsLabel,
            'calculation_revision' => $hasOfficialEvaluation && isset($calculation['revision_numero'])
                ? (int) $calculation['revision_numero']
                : 'No disponible',
            'events' => $events,
            'modal_id' => 'journey-detail-' . $modalSequence,
            'is_qa_fixture' => $isQaFixture,
        ];
    }
}

$formatObservedScheduleSegment = static function (array $segment) use ($esc): string {
    $type = strtoupper((string) ($segment['tipo'] ?? $segment['tipo_segmento'] ?? ''));
    $label = $type === 'BREAK' ? 'Descanso: ' : ($type === 'WORK' ? 'Trabajo: ' : 'Segmento: ');
    $start = substr((string) ($segment['hora_inicio_local'] ?? ''), 0, 5);
    $end = substr((string) ($segment['hora_fin_local'] ?? ''), 0, 5);
    $startOffset = (int) ($segment['dia_offset_inicio'] ?? 0);
    $endOffset = (int) ($segment['dia_offset_fin'] ?? 0);
    $offset = $endOffset > $startOffset ? ' (+' . ($endOffset - $startOffset) . ' día)' : '';
    return $label . $start . '–' . $end . $offset;
};

// Fallback observation mapping for backward-compatible readonly tests
$observationDetails = [];
$observationSequence = 0;
if ($hasLiveObservationSet) {
    foreach ($journeyObservations as $observation) {
        if (!is_array($observation)) continue;
        $observationSequence++;
        $schedule = is_array($observation['schedule'] ?? null) ? $observation['schedule'] : null;
        $scheduleOrigin = is_array($schedule['origen'] ?? null) ? $schedule['origen'] : [];
        $scheduleState = strtoupper((string) ($schedule['estado_jornada'] ?? ''));
        $scheduleLabel = match ($scheduleState) {
            'SCHEDULED' => 'Horario efectivo informado por API V2',
            'DAY_OFF' => 'Descanso efectivo informado por API V2',
            'UNSCHEDULED' => 'Sin asignación confirmada',
            'UNKNOWN' => 'Cobertura de horario desconocida',
            default => match ((string) ($observation['schedule_error'] ?? '')) {
                'schedule_permission_denied' => 'Horario no consultado: permiso no disponible',
                'schedule_not_queried_unverified_source' => 'Horario no consultado: evidencia no verificable',
                'read_limit_exceeded' => 'Horario no consultado: límite de lectura',
                'identity_unresolved' => 'Horario no consultado: identidad sin resolver',
                default => 'Horario no disponible',
            },
        };
        $scheduleData = is_array($schedule['horario_local'] ?? null) ? $schedule['horario_local'] : [];
        $segments = is_array($scheduleData['segmentos'] ?? null) ? $scheduleData['segmentos'] : [];
        $observationDetails[] = [
            'worker_id' => is_int($observation['worker_id'] ?? null) ? $observation['worker_id'] : null,
            'worker_name' => (string) ($observation['worker_name'] ?? 'Identidad de trabajador no resuelta'),
            'date' => (string) ($observation['date'] ?? $selectedDate),
            'timezone' => (string) ($observation['timezone'] ?? 'No disponible'),
            'identity_status' => (string) ($observation['identity_status'] ?? 'UNKNOWN'),
            'events' => is_array($observation['events'] ?? null) ? $observation['events'] : [],
            'schedule_label' => $scheduleLabel,
            'schedule_segments' => $segments,
            'schedule_timezone' => (string) ($scheduleData['timezone'] ?? ''),
            'schedule_source' => 'API V2 · GET de horario efectivo',
            'schedule_origin' => $scheduleOrigin,
            'physical_station_id' => is_array($observation['events'] ?? null) && isset($observation['events'][0]['id_estacion_resuelta'])
                ? (int) $observation['events'][0]['id_estacion_resuelta']
                : null,
            'schedule_error' => (string) ($observation['schedule_error'] ?? ''),
            'modal_id' => 'journey-observation-' . $observationSequence,
        ];
    }
}
?>
<div id="container" class="mt-4 mb-5">
    <section class="card" aria-labelledby="journey-workspace-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2 gap-md-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-briefcase fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="journey-workspace-heading">Jornadas laborales</h2>
                <span class="small text-white-50">Consulta de jornadas evaluadas y persistidas</span>
            </div>
        </div>

        <div class="card-body">
            <p class="text-muted">Consulta el resultado de las jornadas evaluadas y persistidas para la localidad, colaborador y periodo seleccionados.</p>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#checador-journeys-search-modal" id="open-journeys-search-modal-btn">
                        <i class="ti ti-filter fs-5" aria-hidden="true"></i>
                        <span>Buscar / Filtrar</span>
                    </button>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 small" aria-label="Criterios de consulta activos">
                    <?php if ($journeyDateFrom !== '' || $journeyDateTo !== ''): ?>
                        <span class="badge rounded-pill bg-light text-dark border py-2 px-3">
                            <i class="ti ti-calendar me-1 text-primary" aria-hidden="true"></i>
                            <?= $esc($journeyDateFrom) ?><?php if ($journeyDateTo !== '' && $journeyDateTo !== $journeyDateFrom): ?> al <?= $esc($journeyDateTo) ?><?php endif; ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($selectedWorkerId !== null): ?>
                        <?php
                        $selectedWorkerLabel = 'Colaborador ID ' . (int) $selectedWorkerId;
                        foreach ($workerSelectOptions as $opt) {
                            if (is_array($opt) && (int) ($opt['worker_id'] ?? 0) === (int) $selectedWorkerId) {
                                $selectedWorkerLabel = (string) ($opt['full_name'] ?? $selectedWorkerLabel);
                                break;
                            }
                        }
                        ?>
                        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle py-2 px-3">
                            <i class="ti ti-user me-1" aria-hidden="true"></i><?= $esc($selectedWorkerLabel) ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($journeyStatus !== ''): ?>
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle py-2 px-3">
                            <i class="ti ti-check me-1" aria-hidden="true"></i><?= $esc($contractualStatuses[$journeyStatus] ?? $journeyStatus) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Institucional de Búsqueda y Filtros de Jornadas -->
            <div class="modal fade" id="checador-journeys-search-modal" tabindex="-1" aria-labelledby="journeys-search-modal-title" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <form method="get" action="/departamento-operativo/recursos-humanos/checador/jornadas" class="modal-content" id="journeys-filter-form" aria-label="Filtros de consulta de jornadas">
                        <div class="modal-header modal-colored-header bg-primary text-white">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-briefcase fs-5"></i></span>
                                <div>
                                    <h3 class="modal-title h5 text-white mb-0" id="journeys-search-modal-title">Buscar jornadas laborales</h3>
                                    <span class="small text-white-50">Selecciona los criterios para consultar las jornadas registradas</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="journey-date-from">Fecha inicial</label>
                                    <input class="form-control" type="date" id="journey-date-from" name="date_from" value="<?= $esc($journeyDateFrom) ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="journey-date-to">Fecha final</label>
                                    <input class="form-control" type="date" id="journey-date-to" name="date_to" value="<?= $esc($journeyDateTo) ?>" required>
                                </div>

                                <div class="col-12">
                                    <div class="select2-modal-field">
                                        <label class="form-label fw-semibold" for="journey-worker-filter">Colaborador</label>
                                        <select class="form-select" id="journey-worker-filter" name="worker_id">
                                            <option value="">Todos los colaboradores</option>
                                            <?php foreach ($workerSelectOptions as $option): if (!is_array($option)) continue; $optionId = (int) ($option['worker_id'] ?? 0); if ($optionId < 1) continue; ?>
                                                <option value="<?= $optionId ?>" <?= $selectedWorkerId === $optionId ? 'selected' : '' ?>><?= $esc($option['full_name'] ?? ('Personal ' . $optionId)) ?><?php if (!empty($option['employee_number'])): ?> · N.º <?= $esc($option['employee_number']) ?><?php else: ?> · ID <?= $optionId ?><?php endif; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="select2-modal-field">
                                        <label class="form-label fw-semibold" for="journey-status-filter">Estado</label>
                                        <select class="form-select" id="journey-status-filter" name="status">
                                            <option value="">Todos los estados</option>
                                            <?php foreach ($contractualStatuses as $statusCode => $statusName): ?>
                                                <option value="<?= $esc($statusCode) ?>" <?= $journeyStatus === $statusCode ? 'selected' : '' ?>><?= $esc($statusName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-outline-secondary" id="journeys-reset-filters-btn">
                                <i class="ti ti-rotate me-1" aria-hidden="true"></i>Limpiar filtros
                            </button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary journey-filter-btn" id="journeys-submit-filters-btn">
                                    <i class="ti ti-search me-1" aria-hidden="true"></i>Consultar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (($state ?? '') !== 'success' && !$hasLiveObservationSet && ($persistedJourneysList === null || $persistedJourneysList === []) && $journeyDetails === []): ?>
                <?php if (!empty($stateMessage)): ?>
                    <div class="alert alert-warning" role="status"><?= $esc($stateMessage) ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($persistedJourneysList !== null): ?>
                <?php if ($persistedJourneysList === []): ?>
                    <div class="alert alert-secondary mb-0" role="status">
                        <i class="ti ti-info-circle me-1" aria-hidden="true"></i>No se encontraron jornadas laborales para los filtros seleccionados.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="checador-journeys-table" class="table table-striped table-bordered align-middle mb-0 w-100">
                            <caption class="visually-hidden">Jornadas laborales evaluadas y persistidas</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Fecha de jornada</th>
                                    <th scope="col">Colaborador</th>
                                    <th scope="col">Localidad</th>
                                    <th scope="col">Turno</th>
                                    <th scope="col">Entrada</th>
                                    <th scope="col">Salida</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($persistedJourneysList as $j): ?>
                                    <tr>
                                        <td><?= $esc($j['date']) ?></td>
                                        <td>
                                            <strong><?= $esc($j['worker_name']) ?></strong>
                                            <?php if (!empty($j['employee_number'])): ?>
                                                <div class="small text-muted">N.º <?= $esc($j['employee_number']) ?></div>
                                            <?php else: ?>
                                                <div class="small text-muted">ID <?= (int) $j['worker_id'] ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $esc($j['workplace_name']) ?></td>
                                        <td><?= $esc($j['shift_name']) ?></td>
                                        <td><?= $esc($j['entry_display']) ?></td>
                                        <td><?= $esc($j['exit_display']) ?></td>
                                        <td><span class="<?= $esc($j['status_badge']['class']) ?>"><?= $esc($j['status_badge']['label']) ?></span></td>
                                        <td class="table-actions-cell text-center">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#journey-detail-<?= (int) $j['id'] ?>" aria-controls="journey-detail-<?= (int) $j['id'] ?>" aria-haspopup="dialog">
                                                <i class="ti ti-eye me-1" aria-hidden="true"></i>Detalle
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php elseif ($journeyDetails !== []): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <caption class="visually-hidden">Resultados de Jornada por trabajador</caption>
                        <thead><tr><th scope="col">Trabajador</th><th scope="col">Fecha laboral</th><th scope="col">Horario informado</th><th scope="col">Resultado</th><th scope="col">Tiempo informado</th><th scope="col" class="text-center">Acciones</th></tr></thead>
                        <tbody>
                        <?php foreach ($journeyDetails as $detail): ?>
                            <tr>
                                <th scope="row"><?= $esc($detail['worker_name']) ?></th>
                                <td><?= $esc($detail['date']) ?></td>
                                <td><?= $esc($detail['schedule_label']) ?></td>
                                <td><?= $esc($detail['result_label']) ?></td>
                                <td><?= $esc($detail['duration']) ?><?php if ($detail['seconds'] !== null): ?><span class="d-block small text-muted">Informado por la evaluación</span><?php endif; ?></td>
                                <td class="table-actions-cell">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $esc($detail['modal_id']) ?>" aria-controls="<?= $esc($detail['modal_id']) ?>" aria-haspopup="dialog">
                                        <i class="ti ti-history me-1" aria-hidden="true"></i>Ver trazabilidad
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif ($hasLiveObservationSet): ?>
                <div class="alert alert-info py-2" role="status">
                    <strong>Jornada no evaluada en esta consulta.</strong> Se muestran marcaciones observadas y, cuando la API lo confirma, el horario efectivo. No se infiere asistencia correcta, falta, retardo ni tiempo trabajado. La API vigente no ofrece una lectura GET de evaluaciones persistidas; por eso esta pantalla no confirma si existe una evaluación anterior.
                </div>
                <?php $coverage = $journeyObservationCoverage; ?>
                <div class="small text-muted mb-3" aria-live="polite">
                    API: <?= (int) ($coverage['api_records_read'] ?? 0) ?> marcaciones observadas · páginas <?= (int) ($coverage['pages_read'] ?? 0) ?>/<?= (int) ($coverage['total_pages'] ?? 0) ?> · cobertura de fuente: desconocida · lectura no persistida.
                    Identidad resuelta: <?= (int) ($coverage['worker_resolved_events'] ?? 0) ?> eventos; identidad sin resolver: <?= (int) ($coverage['unresolved_worker_events'] ?? 0) ?>.
                </div>
                <?php if ($observationDetails === []): ?>
                    <div class="alert alert-secondary" role="status">
                        <?php if ((int) ($coverage['api_records_read'] ?? 0) === 0): ?>La API devolvió cero marcaciones para esta estación y fecha. Esto no confirma que la fuente haya recibido todas las marcaciones del período.<?php else: ?>La consulta autorizada no devolvió marcaciones para el trabajador seleccionado. No se infiere falta ni ausencia.<?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <caption class="visually-hidden">Marcaciones observadas y horario efectivo consultado por identidad de trabajador</caption>
                            <thead><tr><th scope="col">Trabajador</th><th scope="col">Fecha laboral</th><th scope="col">Marcaciones observadas</th><th scope="col">Horario efectivo</th><th scope="col">Jornada</th><th scope="col" class="text-center">Acciones</th></tr></thead>
                            <tbody>
                            <?php foreach ($observationDetails as $detail): ?>
                                <tr>
                                    <th scope="row"><?= $esc($detail['worker_name']) ?><?php if ($detail['worker_id'] !== null): ?><div class="small text-muted">ID <?= (int) $detail['worker_id'] ?></div><?php endif; ?></th>
                                    <td><?= $esc($detail['date']) ?></td>
                                    <td><?= count($detail['events']) ?></td>
                                    <td><?= $esc($detail['schedule_label']) ?><?php if ($detail['schedule_segments'] !== []): ?><div class="small text-muted"><?php foreach ($detail['schedule_segments'] as $segment): if (is_array($segment)): ?><?= $esc($formatObservedScheduleSegment($segment)) ?><br><?php endif; endforeach; ?></div><?php endif; ?></td>
                                    <td><span class="badge text-bg-light border">No evaluada</span></td>
                                    <td class="table-actions-cell text-center"><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $esc($detail['modal_id']) ?>" aria-controls="<?= $esc($detail['modal_id']) ?>" aria-haspopup="dialog"><i class="ti ti-history me-1" aria-hidden="true"></i>Ver trazabilidad</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($persistedJourneysList !== null): ?>
        <?php foreach ($persistedJourneysList as $j): ?>
            <div class="modal fade" id="journey-detail-<?= (int) $j['id'] ?>" tabindex="-1" aria-labelledby="journey-detail-<?= (int) $j['id'] ?>-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title h5" id="journey-detail-<?= (int) $j['id'] ?>-title">
                                <i class="ti ti-briefcase me-1 text-primary" aria-hidden="true"></i>Detalle de Jornada · <?= $esc($j['worker_name']) ?>
                            </h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar detalle de Jornada"></button>
                        </div>
                        <div class="modal-body text-start">
                            <dl class="row small mb-3">
                                <dt class="col-sm-4 text-muted">Fecha laboral</dt>
                                <dd class="col-sm-8 fw-semibold"><?= $esc($j['date']) ?></dd>

                                <dt class="col-sm-4 text-muted">Localidad</dt>
                                <dd class="col-sm-8"><?= $esc($j['workplace_name']) ?></dd>

                                <dt class="col-sm-4 text-muted">Colaborador</dt>
                                <dd class="col-sm-8"><?= $esc($j['worker_name']) ?><?php if (!empty($j['employee_number'])): ?> · N.º <?= $esc($j['employee_number']) ?><?php else: ?> · ID <?= (int) $j['worker_id'] ?><?php endif; ?></dd>

                                <dt class="col-sm-4 text-muted">Turno programado</dt>
                                <dd class="col-sm-8"><?= $esc($j['shift_name']) ?></dd>

                                <dt class="col-sm-4 text-muted">Entrada observada</dt>
                                <dd class="col-sm-8"><?= $esc($j['entry_display']) ?></dd>

                                <dt class="col-sm-4 text-muted">Salida observada</dt>
                                <dd class="col-sm-8"><?= $esc($j['exit_display']) ?></dd>

                                <dt class="col-sm-4 text-muted">Tiempo programado</dt>
                                <dd class="col-sm-8"><?= $esc($j['scheduled_duration_display']) ?></dd>

                                <dt class="col-sm-4 text-muted">Tiempo trabajado</dt>
                                <dd class="col-sm-8"><?= $esc($j['duration_display']) ?></dd>

                                <dt class="col-sm-4 text-muted">Resultado de evaluación</dt>
                                <dd class="col-sm-8"><span class="<?= $esc($j['status_badge']['class']) ?>"><?= $esc($j['status_badge']['label']) ?></span></dd>

                                <dt class="col-sm-4 text-muted">Revisión de cálculo</dt>
                                <dd class="col-sm-8">Rev. <?= (int) $j['revision_number'] ?> · Algoritmo <?= $esc($j['algorithm_version']) ?></dd>

                                <dt class="col-sm-4 text-muted">Huella de ejecución</dt>
                                <dd class="col-sm-8"><code class="text-break small"><?= $esc($j['execution_fingerprint']) ?></code></dd>

                                <dt class="col-sm-4 text-muted">Zona horaria</dt>
                                <dd class="col-sm-8"><?= $esc($j['timezone']) ?></dd>
                            </dl>

                            <?php if (!empty($j['segments'])): ?>
                                <h6 class="fw-semibold mt-3 mb-2 border-bottom pb-1"><i class="ti ti-layout-grid me-1"></i>Segmentos calculados</h6>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>N.º</th>
                                                <th>Tipo</th>
                                                <th>Estado</th>
                                                <th>Programado</th>
                                                <th>Trabajado</th>
                                                <th>Entrada</th>
                                                <th>Salida</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($j['segments'] as $seg): ?>
                                                <tr>
                                                    <td><?= (int) ($seg['ordinal_segmento'] ?? 1) ?></td>
                                                    <td><?= $esc($seg['tipo_segmento'] ?? 'WORK') ?></td>
                                                    <td><?= $esc($seg['estado_segmento'] ?? '--') ?></td>
                                                    <td><?= \App\Services\ChecadorPersistedJourneyReadService::formatDuration(isset($seg['segundos_programados']) && is_numeric($seg['segundos_programados']) ? (int) $seg['segundos_programados'] : null) ?></td>
                                                    <td><?= \App\Services\ChecadorPersistedJourneyReadService::formatDuration(isset($seg['segundos_trabajados']) && is_numeric($seg['segundos_trabajados']) ? (int) $seg['segundos_trabajados'] : null) ?></td>
                                                    <td><?= \App\Services\ChecadorPersistedJourneyReadService::format12hTime($seg['seg_entry_utc'] ?? null, $j['timezone'], $seg['seg_entry_raw'] ?? null) ?></td>
                                                    <td><?= \App\Services\ChecadorPersistedJourneyReadService::format12hTime($seg['seg_exit_utc'] ?? null, $j['timezone'], $seg['seg_exit_raw'] ?? null) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($j['raw_punches'])): ?>
                                <h6 class="fw-semibold mt-3 mb-2 border-bottom pb-1"><i class="ti ti-fingerprint me-1"></i>Marcaciones asociadas (trazabilidad)</h6>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Rol</th>
                                                <th>UUID</th>
                                                <th>Captura (12h)</th>
                                                <th>Dispositivo</th>
                                                <th>Método</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($j['raw_punches'] as $punch): ?>
                                                <tr>
                                                    <td><?= $esc(match(strtoupper((string) ($punch['rol_marcacion'] ?? ''))) {
                                                        'ENTRY' => 'Entrada',
                                                        'EXIT' => 'Salida',
                                                        default => (string) ($punch['rol_marcacion'] ?? '--')
                                                    }) ?></td>
                                                    <td class="text-break"><code class="small"><?= $esc($punch['event_uuid'] ?? '--') ?></code></td>
                                                    <td><?= \App\Services\ChecadorPersistedJourneyReadService::format12hTime($punch['ocurrido_at_utc'] ?? null, $j['timezone'], $punch['source_timestamp_raw'] ?? null) ?></td>
                                                    <td><?= isset($punch['id_dispositivo']) ? (int) $punch['id_dispositivo'] : '--' ?></td>
                                                    <td><?= $esc($punch['metodo_captura'] ?? '--') ?></td>
                                                    <td><?= $esc($punch['processing_status'] ?? '--') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($j['incidences'])): ?>
                                <h6 class="fw-semibold mt-3 mb-2 border-bottom pb-1"><i class="ti ti-alert-triangle me-1"></i>Incidencias aplicadas</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tipo</th>
                                                <th>UUID</th>
                                                <th>Estado</th>
                                                <th>Aplicación</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($j['incidences'] as $inc): ?>
                                                <tr>
                                                    <td><?= $esc($inc['tipo_incidencia'] ?? '--') ?></td>
                                                    <td class="text-break"><code class="small"><?= $esc($inc['uuid_incidencia'] ?? '--') ?></code></td>
                                                    <td><?= $esc($inc['estado_actual'] ?? '--') ?></td>
                                                    <td><?= $esc($inc['estado_aplicacion'] ?? '--') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php elseif ($journeyDetails !== []): ?>
        <?php foreach ($journeyDetails as $detail): ?>
            <div class="modal fade" id="<?= $esc($detail['modal_id']) ?>" tabindex="-1" aria-labelledby="<?= $esc($detail['modal_id']) ?>-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title h5" id="<?= $esc($detail['modal_id']) ?>-title">Trazabilidad de Jornada · personal <?= (int) $detail['worker_id'] ?></h3>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar detalle de Jornada"></button>
                        </div>
                        <div class="modal-body text-start">
                            <dl class="row small mb-2">
                                <dt class="col-sm-4">Zona laboral</dt><dd class="col-sm-8 text-break"><?= $esc($detail['timezone']) ?></dd>
                                <?php if ($detail['is_qa_fixture']): ?>
                                    <dt class="col-sm-4">Procedencia</dt><dd class="col-sm-8">Escenario sintético QA local; no representa una evaluación oficial de Journey.</dd>
                                <?php endif; ?>
                                <dt class="col-sm-4">Referencia de evidencia</dt><dd class="col-sm-8 text-break"><?= $esc($detail['audit_ref']) ?></dd>
                                <dt class="col-sm-4">Versión de regla</dt><dd class="col-sm-8 text-break"><?= $esc($detail['policy_rule_version']) ?></dd>
                                <dt class="col-sm-4">Marcaciones observadas</dt><dd class="col-sm-8"><?= $esc($detail['observed_events_label']) ?></dd>
                                <dt class="col-sm-4">Revisión de cálculo</dt><dd class="col-sm-8"><?= $esc($detail['calculation_revision']) ?></dd>
                            </dl>
                            <?php if ($detail['events'] !== []): ?>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <caption class="visually-hidden">Eventos disponibles para consulta de trazabilidad</caption>
                                        <thead><tr><th scope="col">Rol del evento</th><th scope="col">Evento</th><th scope="col">Captura UTC</th><th scope="col">Dispositivo</th></tr></thead>
                                        <tbody>
                                        <?php foreach ($detail['events'] as $event): if (!is_array($event)) continue; ?>
                                            <tr>
                                                <td><?= $esc(match ((string) ($event['pairing_role'] ?? '')) {
                                                    'ENTRY' => 'Entrada',
                                                    'EXIT' => 'Salida',
                                                    'SURPLUS' => 'Marcación adicional',
                                                    'UNMATCHED' => 'Sin emparejar',
                                                    default => 'No disponible',
                                                }) ?></td>
                                                <td class="text-break"><code class="text-break"><?= $esc($event['event_uuid'] ?? 'No disponible') ?></code></td>
                                                <td class="text-break"><?= $esc($event['ocurrido_at_utc'] ?? 'No disponible') ?></td>
                                                <td><?= isset($event['id_dispositivo']) ? (int) $event['id_dispositivo'] : 'No disponible' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php elseif ($hasLiveObservationSet): ?>
        <?php foreach ($observationDetails as $detail): ?>
            <div class="modal fade" id="<?= $esc($detail['modal_id']) ?>" tabindex="-1" aria-labelledby="<?= $esc($detail['modal_id']) ?>-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <div class="modal-header"><h3 class="modal-title h5" id="<?= $esc($detail['modal_id']) ?>-title">Trazabilidad de marcaciones · <?= $esc($detail['worker_name']) ?></h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar detalle de marcaciones"></button></div>
                        <div class="modal-body">
                            <p class="text-muted">Datos crudos consultados en vivo desde API V2. No se generó snapshot ni se ejecutó evaluación de jornada.</p>
                            <dl class="row small">
                                <dt class="col-sm-4">Fecha laboral</dt><dd class="col-sm-8"><?= $esc($detail['date']) ?></dd>
                                <dt class="col-sm-4">Zona horaria</dt><dd class="col-sm-8 text-break"><?= $esc($detail['timezone']) ?></dd>
                                <dt class="col-sm-4">Identidad</dt><dd class="col-sm-8"><?= $esc($detail['identity_status'] === 'RESOLVED_BY_API' ? 'Resuelta por API' : 'No resuelta') ?></dd>
                                <dt class="col-sm-4">Estación física resuelta</dt><dd class="col-sm-8"><?= $detail['physical_station_id'] !== null ? (int) $detail['physical_station_id'] : 'No disponible' ?></dd>
                                <dt class="col-sm-4">Horario</dt><dd class="col-sm-8"><?= $esc($detail['schedule_label']) ?></dd>
                                <?php if ($detail['schedule_timezone'] !== ''): ?><dt class="col-sm-4">Zona del horario</dt><dd class="col-sm-8"><?= $esc($detail['schedule_timezone']) ?></dd><?php endif; ?>
                                <?php if ($detail['schedule_origin'] !== []): ?><dt class="col-sm-4">Origen de horario</dt><dd class="col-sm-8">Asignación <?= (int) ($detail['schedule_origin']['id_asignacion'] ?? 0) ?> · plantilla <?= $esc($detail['schedule_origin']['clave_plantilla'] ?? 'descanso explícito') ?> · versión <?= isset($detail['schedule_origin']['version_plantilla']) ? (int) $detail['schedule_origin']['version_plantilla'] : 'No aplica' ?></dd><?php endif; ?>
                                <?php if ($detail['schedule_segments'] !== []): ?><dt class="col-sm-4">Segmentos efectivos</dt><dd class="col-sm-8"><?php foreach ($detail['schedule_segments'] as $segment): if (is_array($segment)): ?><div><?= $esc($formatObservedScheduleSegment($segment)) ?></div><?php endif; endforeach; ?></dd><?php endif; ?>
                                <dt class="col-sm-4">Evaluación persistida</dt><dd class="col-sm-8">No consultable: API V2 no expone una lectura GET para este dato.</dd>
                            </dl>
                            <div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0"><caption class="visually-hidden">Trazabilidad de eventos crudos</caption><thead><tr><th scope="col">UUID</th><th scope="col">Ocurrido UTC</th><th scope="col">Recibido UTC</th><th scope="col">Estado de integridad</th><th scope="col">Estado de procesamiento</th><th scope="col">Captura</th><th scope="col">Dispositivo</th></tr></thead><tbody>
                                <?php foreach ($detail['events'] as $event): if (!is_array($event)) continue; ?>
                                    <tr><td class="text-break"><code><?= $esc($event['event_uuid'] ?? 'No disponible') ?></code></td><td class="text-break"><?= $esc($event['ocurrido_at_utc'] ?? 'No disponible') ?></td><td class="text-break"><?= $esc($event['recibido_at_utc'] ?? 'No disponible') ?></td><td><?= $esc($event['integrity']['status'] ?? 'No disponible') ?></td><td><?= $esc($event['processing_status'] ?? 'No disponible') ?></td><td><?= $esc($event['metodo_captura'] ?? 'No disponible') ?></td><td><?= isset($event['id_dispositivo']) ? (int) $event['id_dispositivo'] : 'No disponible' ?></td></tr>
                                <?php endforeach; ?>
                            </tbody></table></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

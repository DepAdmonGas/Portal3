<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$persistedIncidencesList = is_array($persistedIncidences ?? null) ? $persistedIncidences : null;
$incidencesByWorker = is_array($administrativeIncidences ?? null) ? $administrativeIncidences : [];
$workerDisplayNames = is_array($workerDisplayNames ?? null) ? $workerDisplayNames : [];
$workerSelectOptions = is_array($workerSelectOptions ?? null) ? $workerSelectOptions : [];
$readErrors = is_array($administrativeErrors ?? null) ? $administrativeErrors : [];
$selectedDate = is_string($selectedDate ?? null) ? $selectedDate : '';
$incidenceDateFrom = is_string($incidenceDateFrom ?? null) ? $incidenceDateFrom : (is_string($dateFrom ?? null) ? $dateFrom : $selectedDate);
$incidenceDateTo = is_string($incidenceDateTo ?? null) ? $incidenceDateTo : (is_string($dateTo ?? null) ? $dateTo : $selectedDate);
$selectedWorkerId = is_int($selectedWorkerId ?? null) ? $selectedWorkerId : (is_numeric($selectedWorkerId ?? null) ? (int) $selectedWorkerId : null);
$incidenceType = is_string($incidenceType ?? null) ? $incidenceType : (is_string($typeFilter ?? null) ? $typeFilter : '');
$incidenceStatus = is_string($incidenceStatus ?? null) ? $incidenceStatus : (is_string($statusFilter ?? null) ? $statusFilter : '');
$contractualIncidenceTypes = is_array($contractualIncidenceTypes ?? null) ? $contractualIncidenceTypes : \App\Services\ChecadorPersistedIncidenceReadService::getContractualTypes();
$contractualIncidenceStatuses = is_array($contractualIncidenceStatuses ?? null) ? $contractualIncidenceStatuses : \App\Services\ChecadorPersistedIncidenceReadService::getContractualStatuses();

$hasConfirmedIncidences = false;
foreach ($incidencesByWorker as $workerIncidences) {
    if (is_array($workerIncidences) && $workerIncidences !== []) {
        $hasConfirmedIncidences = true;
        break;
    }
}
$persistentQaFixtureMode = ($persistentQaFixtureMode ?? false) === true;
$createFeedback = is_array($incidenceCreateFeedback ?? null) ? $incidenceCreateFeedback : null;
$createValues = is_array($createFeedback['form_values'] ?? null) ? $createFeedback['form_values'] : [];
$createWorkerId = filter_var($createFeedback['worker_id'] ?? $selectedWorkerId ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$createWorkerId = $createWorkerId === false ? null : (int) $createWorkerId;
$createModalOpenOnLoad = $createFeedback !== null && ($createFeedback['type'] ?? '') !== 'success';
$localQaIncidenceStoreAvailable = ($localQaIncidenceStoreAvailable ?? false) === true;
$p3gBApiPostOperationsEnabled = ($p3gBApiPostOperationsEnabled ?? false) === true;
$createWorkerOptions = $workerSelectOptions;
if ($localQaIncidenceStoreAvailable) {
    $qaWorkerIds = \App\Services\ChecadorPersistentQaFixtureService::WORKER_IDS;
    $createWorkerOptions = array_values(array_filter(
        $workerSelectOptions,
        static fn(mixed $option): bool => is_array($option)
            && in_array((int) ($option['worker_id'] ?? 0), $qaWorkerIds, true)
    ));
}
$realApiPostOperationsEnabled = ($realApiPostOperationsEnabled ?? false) === true;
$incidenceCreateAllowed = ($incidenceCreateAllowed ?? false) === true
    && ($localQaIncidenceStoreAvailable || $realApiPostOperationsEnabled || $p3gBApiPostOperationsEnabled);
$selectedDateValue = trim((string) ($selectedDate ?? ''));
$selectedWorkplaceValue = trim((string) ($selectedWorkplaceName ?? ''));
$contextWorkerId = filter_var($selectedWorkerId ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$workspaceContextWorkerName = $contextWorkerId !== false
    ? trim((string) ($workerDisplayNames[(int) $contextWorkerId] ?? ''))
    : '';
$workspaceContextWorkerNumber = '';
if ($contextWorkerId !== false) {
    foreach ($workerSelectOptions as $workerOption) {
        if (is_array($workerOption) && (int) ($workerOption['worker_id'] ?? 0) === (int) $contextWorkerId) {
            $workspaceContextWorkerNumber = trim((string) ($workerOption['employee_number'] ?? ''));
            break;
        }
    }
}
$selectedDateDisplay = $selectedDateValue;
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $selectedDateValue, $dateParts) === 1
    && checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])) {
    $selectedDateDisplay = $dateParts[3] . '/' . $dateParts[2] . '/' . $dateParts[1];
}
$workerNumberFor = static function (int $workerId) use ($workerSelectOptions): string {
    foreach ($workerSelectOptions as $workerOption) {
        if (is_array($workerOption) && (int) ($workerOption['worker_id'] ?? 0) === $workerId) {
            return trim((string) ($workerOption['employee_number'] ?? ''));
        }
    }
    return '';
};
$incidenceTimezone = is_array($timezone ?? null) ? trim((string) ($timezone['timezone'] ?? '')) : '';
$revisionTimestampForDisplay = static function (mixed $timestamp) use ($incidenceTimezone): array {
    $raw = trim((string) $timestamp);
    if ($raw === '') {
        return ['Fecha no disponible', $incidenceTimezone !== '' ? $incidenceTimezone : 'UTC'];
    }
    try {
        $utc = new DateTimeZone('UTC');
        $localZone = $incidenceTimezone !== '' ? new DateTimeZone($incidenceTimezone) : $utc;
        $instant = new DateTimeImmutable($raw, $utc);
        return [$instant->setTimezone($localZone)->format('d/m/Y H:i'), $localZone->getName()];
    } catch (Throwable) {
        return [$raw !== '' ? $raw . ' UTC' : 'Fecha no disponible', ''];
    }
};
$categoryLabel = static fn(string $category): string => match ($category) {
    'VACATION' => 'Vacaciones', 'PERMISSION' => 'Permiso', 'DISABILITY', 'INCAPACITY' => 'Incapacidad',
    'JUSTIFICATION' => 'Justificación', 'AUTHORIZED_LATENESS' => 'Retardo autorizado',
    'AUTHORIZED_EARLY_DEPARTURE' => 'Salida anticipada autorizada', 'OFFICIAL_MISSION' => 'Comisión oficial',
    'PUNCH_ATTENDANCE' => 'Incidencia de marcación',
    default => 'Tipo no disponible',
};
$createCategories = $localQaIncidenceStoreAvailable
    ? ['VACATION' => 'Vacaciones', 'PERMISSION' => 'Permiso', 'INCAPACITY' => 'Incapacidad', 'JUSTIFICATION' => 'Justificación']
    : ((!$realApiPostOperationsEnabled && $p3gBApiPostOperationsEnabled)
        ? ['PUNCH_ATTENDANCE' => 'Incidencia relacionada con marcación (QA de desarrollo)']
        : ['VACATION' => 'Vacaciones', 'PERMISSION' => 'Permiso', 'DISABILITY' => 'Incapacidad', 'AUTHORIZED_LATENESS' => 'Retardo autorizado', 'AUTHORIZED_EARLY_DEPARTURE' => 'Salida anticipada autorizada', 'OFFICIAL_MISSION' => 'Comisión oficial']);
if (!$localQaIncidenceStoreAvailable && $realApiPostOperationsEnabled && $p3gBApiPostOperationsEnabled) {
    $createCategories['PUNCH_ATTENDANCE'] = 'Incidencia relacionada con marcación (QA de desarrollo)';
}
$checkKindLabelFor = static fn(string $kind): string => match ($kind) {
    'ENTRY' => 'Entrada', 'LUNCH_EXIT' => 'Salida a comida', 'LUNCH_RETURN' => 'Regreso de comida', 'FINAL_EXIT' => 'Salida final',
    default => 'No disponible',
};
$checkNatureLabelFor = static fn(string $nature): string => match ($nature) {
    'LATENESS' => 'Retardo declarado', 'OMISSION' => 'Omisión declarada', default => 'No disponible',
};
?>

<?php if ($persistedIncidencesList !== null): ?>
<div id="container" class="mt-4 mb-5">
    <section class="card" aria-labelledby="incidence-workspace-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2 gap-md-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-alert-triangle fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="incidence-workspace-heading">Incidencias</h2>
                <span class="small text-white-50">Consulta de incidencias evaluadas y persistidas</span>
            </div>
        </div>

        <div class="card-body">
            <p class="text-muted">Consulta las incidencias evaluadas y persistidas para la localidad, colaborador, tipo y periodo seleccionados.</p>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#checador-incidences-search-modal" id="open-incidences-search-modal-btn">
                        <i class="ti ti-filter fs-5" aria-hidden="true"></i>
                        <span>Buscar / Filtrar</span>
                    </button>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2 small" aria-label="Criterios de consulta activos">
                    <?php if ($incidenceDateFrom !== '' || $incidenceDateTo !== ''): ?>
                        <span class="badge rounded-pill bg-light text-dark border py-2 px-3">
                            <i class="ti ti-calendar me-1 text-primary" aria-hidden="true"></i>
                            <?= $esc($incidenceDateFrom) ?><?php if ($incidenceDateTo !== '' && $incidenceDateTo !== $incidenceDateFrom): ?> al <?= $esc($incidenceDateTo) ?><?php endif; ?>
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

                    <?php if ($incidenceType !== ''): ?>
                        <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle py-2 px-3">
                            <i class="ti ti-tag me-1" aria-hidden="true"></i><?= $esc($contractualIncidenceTypes[$incidenceType] ?? $incidenceType) ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($incidenceStatus !== ''): ?>
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle py-2 px-3">
                            <i class="ti ti-check me-1" aria-hidden="true"></i><?= $esc($contractualIncidenceStatuses[$incidenceStatus] ?? $incidenceStatus) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Institucional de Búsqueda y Filtros de Incidencias -->
            <div class="modal fade" id="checador-incidences-search-modal" tabindex="-1" aria-labelledby="incidences-search-modal-title" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <form method="get" action="/departamento-operativo/recursos-humanos/checador/incidencias" class="modal-content" id="incidences-filter-form" aria-label="Filtros de consulta de incidencias">
                        <div class="modal-header modal-colored-header bg-primary text-white">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-alert-triangle fs-5"></i></span>
                                <div>
                                    <h3 class="modal-title h5 text-white mb-0" id="incidences-search-modal-title">Buscar incidencias</h3>
                                    <span class="small text-white-50">Selecciona los criterios para consultar las incidencias registradas</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="incidence-date-from">Fecha inicial</label>
                                    <input class="form-control" type="date" id="incidence-date-from" name="date_from" value="<?= $esc($incidenceDateFrom) ?>" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold" for="incidence-date-to">Fecha final</label>
                                    <input class="form-control" type="date" id="incidence-date-to" name="date_to" value="<?= $esc($incidenceDateTo) ?>" required>
                                </div>

                                <div class="col-12">
                                    <div class="select2-modal-field">
                                        <label class="form-label fw-semibold" for="incidence-worker-filter">Colaborador</label>
                                        <select class="form-select" id="incidence-worker-filter" name="worker_id">
                                            <option value="">Todos los colaboradores</option>
                                            <?php foreach ($workerSelectOptions as $option): if (!is_array($option)) continue; $optionId = (int) ($option['worker_id'] ?? 0); if ($optionId < 1) continue; ?>
                                                <option value="<?= $optionId ?>" <?= $selectedWorkerId === $optionId ? 'selected' : '' ?>><?= $esc($option['full_name'] ?? ('Personal ' . $optionId)) ?><?php if (!empty($option['employee_number'])): ?> · N.º <?= $esc($option['employee_number']) ?><?php else: ?> · ID <?= $optionId ?><?php endif; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="select2-modal-field">
                                        <label class="form-label fw-semibold" for="incidence-type-filter">Tipo de incidencia</label>
                                        <select class="form-select" id="incidence-type-filter" name="type">
                                            <option value="">Todos los tipos</option>
                                            <?php foreach ($contractualIncidenceTypes as $typeCode => $typeName): ?>
                                                <option value="<?= $esc($typeCode) ?>" <?= $incidenceType === $typeCode ? 'selected' : '' ?>><?= $esc($typeName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <div class="select2-modal-field">
                                        <label class="form-label fw-semibold" for="incidence-status-filter">Estado</label>
                                        <select class="form-select" id="incidence-status-filter" name="status">
                                            <option value="">Todos los estados</option>
                                            <?php foreach ($contractualIncidenceStatuses as $statusCode => $statusName): ?>
                                                <option value="<?= $esc($statusCode) ?>" <?= $incidenceStatus === $statusCode ? 'selected' : '' ?>><?= $esc($statusName) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-outline-secondary" id="incidences-reset-filters-btn">
                                <i class="ti ti-rotate me-1" aria-hidden="true"></i>Limpiar filtros
                            </button>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary incidence-filter-btn" id="incidences-submit-filters-btn">
                                    <i class="ti ti-search me-1" aria-hidden="true"></i>Consultar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (($state ?? '') !== 'success' && $persistedIncidencesList === []): ?>
                <?php if (!empty($stateMessage)): ?>
                    <div class="alert alert-warning" role="status"><?= $esc($stateMessage) ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($persistedIncidencesList === []): ?>
                <div class="alert alert-secondary mb-0" role="status">
                    <i class="ti ti-info-circle me-1" aria-hidden="true"></i>No se encontraron incidencias para los filtros seleccionados.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="checador-incidences-table" class="table table-striped table-bordered align-middle mb-0 w-100">
                        <caption class="visually-hidden">Incidencias evaluadas y persistidas</caption>
                        <thead>
                            <tr>
                                <th scope="col">Fecha / Periodo</th>
                                <th scope="col">Colaborador</th>
                                <th scope="col">Localidad</th>
                                <th scope="col">Turno programado</th>
                                <th scope="col">Tipo de incidencia</th>
                                <th scope="col">Estado</th>
                                <th scope="col" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($persistedIncidencesList as $inc): ?>
                                <tr>
                                    <td><?= $esc($inc['date_display']) ?></td>
                                    <td>
                                        <strong><?= $esc($inc['worker_name']) ?></strong>
                                        <?php if (!empty($inc['employee_number'])): ?>
                                            <div class="small text-muted">N.º <?= $esc($inc['employee_number']) ?></div>
                                        <?php else: ?>
                                            <div class="small text-muted">ID <?= (int) $inc['worker_id'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $esc($inc['workplace_name']) ?></td>
                                    <td><?= $esc($inc['shift_name']) ?></td>
                                    <td><?= $esc($inc['type_label']) ?></td>
                                    <td><span class="<?= $esc($inc['status_badge']['class']) ?>"><?= $esc($inc['status_badge']['label']) ?></span></td>
                                    <td class="table-actions-cell text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $esc($inc['modal_id']) ?>" aria-controls="<?= $esc($inc['modal_id']) ?>" aria-haspopup="dialog">
                                            <i class="ti ti-eye me-1" aria-hidden="true"></i>Detalle
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Read-Only Detail Modals for Persisted Incidences -->
    <?php foreach ($persistedIncidencesList as $inc): ?>
        <div class="modal fade" id="<?= $esc($inc['modal_id']) ?>" tabindex="-1" aria-labelledby="<?= $esc($inc['modal_id']) ?>-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary align-items-start">
                        <div class="d-flex flex-grow-1 align-items-start gap-2 me-2">
                            <i class="ti ti-eye text-white fs-5 mt-1" aria-hidden="true"></i>
                            <div>
                                <h4 class="modal-title h5 text-white text-wrap mb-0" id="<?= $esc($inc['modal_id']) ?>-title">Detalle de incidencia · <?= $esc($inc['type_label']) ?></h4>
                                <p class="small text-white-50 mb-0 mt-1"><?= $esc($inc['worker_name']) ?><?php if (!empty($inc['employee_number'])): ?> · N.º <?= $esc($inc['employee_number']) ?><?php endif; ?> · <?= $esc($inc['workplace_name']) ?></p>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <span class="d-block small text-muted">Colaborador</span>
                                    <strong><?= $esc($inc['worker_name']) ?></strong>
                                    <?php if (!empty($inc['employee_number'])): ?>
                                        <span class="d-block small text-muted mt-1">N.º nómina: <?= $esc($inc['employee_number']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <span class="d-block small text-muted">Tipo de incidencia</span>
                                    <strong><?= $esc($inc['type_label']) ?></strong>
                                    <span class="d-block small text-muted mt-1">Alcance: <?= $esc($inc['scope']) ?></span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <span class="d-block small text-muted">Periodo o fecha aplicada</span>
                                    <strong><?= $esc($inc['date_display']) ?></strong>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <span class="d-block small text-muted">Estado actual</span>
                                    <div><span class="<?= $esc($inc['status_badge']['class']) ?>"><?= $esc($inc['status_badge']['label']) ?></span></div>
                                    <span class="d-block small text-muted mt-1">Revisión actual: #<?= (int) $inc['current_revision'] ?></span>
                                </div>
                            </div>
                            <?php if (!empty($inc['folio_evidencia'])): ?>
                                <div class="col-12 col-md-6">
                                    <div class="border rounded p-3 h-100">
                                        <span class="d-block small text-muted">Folio de evidencia</span>
                                        <strong><?= $esc($inc['folio_evidencia']) ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="col-12 col-md-<?= !empty($inc['folio_evidencia']) ? '6' : '12' ?>">
                                <div class="border rounded p-3 h-100">
                                    <span class="d-block small text-muted">Turno programado</span>
                                    <strong><?= $esc($inc['shift_name']) ?></strong>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="border rounded p-3">
                                    <span class="d-block small text-muted">Motivo o descripción</span>
                                    <p class="mb-0 mt-1"><?= !empty($inc['motivo']) ? $esc($inc['motivo']) : 'Sin motivo documentado' ?></p>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($inc['punches'])): ?>
                            <div class="border rounded p-3 mb-3">
                                <h5 class="h6 mb-2 d-flex align-items-center gap-2">
                                    <i class="ti ti-fingerprint text-primary" aria-hidden="true"></i>Marcaciones del periodo
                                </h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col">Hora (12 hrs)</th>
                                                <th scope="col">Fecha</th>
                                                <th scope="col">Dispositivo</th>
                                                <th scope="col">Método</th>
                                                <th scope="col">UUID del evento</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($inc['punches'] as $p): ?>
                                                <tr>
                                                    <td><strong><?= $esc($p['time_12h']) ?></strong></td>
                                                    <td><?= $esc(\App\Services\ChecadorPersistedIncidenceReadService::formatCivilDate($p['fecha_local'])) ?></td>
                                                    <td>Dispositivo <?= (int) $p['dispositivo_id'] ?></td>
                                                    <td><span class="badge text-bg-light border"><?= $esc($p['metodo_captura']) ?></span></td>
                                                    <td><span class="font-monospace small text-secondary user-select-all text-break"><?= $esc($p['event_uuid']) ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php elseif (($inc['punch_projection_state'] ?? '') === 'UNAVAILABLE'): ?>
                            <div class="alert alert-light border text-body-secondary py-2" role="status">
                                No fue posible consultar las marcaciones contextuales del periodo; no se confirma que estén ausentes.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($inc['evaluations'])): ?>
                            <div class="border rounded p-3 mb-3">
                                <h5 class="h6 mb-2 d-flex align-items-center gap-2">
                                    <i class="ti ti-checklist text-primary" aria-hidden="true"></i>Resultado de evaluación administrativa
                                </h5>
                                <?php foreach ($inc['evaluations'] as $ev): ?>
                                    <dl class="row small mb-2 border-bottom pb-2">
                                        <dt class="col-sm-4">Estado evaluación</dt>
                                        <dd class="col-sm-8"><span class="badge bg-light text-dark border"><?= $esc($ev['estado_evaluacion']) ?></span></dd>
                                        <dt class="col-sm-4">Revisión humana</dt>
                                        <dd class="col-sm-8"><?= $esc($ev['estado_revision_humana']) ?></dd>
                                        <dt class="col-sm-4">Versión de algoritmo</dt>
                                        <dd class="col-sm-8"><?= $esc($ev['version_algoritmo']) ?> · Política: <?= $esc($ev['version_politica']) ?></dd>
                                        <dt class="col-sm-4">Fingerprint</dt>
                                        <dd class="col-sm-8 font-monospace small text-break"><?= $esc($ev['fingerprint']) ?></dd>
                                    </dl>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="border rounded p-3 mb-3">
                            <h5 class="h6 mb-2 d-flex align-items-center gap-2">
                                <i class="ti ti-history text-primary" aria-hidden="true"></i>Referencias de auditoría
                            </h5>
                            <dl class="row small mb-0">
                                <dt class="col-sm-4">UUID de incidencia</dt>
                                <dd class="col-sm-8 font-monospace small user-select-all text-break"><?= $esc($inc['uuid']) ?></dd>
                                <dt class="col-sm-4">Registrado el</dt>
                                <dd class="col-sm-8"><?= $esc($inc['created_at_local']) ?> (Local)</dd>
                                <?php if (!empty($inc['created_by_user_id'])): ?>
                                    <dt class="col-sm-4">Registrado por</dt>
                                    <dd class="col-sm-8">Usuario ID <?= (int) $inc['created_by_user_id'] ?></dd>
                                <?php endif; ?>
                                <?php if (!empty($inc['approved_by_user_id'])): ?>
                                    <dt class="col-sm-4">Aprobado por</dt>
                                    <dd class="col-sm-8">Usuario ID <?= (int) $inc['approved_by_user_id'] ?></dd>
                                <?php endif; ?>
                            </dl>
                        </div>

                        <?php if (!empty($inc['revisions'])): ?>
                            <div class="border rounded p-3">
                                <h5 class="h6 mb-2 d-flex align-items-center gap-2">
                                    <i class="ti ti-list text-primary" aria-hidden="true"></i>Historial de revisiones
                                </h5>
                                <ol class="small mb-0 ps-3">
                                    <?php foreach ($inc['revisions'] as $rev): ?>
                                        <li class="mb-2">
                                            <div class="d-flex flex-wrap align-items-center gap-2">
                                                <strong>Revisión #<?= (int) $rev['revision_numero'] ?></strong>
                                                <span class="<?= $esc($rev['status_badge']['class']) ?>"><?= $esc($rev['status_badge']['label']) ?></span>
                                                <span class="text-muted">· <?= $esc($rev['creado_at_local']) ?></span>
                                            </div>
                                            <?php if (!empty($rev['motivo'])): ?>
                                                <p class="mb-1 mt-1"><?= $esc($rev['motivo']) ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($rev['folio_evidencia'])): ?>
                                                <div class="text-muted">Evidencia: <?= $esc($rev['folio_evidencia']) ?></div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer justify-content-end gap-2">
                        <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                            <i class="ti ti-x me-1" aria-hidden="true"></i>Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php else: ?>
<!-- Legacy / QA Fixture Incidence View (preserves compatibility with synthetic QA suites) -->
<div id="container" class="mt-4 mb-5" x-data="{ ...actions(), ...checadorIncidencePage() }" data-create-worker="<?= $createWorkerId ?? '' ?>" data-open-create="<?= $createModalOpenOnLoad ? '1' : '0' ?>" data-applied-date="<?= $esc($selectedDateValue) ?>" data-applied-worker="<?= $contextWorkerId === false ? '' : (int) $contextWorkerId ?>">
    <section class="card" aria-label="Incidencias">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex flex-wrap align-items-baseline gap-2">
                <?php if (($selectedWorkplaceId ?? null) !== null): ?><span class="small text-muted">Localidad <?= (int) $selectedWorkplaceId ?></span><?php endif; ?>
            </div>
            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checador-incidence-search-modal" aria-controls="checador-incidence-search-modal">
                    <i class="ti ti-search me-1" aria-hidden="true"></i>Buscar
                </button>
                <?php if ($incidenceCreateAllowed): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checador-create-incidence-modal">
                        <i class="ti ti-plus me-1" aria-hidden="true"></i>Nueva incidencia
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="modal fade" id="checador-incidence-search-modal" tabindex="-1" aria-labelledby="checador-incidence-search-title" aria-hidden="true" x-ref="incidenceSearchModal">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <form method="get" action="/departamento-operativo/recursos-humanos/checador/incidencias" class="modal-content" aria-label="Búsqueda avanzada de incidencias" x-ref="incidenceSearchForm" x-on:submit="searchSubmitting = true">
                        <div class="modal-header modal-colored-header bg-primary text-white">
                            <h3 class="modal-title h5 text-white d-flex align-items-center gap-2" id="checador-incidence-search-title">
                                <i class="ti ti-search" aria-hidden="true"></i>Búsqueda avanzada
                            </h3>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar búsqueda avanzada"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label" for="checador-incidence-filter-date">Fecha laboral</label>
                                    <input class="form-control" type="date" id="checador-incidence-filter-date" name="date" value="<?= $esc($selectedDateValue) ?>" x-ref="incidenceSearchDate" x-model="searchDate" required>
                                </div>
                                <div class="col-12 col-md-8">
                                    <label class="form-label" for="checador-incidence-filter-worker">Colaborador</label>
                                    <div class="select2-modal-field is-select2-pending" x-ref="incidenceSearchWorkerWrapper">
                                        <select class="form-select" id="checador-incidence-filter-worker" name="worker_id" x-ref="incidenceSearchWorker" x-model="searchWorkerId">
                                            <option value=""<?= $contextWorkerId === false ? ' selected' : '' ?>>Todos los colaboradores</option>
                                            <?php foreach ($workerSelectOptions as $workerOption): ?>
                                                <?php
                                                if (!is_array($workerOption)) continue;
                                                $optionWorkerId = filter_var($workerOption['worker_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                                                $optionName = trim((string) ($workerOption['full_name'] ?? ''));
                                                if ($optionWorkerId === false || $optionName === '') continue;
                                                $optionNumber = trim((string) ($workerOption['employee_number'] ?? ''));
                                                ?>
                                                <option value="<?= (int) $optionWorkerId ?>"<?= $contextWorkerId !== false && (int) $contextWorkerId === (int) $optionWorkerId ? ' selected' : '' ?>><?= $esc($optionName) ?><?= $optionNumber !== '' ? ' · N.º ' . $esc($optionNumber) : '' ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php if (($workerSelectStatus ?? '') === 'partial' || ($workerSelectStatus ?? '') === 'current_roster_only'): ?><small class="form-text text-muted">Se muestran personas activas actuales; su pertenencia a la fecha consultada no está verificada.</small><?php elseif (($workerSelectStatus ?? '') !== 'ready'): ?><small class="form-text text-muted">El padrón autorizado no está disponible.</small><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer flex-wrap gap-2">
                            <a class="btn btn-outline-secondary me-md-auto" href="/departamento-operativo/recursos-humanos/checador/incidencias">
                                <i class="ti ti-filter-off me-1" aria-hidden="true"></i>Limpiar filtros
                            </a>
                            <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                                <i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary" x-bind:disabled="searchSubmitting">
                                <i class="ti ti-search me-1" aria-hidden="true"></i>Buscar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <p class="small text-body-secondary border-bottom pb-3 mb-3" aria-live="polite">
                <i class="ti ti-user me-1" aria-hidden="true"></i>
                <?php if ($contextWorkerId !== false): ?>
                    <span><?= $esc($workspaceContextWorkerName !== '' ? $workspaceContextWorkerName : 'Personal ' . (int) $contextWorkerId) ?></span><?php if ($workspaceContextWorkerNumber !== ''): ?>
                        <span class="text-muted">· N.º <?= $esc($workspaceContextWorkerNumber) ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span>Todos los colaboradores</span>
                <?php endif; ?>
                <span class="mx-1" aria-hidden="true">·</span>
                <i class="ti ti-calendar me-1" aria-hidden="true"></i>
                <span><?= $esc($selectedDateDisplay !== '' ? $selectedDateDisplay : 'Fecha no disponible') ?></span>
            </p>
            <?php if (is_array($lifecycleFeedback ?? null)): ?>
                <div class="alert alert-<?= ($lifecycleFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
                    <?= $esc($lifecycleFeedback['message'] ?? 'No fue posible confirmar la acción.') ?>
                </div>
            <?php endif; ?>
            <?php if ($createFeedback !== null): ?>
                <div class="alert alert-<?= ($createFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
                    <?= $esc($createFeedback['message'] ?? 'No fue posible confirmar el alta.') ?>
                </div>
            <?php endif; ?>
            <?php if (($state ?? '') === 'partial' || ($state ?? '') === 'success' && $readErrors !== []): ?>
                <div class="alert alert-warning py-2" role="status">Algunos registros no pudieron consultarse</div>
            <?php elseif (($state ?? '') === 'error'): ?>
                <div class="alert alert-light border text-body-secondary py-2" role="status">No fue posible completar la consulta de incidencias</div>
            <?php elseif (($state ?? '') === 'success' && !$hasConfirmedIncidences): ?>
                <div class="alert alert-info py-2" role="status">No hay incidencias registradas para esta búsqueda</div>
            <?php endif; ?>
            <?php $incidenceModals = []; ?>
            <?php foreach ($incidencesByWorker as $workerId => $incidences): ?>
                <?php if (!is_array($incidences) || $incidences === []) continue; ?>
                <?php if ($contextWorkerId === false || count($incidencesByWorker) > 1): ?>
                    <?php $groupWorkerName = trim((string) ($workerDisplayNames[(int) $workerId] ?? '')); $groupWorkerNumber = $workerNumberFor((int) $workerId); ?>
                    <h3 class="h6 mt-3"><?= $esc($groupWorkerName !== '' ? $groupWorkerName : 'Personal ' . (int) $workerId) ?><?php if ($groupWorkerNumber !== ''): ?> <span class="small text-muted fw-normal">· N.º <?= $esc($groupWorkerNumber) ?></span><?php endif; ?></h3>
                <?php endif; ?>
                    <div class="table-responsive"><table class="table table-striped table-bordered align-middle">
                        <caption class="visually-hidden">Incidencias y estado reportado para personal <?= (int) $workerId ?></caption>
                        <thead><tr><th scope="col">Tipo</th><th scope="col">Periodo</th><th scope="col">Estado</th><th scope="col">Revisión</th><th scope="col">Detalle e historial</th></tr></thead><tbody>
                        <?php foreach ($incidences as $incidenceIndex => $incidence):
                            $isQaIncidence = ($incidence['source_classification'] ?? null) === 'SYNTHETIC_QA_FIXTURE';
                            $revisions = is_array($incidence['revisions'] ?? null) ? $incidence['revisions'] : [];
                            $latest = $revisions === [] ? [] : $revisions[array_key_last($revisions)];
                            $incidenceModalId = 'incidence-history-' . (int) $workerId . '-' . (int) $incidenceIndex;
                            $incidenceWorkerName = trim((string) ($workerDisplayNames[(int) $workerId] ?? ''));
                            $incidenceWorkerNumber = '';
                            foreach ($workerSelectOptions as $workerOption) {
                                if (is_array($workerOption) && (int) ($workerOption['worker_id'] ?? 0) === (int) $workerId) {
                                    $incidenceWorkerNumber = trim((string) ($workerOption['employee_number'] ?? ''));
                                    break;
                                }
                            }
                            $incidenceTypeLabel = $isQaIncidence
                                ? trim((string) ($incidence['qa_title'] ?? 'Incidencia'))
                                : $categoryLabel((string) ($incidence['category'] ?? ''));
                            $incidenceStartDate = trim((string) ($incidence['date_range']['start_date'] ?? ''));
                            $incidenceEndDate = trim((string) ($incidence['date_range']['end_date'] ?? ''));
                            $incidencePeriod = $incidenceStartDate !== '' || $incidenceEndDate !== ''
                                ? ($incidenceStartDate !== '' ? $incidenceStartDate : 'No disponible') . ' — ' . ($incidenceEndDate !== '' ? $incidenceEndDate : 'No disponible')
                                : 'No disponible';
                            $incidenceStatusLabel = $isQaIncidence
                                ? trim((string) ($incidence['qa_status_label'] ?? 'Requiere revisión'))
                                : \App\Services\ChecadorPresentationFormatter::incidenceStatusLabel($incidence['current_status'] ?? null);
                            $incidenceDescription = trim((string) ($isQaIncidence
                                ? ($incidence['qa_detail'] ?? '')
                                : ($incidence['summary_reason'] ?? $latest['reason'] ?? '')));
                            $incidenceCurrentRevision = filter_var($incidence['current_revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                            $incidenceReference = trim((string) ($incidence['uuid_incidencia'] ?? ''));
                            $incidenceEvidenceReference = trim((string) ($incidence['summary_evidence_reference'] ?? $latest['evidence_reference'] ?? ''));
                            $incidenceCheckReference = is_array($incidence['check_reference'] ?? null) ? $incidence['check_reference'] : [];
                        ?>
                            <tr>
                                <th scope="row"><?= $esc($isQaIncidence ? ($incidence['qa_title'] ?? 'Incidencia') : $categoryLabel((string) ($incidence['category'] ?? ''))) ?></th>
                                <td><?= $esc($incidence['date_range']['start_date'] ?? 'No disponible') ?> — <?= $esc($incidence['date_range']['end_date'] ?? 'No disponible') ?></td>
                                <td><?= $isQaIncidence ? $esc($incidence['qa_status_label'] ?? 'Requiere revisión') : $esc(\App\Services\ChecadorPresentationFormatter::incidenceStatusLabel($incidence['current_status'] ?? null)) ?></td>
                                <td><?= $isQaIncidence ? '—' : (int) ($incidence['current_revision'] ?? 0) ?></td>
                                <td class="table-actions-cell text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $esc($incidenceModalId) ?>"><i class="ti ti-eye me-1" aria-hidden="true"></i>Ver detalle</button>
                                    <?php ob_start(); ?>
                                    <div class="modal fade" id="<?= $esc($incidenceModalId) ?>" tabindex="-1" aria-labelledby="<?= $esc($incidenceModalId) ?>-title" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                                            <div class="modal-content">
                                            <div class="modal-header bg-primary align-items-start">
                                                <div class="d-flex flex-grow-1 align-items-start gap-2 me-2">
                                                    <i class="ti ti-eye text-white fs-5 mt-1" aria-hidden="true"></i>
                                                    <div>
                                                        <h4 class="modal-title h5 text-white text-wrap mb-0" id="<?= $esc($incidenceModalId) ?>-title">Detalle de incidencia · <?= $esc($incidenceTypeLabel) ?></h4>
                                                        <p class="small text-white-50 mb-0 mt-1"><?= $esc($incidenceWorkerName !== '' ? $incidenceWorkerName : 'Personal ' . (int) $workerId) ?></p>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3 mb-3">
                                                    <div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><span class="d-block small text-muted">Colaborador</span><strong><?= $esc($incidenceWorkerName !== '' ? $incidenceWorkerName : 'Personal ' . (int) $workerId) ?></strong><?php if ($incidenceWorkerNumber !== ''): ?><span class="d-block small text-muted mt-1">N.º <?= $esc($incidenceWorkerNumber) ?></span><?php endif; ?></div></div>
                                                    <div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><span class="d-block small text-muted">Tipo de incidencia</span><strong><?= $esc($incidenceTypeLabel) ?></strong></div></div>
                                                    <div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><span class="d-block small text-muted">Fecha o periodo</span><strong><?= $esc($incidencePeriod) ?></strong></div></div>
                                                    <div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><span class="d-block small text-muted">Estado</span><strong><?= $esc($incidenceStatusLabel) ?></strong></div></div>
                                                    <?php if ($incidenceCurrentRevision !== false): ?><div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><span class="d-block small text-muted">Revisión actual</span><strong><?= (int) $incidenceCurrentRevision ?></strong></div></div><?php endif; ?>
                                                    <div class="col-12"><div class="border rounded p-3"><span class="d-block small text-muted">Descripción</span><p class="mb-0 mt-1"><?= $esc($incidenceDescription !== '' ? $incidenceDescription : 'No disponible') ?></p></div></div>
                                                    <?php if ($incidenceCheckReference !== []): ?>
                                                        <div class="col-12"><div class="border rounded p-3"><h5 class="h6">Trazabilidad del chequeo declarado</h5><p class="small text-muted">La incidencia permanece pendiente de revisión; estos datos no son una evaluación oficial de jornada ni un cálculo automático de retardo.</p>
                                                            <dl class="row small mb-0"><dt class="col-sm-4">Fecha local</dt><dd class="col-sm-8"><?= $esc($incidenceCheckReference['check_date'] ?? '') ?></dd><dt class="col-sm-4">Marcación declarada</dt><dd class="col-sm-8"><?= $esc($checkKindLabelFor((string) ($incidenceCheckReference['check_kind'] ?? ''))) ?></dd><dt class="col-sm-4">Naturaleza declarada</dt><dd class="col-sm-8"><?= $esc($checkNatureLabelFor((string) ($incidenceCheckReference['nature'] ?? ''))) ?></dd><dt class="col-sm-4">Política vinculada</dt><dd class="col-sm-8">Versión #<?= (int) ($incidenceCheckReference['policy_version'] ?? 0) ?> · <?= $esc($incidenceCheckReference['timezone_id'] ?? '') ?></dd><dt class="col-sm-4">Vencimiento local</dt><dd class="col-sm-8"><?= $esc($incidenceCheckReference['cutoff_local'] ?? '') ?></dd>
                                                                <?php if (($incidenceCheckReference['source_event_uuid'] ?? null) !== null): ?><dt class="col-sm-4">Evento fuente inmutable</dt><dd class="col-sm-8 text-break">Dispositivo <?= (int) ($incidenceCheckReference['source_event_device_id'] ?? 0) ?> · <?= $esc($incidenceCheckReference['source_event_uuid']) ?></dd><?php endif; ?>
                                                            </dl>
                                                        </div></div>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($incidenceReference !== '' || $incidenceEvidenceReference !== ''): ?>
                                                    <dl class="row small mb-3">
                                                        <?php if ($incidenceReference !== ''): ?><dt class="col-sm-4">Referencia</dt><dd class="col-sm-8 text-break"><span class="font-monospace small text-secondary user-select-all text-break"><?= $esc($incidenceReference) ?></span></dd><?php endif; ?>
                                                        <?php if ($incidenceEvidenceReference !== ''): ?><dt class="col-sm-4">Evidencia</dt><dd class="col-sm-8 text-break mb-0"><?= $esc($incidenceEvidenceReference) ?></dd><?php endif; ?>
                                                    </dl>
                                                <?php endif; ?>
                                    <?php if ($revisions !== []): ?><h5 class="h6">Historial de revisión</h5><ol class="small mb-0">
                                        <?php foreach ($revisions as $revision): ?><?php [$revisionTimestamp, $revisionTimezone] = $revisionTimestampForDisplay($revision['created_at_utc'] ?? null); $revisionActorId = filter_var($revision['created_by_user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); ?><li class="mb-2"><div class="d-flex flex-wrap align-items-center gap-2"><strong>Revisión <?= (int) ($revision['revision_number'] ?? 0) ?></strong><span class="badge text-bg-light border text-body-secondary"><?= $esc(\App\Services\ChecadorPresentationFormatter::incidenceStatusLabel($revision['status'] ?? null)) ?></span></div><p class="mb-1 mt-1"><?= $esc($revision['reason'] ?? 'Sin motivo disponible') ?></p><span class="small text-muted"><?= $revisionActorId !== false ? 'Usuario ID ' . (int) $revisionActorId : 'Usuario no disponible' ?> · <?= $esc($revisionTimestamp) ?><?= $revisionTimezone !== '' ? ' · ' . $esc($revisionTimezone) : '' ?></span><?php if (($revision['evidence_reference'] ?? null) !== null): ?><br><span class="text-muted">Evidencia: <?= $esc($revision['evidence_reference']) ?></span><?php endif; ?></li><?php endforeach; ?>
                                    </ol><?php endif; ?>
                                            </div>
                                            <div class="modal-footer justify-content-end gap-2"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x" aria-hidden="true"></i> Cerrar</button></div>
                                        </div></div>
                                    </div>
                                    <?php $incidenceModals[] = (string) ob_get_clean(); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody></table></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php foreach ($incidenceModals as $incidenceModalMarkup): ?><?= $incidenceModalMarkup ?><?php endforeach; ?>
    <?php if ($incidenceCreateAllowed): ?>
        <div class="modal fade" id="checador-create-incidence-modal" tabindex="-1" aria-labelledby="checador-create-incidence-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/crear" class="modal-content" id="checador-create-incidence-form">
                    <div class="modal-header bg-primary">
                        <h2 class="modal-title h5 text-white d-flex align-items-center gap-2" id="checador-create-incidence-title">
                            <i class="ti ti-file-plus" aria-hidden="true"></i>Nueva incidencia
                        </h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($createFeedback !== null && ($createFeedback['type'] ?? '') !== 'success'): ?>
                            <div class="alert alert-warning" role="alert"><?= $esc($createFeedback['message'] ?? 'No fue posible guardar la incidencia.') ?></div>
                        <?php endif; ?>
                        <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                        <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                        <input type="hidden" name="return_date" value="<?= $esc($selectedDateValue) ?>">
                        <input type="hidden" name="idempotency_key" value="<?= $esc(($createFeedback['retry_idempotency_key'] ?? '') ?: ($incidenceCreateKey ?? '')) ?>">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="incidence-create-workplace">Estación autorizada</label>
                                <input class="form-control" id="incidence-create-workplace" value="<?= $esc($selectedWorkplaceValue !== '' ? $selectedWorkplaceValue : 'Centro autorizado') ?>" readonly>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="checador-create-worker">Colaborador</label>
                                <div id="checador-create-worker-wrapper" class="select2-modal-field is-select2-pending" x-ref="incidenceWorkerSelectWrapper">
                                    <select class="form-select" id="checador-create-worker" name="worker_id" x-ref="incidenceWorkerSelect" x-model="formWorkerId" required>
                                        <option value="">Selecciona un colaborador</option>
                                        <?php foreach ($createWorkerOptions as $workerOption): if (!is_array($workerOption)) continue; $optionWorkerId = (int) ($workerOption['worker_id'] ?? 0); ?>
                                            <option value="<?= $optionWorkerId ?>" <?= $createWorkerId === $optionWorkerId ? 'selected' : '' ?>><?= $esc($workerOption['full_name'] ?? ('Personal ' . $optionWorkerId)) ?><?= trim((string) ($workerOption['employee_number'] ?? '')) !== '' ? ' · ' . $esc($workerOption['employee_number']) : '' ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if (($workerSelectStatus ?? '') === 'partial' || ($workerSelectStatus ?? '') === 'current_roster_only'): ?><small class="text-muted">Se muestran personas activas actuales; su pertenencia a la fecha consultada no está verificada.</small><?php elseif (($workerSelectStatus ?? '') !== 'ready'): ?><small class="text-muted">El padrón autorizado no está disponible.</small><?php endif; ?>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="incidence-create-category">Tipo de incidencia</label>
                                <select class="form-select" id="incidence-create-category" name="category" required>
                                    <option value="">Selecciona</option>
                                    <?php foreach ($createCategories as $code => $label): ?>
                                        <option value="<?= $esc($code) ?>" <?= ($createValues['category'] ?? '') === $code ? 'selected' : '' ?>><?= $esc($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-8" id="incidence-create-period-fields">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="incidence-create-start">Fecha inicial</label>
                                        <input class="form-control" type="date" id="incidence-create-start" name="start_date" value="<?= $esc($createValues['start_date'] ?? $selectedDateValue) ?>" required>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="incidence-create-end">Fecha final</label>
                                        <input class="form-control" type="date" id="incidence-create-end" name="end_date" value="<?= $esc($createValues['end_date'] ?? $selectedDateValue) ?>" required>
                                    </div>
                                </div>
                            </div>
                            <?php if ($p3gBApiPostOperationsEnabled): ?>
                                <div class="col-12" id="incidence-create-punch-check-fields" hidden>
                                    <div class="border rounded p-3">
                                        <h3 class="h6">Chequeo de marcación</h3>
                                        <p class="small text-muted">La categoría registra una declaración pendiente y su vínculo de trazabilidad. No crea ni modifica marcaciones, no calcula retardos y requiere una política V2 vigente para la fecha.</p>
                                        <div class="row g-3">
                                            <div class="col-12 col-md-4"><label class="form-label" for="incidence-create-check-date">Fecha local del chequeo</label><input class="form-control" type="date" id="incidence-create-check-date" name="check_date" value="<?= $esc($createValues['check_date'] ?? $selectedDateValue) ?>" data-punch-required="1" disabled></div>
                                            <div class="col-12 col-md-4"><label class="form-label" for="incidence-create-check-kind">Marcación esperada</label><select class="form-select" id="incidence-create-check-kind" name="check_kind" data-punch-required="1" disabled><option value="">Selecciona</option><?php foreach (['ENTRY' => 'Entrada', 'LUNCH_EXIT' => 'Salida a comida', 'LUNCH_RETURN' => 'Regreso de comida', 'FINAL_EXIT' => 'Salida final'] as $checkCode => $checkLabel): ?><option value="<?= $esc($checkCode) ?>" <?= ($createValues['check_kind'] ?? '') === $checkCode ? 'selected' : '' ?>><?= $esc($checkLabel) ?></option><?php endforeach; ?></select></div>
                                            <div class="col-12 col-md-4"><label class="form-label" for="incidence-create-nature">Tipo de declaración</label><select class="form-select" id="incidence-create-nature" name="nature" data-punch-required="1" disabled><option value="">Selecciona</option><option value="LATENESS" <?= ($createValues['nature'] ?? '') === 'LATENESS' ? 'selected' : '' ?>>Retardo declarado</option><option value="OMISSION" <?= ($createValues['nature'] ?? '') === 'OMISSION' ? 'selected' : '' ?>>Omisión declarada</option></select></div>
                                            <div class="col-12" id="incidence-create-source-event-fields" hidden><div class="row g-3"><div class="col-12 col-md-4"><label class="form-label" for="incidence-create-source-device">ID de dispositivo fuente</label><input class="form-control" type="number" min="1" step="1" id="incidence-create-source-device" name="source_event_device_id" value="<?= $esc($createValues['source_event_device_id'] ?? '') ?>" disabled></div><div class="col-12 col-md-8"><label class="form-label" for="incidence-create-source-uuid">UUID de marcación inmutable</label><input class="form-control" id="incidence-create-source-uuid" name="source_event_uuid" maxlength="64" value="<?= $esc($createValues['source_event_uuid'] ?? '') ?>" disabled></div></div></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php if ($localQaIncidenceStoreAvailable): ?><div class="col-12"><small class="form-text text-muted">El periodo debe incluir la fecha laboral seleccionada.</small></div><?php endif; ?>
                            <div class="col-12">
                                <label class="form-label" for="incidence-create-reason">Motivo o descripción</label>
                                <textarea class="form-control" id="incidence-create-reason" name="reason" rows="3" maxlength="2000" required><?= $esc($createValues['reason'] ?? '') ?></textarea>
                                <small class="form-text text-muted"><?= $p3gBApiPostOperationsEnabled ? 'La prueba P3G-B requiere que la descripción QA comience con [QA DEV] y que la referencia de evidencia con QA-M06-. No se crean incidencias automáticamente.' : 'Para una entrada o salida no registrada, selecciona Justificación y documenta el motivo.' ?></small>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="incidence-create-evidence">Referencia de evidencia (opcional)</label>
                                <input class="form-control" id="incidence-create-evidence" name="evidence_reference" value="<?= $esc($createValues['evidence_reference'] ?? '') ?>" maxlength="500">
                            </div>
                            <div class="col-12">
                                <label class="form-check-label" for="incidence-create-confirm">
                                    <input class="form-check-input me-1" id="incidence-create-confirm" type="checkbox" name="confirm_create" value="1" required<?= !empty($createValues['confirm_create']) ? ' checked' : '' ?>>
                                    Confirmo registrar la incidencia para el colaborador y periodo indicados.
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end gap-2">
                        <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i>Registrar incidencia</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

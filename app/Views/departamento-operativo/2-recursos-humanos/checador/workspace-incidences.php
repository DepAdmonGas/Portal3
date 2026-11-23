<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$workers = is_array($incidenceWorkers ?? null) ? $incidenceWorkers : [];
$categories = is_array($incidenceCategories ?? null) ? $incidenceCategories : [];
$feedback = is_array($incidenceFeedback ?? null) ? $incidenceFeedback : null;
$feedbackMessage = is_string($feedback['message'] ?? null) && trim($feedback['message']) !== ''
    ? trim($feedback['message'])
    : null;
$readErrorMessage = 'No fue posible consultar las incidencias. Intenta nuevamente.';
$formValues = is_array($incidenceFormValues ?? null) ? $incidenceFormValues : [];
$writerBlockMessage = match ((string) ($incidenceWriterBlockReason ?? '')) {
    'schema_preflight_failed' => 'El esquema SQL requerido no está disponible; no se guardó ningún cambio.',
    default => 'No se pudo confirmar la disponibilidad de la base de datos; no se guardó ningún cambio.',
};
$lifecyclePermissions = is_array($incidenceLifecyclePermissions ?? null) ? $incidenceLifecyclePermissions : [];
$canReviseIncidences = ($incidenceCanRevise ?? false) === true;
$m06WriterReady = ($incidenceWriterReady ?? false) === true;
$canManageIncidences = ($incidenceCanCreate ?? false) === true || $canReviseIncidences || in_array(true, $lifecyclePermissions, true);
?>
<div id="container" class="mt-4 mb-5">
    <section class="card mb-4" aria-labelledby="incidence-workspace-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2 gap-md-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-alert-triangle fs-5"></i></span>
            <h2 class="h5 mb-0 text-white" id="incidence-workspace-heading">Incidencias del personal</h2>
            <div class="dropdown ms-auto">
                <button type="button" class="btn btn-light dropdown-toggle text-dark" id="m06-incidence-options" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Opciones de incidencias">
                    <i class="ti ti-dots-vertical fs-4" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="m06-incidence-options">
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m06-incidence-search-modal"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar incidencias</button></li>
                    <?php if (($incidenceCanCreate ?? false) === true): ?>
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m06-incidence-create-modal"><i class="ti ti-plus me-1" aria-hidden="true"></i>Nueva incidencia</button></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$m06WriterReady && $canManageIncidences): ?><div class="alert alert-warning d-flex align-items-start gap-2" role="status"><i class="ti ti-lock mt-1" aria-hidden="true"></i><span><?= $esc($writerBlockMessage) ?></span></div><?php endif; ?>
            <?php if ($feedbackMessage !== null): ?>
                <?php $feedbackSuccess = ($feedback['type'] ?? '') === 'success'; ?>
                <div class="alert alert-<?= $feedbackSuccess ? 'success' : 'warning' ?> d-flex align-items-start gap-2" role="status">
                    <i class="ti <?= $feedbackSuccess ? 'ti-circle-check' : 'ti-alert-triangle' ?> mt-1" aria-hidden="true"></i>
                    <?= $esc($feedbackMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (($incidenceState ?? '') === 'denied'): ?>
                <div class="alert alert-danger d-flex align-items-start gap-2" role="alert"><i class="ti ti-lock mt-1" aria-hidden="true"></i><span>No tienes permiso efectivo para consultar incidencias en esta localidad.</span></div>
            <?php elseif (($incidenceState ?? '') !== 'ready'): ?>
                <div class="alert alert-warning d-flex align-items-start gap-2" role="alert"><i class="ti ti-alert-triangle mt-1" aria-hidden="true"></i><span><?= $esc($readErrorMessage) ?></span></div>
            <?php else: ?>
                <?php if (($incidenceCanCreate ?? false) === true): ?>
                    <p class="small text-muted">Las nuevas incidencias se registran pendientes de autorización. El registro no genera faltas ni modifica marcaciones.</p>
                <?php endif; ?>
                <div class="text-body-secondary text-center py-3 mb-3" role="status" data-m06-list-state>Cargando incidencias…</div>
                <div class="table-responsive" data-m06-table-wrapper>
                    <table id="m06-incidences-table" class="table table-striped table-bordered align-middle w-100"
                           data-checador-m06-incidences
                           data-m06-workplace-id="<?= (int) ($selectedWorkplaceId ?? 0) ?>"
                           data-m06-workplace-name="<?= $esc($selectedWorkplaceName ?? 'Localidad') ?>"
                           data-m06-can-revise="<?= $canReviseIncidences ? '1' : '0' ?>"
                           data-m06-can-resolve="<?= !empty($lifecyclePermissions['resolve']) ? '1' : '0' ?>"
                           data-m06-can-authorize="<?= !empty($lifecyclePermissions['authorize']) ? '1' : '0' ?>"
                           data-m06-can-reject="<?= !empty($lifecyclePermissions['reject']) ? '1' : '0' ?>"
                           data-m06-can-revoke="<?= !empty($lifecyclePermissions['revoke']) ? '1' : '0' ?>"
                           data-m06-data-url="/departamento-operativo/recursos-humanos/checador/incidencias/data">
                        <caption class="visually-hidden">Incidencias administrativas de <?= $esc($selectedWorkplaceName ?? 'la localidad seleccionada') ?></caption>
                        <thead><tr>
                            <th scope="col">Trabajador</th><th scope="col">Tipo</th><th scope="col">Periodo</th>
                            <th scope="col">Estado</th><th scope="col">Registro UTC</th>
                            <th scope="col" class="text-center"><span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical" aria-hidden="true"></i></th>
                        </tr></thead><tbody></tbody>
                    </table>
                </div>
                <div class="modal fade" id="m06-incidence-detail-modal" tabindex="-1" aria-labelledby="m06-incidence-detail-title" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
                        <div class="modal-header modal-colored-header bg-primary text-white">
                            <h3 class="modal-title text-white h5" id="m06-incidence-detail-title"><i class="ti ti-file-description me-1" aria-hidden="true"></i><span data-m06-detail-heading>Incidencia</span></h3>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <ul class="nav nav-tabs mb-3" role="tablist">
                                <li class="nav-item" role="presentation"><button class="nav-link active" id="m06-incidence-detail-tab" data-bs-toggle="tab" data-bs-target="#m06-incidence-detail-pane" type="button" role="tab" aria-controls="m06-incidence-detail-pane" aria-selected="true"><i class="ti ti-eye me-1" aria-hidden="true"></i>Detalle</button></li>
                                <li class="nav-item" role="presentation"><button class="nav-link" id="m06-incidence-history-tab" data-bs-toggle="tab" data-bs-target="#m06-incidence-history-pane" type="button" role="tab" aria-controls="m06-incidence-history-pane" aria-selected="false"><i class="ti ti-history me-1" aria-hidden="true"></i>Historial (<span data-m06-history-count>0</span>)</button></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="m06-incidence-detail-pane" role="tabpanel" aria-labelledby="m06-incidence-detail-tab" tabindex="0">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">Trabajador</dt><dd class="col-sm-8" data-m06-detail-worker>No disponible</dd>
                                        <dt class="col-sm-4">Localidad</dt><dd class="col-sm-8" data-m06-detail-workplace>No disponible</dd>
                                        <dt class="col-sm-4">Tipo</dt><dd class="col-sm-8" data-m06-detail-category>No disponible</dd>
                                        <dt class="col-sm-4">Periodo</dt><dd class="col-sm-8" data-m06-detail-period>No disponible</dd>
                                        <dt class="col-sm-4">Estado actual</dt><dd class="col-sm-8" data-m06-detail-status>No disponible</dd>
                                        <dt class="col-sm-4">Motivo</dt><dd class="col-sm-8 text-break" data-m06-detail-reason>No disponible</dd>
                                        <dt class="col-sm-4">Evidencia</dt><dd class="col-sm-8 text-break" data-m06-detail-evidence>No disponible</dd>
                                        <dt class="col-sm-4">Registrada UTC</dt><dd class="col-sm-8" data-m06-detail-created>No disponible</dd>
                                        <dt class="col-sm-4">Referencia</dt><dd class="col-sm-8 text-break"><code data-m06-detail-uuid>No disponible</code></dd>
                                    </dl>
                                </div>
                                <div class="tab-pane fade" id="m06-incidence-history-pane" role="tabpanel" aria-labelledby="m06-incidence-history-tab" tabindex="0">
                                    <p class="text-muted mb-0" data-m06-history-empty>El historial de revisiones no está disponible para este registro.</p>
                                    <ol class="list-group list-group-numbered list-group-flush" data-m06-history-list></ol>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cerrar</button></div>
                    </div></div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if ($canReviseIncidences || in_array(true, $lifecyclePermissions, true)): ?>
<div class="modal fade" id="m06-incidence-action-modal" tabindex="-1" aria-labelledby="m06-incidence-action-title" aria-hidden="true" data-m06-action-modal data-m06-writer-ready="<?= $m06WriterReady ? '1' : '0' ?>">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header modal-colored-header bg-primary text-white"><h2 class="modal-title text-white h5" id="m06-incidence-action-title"><i class="ti ti-edit me-1" aria-hidden="true"></i><span data-m06-action-title>Administrar incidencia</span></h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <form method="post" action="" data-m06-action-form>
            <div class="modal-body row g-3">
                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                <input type="hidden" name="workplace_id" value="">
                <input type="hidden" name="expected_current_revision" value="">
                <div class="col-12"><label class="form-label" for="m06-action-reason" data-m06-reason-label>Motivo</label><textarea class="form-control" id="m06-action-reason" name="reason" rows="3" maxlength="5000" data-m06-reason required></textarea></div>
                <div class="col-12"><label class="form-label" for="m06-action-evidence">Referencia de evidencia (opcional)</label><input class="form-control" id="m06-action-evidence" name="evidence_reference" maxlength="100"></div>
                <div class="col-12 col-md-6" data-m06-revision-date><label class="form-label" for="m06-action-start">Fecha de inicio</label><input class="form-control" type="date" id="m06-action-start" name="start_date"></div>
                <div class="col-12 col-md-6" data-m06-revision-date><label class="form-label" for="m06-action-end">Fecha de término</label><input class="form-control" type="date" id="m06-action-end" name="end_date"></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="m06-confirm-action" name="confirm_action" value="1" required><label class="form-check-label" for="m06-confirm-action" data-m06-confirm-label>Confirmo esta acción sobre la revisión actual.</label></div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button><button type="submit" class="btn btn-primary" data-m06-action-submit <?= $m06WriterReady ? '' : 'disabled' ?>><i class="ti ti-check me-1" aria-hidden="true"></i><span data-m06-submit-label>Confirmar</span></button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>

<div class="modal fade" id="m06-incidence-search-modal" tabindex="-1" aria-labelledby="m06-incidence-search-title" aria-hidden="true" data-m06-select2-modal>
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header modal-colored-header bg-primary text-white"><h2 class="modal-title text-white h5" id="m06-incidence-search-title"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar incidencias</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/data" data-m06-filter-form x-data="m06IncidenceFilters" @submit.prevent="buscar">
            <div class="modal-body row g-3">
                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                <div class="col-12"><label class="form-label" for="m06-filter-worker">Trabajador</label><div class="select2-modal-field w-100"><select class="form-select w-100" id="m06-filter-worker" name="worker_id" x-model="filters.worker_id" data-m06-worker-select="filter"><option value="">Todos</option><?php foreach ($workers as $worker): ?><option value="<?= (int) ($worker['worker_id'] ?? 0) ?>"><?= $esc($worker['full_name'] ?? 'Nombre no disponible') ?> · <?= (int) ($worker['worker_id'] ?? 0) ?></option><?php endforeach; ?></select></div></div>
                <div class="col-12"><label class="form-label" for="m06-filter-category">Tipo de incidencia</label><select class="form-select" id="m06-filter-category" name="category" x-model="filters.category"><option value="">Todos</option><?php foreach ($categories as $code => $label): ?><option value="<?= $esc($code) ?>"><?= $esc($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-sm-6"><label class="form-label" for="m06-filter-start">Desde</label><input class="form-control" type="date" id="m06-filter-start" name="start_date" x-model="filters.start_date"></div>
                <div class="col-sm-6"><label class="form-label" for="m06-filter-end">Hasta</label><input class="form-control" type="date" id="m06-filter-end" name="end_date" x-model="filters.end_date"></div>
                <div class="col-12"><label class="form-label" for="m06-filter-status">Estado</label><select class="form-select" id="m06-filter-status" name="status" x-model="filters.status"><option value="">Todos</option><?php foreach (['PENDING_APPROVAL' => 'Pendiente de autorización', 'RESOLVED_PENDING_AUTHORIZATION' => 'Resuelta · pendiente de autorización', 'ACTIVE' => 'Activa', 'REVIEW_REQUIRED' => 'Requiere revisión', 'REJECTED' => 'Rechazada', 'CANCELLED' => 'Cancelada', 'REVOKED' => 'Revocada', 'SUPERSEDED' => 'Sustituida'] as $code => $label): ?><option value="<?= $esc($code) ?>"><?= $esc($label) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="modal-footer">
                <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0 me-0 me-md-2"><button class="btn btn-outline-secondary" type="button" @click="limpiar"><i class="ti ti-refresh me-1" aria-hidden="true"></i>Limpiar</button></div>
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0 me-0 me-md-2"><button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button></div>
                    <div class="col-12 col-md-auto d-grid"><button type="submit" class="btn btn-primary" :disabled="cargando"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar</button></div>
                </div>
            </div>
        </form>
    </div></div>
</div>

<?php if (($incidenceCanCreate ?? false) === true): ?>
<div class="modal fade" id="m06-incidence-create-modal" tabindex="-1" aria-labelledby="m06-incidence-create-title" aria-hidden="true" data-m06-auto-open="<?= $feedback !== null && ($feedback['type'] ?? '') === 'error' ? '1' : '0' ?>" data-m06-select2-modal>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header modal-colored-header bg-primary text-white"><h2 class="modal-title text-white h5" id="m06-incidence-create-title"><i class="ti ti-plus me-1" aria-hidden="true"></i>Nueva incidencia</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/crear">
            <div class="modal-body row g-3">
                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                <input type="hidden" name="idempotency_key" value="<?= $esc($incidenceIdempotencyKey ?? '') ?>">
                <?php if (($incidenceRosterAvailable ?? false) !== true): ?><div class="col-12"><div class="alert alert-warning mb-0" role="status">No se pudo confirmar el padrón autorizado. No hay trabajadores disponibles para seleccionar.</div></div><?php endif; ?>
                <div class="col-12"><label class="form-label" for="m06-create-worker">Trabajador</label><div class="select2-modal-field w-100"><select class="form-select w-100" id="m06-create-worker" name="worker_id" data-m06-worker-select="create" required <?= ($incidenceRosterAvailable ?? false) !== true ? 'disabled' : '' ?>><option value="">Selecciona un trabajador</option><?php foreach ($workers as $worker): ?><option value="<?= (int) ($worker['worker_id'] ?? 0) ?>" <?= (string) ($formValues['worker_id'] ?? '') === (string) ($worker['worker_id'] ?? '') ? 'selected' : '' ?>><?= $esc($worker['full_name'] ?? 'Nombre no disponible') ?> · ID <?= (int) ($worker['worker_id'] ?? 0) ?></option><?php endforeach; ?></select></div></div>
                <div class="col-12 col-md-6"><label class="form-label" for="m06-create-category">Tipo</label><select class="form-select" id="m06-create-category" name="category" required><option value="">Selecciona</option><?php foreach ($categories as $code => $label): ?><option value="<?= $esc($code) ?>" <?= ($formValues['category'] ?? '') === $code ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-12 col-md-6"><label class="form-label" for="m06-create-start">Fecha de inicio</label><input class="form-control" type="date" id="m06-create-start" name="start_date" value="<?= $esc($formValues['start_date'] ?? '') ?>" required></div>
                <div class="col-12 col-md-6"><label class="form-label" for="m06-create-end">Fecha de término</label><input class="form-control" type="date" id="m06-create-end" name="end_date" value="<?= $esc($formValues['end_date'] ?? '') ?>" required></div>
                <div class="col-12"><label class="form-label" for="m06-create-reason">Motivo o descripción</label><textarea class="form-control" id="m06-create-reason" name="reason" rows="3" maxlength="5000" required><?= $esc($formValues['reason'] ?? '') ?></textarea></div>
                <div class="col-12"><label class="form-label" for="m06-create-evidence">Referencia de evidencia (opcional)</label><input class="form-control" id="m06-create-evidence" name="evidence_reference" maxlength="100" value="<?= $esc($formValues['evidence_reference'] ?? '') ?>"></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="m06-confirm-create" name="confirm_create" value="1" required><label class="form-check-label" for="m06-confirm-create">Confirmo el registro de esta incidencia para la persona y periodo seleccionados.</label></div></div>
            </div>
            <div class="modal-footer">
                <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0 me-0 me-md-2"><button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button></div>
                    <div class="col-12 col-md-auto d-grid"><button type="submit" class="btn btn-success" <?= (($incidenceWriterReady ?? false) !== true || ($incidenceRosterAvailable ?? false) !== true) ? 'disabled' : '' ?>><i class="ti ti-check me-1" aria-hidden="true"></i>Guardar incidencia</button></div>
                </div>
            </div>
        </form>
    </div></div>
</div>
<?php endif; ?>

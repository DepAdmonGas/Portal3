<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$items = is_array($incidenceItems ?? null) ? $incidenceItems : [];
$filters = is_array($incidenceFilters ?? null) ? $incidenceFilters : [];
$workers = is_array($incidenceWorkers ?? null) ? $incidenceWorkers : [];
$categories = is_array($incidenceCategories ?? null) ? $incidenceCategories : [];
$feedback = is_array($incidenceFeedback ?? null) ? $incidenceFeedback : null;
$feedbackMessage = is_string($feedback['message'] ?? null) && trim($feedback['message']) !== ''
    ? trim($feedback['message'])
    : null;
$formValues = is_array($incidenceFormValues ?? null) ? $incidenceFormValues : [];
$writerBlockMessage = match ((string) ($incidenceWriterBlockReason ?? '')) {
    'api_writer_cutover_unverified' => 'El guardado permanece cerrado hasta comprobar el corte del escritor anterior',
    'schema_preflight_failed' => 'El guardado permanece cerrado porque no se confirmó el esquema y los permisos SQL requeridos',
    default => 'El guardado permanece cerrado hasta verificar las condiciones operativas requeridas',
};
$readErrorMessage = match ((string) ($incidenceReadError ?? '')) {
    'sql_read_failed' => 'Ocurrió un error SQL al consultar las incidencias. Intenta actualizar la página.',
    'invalid_filter' => 'Los filtros enviados no son válidos. Corrígelos y vuelve a buscar.',
    'permission_denied' => 'No tienes permiso efectivo para consultar incidencias en esta localidad.',
    default => 'No fue posible consultar las incidencias. No se interpreta el error como una lista vacía.',
};
$pagination = is_array($incidencePagination ?? null) ? $incidencePagination : [];
$statusLabel = static fn(string $status): string => match ($status) {
    'PENDING_APPROVAL' => 'Pendiente de autorización',
    'RESOLVED_PENDING_AUTHORIZATION' => 'Resuelta · pendiente de autorización',
    'ACTIVE' => 'Activa',
    'CANCELLED' => 'Cancelada',
    'SUPERSEDED' => 'Sustituida',
    'REVIEW_REQUIRED' => 'Requiere revisión',
    'REJECTED' => 'Rechazada',
    'REVOKED' => 'Revocada',
    default => 'Estado no disponible',
};
$lifecyclePermissions = is_array($incidenceLifecyclePermissions ?? null) ? $incidenceLifecyclePermissions : [];
$canReviseIncidences = ($incidenceCanRevise ?? false) === true;
$m06WriterReady = ($incidenceWriterReady ?? false) === true;
$canManageIncidences = ($incidenceCanCreate ?? false) === true || $canReviseIncidences || in_array(true, $lifecyclePermissions, true);
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(0, (int) ($pagination['total_pages'] ?? 0));
$queryBase = array_filter([
    'workplace_id' => (int) ($selectedWorkplaceId ?? 0),
    'worker_id' => $filters['worker_id'] ?? null,
    'category' => $filters['category'] ?? null,
    'status' => $filters['status'] ?? null,
    'start_date' => $filters['start_date'] ?? null,
    'end_date' => $filters['end_date'] ?? null,
], static fn(mixed $value): bool => $value !== null && $value !== '');
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
            <?php if (!$m06WriterReady && $canManageIncidences): ?><div class="alert alert-warning d-flex align-items-start gap-2" role="status"><i class="ti ti-lock mt-1" aria-hidden="true"></i><span><?= $esc($writerBlockMessage) ?>. Las acciones de escritura permanecen deshabilitadas hasta que se compruebe el corte API.</span></div><?php endif; ?>
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
                <?php if ($items === []): ?>
                    <p class="text-body-secondary text-center py-3 mb-0" role="status"><i class="ti ti-info-circle text-primary me-1" aria-hidden="true"></i>No hay incidencias registradas para los filtros seleccionados</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="m06-incidences-table" class="table table-striped table-bordered align-middle w-100" data-checador-m06-incidences>
                            <caption class="visually-hidden">Incidencias administrativas de <?= $esc($selectedWorkplaceName ?? 'la localidad seleccionada') ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col">Trabajador</th>
                                    <th scope="col">Tipo</th>
                                    <th scope="col">Periodo</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Registro UTC</th>
                                    <th scope="col" class="text-center"><span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical" aria-hidden="true"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $index => $item):
                                    $modalId = 'm06-incidence-detail-' . $index;
                                    $category = (string) ($item['category'] ?? '');
                                    $status = (string) ($item['current_status'] ?? '');
                                    $revisions = is_array($item['revisions'] ?? null) ? $item['revisions'] : [];
                                    $uuid = (string) ($item['uuid_incidencia'] ?? '');
                                    $actionBase = '/departamento-operativo/recursos-humanos/checador/incidencias/' . rawurlencode($uuid);
                                    $revisionTerminal = in_array($status, ['CANCELLED', 'REVOKED', 'REJECTED'], true);
                                    $canResolve = !empty($lifecyclePermissions['resolve']) && !in_array($status, ['CANCELLED', 'REVOKED'], true);
                                    $canAuthorize = !empty($lifecyclePermissions['authorize']) && !in_array($status, ['CANCELLED', 'REVOKED', 'REJECTED'], true);
                                    $canReject = !empty($lifecyclePermissions['reject']) && !in_array($status, ['CANCELLED', 'REVOKED', 'REJECTED'], true);
                                    $canRevoke = !empty($lifecyclePermissions['revoke']) && !in_array($status, ['CANCELLED', 'REVOKED'], true);
                                ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold"><?= $esc($item['worker_name'] ?? 'Nombre no disponible') ?></span>
                                            <span class="d-block small text-muted">ID <?= (int) ($item['worker_id'] ?? 0) ?></span>
                                        </td>
                                        <td><?= $esc($categories[$category] ?? 'Tipo no disponible') ?></td>
                                        <td class="text-nowrap"><?= $esc($item['start_date'] ?? '') ?> — <?= $esc($item['end_date'] ?? '') ?></td>
                                        <td><span class="badge <?= $status === 'PENDING_APPROVAL' ? 'text-bg-warning' : 'text-bg-secondary' ?>"><?= $esc($statusLabel($status)) ?></span></td>
                                        <td class="text-nowrap"><?= $esc($item['created_at_utc'] ?? 'No disponible') ?></td>
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn p-0 border-0 bg-transparent text-body" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones para <?= $esc($item['worker_name'] ?? 'trabajador') ?>">
                                                    <i class="ti ti-dots-vertical fs-5" aria-hidden="true"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#<?= $esc($modalId) ?>" data-incidence-tab="detail"><i class="ti ti-eye me-1" aria-hidden="true"></i>Ver detalle</button></li>
                                                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#<?= $esc($modalId) ?>" data-incidence-tab="history"><i class="ti ti-history me-1" aria-hidden="true"></i>Ver historial</button></li>
                                                    <?php if (($canReviseIncidences && !$revisionTerminal) || $canResolve || $canAuthorize || $canReject || $canRevoke): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                                                    <?php if ($canReviseIncidences && !$revisionTerminal): ?><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m06-incidence-action-modal" data-m06-open-action data-m06-action="revision" data-m06-action-url="<?= $esc($actionBase . '/revisiones') ?>" data-m06-current-revision="<?= (int) ($item['current_revision'] ?? 0) ?>" data-m06-workplace="<?= (int) ($item['workplace_id'] ?? 0) ?>" data-m06-start="<?= $esc($item['start_date'] ?? '') ?>" data-m06-end="<?= $esc($item['end_date'] ?? '') ?>"><i class="ti ti-edit me-1" aria-hidden="true"></i>Crear revisión</button></li><?php endif; ?>
                                                    <?php if ($canResolve): ?><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m06-incidence-action-modal" data-m06-open-action data-m06-action="resolve" data-m06-action-url="<?= $esc($actionBase . '/resolver') ?>" data-m06-current-revision="<?= (int) ($item['current_revision'] ?? 0) ?>" data-m06-workplace="<?= (int) ($item['workplace_id'] ?? 0) ?>"><i class="ti ti-check me-1" aria-hidden="true"></i>Resolver</button></li><?php endif; ?>
                                                    <?php if ($canAuthorize): ?><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m06-incidence-action-modal" data-m06-open-action data-m06-action="authorize" data-m06-action-url="<?= $esc($actionBase . '/autorizar') ?>" data-m06-current-revision="<?= (int) ($item['current_revision'] ?? 0) ?>" data-m06-workplace="<?= (int) ($item['workplace_id'] ?? 0) ?>"><i class="ti ti-shield-check me-1" aria-hidden="true"></i>Autorizar</button></li><?php endif; ?>
                                                    <?php if ($canReject): ?><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m06-incidence-action-modal" data-m06-open-action data-m06-action="reject" data-m06-action-url="<?= $esc($actionBase . '/rechazar') ?>" data-m06-current-revision="<?= (int) ($item['current_revision'] ?? 0) ?>" data-m06-workplace="<?= (int) ($item['workplace_id'] ?? 0) ?>"><i class="ti ti-ban me-1" aria-hidden="true"></i>Rechazar</button></li><?php endif; ?>
                                                    <?php if ($canRevoke): ?><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m06-incidence-action-modal" data-m06-open-action data-m06-action="revoke" data-m06-action-url="<?= $esc($actionBase . '/revocar') ?>" data-m06-current-revision="<?= (int) ($item['current_revision'] ?? 0) ?>" data-m06-workplace="<?= (int) ($item['workplace_id'] ?? 0) ?>"><i class="ti ti-arrow-back-up me-1" aria-hidden="true"></i>Revocar</button></li><?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php foreach ($items as $index => $item):
                        $modalId = 'm06-incidence-detail-' . $index;
                        $category = (string) ($item['category'] ?? '');
                        $status = (string) ($item['current_status'] ?? '');
                        $revisions = is_array($item['revisions'] ?? null) ? $item['revisions'] : [];
                        $uuid = (string) ($item['uuid_incidencia'] ?? '');
                    ?>
                        <div class="modal fade" id="<?= $esc($modalId) ?>" tabindex="-1" aria-labelledby="<?= $esc($modalId) ?>-title" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
                                <div class="modal-header modal-colored-header bg-primary text-white">
                                    <h3 class="modal-title text-white h5" id="<?= $esc($modalId) ?>-title"><i class="ti ti-file-description me-1" aria-hidden="true"></i>Incidencia · <?= $esc($categories[$category] ?? 'Tipo no disponible') ?></h3>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body">
                                    <ul class="nav nav-tabs mb-3" role="tablist">
                                        <li class="nav-item" role="presentation"><button class="nav-link active" id="<?= $esc($modalId) ?>-detail-tab" data-bs-toggle="tab" data-bs-target="#<?= $esc($modalId) ?>-detail" type="button" role="tab" aria-controls="<?= $esc($modalId) ?>-detail" aria-selected="true"><i class="ti ti-eye me-1" aria-hidden="true"></i>Detalle</button></li>
                                        <li class="nav-item" role="presentation"><button class="nav-link" id="<?= $esc($modalId) ?>-history-tab" data-bs-toggle="tab" data-bs-target="#<?= $esc($modalId) ?>-history" type="button" role="tab" aria-controls="<?= $esc($modalId) ?>-history" aria-selected="false"><i class="ti ti-history me-1" aria-hidden="true"></i>Historial (<?= count($revisions) ?>)</button></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div class="tab-pane fade show active" id="<?= $esc($modalId) ?>-detail" role="tabpanel" aria-labelledby="<?= $esc($modalId) ?>-detail-tab" tabindex="0">
                                            <dl class="row mb-0">
                                                <dt class="col-sm-4">Trabajador</dt><dd class="col-sm-8"><?= $esc($item['worker_name'] ?? 'No disponible') ?> · ID <?= (int) ($item['worker_id'] ?? 0) ?></dd>
                                                <dt class="col-sm-4">Localidad</dt><dd class="col-sm-8"><?= $esc($selectedWorkplaceName ?? 'No disponible') ?> · <?= (int) ($item['workplace_id'] ?? 0) ?></dd>
                                                <dt class="col-sm-4">Tipo</dt><dd class="col-sm-8"><?= $esc($categories[$category] ?? 'No disponible') ?></dd>
                                                <dt class="col-sm-4">Periodo</dt><dd class="col-sm-8"><?= $esc($item['start_date'] ?? 'No disponible') ?> — <?= $esc($item['end_date'] ?? 'No disponible') ?></dd>
                                                <dt class="col-sm-4">Estado actual</dt><dd class="col-sm-8"><?= $esc($statusLabel($status)) ?></dd>
                                                <dt class="col-sm-4">Motivo</dt><dd class="col-sm-8 text-break"><?= $esc($item['reason'] ?? 'No disponible') ?></dd>
                                                <dt class="col-sm-4">Evidencia</dt><dd class="col-sm-8 text-break"><?= $esc($item['evidence_reference'] ?? 'No disponible') ?></dd>
                                                <dt class="col-sm-4">Registrada UTC</dt><dd class="col-sm-8"><?= $esc($item['created_at_utc'] ?? 'No disponible') ?></dd>
                                                <dt class="col-sm-4">Referencia</dt><dd class="col-sm-8 text-break"><code><?= $esc($uuid !== '' ? $uuid : 'No disponible') ?></code></dd>
                                            </dl>
                                        </div>
                                        <div class="tab-pane fade" id="<?= $esc($modalId) ?>-history" role="tabpanel" aria-labelledby="<?= $esc($modalId) ?>-history-tab" tabindex="0">
                                            <?php if ($revisions === []): ?>
                                                <p class="text-muted mb-0">El historial de revisiones no está disponible para este registro.</p>
                                            <?php else: ?>
                                                <ol class="list-group list-group-numbered list-group-flush">
                                                    <?php foreach ($revisions as $revision): ?>
                                                        <li class="list-group-item px-0">
                                                            <div class="d-flex flex-wrap justify-content-between gap-2"><strong>Revisión <?= (int) ($revision['revision_number'] ?? 0) ?> · <?= $esc($statusLabel((string) ($revision['status'] ?? ''))) ?></strong><span class="small text-muted"><?= $esc($revision['created_at_utc'] ?? 'Fecha no disponible') ?> UTC</span></div>
                                                            <div class="small mt-1"><strong>Registró:</strong> <?= (int) ($revision['created_by_user_id'] ?? 0) > 0 ? 'Usuario ' . (int) $revision['created_by_user_id'] : 'Actor no disponible' ?></div>
                                                            <div class="small mt-1"><strong>Motivo:</strong> <?= $esc($revision['reason'] ?? 'No disponible') ?></div>
                                                            <div class="small text-muted mt-1"><strong>Evidencia:</strong> <?= $esc(($revision['evidence_reference'] ?? null) ?: 'No disponible') ?></div>
                                                            <div class="small text-muted mt-1"><strong>Referencia:</strong> <code><?= $esc($uuid !== '' ? $uuid : 'No disponible') ?></code></div>
                                                            <?php if (($revision['approved_by_user_id'] ?? null) !== null): ?><div class="small text-muted mt-1">Actor de decisión usuario <?= (int) $revision['approved_by_user_id'] ?></div><?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ol>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cerrar</button></div>
                            </div></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Paginación de incidencias"><ul class="pagination justify-content-end mb-0">
                            <?php foreach (range(1, $totalPages) as $pageNumber): ?>
                                <li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= $esc(http_build_query($queryBase + ['page' => $pageNumber], '', '&', PHP_QUERY_RFC3986)) ?>" <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a></li>
                            <?php endforeach; ?>
                        </ul></nav>
                    <?php endif; ?>
                <?php endif; ?>
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
        <form method="get" action="/departamento-operativo/recursos-humanos/checador/incidencias">
            <div class="modal-body row g-3">
                <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                <div class="col-12"><label class="form-label" for="m06-filter-worker">Trabajador</label><div class="select2-modal-field w-100"><select class="form-select w-100" id="m06-filter-worker" name="worker_id" data-m06-worker-select="filter"><option value="">Todos</option><?php foreach ($workers as $worker): ?><option value="<?= (int) ($worker['worker_id'] ?? 0) ?>" <?= (string) ($filters['worker_id'] ?? '') === (string) ($worker['worker_id'] ?? '') ? 'selected' : '' ?>><?= $esc($worker['full_name'] ?? 'Nombre no disponible') ?> · <?= (int) ($worker['worker_id'] ?? 0) ?></option><?php endforeach; ?></select></div></div>
                <div class="col-12"><label class="form-label" for="m06-filter-category">Tipo de incidencia</label><select class="form-select" id="m06-filter-category" name="category"><option value="">Todos</option><?php foreach ($categories as $code => $label): ?><option value="<?= $esc($code) ?>" <?= ($filters['category'] ?? '') === $code ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-sm-6"><label class="form-label" for="m06-filter-start">Desde</label><input class="form-control" type="date" id="m06-filter-start" name="start_date" value="<?= $esc($filters['start_date'] ?? '') ?>"></div>
                <div class="col-sm-6"><label class="form-label" for="m06-filter-end">Hasta</label><input class="form-control" type="date" id="m06-filter-end" name="end_date" value="<?= $esc($filters['end_date'] ?? '') ?>"></div>
                <div class="col-12"><label class="form-label" for="m06-filter-status">Estado</label><select class="form-select" id="m06-filter-status" name="status"><option value="">Todos</option><?php foreach (['PENDING_APPROVAL' => 'Pendiente de autorización', 'RESOLVED_PENDING_AUTHORIZATION' => 'Resuelta · pendiente de autorización', 'ACTIVE' => 'Activa', 'REVIEW_REQUIRED' => 'Requiere revisión', 'REJECTED' => 'Rechazada', 'CANCELLED' => 'Cancelada', 'REVOKED' => 'Revocada', 'SUPERSEDED' => 'Sustituida'] as $code => $label): ?><option value="<?= $esc($code) ?>" <?= ($filters['status'] ?? '') === $code ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="modal-footer">
                <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0 me-0 me-md-2"><a class="btn btn-outline-secondary" href="/departamento-operativo/recursos-humanos/checador/incidencias?workplace_id=<?= (int) ($selectedWorkplaceId ?? 0) ?>"><i class="ti ti-refresh me-1" aria-hidden="true"></i>Limpiar</a></div>
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0 me-0 me-md-2"><button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button></div>
                    <div class="col-12 col-md-auto d-grid"><button type="submit" class="btn btn-primary"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar</button></div>
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
                <?php foreach (['worker_id' => 'worker_id', 'category' => 'category', 'status' => 'status', 'start_date' => 'start_date', 'end_date' => 'end_date'] as $filterKey => $name): ?><?php if (($filters[$filterKey] ?? null) !== null && ($filters[$filterKey] ?? '') !== ''): ?><input type="hidden" name="filter_<?= $esc($name) ?>" value="<?= $esc($filters[$filterKey]) ?>"><?php endif; ?><?php endforeach; ?>
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

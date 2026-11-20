<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$items = is_array($adjustmentItems ?? null) ? $adjustmentItems : [];
$workers = is_array($adjustmentWorkers ?? null) ? $adjustmentWorkers : [];
$filters = is_array($adjustmentFilters ?? null) ? $adjustmentFilters : [];
$pagination = is_array($adjustmentPagination ?? null) ? $adjustmentPagination : [];
$types = is_array($adjustmentTypes ?? null) ? $adjustmentTypes : [];
$statuses = is_array($adjustmentStatuses ?? null) ? $adjustmentStatuses : [];
$state = (string) ($adjustmentState ?? 'error');
$stateMessage = trim((string) ($adjustmentStateMessage ?? ''));
$page = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(0, (int) ($pagination['total_pages'] ?? 0));
$baseQuery = array_filter([
    'workplace_id' => $selectedWorkplaceId ?? null,
    'start_date' => $filters['start_date'] ?? null,
    'end_date' => $filters['end_date'] ?? null,
    'worker_id' => $filters['worker_id'] ?? null,
    'type' => $filters['type'] ?? null,
    'status' => $filters['status'] ?? null,
], static fn(mixed $value): bool => $value !== null && $value !== '');
$typeLabel = static fn(string $type): string => $types[$type] ?? 'Tipo no reconocido';
$statusLabel = static fn(string $status): string => $statuses[$status] ?? 'Estado no reconocido';
$actorLabel = static fn(mixed $actor): string => (int) $actor > 0 ? (string) (int) $actor : 'No disponible';
?>
<div id="container" class="mt-4 mb-5">
    <section class="card" aria-labelledby="adjustments-workspace-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-adjustments fs-5"></i></span>
            <div><h2 class="h5 mb-0 text-white" id="adjustments-workspace-heading">Ajustes administrativos</h2><?php if (trim((string) ($selectedWorkplaceName ?? '')) !== ''): ?><span class="small text-white-50"><?= $esc($selectedWorkplaceName) ?></span><?php endif; ?></div>
            <div class="dropdown ms-auto">
                <button type="button" class="btn btn-light dropdown-toggle text-dark" id="m07-adjustments-options" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Opciones de ajustes"><i class="ti ti-dots-vertical fs-4" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="m07-adjustments-options">
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#m07-adjustments-search-modal" <?= empty($selectedWorkplaceId) ? 'disabled' : '' ?>><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar ajustes</button></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <?php if ($state === 'selection_required' || $state === 'filter_required'): ?>
                <div class="alert alert-info d-flex align-items-start gap-2 mb-0" role="status"><i class="ti ti-info-circle mt-1" aria-hidden="true"></i><span><?= $esc($stateMessage) ?></span></div>
            <?php elseif ($state !== 'ready'): ?>
                <div class="alert alert-<?= $state === 'denied' ? 'danger' : 'warning' ?> d-flex align-items-start gap-2 mb-0" role="<?= $state === 'denied' ? 'alert' : 'status' ?>"><i class="ti <?= $state === 'denied' ? 'ti-lock' : 'ti-alert-triangle' ?> mt-1" aria-hidden="true"></i><span><?= $esc($stateMessage !== '' ? $stateMessage : 'No fue posible consultar los ajustes persistidos.') ?></span></div>
            <?php elseif ((int) ($adjustmentExcludedUnverified ?? 0) > 0): ?>
                <div class="alert alert-warning d-flex align-items-start gap-2" role="status"><i class="ti ti-alert-triangle mt-1" aria-hidden="true"></i><span>Hay <?= (int) $adjustmentExcludedUnverified ?> registros con relaciones, historial o adscripción no verificables; no se presentan como ajustes confirmados.</span></div>
                <?php if ($items === []): ?><p class="text-body-secondary text-center py-3 mb-0" role="status"><i class="ti ti-info-circle text-primary me-1" aria-hidden="true"></i>No hay ajustes verificables para los filtros seleccionados.</p><?php endif; ?>
            <?php elseif ($items === []): ?>
                <p class="text-body-secondary text-center py-3 mb-0" role="status"><i class="ti ti-info-circle text-primary me-1" aria-hidden="true"></i>No hay ajustes registrados para los filtros seleccionados.</p>
            <?php endif; ?>

            <?php if ($state === 'ready' && $items !== []): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle w-100" id="m07-adjustments-table" data-checador-adjustments>
                        <caption class="visually-hidden">Ajustes persistidos de <?= $esc($selectedWorkplaceName ?? 'la localidad autorizada') ?></caption>
                        <thead><tr><th scope="col">Trabajador</th><th scope="col">Fecha laboral</th><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">Revisión</th><th scope="col">Acciones</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $group):
                            $current = is_array($group['current'] ?? null) ? $group['current'] : [];
                            $revisions = is_array($group['revisions'] ?? null) ? $group['revisions'] : [];
                            $modalId = 'm07-adjustment-' . (int) ($current['id'] ?? 0);
                            $adjustmentWorker = trim((string) ($current['worker_name'] ?? '')) ?: 'Nombre no disponible';
                        ?>
                            <tr>
                                <th scope="row"><?= $esc($adjustmentWorker) ?><?php if (!empty($current['employee_number'])): ?><span class="d-block small text-body-secondary"><?= $esc($current['employee_number']) ?></span><?php endif; ?></th>
                                <td><?= $esc($current['work_date'] ?? 'No disponible') ?></td>
                                <td><?= $esc($typeLabel((string) ($current['type'] ?? ''))) ?></td>
                                <td><?= $esc($statusLabel((string) ($current['status'] ?? ''))) ?></td>
                                <td><?= (int) ($current['revision'] ?? 0) ?></td>
                                <td><div class="dropdown">
                                    <button type="button" class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones para <?= $esc($adjustmentWorker) ?>"><i class="ti ti-dots-vertical" aria-hidden="true"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#<?= $esc($modalId) ?>" data-adjustment-tab="detail"><i class="ti ti-eye me-1" aria-hidden="true"></i>Ver detalle</button></li>
                                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#<?= $esc($modalId) ?>" data-adjustment-tab="history"><i class="ti ti-history me-1" aria-hidden="true"></i>Ver historial</button></li>
                                    </ul>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?><nav aria-label="Paginación de ajustes"><ul class="pagination justify-content-end mt-3 mb-0"><?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): $href = '?' . http_build_query($baseQuery + ['page' => $pageNumber], '', '&', PHP_QUERY_RFC3986); ?><li class="page-item <?= $pageNumber === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $esc($href) ?>" <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="modal fade" id="m07-adjustments-search-modal" tabindex="-1" aria-labelledby="m07-adjustments-search-title" aria-hidden="true" data-m07-adjustment-search-modal>
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="get" action="/departamento-operativo/recursos-humanos/checador/ajustes" data-m07-adjustment-filter-form>
            <div class="modal-header bg-primary text-white"><h3 class="modal-title h5 text-white" id="m07-adjustments-search-title"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar ajustes</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p class="small text-body-secondary">Consulta los ajustes persistidos para la localidad autorizada. El periodo admite hasta 31 fechas laborales.</p>
                <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                <div class="mb-3"><label for="m07-adjustment-worker" class="form-label">Trabajador</label><div class="select2-modal-field w-100"><select id="m07-adjustment-worker" name="worker_id" class="form-select w-100" data-m07-worker-select><option value="">Todos</option><?php foreach ($workers as $worker): if (!is_array($worker)) continue; $id = (int) ($worker['worker_id'] ?? 0); $name = trim((string) ($worker['full_name'] ?? '')); if ($id < 1 || $name === '') continue; $number = trim((string) ($worker['employee_number'] ?? '')); ?><option value="<?= $id ?>" <?= $id === (int) ($filters['worker_id'] ?? 0) ? 'selected' : '' ?>><?= $esc($name . ($number !== '' && $number !== '0' ? ' · ' . $number : '')) ?></option><?php endforeach; ?></select></div><?php if (trim((string) ($adjustmentWorkerOptionsMessage ?? '')) !== ''): ?><div class="form-text"><?= $esc($adjustmentWorkerOptionsMessage) ?></div><?php endif; ?></div>
                <div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label" for="m07-adjustment-start">Fecha inicial</label><input class="form-control" type="date" id="m07-adjustment-start" name="start_date" value="<?= $esc($filters['start_date'] ?? '') ?>" required></div><div class="col-md-6"><label class="form-label" for="m07-adjustment-end">Fecha final</label><input class="form-control" type="date" id="m07-adjustment-end" name="end_date" value="<?= $esc($filters['end_date'] ?? '') ?>" required></div></div>
                <div class="row g-3"><div class="col-md-6"><label class="form-label" for="m07-adjustment-type">Tipo</label><select class="form-select" id="m07-adjustment-type" name="type"><option value="">Todos</option><?php foreach ($types as $value => $label): ?><option value="<?= $esc($value) ?>" <?= ($filters['type'] ?? '') === $value ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label" for="m07-adjustment-status">Estado</label><select class="form-select" id="m07-adjustment-status" name="status"><option value="">Todos</option><?php foreach ($statuses as $value => $label): ?><option value="<?= $esc($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div></div>
            </div>
            <div class="modal-footer"><button type="reset" class="btn btn-light"><i class="ti ti-eraser me-1" aria-hidden="true"></i>Limpiar</button><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button><button type="submit" class="btn btn-primary"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar</button></div>
        </form>
    </div></div>
</div>

<?php foreach ($items as $group):
    $current = is_array($group['current'] ?? null) ? $group['current'] : [];
    $revisions = is_array($group['revisions'] ?? null) ? $group['revisions'] : [];
    $modalId = 'm07-adjustment-' . (int) ($current['id'] ?? 0);
    $detailTabId = $modalId . '-detail-tab';
    $historyTabId = $modalId . '-history-tab';
?>
<div class="modal fade" id="<?= $esc($modalId) ?>" tabindex="-1" aria-labelledby="<?= $esc($modalId) ?>-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
        <div class="modal-header bg-primary text-white"><h3 class="modal-title h5 text-white" id="<?= $esc($modalId) ?>-title"><i class="ti ti-adjustments me-1" aria-hidden="true"></i>Ajuste administrativo · <?= $esc($current['worker_name'] ?? 'Nombre no disponible') ?></h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <ul class="nav nav-tabs mb-3" role="tablist"><li class="nav-item" role="presentation"><button class="nav-link active" id="<?= $esc($detailTabId) ?>" data-bs-toggle="tab" data-bs-target="#<?= $esc($modalId) ?>-detail" type="button" role="tab" aria-controls="<?= $esc($modalId) ?>-detail" aria-selected="true">Detalle</button></li><li class="nav-item" role="presentation"><button class="nav-link" id="<?= $esc($historyTabId) ?>" data-bs-toggle="tab" data-bs-target="#<?= $esc($modalId) ?>-history" type="button" role="tab" aria-controls="<?= $esc($modalId) ?>-history" aria-selected="false">Historial (<?= count($revisions) ?>)</button></li></ul>
            <div class="tab-content"><section class="tab-pane fade show active" id="<?= $esc($modalId) ?>-detail" role="tabpanel" aria-labelledby="<?= $esc($detailTabId) ?>" tabindex="0">
                <div class="row g-3"><div class="col-sm-6"><strong>Trabajador</strong><div><?= $esc($current['worker_name'] ?? 'No disponible') ?><?php if (!empty($current['employee_number'])): ?> · <?= $esc($current['employee_number']) ?><?php endif; ?></div></div><div class="col-sm-6"><strong>Localidad</strong><div><?= $esc($current['workplace_name'] ?? 'No disponible') ?></div></div><div class="col-sm-6"><strong>Fecha laboral</strong><div><?= $esc($current['work_date'] ?? 'No disponible') ?></div></div><div class="col-sm-6"><strong>Tipo y estado</strong><div><?= $esc($typeLabel((string) ($current['type'] ?? ''))) ?> · <?= $esc($statusLabel((string) ($current['status'] ?? ''))) ?></div></div><div class="col-sm-6"><strong>Revisión actual</strong><div><?= (int) ($current['revision'] ?? 0) ?></div></div><div class="col-sm-6"><strong>Registro (UTC)</strong><div><?= $esc($current['created_at_utc'] ?? 'No disponible') ?></div></div><div class="col-sm-6"><strong>Hora interpretada de entrada (UTC)</strong><div><?= $esc($current['entry_utc'] ?? 'No disponible') ?></div></div><div class="col-sm-6"><strong>Hora interpretada de salida (UTC)</strong><div><?= $esc($current['exit_utc'] ?? 'No disponible') ?></div></div><div class="col-12"><strong>Motivo</strong><p class="mb-0 text-break"><?= $esc($current['reason'] ?? 'No disponible') ?></p></div><div class="col-sm-6"><strong>Referencia del ajuste</strong><div class="text-break"><code><?= $esc($current['uuid'] ?? 'No disponible') ?></code></div></div><div class="col-sm-6"><strong>Referencia lógica</strong><div class="text-break"><code><?= $esc($current['logical_uuid'] ?? 'No disponible') ?></code></div></div><div class="col-sm-6"><strong>Jornada persistida</strong><div><?= $actorLabel($current['journey_id'] ?? null) ?></div></div><div class="col-sm-6"><strong>Cálculo P2 asociado</strong><div><?= $actorLabel($current['calculation_id'] ?? null) ?></div></div><div class="col-sm-6"><strong>Actor creador</strong><div><?= $actorLabel($current['created_by_user_id'] ?? null) ?></div></div><div class="col-sm-6"><strong>Actor aprobador persistido</strong><div><?= $actorLabel($current['approved_by_user_id'] ?? null) ?></div></div></div>
                <?php if (($current['evaluation_link_integrity'] ?? 'verified') === 'unverified'): ?><div class="alert alert-warning mt-3 mb-0" role="status">Existe una relación con evaluación que no coincide con las claves del ajuste; no se presenta como vínculo confirmado.</div><?php endif; ?>
                <?php $currentEvaluations = is_array($current['evaluation_links'] ?? null) ? $current['evaluation_links'] : []; if ($currentEvaluations !== []): ?><hr><h4 class="h6">Evaluaciones relacionadas</h4><div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr><th>Revisión</th><th>Estado</th><th>Revisión humana</th><th>Fecha UTC</th></tr></thead><tbody><?php foreach ($currentEvaluations as $evaluation): ?><tr><td><?= (int) ($evaluation['revision'] ?? 0) ?></td><td><?= $esc($evaluation['status'] ?? 'No disponible') ?></td><td><?= $esc($evaluation['human_review_status'] ?? 'No disponible') ?></td><td><?= $esc($evaluation['created_at_utc'] ?? 'No disponible') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </section><section class="tab-pane fade" id="<?= $esc($modalId) ?>-history" role="tabpanel" aria-labelledby="<?= $esc($historyTabId) ?>" tabindex="0">
                <?php if ($revisions === []): ?><p class="text-body-secondary">No hay revisiones persistidas disponibles.</p><?php else: ?><div class="table-responsive"><table class="table table-striped table-bordered align-middle"><thead><tr><th>Revisión</th><th>Estado</th><th>Fecha UTC</th><th>Actor creador</th><th>Actor aprobador</th><th>Motivo</th><th>Tipo</th><th>Evaluaciones vinculadas</th></tr></thead><tbody><?php foreach ($revisions as $revision): $evals = is_array($revision['evaluation_links'] ?? null) ? $revision['evaluation_links'] : []; ?><tr><td><?= (int) ($revision['revision'] ?? 0) ?></td><td><?= $esc($statusLabel((string) ($revision['status'] ?? ''))) ?></td><td><?= $esc($revision['created_at_utc'] ?? 'No disponible') ?></td><td><?= $esc($actorLabel($revision['created_by_user_id'] ?? null)) ?></td><td><?= $esc($actorLabel($revision['approved_by_user_id'] ?? null)) ?></td><td class="text-break"><?= $esc($revision['reason'] ?? 'No disponible') ?></td><td><?= $esc($typeLabel((string) ($revision['type'] ?? ''))) ?></td><td><?php if (($revision['evaluation_link_integrity'] ?? '') === 'unverified'): ?>Vínculo no verificable<?php elseif ($evals === []): ?>Sin vínculo confirmado<?php else: ?><?= implode(', ', array_map(static fn(array $evaluation): string => 'R' . (int) ($evaluation['revision'] ?? 0) . ' · ' . (string) ($evaluation['status'] ?? ''), $evals)) ?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
            </section></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cerrar</button></div>
    </div></div>
</div>
<?php endforeach; ?>

<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$incidencesByWorker = is_array($administrativeIncidences ?? null) ? $administrativeIncidences : [];
$readErrors = is_array($administrativeErrors ?? null) ? $administrativeErrors : [];
$categoryLabel = static fn(string $category): string => match ($category) {
    'VACATION' => 'Vacaciones', 'PERMISSION' => 'Permiso', 'INCAPACITY' => 'Incapacidad', 'JUSTIFICATION' => 'Justificación', default => 'Tipo no disponible',
};
?>
<div id="container" class="mt-4 mb-5">
    <?php require __DIR__ . '/_workspace-context.php'; ?>
    <section class="card" aria-labelledby="incidence-workspace-heading">
        <div class="card-header"><h2 class="h5 mb-0" id="incidence-workspace-heading">Incidencias e historial</h2></div>
        <div class="card-body">
            <p class="text-muted">Consulta los registros y su historial. Las acciones disponibles dependen de los permisos de este perfil y se confirman mediante la lectura autorizada.</p>
            <?php if (($demoMode ?? false) === true): ?>
                <div class="alert alert-info" role="status">Demostración sintética: estas acciones sólo cambian el historial local. No se modifica información operativa real.</div>
            <?php endif; ?>
            <?php if (is_array($lifecycleFeedback ?? null)): ?>
                <div class="alert alert-<?= ($lifecycleFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
                    <?= $esc($lifecycleFeedback['message'] ?? 'No fue posible confirmar la acción.') ?>
                </div>
            <?php endif; ?>
            <?php if (($state ?? '') !== 'success'): ?><div class="alert alert-warning" role="status"><?= $esc(($stateMessage ?? '') !== '' ? $stateMessage : 'La consulta estable no está disponible; no se confirma un resultado vacío.') ?></div><?php endif; ?>
            <?php if (($state ?? '') === 'success' && $readErrors === [] && $incidencesByWorker === []): ?>
                <div class="alert alert-info" role="status">No hay incidencias dentro del conjunto consultado, o no se observó un trabajador elegible para esta lectura. No se infieren faltas.</div>
            <?php endif; ?>
            <?php foreach ($readErrors as $workerId => $kind): ?>
                <div class="alert alert-warning" role="status">La lectura de incidencias de personal <?= (int) $workerId ?> no está disponible. Una respuesta fallida no confirma que no existan registros.</div>
            <?php endforeach; ?>
            <?php foreach ($incidencesByWorker as $workerId => $incidences): ?>
                <h3 class="h6 mt-3">Personal <?= (int) $workerId ?> · <?= $esc($selectedWorkplaceName ?? 'Centro autorizado') ?> · <?= $esc($selectedDate ?? '') ?></h3>
                <?php
                $createFeedback = is_array($incidenceCreateFeedback ?? null)
                    && (int) ($incidenceCreateFeedback['worker_id'] ?? 0) === (int) $workerId
                    ? $incidenceCreateFeedback
                    : null;
                $createValues = is_array($createFeedback['form_values'] ?? null) ? $createFeedback['form_values'] : [];
                ?>
                <?php if (($incidenceCreateAllowed ?? false) === true): ?>
                    <details class="card card-body mb-3">
                        <summary class="fw-semibold">Registrar una incidencia</summary>
                        <p class="small text-muted mt-2">El alta se envía a la fuente autorizada y sólo se confirma después de volver a leer el registro.</p>
                        <?php if ($createFeedback !== null): ?>
                            <div class="alert alert-<?= ($createFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?> py-2" role="status">
                                <?= $esc($createFeedback['message'] ?? 'No fue posible confirmar el alta.') ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/crear" class="row g-3">
                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                            <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                            <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                            <input type="hidden" name="return_date" value="<?= $esc($selectedDate ?? '') ?>">
                            <input type="hidden" name="snapshot_ref" value="<?= $esc($snapshotRef ?? '') ?>">
                            <input type="hidden" name="idempotency_key" value="<?= $esc(($createFeedback['retry_idempotency_key'] ?? '') ?: ($incidenceCreateKeys[$workerId] ?? '')) ?>">
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="incidence-category-<?= (int) $workerId ?>">Tipo</label>
                                <select class="form-select" id="incidence-category-<?= (int) $workerId ?>" name="category" required>
                                    <option value="">Selecciona</option>
                                    <?php foreach (['VACATION' => 'Vacaciones', 'PERMISSION' => 'Permiso', 'INCAPACITY' => 'Incapacidad', 'JUSTIFICATION' => 'Justificación'] as $code => $label): ?>
                                        <option value="<?= $esc($code) ?>" <?= ($createValues['category'] ?? '') === $code ? 'selected' : '' ?>><?= $esc($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="incidence-start-<?= (int) $workerId ?>">Desde</label>
                                <input class="form-control" type="date" id="incidence-start-<?= (int) $workerId ?>" name="start_date" value="<?= $esc($createValues['start_date'] ?? '') ?>" required>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="incidence-end-<?= (int) $workerId ?>">Hasta</label>
                                <input class="form-control" type="date" id="incidence-end-<?= (int) $workerId ?>" name="end_date" value="<?= $esc($createValues['end_date'] ?? '') ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="incidence-reason-<?= (int) $workerId ?>">Motivo</label>
                                <textarea class="form-control" id="incidence-reason-<?= (int) $workerId ?>" name="reason" required><?= $esc($createValues['reason'] ?? '') ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="incidence-evidence-<?= (int) $workerId ?>">Referencia de evidencia (opcional)</label>
                                <input class="form-control" id="incidence-evidence-<?= (int) $workerId ?>" name="evidence_reference" value="<?= $esc($createValues['evidence_reference'] ?? '') ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="confirm_create" value="1" required> Confirmo enviar esta incidencia para el trabajador y periodo indicados.</label>
                            </div>
                            <div class="col-12"><button class="btn btn-primary" type="submit">Enviar incidencia</button></div>
                        </form>
                    </details>
                <?php endif; ?>
                <?php if ($incidences === []): ?><p class="text-muted">La consulta no devolvió incidencias para este trabajador y fecha.</p><?php else: ?>
                    <div class="table-responsive"><table class="table table-striped table-bordered align-middle">
                        <caption class="visually-hidden">Incidencias y estado reportado para personal <?= (int) $workerId ?></caption>
                        <thead><tr><th scope="col">Tipo</th><th scope="col">Periodo</th><th scope="col">Estado</th><th scope="col">Revisión</th><th scope="col">Detalle e historial</th></tr></thead><tbody>
                        <?php foreach ($incidences as $incidenceIndex => $incidence):
                            $revisions = is_array($incidence['revisions'] ?? null) ? $incidence['revisions'] : [];
                            $latest = $revisions === [] ? [] : $revisions[array_key_last($revisions)];
                            $incidenceModalId = 'incidence-history-' . (int) $workerId . '-' . (int) $incidenceIndex;
                        ?>
                            <tr>
                                <th scope="row"><?= $esc($categoryLabel((string) ($incidence['category'] ?? ''))) ?></th>
                                <td><?= $esc($incidence['date_range']['start_date'] ?? 'No disponible') ?> — <?= $esc($incidence['date_range']['end_date'] ?? 'No disponible') ?></td>
                                <td><?= $esc(\App\Services\ChecadorPresentationFormatter::incidenceStatusLabel($incidence['current_status'] ?? null)) ?></td>
                                <td><?= (int) ($incidence['current_revision'] ?? 0) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= $esc($incidenceModalId) ?>">Ver detalle</button>
                                    <div class="modal fade" id="<?= $esc($incidenceModalId) ?>" tabindex="-1" aria-labelledby="<?= $esc($incidenceModalId) ?>-title" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
                                            <div class="modal-header"><h4 class="modal-title h5" id="<?= $esc($incidenceModalId) ?>-title">Registro e historial · <?= $esc($categoryLabel((string) ($incidence['category'] ?? ''))) ?></h4><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar detalle de incidencia"></button></div>
                                            <div class="modal-body">
                                    <dl class="row small mb-2"><dt class="col-sm-4">Referencia</dt><dd class="col-sm-8 text-break"><code><?= $esc($incidence['uuid_incidencia'] ?? 'No disponible') ?></code></dd>
                                        <dt class="col-sm-4">Motivo vigente</dt><dd class="col-sm-8"><?= $esc($incidence['summary_reason'] ?? $latest['reason'] ?? 'No disponible') ?></dd>
                                        <dt class="col-sm-4">Evidencia</dt><dd class="col-sm-8 text-break"><?= $esc($incidence['summary_evidence_reference'] ?? $latest['evidence_reference'] ?? 'No disponible') ?></dd>
                                    </dl>
                                    <?php if ($revisions === []): ?><p class="small text-muted">Historial de revisiones no disponible.</p><?php else: ?><ol class="small mb-0">
                                        <?php foreach ($revisions as $revision): ?><li class="mb-2"><strong>Revisión <?= (int) ($revision['revision_number'] ?? 0) ?> · <?= $esc(\App\Services\ChecadorPresentationFormatter::incidenceStatusLabel($revision['status'] ?? null)) ?></strong><br><?= $esc($revision['reason'] ?? 'Sin motivo disponible') ?><br>Actor <?= (int) ($revision['created_by_user_id'] ?? 0) ?> · <?= $esc($revision['created_at_utc'] ?? 'Fecha no disponible') ?> UTC<?php if (($revision['evidence_reference'] ?? null) !== null): ?><br>Evidencia: <?= $esc($revision['evidence_reference']) ?><?php endif; ?></li><?php endforeach; ?>
                                    </ol><?php endif; ?>
                                    <?php
                                    $incidenceUuid = (string) ($incidence['uuid_incidencia'] ?? '');
                                    $incidenceStatus = (string) ($incidence['current_status'] ?? 'UNKNOWN');
                                    $incidenceRevision = (int) ($incidence['current_revision'] ?? 0);
                                    $incidenceLifecycleKey = $lifecycleKeys[$incidenceUuid] ?? [];
                                    $canResolve = !empty($lifecyclePermissions['resolve']) && !in_array($incidenceStatus, ['CANCELLED', 'REVOKED'], true);
                                    $canAuthorize = !empty($lifecyclePermissions['authorize']) && !in_array($incidenceStatus, ['CANCELLED', 'REVOKED', 'REJECTED'], true);
                                    $canReject = !empty($lifecyclePermissions['reject']) && !in_array($incidenceStatus, ['CANCELLED', 'REVOKED', 'REJECTED'], true);
                                    $canRevoke = !empty($lifecyclePermissions['revoke']) && !in_array($incidenceStatus, ['CANCELLED', 'REVOKED'], true);
                                    ?>
                                    <?php if ($canResolve || $canAuthorize || $canReject || $canRevoke): ?>
                                        <div class="border-top mt-3 pt-3" aria-label="Acciones administrativas">
                                            <p class="small text-muted mb-2">Cada acción requiere confirmación y conserva la revisión esperada. El estado se vuelve a leer después del envío.</p>
                                            <?php if ($canResolve): ?>
                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/<?= rawurlencode($incidenceUuid) ?>/resolve" class="border rounded p-2 mb-2">
                                                    <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                    <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                                    <input type="hidden" name="snapshot_ref" value="<?= $esc($snapshotRef ?? '') ?>">
                                                    <input type="hidden" name="expected_current_revision" value="<?= $incidenceRevision ?>">
                                                    <input type="hidden" name="idempotency_key" value="<?= $esc($incidenceLifecycleKey['resolve'] ?? '') ?>">
                                                    <input type="hidden" name="resolution_action" value="CONFIRM_INCIDENCE_PREVALENCE">
                                                    <label class="form-label small" for="resolve-notes-<?= $esc($incidenceUuid) ?>">Resolver o revisar (motivo)</label>
                                                    <textarea class="form-control form-control-sm mb-2" id="resolve-notes-<?= $esc($incidenceUuid) ?>" name="resolution_notes" required></textarea>
                                                    <label class="form-label small" for="resolve-evidence-<?= $esc($incidenceUuid) ?>">Evidencia (opcional)</label>
                                                    <input class="form-control form-control-sm mb-2" id="resolve-evidence-<?= $esc($incidenceUuid) ?>" name="evidence_reference">
                                                    <label class="small d-block mb-2"><input class="form-check-input me-1" type="checkbox" name="confirm_action" value="1" required> Confirmo esta revisión administrativa.</label>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Resolver o revisar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($canAuthorize): ?>
                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/<?= rawurlencode($incidenceUuid) ?>/authorize" class="border rounded p-2 mb-2">
                                                    <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                    <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                                    <input type="hidden" name="snapshot_ref" value="<?= $esc($snapshotRef ?? '') ?>">
                                                    <input type="hidden" name="expected_current_revision" value="<?= $incidenceRevision ?>">
                                                    <input type="hidden" name="idempotency_key" value="<?= $esc($incidenceLifecycleKey['authorize'] ?? '') ?>">
                                                    <label class="form-label small" for="authorize-notes-<?= $esc($incidenceUuid) ?>">Nota de autorización (opcional)</label>
                                                    <textarea class="form-control form-control-sm mb-2" id="authorize-notes-<?= $esc($incidenceUuid) ?>" name="action_notes"></textarea>
                                                    <label class="small d-block mb-2"><input class="form-check-input me-1" type="checkbox" name="confirm_action" value="1" required> Confirmo la autorización explícita.</label>
                                                    <button type="submit" class="btn btn-sm btn-outline-success">Autorizar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($canReject): ?>
                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/<?= rawurlencode($incidenceUuid) ?>/reject" class="border rounded p-2 mb-2">
                                                    <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                    <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                                    <input type="hidden" name="snapshot_ref" value="<?= $esc($snapshotRef ?? '') ?>">
                                                    <input type="hidden" name="expected_current_revision" value="<?= $incidenceRevision ?>">
                                                    <input type="hidden" name="idempotency_key" value="<?= $esc($incidenceLifecycleKey['reject'] ?? '') ?>">
                                                    <label class="form-label small" for="reject-reason-<?= $esc($incidenceUuid) ?>">Motivo del rechazo</label>
                                                    <textarea class="form-control form-control-sm mb-2" id="reject-reason-<?= $esc($incidenceUuid) ?>" name="rejection_reason" required></textarea>
                                                    <label class="small d-block mb-2"><input class="form-check-input me-1" type="checkbox" name="confirm_action" value="1" required> Confirmo el rechazo explícito.</label>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Rechazar</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($canRevoke): ?>
                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/<?= rawurlencode($incidenceUuid) ?>/revoke" class="border rounded p-2">
                                                    <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                    <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                                    <input type="hidden" name="snapshot_ref" value="<?= $esc($snapshotRef ?? '') ?>">
                                                    <input type="hidden" name="expected_current_revision" value="<?= $incidenceRevision ?>">
                                                    <input type="hidden" name="idempotency_key" value="<?= $esc($incidenceLifecycleKey['revoke'] ?? '') ?>">
                                                    <label class="form-label small" for="revoke-reason-<?= $esc($incidenceUuid) ?>">Motivo de revocación o cancelación</label>
                                                    <textarea class="form-control form-control-sm mb-2" id="revoke-reason-<?= $esc($incidenceUuid) ?>" name="revocation_reason" required></textarea>
                                                    <label class="form-label small" for="revoke-evidence-<?= $esc($incidenceUuid) ?>">Evidencia (opcional)</label>
                                                    <input class="form-control form-control-sm mb-2" id="revoke-evidence-<?= $esc($incidenceUuid) ?>" name="evidence_reference">
                                                    <label class="small d-block mb-2"><input class="form-check-input me-1" type="checkbox" name="confirm_action" value="1" required> Confirmo la revocación explícita.</label>
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">Revocar o cancelar</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                            </div>
                                            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
                                        </div></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
</div>

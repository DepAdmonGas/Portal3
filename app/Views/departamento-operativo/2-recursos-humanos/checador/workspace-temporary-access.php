<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$panel = is_array($temporaryAccessPanel ?? null) ? $temporaryAccessPanel : [];
$requests = is_array($panel['requests'] ?? null) ? $panel['requests'] : [];
$canEdit = ($panel['can_edit'] ?? false) === true
    && ($demoMode ?? false) !== true
    && ($realApiPostOperationsEnabled ?? false) === true;
$cancelableUuid = strtolower((string) ($panel['cancelable_uuid'] ?? ''));
?>
<div id="container" class="mt-4 mb-5">
    <?php require __DIR__ . '/_workspace-context.php'; ?>
    <section class="card" aria-labelledby="temporary-access-workspace-heading">
        <div class="card-header"><h2 class="h5 mb-0" id="temporary-access-workspace-heading">Solicitudes de acceso temporal</h2></div>
        <div class="card-body">
            <p class="text-muted">Cola de supervisión para el centro físico autorizado. El PIN no se consulta ni se vuelve a mostrar en esta lista.</p>
            <?php if (is_array($temporaryAccessFeedback ?? null)): ?>
                <div class="alert alert-<?= ($temporaryAccessFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
                    <?= $esc($temporaryAccessFeedback['message'] ?? 'La operación de supervisión terminó.') ?>
                    <?php if (($canViewTemporaryPin ?? false) === true
                        && isset($temporaryAccessFeedback['temporary_pin'])
                        && preg_match('/\A[0-9]{6}\z/', (string) $temporaryAccessFeedback['temporary_pin'])): ?>
                        <p class="small mb-1 mt-2">Código de un solo uso:</p>
                        <p class="h3 mb-1"><code><?= $esc($temporaryAccessFeedback['temporary_pin']) ?></code></p>
                        <p class="small mb-0">Vence: <?= $esc($temporaryAccessFeedback['pin_expires_at_utc'] ?? 'No disponible') ?> UTC. Este código no se volverá a mostrar después de cerrar este mensaje.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (($panel['state'] ?? '') === 'demo_blocked'): ?>
                <div class="alert alert-info" role="status"><?= $esc($panel['message'] ?? 'La cola no está disponible en la demostración.') ?></div>
            <?php elseif (($panel['state'] ?? '') === 'error'): ?>
                <div class="alert alert-warning" role="status"><?= $esc($panel['message'] ?? 'La lectura de solicitudes no está disponible; no se confirma que la cola esté vacía.') ?></div>
            <?php elseif (($panel['state'] ?? '') === 'success' && $requests === []): ?>
                <div class="alert alert-secondary" role="status">La consulta de la cola terminó sin solicitudes visibles para este centro.</div>
            <?php elseif ($requests !== []): ?>
                <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><caption class="visually-hidden">Solicitudes de acceso temporal devueltas para el centro autorizado</caption><thead><tr><th scope="col">Trabajador</th><th scope="col">Dispositivo</th><th scope="col">Motivo</th><th scope="col">Estado informado</th><th scope="col">Solicitud UTC</th><th scope="col">Vencimiento UTC</th><th scope="col">Referencia</th><th scope="col">Acciones</th></tr></thead><tbody>
                    <?php foreach ($requests as $request): $requestUuid = strtolower((string) ($request['request_uuid'] ?? '')); ?><tr>
                        <th scope="row"><?= $esc($request['nombre_trabajador'] ?? 'No disponible') ?><div class="small text-muted">ID <?= (int) ($request['id_personal_resuelto'] ?? 0) ?></div></th>
                        <td><?= $esc($request['dispositivo_nombre'] ?? 'No disponible') ?><div class="small text-muted">Centro <?= (int) ($request['id_estacion'] ?? 0) ?></div></td>
                        <td><?= $esc($request['reason'] ?? 'No disponible') ?></td>
                        <td>No incluido en esta lectura</td>
                        <td><?= $esc($request['requested_at_utc'] ?? 'No disponible') ?></td>
                        <td><?= $esc($request['request_expires_at_utc'] ?? 'No disponible') ?></td>
                        <td class="text-break"><code><?= $esc($request['request_uuid'] ?? 'No disponible') ?></code></td>
                        <td>
                            <?php if ($canEdit && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $requestUuid)): ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach (['authorize' => 'Autorizar', 'reject' => 'Rechazar'] as $action => $label): ?>
                                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/<?= rawurlencode($requestUuid) ?>/<?= $action ?>">
                                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                            <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                            <input type="hidden" name="worker_id" value="<?= (int) ($request['id_personal_resuelto'] ?? 0) ?>">
                                            <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                            <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo <?= strtolower($label) ?> esta solicitud.</label>
                                            <button type="submit" class="btn btn-sm btn-outline-<?= $action === 'authorize' ? 'success' : 'danger' ?>"><?= $esc($label) ?></button>
                                        </form>
                                    <?php endforeach; ?>
                                    <?php if ($cancelableUuid !== '' && hash_equals($cancelableUuid, $requestUuid)): ?>
                                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/<?= rawurlencode($requestUuid) ?>/cancel">
                                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                            <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                            <input type="hidden" name="worker_id" value="<?= (int) ($request['id_personal_resuelto'] ?? 0) ?>">
                                            <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                            <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo cancelar esta autorización temporal.</label>
                                            <button type="submit" class="btn btn-sm btn-outline-warning">Cancelar autorización</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?><span class="small text-muted">No disponible para este perfil o ambiente.</span><?php endif; ?>
                        </td>
                    </tr><?php endforeach; ?>
                </tbody></table></div>
            <?php else: ?>
                <div class="alert alert-secondary" role="status">La consulta no está disponible en este momento.</div>
            <?php endif; ?>
            <p class="small text-muted mt-3 mb-0">Las acciones de supervisión requieren lectura y edición independientes, confirmación explícita y un ambiente habilitado. La consulta no presenta el PIN ya emitido.</p>
        </div>
    </section>
</div>

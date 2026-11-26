<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$panel = is_array($temporaryAccessPanel ?? null) ? $temporaryAccessPanel : [];
$requests = is_array($panel['requests'] ?? null) ? $panel['requests'] : [];
$canEdit = ($panel['can_edit'] ?? false) === true
    && ($demoMode ?? false) !== true
    && ($temporaryAccessPostOperationsEnabled ?? false) === true;
$cancelableUuid = strtolower((string) ($panel['cancelable_uuid'] ?? ''));
$selectedWorkplace = (int) ($selectedWorkplaceId ?? 0);
?>
<div id="container" class="mt-4 mb-5">
    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="/departamento-operativo/recursos-humanos/checador"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Volver al Checador</a>
    </div>
    <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
        <div class="col">
            <section class="card bg-body border rounded-3 shadow-sm card-hover h-100 mb-0" aria-label="PIN de configuración del Lector">
                <div class="card-body p-3 p-lg-4">
                    <?php $administrativePinReturnTo = 'acceso-temporal'; require __DIR__ . '/workspace-administrative-pin.php'; ?>
                </div>
            </section>
        </div>
        <div class="col">
            <section class="card bg-body border rounded-3 shadow-sm card-hover h-100 mb-0" aria-labelledby="personnel-pin-title">
                <div class="card-body d-flex align-items-center gap-3 p-3 p-lg-4">
                    <span class="rounded-3 bg-primary-subtle text-primary p-3 d-inline-flex align-items-center justify-content-center flex-shrink-0"><i class="ti ti-key fs-9" aria-hidden="true"></i></span>
                    <div class="d-flex flex-column flex-grow-1 h-100">
                        <h2 class="card-title fs-6 fw-bolder mb-2" id="personnel-pin-title">PIN de acceso del personal</h2>
                        <p class="text-body-secondary mb-3">Autoriza marcaciones temporales cuando un trabajador no puede utilizar su huella.</p>
                        <ul class="list-unstyled d-flex flex-wrap gap-2 mb-3"><li><span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2 py-1">15 minutos</span></li><li><span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2 py-1">Una marcación</span></li></ul>
                        <?php if (($panel['state'] ?? '') === 'success'): ?>
                            <p class="mb-3">Solicitudes pendientes: <strong><?= count($requests) ?></strong></p>
                        <?php else: ?>
                            <p class="text-body-secondary mb-3">Solicitudes pendientes: no disponibles</p>
                        <?php endif; ?>
                        <div class="mt-auto"><a class="btn btn-primary" href="#temporary-access-requests"><i class="ti ti-list-details me-1" aria-hidden="true"></i>Administrar solicitudes</a></div>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <section class="card" id="temporary-access-requests" aria-labelledby="temporary-access-workspace-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-key fs-5"></i></span>
            <div><h2 class="h5 mb-0 text-white" id="temporary-access-workspace-heading">Solicitudes de acceso temporal</h2><?php if (trim((string) ($selectedWorkplaceName ?? '')) !== ''): ?><span class="small text-white-50"><?= $esc($selectedWorkplaceName) ?></span><?php endif; ?></div>
        </div>
        <div class="card-body">
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
                <p class="text-body-secondary text-center py-3 mb-0" role="status"><i class="ti ti-info-circle text-primary me-1" aria-hidden="true"></i>No hay solicitudes pendientes para esta estación.</p>
            <?php elseif ($requests !== []): ?>
                <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><caption class="visually-hidden">Solicitudes de acceso temporal devueltas para el centro autorizado</caption><thead><tr><th scope="col">Trabajador</th><th scope="col">Dispositivo</th><th scope="col">Motivo</th><th scope="col">Estado informado</th><th scope="col">Solicitud UTC</th><th scope="col">Vencimiento UTC</th><th scope="col">Acciones</th></tr></thead><tbody>
                    <?php foreach ($requests as $request): $requestUuid = strtolower((string) ($request['request_uuid'] ?? '')); ?><tr>
                        <th scope="row"><?= $esc($request['nombre_trabajador'] ?? 'No disponible') ?></th>
                        <td><?= $esc($request['dispositivo_nombre'] ?? 'No disponible') ?></td>
                        <td><?= $esc($request['reason'] ?? 'No disponible') ?></td>
                        <td>No incluido en esta lectura</td>
                        <td><?= $esc($request['requested_at_utc'] ?? 'No disponible') ?></td>
                        <td><?= $esc($request['request_expires_at_utc'] ?? 'No disponible') ?></td>
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
        </div>
    </section>
</div>

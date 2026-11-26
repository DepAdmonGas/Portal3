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
    <?php
    $adminPanel = is_array($administrativePinPanel ?? null) ? $administrativePinPanel : [];
    $adminFeedback = is_array($administrativePinFeedback ?? null) ? $administrativePinFeedback : null;
    $devices = array_values(array_filter($adminPanel['devices'] ?? [], static fn(array $device): bool => !empty($device['device_active']) && !empty($device['credential_active']) && !empty($device['biometric_grant_active']) && empty($device['session'])));
    $canGenerate = !empty($adminPanel['can_issue']) && !empty($adminPanel['secret_configured']) && $devices !== [];
    ?>
    <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
        <div class="col"><section class="card bg-body border rounded-3 shadow-sm h-100 mb-0"><div class="card-body d-flex align-items-center gap-3 p-3 p-lg-4">
            <span class="rounded-3 bg-primary-subtle text-primary p-3 flex-shrink-0"><i class="ti ti-shield-lock fs-9" aria-hidden="true"></i></span>
            <div><h2 class="card-title fs-6 fw-bolder mb-2">PIN de configuración</h2><p class="text-body-secondary mb-2">Autoriza la configuración biométrica del Lector.</p>
            <p class="small mb-3">5 minutos · Un solo uso</p><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#configuration-pin-modal" <?= !$canGenerate ? 'disabled' : '' ?>>Generar PIN</button></div>
        </div></section></div>
        <div class="col"><section class="card bg-body border rounded-3 shadow-sm h-100 mb-0"><div class="card-body d-flex align-items-center gap-3 p-3 p-lg-4">
            <span class="rounded-3 bg-primary-subtle text-primary p-3 flex-shrink-0"><i class="ti ti-key fs-9" aria-hidden="true"></i></span>
            <div><h2 class="card-title fs-6 fw-bolder mb-2" id="personnel-pin-title">PIN de acceso del personal</h2><p class="text-body-secondary mb-2">Autoriza una marcación cuando no se puede usar la huella.</p>
            <p class="small mb-3">15 minutos · Una marcación<?php if (($panel['state'] ?? '') === 'success'): ?> · Pendientes: <?= count($requests) ?><?php endif; ?></p><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#personnel-pin-modal">Administrar PIN</button></div>
        </div></section></div>
    </div>
    <div class="modal fade" id="configuration-pin-modal" tabindex="-1" aria-labelledby="configuration-pin-heading" aria-hidden="true" <?= $adminFeedback !== null ? 'data-show-feedback="1"' : '' ?>>
        <div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-primary text-white"><h2 class="modal-title fs-5 text-white" id="configuration-pin-heading">Generar PIN de configuración</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
        <?php if ($adminFeedback !== null): ?><div class="alert alert-<?= ($adminFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status"><?= $esc($adminFeedback['message'] ?? '') ?></div><?php endif; ?>
        <?php if ($adminFeedback !== null && preg_match('/\A[0-9]{6}\z/D', (string) ($adminFeedback['pin'] ?? ''))): ?>
            <p class="h3"><code data-one-time-pin><?= $esc($adminFeedback['pin']) ?></code></p><p>Dispositivo: <?= $esc($adminFeedback['device_name'] ?? 'No disponible') ?><br>Vence: <?= $esc($adminFeedback['expires_at_utc'] ?? 'No disponible') ?> UTC</p><button type="button" class="btn btn-outline-primary" data-copy-pin>Copiar PIN</button>
        <?php elseif ($canGenerate): ?>
            <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/admin-pin"><input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>"><input type="hidden" name="return_to" value="acceso-temporal">
            <label for="configuration-pin-device" class="form-label">Dispositivo autorizado</label><select id="configuration-pin-device" name="device_id" class="form-select mb-3" required><?php foreach ($devices as $device): ?><option value="<?= (int) $device['device_id'] ?>"><?= $esc($device['device_name']) ?></option><?php endforeach; ?></select><p class="small">Vigencia: cinco minutos. Un solo uso.</p><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo generar el PIN.</label><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary">Generar PIN</button></form>
        <?php else: ?><p class="text-body-secondary mb-0">La generación no está disponible para esta estación.</p><?php endif; ?>
        </div><div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cerrar</button></div></div></div>
    </div>
    <div class="modal fade" id="personnel-pin-modal" tabindex="-1" aria-labelledby="temporary-access-workspace-heading" aria-hidden="true" <?= is_array($temporaryAccessFeedback ?? null) ? 'data-show-feedback="1"' : '' ?>><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
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
                        <p class="h3 mb-1"><code data-one-time-pin><?= $esc($temporaryAccessFeedback['temporary_pin']) ?></code></p>
                        <?php if (!empty($temporaryAccessFeedback['worker_name'])): ?><p class="mb-1">Trabajador: <?= $esc($temporaryAccessFeedback['worker_name']) ?></p><?php endif; ?>
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
    <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cerrar</button></div></div></div></div>
    <div class="accordion" id="pin-history-accordion"><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pin-history" aria-expanded="false" aria-controls="pin-history">Historial de autorizaciones</button></h2><div id="pin-history" class="accordion-collapse collapse" data-bs-parent="#pin-history-accordion"><div class="accordion-body"><?php $administrativePinReturnTo = 'acceso-temporal'; $administrativePinFeedback = null; $administrativePinHistoryOnly = true; require __DIR__ . '/workspace-administrative-pin.php'; ?></div></div></div></div>
</div>

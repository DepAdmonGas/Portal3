<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$panel = is_array($temporaryAccessPanel ?? null) ? $temporaryAccessPanel : [];
$requests = is_array($panel['requests'] ?? null) ? $panel['requests'] : [];
$canEdit = ($panel['can_edit'] ?? false) === true
    && ($demoMode ?? false) !== true
    && ($realApiPostOperationsEnabled ?? false) === true;
$cancelableUuid = strtolower((string) ($panel['cancelable_uuid'] ?? ''));
$adminPanel = is_array($administrativePinPanel ?? null) ? $administrativePinPanel : [];
$adminDevices = is_array($adminPanel['devices'] ?? null) ? $adminPanel['devices'] : [];
$adminHistory = is_array($adminPanel['history'] ?? null) ? $adminPanel['history'] : [];
$adminFeedback = is_array($administrativePinFeedback ?? null) ? $administrativePinFeedback : null;
$selectedWorkplace = (int) ($selectedWorkplaceId ?? 0);
?>
<div id="container" class="mt-4 mb-5">
    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="/departamento-operativo/recursos-humanos/checador"><i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Volver al Checador</a>
    </div>
    <?php if ($adminFeedback !== null): ?>
        <div class="alert alert-<?= ($adminFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
            <?= $esc($adminFeedback['message'] ?? 'La operación del PIN terminó.') ?>
            <?php if (preg_match('/\A[0-9]{6}\z/D', (string)($adminFeedback['pin'] ?? ''))): ?>
                <p class="small mb-1 mt-2">PIN de configuración de un solo uso:</p>
                <p class="h3 mb-1"><code><?= $esc($adminFeedback['pin']) ?></code></p>
                <p class="small mb-0">Dispositivo: <?= $esc($adminFeedback['device_name'] ?? 'No disponible') ?> · Vence <?= $esc($adminFeedback['expires_at_utc'] ?? 'No disponible') ?> UTC. No volverá a mostrarse.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
        <div class="col">
            <section class="card bg-body border rounded-3 shadow-sm card-hover h-100 mb-0" aria-labelledby="personnel-pin-title">
                <div class="card-body d-flex align-items-center gap-3 p-3 p-lg-4">
                    <span class="rounded-3 bg-primary-subtle text-primary p-3 d-inline-flex align-items-center justify-content-center flex-shrink-0"><i class="ti ti-key fs-9" aria-hidden="true"></i></span>
                    <div class="d-flex flex-column flex-grow-1 h-100">
                    <h2 class="card-title fs-6 fw-bolder mb-2" id="personnel-pin-title">PIN de acceso del personal</h2>
                    <p class="text-body-secondary mb-3">Autoriza marcaciones temporales cuando un trabajador no puede utilizar su huella.</p>
                    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-3"><li><span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2 py-1">15 minutos</span></li><li><span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2 py-1">Una marcación</span></li></ul>
                    <?php if (($panel['state'] ?? '') === 'success'): ?><p class="mb-3">Solicitudes pendientes: <strong><?= count($requests) ?></strong></p><?php else: ?><p class="text-body-secondary mb-3">Solicitudes pendientes: no disponibles</p><?php endif; ?>
                    <div class="mt-auto"><a class="btn btn-primary" href="#temporary-access-requests"><i class="ti ti-list-details me-1" aria-hidden="true"></i>Administrar solicitudes</a></div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col">
            <section class="card bg-body border rounded-3 shadow-sm card-hover h-100 mb-0" aria-labelledby="configuration-pin-title">
                <div class="card-body d-flex align-items-center gap-3 p-3 p-lg-4">
                    <span class="rounded-3 bg-primary-subtle text-primary p-3 d-inline-flex align-items-center justify-content-center flex-shrink-0"><i class="ti ti-shield-lock fs-9" aria-hidden="true"></i></span>
                    <div class="d-flex flex-column flex-grow-1 h-100">
                    <h2 class="card-title fs-6 fw-bolder mb-2" id="configuration-pin-title">PIN de configuración</h2>
                    <p class="text-body-secondary mb-3">Autoriza al Encargado para registrar y actualizar las huellas del personal.</p>
                    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-3"><li><span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2 py-1">Vigencia: 5 minutos</span></li><li><span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2 py-1">Hasta 3 intentos</span></li><li><span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold px-2 py-1">Uso administrativo</span></li></ul>
                    <?php if (($adminPanel['ok'] ?? false) === true && $adminDevices !== []): ?>
                        <?php foreach ($adminDevices as $adminDevice): ?>
                            <div class="border rounded p-2 mb-2">
                                <div class="fw-semibold"><?= $esc($adminDevice['device_name'] ?? 'Dispositivo') ?></div>
                                <div class="small text-body-secondary mb-2">
                                    <?php if (!empty($adminDevice['session'])): ?>Sesión <?= $esc($adminDevice['session']['state']) ?> · vence <?= $esc($adminDevice['session']['expires_at_utc']) ?> UTC
                                    <?php else: ?>Sin sesión administrativa activa<?php endif; ?>
                                </div>
                                <?php if (!empty($adminDevice['pending_pin'])): ?>
                                    <div class="small mb-2">PIN pendiente hasta <?= $esc($adminDevice['pending_pin']['expires_at_utc']) ?> UTC</div>
                                    <?php if (!empty($adminPanel['can_revoke'])): ?>
                                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/admin-pin/<?= rawurlencode((string)$adminDevice['pending_pin']['authorization_id']) ?>/revoke" class="mb-2">
                                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>">
                                            <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo revocar el PIN pendiente.</label>
                                            <button type="submit" class="btn btn-sm bg-danger-subtle text-danger"><i class="ti ti-x me-1" aria-hidden="true"></i>Revocar PIN</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($adminDevice['session']) && !empty($adminPanel['can_revoke'])): ?>
                                    <form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/admin-session/<?= rawurlencode((string)$adminDevice['session']['session_id']) ?>/revoke">
                                        <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>">
                                        <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo revocar la sesión.</label>
                                        <button type="submit" class="btn btn-sm bg-danger-subtle text-danger"><i class="ti ti-x me-1" aria-hidden="true"></i>Revocar sesión</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif (($adminPanel['ok'] ?? false) === true): ?>
                        <p class="text-body-secondary mb-3">No hay dispositivos disponibles en esta estación.</p>
                    <?php else: ?>
                        <p class="text-body-secondary mb-3">La emisión no está disponible: faltan tablas, configuración privada o lectura autorizada.</p>
                    <?php endif; ?>
                    <?php $issuableDevices = array_values(array_filter($adminDevices, static fn(array $device): bool => !empty($device['device_active']) && !empty($device['credential_active']) && !empty($device['biometric_grant_active']) && empty($device['session']))); ?>
                    <?php if (!empty($adminPanel['can_issue']) && !empty($adminPanel['secret_configured']) && $issuableDevices !== []): ?>
                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/admin-pin" class="mt-auto">
                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>">
                            <label class="form-label" for="admin-pin-device">Dispositivo autorizado</label>
                            <select id="admin-pin-device" name="device_id" class="form-select mb-2" required>
                                <?php foreach ($issuableDevices as $adminDevice): ?><option value="<?= (int)$adminDevice['device_id'] ?>"><?= $esc($adminDevice['device_name']) ?></option><?php endforeach; ?>
                            </select>
                            <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo generar un PIN administrativo de un solo uso.</label>
                            <button type="submit" class="btn btn-primary"><i class="ti ti-key me-1" aria-hidden="true"></i>Generar PIN de configuración</button>
                        </form>
                    <?php else: ?>
                        <div class="mt-auto">
                            <button type="button" class="btn btn-primary" disabled><i class="ti ti-lock me-1" aria-hidden="true"></i>Generar PIN de configuración</button>
                            <p class="small text-body-secondary mt-2 mb-0"><?= empty($adminPanel['can_issue']) ? 'La cuenta tiene acceso de consulta; la emisión requiere autorización específica.' : 'La emisión requiere esquema, secreto compartido, credencial y grant biométrico vigentes, y ausencia de sesión activa.' ?></p>
                        </div>
                    <?php endif; ?>
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
    <section class="card" aria-labelledby="pin-history-heading">
        <div class="card-header bg-primary text-white"><h2 class="h5 mb-0 text-white" id="pin-history-heading"><i class="ti ti-history me-2" aria-hidden="true"></i>Historial de autorizaciones</h2></div>
        <div class="card-body">
            <?php if (($adminPanel['ok'] ?? false) !== true): ?>
                <div class="alert alert-warning" role="status">El historial de PIN de configuración no está disponible; no se confirma que no existan registros.</div>
            <?php elseif ($adminHistory === []): ?>
                <p class="text-body-secondary text-center py-3 mb-0" role="status"><i class="ti ti-info-circle text-primary me-1" aria-hidden="true"></i>No hay autorizaciones de configuración registradas.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><caption class="visually-hidden">Historial persistido de PIN de configuración</caption><thead><tr><th>Fecha y hora UTC</th><th>Tipo</th><th>Encargado</th><th>Dispositivo</th><th>Vigencia</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
                    <?php foreach ($adminHistory as $entry): ?><tr>
                        <td><?= $esc($entry['issued_at_utc'] ?? 'No disponible') ?></td><td>PIN de configuración</td>
                        <td><?= $esc($entry['actor_name'] ?? 'No disponible') ?></td><td><?= $esc($entry['device_name'] ?? 'No disponible') ?></td>
                        <td><?= $esc($entry['expires_at_utc'] ?? 'No disponible') ?> UTC</td>
                        <td><?= $esc($entry['session_state'] ? 'Consumido · sesión '.$entry['session_state'] : $entry['state']) ?></td>
                        <td><?php if (($adminPanel['can_revoke'] ?? false) && ($entry['session_state'] ?? '') === 'ACTIVE' && !empty($entry['session_id'])): ?>
                            <form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/admin-session/<?= rawurlencode((string)$entry['session_id']) ?>/revoke">
                                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>">
                                <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo revocar.</label><button type="submit" class="btn btn-sm bg-danger-subtle text-danger"><i class="ti ti-x me-1" aria-hidden="true"></i>Revocar sesión</button>
                            </form>
                        <?php elseif (($adminPanel['can_revoke'] ?? false) && ($entry['state'] ?? '') === 'PENDING'): ?>
                            <form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/admin-pin/<?= rawurlencode((string)$entry['authorization_id']) ?>/revoke">
                                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>">
                                <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo revocar.</label><button type="submit" class="btn btn-sm bg-danger-subtle text-danger"><i class="ti ti-x me-1" aria-hidden="true"></i>Revocar PIN</button>
                            </form>
                        <?php else: ?>—<?php endif; ?></td>
                    </tr><?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </div>
    </section>
</div>

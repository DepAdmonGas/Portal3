<?php
declare(strict_types=1);

$esc = $esc ?? static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$adminPanel = is_array($administrativePinPanel ?? null) ? $administrativePinPanel : ['ok' => false, 'state' => 'unavailable', 'devices' => [], 'history' => [], 'can_issue' => false, 'can_revoke' => false];
$adminDevices = is_array($adminPanel['devices'] ?? null) ? $adminPanel['devices'] : [];
$adminHistory = is_array($adminPanel['history'] ?? null) ? $adminPanel['history'] : [];
$adminFeedback = is_array($administrativePinFeedback ?? null) ? $administrativePinFeedback : null;
$selectedWorkplace = (int) ($selectedWorkplaceId ?? 0);
?>
            <?php if (($adminPanel['state'] ?? '') !== 'denied'): ?>
                <?php if (empty($administrativePinHistoryOnly)): ?><section class="border rounded p-3 mt-3" aria-labelledby="m11-admin-pin-heading"><?php endif; ?>
                    <?php if (empty($administrativePinHistoryOnly)): ?>
                    <h3 class="h6 mb-1" id="m11-admin-pin-heading"><i class="ti ti-shield-lock me-1" aria-hidden="true"></i>PIN de configuración</h3>
                    <p class="small text-muted mb-3">PIN administrativo para autorizar temporalmente la configuración biométrica desde el Lector. Vigencia de quince minutos.</p>
                    <?php if ($adminFeedback !== null): ?>
                        <div class="alert alert-<?= ($adminFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
                            <?= $esc($adminFeedback['message'] ?? 'La operación del PIN terminó.') ?>
                            <?php if (preg_match('/\A[0-9]{6}\z/D', (string) ($adminFeedback['pin'] ?? ''))): ?>
                                <p class="small mb-1 mt-2">PIN de configuración de un solo uso:</p>
                                <p class="h3 mb-1"><code><?= $esc($adminFeedback['pin']) ?></code></p>
                                <p class="small mb-0">Dispositivo: <?= $esc($adminFeedback['device_name'] ?? 'No disponible') ?> · Vence <?= $esc($adminFeedback['expires_at_utc'] ?? 'No disponible') ?> UTC. No volverá a mostrarse.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
                                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/admin-pin/<?= rawurlencode((string) $adminDevice['pending_pin']['authorization_id']) ?>/revoke" class="mb-2">
                                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>"><?php if (($administrativePinReturnTo ?? '') === 'acceso-temporal'): ?><input type="hidden" name="return_to" value="acceso-temporal"><?php endif; ?>
                                            <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo revocar el PIN pendiente.</label>
                                            <button type="submit" class="btn btn-sm bg-danger-subtle text-danger"><i class="ti ti-x me-1" aria-hidden="true"></i>Revocar PIN</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($adminDevice['session']) && !empty($adminPanel['can_revoke'])): ?>
                                    <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/admin-session/<?= rawurlencode((string) $adminDevice['session']['session_id']) ?>/revoke">
                                        <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>"><?php if (($administrativePinReturnTo ?? '') === 'acceso-temporal'): ?><input type="hidden" name="return_to" value="acceso-temporal"><?php endif; ?>
                                        <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo revocar la sesión.</label>
                                        <button type="submit" class="btn btn-sm bg-danger-subtle text-danger"><i class="ti ti-x me-1" aria-hidden="true"></i>Revocar sesión</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif (($adminPanel['ok'] ?? false) === true): ?>
                        <p class="text-body-secondary mb-3">No hay dispositivos disponibles en esta estación.</p>
                    <?php else: ?>
                        <div class="alert alert-warning" role="status">No fue posible consultar el estado del PIN de configuración en la base compartida.</div>
                    <?php endif; ?>
                    <?php $issuableDevices = array_values(array_filter($adminDevices, static fn(array $device): bool => !empty($device['device_active']) && !empty($device['credential_active']) && !empty($device['biometric_grant_active']) && empty($device['session']))); ?>
                    <?php if (!empty($adminPanel['can_issue']) && !empty($adminPanel['secret_configured']) && $issuableDevices !== []): ?>
                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/admin-pin" class="mt-3">
                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>"><?php if (($administrativePinReturnTo ?? '') === 'acceso-temporal'): ?><input type="hidden" name="return_to" value="acceso-temporal"><?php endif; ?>
                            <label class="form-label" for="admin-pin-device">Dispositivo autorizado</label>
                            <select id="admin-pin-device" name="device_id" class="form-select mb-2" required>
                                <?php foreach ($issuableDevices as $adminDevice): ?><option value="<?= (int) $adminDevice['device_id'] ?>"><?= $esc($adminDevice['device_name']) ?></option><?php endforeach; ?>
                            </select>
                            <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo generar un PIN administrativo de un solo uso.</label>
                            <button type="submit" class="btn btn-primary"><i class="ti ti-key me-1" aria-hidden="true"></i>Generar PIN de configuración</button>
                        </form>
                    <?php else: ?>
                        <div class="mt-3">
                            <button type="button" class="btn btn-primary" disabled><i class="ti ti-lock me-1" aria-hidden="true"></i>Generar PIN de configuración</button>
                            <p class="small text-body-secondary mt-2 mb-0"><?= empty($adminPanel['can_issue']) ? 'La emisión requiere permisos de edición y el servicio habilitado.' : 'La emisión requiere esquema, secreto compartido, credencial y grant biométrico vigentes, y ausencia de sesión activa.' ?></p>
                        </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <div<?= empty($administrativePinHistoryOnly) ? ' class="mt-4"' : '' ?>>
                        <?php if (empty($administrativePinHistoryOnly)): ?><h4 class="h6"><i class="ti ti-history me-1" aria-hidden="true"></i>Historial de PIN de configuración</h4><?php endif; ?>
                        <?php if (($adminPanel['ok'] ?? false) !== true): ?>
                            <div class="alert alert-warning mb-0" role="status">El historial no está disponible; no se confirma que no existan registros.</div>
                        <?php elseif ($adminHistory === []): ?>
                            <p class="text-body-secondary text-center py-3 mb-0" role="status">No hay autorizaciones de configuración registradas.</p>
                        <?php else: ?>
                            <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><caption class="visually-hidden">Historial persistido de PIN de configuración</caption><thead><tr><th>Fecha y hora UTC</th><th>Encargado</th><th>Dispositivo</th><th>Vigencia</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
                                <?php foreach ($adminHistory as $entry): ?><tr>
                                    <td><?= $esc($entry['issued_at_utc'] ?? 'No disponible') ?></td>
                                    <td><?= $esc($entry['actor_name'] ?? 'No disponible') ?></td><td><?= $esc($entry['device_name'] ?? 'No disponible') ?></td>
                                    <td><?= $esc($entry['expires_at_utc'] ?? 'No disponible') ?> UTC</td>
                                    <td><?= $esc($entry['session_state'] ? 'Consumido · sesión ' . $entry['session_state'] : $entry['state']) ?></td>
                                    <td><?php if (($adminPanel['can_revoke'] ?? false) && ($entry['session_state'] ?? '') === 'ACTIVE' && !empty($entry['session_id'])): ?>
                                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/admin-session/<?= rawurlencode((string) $entry['session_id']) ?>/revoke">
                                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>"><?php if (($administrativePinReturnTo ?? '') === 'acceso-temporal'): ?><input type="hidden" name="return_to" value="acceso-temporal"><?php endif; ?>
                                            <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo revocar.</label><button type="submit" class="btn btn-sm bg-danger-subtle text-danger"><i class="ti ti-x me-1" aria-hidden="true"></i>Revocar sesión</button>
                                        </form>
                                    <?php elseif (($adminPanel['can_revoke'] ?? false) && ($entry['state'] ?? '') === 'PENDING'): ?>
                                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/admin-pin/<?= rawurlencode((string) $entry['authorization_id']) ?>/revoke">
                                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>"><?php if (($administrativePinReturnTo ?? '') === 'acceso-temporal'): ?><input type="hidden" name="return_to" value="acceso-temporal"><?php endif; ?>
                                            <label class="form-check small mb-1"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo revocar.</label><button type="submit" class="btn btn-sm bg-danger-subtle text-danger"><i class="ti ti-x me-1" aria-hidden="true"></i>Revocar PIN</button>
                                        </form>
                                    <?php else: ?>—<?php endif; ?></td>
                                </tr><?php endforeach; ?>
                            </tbody></table></div>
                        <?php endif; ?>
                    </div>
                <?php if (empty($administrativePinHistoryOnly)): ?></section><?php endif; ?>
            <?php endif; ?>

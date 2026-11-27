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
            <p class="small mb-3">15 minutos · Un solo uso</p><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#configuration-pin-modal" <?= !$canGenerate ? 'disabled' : '' ?>>Generar PIN</button></div>
        </div></section></div>
        <div class="col"><section class="card bg-body border rounded-3 shadow-sm h-100 mb-0"><div class="card-body d-flex align-items-center gap-3 p-3 p-lg-4">
            <span class="rounded-3 bg-primary-subtle text-primary p-3 flex-shrink-0"><i class="ti ti-key fs-9" aria-hidden="true"></i></span>
            <div><h2 class="card-title fs-6 fw-bolder mb-2" id="personnel-pin-title">PIN de acceso del personal</h2><p class="text-body-secondary mb-2">Autoriza una marcación cuando no se puede usar la huella.</p>
            <p class="small mb-3">15 minutos · Una marcación<?php if (($panel['state'] ?? '') === 'success'): ?> · Solicitudes: <?= count($requests) ?><?php endif; ?></p><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#personnel-pin-modal">Administrar PIN</button></div>
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
            <label for="configuration-pin-device" class="form-label">Dispositivo autorizado</label><select id="configuration-pin-device" name="device_id" class="form-select mb-3" required><?php foreach ($devices as $device): ?><option value="<?= (int) $device['device_id'] ?>"><?= $esc($device['device_name']) ?></option><?php endforeach; ?></select><p class="small">Vigencia: quince minutos. Un solo uso.</p><label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo generar el PIN.</label><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Cancelar</button> <button type="submit" class="btn btn-primary">Generar PIN</button></form>
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
                <p class="text-body-secondary text-center py-3 mb-0" role="status"><i class="ti ti-info-circle text-primary me-1" aria-hidden="true"></i>No se devolvieron solicitudes para esta estación.</p>
            <?php elseif ($requests !== []): ?>
                <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0" id="temporary-access-requests-table"><caption class="visually-hidden">Solicitudes de acceso temporal devueltas para el centro autorizado</caption><thead><tr><th scope="col">Trabajador</th><th scope="col">Dispositivo</th><th scope="col">Motivo</th><th scope="col">Fecha de solicitud (UTC)</th><th scope="col">Vencimiento de solicitud (UTC)</th><th scope="col">Acciones</th></tr></thead><tbody>
                    <?php foreach ($requests as $request): $requestUuid = strtolower((string) ($request['request_uuid'] ?? '')); ?><tr>
                        <th scope="row"><?= $esc($request['nombre_trabajador'] ?? 'No disponible') ?><?php if ((int) ($request['id_personal_resuelto'] ?? 0) > 0): ?><span class="d-block small text-body-secondary">ID RH: <?= (int) $request['id_personal_resuelto'] ?></span><?php endif; ?></th>
                        <td><?= $esc($request['dispositivo_nombre'] ?? 'No disponible') ?></td>
                        <td><?= $esc($request['reason'] ?? 'No disponible') ?></td>
                        <td><?= $esc($request['requested_at_utc'] ?? 'No disponible') ?></td>
                        <td><?= $esc($request['request_expires_at_utc'] ?? 'No disponible') ?></td>
                        <td>
                            <?php if ($canEdit && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $requestUuid)): ?>
                                <div class="dropdown d-inline-block">
                                    <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false" aria-label="Acciones de solicitud"><i class="ti ti-dots-vertical fs-4" aria-hidden="true"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                        <?php foreach (['authorize' => ['Autorizar', 'ti-check', 'success'], 'reject' => ['Rechazar', 'ti-x', 'danger']] as $action => [$label, $icon, $tone]): ?>
                                            <li><form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/<?= rawurlencode($requestUuid) ?>/<?= $action ?>" class="px-3 py-2">
                                                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                                <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                <input type="hidden" name="worker_id" value="<?= (int) ($request['id_personal_resuelto'] ?? 0) ?>">
                                                <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                                <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo <?= strtolower($label) ?> esta solicitud.</label>
                                                <button type="submit" class="dropdown-item text-<?= $tone ?> px-0"><i class="ti <?= $icon ?> me-1" aria-hidden="true"></i><?= $esc($label) ?></button>
                                            </form></li>
                                        <?php endforeach; ?>
                                        <?php if ($cancelableUuid !== '' && hash_equals($cancelableUuid, $requestUuid)): ?>
                                            <li><form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/<?= rawurlencode($requestUuid) ?>/cancel" class="px-3 py-2">
                                                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                                <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                <input type="hidden" name="worker_id" value="<?= (int) ($request['id_personal_resuelto'] ?? 0) ?>">
                                                <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                                <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_action" value="1" required> Confirmo cancelar esta autorización temporal.</label>
                                                <button type="submit" class="dropdown-item text-warning px-0"><i class="ti ti-ban me-1" aria-hidden="true"></i>Cancelar autorización</button>
                                            </form></li>
                                        <?php endif; ?>
                                    </ul>
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
    <?php if (is_array($temporaryAccessHistory ?? null)): $historyFilters = $temporaryAccessHistoryFilters ?? []; ?>
    <section class="card mb-3" aria-labelledby="attendance-pin-history-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between"><h2 class="h5 mb-0 text-white" id="attendance-pin-history-heading">Solicitudes de PIN de asistencia</h2><button type="button" class="btn bg-white text-primary" data-bs-toggle="modal" data-bs-target="#pin-history-search-modal"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar</button></div>
        <div class="card-body">
            <div class="modal fade" id="pin-history-search-modal" tabindex="-1" aria-labelledby="pin-history-search-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary"><h3 class="modal-title h5 text-white" id="pin-history-search-title"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar solicitudes de PIN</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><form method="get"><div class="modal-body"><div class="row g-2">
                <input type="hidden" name="workplace_id" value="<?= $selectedWorkplace ?>">
                <div class="col-md-3"><label class="form-label" for="history-search">Buscar</label><input class="form-control" id="history-search" name="history_search" value="<?= $esc($historyFilters['search'] ?? '') ?>" maxlength="100"></div>
                <div class="col-md-2"><label class="form-label" for="history-worker">ID RH</label><input class="form-control" id="history-worker" name="history_worker_id" type="number" min="1" value="<?= $esc($historyFilters['worker_id'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label" for="history-status">Estado</label><select class="form-select" id="history-status" name="history_status"><option value="">Todos</option><?php foreach (['PENDING'=>'Pendiente','AUTHORIZED'=>'Autorizado','REJECTED'=>'Rechazado','CANCELLED'=>'Cancelado','EXPIRED'=>'Vencido','USED'=>'Utilizado','LOCKED'=>'Bloqueado'] as $historyState => $historyLabel): ?><option value="<?= $esc($historyState) ?>" <?= ($historyFilters['status'] ?? '') === $historyState ? 'selected' : '' ?>><?= $esc($historyLabel) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label" for="history-from">Desde (UTC)</label><input class="form-control" id="history-from" name="history_from" type="date" value="<?= $esc($historyFilters['from'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label" for="history-to">Hasta (UTC)</label><input class="form-control" id="history-to" name="history_to" type="date" value="<?= $esc($historyFilters['to'] ?? '') ?>"></div>
                <div class="col-md-2"><label class="form-label" for="history-sort">Ordenar por</label><select class="form-select" id="history-sort" name="history_sort"><?php foreach (['date'=>'Fecha','worker'=>'Trabajador','status'=>'Estado'] as $value=>$label): ?><option value="<?= $esc($value) ?>" <?= ($historyFilters['sort'] ?? 'date') === $value ? 'selected' : '' ?>><?= $esc($label) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label" for="history-direction">Orden</label><select class="form-select" id="history-direction" name="history_direction"><option value="desc" <?= ($historyFilters['direction'] ?? 'desc') === 'desc' ? 'selected' : '' ?>>Descendente</option><option value="asc" <?= ($historyFilters['direction'] ?? '') === 'asc' ? 'selected' : '' ?>>Ascendente</option></select></div>
                </div></div><div class="modal-footer"><a class="btn btn-outline-secondary" href="?workplace_id=<?= $selectedWorkplace ?>">Limpiar</a><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Buscar</button></div></form></div></div></div>
            <?php if (empty($temporaryAccessHistory['ok'])): ?><div class="alert alert-warning" role="status"><?= ($temporaryAccessHistory['error']['kind'] ?? '') === 'invalid_filter' ? 'Los filtros del historial no son válidos.' : 'El historial de solicitudes no está disponible.' ?></div>
            <?php elseif (empty($temporaryAccessHistory['items'])): ?><p class="text-body-secondary">No se encontraron solicitudes.</p>
            <?php else: ?><div class="table-responsive"><table class="table table-striped table-bordered align-middle"><thead><tr><th>Trabajador</th><th>Dispositivo</th><th>Motivo</th><th>Solicitud UTC</th><th>Estado persistido</th><th>Vencimiento PIN UTC</th></tr></thead><tbody>
                <?php foreach ($temporaryAccessHistory['items'] as $item): $statusLabels = ['PENDING'=>'Pendiente','AUTHORIZED'=>'Autorizado','REJECTED'=>'Rechazado','CANCELLED'=>'Cancelado','EXPIRED'=>'Vencido','USED'=>'Utilizado','LOCKED'=>'Bloqueado']; ?><tr><td><?= $esc($item['nombre_trabajador'] ?? 'No disponible') ?></td><td><?= $esc($item['dispositivo_nombre'] ?? '') ?></td><td><?= $esc($item['reason'] ?? '') ?></td><td><?= $esc($item['requested_at_utc'] ?? '') ?></td><td><span class="badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 border text-body-emphasis <?= in_array(($item['status'] ?? ''), ['AUTHORIZED','USED'], true) ? 'bg-success-subtle border-success-subtle' : (in_array(($item['status'] ?? ''), ['REJECTED','LOCKED'], true) ? 'bg-danger-subtle border-danger-subtle' : (($item['status'] ?? '') === 'PENDING' ? 'bg-warning-subtle border-warning-subtle' : 'bg-body-secondary')) ?>"><i class="ti ti-info-circle" aria-hidden="true"></i><?= $esc($statusLabels[$item['status'] ?? ''] ?? ($item['status'] ?? '')) ?></span></td><td><?= $esc($item['pin_expires_at_utc'] ?? '—') ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
            <?php if (!empty($temporaryAccessHistory['ok'])): $historyPage = (int)$temporaryAccessHistory['page']; $historyTotal = (int)$temporaryAccessHistory['total']; $historyPages = max(1,(int)ceil($historyTotal / (int)$temporaryAccessHistory['per_page'])); $historyParams = ['workplace_id'=>$selectedWorkplace]; foreach ($historyFilters as $key=>$value) { if ($value !== '') $historyParams['history_'.$key] = $value; } ?>
                <p class="small">Solicitudes: <?= $historyTotal ?> · Página <?= $historyPage ?> de <?= $historyPages ?></p><nav aria-label="Páginas del historial"><?php if ($historyPage > 1): $historyParams['history_page']=$historyPage-1; ?><a class="btn btn-outline-primary btn-sm" href="?<?= $esc(http_build_query($historyParams)) ?>">Anterior</a><?php endif; ?> <?php if ($historyPage < $historyPages): $historyParams['history_page']=$historyPage+1; ?><a class="btn btn-outline-primary btn-sm" href="?<?= $esc(http_build_query($historyParams)) ?>">Siguiente</a><?php endif; ?></nav>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
    <section class="card" id="pin-history" aria-labelledby="pin-history-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-history fs-5"></i></span>
            <h2 class="h5 mb-0 text-white" id="pin-history-heading">Historial de autorizaciones</h2>
        </div>
        <div class="card-body"><?php $administrativePinReturnTo = 'acceso-temporal'; $administrativePinFeedback = null; $administrativePinHistoryOnly = true; require __DIR__ . '/workspace-administrative-pin.php'; ?></div>
    </section>
</div>

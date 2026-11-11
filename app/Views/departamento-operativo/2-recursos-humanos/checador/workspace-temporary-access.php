<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$panel = is_array($temporaryAccessPanel ?? null) ? $temporaryAccessPanel : [];
$requests = is_array($panel['requests'] ?? null) ? $panel['requests'] : [];
$canAuthorize = ($panel['can_authorize'] ?? false) === true
    && ($demoMode ?? false) !== true
    && ($temporaryAccessPostOperationsEnabled ?? false) === true;
$canCancel = ($panel['can_cancel'] ?? false) === true
    && ($demoMode ?? false) !== true
    && ($temporaryAccessPostOperationsEnabled ?? false) === true;
$canReject = ($panel['can_reject'] ?? false) === true
    && ($demoMode ?? false) !== true
    && ($temporaryAccessPostOperationsEnabled ?? false) === true;
$cancelableUuid = strtolower((string) ($panel['cancelable_uuid'] ?? ''));
$actionForm = static function (string $action, string $label, string $icon, string $requestUuid, int $workerId, bool $dropdownItem) use ($esc, $selectedWorkplaceId, $selectedDate): string {
    $confirmationId = 'temporary-access-confirm-' . $action . '-' . $requestUuid;
    $buttonClass = $dropdownItem
        ? 'dropdown-item d-flex align-items-center gap-2 px-0'
        : 'btn btn-sm btn-primary';
    $html = '<form method="post" action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/' . rawurlencode($requestUuid) . '/' . $action . '" class="' . ($dropdownItem ? 'px-3 py-2' : 'd-inline-flex flex-wrap align-items-center justify-content-center gap-2') . '">';
    $html .= '<input type="hidden" name="_csrf_token" value="' . $esc(\App\Core\CsrfToken::token()) . '">';
    $html .= '<input type="hidden" name="workplace_id" value="' . (int) ($selectedWorkplaceId ?? 0) . '">';
    $html .= '<input type="hidden" name="worker_id" value="' . $workerId . '">';
    $html .= '<input type="hidden" name="date" value="' . $esc($selectedDate ?? '') . '">';
    $html .= '<div class="form-check small' . ($dropdownItem ? ' mb-2' : ' mb-0') . '"><input class="form-check-input" type="checkbox" id="' . $esc($confirmationId) . '" name="confirm_action" value="1" required><label class="form-check-label" for="' . $esc($confirmationId) . '">Confirmo ' . $esc(strtolower($label)) . ' esta solicitud.</label></div>';
    $html .= '<button type="submit" class="' . $buttonClass . '"><i class="ti ' . $esc($icon) . '" aria-hidden="true"></i>' . $esc($label) . '</button></form>';
    return $html;
};
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
            <?php if ($canCancel && $cancelableUuid !== ''): ?>
                <div class="border rounded p-3 mb-3" aria-label="Autorización temporal reciente">
                    <p class="small mb-2">Puedes cancelar la autorización temporal reciente mientras siga vigente.</p>
                    <?= $actionForm('cancel', 'Cancelar autorización', 'ti-ban', $cancelableUuid, 0, false) ?>
                </div>
            <?php endif; ?>
            <?php if (($panel['state'] ?? '') === 'demo_blocked'): ?>
                <div class="alert alert-info" role="status"><?= $esc($panel['message'] ?? 'La cola no está disponible en la demostración.') ?></div>
            <?php elseif (($panel['state'] ?? '') === 'error'): ?>
                <div class="alert alert-warning" role="status"><?= $esc($panel['message'] ?? 'La lectura de solicitudes no está disponible; no se confirma que la cola esté vacía.') ?></div>
            <?php elseif (($panel['state'] ?? '') === 'success' && $requests === []): ?>
                <div class="alert alert-secondary" role="status">No hay solicitudes de PIN temporal pendientes.</div>
            <?php elseif ($requests !== []): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <caption class="visually-hidden">Solicitudes de acceso temporal del centro físico autorizado</caption>
                        <thead>
                            <tr>
                                <th scope="col">Trabajador</th>
                                <th scope="col">Dispositivo</th>
                                <th scope="col">Motivo</th>
                                <th scope="col">Estado</th>
                                <th scope="col">Solicitud UTC</th>
                                <th scope="col">Vencimiento UTC</th>
                                <th scope="col" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request):
                                $requestUuid = strtolower((string) ($request['request_uuid'] ?? ''));
                                $validRequestUuid = preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $requestUuid) === 1;
                                $actionsMenuId = 'temporary-access-actions-' . $requestUuid;
                                $actionsTriggerId = 'temporary-access-actions-trigger-' . $requestUuid;
                                $actionsLabel = 'Acciones para ' . (string) ($request['nombre_trabajador'] ?? 'esta solicitud');
                                $availableActions = [];
                                if ($canAuthorize) $availableActions['authorize'] = ['Autorizar', 'ti-check'];
                                if ($canReject) $availableActions['reject'] = ['Rechazar', 'ti-x'];
                                $reasonLabel = match (strtoupper(trim((string) ($request['reason'] ?? '')))) {
                                    'FINGERPRINT_NOT_RECOGNIZED' => 'No se reconoció la huella',
                                    default => 'Motivo de contingencia',
                                };
                            ?>
                                <tr>
                                    <th scope="row">
                                        <?= $esc($request['nombre_trabajador'] ?? 'No disponible') ?>
                                    </th>
                                    <td><?= $esc($request['dispositivo_nombre'] ?? 'No disponible') ?></td>
                                    <td><?= $esc($reasonLabel) ?></td>
                                    <td>Sin información de estado</td>
                                    <td><?= $esc($request['requested_at_utc'] ?? 'No disponible') ?></td>
                                    <td><?= $esc($request['request_expires_at_utc'] ?? 'No disponible') ?></td>
                                    <td class="text-center align-middle">
                                        <?php if ($validRequestUuid && count($availableActions) === 1):
                                            [$action, [$label, $icon]] = [array_key_first($availableActions), reset($availableActions)];
                                            echo $actionForm($action, $label, $icon, $requestUuid, (int) ($request['id_personal_resuelto'] ?? 0), false);
                                        elseif ($validRequestUuid && count($availableActions) > 1): ?>
                                            <div class="dropdown dropstart">
                                                <button
                                                    type="button"
                                                    id="<?= $esc($actionsTriggerId) ?>"
                                                    class="btn btn-sm btn-link text-muted text-decoration-none p-1"
                                                    data-bs-toggle="dropdown"
                                                    data-bs-auto-close="outside"
                                                    aria-haspopup="true"
                                                    aria-expanded="false"
                                                    aria-controls="<?= $esc($actionsMenuId) ?>"
                                                    aria-label="<?= $esc($actionsLabel) ?>"
                                                >
                                                    <i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i>
                                                </button>
                                                <ul class="dropdown-menu" id="<?= $esc($actionsMenuId) ?>" aria-labelledby="<?= $esc($actionsTriggerId) ?>">
                                                    <?php foreach ($availableActions as $action => [$label, $icon]): ?>
                                                        <li>
                                                            <?= $actionForm($action, $label, $icon, $requestUuid, (int) ($request['id_personal_resuelto'] ?? 0), true) ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary" role="status">La consulta no está disponible en este momento.</div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if (!empty($capabilitiesPanel['visible'])): ?>
<?php
$capEsc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$capPrincipalType = (string) $capabilitiesPanel['principal_type'];
$capPrincipalId = (int) $capabilitiesPanel['principal_id'];
$capPost = $capPrincipalType === 'POSITION';
$capAction = '/departamento-operativo/recursos-humanos/checador/configuracion/permisos-capacidades/'
    . ($capPost ? 'puestos/' : 'usuarios/') . $capPrincipalId;
$capFeedbackMessages = [
    'saved' => 'El permiso se guardó y verificó correctamente.',
    'invalid_input' => 'Revisa el permiso, la decisión y el motivo. No incluyas PIN, tokens ni secretos.',
    'principal_unavailable' => 'El puesto o usuario ya no está activo.',
    'administrator_permission_denied' => 'No tienes permiso para administrar estas capacidades.',
    'global_scope_required' => 'La administración de grants globales requiere alcance completo de Checador.',
    'checador_acl_read_denied' => 'No tienes permiso efectivo para consultar las capacidades del Checador.',
    'principal_out_of_scope' => 'No puedes modificar las capacidades de tu propio puesto o usuario.',
    'capability_not_applicable' => 'Esta capacidad no aplica al puesto seleccionado.',
    'already_inherited' => 'El permiso individual ya hereda el permiso del puesto.',
    'state_stale' => 'El permiso cambió desde que abriste esta pantalla. Actualiza y vuelve a intentarlo.',
    'grant_schema_unavailable' => 'La tabla de capacidades no cumple el esquema requerido; no se guardaron cambios.',
    'audit_schema_pending' => 'Guardado deshabilitado: requiere aprobar y aplicar la ampliación de auditoría propuesta.',
    'permission_write_unavailable' => 'No se pudo guardar el permiso. No se confirmó ningún cambio.',
];
$capFeedbackCode = is_array($capabilitiesFeedback ?? null) ? (string) ($capabilitiesFeedback['code'] ?? '') : '';
?>
<div class="col-12">
    <div class="card mb-3">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
            <i class="ti ti-shield-lock" aria-hidden="true"></i>
            <h5 class="mb-0 text-white">Capacidades Checador V2</h5>
        </div>
        <div class="card-body">
            <p class="text-body-secondary">Permisos funcionales del Checador. Las denegaciones individuales prevalecen sobre el puesto; “Heredar” elimina la decisión individual y vuelve a aplicar el puesto.</p>
            <?php if ($capFeedbackCode !== ''): ?>
                <div class="alert <?= !empty($capabilitiesFeedback['ok']) ? 'alert-success' : 'alert-warning' ?>" role="status">
                    <?= $capEsc($capFeedbackMessages[$capFeedbackCode] ?? 'No fue posible completar el cambio de permiso.') ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($capabilitiesPanel['blocker'])): ?>
                <div class="alert alert-warning" role="alert"><?= $capEsc($capabilitiesPanel['blocker']) ?></div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th scope="col">Capacidad</th>
                        <th scope="col">Puesto</th>
                        <th scope="col">Individual</th>
                        <th scope="col">Efectivo</th>
                        <th scope="col">Administración</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($capabilitiesPanel['items'] as $capItem): ?>
                        <tr>
                            <th scope="row">
                                <div><?= $capEsc($capItem['label']) ?></div>
                                <small class="text-body-secondary"><?= $capEsc($capItem['key']) ?> · <?= $capEsc($capItem['description']) ?></small>
                                <?php if (empty($capItem['applicable'])): ?><div><small class="text-body-secondary">No aplicable a este puesto</small></div><?php endif; ?>
                            </th>
                            <td><?= $capEsc($capItem['position_state']) ?></td>
                            <td><?= $capEsc($capItem['individual_state']) ?></td>
                            <td><?= $capEsc($capItem['effective_state']) ?><br><small class="text-body-secondary">Origen: <?= $capEsc($capItem['effective_source']) ?></small></td>
                            <td>
                                <?php if (!empty($capItem['applicable'])): ?>
                                    <form method="post" action="<?= $capEsc($capAction) ?>" class="d-flex flex-column gap-2" aria-label="Cambiar <?= $capEsc($capItem['label']) ?>">
                                        <input type="hidden" name="_csrf_token" value="<?= $capEsc($capabilitiesCsrfToken ?? '') ?>">
                                        <input type="hidden" name="permission_key" value="<?= $capEsc($capItem['key']) ?>">
                                        <input type="hidden" name="expected_state" value="<?= $capEsc($capItem['expected_state']) ?>">
                                        <select class="form-select form-select-sm" name="decision" required <?= empty($capabilitiesPanel['can_write']) ? 'disabled' : '' ?>>
                                            <option value="" selected disabled>Selecciona una decisión</option>
                                            <option value="allow">Permitir</option>
                                            <option value="deny">Denegar</option>
                                            <?php if (!$capPost): ?><option value="inherit" <?= empty($capItem['direct_exists']) ? 'disabled' : '' ?>>Heredar del puesto</option><?php endif; ?>
                                        </select>
                                        <input class="form-control form-control-sm" type="text" name="reason" minlength="5" maxlength="200" required autocomplete="off" placeholder="Motivo (5–200 caracteres)" <?= empty($capabilitiesPanel['can_write']) ? 'disabled' : '' ?>>
                                        <button class="btn btn-sm btn-primary" type="submit" <?= empty($capabilitiesPanel['can_write']) ? 'disabled' : '' ?>>Guardar decisión</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-body-secondary">Sin acción para este puesto</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if (($capabilitiesPanel['history']['status'] ?? '') === 'ready'): ?>
        <div class="card mt-3">
            <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
                <i class="ti ti-history" aria-hidden="true"></i>
                <h6 class="mb-0 text-white">Historial de cambios</h6>
            </div>
            <div class="card-body">
                <?php if (($capabilitiesPanel['history']['items'] ?? []) === []): ?>
                    <p class="mb-0">No hay cambios registrados para este usuario o puesto.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead><tr><th>Fecha UTC</th><th>Administrador</th><th>Capacidad</th><th>Acción</th><th>Motivo</th></tr></thead>
                            <tbody>
                            <?php foreach ($capabilitiesPanel['history']['items'] as $event): ?>
                                <tr>
                                    <td><?= $capEsc($event['created_at_utc'] ?? '') ?></td>
                                    <td><?= $capEsc($event['actor_name'] ?? '') ?></td>
                                    <td><?= $capEsc($event['permission_key'] ?? '') ?></td>
                                    <td><?= $capEsc($event['action'] ?? '') ?></td>
                                    <td><?= $capEsc($event['reason'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif (($capabilitiesPanel['history']['status'] ?? '') === 'unavailable'): ?>
        <p class="small text-body-secondary mt-3 mb-0">El historial no está disponible para consulta.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

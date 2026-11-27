<?php

declare(strict_types=1);

$directoryData = is_array($directory ?? null) ? $directory : [];
$positions = is_array($directoryData['positions'] ?? null) ? $directoryData['positions'] : [];
$users = is_array($directoryData['users'] ?? null) ? $directoryData['users'] : [];
$principalType = is_string($principalType ?? null) ? $principalType : null;
$principalId = is_int($principalId ?? null) ? $principalId : null;
$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$capabilityBase = '/departamento-operativo/recursos-humanos/checador/configuracion/permisos-capacidades';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="card mb-4">
        <div class="card-header bg-primary text-white d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <i class="ti ti-shield-lock" aria-hidden="true"></i>
                <h1 class="h5 mb-0 text-white">Permisos y capacidades del Checador</h1>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($actorAccess['can_write'])): ?>
                <div class="alert alert-info mb-0" role="status">Acceso de consulta. Para modificar grants se requiere el permiso efectivo de edición de Recursos Humanos y alcance global completo del Checador.</div>
            <?php else: ?>
                <p class="mb-0">Los grants por puesto y usuario son globales. Las denegaciones individuales prevalecen sobre el puesto. “Heredar” restaura el permiso efectivo del puesto.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (is_array($capabilitiesFeedback ?? null)): ?>
        <?php
        $feedbackCode = (string) ($capabilitiesFeedback['code'] ?? '');
        $feedbackMessages = [
            'saved' => 'El permiso se guardó y confirmó mediante lectura posterior.',
            'invalid_input' => 'Revisa la capacidad, decisión y motivo. No incluyas PIN, tokens ni secretos.',
            'principal_unavailable' => 'El puesto o usuario ya no está activo.',
            'administrator_permission_denied' => 'No tienes permiso efectivo para administrar estas capacidades.',
            'principal_out_of_scope' => 'No puedes asignar capacidades a tu propio usuario o puesto.',
            'capability_not_applicable' => 'La capacidad seleccionada no aplica a este puesto.',
            'already_inherited' => 'El permiso individual ya hereda el permiso del puesto.',
            'state_stale' => 'El permiso cambió desde que abriste esta pantalla. Actualiza e inténtalo nuevamente.',
            'grant_schema_unavailable' => 'La estructura de permisos no está disponible; no se guardaron cambios.',
            'audit_schema_pending' => 'La auditoría requerida no está disponible; no se guardaron cambios.',
            'permission_write_unavailable' => 'No se pudo confirmar el cambio. No se muestra como guardado.',
        ];
        ?>
        <div class="alert <?= !empty($capabilitiesFeedback['ok']) ? 'alert-success' : 'alert-warning' ?>" role="status">
            <?= $esc($feedbackMessages[$feedbackCode] ?? 'No fue posible completar el cambio de permiso.') ?>
        </div>
    <?php endif; ?>

    <?php if ($principalType !== null && $principalId !== null && is_array($capabilitiesPanel ?? null)): ?>
        <?php require dirname(__DIR__, 3) . '/sistemas/partials/checador-capability-grants-panel.php'; ?>
    <?php else: ?>
        <?php if (($directoryData['ok'] ?? false) !== true): ?>
            <div class="alert alert-warning" role="alert">No fue posible cargar los puestos y usuarios autorizables.</div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-xl-2 g-3">
                <div class="col">
                    <section class="card h-100">
                        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
                            <i class="ti ti-users-group" aria-hidden="true"></i>
                            <h2 class="h6 mb-0 text-white">Administración por puesto</h2>
                        </div>
                        <div class="card-body">
                            <?php if ($positions === []): ?>
                                <p class="mb-0">No hay puestos activos aplicables al catálogo de capacidades.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <thead><tr><th>Puesto</th><th class="text-end">Acción</th></tr></thead>
                                        <tbody><?php foreach ($positions as $position): ?>
                                            <tr>
                                                <td><?= $esc($position['name'] ?? '') ?></td>
                                                <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= $esc($capabilityBase . '/puestos/' . (int) ($position['id'] ?? 0)) ?>">Consultar capacidades</a></td>
                                            </tr>
                                        <?php endforeach; ?></tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
                <div class="col">
                    <section class="card h-100">
                        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
                            <i class="ti ti-user-shield" aria-hidden="true"></i>
                            <h2 class="h6 mb-0 text-white">Administración por usuario</h2>
                        </div>
                        <div class="card-body">
                            <?php if ($users === []): ?>
                                <p class="mb-0">No hay usuarios activos en puestos aplicables al catálogo.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <thead><tr><th>Usuario</th><th>Puesto</th><th class="text-end">Acción</th></tr></thead>
                                        <tbody><?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?= $esc($user['name'] ?? '') ?> <small class="text-body-secondary">(ID <?= (int) ($user['id'] ?? 0) ?>)</small></td>
                                                <td><?= $esc($user['position_name'] ?? '') ?></td>
                                                <td class="text-end"><a class="btn btn-sm btn-primary" href="<?= $esc($capabilityBase . '/usuarios/' . (int) ($user['id'] ?? 0)) ?>">Consultar capacidades</a></td>
                                            </tr>
                                        <?php endforeach; ?></tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
            <?php $globalHistory = is_array($directoryData['history'] ?? null) ? $directoryData['history'] : ['status' => 'unavailable', 'items' => []]; ?>
            <?php if (($globalHistory['status'] ?? '') === 'ready'): ?>
                <section class="card mt-4">
                    <div class="card-header bg-primary text-white d-flex align-items-center gap-2"><i class="ti ti-history" aria-hidden="true"></i><h2 class="h6 mb-0 text-white">Historial de cambios de capacidades</h2></div>
                    <div class="card-body">
                        <?php if (($globalHistory['items'] ?? []) === []): ?>
                            <p class="mb-0">No hay cambios registrados.</p>
                        <?php else: ?>
                            <div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0">
                                <thead><tr><th>Fecha UTC</th><th>Administrador</th><th>Principal</th><th>Capacidad</th><th>Acción</th><th>Motivo</th></tr></thead>
                                <tbody><?php foreach ($globalHistory['items'] as $event): ?>
                                    <tr><td><?= $esc($event['created_at_utc'] ?? '') ?></td><td><?= $esc($event['actor_name'] ?? '') ?></td><td><?= $esc(($event['principal_type'] ?? '') . ' ' . ($event['principal_id'] ?? '')) ?></td><td><?= $esc($event['permission_key'] ?? '') ?></td><td><?= $esc($event['action'] ?? '') ?></td><td><?= $esc($event['reason'] ?? '') ?></td></tr>
                                <?php endforeach; ?></tbody>
                            </table></div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif (($globalHistory['status'] ?? '') === 'unavailable'): ?>
                <div class="alert alert-warning mt-4" role="status">El historial de cambios no está disponible.</div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

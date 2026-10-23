<?php
$workspaceEsc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 border rounded p-3 mb-4" aria-label="Contexto de Checador">
    <div class="d-flex flex-wrap align-items-center gap-2 small" aria-live="polite">
        <span><strong>Centro:</strong> <?= $workspaceEsc(($selectedWorkplaceName ?? '') !== '' ? $selectedWorkplaceName : 'No seleccionado') ?></span>
        <?php if (($selectedWorkplaceId ?? null) !== null): ?><span class="text-muted">· Localidad <?= (int) $selectedWorkplaceId ?></span><?php endif; ?>
        <?php if (($selectedDate ?? '') !== ''): ?><span><strong>Fecha laboral:</strong> <?= $workspaceEsc($selectedDate) ?></span><?php endif; ?>
        <?php if (($selectedWorkerId ?? null) !== null): ?><span><strong>Colaborador:</strong> <?= (int) $selectedWorkerId ?></span><?php endif; ?>
        <?php if (($demoMode ?? false) === true): ?><span class="badge rounded-pill text-bg-secondary" title="Respuestas locales no operativas">Demo</span><?php endif; ?>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $workspaceEsc($dashboardHref ?? '/departamento-operativo/recursos-humanos/checador') ?>">Volver a asistencia diaria</a>
</div>

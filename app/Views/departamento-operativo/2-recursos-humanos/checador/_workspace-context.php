<?php
$workspaceEsc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$workspaceContextWorkerName = trim((string) ($workspaceContextWorkerName ?? ''));
$workspaceContextWorkerNumber = trim((string) ($workspaceContextWorkerNumber ?? ''));
?>
<div class="d-flex flex-wrap align-items-center gap-3 border rounded p-3 mb-4" aria-label="Contexto de Checador">
    <div class="d-flex flex-wrap align-items-center gap-2 small" aria-live="polite">
        <span><strong>Centro:</strong> <?= $workspaceEsc(($selectedWorkplaceName ?? '') !== '' ? $selectedWorkplaceName : 'No seleccionado') ?></span>
        <?php if (($selectedWorkplaceId ?? null) !== null): ?><span class="text-muted">· Localidad <?= (int) $selectedWorkplaceId ?></span><?php endif; ?>
        <?php if (($selectedDate ?? '') !== ''): ?><span><strong>Fecha laboral:</strong> <?= $workspaceEsc($selectedDate) ?></span><?php endif; ?>
        <?php if (($selectedWorkerId ?? null) !== null): ?>
            <span>
                <strong>Colaborador:</strong>
                <?= $workspaceEsc($workspaceContextWorkerName !== '' ? $workspaceContextWorkerName : (string) (int) $selectedWorkerId) ?>
                <?php if ($workspaceContextWorkerName !== '' && $workspaceContextWorkerNumber !== ''): ?><span class="text-muted ms-1">N.º <?= $workspaceEsc($workspaceContextWorkerNumber) ?></span>
                <?php elseif ($workspaceContextWorkerName !== ''): ?><span class="text-muted ms-1">ID <?= (int) $selectedWorkerId ?></span><?php endif; ?>
            </span>
        <?php endif; ?>
        <?php if (($demoMode ?? false) === true): ?><span class="badge rounded-pill text-bg-secondary" title="Respuestas locales no operativas">Demo</span><?php endif; ?>
    </div>
</div>

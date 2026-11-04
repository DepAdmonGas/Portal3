<?php

$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<div id="refacciones-module"
     class="refacciones-module mt-3"
     data-base-url="<?= $escape($baseUrl) ?>"
     data-context-url="<?= $escape($baseUrl) ?>/contexto"
     data-calendar-events="<?= $escape(json_encode($calendarEvents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]') ?>">
    <?php require __DIR__ . '/' . $sectionView . '.php'; ?>
</div>

<?php
$estadoEnvioComentario = (string) ($estadoEnvio ?? 'guardandoComentario');
?>
<template x-if="<?= htmlspecialchars($estadoEnvioComentario, ENT_QUOTES, 'UTF-8') ?>">
<div class="chat-comment-sending-state px-3 pb-3" role="status" aria-live="polite">
<div class="d-flex justify-content-end">
<div class="border border-primary-subtle rounded-4 bg-primary-subtle p-3 shadow-sm" style="max-width: 420px; width: 100%;">
<div class="d-flex align-items-center gap-3">
<div class="flex-shrink-0 rounded-circle bg-white d-flex align-items-center justify-content-center text-primary" style="width: 52px; height: 52px;">
<i class="ti ti-send fs-4"></i>
</div>
<div>
<div class="fw-semibold text-primary">Enviando mensaje...</div>
<div class="small text-muted">Estamos entregando tu comentario</div>
</div>
</div>
<div class="progress rounded-pill mt-3" style="height: 4px;" aria-label="Enviando comentario">
<div class="progress-bar progress-bar-striped progress-bar-animated bg-primary w-100"></div>
</div>
</div>
</div>
</div>
</template>

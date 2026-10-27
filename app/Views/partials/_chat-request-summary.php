<?php
$contextoComentarioExpression = (string)($contextoComentarioExpression ?? 'null');
$contextoComentarioExpression = htmlspecialchars($contextoComentarioExpression, ENT_QUOTES, 'UTF-8');
?>
<div class="px-3 pt-3 pb-3 mb-3 border-bottom flex-shrink-0"
     x-data="{ resumenChatSolicitud: null }"
     x-effect="resumenChatSolicitud = window.resumenContextoComentario(<?= $contextoComentarioExpression ?>)"
     x-show="resumenChatSolicitud"
     x-cloak>
    <div class="card border shadow-sm mb-0">
        <div class="card-header bg-primary-subtle py-2 px-3 d-flex align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2 text-primary fw-semibold">
                <i class="ti ti-list-details fs-5"></i>
                <span>Detalle</span>
            </div>
            <span class="badge text-bg-primary d-inline-flex align-items-center gap-1 px-2 py-1"
                  x-show="resumenChatSolicitud && resumenChatSolicitud.referencia">
                <i class="ti ti-hash"></i>
                <span x-text="resumenChatSolicitud ? resumenChatSolicitud.referencia : ''"></span>
            </span>
        </div>
        <div class="card-body p-3" x-show="resumenChatSolicitud">
            <div class="row g-2" x-show="resumenChatSolicitud && resumenChatSolicitud.campos.length">
                <template x-for="(campo, indice) in (resumenChatSolicitud ? resumenChatSolicitud.campos : [])" :key="indice">
                    <div class="col-6">
                        <div class="border rounded-2 bg-body px-2 py-2">
                            <div class="form-label small mb-0" x-text="campo.etiqueta"></div>
                            <div class="small text-body text-break" x-text="campo.valor"></div>
                        </div>
                    </div>
                </template>
            </div>
            <div class="rounded-2 border bg-light-subtle px-3 py-2"
                 :class="resumenChatSolicitud && resumenChatSolicitud.campos.length ? 'mt-3' : ''"
                 x-show="resumenChatSolicitud && resumenChatSolicitud.descripcion">
                <div class="d-flex align-items-center gap-2 text-primary form-label small mb-0">
                    <i class="ti ti-align-left fs-5"></i>
                    <span x-text="resumenChatSolicitud && resumenChatSolicitud.concepto ? 'Concepto y descripción' : 'Descripción'"></span>
                </div>
                <div class="small text-body" style="max-height:128px; overflow-y:auto; white-space:pre-wrap; overflow-wrap:anywhere;"
                     x-text="resumenChatSolicitud ? resumenChatSolicitud.descripcion : ''"></div>
            </div>
        </div>
    </div>
</div>

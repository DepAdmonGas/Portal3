<div id="container" class="mt-4 mb-5"
     data-id-usuario="<?= $idUsuario ?>"
     data-id-estacion="<?= $idEstacion ?>"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
     data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
     data-puede-crear="<?= $puedeCrear ? 'true' : 'false' ?>"
     data-puede-editar="<?= $puedeEditar ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $puedeEliminar ? 'true' : 'false' ?>"
     data-puede-descargar="<?= $puedeDescargar ? 'true' : 'false' ?>"
     data-id-year="<?= $idYear ?>"
     data-id-mes="<?= $idMes ?>"
     data-year-mes-template="<?= htmlspecialchars($yearMesTemplate, ENT_QUOTES, 'UTF-8') ?>"
     x-data="{ ...actions(), ...mermaComponent() }">

<style>
@media (min-width: 992px) {
    #container .table-responsive { overflow-x: auto; }
}
#container .dropdown-menu { z-index: 9999; }
</style>

<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center mb-3"
             :class="puedeCrear ? 'justify-content-between' : 'justify-content-between'">

            <div class="d-grid d-md-block mb-1 mb-md-0">
                <button type="button" class="btn bg-success-subtle text-success w-100"
                        onclick="descargarExcel()">
                    <i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel
                </button>
            </div>

            <div x-show="puedeCrear" class="d-grid d-md-block ">
                <a href="/departamento-operativo/importacion/formato-descarga-merma-nuevo"
                   class="btn bg-primary-subtle text-primary">
                    <i class="ti ti-plus me-1"></i> Nuevo
                </a>
            </div>

        </div>
    </div>
</div>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-merma" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Offcanvas Comentarios -->
    <div class="offcanvas offcanvas-end d-flex flex-column" tabindex="-1" id="offcanvasComentarios" style="width: 480px; max-height: 100dvh; overflow: hidden;">
        <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">
            <div class="hstack gap-3">
                <div class="position-relative">
                    <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px; height:48px;">
                        <i class="ti ti-message-circle text-primary fs-7"></i>
                    </div>
                </div>
                <div>
                    <h5 class="mb-1 text-white">COMENTARIOS</h5>
                    <p class="mb-0 text-white opacity-75">
                        Formato de Descarga (#00<span x-text="comentarioFolio"></span>)
                    </p>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>

        <style>
            #offcanvasComentarios .actividad-ticket-field {
                min-height: 62px;
                background: var(--bs-body-bg);
                font-size: .82rem;
            }
        </style>
        <div class="px-3 pt-3 pb-3 border-bottom flex-shrink-0"
             x-show="detalleComentarioMerma"
             x-cloak>
            <div class="row g-2">
                <div class="col-4">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-hash fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0">Folio:</div>
                            <div x-text="detalleComentarioMerma ? '00' + detalleComentarioMerma.folio : ''"></div>
                        </div>
                    </div>
                </div>
                <div class="col-8">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-calendar-event fs-5"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="form-label small mb-0">Fecha y hora:</div>
                            <div x-text="detalleComentarioMerma && detalleComentarioMerma.fecha_hora ? detalleComentarioMerma.fecha_hora : 'S/I'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-8">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-info-subtle text-info flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-user fs-5"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="form-label small mb-0">Responsable:</div>
                            <div x-text="detalleComentarioMerma && detalleComentarioMerma.responsable ? detalleComentarioMerma.responsable : 'S/I'"></div>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="actividad-ticket-field border rounded-2 px-2 py-2 d-flex align-items-center gap-2 h-100">
                        <span class="d-inline-flex align-items-center justify-content-center rounded bg-success-subtle text-success flex-shrink-0" style="width:34px;height:34px;">
                            <i class="ti ti-gas-station fs-5"></i>
                        </span>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="form-label small mb-0">Producto:</div>
                            <div x-text="detalleComentarioMerma && detalleComentarioMerma.producto ? detalleComentarioMerma.producto : 'S/I'"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
            <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
                <div class="chat-box-inner p-3 flex-grow-1 overflow-auto"
                     style="min-height: 0; overscroll-behavior: contain;"
                     x-ref="chatContainer">

                    <template x-if="comentarios.length === 0 && !guardandoComentario">
                        <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 360px;">
                            <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                            <p class="text-muted mb-0 fs-5">Sin comentarios</p>
                        </div>
                    </template>

                    <div class="chat-list active-chat p-2">
                <template x-for="c in ordenarComentariosChat(comentarios)" :key="c.id">
                            <div class="d-flex mb-3"
                                 :class="c.esPropio ? 'justify-content-end' : 'justify-content-start'">
                                <template x-if="!c.esPropio">
                                    <div class="d-flex gap-3 align-items-start">
                                        <div class="flex-shrink-0">
                                            <div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px; height:45px;">
                                                <i class="ti ti-user text-white fs-5"></i>
                                            </div>
                                        </div>
                                        <div>
                                            <h6 class="fw-semibold mb-1" x-text="c.usuario_nombre || 'Usuario'"></h6>
                                            <div class="fs-3 text-muted mb-1" x-text="c.fecha_hora || ''"></div>
                                            <div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="c.esPropio">
                                    <div class="d-flex flex-column align-items-end">
                                        <div class="fs-3 text-muted mb-1 text-end" x-text="c.fecha_hora || ''"></div>
                                        <div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width: 420px;" x-text="c.comentario"></div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>

</div>
            </div>
        </div>
        <?php \App\Core\View::renderPartial('partials/_chat-comment-sending', ['estadoEnvio' => 'guardandoComentario']); ?>





        <div class="px-3 py-3 border-top bg-white flex-shrink-0">
            <div class="d-flex align-items-center gap-2">
                <div class="flex-grow-1">
                    <textarea class="form-control border-0 bg-light rounded-pill px-3 py-2"
                              rows="1"
                              placeholder="Escribe un comentario..."
                              style="resize:none;"
                              x-model="nuevoComentario"
                              @keydown.enter.prevent="agregarComentario()"></textarea>
                </div>
                <div class="flex-shrink-0">
                    <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
                            style="width:44px; height:44px;"
                            type="button"
                            @click="agregarComentario()"
                            :disabled="guardandoComentario || !nuevoComentario.trim()">
                        <template x-if="!guardandoComentario">
                            <i class="ti ti-send fs-5"></i>
                        </template>
                        <template x-if="guardandoComentario">
                            <span class="spinner-border spinner-border-sm"></span>
                        </template>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

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
        <div class="d-flex mb-3" :class="puedeCrear ? 'justify-content-between' : 'justify-content-end'">

            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn bg-success-subtle text-success" onclick="descargarExcel()">
                    <i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel
                </button>
            </div>

            <div x-show="puedeCrear">
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

        <div class="d-flex flex-column flex-grow-1 overflow-hidden" style="min-height: 0;">
            <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height: 0;">
                <div class="chat-box-inner p-3 flex-grow-1 overflow-auto"
                     style="min-height: 0; overscroll-behavior: contain;"
                     x-ref="chatContainer">

                    <template x-if="comentarios.length === 0">
                        <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 360px;">
                            <i class="ti ti-message-off text-muted mb-2" style="font-size: 55px;"></i>
                            <p class="text-muted mb-0 fs-5">Sin comentarios</p>
                        </div>
                    </template>

                    <div class="chat-list active-chat p-2">
                        <template x-for="c in comentarios" :key="c.id">
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

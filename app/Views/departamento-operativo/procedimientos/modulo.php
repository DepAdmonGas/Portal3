<div id="procedimientos-do"
     data-modulo="<?= $modulo ?>"
     data-modulo-nombre="<?= htmlspecialchars($moduloNombre, ENT_QUOTES, 'UTF-8') ?>"
     x-data="{ ...actions(), ...procedimientosDoComponent() }"
     @procedimientos-do:detalle.window="verDetalle($event.detail)"
     @procedimientos-do:eliminar.window="eliminar($event.detail)">

    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
        <button type="button"
                class="btn bg-primary-subtle text-primary"
                @click="abrirAgregar()">
            <i class="ti ti-plus me-1"></i> Nuevo
        </button>
    </div>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-procedimientos-do" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modalProcedimiento" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
 
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white">
                        <i class="ti ti-clipboard-text me-1"></i> Nuevo procedimiento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form novalidate @submit.prevent="guardar()">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="procedimiento-fecha">* Fecha:</label>
                                <input type="date"
                                       class="form-control"
                                       :class="{ 'is-invalid': errores.fecha }"
                                       id="procedimiento-fecha"
                                       required
                                       x-model="form.fecha"
                                       @input="errores.fecha = ''">
                                <div class="invalid-feedback" x-show="errores.fecha" x-text="errores.fecha"></div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="procedimiento-titulo">* Título:</label>
                                <input type="text"
                                       class="form-control"
                                       :class="{ 'is-invalid': errores.titulo }"
                                       id="procedimiento-titulo"
                                       maxlength="500"
                                       required
                                       x-model="form.titulo"
                                       @input="errores.titulo = ''">
                                <div class="invalid-feedback" x-show="errores.titulo" x-text="errores.titulo"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="procedimiento-documento">* Documento:</label>
                                <input type="file"
                                       class="form-control"
                                       :class="{ 'is-invalid': errores.documento }"
                                       id="procedimiento-documento"
                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.gif,.webp,.txt,.csv,.odt,.ods,.odp"
                                       required
                                       x-ref="documento"
                                       @change="errores.documento = ''">
                                <div class="invalid-feedback" x-show="errores.documento" x-text="errores.documento"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
         <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                                  <i class="ti ti-x"></i> Cancelar
                              </button>
                        <button type="submit" class="btn btn-success" :disabled="loading">
                            <span class="spinner-border spinner-border-sm me-1" x-show="loading"></span>
                            <i class="ti ti-check"></i> Guardar
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <div class="modal fade" id="modalProcedimientoDetalle" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white">
                        <i class="ti ti-eye me-1"></i> Detalle del procedimiento
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <span class="form-label">Título:</span>
                            <div x-text="detalle.titulo"></div>
                        </div>
                        <div class="col-md-4">
                            <span class="form-label">Fecha:</span>
                            <div x-text="detalle.fecha"></div>
                        </div>
                    </div>

                                            <span class="form-label mb-3">Documento:</span>
                    <template x-if="detalle.previsualizable">

                        <iframe :src="detalle.previewUrl" class="border-0 w-100 mt-2 rounded" height="650" title="Documento del procedimiento"></iframe>
                    </template>

                    <template x-if="!detalle.previsualizable">
                        <div class="text-center py-5">
                            <i class="ti ti-file-off fs-8 text-muted d-block mb-2"></i>
                            <p class="text-muted mb-0" x-text="detalle.mensajeArchivo"></p>
                        </div>
                    </template>
                </div>

                <div class="modal-footer">
         <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                                  <i class="ti ti-x"></i> Cancelar
                              </button>

                    <button type="button"
                            class="btn btn-danger"
                            @click="eliminar(detalle)"
                            x-show="detalle.id">
                        <i class="ti ti-trash me-1"></i> Eliminar
                    </button>

                                                  <a class="btn bg-primary-subtle text-primary"
                       :href="detalle.downloadUrl"
                       x-show="detalle.archivo_disponible">
                        <i class="ti ti-download me-1"></i> Descargar
                    </a>

                </div>

            </div>
        </div>
    </div>

</div>

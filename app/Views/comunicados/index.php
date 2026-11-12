<!-- Comunicados -->
<div id="comunicados"
     class="mt-3 mb-4"
     x-data="{ ...actions(), ...comunicadosComponent() }"
     data-tipo-descarga="<?= htmlspecialchars($tipoDescarga, ENT_QUOTES, 'UTF-8') ?>"
     data-puede-agregar="<?= $permisos['puede_agregar'] ? 'true' : 'false' ?>"
     data-puede-editar="<?= $permisos['puede_editar'] ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $permisos['puede_eliminar'] ? 'true' : 'false' ?>"
     data-puede-descargar="<?= $permisos['puede_descargar'] ? 'true' : 'false' ?>"
     data-puede-acusar="<?= $permisos['puede_acusar'] ? 'true' : 'false' ?>"
     data-es-admin="<?= $permisos['es_admin'] ? 'true' : 'false' ?>">

    <div class="row g-3">

        <div class="col-12">
            <button type="button"
                    class="btn bg-primary-subtle text-primary float-end"
                    id="com-btn-agregar"
                    x-show="puedeAgregar"
                    @click="abrirModalAgregar()">
                <i class="ti ti-plus"></i> Nuevo
            </button>
        </div>

        <div class="table-responsive pb-5">
            <table id="tabla-comunicados"
                   class="table table-striped table-bordered mb-0 text-nowrap align-middle"></table>
        </div>
    </div>

    <!-- Modal: alta / edición de comunicado -->
    <div class="modal fade" id="modalComunicado" tabindex="-1"
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <i :class="modo === 'editar' ? 'ti ti-pencil me-2' : 'ti ti-speakerphone me-2'"></i>
                    <h5 class="modal-title text-white" x-text="modo === 'editar' ? 'Editar comunicado' : 'Nuevo comunicado'">Comunicado</h5>
                    <button type="button" class="btn-close btn-close-white"
                            data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="row">

                        <div class="col-12 mb-3">
                            <label class="form-label">* Fecha:</label>
                            <input type="date" class="form-control"
                                   :class="{ 'is-invalid': errores.fecha }"
                                   x-model="form.fecha">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">* Título:</label>
                            <input type="text" class="form-control"
                                   :class="{ 'is-invalid': errores.titulo }"
                                   x-model="form.titulo">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">* Documento adjunto:</label>

                            <template x-if="modo === 'editar' && form.archivo_actual">
                                <div class="mb-2">
                                    <a class="pointer text-primary small"
                                       :href="form.archivo_url" target="_blank">
                                        <i class="ti ti-paperclip me-1"></i>
                                        <span x-text="form.archivo_actual"></span>
                                    </a>
                                </div>
                            </template>

                            <input type="file" class="form-control mb-2"
                                   :class="{ 'is-invalid': errores.archivo }"
                                   x-ref="inputArchivo">
                            <div class="form-text" x-show="modo === 'editar'">
                                Deja el campo vacío para conservar el documento actual.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid me-0 me-md-2 mb-1 mb-md-0">
                            <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                                <i class="ti ti-x"></i> Cancelar
                            </button>
                        </div>
                        <div class="col-12 col-md-auto d-grid">
                            <button type="button" class="btn btn-success"
                                    @click="guardar()"
                                    :disabled="guardando">
                                <i class="ti ti-check"></i> Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: detalle de comunicado -->
    <div class="modal fade" id="modalDetalleComunicado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h4 class="modal-title text-white d-flex align-items-center gap-2">
                        <i class="ti ti-eye"></i> Detalle del comunicado
                    </h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="row" x-show="detalle">
                        <div class="col-xl-8 col-md-12 mb-3">
                            <label class="mb-1 form-label">Título:</label>
                            <div x-text="detalle ? detalle.titulo : ''"></div>
                        </div>

                        <div class="col-xl-4 col-md-12 mb-3">
                            <label class="mb-1 form-label">Fecha:</label>
                            <div x-text="detalle ? detalle.fecha_fmt : ''"></div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="mb-1 form-label">Documento adjunto:</label>

                            <template x-if="detalle && detalle.existe_archivo">
                                <div>
                                    <div class="d-flex justify-content-start mb-2">
                                        <a class="btn btn-primary"
                                           :href="detalle.archivo_url" download>
                                            <i class="ti ti-download me-1"></i> Descargar
                                        </a>
                                    </div>
                                    <iframe class="border-0"
                                            :src="detalle.archivo_url + '&view=1'"
                                            style="width:100%;height:450px;border:1px solid #dee2e6;border-radius:.375rem;background:#f8f9fa;"></iframe>
                                </div>
                            </template>

                            <template x-if="detalle && !detalle.existe_archivo">
                                <div>No se encontro información</div>
                            </template>
                        </div>

                        <div class="col-12" x-show="esAdmin && detalle && detalle.acuses && detalle.acuses.length > 0">
                            <label class="mb-1 form-label">Personal que visualizó el comunicado:</label>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th class="text-start">Nombre</th>
                                            <th class="text-center">Estación</th>
                                            <th class="text-center">Fecha de visualización</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(acuse, index) in (detalle ? detalle.acuses : [])" :key="index">
                                            <tr>
                                                <td class="text-start" x-text="acuse.usuario"></td>
                                                <td class="text-center" x-text="acuse.estacion"></td>
                                                <td class="text-center" x-text="acuse.fecha_fmt"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="text-center py-4" x-show="cargandoDetalle">
                        <span class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>

                <div class="modal-footer">
                    <template x-if="detalle && !detalle.acusado && puedeAcusar">
                        <button type="button" class="btn btn-success"
                                @click="confirmarAcuse()"
                                :disabled="guardando">
                            <i class="ti ti-check"></i> De Acuerdo
                        </button>
                    </template>
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

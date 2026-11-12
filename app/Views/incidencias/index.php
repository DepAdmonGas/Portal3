<!-- Incidencias -->
<div id="incidencias"
     class="mt-3 mb-4"
     x-data="{ ...actions(), ...incidenciasComponent() }"
     data-modulo="<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>"
     data-tipo-descarga="<?= htmlspecialchars($tipoDescarga, ENT_QUOTES, 'UTF-8') ?>"
     data-puede-agregar="<?= $permisos['puede_agregar'] ? 'true' : 'false' ?>"
     data-puede-editar="<?= $permisos['puede_editar'] ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $permisos['puede_eliminar'] ? 'true' : 'false' ?>"
     data-puede-descargar="<?= $permisos['puede_descargar'] ? 'true' : 'false' ?>"
     data-mostrar-estacion="<?= $mostrarFilaEstacion ? 'true' : 'false' ?>"
     data-id-estacion="<?= $estacionContexto !== null ? (int) $estacionContexto : '' ?>"
     data-estaciones="<?= htmlspecialchars(json_encode($estaciones, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">

    <?php /* El selector de estación lo imprime el layout 'main' a partir de
            moduleStationKey; ver ModuleStationService::render(). */ ?>

    <div class="row g-3">

        <div class="col-12">
            <?php /* Disponible para todo usuario con acceso al módulo. Si el
                    selector no fija una estación, el formulario la pide. */ ?>
            <button type="button"
                    class="btn bg-primary-subtle text-primary float-end"
                    id="inc-btn-agregar"
                    x-show="puedeAgregar"
                    @click="abrirModalAgregar()">
                <i class="ti ti-plus"></i> Nuevo
            </button>
        </div>

        <div class="table-responsive pb-5">
            <table id="tabla-incidencias"
                   class="table table-striped table-bordered mb-0 text-nowrap align-middle"></table>
        </div>
    </div>

    <!-- Modal: alta / edición de incidencia -->
    <div class="modal fade" id="modalIncidencia" tabindex="-1"
         data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
<div class="modal-header modal-colored-header bg-primary text-white">
    <i :class="modo === 'editar' ? 'ti ti-pencil me-2' : 'ti ti-alert-triangle me-2'"></i>
    <h5 class="modal-title text-white" x-text="modo === 'editar' ? 'Editar incidencia' : 'Nueva incidencia'">Incidencia</h5>
    <button type="button" class="btn-close btn-close-white"
            data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>

                <div class="modal-body">
                    <div class="row">

                        <div class="col-xl-6 col-md-6 mb-3">
                            <label class="form-label">* Fecha:</label>
                            <input type="date" class="form-control"
                                   :class="{ 'is-invalid': errores.fecha }"
                                   x-model="form.fecha">
                        </div>

                        <div class="col-xl-6 col-md-6 mb-3">
                            <label class="form-label">* Hora:</label>
                            <input type="time" class="form-control"
                                   :class="{ 'is-invalid': errores.hora }"
                                   x-model="form.hora">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">* Incidente:</label>
                            <input type="text" class="form-control"
                                   :class="{ 'is-invalid': errores.incidente }"
                                   x-model="form.incidente">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">* Responsable:</label>
                            <input type="text" class="form-control"
                                   :class="{ 'is-invalid': errores.responsable }"
                                   x-model="form.responsable">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">* Asunto:</label>
                            <input type="text" class="form-control"
                                   :class="{ 'is-invalid': errores.asunto }"
                                   x-model="form.asunto">
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">* Comentarios:</label>
                            <textarea class="form-control" rows="4"
                                      :class="{ 'is-invalid': errores.comentarios }"
                                      x-model="form.comentarios"></textarea>
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
                                Deja el campo vacío para conservar el archivo actual.
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

    <!-- Modal: detalle de incidencia -->
    <div class="modal fade" id="modalDetalleIncidencia" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h4 class="modal-title text-white d-flex align-items-center gap-2">
                        <i class="ti ti-eye"></i> Detalle de la incidencia
                    </h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="row" x-show="detalle">
                        <div class="col-12 mb-3">
                            <label class="mb-1 form-label">Fecha y hora:</label>
                            <div x-text="detalle ? detalle.fecha_hora_fmt : ''"></div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="mb-1 form-label">Incidente:</label>
                            <div x-text="detalle ? detalle.incidente : ''"></div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="mb-1 form-label">Responsable:</label>
                            <div x-text="detalle ? detalle.responsable : ''"></div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="mb-1 form-label">Asunto:</label>
                            <div x-text="detalle ? detalle.asunto : ''"></div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="mb-1 form-label">Comentarios:</label>
                            <div class="d-block" x-text="detalle ? detalle.comentarios : ''"></div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="mb-1 form-label">Documnento adjunto:</label>

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
                                <label>Sin información</label>
                            </template>
                        </div>
                    </div>

                    <div class="text-center py-4" x-show="cargandoDetalle">
                        <span class="spinner-border spinner-border-sm"></span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

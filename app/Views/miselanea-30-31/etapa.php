<!-- Miscelánea 30, 31 — <?= htmlspecialchars($etapa['titulo'], ENT_QUOTES, 'UTF-8') ?> -->
<div id="miselanea-30-31"
     x-data="{ ...actions(), ...miselaneaComponent() }"
     data-year="<?= (int) $year ?>"
     data-etapa="<?= htmlspecialchars($etapa['slug'], ENT_QUOTES, 'UTF-8') ?>"
     data-categoria="<?= (int) $etapa['categoria'] ?>"
     data-id-estacion="<?= $idEstacion !== null ? (int) $idEstacion : '' ?>"
     data-estacion="<?= htmlspecialchars($estacion, ENT_QUOTES, 'UTF-8') ?>"
     data-puede-agregar="<?= $permisos['puede_agregar'] ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $permisos['puede_eliminar'] ? 'true' : 'false' ?>">


    <?php if ($idEstacion === null): ?>

        <div id="miselanea-empty-message"
             class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
            Debes de seleccionar una estación del menú superior para poder visualizar MISELANEA 30, 31.
        </div>

    <?php else: ?>

<div class="alert alert-primary mt-3 py-4 text-center" role="alert">
<?= htmlspecialchars($etapa['descripcion'], ENT_QUOTES, 'UTF-8') ?>
</div>

<div class="table-responsive pb-5 mt-4">
<table id="tabla-miselanea-documentos" class="table table-striped table-bordered mb-0 align-middle w-100"></table>
</div>

        <!-- Modal: archivos del documento -->
        <div class="modal fade" id="modalMiselaneaDocumento" tabindex="-1"
             data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header modal-colored-header bg-primary text-white">
                        <h5 class="modal-title text-white"
                            x-text="tituloDocumento">Documento</h5>
                        <button type="button" class="btn-close btn-close-white"
                                data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <template x-if="puedeAgregar">
                            <div class="row align-items-end mb-3">
                                <div class="col-12 mb-3">
                                    <label class="form-label mb-1">* Detalle:</label>
                                    <textarea class="form-control" rows="1"
                                              :class="{ 'is-invalid': errores.detalle }"
                                              x-model="form.detalle"></textarea>
                                </div>

                                <div class="col-12 mb-3">
    <label class="form-label mb-1">* Documento:</label>
    
    <div class="input-group">
        <!-- Input de archivo -->
        <input type="file" class="form-control"
               :class="{ 'is-invalid': errores.archivo }"
               x-ref="archivo">
               
        <!-- Botón unido -->
        <button type="button"
                class="btn bg-primary-subtle text-primary"
                @click="agregarArchivo()"
                :disabled="guardando">
            <i class="ti ti-plus"></i> <span>Nuevo</span>
        </button>
    </div>
</div>

          
                            </div>
                        </template>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-start">Detalle</th>
                                        <th class="text-center">Fecha</th>
                                        <th class="text-center" width="48px">
                                            <i class="ti ti-download text-primary fs-6"></i>
                                        </th>
                                        <th class="text-center" width="48px"
                                            x-show="puedeEliminar">
                                            <i class="ti ti-trash fs-6 text-danger"></i>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="arch in archivos" :key="arch.id">
                                        <tr>
                                            <td class="text-start" x-text="arch.detalle"></td>
                                            <td class="text-center text-nowrap" x-text="arch.fecha_fmt"></td>
                                            <td class="text-center">
                                                <a class="pointer" x-show="arch.existe_archivo"
                                                   @click="download(tipoDescarga, arch.archivo)"
                                                   title="Descargar">
                                                    <i class="ti ti-download fs-6 text-primary"></i>
                                                </a>
                                                <span class="text-muted" x-show="!arch.existe_archivo"><i class="ti ti-file-off fs-6 text-muted"></i></span>
                                            </td>
                                            <td class="text-center" x-show="puedeEliminar">
                                                <a class="pointer"
                                                   @click="eliminarArchivo(arch.id, arch.detalle)"
                                                   title="Eliminar">
                                                    <i class="ti ti-trash fs-6 text-danger"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    </template>

                                    <template x-if="archivos.length === 0">
                                        <tr>
                                            <td :colspan="puedeEliminar ? 4 : 3"
                                                class="text-center text-primary">
                                                No se encontró información
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-center py-4" x-show="cargando">
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

    <?php endif; ?>

</div>

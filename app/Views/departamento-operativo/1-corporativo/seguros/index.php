<div id="container" class="pb-4"
    data-module-station-key="seguros"
    x-data="{ ...actions(), ...segurosComponent() }"
    @seguros:detalle.window="abrirDetalle($event.detail.id)"
    @seguros:editar.window="abrirModalEditar($event.detail.id)"
    @seguros:eliminar.window="eliminarIncidencia($event.detail.id)">

    <div id="seguros-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4" style="display:<?= ($idEstacion || $global) ? 'none' : '' ?>">
        Debes de seleccionar una estación del menú superior para poder visualizar los registros de seguros.
    </div>

    <div id="seguros-content" style="display:<?= ($idEstacion || $global) ? '' : 'none' ?>">
        <div class="text-end mt-3 mb-3">
            <div class="text-end">
                <div class="btn-group" id="seguros-acciones" style="display:<?= $global ? 'none' : '' ?>">
                    <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="ti ti-dots-vertical fs-4"></i>
                    </button>
                    <ul class="dropdown-menu animated rubberBand pointer">
                        <li x-show="permisos.id_puesto !== 6"><a class="dropdown-item pointer" @click="abrirModalAgregar()"><i class="ti ti-plus"></i> Nuevo</a></li>
                        <li><a class="dropdown-item pointer" @click="abrirModalPoliza()"><i class="ti ti-file-text"></i> Póliza de Seguro</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="table-responsive pb-5 overflow-x-auto overflow-y-hidden" >
            <table id="tabla-seguros" class="table table-striped table-bordered mb-0 align-middle seguros-seleccionable portal-table portal-table--selectable">
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="modal-detalle" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" >
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white">
                        <i class="ti ti-eye"></i>
                    Detalle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">

                    <div class="row">

                        <div class="col-6 col-md-12">
                            <label class="form-label fw-semibold mb-1">Fecha:</label>
                            <p class="mb-3" x-text="detalle.fecha"></p>
                        </div>

                        <div class="col-6 col-md-12">
                            <label class="form-label fw-semibold mb-1">Hora:</label>
                            <p class="mb-3" x-text="detalle.hora"></p>
                        </div>

                        <div class="col-6 col-md-12">
                            <label class="form-label fw-semibold mb-1">Asunto:</label>
                            <p class="mb-3" x-text="detalle.asunto"></p>
                        </div>

                        <div class="col-6 col-md-12">
                            <label class="form-label fw-semibold mb-1">Solución / Resultados Finales:</label>
                            <p class="mb-3" x-text="detalle.solucion"></p>
                        </div>

                        <div class="col-6 col-md-12">
                            <label class="form-label fw-semibold mb-3">Solución / Resultados Finales:</label>
                            <p class="mb-3" x-text="detalle.solucion"></p>
                        </div>


                        <div class="col-12">
                            <label class="form-label fw-semibold mb-1">Observaciones:</label>
                            <p class="mb-3" x-text="detalle.observaciones"></p>
                        </div>

                    </div>

                        <label class="form-label fw-semibold mb-2">Evidencia:</label>
                        <template x-if="detalle.archivo">
                            <iframe class="border-0 mt-1" :src="'/download?tipo=seguros-incidencias&file=' + encodeURIComponent(detalle.archivo) + '&view=1'" width="100%" height="300px"></iframe>
                        </template>
                        <template x-if="!detalle.archivo">
                            <p class="text-muted">S/I</p>
                        </template>
                   
                </div>

                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid">
   <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                    <i class="ti ti-x"></i>    
                    Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>





        </div>
    </div>

    <div class="modal fade" id="modal-incidencia" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false"
        @hidden.bs.modal="resetModalIncidencia()">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"> 
                    <i class="ti" :class="incidenciaModo === 'editar' ? 'ti-edit' : 'ti-clipboard-text'"></i>
                    <span x-text="incidenciaModalTitle"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">* Fecha:</label>
                        <input type="date" class="form-control" x-model="incidenciaForm.fecha" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">* Hora:</label>
                        <input type="time" class="form-control" x-model="incidenciaForm.hora" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">* Asunto:</label>
                        <input type="text" class="form-control" x-model="incidenciaForm.asunto" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">* Observaciones:</label>
                        <textarea class="form-control" x-model="incidenciaForm.observaciones" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">* Solución / Resultados Finales:</label>
                        <textarea class="form-control" x-model="incidenciaForm.solucion" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold mb-1">* Evidencia:</label>
                        <input type="file" class="form-control" x-ref="incidenciaFileInput">
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                        <div class="col-12 col-md-auto d-grid me-0 me-md-2">
<button type="button" class="btn bg-danger-subtle text-danger" @click="cancelarEdicionIncidencia" :disabled="loading">
<i class="ti ti-x"></i>    
Cancelar</button>
                        </div>
                        <div class="col-12 col-md-auto d-grid mt-1 mt-md-0">
 <button type="button" class="btn btn-success" @click="guardarIncidencia" :disabled="loading">
<i class="ti ti-check"></i>
 Guardar</button>
                        </div>
                    </div>
                   </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-poliza" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" @hidden.bs.modal="resetModalPoliza()">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white">
                    <i class="ti ti-file-text-shield"></i> 
                    Póliza de Seguro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">* Fecha de Emisión:</label>
                    <input type="date" class="form-control mb-3" x-model="polizaForm.emision" @change="calcularVencimiento">

                    <label class="form-label fw-semibold">* Fecha de Vencimiento:</label>
                    <template x-if="!polizaForm.vencimiento">
                        <div class="text-muted mb-3">S/I</div>
                    </template>
                    <template x-if="polizaForm.vencimiento">
                        <input type="date" class="form-control mb-3" x-model="polizaForm.vencimiento">
                    </template>
                    <label class="form-label fw-semibold">* Documento:</label>
                    <input type="file" class="form-control mb-3" x-ref="polizaFileInput">

             

                    <div x-show="polizaModo !== 'editar'" class="table-responsive overflow-x-auto overflow-y-hidden">
                        <table class="table table-striped table-bordered mb-0 text-nowrap align-middle mt-3 seguros-seleccionable portal-table portal-table--selectable">
                            <thead>
                                <tr>
                                    <th class="text-center align-middle">Fecha de emisión</th>
                                    <th class="text-center align-middle">Fecha de vencimiento</th>
                                    <th class="text-center align-middle" width="48px"><i class="ti ti-file-download text-primary fs-6"></i></th>
                                    <th class="text-center align-middle" width="48px"><i class="ti ti-pencil text-warning fs-6"></i></th>
                                    <th class="text-center align-middle" width="48px"><i class="ti ti-trash text-danger  fs-6"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="p in polizas" :key="p.id">
                                    <tr class="pointer" :class="{ selected: selectedPolizaId === p.id }" @click="seleccionarPoliza(p.id)">
                                        <td class="text-center align-middle" x-text="p.emision"></td>
                                        <td class="text-center align-middle" x-text="p.vencimiento"></td>
                                        <td class="text-center align-middle">
                                            <template x-if="p.archivo">
                                                <a href="" @click.prevent="download('seguros-polizas', p.archivo)"><i class="ti ti-file-download text-primary fs-6"></i></a>
                                            </template>
                                            <template x-if="!p.archivo">
                                                <i class="ti ti-ban text-muted"></i>
                                            </template>
                                        </td>
                                        <td class="text-center align-middle">
                                            <a class="pointer" @click="editarPoliza(p.id)" ><i class="ti ti-pencil fs-6 text-warning"></i></a>
                                        </td>
                                        <td class="text-center align-middle">
                                            <a class="pointer" @click="eliminarPoliza(p.id)"><i class="ti ti-trash fs-6 text-danger"></i></a>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="polizas.length === 0">
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-2 align-middle">
                                            <small>No se encontró información para mostrar</small>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

<div class="modal-footer">
    <div class="row g-2 ms-auto w-100 justify-content-end">

    <div class="col-12 col-md-auto d-grid">
    <button
        type="button"
        class="btn bg-danger-subtle text-danger"
        @click="polizaModo === 'editar' ? cancelarEdicionPoliza() : null"
        :data-bs-dismiss="polizaModo === 'agregar' ? 'modal' : null"
        :disabled="loading">
        <i class="ti ti-x"></i>
        Cancelar
    </button>
</div>

        <!-- Un solo botón Guardar -->
        <div class="col-12 col-md-auto d-grid">
            <button
                type="button"
                class="btn btn-success"
                @click="guardarPoliza"
                :disabled="loading">
                <i class="ti ti-check"></i>
                <span x-text="loading ? 'Guardando...' : 'Guardar'"></span>
            </button>
        </div>

    </div>
</div>

                
            </div>
        </div>
    </div>

</div>

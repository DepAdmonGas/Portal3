<div id="container" class="pb-4"
    data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
    data-estacion-id="<?= (int) ($estacionId ?? 0) ?>">

<?php if (empty($estacionId)): ?>

    <div id="sgm-empty-message"
        class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SGM.
    </div>

<?php else: ?>

<div id="sgm-content" x-data="{ ...actions(), ...requisitosLegalesForm()}" data-ngobierno="<?= $title ?>" data-modulo="1">

    <div class="text-end mt-2">
          <?= 
          !empty($permisos['crear']) ? 
          '<button type="button" class="btn btn-primary" @click="openNuevo()">
          <i class="ti ti-plus"></i> Nuevo
          </button>' 
          : '' 
          ?>   
    </div>

      <div class="datatables mt-4">
    <div class="table-responsive">
      <table id="table-lista-requisitos-legales-detalle" class="table table-bordered table-striped mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>Dependencia</th>
            <th>Permiso</th>
            <th>Vigencia</th>
            <th>Fecha emisión</th>
            <th>Fecha vencimiento</th>
            <th>Acuse</th>
            <th>Requisito Legal</th>
            <th>% Cumplimiento</th>
            <th>Renovacion</th>
            <th>Estatus</th>
          <th class="text-center">
          <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <div class="mb-2"><small>% de cumplimiento por nivel de gobierno</small></div>
    <div class="progress" style="height: 20px;">
    <?php 
    $nGobierno = $title; 
    $cumplimiento = round($requisitos[$nGobierno]['Cumplimiento'] ?? 0); 
    ?>
        <div 
            id="cumplimiento-progress-bar"
            class="progress-bar progress-bar-striped progress-bar-animated 
            <?= $cumplimiento == 100 ? 'text-bg-success' : ($cumplimiento >= 50 ? 'text-bg-warning' : 'text-bg-danger') ?>"
            role="progressbar"
            aria-valuenow="<?= $cumplimiento ?>"
            aria-valuemin="0"
            aria-valuemax="100"
            style="width: <?= $cumplimiento ?>%;">
            
            <span id="cumplimiento-progress-label">Cumple <?= $cumplimiento ?>%</span>

        </div>
    </div>

    <!-- MODAL NUEVO-->
    <div class="modal fade"
        id="nuevo"
        x-ref="modalNuevo"
        x-init="getPermisos()"
        tabindex="-1">

        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
            <div class="modal-content">

                <!-- HEADER -->
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" x-text="mode === 'edit' ? 'Editar requisito legal' : 'Agregar requisito legal'"></h4>
                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"
                            @click="resetModal()">
                    </button>
                </div>

                <!-- BODY -->
                <div class="modal-body">

                    

                    <!-- Perniso -->
                    <label class="form-label mt-3">* Permiso:</label>
                    <div class="select2-modal-field is-select2-pending"
                        x-ref="permisoWrapper"
                        :class="errors.permiso ? 'is-invalid' : ''">
                        <select id="selectPermiso"
                                x-ref="selectPermiso"
                                data-width="100%">
                    
                        <option value="">Seleccione</option>

                        <template x-for="e in permisos" :key="e.id">
                            <option :value="e.id" x-text="e.permiso"></option>
                        </template>
                    </select>
                    </div>

                    <!-- Vigencia -->
                    <label class="form-label mt-3">* Vigencia:</label>
                    <select class="form-control"
                            x-model="vigencia"
                            @change="errors.vigencia = false"
                            :class="errors.vigencia ? 'is-invalid' : ''">
                        <option value="">Seleccione</option>
                        <option value="Anual">Anual</option>
                        <option value="Bianual">Bianual</option>
                        <option value="Permanente">Permanente</option>
                        <option value="Trimestral">Trimestral</option>
                        <option value="Diario">Diario</option>
                        <option value="Cuando se realice cambio">Cuando se realice cambio</option>
                        <option value="Semestral">Semestral</option>
                        <option value="Mejora continua">Mejora continua</option>
                        <option value="3 años">3 años</option>
                        <option value="5 años">5 años</option>
                        <option value="10 años">10 años</option>
                        <option value="30 años">30 años</option>
                    </select>

                    <div class="row" x-show="mode === 'create'">
                        <div class="col-lg-6 col-md-12">
                            <label class="form-label mt-3">Fecha de emisión</label>
                            <input type="date" class="form-control" x-model="fechaemision"
                            @change="errors.fechaemision = false"
                            :class="errors.fechaemision ? 'is-invalid' : ''">
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <label class="form-label mt-3">Fecha de vencimiento</label>
                            <input type="date" class="form-control" x-model="fechavencimiento" readonly>
                        </div>

                        <div class="col-lg-6 col-md-12">
                            <label class="form-label mt-3">Acuse PDF</label>
                            <input class="form-control" type="file" x-ref="acusePDF">
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <label class="form-label mt-3">Requisito Legal PDF</label>
                            <input class="form-control" type="file" x-ref="requisitoPDF">
                        </div>
                    </div>

                <label class="form-label mt-3">Renovación</label>

                <table class="table table-sm table-bordered">
                <tbody>
                    <tr class="font-weight-bold">
                    <td class="text-center align-middle bg-light text-black">Ene</td>
                    <td class="text-center align-middle bg-light text-black">Feb</td>
                    <td class="text-center align-middle bg-light text-black">Mar</td>
                    <td class="text-center align-middle bg-light text-black">Abr</td>
                    <td class="text-center align-middle bg-light text-black">May</td>
                    <td class="text-center align-middle bg-light text-black">Jun</td>
                    <td class="text-center align-middle bg-light text-black">Jul</td>
                    <td class="text-center align-middle bg-light text-black">Ago</td>
                    <td class="text-center align-middle bg-light text-black">Sep</td>
                    <td class="text-center align-middle bg-light text-black">Oct</td>
                    <td class="text-center align-middle bg-light text-black">Nov</td>
                    <td class="text-center align-middle bg-light text-black">Dic</td>   
                </tr> 
                <tr>
                    <td class="text-center align-middle"><input type="checkbox" id="ene" x-ref="ene" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="feb" x-ref="feb" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="mar" x-ref="mar" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="abr" x-ref="abr" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="may" x-ref="may" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="jun" x-ref="jun" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="jul" x-ref="jul" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="ago" x-ref="ago" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="sep" x-ref="sep" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="oct" x-ref="oct" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="nov" x-ref="nov" style="zoom: 150%;"></td>
                    <td class="text-center align-middle"><input type="checkbox" id="dic" x-ref="dic" style="zoom: 150%;"></td>
                </tr>
                </tbody>
                </table>

                </div>

                <!-- FOOTER -->
                <div class="modal-footer">

                    <button type="button"
                            class="btn bg-danger-subtle text-danger"
                            data-bs-dismiss="modal"
                            @click="resetModal()">
                        <i class="ti ti-x"></i> Cancelar
                    </button>

                    <button type="button"
                            class="btn btn-success"
                            @click="submit()"
                            :disabled="loading">

                            <i class="ti ti-check"></i>

                        <span x-show="!loading">Guardar</span>
                        <span x-show="loading">Guardando...</span>

                    </button>

                </div>

            </div>
        </div>
    </div>


    <!-- MODAL DETALLE-->
    <div class="modal fade"
        id="modalDetalle"
        @hidden.bs.modal="detalle = {}; matriz = []; renovacion = []"
        tabindex="-1">

        <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
            <div class="modal-content">

                <!-- HEADER -->
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white">Detalle del requisito legal</h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="resetModal()"></button>
                </div>

                <!-- BODY -->
                <div class="modal-body">

                    <!-- DETALLE -->
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Nivel de gobierno</label>
                            <div x-text="detalle.nivel_gobierno || 'S/I'"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Municipio / Estado</label>
                            <div x-text="detalle.mun_alc_est"></div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Dependencia</label>
                            <div x-text="detalle.dependencia"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Permiso</label>
                            <div x-text="detalle.permiso"></div>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Vigencia</label>
                            <div x-text="detalle.vigencia"></div>
                        </div>
                    </div>

                    <div class="row mt-2" x-show="detalle.fundamento">
                        <div class="col-12">
                            <label class="form-label">Fundamento</label>
                            <div x-text="detalle.fundamento"></div>
                        </div>
                    </div>

                    <hr>

                    <!-- MATRIZ -->
                    <table class="table table-bordered">
                        <tr class="text-center">
                            <th>Fecha emisión</th>
                            <th>Fecha vencimiento</th>
                            <th>Acuse</th>
                            <th>Requisito</th>
                        </tr>

                        <template x-for="row in matriz" :key="row.fecha_emision">
                            <tr class="text-center">

                                <td x-text="row.fecha_emision || 'S/I'"></td>
                                <td x-text="row.fecha_vencimiento || 'S/I'"></td>

                                <!-- ACUSE -->
                                <td>
                                    <template x-if="row.acuse">
                                       <a href="javascript:void(0)"
                                        @click="download('requisitos-legales', row.acuse)">
                                            <i class="ti ti-download text-success fs-6"></i>
                                        </a>
                                    </template>
                                    <template x-if="!row.acuse">
                                        <i class="ti ti-x text-danger fs-6"></i>
                                    </template>
                                </td>

                                <!-- REQUISITO -->
                                <td>
                                    <template x-if="row.requisito">
                                        <a href="javascript:void(0)"
                                        @click="download('requisitos-legales', row.requisito)">
                                            <i class="ti ti-download text-success fs-6"></i>
                                        </a>
                                    </template>
                                    <template x-if="!row.requisito">
                                        <i class="ti ti-x text-danger fs-6"></i>
                                    </template>
                                </td>

                            </tr>
                        </template>

                        <tr x-show="matriz.length === 0">
                            <td colspan="4" class="text-center">
                                No se encontró información
                            </td>
                        </tr>
                    </table>


                <label class="form-label">Renovación</label>

                <table class="table table-sm table-bordered">
                <tbody>
                    <tr class="font-weight-bold">
                    <td class="text-center align-middle bg-light text-black">Ene</td>
                    <td class="text-center align-middle bg-light text-black">Feb</td>
                    <td class="text-center align-middle bg-light text-black">Mar</td>
                    <td class="text-center align-middle bg-light text-black">Abr</td>
                    <td class="text-center align-middle bg-light text-black">May</td>
                    <td class="text-center align-middle bg-light text-black">Jun</td>
                    <td class="text-center align-middle bg-light text-black">Jul</td>
                    <td class="text-center align-middle bg-light text-black">Ago</td>
                    <td class="text-center align-middle bg-light text-black">Sep</td>
                    <td class="text-center align-middle bg-light text-black">Oct</td>
                    <td class="text-center align-middle bg-light text-black">Nov</td>
                    <td class="text-center align-middle bg-light text-black">Dic</td>   
                </tr> 
                <tr>
                <template x-for="mes in [
                        'enero','febrero','marzo','abril','mayo','junio',
                        'julio','agosto','septiembre','octubre','noviembre','diciembre'
                    ]">
                        <td class="text-center">
                            <i class="ti"
                            :class="renovacion[mes] == 1 
                                    ? 'ti-check text-success' 
                                    : 'ti-x text-danger'">
                            </i>
                        </td>
                    </template>
                </tr>
                </tbody>
                </table>

                </div>

                <!-- FOOTER -->
                <div class="modal-footer">
                    <button type="button"
                            class="btn bg-danger-subtle text-danger"
                            data-bs-dismiss="modal"
                            @click="resetModal()">
                        <i class="ti ti-x"></i> Cerrar
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL HISTORIAL -->
    <div class="modal fade"
        id="modalHistorial"
        x-ref="modalHistorial"
        @hidden.bs.modal="resetHistorialModal()"
        tabindex="-1">

        <div class="modal-dialog modal-dialog-scrollable modal-xl modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h4 class="modal-title text-white" x-text="historialTitle || 'Historial'"></h4>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    
                    <div class="table-responsive" x-show="!showHistorialForm" x-transition>

                <div class="d-flex justify-content-end mb-3">
                <button class="btn btn-primary"
                        @click="showHistorialForm = true; resetHistorialForm()">
                    <i class="ti ti-plus"></i> Nuevo
                </button>
            </div>
                        
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr class="text-center">
                                    <th>Fecha emisión</th>
                                    <th>Fecha vencimiento</th>
                                    <th>Acuse</th>
                                    <th>Requisito legal</th>
                                    <th><a class="text-muted"><i class="ti ti-edit fs-6"></i></a></th>
                                    <th><a class="text-muted"><i class="ti ti-trash fs-6"></i></a></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="row in historialRows" :key="row.id">
                                    <tr class="text-center">
                                        <td x-text="row.fecha_emision || 'S/I'"></td>
                                        <td x-text="row.fecha_vencimiento || 'S/I'"></td>
                                        <td>
                                            <template x-if="row.acusepdf">
                                                <a href="javascript:void(0)"
                                                    @click="download('requisitos-legales', row.acusepdf)">
                                                    <i class="ti ti-download text-success fs-6"></i>
                                                </a>
                                            </template>
                                            <template x-if="!row.acusepdf">
                                                <i class="ti ti-x text-danger fs-6"></i>
                                            </template>
                                        </td>
                                        <td>
                                            <template x-if="row.requisitolegalpdf">
                                                <a href="javascript:void(0)"
                                                    @click="download('requisitos-legales', row.requisitolegalpdf)">
                                                    <i class="ti ti-download text-success fs-6"></i>
                                                </a>
                                            </template>
                                            <template x-if="!row.requisitolegalpdf">
                                                <i class="ti ti-x text-danger fs-6"></i>
                                            </template>
                                        </td>
                                        <td>
                                            <a @click="editHistorialRow(row)"><i class="ti ti-edit fs-6"></i></a>
                                        </td>
                                        <td><a @click="deleteHistorialRow(row)"><i class="ti ti-trash text-danger fs-6"></i></a></td>
                                    </tr>
                                </template>
                                <tr x-show="historialRows.length === 0">
                                    <td colspan="5" class="text-center">No se encontró información</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    </div>

                <div x-show="showHistorialForm" x-transition>

                    <h5 class="mb-3" x-text="historialForm.id ? 'Editar registro' : 'Nuevo registro'"></h5>

                    <div class="row">
                        <div class="col-lg-6 col-md-12">
                            <label class="form-label">Fecha de emisión</label>
                            <input type="date"
                                class="form-control"
                                x-model="historialForm.fecha_emision"
                                @change="historialErrors.fecha_emision = false"
                                :class="historialErrors.fecha_emision ? 'is-invalid' : ''">
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <label class="form-label">Fecha de vencimiento</label>
                            <input type="date"
                                class="form-control"
                                x-model="historialForm.fecha_vencimiento">
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <label class="form-label mt-3">Acuse PDF</label>
                            <input class="form-control" type="file" x-ref="historialAcusePDF">
                        </div>
                        <div class="col-lg-6 col-md-12">
                            <label class="form-label mt-3">Requisito Legal PDF</label>
                            <input class="form-control" type="file" x-ref="historialRequisitoPDF" >
                        </div>
                    </div>

                    <div class="modal-footer">

                    <button type="button"
                            class="btn bg-danger-subtle text-danger"
                            @click="showHistorialForm = false; resetHistorialForm()">
                        <i class="ti ti-x"></i> Cancelar
                    </button>

                    <button type="button"
                            class="btn btn-success"
                            @click="submitHistorial()"
                            :disabled="loading">
                            <i class="ti ti-check"></i>
                        <span x-text="historialForm.id ? 'Actualizar' : 'Guardar'"></span>
                    </button>
                </div>

                    </div>

                </div>


            </div>
        </div>
    </div>

</div>

<?php endif; ?>

</div>

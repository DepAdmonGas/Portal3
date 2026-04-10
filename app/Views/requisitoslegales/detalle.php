<div id="container" class="pb-4" x-data="actions()" data-ngobierno="<?= $title ?>">

    <div class="text-end mt-2">
        <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <?= 
                !empty($permisos['crear']) ? 
                '<li>
                    <a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#nuevo"><i class="ti ti-plus"></i> Agregar</a>
                </li>' 
                : '' 
                ?>  
                
                 <li>
                    <a class="dropdown-item"><i class="ti ti-search"></i> Buscar</a>
                </li>
            </ul>
        </div>
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
            class="progress-bar progress-bar-striped progress-bar-animated 
            <?= $cumplimiento == 100 ? 'text-bg-success' : ($cumplimiento >= 50 ? 'text-bg-warning' : 'text-bg-danger') ?>"
            role="progressbar"
            aria-valuenow="<?= $cumplimiento ?>"
            aria-valuemin="0"
            aria-valuemax="100"
            style="width: <?= $cumplimiento ?>%;">
            
            Cumple <?= $cumplimiento ?>%

        </div>
    </div>

</div>

<div class="modal fade"
     id="nuevo"
     x-ref="modalNuevo"
     x-data="{ ...actions(), ...requisitosLegalesForm()}"
     x-init="getPermisos()"
     tabindex="-1">

    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <h4 class="modal-title">Agregar requisito legal</h4>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
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

                <div class="row">
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
                        <input class="form-control" type="file" x-model="acusePDF">
                    </div>
                    <div class="col-lg-6 col-md-12">
                        <label class="form-label mt-3">Requisito Legal PDF</label>
                        <input class="form-control" type="file" x-model="requisitoPDF">
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
                <td class="text-center align-middle"><input type="checkbox" id="ene" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="feb" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="mar" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="abr" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="may" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="jun" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="jul" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="ago" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="sep" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="oct" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="nov" style="zoom: 150%;"></td>
                <td class="text-center align-middle"><input type="checkbox" id="dic" style="zoom: 150%;"></td>
            </tr>
            </tbody>
            </table>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer">

                <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                    Cancelar
                </button>

                <button type="button"
                        class="btn btn-success"
                        @click="submit()"
                        :disabled="loading">

                    <span x-show="!loading">Guardar</span>
                    <span x-show="loading">Guardando...</span>

                </button>

            </div>

        </div>
    </div>
</div>


<!-- MODAL -->
<div class="modal fade"
     id="modalDetalle"
     x-data="{ ...actions(), ...requisitosLegalesForm()}"
     tabindex="-1">

    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">
                <h4 class="modal-title">Detalle del requisito legal</h4>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>
            </div>

            <!-- BODY -->
            <div class="modal-body">

            <table class="table table-bordered">
            <tr>
                <th class="text-center align-middle" >Fecha emisión</th>   
                <th class="text-center align-middle" >Fecha de vencimiento</th>    
                <th class="text-center align-middle" >Acuse PDF</th>    
                <th class="text-center align-middle" >Requisito Legal PDF</th>  
            </tr>
            </table>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer">

                <button type="button"
                        class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                    Cancelar
                </button>


            </div>

        </div>
    </div>
</div>

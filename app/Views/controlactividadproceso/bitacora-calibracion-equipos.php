<div id="container" class="pb-4"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...bitacoraCalibracionEquipos()}">

<?php if (empty($estacionId)): ?>
 
    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

<div class="text-end">
    <div class="btn-group">
        <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="ti ti-dots-vertical fs-4"></i>
        </button>
        <ul class="dropdown-menu animated rubberBand">
            <li><a class="dropdown-item pointer"  href="javascript:void(0)" @click="modalNuevoOpen()"><i class="ti ti-plus"></i> Nuevo </a></li>
          <li><a class="dropdown-item pointer" href="javascript:void(0)" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>
          <li>
              <a class="dropdown-item pointer" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
          </li>
        </ul>
    </div>
</div>

  <div class="datatables">
        <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
      <table id="table-bitacora-calibracion-equipos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th class="text-center align-middle" width="96px">Folio</th>
            <th class="text-center align-middle">Fecha</th>
            <th class="text-center align-middle">Equipo</th>
            <th class="text-center align-middle" width="100px">Resultados</th>
            <th class="text-center align-middle" width="100px">Estado</th>
          <th class="text-center" width="48px">
          <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

    <!--- Modal nuevo -->

    <div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

    <div class="modal-header modal-colored-header bg-primary text-white">
        <h4 class="modal-title text-white">
   <i class="ti ti-settings-plus"></i>       
        Nueva calibración de equipos
    </h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" @click="closeModalNuevo()"></button>
    </div>

    <div class="modal-body">

    <label class="form-label">* Equipo:</label>

    <select class="form-select" x-model="equipo"
        :class="errorNuevo.equipo ? 'is-invalid' : ''"
        @change="errorNuevo.equipo = false">
    <option>Selecciona una opción...</option>
    <option>Tanques de almacenamiento</option>
    <option>Sondas de medición</ption>
    <option>Dispensario</option>
    <option>Jarra patron</option>
  </select>

    </div>

    <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" @click="closeModalNuevo()"><i class="ti ti-x"></i> Cancelar</button>
        <button class="btn btn-success" @click="guardarNuevo()"><i class="ti ti-check"></i> Guardar
        </button>
    </div>

    </div>
    </div>
    </div>

  <!-- Modal Resultados -->

  <div class="modal fade" id="modalResultados" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">

              <div class="modal-header modal-colored-header bg-primary text-white">
                  <h5 class="modal-title text-white">
                    <i class="ti ti-folder-up"></i>
                      Resultados
                  </h5>

                  <button
                      type="button"
                      class="btn-close btn-close-white"
                      data-bs-dismiss="modal">
                  </button>
              </div>

              <div class="modal-body">

                  <div class="row mb-3">

                      <div class="col-6 ">
                          <span class="form-label">Equipo:</span>
                          <div x-text="resultadoSeleccionado.equipo"></div>
                      </div>

                      <div class="col-6">
                         <span class="form-label">Fecha:</span> 
                          <div x-text="resultadoSeleccionado.fecha"></div>
                      </div>

                  </div>

                  <label class="form-label">
                      * Archivo (PDF):
                  </label>

                  <input
                      type="file"
                      class="form-control"
                      accept=".pdf"
                      @change="
                          archivoResultado =
                          $event.target.files[0]
                      ">



              </div>

              <div class="modal-footer">

                  <button
                      class="btn bg-danger-subtle text-danger"
                      data-bs-dismiss="modal">

                      <i class="ti ti-x"></i> Cancelar

                  </button>

                  <template
                      x-if="resultadoSeleccionado.resultado">

                      <div>

                          <a
                              class="btn bg-primary-subtle text-primary"
                              target="_blank"
                              :href="'/uploads/archivos/calibracion/' + resultadoSeleccionado.resultado">
                            
                              Descargar resultados 
                              <i class="ti ti-file-type-pdf fs-6"></i>

                          </a>

                      </div>

                  </template>

                  <button
                      class="btn btn-success"
                      @click="guardarResultado()">

                      <i class="ti ti-check"></i> Guardar

                  </button>

              </div>

          </div>
      </div>
  </div>


  <!-- Modal Detalle -->

  <div
      class="modal fade"
      id="modalDetalle"
      tabindex="-1">

      <div class="modal-dialog modal-xl modal-dialog-centered">

          <div class="modal-content">

              <div class="modal-header modal-colored-header bg-primary text-white">

                  <h4 class="modal-title text-white">
                   <i class="ti ti-eye ms-2"></i>
                      Detalle calibración de equipos
                  </h4>

                  <button
                      type="button"
                      class="btn-close btn-close-white"
                      data-bs-dismiss="modal">
                  </button>

              </div>

              <div class="modal-body">

                  <div x-show="detalle">

                      <!-- ENCABEZADO -->

                      <div class="row">

                          <div class="col-md-3 mb-3">
                              <label class="form-label mb-1">Equipo:</label>
                              <div x-text="detalle.equipo"></div>
                          </div>

                          <div class="col-md-3 mb-3">
                              <label class="form-label mb-1">Folio:</label>
                              <div x-text="'00' + detalle.folio"></div>
                          </div>

                          <div class="col-md-3 mb-3">
                              <label class="form-label mb-1">Fecha:</label>
                              <div x-text="detalle.fecha_formateada"></div>
                          </div>

                          <div class="col-md-3 mb-3">
                              <label class="form-label mb-1">Hora:</label>
                              <div x-text="detalle.hora_formateada"></div>
                          </div>
                          

                      </div>


                      <div class="mt-2 mb-2" x-html="DOMPurify.sanitize(otrosDetalle)"></div>



                      <!-- TABLA DINAMICA -->


                      <div class="mb-2 mt-4" x-html="DOMPurify.sanitize(tablaDetalle)"></div>


                

                      <label class="form-label">
                          Observaciones:
                      </label>

              <div x-text="detalle.observaciones ? detalle.observaciones : 'Sin observaciones'"></div>

<div class="row mt-4">
<!-- Responsable de la verificación -->
    <div class="col-xl-6 col-lg-6 col-md-6 mb-3">
        <template x-if="detalle.responsable_verificacion">
            <div class="card border h-100">
                <div class="card-header bg-primary text-white py-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                            <i class="ti ti-user-check fs-5"></i>
                        </div>
                        <div class="ms-3 overflow-hidden">
                            <h6 class="mb-0 text-white">Responsable de la verificación</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                    <i class="ti ti-user text-primary mb-3" style="font-size:80px;"></i>
                </div>
                <div class="card-footer bg-light text-center">
                    <h6 class="mb-0 fw-semibold text-truncate" x-text="detalle.responsable_verificacion"></h6>
                </div>
            </div>
        </template>
        <template x-if="!detalle.responsable_verificacion">
            <div class="card border h-100">
                <div class="card-header bg-primary text-white py-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                            <i class="ti ti-clock-hour-4 fs-5"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0 text-white">Responsable de la verificación</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                    <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                    <h6 class="text-muted mb-0">Sin responsable registrado</h6>
                </div>
                <div class="card-footer bg-light text-center">
                    <small class="text-muted">Pendiente de asignar</small>
                </div>
            </div>
        </template>
    </div>

    <!-- Supervisor de la actividad -->
    <div class="col-xl-6 col-lg-6 col-md-6 mb-3">

        <template x-if="detalle.usuario?.firma_url">
            <div class="card border h-100">
                <div class="card-header bg-primary text-white py-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                            <i class="ti ti-user-check fs-5"></i>
                        </div>
                        <div class="ms-3 overflow-hidden">
                            <h6 class="mb-0 text-white">Firma de quien supervisa la actividad</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                    <div>
                        <img :src="detalle.usuario.firma_url" class="img-fluid" style="max-height:90px;object-fit:contain;" alt="Firma">
                    </div>
                </div>
                <div class="card-footer bg-light text-center">
                    <h6 class="mb-0 fw-semibold text-truncate" x-text="detalle.usuario?.nombre"></h6>
                </div>
            </div>
        </template>

        <template x-if="!detalle.usuario?.firma_url">
            <div class="card border h-100">
                <div class="card-header bg-primary text-white py-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                            <i class="ti ti-clock-hour-4 fs-5"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="mb-0 text-white">Firma de quien supervisa la actividad</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                    <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                    <h6 class="text-muted mb-0" x-text="detalle.usuario?.nombre || 'Sin firma registrada'"></h6>
                </div>
                <div class="card-footer bg-light text-center">
                    <small class="text-muted">Pendiente de firma electronica</small>
                </div>
            </div>
        </template>
    </div>
</div>

                  </div>

              </div>
              <div class="modal-footer">
                <button type="button"
class="btn bg-danger-subtle text-danger"
data-bs-dismiss="modal">
<i class="ti ti-x"></i> Cerrar
</button>
              </div>

          </div>

      </div>

  </div>

    <!-- MODAL BUSCAR -->
<div
    class="modal fade"
    id="ModalBuscar"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    <i class="ti ti-search ms-2"></i>
                    Buscar
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    @click="limpiarBuscar()">
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body">

  
                    <!-- YEAR -->                
                    
                    <label class="form-label mt-2">* Año:</label>
                    <select
                        class="form-select mb-3"
                        x-model="filtro.year"
                        :class="errorsBuscar.year ? 'is-invalid' : ''"
                        @input="errorsBuscar.year = false">

                        <option value="">
                            Selecciona una opción...
                        </option>

                        <template x-for="year in years">

                            <option
                                :value="year"
                                x-text="year">
                            </option>

                        </template>

                    </select>

                    <!-- MES -->
                    <label class="form-label mt-2">Mes:</label>

                    <select
                        class="form-select"
                        x-model="filtro.mes">

                        <option value="">
                            Todos
                        </option>

                        <option value="1">Enero</option>
                        <option value="2">Febrero</option>
                        <option value="3">Marzo</option>
                        <option value="4">Abril</option>
                        <option value="5">Mayo</option>
                        <option value="6">Junio</option>
                        <option value="7">Julio</option>
                        <option value="8">Agosto</option>
                        <option value="9">Septiembre</option>
                        <option value="10">Octubre</option>
                        <option value="11">Noviembre</option>
                        <option value="12">Diciembre</option>

                    </select>

            </div>

            <!-- FOOTER -->
              <div class="modal-footer">

                <button
                class="btn bg-danger-subtle text-danger"
                data-bs-dismiss="modal"
                @click="limpiarBuscar()">
                    <i class="ti ti-x"></i> Cancelar
                </button>

                <button
                class="btn btn-success"
                @click="buscar()">
                    <i class="ti ti-search"></i> Buscar
                </button>
            </div>

        </div>

    </div>

</div>
<?php endif; ?>

</div>

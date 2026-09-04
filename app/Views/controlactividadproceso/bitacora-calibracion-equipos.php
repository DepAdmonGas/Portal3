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
           <th>Folio</th>
            <th>Fecha</th>
            <th>Equipo</th>
            <th>Resultados</th>
            <th>Estado</th>
          <th class="text-center" width="100px">
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

                          <div class="col-md-3">
                              <label class="form-label mb-1">Equipo:</label>
                              <div x-text="detalle.equipo"></div>
                          </div>

                          <div class="col-md-3">
                              <label class="form-label mb-1">Folio:</label>
                              <div x-text="'00' + detalle.folio"></div>
                          </div>

                          <div class="col-md-3">
                              <label class="form-label mb-1">Fecha:</label>
                              <div x-text="detalle.fecha_formateada"></div>
                          </div>

                          <div class="col-md-3">
                              <label class="form-label mb-1">Hora:</label>
                              <div x-text="detalle.hora_formateada"></div>
                          </div>
                          

                      </div>

                      <div class="mt-2 mb-2" x-html="otrosDetalle"></div>


                      <!-- TABLA DINAMICA -->

                      <div class="mb-2 mt-4" x-html="tablaDetalle"></div>

                

                      <label class="form-label">
                          Observaciones:
                      </label>

                      <div
                          x-text="detalle.observaciones">
                      </div>

                      <div class="row mt-4">

                          <div class="col-md-6 text-center">

                              <div
                                  x-text="detalle.responsable_verificacion">
                              </div>

                              <div class="border-top mt-2 pt-1">
                                  Responsable de la verificación
                              </div>

                          </div>

                          <div class="col-md-6 text-center">

                            <template x-if="detalle.usuario?.firma_url">
                                <img
                                    width="100"
                                    :src="detalle.usuario.firma_url"
                                    alt="Firma">
                            </template>

                              <div
                                  x-text="detalle.usuario?.nombre">
                              </div>

                              <div class="border-top mt-2 pt-1">
                                  Firma de quien supervisa la actividad
                              </div>


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
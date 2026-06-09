<div id="container" class="pb-4"
x-data="{ ...actions(), ...bitacoraCalibracionEquipos()}">

<div class="text-end">
    <div class="btn-group">
        <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="ti ti-dots-vertical fs-4"></i>
        </button>
        <ul class="dropdown-menu animated rubberBand">
            <li><a class="dropdown-item"  href="javascript:void(0)" @click="modalNuevoOpen()"><i class="ti ti-plus"></i> Nuevo </a></li>
          <li><a class="dropdown-item" href="javascript:void(0)" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>
          <li>
              <a class="dropdown-item" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
          </li>
        </ul>
    </div>
</div>

  <div class="datatables mt-3">
    <div class="table-responsive">
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
    <div class="modal-dialog">
    <div class="modal-content">

    <div class="modal-header rounded-0 head-modal">
        <h4 class="modal-title">Agregar calibración de equipos</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" @click="closeModalNuevo()"></button>
    </div>

    <div class="modal-body">

    <label class="form-label">* Equipo:</label>

    <select class="form-control" x-model="equipo"
        :class="errorNuevo.equipo ? 'is-invalid' : ''"
        @change="errorNuevo.equipo = false">
    <option>Seleccione</option>
    <option>Tanques de almacenamiento</option>
    <option>Sondas de medición</option>
    <option>Dispensario</option>
    <option>Jarra patron</option>
  </select>

    </div>

    <div class="modal-footer">
        <button class="btn bg-danger-subtle text-danger" @click="closeModalNuevo()">Cancelar</button>
        <button class="btn btn-primary" @click="guardarNuevo()">Guardar
        </button>
    </div>

    </div>
    </div>
    </div>

  <!-- Modal Resultados -->

  <div class="modal fade" id="modalResultados" tabindex="-1">
      <div class="modal-dialog">
          <div class="modal-content">

              <div class="modal-header">
                  <h5 class="modal-title">
                      Adjuntar resultados
                  </h5>

                  <button
                      type="button"
                      class="btn-close"
                      data-bs-dismiss="modal">
                  </button>
              </div>

              <div class="modal-body">

                  <div class="row mb-3">

                      <div class="col-6">
                          <strong>Equipo:</strong>
                          <div x-text="resultadoSeleccionado.equipo"></div>
                      </div>

                      <div class="col-6">
                          <strong>Fecha:</strong>
                          <div x-text="resultadoSeleccionado.fecha"></div>
                      </div>

                  </div>

                  <label class="form-label">
                      * Archivo PDF
                  </label>

                  <input
                      type="file"
                      class="form-control"
                      accept=".pdf"
                      @change="
                          archivoResultado =
                          $event.target.files[0]
                      ">

                  <template
                      x-if="resultadoSeleccionado.resultado">

                      <div class="mt-3">

                          <hr>

                          <a
                              class="btn btn-secondary"
                              target="_blank"
                              :href="'/uploads/archivos/calibracion/' + resultadoSeleccionado.resultado">
                            
                              Resultados de la calibración
                              <i class="ti ti-file-type-pdf fs-6"></i>

                          </a>

                      </div>

                  </template>

              </div>

              <div class="modal-footer">

                  <button
                      class="btn bg-danger-subtle text-danger"
                      data-bs-dismiss="modal">

                      Cancelar

                  </button>

                  <button
                      class="btn btn-primary"
                      @click="guardarResultado()">

                      Guardar

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

      <div class="modal-dialog modal-xl">

          <div class="modal-content">

              <div class="modal-header">

                  <h5 class="modal-title">
                      Detalle calibración de equipos
                  </h5>

                  <button
                      type="button"
                      class="btn-close"
                      data-bs-dismiss="modal">
                  </button>

              </div>

              <div class="modal-body">

                  <div x-show="detalle">

                      <!-- ENCABEZADO -->

                      <div class="row">

                          <div class="col-md-3 mt-2">
                              <label class="form-label">Equipo:</label>
                              <div x-text="detalle.equipo"></div>
                          </div>

                          <div class="col-md-3 mt-2">
                              <label class="form-label">Folio:</label>
                              <div x-text="'00' + detalle.folio"></div>
                          </div>

                          <div class="col-md-3 mt-2">
                              <label class="form-label">Fecha:</label>
                              <div x-text="detalle.fecha_formateada"></div>
                          </div>

                          <div class="col-md-3 mt-2">
                              <label class="form-label">Hora:</label>
                              <div x-text="detalle.hora_formateada"></div>
                          </div>
                          

                      </div>

                      <div x-html="otrosDetalle"></div>

                      <hr>

                      <!-- TABLA DINAMICA -->

                      <div x-html="tablaDetalle"></div>

                

                      <label class="fw-bold">
                          Observaciones:
                      </label>

                      <div
                          class="border p-2"
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
            <div class="modal-header rounded-0 head-modal">

                <h4 class="modal-title">
                    Buscar
                </h4>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    @click="limpiarBuscar()">
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body">

  
                    <!-- YEAR -->                
                    
                    <label class="form-label mt-2">* Año:</label>
                    <select
                        class="form-control mb-3"
                        x-model="filtro.year"
                        :class="errorsBuscar.year ? 'is-invalid' : ''"
                        @input="errorsBuscar.year = false">

                        <option value="">
                            Selecciona
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
                        class="form-control"
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
                    Cancelar
                </button>

                <button
                class="btn btn-primary"
                @click="buscar()">
                    Buscar
                </button>
            </div>

        </div>

    </div>

</div>

</div>
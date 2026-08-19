<div id="container"
x-data="{ ...actions(), ...bitacoraResiduos()}">

     <div class="text-end">
    <div class="btn-group">
        <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="ti ti-dots-vertical fs-4"></i>
        </button>
        <ul class="dropdown-menu animated rubberBand">
         <li><a class="dropdown-item pointer"  href="javascript:void(0)" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>
          <li>
              <a class="dropdown-item pointer" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
          </li>
        
        </ul>
    </div>
</div>

<div class="datatables">
<div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
<table
    id="table-residuos-peligrosos"
    class="table table-striped table-bordered">
    <thead>  
        <tr>
            <th>Folio</th>
            <th>Nombre del residuo peligroso</th>
            <th>Cantidad generada</th>
            <th>Peligrosidad</th>
            <th>Área o proceso de generación</th>
            <th>Fecha ingreso</th>
            <th>Fecha salida</th>
            <th width="40px"><i class="ti ti-eye fs-6 text-muted"></i></th>
        </tr>
    </thead>

</table>
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
                    Buscar</h4>

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

                          <option value="">Selecciona una opcion...</option>

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

<!-- MODAL DETALLE -->
<div
    class="modal fade"
    id="ModalDetalle"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    <i class="ti ti-eye ms-2"></i>
                    Detalle del Registro
                    <span
                        x-text="detalleRegistro.folio">
                    </span>
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <label class="form-label">
                        
                   
                    <strong>
                        Nombre del residuo peligroso:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.nombreresiduo || 'S/I'">
                    </div>
                     
                </div>

                <div class="mb-3">
                    <label class="form-label">
                    <strong>
                        Cantidad generada:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.cantidadgenerada || 'S/I'">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                    <strong>
                        Características de peligrosidad:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.caracteristica_descripcion || 'S/I'">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                    <strong>
                        Área o proceso de generación:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.areaproceso || 'S/I'">
                    </div>
                </div>

                <div class="row">

                    <div class="col-md-6">
                        <label class="form-label">
                        <strong>
                            Fecha de ingreso:
                        </strong>
</label>
                        <div
                            x-text="detalleRegistro.fechaingreso_larga || 'S/I'">
                        </div>

                    </div>

                    <div class="col-md-6">
<label class="form-label">
                        <strong>
                            Fecha de salida:
                        </strong>
</label>
                        <div
                            x-text="detalleRegistro.fechasalida_larga || 'S/I'">
                        </div>

                    </div>

                </div>

                <hr>

                <h5 >
                    Recolector
                </h5>

                <div class="mb-2">
<label class="form-label">
                    <strong>
                        Nombre o razón social:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.nombrerecolector || 'S/I'">
                    </div>

                </div>

                <div class="mb-3">
<label class="form-label">
                    <strong>
                        Número de autorización Semarnat:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.numerorecolector || 'S/I'">
                    </div>

                </div>

                <hr>

                <h5>
                   Transportista
                </h5>

                <div class="mb-2">
<label class="form-label">
                    <strong>
                        Nombre o razón social:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.nombretransportista || 'S/I'">
                    </div>

                </div>

                <div class="mb-3">
<label class="form-label">
                    <strong>
                        Número de autorización Semarnat:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.numerotransportista || 'S/I'">
                    </div>

                </div>

                <hr>

                <h5>
                    Destinatario
                </h5>

                <div class="mb-2">
<label class="form-label">
                    <strong>
                        Nombre o razón social:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.nombredestinatario || 'S/I'">
                    </div>

                </div>

                <div class="mb-2">
<label class="form-label">
                    <strong>
                        Número de autorización Semarnat:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.numerodestinatario || 'S/I'">
                    </div>

                </div>

                <div class="mb-3">
<label class="form-label">
                        <strong>
                        Proceso de destino final:
                    </strong>
</label>

                    <div
                        x-text="detalleRegistro.procesodestinatario || 'S/I'">
                    </div>

                </div>

                <hr>

                <div>
<label class="form-label">
                    <strong>
                        Responsable Técnico de la Bitácora:
                    </strong>
</label>
                    <div
                        x-text="detalleRegistro.responsable || 'S/I'">
                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
</div>
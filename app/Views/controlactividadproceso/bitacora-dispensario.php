<div id="container"
     class="pb-4"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '', ENT_QUOTES, 'UTF-8') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
     x-data="{ ...actions(), ...bitacoraDispensario() }">

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
        <li><a class="dropdown-item pointer"  href="javascript:void(0)" @click="openNuevoModal()"><i class="ti ti-plus"></i> Nuevo </a></li>
         <li><a class="dropdown-item pointer"  href="javascript:void(0)" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>
          <li>
              <a class="dropdown-item pointer" :href="excelUrl"><i class="ti ti-download"></i> Descargar</a>
          </li>

          <li>
            <hr class="dropdown-divider">
          </li>

           <li>
              <a class="dropdown-item pointer" href="/sasisopa/control-actividades-procesos/calibracion-equipos/configuracion-dispensario"><i class="ti ti-gas-station"></i> Dispensario</a>
          </li>
        
        </ul>
    </div>
</div>

  <div class="datatables">
   <div class="table-responsive pb-4 overflow-x-auto overflow-y-hidden">
      <table id="table-bitacora-dispensario" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
        <thead>
          <tr>
           <th>Fecha</th>
            <th>Hora inicio</th>
            <th>Hora termino</th>
            <th style="max-width:100px;">Dispensario</th>
            <th style="max-width:100px;">Marca</th>
            <th style="max-width:100px;">Modelo</th>
            <th style="max-width:100px;">Serie</th>
            <th style="max-width:100px;">Lado</th>
            <th>Producto</th>
            <th>Motivo</th>
            <th style="max-width:100px;">Responsable</th>
            <th style="max-width:100px;">Detalle</th>
          <th class="text-center" width="40px">
          <a class="text-muted"><i class="ti ti-dots-vertical fs-6"></i></a>
          </th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <!-- MODAL NUEVO -->
  <div
      class="modal fade"
      id="ModalNuevo"
      tabindex="-1"
      aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-centered">
          <div class="modal-content">
              <div class="modal-header modal-colored-header bg-primary text-white">
                  <h4 class="modal-title text-white">
                <i class="ti ti-clipboard-plus"></i>
                  Nuevo registro:
                </h4>
                  <button
                      type="button"
                      class="btn-close btn-close-white"
                      data-bs-dismiss="modal"
                      @click="limpiarNuevo()">
                  </button>

              </div>

              <div class="modal-body">

                  <!-- MOTIVO -->
                  <label class="form-label">
                      * Motivo:
                  </label>

                  <select
                      class="form-select"
                      x-model="nuevo.motivo">

                      <option value="">
                          Selecciona una opción...
                      </option>

                      <option value="1">
                          Ajuste
                      </option>

                      <option value="3">
                          Apertura en puerta
                      </option>

                      <option value="4">
                          Acceso al modo de programación
                      </option>

                      <option value="5">
                          Cambio de fecha y hora
                      </option>

                      <option value="6">
                          Actualización del o los programas de cómputo
                      </option>

                      <option value="7">
                          Mantenimiento General
                      </option>

                  </select>

                  <template x-if="nuevo.motivo">

                      <div class="row mt-3">

                          <div class="col-md-6">
                              <label class="form-label">
                                  * Fecha:
                              </label>

                              <input
                                  type="date"
                                  class="form-control"
                                  x-model="nuevo.fecha"
                                  :class="errorsNuevo.fecha ? 'is-invalid' : ''"
                                  @input="errorsNuevo.fecha = false" >
                          </div>

                          <div class="col-md-6">
                              <label class="form-label">
                                  * Hora inicio:
                              </label>

                              <input
                                  type="time"
                                  class="form-control"
                                  x-model="nuevo.hora_inicio"
                                  :class="errorsNuevo.hora_inicio ? 'is-invalid' : ''"
                                  @input="errorsNuevo.hora_inicio = false">
                          </div>

                          <div class="col-md-6 mt-3">
                              <label class="form-label">
                                  * Hora término:
                              </label>

                              <input
                                  type="time"
                                  class="form-control"
                                  x-model="nuevo.hora_termino">
                          </div>

                          <template x-if="nuevo.motivo != 2">

                              <div class="col-md-6 mt-3">

                                  <label class="form-label">
                                      * Dispensario:
                                  </label>

                                  <select
                                      class="form-select"
                                      x-model="nuevo.id_dispensario"
                                      :class="errorsNuevo.id_dispensario ? 'is-invalid' : ''"
                                      @input="errorsNuevo.id_dispensario = false">

                                      <option value="">
                                          Selecciona una opcion...
                                      </option>

                                      <template
                                          x-for="d in dispensarios"
                                          :key="d.id">

                                          <option
                                              :value="d.id"
                                              x-text="d.no_dispensario">
                                          </option>

                                      </template>

                                  </select>

                              </div>

                          </template>

                          <template x-if="nuevo.motivo != 2">

                              <div class="col-md-6 mt-3">

                                  <label class="form-label">
                                      * Lado:
                                  </label>

                                  <select
                                      class="form-select"
                                      x-model="nuevo.lado">

                                      <option value="">
                                          Selecciona una opcion...
                                      </option>

                                      <option value="1">
                                          1
                                      </option>

                                      <option value="2">
                                          2
                                      </option>

                                  </select>

                              </div>

                          </template>

                          <div class="col-md-6 mt-3">

                              <label class="form-label">
                                  Producto:
                              </label>

                              <select
                                  class="form-select"
                                  x-model="nuevo.producto">

                                  <option value="">
                                      Selecciona una opcion...
                                  </option>

                                  <template
                                      x-for="p in productos">

                                      <option
                                          :value="p"
                                          x-text="p">
                                      </option>

                                  </template>

                              </select>

                          </div>

                          <div class="col-12 mt-3">

                              <label class="form-label">

                                  <span
                                      x-text="
                                      nuevo.motivo == 2
                                      ? '* Precio:'
                                      : '* Detalle:'
                                      ">
                                  </span>

                              </label>

                              <textarea
                                  class="form-control"
                                  rows="4"
                                  x-model="nuevo.detalle">
                              </textarea>

                          </div>

                      </div>

                  </template>

              </div>

              <div class="modal-footer">

                  <button
                      class="btn bg-danger-subtle text-danger"
                      data-bs-dismiss="modal">

                      <i class="ti ti-x"></i> Cancelar

                  </button>

                  <button
                      class="btn btn-success"
                      @click="guardar()">

                      <i class="ti ti-check"></i> Guardar

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
                    <i class="ti ti-search"></i>
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

                      <div class="text-end mt-3">
                      <button
                        class="btn"
                        @click="limpiarFiltros()">
                        <i class="ti ti-filter"></i> Mostrar todo
                    </button>
                    </div>

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

      <div class="modal-dialog modal-xl modal-dialog-centered">
          <div class="modal-content">
              <div class="modal-header modal-colored-header bg-primary text-white">
                  <h4 class="modal-title text-white d-flex align-items-center">
    <i class="ti ti-file me-1"></i>

    <span x-text="'Registro #' + (detalleRegistro.id ?? '')"></span>
</h4>

                  <button
                      type="button"
                      class="btn-close btn-close-white"
                      data-bs-dismiss="modal">
                  </button>

              </div>

              <div class="modal-body">

                  <!-- FECHA -->
                  
                  <table class="table table-bordered table-sm">

                      <thead>

                          <tr>

                              <th class="text-center bg-light">
                                  Fecha
                              </th>

                              <th class="text-center bg-light">
                                  Hora inicio
                              </th>

                              <th class="text-center bg-light">
                                  Hora término
                              </th>

                              <th class="text-center bg-light">
                                  Responsable
                              </th>

                          </tr>

                      </thead>

                      <tbody>

                          <tr>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.fecha_larga">
                              </td>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.hora_inicio">
                              </td>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.hora_termino || 'S/I'">
                              </td>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.responsable">
                              </td>

                          </tr>

                      </tbody>

                  </table>

                  <!-- DISPENSARIO -->

                  <table class="table table-bordered table-sm">

                      <thead>

                          <tr>

                              <th class="text-center bg-light">
                                  Dispensario
                              </th>

                              <th class="text-center bg-light">
                                  Marca
                              </th>

                              <th class="text-center bg-light">
                                  Modelo
                              </th>

                              <th class="text-center bg-light">
                                  Serie
                              </th>

                              <th class="text-center bg-light">
                                  Lado
                              </th>

                              <th class="text-center bg-light">
                                  Producto
                              </th>

                          </tr>

                      </thead>

                      <tbody>

                          <tr>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.no_dispensario">
                              </td>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.marca">
                              </td>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.modelo">
                              </td>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.serie">
                              </td>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.lado">
                              </td>

                              <td
                                  class="text-center"
                                  x-text="detalleRegistro.producto">
                              </td>

                          </tr>

                      </tbody>

                  </table>

                  <!-- MOTIVO -->

                  <table class="table table-bordered table-sm">

                      <thead>

                          <tr>

                              <th class="bg-light">
                                  Motivo
                              </th>

                          </tr>

                      </thead>

                      <tbody>

                          <tr>

                              <td
                                  x-text="detalleRegistro.clave_motivo">
                              </td>

                          </tr>

                      </tbody>

                  </table>

                  <!-- DETALLE -->

                  <table class="table table-bordered table-sm mb-0">

                      <thead>

                          <tr>

                              <th class="bg-light">
                                  Detalle
                              </th>

                          </tr>

                      </thead>

                      <tbody>

                          <tr>

                              <td
                                  style="white-space: pre-wrap;"
                                  x-text="detalleRegistro.detalle">
                              </td>

                          </tr>

                      </tbody>

                  </table>

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
    <?php endif; ?>

</div>
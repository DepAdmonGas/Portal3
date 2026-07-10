<div id="container" class="mb-4" 
x-data="{...actions(), ...recepcionDescargaProducto()}">

<div class="text-end">
    <div class="btn-group">
        <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="ti ti-dots-vertical fs-4"></i>
        </button>
        <ul class="dropdown-menu animated rubberBand">
          <?= !empty($permisos['crear']) ? 
          '<li><a class="dropdown-item"  href="javascript:void(0)" @click="openBuscarModal()"><i class="ti ti-search"></i> Buscar </a></li>' 
          : '' 
          ?>
          <li>
              <a class="dropdown-item" :href="pdfUrl"><i class="ti ti-download"></i> Descargar</a>
          </li>
        </ul>
    </div>
</div>

    <div class="datatables">
        <div class="table-responsive">
        <table id="table-recepcion-descarga-producto" class="table table-bordered align-middle">
            <thead>
            <tr>
            <th>Folio</th>
			<th>Fecha</th>
			<th>Hora llegada</th>
            <th>Hora salida</th>
            <th>Vehículo (Placas)</th>
            <th>Operador</th>
			<th>No. Factura</th>
			<th>Litros compra</th>
			<th>Producto</th>
			<th>Persona que recibe</th>
			<th>Persona que superviso</th>
            </tr>
            </thead>
            <tbody></tbody>
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
                    Buscar
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    @click="limpiar()">
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body">

  
                    <!-- YEAR -->                
                    
                    <label class="form-label mt-2">* Año:</label>
                    <select
                        class="form-control mb-3"
                        x-model="filtros.year"
                        :class="errors.year ? 'is-invalid' : ''"
                        @input="errors.year = false">

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
                        x-model="filtros.mes">

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
                @click="limpiar()">
                    <i class="ti ti-x"></i> Cancelar
                </button>

                <button
                class="btn btn-primary"
                @click="buscar()">
                    <i class="ti ti-search"></i> Buscar
                </button>
            </div>

        </div>

    </div>

</div>

<!-- Modal detalle -->

<div
    class="modal fade"
    id="modalDetalle"
    tabindex="-1">

    <div class="modal-dialog modal-dialog-scrollable modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header modal-colored-header bg-primary text-white">

                    <h4 class="modal-title text-white">
                        Folio:
                        <span x-text="detalle.folio"></span>
                    </h4>
           
                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>


            <div class="modal-body">

            <h5>Información General</h5>

            <div class="d-flex align-items-center my-4">
                    <div class="border-end pe-4 w-100">
                      <h6 class="text-muted fw-normal">Fecha</h6>
                        <div class="fw-bolder"
                        x-text="detalle.fecha?.detalle">
                        </div>
                    </div>
                    <div class="ms-4 border-end pe-4 w-100">
                      <h6 class="text-muted fw-normal">Hora llegada</h6>
                        <div
                        class="fw-bolder"
                        x-text="detalle.hora_llegada?.display">
                        </div>
                    </div>
                    <div class="ms-4 border-end w-100">
                      <h6 class="text-muted fw-normal">Hora salida</h6>
                       <div
                        class="fw-bolder"
                        x-text="detalle.hora_salida?.display">
                        </div>
                    </div>
                    <div class="ms-4 w-100">
                      <h6 class="text-muted fw-normal">Tiempo descarga</h6>
                       <div
                        class="fw-bolder"
                        x-text="detalle.tiempo_descarga">
                        </div>
                    </div>
            </div>

            <div class="d-flex align-items-center my-4">
                    <div class="border-end pe-4 w-100">
                      <h6 class="text-muted fw-normal">Factura</h6>
                        <div class="fw-bolder"
                        x-text="detalle.no_factura">
                        </div>
                    </div>
                    <div class="ms-4 border-end pe-4 w-100">
                      <h6 class="text-muted fw-normal">Producto</h6>
                        <div
                        class="fw-bolder"
                        :style="`color:${detalle.producto?.color}`"
                        x-text="detalle.producto?.nombre">
                        </div>
                    </div>
                    <div class="ms-4 w-100">
                      <h6 class="text-muted fw-normal">Litros Compra</h6>
                       <div
                        class="fw-bolder"
                        x-text="detalle.litros_compra?.display">
                        </div>
                    </div>
            </div>

            <div class="d-flex align-items-center my-4">
                    <div class="border-end pe-4 w-100">
                      <h6 class="text-muted fw-normal">Operador</h6>
                        <div class="fw-bolder"
                        x-text="detalle.operador">
                        </div>
                    </div>
                    <div class="ms-4 border-end pe-4 w-100">
                      <h6 class="text-muted fw-normal">Vehículo (Placas)</h6>
                        <div
                        class="fw-bolder"
                        x-text="detalle.placa">
                        </div>
                    </div>
                    <div class="ms-4 border-end w-100">
                      <h6 class="text-muted fw-normal">No. Remolque</h6>
                       <div
                        class="fw-bolder"
                        x-text="detalle.no_remolque">
                        </div>
                    </div>
                    <div class="ms-4 w-100">
                      <h6 class="text-muted fw-normal">Línea transporte</h6>
                        <div class="fw-bolder"
                        x-text="detalle.linea_transporte">
                        </div>
                    </div>
            </div>

                <!-- ===================================================== -->
                <!-- TANQUES -->
                <!-- ===================================================== -->

                <table class="table table-sm">

    <thead class="table-light">

        <tr>

            <th class="text-center">
                No. Tanque
            </th>

            <th class="text-end">
                Inventario Inicial
            </th>

            <th class="text-end">
                Inventario Final
            </th>

            <th class="text-center">
                Aditivación
            </th>

        </tr>

    </thead>

    <tbody>

        <template
            x-for="tanque in (detalle.tanques?.items || [])"
            :key="tanque.no_tanque">

            <tr>

                <td
                    class="text-center"
                    x-text="tanque.no_tanque">
                </td>

                <td
                    class="text-end"
                    x-text="tanque.inventario_inicial">
                </td>

                <td
                    class="text-end"
                    x-text="tanque.inventario_final">
                </td>

                <td
                    class="text-center"
                    x-text="tanque.aditivacion">
                </td>

            </tr>

        </template>

    </tbody>

    <tfoot>

        <tr>

            <td
                colspan="4"
                class="text-end fw-bolder">

                Merma:

                <span
                    x-text="detalle.tanques?.merma || '0.00'">
                </span>

            </td>

        </tr>

    </tfoot>

</table>

                <!-- ===================================================== -->
                <!-- SELLOS -->
                <!-- ===================================================== -->

                <h6 class="fw-bolder">
    Sellos
</h6>

<template
    x-for="sello in (detalle.sellos?.sellos || [])"
    :key="sello.verificar">

    <div
        class="d-flex justify-content-between border-bottom py-2">

        <div
            x-text="sello.verificar">
        </div>

        <div
            class="fw-bolder"
            x-text="sello.resultado">
        </div>

    </div>

</template>

<div class="d-flex justify-content-between border-bottom py-2">

    <div>
        No. Serie:
    </div>

    <div
        class="fw-bolder"
        x-text="detalle.sello_noserie">
    </div>

</div>

                <!-- NICE -->
                 <h6 class="fw-bolder mt-3">
    NICE
</h6>

<template
    x-for="nice in (detalle.sellos?.nice || [])"
    :key="nice.verificar">

    <div
        class="d-flex justify-content-between border-bottom py-2">

        <div
            x-text="nice.verificar">
        </div>

        <div
            class="fw-bolder"
            x-text="nice.resultado">
        </div>

    </div>

</template>          

                <div class="d-flex justify-content-between border-bottom py-2">
                    <div>Manómetro:</div>
                    <div class="fw-bolder" x-text="detalle.manometro">
                    </div>
                </div>

                <div class="d-flex justify-content-between border-bottom py-2">
                    <div>Temperatura:</div>
                    <div class="fw-bolder" x-text="detalle.temperatura">
                    </div>
                </div>

                <h6 class="fw-bolder mt-4">Observaciones</h6>
                <div class="p-3 bg-light rounded-2" x-text="detalle.observaciones || 'Sin observaciones'"></div>


    
                <!-- ===================================================== -->
                <!-- FIRMAS -->
                <!-- ===================================================== -->

                <div class="row mt-4">

                    <!-- RECIBE -->

                    <div class="col-md-6 mb-3">

                        <div class="text-center">

                            <h6 class="fw-bolder text-muted">Firma de quien recibe</h6>
                            <template x-if="detalle.firma_recibe?.firma">
                                    <img :src="detalle.firma_recibe.firma" width="140">
                            </template>
                            <div class="small mt-2" x-text="detalle.firma_recibe?.nombre"></div>

                        </div>

                    </div>

                    <!-- SUPERVISA -->

                     <div class="col-md-6 mb-3">

                        <div class="text-center">

                            <h6 class="fw-bolder text-muted">Firma de quien supervisa</h6>
                            <template x-if="detalle.firma_supervisa?.firma">
                                    <img :src="detalle.firma_supervisa.firma" width="140">
                            </template>
                            <div class="small mt-2" x-text="detalle.firma_supervisa?.nombre"></div>

                        </div>

                    </div>

                 
                </div>

            </div>

        </div>

    </div>

</div>

</div>
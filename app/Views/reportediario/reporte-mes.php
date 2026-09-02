<div id="container" class="mb-4"
data-module-station-key="<?= htmlspecialchars($moduleStationKey ?? '') ?>"
data-estacion-id="<?= (int) ($estacionId ?? 0) ?>"
x-data="{ ...actions(), ...reporteMes(<?= $mes ?>, <?= $year ?>) }">

<?php if (empty($estacionId)): ?>

    <div id="sasisopa-empty-message"
         class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
        Debes de seleccionar una estación del menú superior para poder visualizar los elementos de SASISOPA.
    </div>

<?php else: ?>

    <div id="sasisopa-content">

<div class="text-end mt-2">
   <div class="btn-group">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ti ti-dots-vertical fs-4"></i>
            </button>
            <ul class="dropdown-menu animated rubberBand">
                <li>
                    <a class="dropdown-item pointer" @click="nuevo()"><i class="ti ti-plus"></i> Nuevo</a>
                </li>
                 <li>
                    <a class="dropdown-item pointer" :href="pdfUrl" target="_blank"><i class="ti ti-download"></i> Descargar</a>
                </li>
            </ul>
        </div>
</div>

    <table class="table table-bordered table-sm mt-3">
    <thead>

    <tr>

        <th rowspan="2" class="text-center align-middle">
            Fecha
        </th>

        <template
            x-for="producto in head.productos"
            :key="producto.nombre">

            <th
                colspan="5"
                class="text-center text-white"
                :class="'bg-' + producto.color"
                x-text="producto.nombre">
            </th>

        </template>

        <th class="text-center align-middle" rowspan="2">
            <i class="ti ti-message-2 text-muted fs-6"></i>
        </th>

        <th class="text-center align-middle" rowspan="2">
            <i class="ti ti-dots-vertical text-muted fs-6"></i>
        </th>

    </tr>

    <tr>

        <template
            x-for="producto in head.productos"
            :key="'col-'+producto.nombre">

            <template
                x-for="columna in head.columnas"
                :key="producto.nombre + columna.campo">

                <th
                    class="text-center align-middle"
                    :title="columna.tooltip"
                    x-text="columna.titulo">
                </th>

            </template>

        </template>

    </tr>

    </thead>
    <tbody>

    <template x-for="row in rows" :key="row.fecha">

    <tr>

        <td class="fw-bolder align-middle" x-text="row.fecha_larga"></td>

        <template x-for="(celda,index) in row.celdas" :key="index">

            <td
            class="align-middle"
                :class="celda.clase"
                x-text="celda.valor">
            </td>

        </template>

<td class="text-center align-middle">

    <button
        type="button"
        class="btn btn-sm position-relative mt-2 p-0 bg-transparent border-0"
        @click="abrirMensajes(row)"
        aria-label="Ver mensajes">

        <i class="ti ti-message-2 fs-5"></i>

        <span
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger px-1 py-0"
            x-show="row.mensajes.total > 0"
            x-text="row.mensajes.total">
        </span>

    </button>

</td>

        <td class="text-center align-middle">

         <div class="dropdown dropstart">
            <a href="javascript:void(0)" data-bs-toggle="dropdown">
                <i class="ti ti-dots-vertical fs-6"></i>
            </a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item pointer d-flex align-items-center gap-3"
                       @click="detalle(row)">
                        <i class="fs-4 ti ti-eye"></i>Detalle
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item pointer d-flex align-items-center gap-3"
                        @click="editar(row)">
                            <i class="fs-4 ti ti-edit"></i>Editar
                        </a>
                    </li>
                 </ul>
            </div>

        </td>

    </tr>

    </template>

    </tbody>
    <tfoot>

    <tr>

        <th>TOTAL</th>

        <template x-for="(celda,index) in footer" :key="index">

            <th
                :class="celda.clase"
                x-text="celda.valor">
            </th>

        </template>

        <th colspan="2"></th>

    </tr>

    </tfoot>
    </table>


    <!-- Modal Detalle -->
<div
    class="modal fade"
    id="modalDetalleReporte"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">
                    Detalle del Reporte Diario
                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

            <h5 class="mb-3">
    Fecha:
    <span class="text-muted" x-text="detalleFecha"></span>
</h5>

                <template
                    x-for="(producto,index) in detalleProductos"
                    :key="index">

                    <div class="border mb-3">

                        <div class="p-3">

                            <!-- =========================== -->
                            <!-- TITULO -->
                            <!-- =========================== -->

                            <div style="font-size:1.2em">

                                <label
                                    :style="`border-bottom:2px solid var(--bs-${producto.color})`">

                                    Producto:

                                    <b x-text="producto.nombre"></b>

                                </label>

                            </div>

                            <div class="row">

                                <!-- ======================================= -->
                                <!-- VOLUMEN -->
                                <!-- ======================================= -->

                                <div class="col-xl-5 col-lg-5 col-md-12">

                                    <div class="text-muted mt-2 mb-2 fs-6">

                                        <b>1.</b>
                                        Volúmenes registrados

                                    </div>

                                    <div style="overflow-x:auto">

                                        <table class="table table-bordered table-sm">

                                        <thead>
                                            <tr>
                                                <th class="text-center">Volumen Inicial</th>
                                                <th class="text-center">Volumen Venta</th>
                                                <th class="text-center">Volumen Final</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            <tr class="text-center">

                                                <td x-text="producto.volumen.inicial ?? ''"></td>

                                                <td x-text="producto.volumen.venta ?? ''"></td>

                                                <td x-text="producto.volumen.final ?? ''"></td>

                                            </tr>

                                        </tbody>

                                    </table>

                                    </div>

                                </div>

                                <!-- ======================================= -->
                                <!-- PIPAS -->
                                <!-- ======================================= -->

                                <div class="col-xl-7 col-lg-7 col-md-12">

                                    <div class="text-muted mt-2 mb-2 fs-6">

                                        <b>2.</b>
                                        Compras de pipas

                                    </div>

                                    <div style="overflow-x:auto">

                                        <table class="table table-bordered table-sm">

    <thead>

        <tr>

            <th>Pipa</th>
            <th>Volumen (Lt) de Compra</th>
            <th>Precio ($) por litro de producto</th>
            <th>Costo ($) del flete mas IVA</th>
            <th>No. De factura</th>
            <th>Nombre o Razón Social del Transportista</th>
            <th>Importe</th>

        </tr>

    </thead>

    <tbody>

     <template x-if="producto.pipas.length === 0">
        <tr>
            <td
                colspan="7"
                class="text-center text-muted">
                No se encontró información.
            </td>
        </tr>
    </template>

        <template
            x-for="(pipa,i) in producto.pipas.filter(p=>!p.eliminar)"
            :key="i">

            <tr>

                <td class="text-center">
                    <span x-text="i+1"></span>
                </td>

                <td class="text-end"
                    x-text="pipa.volumen ?? ''">
                </td>

                <td class="text-end"
                    x-text="pipa.precio ?? ''">
                </td>

                <td class="text-end"
                    x-text="pipa.costo ?? ''">
                </td>

                <td
                    x-text="pipa.factura || ''">
                </td>

                <td
                    x-text="pipa.transportista || ''">
                </td>

                <td class="text-end"
                    x-text="pipa.importe ?? ''">
                </td>

            </tr>

        </template>

    </tbody>

                                        </table>

                                    </div>

                                </div>

                            </div>

                            <!-- =========================== -->
                            <!-- MERMA -->
                            <!-- =========================== -->

                            <div class="row">

                            <hr>

<div class="row">

    <div class="col-md-6">

        <strong>Total Compra:</strong>

        <span
            class="text-primary"
            x-text="Number(producto.totalCompra ?? 0).toLocaleString('es-MX',{
                minimumFractionDigits:2,
                maximumFractionDigits:2
            })">
        </span>

    </div>

    <div class="col-md-6 text-end">

        <strong>Total Merma:</strong>

        <span
            class="text-danger fw-bold"
            x-text="Number(producto.merma ?? 0).toLocaleString('es-MX',{
                minimumFractionDigits:2,
                maximumFractionDigits:2
            })">
        </span>

    </div>

</div>

                            </div>

                        </div>

                    </div>

                </template>

            </div>

            <div class="modal-footer">

               <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">

                    <i class="ti ti-x"></i> Cerrar

                </button>

            </div>

        </div>

    </div>

</div>

<div class="modal fade"
     id="modalMensajes"
     tabindex="-1">

    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">

        <div class="modal-content border-0 shadow">

            <!-- Header -->

            <div class="modal-header modal-colored-header bg-primary text-white">

                    <h4 class="modal-title text-white">
                        <i class="ti ti-message-circle me-2 text-primary"></i>
                        Mensaje
                    </h4>

                <button class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <!-- Body -->

            <div class="modal-body bg-light"
                 style="height:550px;overflow-y:auto;">

                <template x-if="mensajes.length==0">

                    <div class="text-center mt-5">

                        <i class="ti ti-message-off fs-7 text-secondary"></i>

                        <div class="mt-2 text-muted">

                            No existen mensajes.

                        </div>

                    </div>

                </template>

                <template
                    x-for="item in mensajes"
                    :key="item.id">

                    <div class="mb-4">

                        <div class="d-flex"
                             :class="item.propio
                                ? 'justify-content-end'
                                : 'justify-content-start'">

                            <!-- Usuario -->

                            <template x-if="!item.propio">

                                <div class="d-flex">

                                    <div class="rounded-circle
                                                bg-secondary
                                                text-white
                                                fw-bold
                                                d-flex
                                                align-items-center
                                                justify-content-center"
                                         style="width:42px;height:42px;">

                                        <span x-text="item.inicial"></span>

                                    </div>

                                    <div class="ms-2">

                                        <div class="fw-semibold small">

                                            <span x-text="item.usuario"></span>

                                        </div>

                                        <div class="rounded-4
                                                    bg-white
                                                    border
                                                    shadow-sm
                                                    px-3
                                                    py-2
                                                    mt-1">

                                            <div style="white-space:pre-wrap"
                                                 x-text="item.mensaje">
                                            </div>

                                        </div>

                                        <div class="small text-muted mt-1">

                                            <i class="ti ti-calendar"></i>

                                            <span x-text="item.fecha"></span>

                                            &nbsp;

                                            <i class="ti ti-clock-hour-4"></i>

                                            <span x-text="item.hora"></span>

                                        </div>

                                    </div>

                                </div>

                            </template>

                            <!-- Yo -->

                            <template x-if="item.propio">

                                <div class="d-flex">

                                    <div class="me-2 text-end">

                                        <div class="fw-semibold
                                                    small
                                                    text-primary">

                                            <span x-text="item.usuario"></span>

                                        </div>

                                        <div class="rounded-4
                                                    text-white
                                                    px-3
                                                    py-2
                                                    mt-1"
                                             style="background:#2563eb;">

                                            <div style="white-space:pre-wrap"
                                                 x-text="item.mensaje">
                                            </div>

                                        </div>

                                        <div class="small text-muted mt-1">

                                            <i class="ti ti-calendar"></i>

                                            <span x-text="item.fecha"></span>

                                            &nbsp;

                                            <i class="ti ti-clock-hour-4"></i>

                                            <span x-text="item.hora"></span>

                                        </div>

                                    </div>

                                    <div class="rounded-circle
                                                text-white
                                                fw-bold
                                                d-flex
                                                align-items-center
                                                justify-content-center"
                                         style="width:42px;
                                                height:42px;
                                                background:#2563eb;">

                                        <span x-text="item.inicial"></span>

                                    </div>

                                </div>

                            </template>

                        </div>

                    </div>

                </template>

            </div>

            <!-- Footer -->

            <div class="modal-footer">

                <div class="input-group">

                    <textarea class="form-control"
                              rows="2"
                              placeholder="Escribe un mensaje..."
                              x-model="nuevoMensaje">
                    </textarea>

                    <button class="btn btn-primary px-4"
                            @click="enviarMensaje()">

                        <i class="ti ti-send"></i>

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

    </div>

    <?php endif; ?>

</div>
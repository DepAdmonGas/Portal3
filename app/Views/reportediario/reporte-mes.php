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

<div class="text-end mt-2 mb-3">
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
<div class="table-responsive">
    <table class="table table-striped table-bordered text-nowrap align-middle">
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
            <i class="ti ti-message text-muted fs-7"></i>
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
    <a href="#" class="btn-badge-historico position-relative d-inline-flex align-items-center justify-content-center"
       title="Comentarios" @click.prevent="abrirMensajes(row)">
        <i class="ti ti-message fs-7"></i>
        <span class="badge-historico position-absolute top-0 start-100 translate-middle"
              x-show="row.mensajes.total > 0" x-text="row.mensajes.total"></span>
    </a>
</td>


        <td class="text-center align-middle" width="48px">
            <div class="dropdown dropstart">
                <a class="pointer text-decoration-none" data-bs-toggle="dropdown" aria-label="Opciones">
                    <i class="ti ti-dots-vertical fs-6"></i>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item pointer d-flex align-items-center gap-3" href="#"
                           @click.prevent="detalle(row)">
                            <i class="fs-4 ti ti-eye"></i>Detalle
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item pointer d-flex align-items-center gap-3" href="#"
                           @click.prevent="editar(row)">
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

</div>


    <!-- Modal Detalle -->
<div
    class="modal fade"
    id="modalDetalleReporte"
    tabindex="-1"
    aria-hidden="true"
    data-portal-table-selection="false"
    data-bs-backdrop="static"
    data-bs-keyboard="false">

    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header modal-colored-header bg-primary text-white">

                <h4 class="modal-title text-white">

                <i class="ti ti-eye"></i>
                    Detalle del Reporte Diario (<label class="text-white" x-text="detalleFecha"></label>)

                </h4>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">


                <template
                    x-for="(producto,index) in detalleProductos"
                    :key="index">
<div class="card">
<div class="card-header text-white" :style="`background-color: var(--bs-${producto.color})`">
 <!-- =========================== -->
                            <!-- TITULO -->
                            <!-- =========================== -->



                                <label>
                                    <b x-text="producto.nombre"></b>
                                </label>


</div>


<!--------aqui comienza el body-card principal----->
<div class="card-body pb-0">

 <div class="row">
<div class="col-12">
<div class="card">
<div class="card-header card-colored-header bg-primary">
            <h5  class="card-title text-white mb-0">
              <i class="ti ti-cash me-1"></i>
            1.
                                        Volúmenes registrados

                                    </h5>
</div>

    <div class="card-body p-0">
                        <!-- ======================================= -->
                                       <!-- VOLUMEN -->
                        <!-- ======================================= -->




                                    <div class="table-responsive">

                                        <table class="table table-striped table-bordered text-nowrap align-middle mb-0">

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
</div>
</div>


<div class="col-12">
<div class="card">
    <div class="card-header card-colored-header bg-primary">
   <h5 class="card-title text-white mb-0">
<i class="ti ti-truck me-1"></i>
                                        2.
                                        Compras de pipas

                                    </h5>
    </div>
<div class="card-body p-0">
   <!-- ======================================= -->
                                <!-- PIPAS -->
                                <!-- ======================================= -->



                                    <div class="table-responsive">

                                        <table class="table table-striped table-bordered text-nowrap align-middle mb-0">

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
</div>

                               </div>

                   </div>
<!---------aqui termina el body-card------->


        <div class="card-footer">
  <!-- =========================== -->
                            <!-- MERMA -->
                            <!-- =========================== -->

                            <div class="row">


    <div class="col-6">

        <strong>Total Compra:</strong>

        <span
            class="text-primary"
            x-text="Number(producto.totalCompra ?? 0).toLocaleString('es-MX',{
                minimumFractionDigits:2,
                maximumFractionDigits:2
            })">
        </span>

    </div>

    <div class="col-6 text-end">

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

                </template>

            </div>

            <div class="modal-footer">
                <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid">
                        <button class="btn bg-danger-subtle text-danger"
                        data-bs-dismiss="modal">
                    <i class="ti ti-x"></i> Cerrar
                </button>
                    </div>
                  </div>




            </div>

        </div>

    </div>

</div>








<div class="offcanvas offcanvas-end d-flex flex-column"
     id="modalMensajes"
     tabindex="-1"
     style="width:480px;max-width:100vw;max-height:100dvh;overflow:hidden;">


        <div class="d-flex flex-column h-100 border-0 shadow">

            <!-- Header -->

            <div class="p-3 border-bottom d-flex align-items-center justify-content-between bg-primary flex-shrink-0">

                    <div class="hstack gap-3">
                        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                            <i class="ti ti-message-circle text-primary fs-7"></i>
                        </div>
                        <div>
                            <h5 class="mb-1 text-white">COMENTARIOS</h5>
                            <p class="mb-0 text-white opacity-75">Reporte diario CRE (<span x-text="mensajeRow ? mensajeRow.fecha_larga : ''"></span>)</p>
                        </div>
                    </div>

                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="offcanvas">
                </button>

            </div>

            <!-- Body -->

            <div class="d-flex flex-column flex-grow-1 overflow-hidden bg-light" style="min-height:0;">
            <?php \App\Core\View::renderPartial('partials/_chat-request-summary.php', ['contextoComentarioExpression' => 'mensajeRow']); ?>
            <div class="chat-box w-100 flex-grow-1 d-flex flex-column" style="min-height:0;">
            <div class="chat-box-inner p-3 flex-grow-1 overflow-auto" style="min-height:0;overscroll-behavior:contain;" x-ref="chatContainer">

                <template x-if="mensajes.length==0">

                    <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height:380px;">

                        <i class="ti ti-message-off text-muted mb-2" style="font-size:55px;"></i>
                        <p class="text-muted mb-0 fs-5">Sin comentarios</p>

                    </div>

                </template>

                <template
                    x-for="item in ordenarComentariosChat(mensajes)"
                    :key="item.id">

                    <div class="d-flex mb-3" :class="item.propio ? 'justify-content-end' : 'justify-content-start'">

                            <template x-if="!item.propio">
                                <div class="d-flex gap-3 align-items-start">
                                    <div class="flex-shrink-0"><div class="rounded-circle bg-dark d-flex align-items-center justify-content-center" style="width:45px;height:45px;"><i class="ti ti-user text-white fs-5"></i></div></div>
                                    <div>
                                        <h6 class="fw-semibold mb-1" x-text="item.usuario || 'Usuario'"></h6>
                                        <div class="fs-3 text-muted mb-1" x-text="item.fechaFormateada"></div>
                                        <div class="p-3 text-bg-success rounded-3 text-white mt-2" style="max-width:420px;white-space:pre-wrap;" x-text="item.mensaje"></div>
                                    </div>
                                </div>
                            </template>
                            <template x-if="item.propio">
                                <div class="d-flex flex-column align-items-end">
                                    <div class="fs-3 text-muted mb-1 text-end" x-text="item.fechaFormateada"></div>
                                    <div class="p-3 bg-primary text-white rounded-3 mt-2" style="max-width:420px;white-space:pre-wrap;" x-text="item.mensaje"></div>
                                </div>
                            </template>
                        </div>

                </template>

            </div></div>
            <?php \App\Core\View::renderPartial('partials/_chat-comment-sending', ['estadoEnvio' => 'enviandoMensaje']); ?>


</div>

            <!-- Footer -->


            <div class="px-3 py-3 border-top bg-white flex-shrink-0">

                <div class="d-flex align-items-center gap-2">

                    <div class="flex-grow-1"><textarea class="form-control border-0 bg-light rounded-pill px-3 py-2"
                              rows="1" placeholder="Escribe un comentario..." style="resize:none;"
                              x-model="nuevoMensaje" @keydown.enter.prevent="enviarMensaje()">
                    </textarea>
                    </div>

                    <button class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
                            style="width:44px;height:44px;" type="button" @click="enviarMensaje()"
                            :disabled="enviandoMensaje || !nuevoMensaje.trim()">
                        <template x-if="!enviandoMensaje"><i class="ti ti-send fs-5"></i></template>
                        <template x-if="enviandoMensaje"><span class="spinner-border spinner-border-sm"></span></template>
                    </button>

                </div>

            </div>

        </div>

    </div>

    </div>

    <?php endif; ?>

</div>

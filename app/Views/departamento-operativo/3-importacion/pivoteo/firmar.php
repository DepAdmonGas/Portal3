<div x-data="pivoteoFirmarComponent()" class="mt-3 mb-4"
     data-detalle='<?= htmlspecialchars(json_encode($detalle, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>

    <!-- Alerta -->
    <template x-if="estatus === 0">
        <div class="alert alert-warning border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-4">
            <h4 class="fw-semibold mb-2">¡Pivoteo pendiente de finalizar!</h4>
            <p class="mb-0">
                Para poder firmar el pivoteo primero debe ser <strong>finalizado</strong>.
            </p>
        </div>
    </template>

    <!-- Tabla superior -->
    <div class="table-responsive">
       <table class="table table-bordered mb-3 text-nowrap align-middle">
            <tbody>
                <tr>
                    <td class="align-middle text-center"><b>Depto. Operativo</b></td>
                    <td class="align-middle text-center" rowspan="3"><h5><b>Pivoteo</b></h5></td>
                    <td class="align-middle text-end"><b>Sucursal:</b></td>
                    <td class="align-middle text-center ps-2" x-text="detalle.sucursal || 'Sin información'"></td>
                </tr>
                <tr>
                    <th class="align-middle text-center" rowspan="2">G500 Network Operación y Finanzas</th>
                    <td class="align-middle text-end"><b>Fecha:</b></td>
                    <td class="align-middle text-center ps-2" x-text="detalle.fecha_display || 'Sin información'"></td>
                </tr>
                <tr>
                    <th class="align-middle text-end">No. De control:</th>
                    <td class="align-middle text-center ps-3" x-text="detalle.nocontrol_txt || ''"></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Causa -->

<div class="card">

<div class="card-header card-colored-header bg-primary">
<h4 class="card-title text-white mb-0">
<i class="ti ti-help-circle"></i> Causa</h4>
</div>

<div class="card-body ">
<p x-text="detalle.causa || 'Sin información'"></p>
</div>

</div>



    <!-- Líneas del pivoteo -->
    <template x-if="filas.length > 0">
        <div class="row mt-3">
            <template x-for="fila in filas" :key="fila.id_detalle">
                <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
                    <div class="table-responsive">
                       <table class="table table-striped table-bordered text-nowrap align-middle">
                            <thead>
                                <tr class="tables-bg text-white text-center">
                                    <th width="50%" colspan="3">Documentación Facturada (CANCELAR)</th>
                                    <th width="50%" colspan="3">Documentación a refacturar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th class="text-center align-middle" width="250px">Estación:</th>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.estacion_fc"></td>
                                    <td class="text-center align-middle" width="250px"><b>Estación:</b></td>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.estacion_fn"></td>
                                </tr>
                                <tr>
                                    <th class="text-center align-middle">Destino:</th>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.destino_fc"></td>
                                    <td class="text-center align-middle"><b>Destino:</b></td>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.destino_fn"></td>
                                </tr>
                                <tr>
                                    <th class="text-center align-middle">Producto:</th>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.producto_fc"></td>
                                    <td class="text-center align-middle"><b>Producto:</b></td>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.producto_fc"></td>
                                </tr>
                                <tr>
                                    <th class="text-center align-middle">Tanque:</th>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.tanque_fc"></td>
                                    <td class="text-center align-middle"><b>Tanque:</b></td>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.tanque_fn"></td>
                                </tr>
                                <tr>
                                    <th class="text-center align-middle">Factura:</th>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.factura_fc"></td>
                                    <td class="text-center align-middle"><b>Factura:</b></td>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.factura_fn"></td>
                                </tr>
                                <tr>
                                    <th class="text-center align-middle">Litros:</th>
                                    <td class="text-center align-middle" colspan="2" x-text="formatoLitros(fila.litros)"></td>
                                    <td class="text-center align-middle"><b>Litros:</b></td>
                                    <td class="text-center align-middle" colspan="2" x-text="formatoLitros(fila.litros)"></td>
                                </tr>
                                <tr>
                                    <th class="text-center align-middle">TAD:</th>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.tad"></td>
                                    <td class="text-center align-middle"><b>TAD:</b></td>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.tad"></td>
                                </tr>
                                <tr>
                                    <th class="text-center align-middle">Unidad:</th>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.unidad"></td>
                                    <td class="text-center align-middle"><b>Unidad:</b></td>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.unidad"></td>
                                </tr>
                                <tr>
                                    <th class="text-center align-middle">Chofer:</th>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.chofer"></td>
                                    <td class="text-center align-middle"><b>Chofer:</b></td>
                                    <td class="text-center align-middle" colspan="2" x-text="fila.chofer"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <template x-if="filas.length === 0">
        <div class="alert alert-secondary border-0 text-center text-muted p-5 mt-4 rounded-3" role="alert">
            <h5 class="fw-semibold text-dark mb-2">Sin líneas registradas</h5>
            <p class="fs-5 mb-0">Agrega una nueva línea para comenzar a capturar el pivoteo.</p>
        </div>
    </template>

    <!---------- FIRMA ---------->
    <div class="row">
<div class="col-md-4 mb-3">
    <div class="card border h-100">
        <!-- Encabezado de la tarjeta -->
        <div class="card-header bg-primary text-white py-3 border-0">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                    <i class="ti fs-6" :class="firmaB ? 'ti-user-check' : (puedeFirmarAhora ? 'ti-circle-check' : 'ti-clock-hour-4')"></i>
                </div>
                <div class="ms-3">
                    <h6 class="mb-0 text-white text-uppercase">FIRMA DEL VOBO</h6>
                </div>
            </div>
        </div>

        <!-- Cuerpo de la tarjeta -->
        <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
            
            <!-- Estado 1: Ya está firmado -->
            <template x-if="firmaB">

          <div class="w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                <i class="ti ti-signature text-primary mb-3" style="font-size:80px;"></i>
                                <h6 class="text-dark mb-2">El formato se firmó por un medio electrónico.</h6>
                                <h6 class="text-dark mb-0"><strong x-text="'Fecha: ' + firmaB.fecha"></strong></h6>
                            </div>
            </template>

            <!-- Estado 2: Puede firmar (Muestra el diseño exacto que pediste) -->
            <template x-if="!firmaB && puedeFirmarAhora">
                <div class="w-100 d-flex flex-column justify-content-center align-items-center">
                    <h4 class="text-primary mb-3">Recepción de Token</h4>
                    <small class="text-primary mb-4">
                        Ingrese el token de seguridad que recibió por Telegram o correo electrónico.
                        Si aún no cuenta con uno, haga clic en alguno de los siguientes botones para generarlo.
                    </small>
                    <div class="row w-100">
                        <div class="col-md-6 mb-3">
                            <button type="button" class="btn btn-success w-100" @click="crearTokenTelegram()" :disabled="botonesDeshabilitados">
                                <i class="ti ti-brand-telegram me-1"></i> Generar token vía Telegram
                            </button>
                        </div>
                        <div class="col-md-6 mb-3">
                            <button type="button" class="btn btn-info text-white w-100" @click="crearTokenEmail()" :disabled="botonesDeshabilitados">
                                <i class="ti ti-mail me-1"></i> Generar token vía Email
                            </button>
                        </div>
                        <div class="col-12">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Token de seguridad" x-model="token">
                                <button class="btn btn-outline-success" type="button" @click="firmar()" :disabled="!token.trim() || guardando">
                                    Firmar solicitud
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Estado 3: Aún no se puede firmar (Falta firma anterior) -->
            <template x-if="!firmaB && !puedeFirmarAhora">
                <div>
                    <i class="ti ti-signature-off text-muted mb-3" style="font-size:100px;"></i>
                    <h6 class="text-muted mb-0">¡Falta la firma del Depto. Operativo!</h6>
                </div>
            </template>
        </div>

        <!-- Pie de tarjeta -->
        <div class="card-footer bg-light text-center">
            <template x-if="firmaB">
                <h6 class="mb-0 fw-semibold text-truncate" x-text="firmaB.nombre"></h6>
            </template>
            <template x-if="!firmaB">
                <small class="text-muted">Pendiente de firma electrónica</small>
            </template>
        </div>
    </div>
</div>

    </div>

</div>

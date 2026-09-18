<div id="container" class="mt-4 mb-5"
     data-id-usuario="<?= (int)$idUsuario ?>"
     data-id-estacion="<?= (int)$idEstacion ?>"
     data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
     data-module-station-key="<?= htmlspecialchars($moduleStationKey, ENT_QUOTES, 'UTF-8') ?>"
     data-correo-default="cambiosdedestinovdm@g500network.com"
     data-puede-crear="<?= $permisos['puedeCrear'] ? 'true' : 'false' ?>"
     data-puede-editar="<?= $permisos['puedeEditar'] ? 'true' : 'false' ?>"
     data-puede-eliminar="<?= $permisos['puedeEliminar'] ? 'true' : 'false' ?>"
     x-data="{ ...actions(), ...pivoteoComponent() }">

    <style>
        @media (min-width: 992px) {
            #container .table-responsive { overflow-x: auto; }
        }
    </style>

    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap mb-3 gap-2">
                <div id="pivoteo-pending-wrapper" class="d-flex align-items-center gap-1">
                    <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis d-inline-flex align-items-center gap-1 px-3 py-2 fs-2 fw-semibold">
                        <i class="ti ti-alert-circle fs-4"></i>
                        <span>Pendientes: <span id="pivoteo-pending-count">0</span></span>
                    </span>
                </div>
                <button type="button" class="btn bg-primary-subtle text-primary" @click="abrirNuevo()" x-show="puedeCrear && estacionEspecifica">
                    <i class="ti ti-plus me-1"></i> Nuevo
                </button>
            </div>
        </div>
    </div>

    <span id="pivoteo-pendientes-data" style="display:none;"><?= htmlspecialchars(json_encode($pendientesData ?? []), ENT_QUOTES, 'UTF-8') ?></span>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-4">
            <table id="tabla-pivoteo" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
                <tbody></tbody>
            </table>
        </div>
    </div>

    <!-- Modal Detalle -->
    <div class="modal fade" id="modalDetalle" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-eye me-2"></i>Detalle pivoteo <span x-text="detalleData.id ? '#' + detalleData.id : ''"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div x-show="detalleLoading" class="text-center py-4">
                        <span class="spinner-border text-primary"></span>
                    </div>

                    <template x-if="!detalleLoading">
                        <div>
                            <div class="table-responsive">
                                <table class="table table-bordered mb-3 text-nowrap align-middle">
                                    <tbody>
                                        <tr>
                                            <td class="align-middle text-center"><b>Depto. Operativo</b></td>
                                            <td class="align-middle text-center" rowspan="3"><h5><b>Pivoteo</b></h5></td>
                                            <td class="align-middle text-end"><b>Sucursal:</b></td>
                                            <td class="align-middle text-center ps-2" x-text="detalleData.sucursal || 'Sin información'"></td>
                                        </tr>
                                        <tr>
                                            <th class="align-middle text-center" rowspan="2">G500 Network Operación y Finanzas</th>
                                            <td class="align-middle text-end"><b>Fecha:</b></td>
                                            <td class="align-middle text-center ps-2" x-text="detalleData.fecha_display || 'Sin información'"></td>
                                        </tr>
                                        <tr>
                                            <th class="align-middle text-end">No. De control:</th>
                                            <td class="align-middle text-center ps-3" x-text="detalleData.nocontrol_txt || ''"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Causa -->
                            <div class="card mb-3">
                                <div class="card-header card-colored-header bg-primary">
                                    <h4 class="card-title text-white mb-0">
                                        <i class="ti ti-help-circle"></i> Causa
                                    </h4>
                                </div>
                                <div class="card-body">
                                    <p x-text="detalleData.causa || 'Sin información'"></p>
                                </div>
                            </div>

                            <!-- Líneas del pivoteo -->
                            <template x-if="detalleData.filas.length > 0">
                                <div class="row">
                                    <template x-for="fila in detalleData.filas" :key="fila.id_detalle">
                                        <div class="col-12 mb-3">
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
                                                            <th class="text-center align-middle" width="150px">Estación:</th>
                                                            <td class="text-center align-middle" colspan="2" x-text="fila.estacion_fc"></td>
                                                            <td class="text-center align-middle" width="150px"><b>Estación:</b></td>
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

                            <template x-if="detalleData.filas.length === 0">
                                <div class="alert alert-secondary border-0 text-center text-muted p-5 rounded-3" role="alert">
                                    <h5 class="fw-semibold text-dark mb-2">Sin líneas registradas</h5>
                                </div>
                            </template>

                            <!-- Firmas -->
<!-- SECCIÓN DE FIRMAS / SUPERVISIÓN -->
<template x-if="detalleData.firmas.length > 0">
    <div class="row mt-2">
        <template x-for="(firma, i) in detalleData.firmas" :key="i">
            <div class="col-12 mb-4">
                <div class="card h-100 shadow-sm">

                    <!-- Header de la Card -->
                    <div class="card-header bg-primary text-white py-3 border-0">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                                <i class="ti ti-shield-check fs-6"></i>
                            </div>
                            <div class="ms-3">
                                <!-- Muestra el título dependiendo del rol o tipo de firma, ej. "SUPERVISÓ" o el rol que venga en firma -->
                                <h6 class="mb-0 text-white" x-text="firma.rol || 'NOMBRE Y FIRMA DEL VO.BO'"></h6>
                            </div>
                        </div>
                    </div>

                    <!-- Body de la Card -->
                    <div class="card-body d-flex justify-content-center align-items-center text-center p-4">

                        <!-- 1. YA FIRMÓ (Si la firma ya cuenta con fecha o estado registrado) -->
                        <template x-if="firma.fecha || firma.firmado">
                            <div class="w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                <i class="ti ti-signature text-primary mb-3" style="font-size:80px;"></i>
                                <h6 class="text-dark mb-2">El formato se firmó por un medio electrónico.</h6>
                                <h6 class="text-dark mb-0"><strong x-text="'Fecha: ' + firma.fecha"></strong></h6>
                            </div>
                        </template>

                        <!-- 2. PUEDE FIRMAR (Condición si el usuario actual es quien debe firmar y aun no lo hace) -->
                        <template x-if="!firma.fecha && !firma.firmado && firma.es_usuario_actual">
                            <div class="w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                <h4 class="text-primary">Recepción de Token</h4>
                                <small class="text-secondary mb-3">Ingrese el token de seguridad que recibió por Telegram o correo electrónico:</small>

                                <div class="row w-100">
                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-success w-100" @click="crearToken('telegram', firma)" :disabled="enviandoToken">
                                            <span x-show="enviandoToken" class="spinner-border spinner-border-sm me-1"></span>
                                            <i class="ti ti-brand-telegram me-1" x-show="!enviandoToken"></i> Telegram
                                        </button>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <button class="btn btn-info text-white w-100" @click="crearToken('email', firma)" :disabled="enviandoToken">
                                            <span x-show="enviandoToken" class="spinner-border spinner-border-sm me-1"></span>
                                            <i class="ti ti-mail me-1" x-show="!enviandoToken"></i> Email
                                        </button>
                                    </div>

                                    <div class="col-12">
                                        <div class="input-group">
                                            <input type="text" class="form-control" placeholder="Token de seguridad" x-model="tokenInput">
                                            <button class="btn btn-outline-success" type="button" @click="firmarConToken(firma)" :disabled="enviandoToken">
                                                <span x-show="enviandoToken" class="spinner-border spinner-border-sm me-1"></span> Firmar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <template x-if="tokenError">
                                    <small class="text-danger mt-2" x-text="tokenError"></small>
                                </template>
                            </div>
                        </template>

                        <!-- 3. NO PUEDE FIRMAR / PENDIENTE (Falta la firma y le pertenece a otro usuario) -->
                        <template x-if="!firma.fecha && !firma.firmado && !firma.es_usuario_actual">
                            <div class="w-100 h-100 d-flex flex-column justify-content-center align-items-center">
                                <i class="ti ti-signature-off text-muted mb-3" style="font-size:80px;"></i>
                                <h6 class="text-muted mb-0">¡Falta la firma de <span x-text="firma.nombre"></span>!</h6>
                            </div>
                        </template>

                    </div>

                    <!-- Footer de la Card -->
                    <div class="card-footer bg-light text-center py-2">
                        <template x-if="firma.fecha || firma.firmado">
                            <div>
                                <h6 class="mb-0 fw-semibold" x-text="firma.nombre"></h6>
                            </div>
                        </template>

                        <template x-if="!firma.fecha && !firma.firmado">
                            <div>
                                <h6 class="mb-0 fw-semibold text-dark" x-text="firma.nombre"></h6>
                                <small class="text-muted">Pendiente de firma</small>
                            </div>
                        </template>
                    </div>

                </div>
            </div>
        </template>
    </div>
</template>



                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Envío por correo -->
    <div class="modal fade" id="modalGmail" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-mail me-2"></i>Envío por correo (<span x-text="gmailId ? '#' + gmailId : ''"></span>)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Correo:</label>
                            <input type="email" class="form-control" x-model="gmailForm.correo">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Asunto:</label>
                            <input type="text" class="form-control" x-model="gmailForm.asunto">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Contenido:</label>
                            <textarea class="form-control" rows="4" x-model="gmailForm.contenido"></textarea>
                        </div>
                    </div>

                    <h5 class="fw-semibold text-primary mt-3 mb-2">Historial de envíos</h5>
       <template x-if="gmailHistorial.length > 0">
    <div style="max-height: 200px; overflow-y: auto;" class="pe-1">
        <div class="list-group list-group-flush gap-2">
            <template x-for="(hc, i) in gmailHistorial" :key="i">
                <div class="list-group-item border rounded-3 bg-light-subtle px-3 py-2 d-flex justify-content-between align-items-center shadow-none">
                    
                    <!-- Información del correo y fecha -->
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 36px; height: 36px;">
                            <i class="ti ti-mail-check fs-5"></i>
                        </div>
                        <div>
                            <div class="fw-semibold text-dark text-break" style="font-size: 13.5px;" x-text="hc.correo"></div>
                            <small class="text-muted d-flex align-items-center mt-0.5">
                                <i class="ti ti-clock me-1" style="font-size: 11px;"></i>
                                <span x-text="hc.fecha_creacion"></span>
                            </small>
                        </div>
                    </div>

                    <!-- Etiqueta de estado -->
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 ms-2 flex-shrink-0" style="font-size: 11px;">
                        Enviado
                    </span>

                </div>
            </template>
        </div>
    </div>
</template>
<div class="alert alert-secondary text-center mb-0" role="alert" x-show="gmailHistorial.length === 0">
    <p class="mb-0">No se encontro información.</p>
</div>                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" @click="guardarGmail()" :disabled="guardando">
                        <template x-if="!guardando"><i class="ti ti-send fs-5 me-1"></i></template>
                        <template x-if="guardando">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                        </template>
                        Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

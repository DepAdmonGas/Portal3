<div x-data="permisosFirmarComponent()" class="mt-3 mb-4"
     data-id="<?= (int)$detalle['id'] ?>"
     data-estado="<?= (int)$detalle['estado'] ?>"
     data-id-usuario="<?= (int)$idUsuario ?>"
     data-puede-firmar-cubre="<?= !empty($permisosFirma['puedeFirmarCubre']) ? 'true' : 'false' ?>"
     data-puede-firmar-vobo="<?= !empty($permisosFirma['puedeFirmarVoBo']) ? 'true' : 'false' ?>"
     data-detalle='<?= htmlspecialchars(json_encode($detalle, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>

<!---------- STATUS ALERTS ---------->
<template x-if="alertMensaje">
<div class="row">
    <div class="col-12 mb-4">
        <div class="alert border-0 d-flex flex-column justify-content-center align-items-center text-center py-4 px-4 mb-0" :class="alertMensaje.clase">
            <h4 class="fw-semibold mb-2" x-text="alertMensaje.titulo"></h4>
            <p class="mb-0" x-html="alertMensaje.texto"></p>
        </div>
    </div>
</div>
</template>

<!---------- DESCRIPCION / DATOS ---------->
<div class="card mb-4">

    <div class="card-header bg-primary text-white py-3 border-0">
        <h5 class="mb-0 text-white d-flex align-items-center gap-2">
            <i class="ti ti-notes fs-5"></i>
            <span>Datos del permiso</span>
        </h5>
    </div>

    <div class="card-body pb-2">

        <div class="row">
            <div class="col-12 text-end mb-3">
                <span class="badge" :class="estado === 2 ? 'bg-success' : (estado === 1 ? 'bg-warning' : 'bg-danger')">
                    <span x-text="detalle.estado_label || ''"></span>
                </span>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-12 text-end">
                <b>No. de Control:</b> <span x-text="('00' + detalle.id)"></span>
                <p x-text="detalle.fecha || ''"></p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <b>Estación / Departamento:</b>
                <p x-text="detalle.estacion || ''"></p>
            </div>
            <div class="col-md-8">
                <label class="form-label mb-1">Colaborador:</label>
                <p x-text="detalle.nombre_colaborador || ''"></p>
            </div>

            <div class="col-md-4">
                <label class="form-label mb-1">Quien cubre:</label>
                <p x-text="detalle.cubre_nombre || ''"></p>
            </div>

            <div class="col-md-8">
                <label class="form-label mb-1">Estación de quien cubre:</label>
                <template x-if="detalle.estacion_cubre_nombre">
                    <p x-text="detalle.estacion_cubre_nombre"></p>
                </template>
            </div>

            <div class="col-md-4">
                <label class="form-label mb-1">Del:</label>
                <p x-text="detalle.fecha_inicio_label || ''"></p>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Al:</label>
                <p x-text="detalle.fecha_termino_label || ''"></p>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1">Días:</label>
                <p x-text="detalle.dias_tomados != null ? detalle.dias_tomados : ''"></p>
            </div>
            <div class="col-12">
                <label class="form-label mb-1">Motivo:</label>
                <p x-text="detalle.motivo || ''"></p>
            </div>
            <div class="col-12" x-show="detalle.observaciones">
                <label class="form-label mb-1">Observaciones:</label>
                <p x-text="detalle.observaciones"></p>
            </div>
        </div>

    </div>
</div>

<!---------- FIRMAS ---------->
<div class="row">

    <!-- Card A: SOLICITANTE -->
    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
        <div class="card border h-100">
            <div class="card-header bg-primary text-white py-3 border-0">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                        <i class="ti ti-user-check fs-5" x-show="firmaA"></i>
                        <i class="ti ti-clock-hour-4 fs-5" x-show="!firmaA"></i>
                    </div>
                    <div class="ms-3 overflow-hidden">
                        <h6 class="mb-0 text-white" x-text="firmaA ? firmaA.tipo_label : 'NOMBRE Y FIRMA DEL SOLICITANTE'"></h6>
                    </div>
                </div>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                <template x-if="firmaA">
                    <img :src="firmaA.firma_img_url" class="img-fluid w-75" :alt="firmaA.usuario_nombre || 'Firma'">
                </template>
                <template x-if="!firmaA">
                    <div class="text-center">
                        <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                        <h6 class="text-muted mb-0">Sin firma registrada</h6>
                    </div>
                </template>
            </div>
            <div class="card-footer bg-light text-center">
                <h6 class="mb-0 fw-semibold text-truncate" x-text="firmaA ? firmaA.usuario_nombre : ''"></h6>
                <small class="text-muted" x-show="!firmaA">Pendiente de firma</small>
            </div>
        </div>
    </div>

    <!-- Card B: QUIEN CUBRE -->
    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
        <div class="card border h-100">
            <div class="card-header bg-primary text-white py-3 border-0">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                        <i class="ti ti-user-check fs-5" x-show="firmaB"></i>
                        <i class="ti ti-circle-check fs-5" x-show="!firmaB && estado === 0 && puedeFirmarCubre"></i>
                        <i class="ti ti-clock-hour-4 fs-5" x-show="!firmaB && !(estado === 0 && puedeFirmarCubre)"></i>
                    </div>
                    <div class="ms-3 overflow-hidden">
                        <h6 class="mb-0 text-white" x-text="firmaB ? firmaB.tipo_label : 'NOMBRE Y FIRMA DE QUIEN CUBRE'"></h6>
                    </div>
                </div>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                <template x-if="firmaB">
                    <img :src="firmaB.firma_img_url" class="img-fluid w-75" :alt="firmaB.usuario_nombre || 'Firma'">
                </template>
                <template x-if="!firmaB && estado === 0 && puedeFirmarCubre">
                    <div class="w-100">
                        <div class="card card-border shadow-none w-100 pt-0">
                            <div class="card-body p-0">
                                <div class="position-relative border border-3 border-dashed rounded-3 overflow-hidden" style="height:250px; background:#fff;">
                                    <canvas id="firma-canvas-b" style="width:100%; height:100%; touch-action:none;"></canvas>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-outline-danger w-100 mt-3 mb-2" @click="limpiarFirmaB()">
                            <i class="ti ti-eraser me-1"></i> Limpiar
                        </button>
                        <button type="button" class="btn btn-success w-100" @click="firmarQuienCubre()" :disabled="firmandoCubre">
                            <template x-if="!firmandoCubre"><i class="ti ti-check me-1"></i></template>
                            <template x-if="firmandoCubre"><span class="spinner-border spinner-border-sm me-1"></span></template>
                            Finalizar
                        </button>
                    </div>
                </template>
                <template x-if="!firmaB && !(estado === 0 && puedeFirmarCubre)">
                    <div class="text-center">
                        <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                        <h6 class="text-muted mb-0">¡Falta la firma de quien cubre!</h6>
                    </div>
                </template>
            </div>
            <div class="card-footer bg-light text-center">
                <h6 class="mb-0 fw-semibold text-truncate" x-text="firmaB ? firmaB.usuario_nombre : ''"></h6>
                <small class="text-muted" x-show="!firmaB" x-text="(estado === 0 && puedeFirmarCubre) ? 'Firma pendiente de registrar' : 'Pendiente de firma'"></small>
            </div>
        </div>
    </div>

    <!-- Card C: VISTO BUENO -->
    <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
        <div class="card border h-100">
            <div class="card-header bg-primary text-white py-3 border-0">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:50px;height:50px;">
                        <i class="ti ti-user-check fs-5" x-show="firmaC"></i>
                        <i class="ti ti-circle-check fs-5" x-show="!firmaC && estado === 1 && puedeFirmarVoBo"></i>
                        <i class="ti ti-clock-hour-4 fs-5" x-show="!firmaC && !(estado === 1 && puedeFirmarVoBo)"></i>
                    </div>
                    <div class="ms-3 overflow-hidden">
                        <h6 class="mb-0 text-white" x-text="firmaC ? firmaC.tipo_label : 'NOMBRE Y FIRMA DEL VISTO BUENO'"></h6>
                    </div>
                </div>
            </div>
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center p-4">
                <template x-if="firmaC">
                    <div class="text-center">
                        <i class="ti ti-signature text-primary mb-3" style="font-size:100px;"></i>
                        <div class="text-dark" style="font-size:.9em;" x-text="firmaC.firma_texto || ''"></div>
                    </div>
                </template>
                <template x-if="!firmaC && estado === 1 && puedeFirmarVoBo">
                    <div class="w-100">
                        <h4 class="text-primary mb-3">Recepción de Token</h4>
                        <small class="text-secondary" style="font-size:.85em;">
                            Ingrese el token de seguridad que recibió por Telegram o correo electrónico. Si aún no cuenta con uno, haga clic en alguno de los siguientes botones para generarlo.
                        </small>
                        <div class="row w-100 mt-2">
                            <div class="col-md-6 mb-3">
                                <button type="button" class="btn btn-success w-100" @click="crearToken('telegram')" :disabled="botonesDeshabilitados">
                                    <i class="ti ti-brand-telegram me-1"></i> Generar token vía Telegram
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <button type="button" class="btn btn-info text-white w-100" @click="crearToken('email')" :disabled="botonesDeshabilitados">
                                    <i class="ti ti-mail me-1"></i> Generar token vía Email
                                </button>
                            </div>
                            <div class="col-12">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Token de seguridad" x-model="token">
                                    <button class="btn btn-outline-success" type="button" @click="firmarVoBo()" :disabled="firmando || !token.trim()">
                                        Firmar visto bueno
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
                <template x-if="!firmaC && !(estado === 1 && puedeFirmarVoBo)">
                    <div class="text-center">
                        <i class="ti ti-signature-off text-gray mb-3" style="font-size:100px;"></i>
                        <h6 class="text-muted mb-0">¡Falta la firma del Visto Bueno!</h6>
                    </div>
                </template>
            </div>
            <div class="card-footer bg-light text-center">
                <h6 class="mb-0 fw-semibold text-truncate" x-text="firmaC ? firmaC.usuario_nombre : ''"></h6>
                <small class="text-muted" x-show="!firmaC" x-text="(estado === 1 && puedeFirmarVoBo) ? 'Pendiente de firma electrónica' : 'Pendiente de firma electrónica'"></small>
            </div>
        </div>
    </div>

</div>

</div>
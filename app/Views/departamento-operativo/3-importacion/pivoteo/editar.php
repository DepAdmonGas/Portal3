<div id="container" class="mt-4 mb-5"
x-data="{ ...actions(), ...pivoteoEditarComponent() }"
data-detalle='<?= htmlspecialchars(json_encode($detalle, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'>

<style>
@media (min-width: 992px) {
#container .table-responsive { overflow-x: auto; }
}
#container .custom-table { font-size: .9em; }
#container select[data-select2-inline] + .select2-container { width: 100% !important; }
#container select[data-select2-inline] + .select2-container .select2-selection--single {
    border: 0 !important;
    background-color: transparent !important;
    border-radius: 0 !important;
    height: auto;
    padding: .375rem .75rem;
}
#container select[data-select2-inline] + .select2-container .select2-selection__rendered {
    text-align: center;
    line-height: 1.5;
    color: inherit;
    padding-left: 1.75rem;
    padding-right: 1.75rem;
}
#container select[data-select2-inline] + .select2-container .select2-selection__placeholder { color: #6c757d; }
</style>

<!-- Barra de acciones -->
<div class="d-flex flex-wrap align-items-center mb-3">
<div class="ms-auto d-flex flex-wrap align-items-center">
<button type="button" class="btn bg-primary-subtle text-primary" @click="abrirAgregar()" x-show="puedeAgregar">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
<button type="button" class="btn btn-success ms-2" @click="confirmarFinalizar()" x-show="puedeFinalizar">
<i class="ti ti-check me-1"></i> Finalizar
</button>
</div>
</div>

<!-- Tabla superior -->
<template x-if="multiestacion">
<div class="table-responsive">
<table class="table table-bordered mb-3 text-nowrap align-middle">
<tbody>
<tr>
<td class="align-middle text-center"><b>Depto. Operativo</b></td>
<td class="align-middle text-center" rowspan="3"><h5><b>Pivoteo</b></h5></td>
<td class="align-middle text-end"><b>Sucursal:</b></td>
<td class="align-middle p-0">
<template x-if="puedeEditarCabecera">
<textarea class="form-control border-0 rounded-0 text-center" rows="1"
x-text="detalle.sucursal"
@change="guardarCabecera(7, $el.value)"></textarea>
</template>
<template x-if="!puedeEditarCabecera">
<div class="ps-2" x-text="detalle.sucursal"></div>
</template>
</td>
</tr>
<tr>
<th class="align-middle text-center" rowspan="2">G500 Network Operación y Finanzas</th>
<td class="align-middle text-end"><b>Fecha:</b></td>
<td class="align-middle p-0">
<template x-if="puedeEditarCabecera">
<input type="date" class="form-control border-0 rounded-0 text-center"
:value="detalle.fecha"
@change="guardarFechaDirecta($el.value)">
</template>
<template x-if="!puedeEditarCabecera">
<div class="ps-2 text-center" x-text="detalle.fecha_display"></div>
</template>
</td>
</tr>
<tr>
<th class="align-middle text-end">No. De control:</th>
<td class="align-middle text-center ps-3" x-text="detalle.nocontrol_txt"></td>
</tr>
</tbody>
</table>
</div>
</template>

<!-- Causa -->
<template x-if="multiestacion">
<div class="card">

<div class="card-header card-colored-header bg-primary">
<h4 class="card-title text-white mb-0">
<i class="ti ti-help-circle"></i> Causa</h4>
</div>

<div class="card-body p-0">
<template x-if="puedeEditarCabecera">
<textarea class="form-control border-0 rounded-0 p-3" rows="4"
x-text="detalle.causa"
@change="guardarCabecera(9, $el.value)"></textarea>
</template>
<template x-if="!puedeEditarCabecera">
<div class="ps-2 py-2 p-3" x-text="detalle.causa"></div>
</template>
</div>

</div>
</template>

<!-- Líneas del pivoteo -->
<template x-if="filas.length > 0">
    <div class="row">
        <template x-for="fila in filas" :key="fila.id_detalle">
            <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3 ">
                <div class="card h-100">
                    <div class="card-body p-0">
                        <div class="table-responsive h-100 ">
                            <table class="table table-striped table-bordered align-middle text-center mb-0 h-100" style="width: 100%;">
                                <thead>
                                    <tr class="text-center align-middle text-white">
                                        <th colspan="3">Documentación Facturada (CANCELAR)</th>
                                        <th colspan="3">Documentación a refacturar</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                  <!-- Estación -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Estación:</td>
                                        <td x-text="fila.estacion_fc"></td>
                                        <td class="p-0" width="98px">
                                            <template x-if="inlineEditable">
                                                <button type="button" class="btn bg-primary-subtle text-primary btn-sm w-100 h-100 rounded-0"
                                                        @click="abrirEstacion(fila.id_detalle, 1, fila.estacion_fc, fila.destino_fc)">
                                                    <i class="ti ti-pencil fs-5"></i>
                                                </button>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Estación:</td>
                                        <td x-text="fila.estacion_fn"></td>
                                        <td class="p-0" width="98px">
                                            <template x-if="inlineEditable">
                                                <button type="button" class="btn bg-primary-subtle text-primary btn-sm w-100 h-100 rounded-0"
                                                        @click="abrirEstacion(fila.id_detalle, 2, fila.estacion_fn, fila.destino_fn)">
                                                    <i class="ti ti-pencil fs-5"></i>
                                                </button>
                                            </template>
                                        </td>
                                    </tr>

                                    <!-- Destino -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Destino:</td>
                                        <td colspan="2" x-text="fila.destino_fc"></td>
                                        <td class="text-center fw-semibold">Destino:</td>
                                        <td colspan="2" x-text="fila.destino_fn"></td>
                                    </tr>

                                    <!-- Producto -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Producto:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center"
                                                        @change="editarCampo(fila.id_detalle, 12, $el.value)">
                                                    <template x-for="(op, oi) in opciones(productos, fila.producto_fc)" :key="'pfc-' + oi">
                                                        <option :value="op" :selected="op === String(fila.producto_fc)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.producto_fc"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Producto:</td>
                                        <td colspan="2" x-text="fila.producto_fc"></td>
                                    </tr>

                                    <!-- Tanque -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Tanque:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center"
                                                        @change="editarCampo(fila.id_detalle, 10, $el.value)">
                                                    <template x-for="(op, oi) in opciones(tanques, fila.tanque_fc)" :key="'tfc-' + oi">
                                                        <option :value="op" :selected="op === String(fila.tanque_fc)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.tanque_fc"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Tanque:</td>
                                        <td colspan="2" x-text="fila.tanque_fn"></td>
                                    </tr>

                                    <!-- Factura -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Factura:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <input type="text" class="form-control border-0 rounded-0 text-center"
                                                       :value="fila.factura_fc"
                                                       @change="editarCampo(fila.id_detalle, 1, $el.value)">
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.factura_fc"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Factura:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <input type="text" class="form-control border-0 rounded-0 text-center"
                                                       :value="fila.factura_fn"
                                                       @change="editarCampo(fila.id_detalle, 2, $el.value)">
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.factura_fn"></div>
                                            </template>
                                        </td>
                                    </tr>

                                    <!-- Litros -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Litros:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <input type="number" step="any" class="form-control border-0 rounded-0 text-center"
                                                       :value="fila.litros"
                                                       @change="editarCampo(fila.id_detalle, 11, $el.value)">
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="formatoLitros(fila.litros)"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Litros:</td>
                                        <td colspan="2" x-text="formatoLitros(fila.litros)"></td>
                                    </tr>

                                    <!-- TAD -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">TAD:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center"
                                                        @change="editarCampo(fila.id_detalle, 3, $el.value)">
                                                    <template x-for="(op, oi) in opciones(tads, fila.tad)" :key="'tad-' + oi">
                                                        <option :value="op" :selected="op === String(fila.tad)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.tad"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">TAD:</td>
                                        <td colspan="2" x-text="fila.tad"></td>
                                    </tr>

                                    <!-- Unidad -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Unidad:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center" data-select2-inline="1" data-opcion="4" :data-detalle="fila.id_detalle">
                                                    <template x-for="(op, oi) in opciones(unidades, fila.unidad)" :key="'uni-' + oi">
                                                        <option :value="op" :selected="op === String(fila.unidad)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.unidad"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Unidad:</td>
                                        <td colspan="2" x-text="fila.unidad"></td>
                                    </tr>

                                    <!-- Chofer -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Chofer:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center" data-select2-inline="1" data-opcion="5" :data-detalle="fila.id_detalle">
                                                    <template x-for="(op, oi) in opciones(choferes, fila.chofer)" :key="'chf-' + oi">
                                                        <option :value="op" :selected="op === String(fila.chofer)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.chofer"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Chofer:</td>
                                        <td colspan="2" x-text="fila.chofer"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Footer de Acciones (Estilo Tarjeta) -->
                    <div class="card-footer bg-light">
                        <div class="row g-3">
                            <div class="col-12">
                                <template x-if="puedeEliminarDetalle">
                                    <button type="button" class="btn bg-danger text-white w-100"
                                            @click="confirmarEliminarDetalle(fila.id_detalle)">
                                        <i class="ti ti-trash me-1"></i> Eliminar
                                    </button>
                                </template>
                                <template x-if="!puedeEliminarDetalle">
                                    <button type="button" class="btn bg-danger text-white w-100 opacity-50" style="cursor: not-allowed;" disabled
                                            title="No se puede eliminar este registro">
                                        <i class="ti ti-trash me-1"></i> Eliminar
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </template>
    </div>
</template>

    <template x-if="filas.length === 0">
        <div class="alert alert-secondary border-0 text-center text-muted p-5 mt-4 rounded-3" role="alert">
            <h5 class="fw-semibold text-dark mb-2">Sin pivoteos registrados</h5>
            <p class="fs-5 mb-0">No se encontraron pivoteos registrados para este formato.</p>
        </div>
    </template>

    <!-- Modal Agregar Línea -->
    <div class="modal fade" id="modalAgregar" tabindex="-1" x-ref="modalAgregar">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-rotate me-2"></i>Nuevo <span x-text="'Pivoteo (#' + id + ')'"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label mb-1">* Producto:</label>
                            <select class="form-select" x-model="formAgregar.producto">
                                <option value=""></option>
                                <template x-for="(op, oi) in productos" :key="'ap-' + oi">
                                    <option :value="op" x-text="op"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">* Tanque:</label>
                            <select class="form-select" x-model="formAgregar.tanque">
                                <option value=""></option>
                                <template x-for="(op, oi) in tanques" :key="'at-' + oi">
                                    <option :value="op" x-text="op"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">* Litros:</label>
                            <input type="number" step="any" min="0" class="form-control" x-model="formAgregar.litros">
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">* TAD:</label>
                            <select class="form-select" x-model="formAgregar.tad">
                                <option value=""></option>
                                <template x-for="(op, oi) in tads" :key="'atd-' + oi">
                                    <option :value="op" x-text="op"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label mb-1">* Unidad:</label>
                            <div class="select2-modal-field is-select2-pending" x-ref="wrapUnidadAgregar">
                                <select class="form-select bg-transparent" x-ref="selUnidadAgregar">
                                    <option value=""></option>
                                    <template x-for="(op, oi) in unidades" :key="'au-' + oi">
                                        <option :value="op" x-text="op"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label mb-1">* Chofer:</label>
                            <div class="select2-modal-field is-select2-pending" x-ref="wrapChoferAgregar">
                                <select class="form-select bg-transparent" x-ref="selChoferAgregar">
                                    <option value=""></option>
                                    <template x-for="(op, oi) in choferes" :key="'ac-' + oi">
                                        <option :value="op" x-text="op"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" @click="guardarAgregar()" :disabled="guardando">
                        <template x-if="!guardando"><i class="ti ti-check fs-5 me-1"></i></template>
                        <template x-if="guardando">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                        </template>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Seleccionar Estación -->
    <div class="modal fade" id="modalEstacion" tabindex="-1" x-ref="modalEstacion">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-pencil me-2"></i>Editar <span x-text="'Pivoteo (#' + id + ')'"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12" :class="{ 'opacity-50 pe-none': !!estacionForm.estacionOtro || !!estacionForm.destinoOtro }">
                            <label class="form-label">* Estación:</label>
                            <div class="select2-modal-field is-select2-pending" x-ref="wrapEstacion">
                                <select class="form-select" x-ref="selEstacion" x-model="estacionForm.estacion"
                                        @change="estacionForm.estacionOtro = ''; estacionForm.destinoOtro = '';">
                                    <option value=""></option>
                                    <template x-for="(op, oi) in estaciones" :key="'es-' + oi">
                                        <option :value="op" x-text="op"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
<hr>
                        <h5 class="text-primary mb-3">Otra Estación</h5>

                            <div :class="{ 'opacity-50 pe-none': !!estacionForm.estacion }">


                            <label class="form-label mb-1">* Nombre:</label>
                                <input type="text" class="form-control mb-3" x-model="estacionForm.estacionOtro"
                                       :disabled="!!estacionForm.estacion"
                                       @input="estacionForm.estacion = ''">

                                       <label class="form-label mb-1">* Destino:</label>
                                    <input type="number" min="0" step="1" class="form-control mb-3" x-model="estacionForm.destinoOtro"
                                       :disabled="!!estacionForm.estacion"
                                       @input="estacionForm.estacion = ''">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-success" @click="guardarEstacion()" :disabled="guardando">
                        <template x-if="!guardando"><i class="ti ti-check fs-5 me-1"></i></template>
                        <template x-if="guardando">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                        </template>
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Envío por correo -->
    <div class="modal fade" id="modalGmail" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-mail me-2"></i>Envío por correo · <span x-text="'Pivoteo #' + id"></span></h5>
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
                </div>
                <div class="modal-body border-top">
                    <div class="fw-semibold text-secondary mb-2">Historial de envíos</div>
                    <template x-if="historialCorreos.length > 0">
                        <ul class="list-group list-group-flush" style="max-height: 180px; overflow-y: auto;">
                            <template x-for="(hc, idx) in historialCorreos" :key="'hc-' + idx">
                                <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                                    <div>
                                        <div class="fw-semibold text-break" x-text="hc.correo"></div>
                                        <small class="text-muted" x-text="hc.fecha_creacion"></small>
                                    </div>
                                    <i class="ti ti-mail-check text-success fs-5 ms-2"></i>
                                </li>
                            </template>
                        </ul>
                    </template>
                    <template x-if="historialCorreos.length === 0">
                        <p class="text-muted mb-0">Sin envíos registrados.</p>
                    </template>
                </div>
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

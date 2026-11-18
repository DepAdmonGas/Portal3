<div id="container" class="mt-4 mb-5"
data-portal-table-selection="false"
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
#modalAgregar .select2-modal-field.is-invalid .select2-selection,
#modalEstacion .select2-modal-field.is-invalid .select2-selection { border-color: var(--bs-danger) !important; }
#container select[data-select2-inline] + .select2-container .select2-selection.is-invalid {
    border: 1px solid var(--bs-danger) !important;
    box-shadow: 0 0 0 .15rem rgba(var(--bs-danger-rgb), .15) !important;
}
#container [data-required-field].is-invalid,
#container td.border-danger {
    border: 2px solid var(--bs-danger) !important;
    border-color: var(--bs-danger) !important;
    box-shadow: inset 0 0 0 1px var(--bs-danger) !important;
    background-color: #fff1f0 !important;
}
#container .pivoteo-save-cell { position: relative; }
#container .pivoteo-save-indicator {
    position: absolute; z-index: 4; right: .35rem; top: 50%; transform: translateY(-50%);
    display: inline-flex; align-items: center; gap: .2rem; padding: .1rem .3rem;
    border-radius: .25rem; background: rgba(255,255,255,.96); color: #198754;
    font-size: .7rem; line-height: 1.2; pointer-events: none; white-space: nowrap;
}
#container textarea ~ .pivoteo-save-indicator { top: .35rem; transform: none; }
</style>

<!-- Barra de acciones -->
<div class="row g-0 ms-auto w-100 justify-content-end mb-3">
                    <div class="col-12 col-md-auto d-grid me-0 me-md-2">
    <button type="button" class="btn bg-primary-subtle text-primary" @click="abrirAgregar()" x-show="puedeAgregar">
<i class="ti ti-plus "></i> 
Nuevo
</button>
                    </div>

                    <div class="col-12 col-md-auto d-grid mt-1 mt-md-0">
                     <button type="button" class="btn btn-success" @click="confirmarFinalizar()" x-show="puedeFinalizar">
<i class="ti ti-check"></i> 
Finalizar
</button> 
                    </div>
                  </div>

<template x-if="camposFaltantes.length > 0">
    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert" aria-live="assertive">
        <i class="ti ti-alert-circle fs-5 mt-1"></i>
        <div class="w-100">
            <strong>Completa los campos obligatorios:</strong>
            <ul class="mb-0 mt-1 ps-3">
                <template x-for="campo in camposFaltantes" :key="campo.key">
                    <li x-text="campo.etiqueta"></li>
                </template>
            </ul>
        </div>
    </div>
</template>






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
@change="guardarCabecera(7, $el.value, $el)"></textarea>
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
:data-required-detail="0" data-required-field="fecha"
:value="detalle.fecha"
@change="guardarFechaDirecta($el.value, $el)">
</template>
<template x-if="!puedeEditarCabecera">
<div class="ps-2 text-center" x-text="detalle.fecha_display" data-required-detail="0" data-required-field="fecha"></div>
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

<template x-if="!multiestacion">
    <div class="row mb-3">
        <div class="col-12 col-md-4">
            <label class="form-label fw-semibold">Fecha:</label>
            <template x-if="puedeEditarFecha">
                <input type="date" class="form-control" data-required-detail="0" data-required-field="fecha"
                       :value="detalle.fecha" @change="guardarFechaDirecta($el.value, $el)">
            </template>
            <template x-if="!puedeEditarFecha">
                <div class="form-control bg-light" data-required-detail="0" data-required-field="fecha" x-text="detalle.fecha_display || ''"></div>
            </template>
        </div>
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
@change="guardarCabecera(9, $el.value, $el)"></textarea>
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
                                        <td x-text="fila.estacion_fc" :data-required-detail="fila.id_detalle" data-required-field="estacion_fc"></td>
                                        <td class="p-0" width="98px">
                                            <template x-if="inlineEditable">
                                                <button type="button" class="btn bg-primary-subtle text-primary btn-sm w-100 h-100 rounded-0"
                                                        @click="abrirEstacion(fila.id_detalle, 1, fila.estacion_fc, fila.destino_fc)">
                                                    <i class="ti ti-edit fs-5"></i>
                                                </button>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Estación:</td>
                                        <td x-text="fila.estacion_fn" :data-required-detail="fila.id_detalle" data-required-field="estacion_fn"></td>
                                        <td class="p-0" width="98px">
                                            <template x-if="inlineEditable">
                                                <button type="button" class="btn bg-primary-subtle text-primary btn-sm w-100 h-100 rounded-0"
                                                        @click="abrirEstacion(fila.id_detalle, 2, fila.estacion_fn, fila.destino_fn)">
                                                    <i class="ti ti-edit fs-5"></i>
                                                </button>
                                            </template>
                                        </td>
                                    </tr>

                                    <!-- Destino -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Destino:</td>
                                        <td colspan="2" x-text="fila.destino_fc" :data-required-detail="fila.id_detalle" data-required-field="destino_fc"></td>
                                        <td class="text-center fw-semibold">Destino:</td>
                                        <td colspan="2" x-text="fila.destino_fn" :data-required-detail="fila.id_detalle" data-required-field="destino_fn"></td>
                                    </tr>

                                    <!-- Producto -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Producto:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center"
                                                        :data-required-detail="fila.id_detalle" data-required-field="producto_fc"
                                                        @change="editarCampo(fila.id_detalle, 12, $el.value, $el)">
                                                    <option value="" :selected="!String(fila.producto_fc || '').trim()">Selecciona una opción...</option>
                                                    <template x-for="(op, oi) in opciones(productos, fila.producto_fc)" :key="'pfc-' + oi">
                                                        <option :value="op" :selected="op === String(fila.producto_fc)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.producto_fc" :data-required-detail="fila.id_detalle" data-required-field="producto_fc"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Producto:</td>
                                        <td colspan="2" x-text="fila.producto_fc" :data-required-detail="fila.id_detalle" data-required-field="producto_fc"></td>
                                    </tr>

                                    <!-- Tanque -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Tanque:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center"
                                                        :data-required-detail="fila.id_detalle" data-required-field="tanque_fc"
                                                        @change="editarCampo(fila.id_detalle, 10, $el.value, $el)">
                                                    <option value="" :selected="!String(fila.tanque_fc || '').trim()">Selecciona una opción...</option>
                                                    <template x-for="(op, oi) in opciones(tanques, fila.tanque_fc)" :key="'tfc-' + oi">
                                                        <option :value="op" :selected="op === String(fila.tanque_fc)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.tanque_fc" :data-required-detail="fila.id_detalle" data-required-field="tanque_fc"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Tanque:</td>
                                        <td colspan="2" x-text="fila.tanque_fn" :data-required-detail="fila.id_detalle" data-required-field="tanque_fc"></td>
                                    </tr>

                                    <!-- Factura -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Factura:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <input type="text" class="form-control border-0 rounded-0 text-center"
                                                       :value="fila.factura_fc"
                                                       :data-required-detail="fila.id_detalle" data-required-field="factura_fc"
                                                       @change="editarCampo(fila.id_detalle, 1, $el.value, $el)">
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.factura_fc" :data-required-detail="fila.id_detalle" data-required-field="factura_fc"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Factura:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <input type="text" class="form-control border-0 rounded-0 text-center"
                                                       :value="fila.factura_fn"
                                                       :data-required-detail="fila.id_detalle" data-required-field="factura_fn"
                                                       @change="editarCampo(fila.id_detalle, 2, $el.value, $el)">
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.factura_fn" :data-required-detail="fila.id_detalle" data-required-field="factura_fn"></div>
                                            </template>
                                        </td>
                                    </tr>

                                    <!-- Litros -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Litros:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <input type="text" inputmode="decimal" class="form-control border-0 rounded-0 text-center"
                                                       :value="formatoLitros(fila.litros)"
                                                       :data-required-detail="fila.id_detalle" data-required-field="litros"
                                                       @input="formatearNumeroInput($event)"
                                                       @change="editarCampo(fila.id_detalle, 11, $el.value, $el)">
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="formatoLitros(fila.litros)" :data-required-detail="fila.id_detalle" data-required-field="litros"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Litros:</td>
                                        <td colspan="2" x-text="formatoLitros(fila.litros)" :data-required-detail="fila.id_detalle" data-required-field="litros"></td>
                                    </tr>

                                    <!-- TAD -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">TAD:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center"
                                                        :data-required-detail="fila.id_detalle" data-required-field="tad"
                                                        @change="editarCampo(fila.id_detalle, 3, $el.value, $el)">
                                                    <option value="" :selected="!String(fila.tad || '').trim()">Selecciona una opción...</option>
                                                    <template x-for="(op, oi) in opciones(tads, fila.tad)" :key="'tad-' + oi">
                                                        <option :value="op" :selected="op === String(fila.tad)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.tad" :data-required-detail="fila.id_detalle" data-required-field="tad"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">TAD:</td>
                                        <td colspan="2" x-text="fila.tad" :data-required-detail="fila.id_detalle" data-required-field="tad"></td>
                                    </tr>

                                    <!-- Unidad -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Unidad:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center" data-select2-inline="1" data-opcion="4" :data-detalle="fila.id_detalle" :data-required-detail="fila.id_detalle" data-required-field="unidad">
                                                    <option value="" :selected="!String(fila.unidad || '').trim()"></option>
                                                    <template x-for="(op, oi) in opciones(unidades, fila.unidad)" :key="'uni-' + oi">
                                                        <option :value="op" :selected="op === String(fila.unidad)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.unidad" :data-required-detail="fila.id_detalle" data-required-field="unidad"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Unidad:</td>
                                        <td colspan="2" x-text="fila.unidad" :data-required-detail="fila.id_detalle" data-required-field="unidad"></td>
                                    </tr>

                                    <!-- Chofer -->
                                    <tr class="align-middle">
                                        <td class="text-center fw-semibold">Chofer:</td>
                                        <td class="p-0" colspan="2">
                                            <template x-if="inlineEditable">
                                                <select class="form-select border-0 rounded-0 text-center" data-select2-inline="1" data-opcion="5" :data-detalle="fila.id_detalle" :data-required-detail="fila.id_detalle" data-required-field="chofer">
                                                    <option value="" :selected="!String(fila.chofer || '').trim()"></option>
                                                    <template x-for="(op, oi) in opciones(choferes, fila.chofer)" :key="'chf-' + oi">
                                                        <option :value="op" :selected="op === String(fila.chofer)" x-text="op"></option>
                                                    </template>
                                                </select>
                                            </template>
                                            <template x-if="!inlineEditable">
                                                <div class="ps-2" x-text="fila.chofer" :data-required-detail="fila.id_detalle" data-required-field="chofer"></div>
                                            </template>
                                        </td>
                                        <td class="text-center fw-semibold">Chofer:</td>
                                        <td colspan="2" x-text="fila.chofer" :data-required-detail="fila.id_detalle" data-required-field="chofer"></td>
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
                                        <i class="ti ti-trash "></i> 
                                        Eliminar
                                    </button>
                                </template>
                                <template x-if="!puedeEliminarDetalle">
                                    <button type="button" class="btn bg-danger text-white w-100 opacity-50" style="cursor: not-allowed;" disabled
                                            title="No se puede eliminar este registro">
                                        <i class="ti ti-trash "></i> 
                                        Eliminar
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

<div class="row mt-3" x-show="puedeFirmarElaborador" x-cloak>
    <div class="col-xl-6 col-lg-6 col-md-12 col-sm-12 mb-3">
        <div class="card border-0 bg-white h-100">
            <div class="card-header text-bg-primary py-3 border-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">
                        <i class="ti ti-signature fs-6"></i>
                    </div>
                    <div class="ms-3"><h5 class="mb-0 text-white">FIRMA DE QUIEN ELABORA</h5></div>
                    </div>
                </div>
            </div>
            <div class="card-body p-3 pb-0">
                <div id="pivoteo-signature-pad" class="signature-pad-wrapper" :style="firmaInvalida ? 'border:2px dashed #dc3545; border-radius:6px; cursor:crosshair;' : 'border:2px dashed #adb5bd; border-radius:6px; cursor:crosshair;'">
                    <div class="signature-pad--body">
                        <canvas id="pivoteo-signature-canvas" style="width:100%; height:250px; display:block; touch-action:none; user-select:none;"></canvas>
                    </div>
                </div>
            </div>
            <button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0" style="border-bottom-left-radius:6px; border-bottom-right-radius:6px;" @click="limpiarFirmaElaborador()">
                <i class="ti ti-eraser me-1"></i> Limpiar firma
            </button>
        </div>
    </div>
</div>

    <!-- Modal Agregar Línea -->
    <div class="modal fade" id="modalAgregar" tabindex="-1" x-ref="modalAgregar" data-bs-backdrop="static" data-bs-keyboard="false">
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
                            <select class="form-select" x-model="formAgregar.producto" :class="{ 'is-invalid': erroresAgregar.producto }" @change="erroresAgregar.producto = false">
                                <option value="">Selecciona una opción...</option>
                                <template x-for="(op, oi) in productos" :key="'ap-' + oi">
                                    <option :value="op" x-text="op"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">* Tanque:</label>
                            <select class="form-select" x-model="formAgregar.tanque" :class="{ 'is-invalid': erroresAgregar.tanque }" @change="erroresAgregar.tanque = false">
                                <option value="">Selecciona una opción...</option>
                                <template x-for="(op, oi) in tanques" :key="'at-' + oi">
                                    <option :value="op" x-text="op"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">* Litros:</label>
                            <input type="text" inputmode="decimal" class="form-control" x-model="formAgregar.litros"
                                   :class="{ 'is-invalid': erroresAgregar.litros }"
                                   @input="formatearNumeroInput($event, 'formAgregar', 'litros'); erroresAgregar.litros = false">
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">* TAD:</label>
                            <select class="form-select" x-model="formAgregar.tad" :class="{ 'is-invalid': erroresAgregar.tad }" @change="erroresAgregar.tad = false">
                                <option value="">Selecciona una opción...</option>
                                <template x-for="(op, oi) in tads" :key="'atd-' + oi">
                                    <option :value="op" x-text="op"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label mb-1">* Unidad:</label>
                            <div class="select2-modal-field is-select2-pending" x-ref="wrapUnidadAgregar" :class="{ 'is-invalid': erroresAgregar.unidad }">
                                <select class="form-select bg-transparent" x-ref="selUnidadAgregar" @change="erroresAgregar.unidad = false">
                                    <option value="">Selecciona una opción...</option>
                                    <template x-for="(op, oi) in unidades" :key="'au-' + oi">
                                        <option :value="op" x-text="op"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label mb-1">* Chofer:</label>
                            <div class="select2-modal-field is-select2-pending" x-ref="wrapChoferAgregar" :class="{ 'is-invalid': erroresAgregar.chofer }">
                                <select class="form-select bg-transparent" x-ref="selChoferAgregar" @change="erroresAgregar.chofer = false">
                                    <option value="">Selecciona una opción...</option>
                                    <template x-for="(op, oi) in choferes" :key="'ac-' + oi">
                                        <option :value="op" x-text="op"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <div class="row g-0 ms-auto w-100 justify-content-end">

                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                      <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    </div>

                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button" class="btn btn-success" @click="guardarAgregar()" :disabled="guardando">
                        <template x-if="!guardando"><i class="ti ti-check"></i></template>
                        <template x-if="guardando">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                        </template>
                        Guardar
                    </button>
                    </div>
                  </div>

                    
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Seleccionar Estación -->
    <div class="modal fade" id="modalEstacion" tabindex="-1" x-ref="modalEstacion" 
data-bs-backdrop="static"  data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <h5 class="modal-title text-white"><i class="ti ti-edit me-2"></i>Editar <span x-text="'Pivoteo (#' + id + ')'"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-12" :class="{ 'opacity-50 pe-none': !!estacionForm.estacionOtro || !!estacionForm.destinoOtro }">
                            <label class="form-label">* Estación:</label>
                            <div class="select2-modal-field is-select2-pending" x-ref="wrapEstacion">
                                <select class="form-select" x-ref="selEstacion" x-model="estacionForm.estacion"
                                        :class="{ 'is-invalid': erroresEstacion.estacion }"
                                        @change="estacionForm.estacionOtro = ''; estacionForm.destinoOtro = ''; erroresEstacion = {}">
                                    <option value="">Selecciona una opción...</option>
                                    <template x-for="(op, oi) in estaciones" :key="'es-' + oi">
                                        <option :value="op" x-text="op"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">

                        <h5 class="text-primary mb-3 mt-3">Otra Estación</h5>

                            <div :class="{ 'opacity-50 pe-none': !!estacionForm.estacion }">


                            <label class="form-label mb-1">* Nombre:</label>
                                <input type="text" class="form-control mb-3" x-model="estacionForm.estacionOtro"
                                       :class="{ 'is-invalid': erroresEstacion.nombre }"
                                       :disabled="!!estacionForm.estacion"
                                       @input="estacionForm.estacion = ''; erroresEstacion.nombre = false; erroresEstacion.estacion = false">

                                       <label class="form-label mb-1" x-text="Number(estacionForm.categoria) === 1 ? '* Destino:' : 'Destino (opcional):'"></label>
                                    <input type="number" min="0" step="1" class="form-control mb-3" x-model="estacionForm.destinoOtro"
                                       :class="{ 'is-invalid': erroresEstacion.destino }"
                                       :disabled="!!estacionForm.estacion"
                                       @input="estacionForm.estacion = ''; erroresEstacion.destino = false; erroresEstacion.estacion = false">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">

    <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                      <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button> 
                    </div>

                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button" class="btn btn-success" @click="guardarEstacion()" :disabled="guardando">
                        <template x-if="!guardando"><i class="ti ti-check"></i></template>
                        <template x-if="guardando">
                            <span class="spinner-border spinner-border-sm me-1"></span>
                        </template>
                        Guardar
                    </button>
                    </div>
                  </div>
                    
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Envío por correo -->
    <div class="modal fade" id="modalGmail" tabindex="-1" 
data-bs-backdrop="static"  data-bs-keyboard="false">
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
                            <input type="email" class="form-control" x-model="gmailForm.correo" :class="{ 'is-invalid': erroresCorreo.correo }" @input="erroresCorreo.correo = false">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Asunto:</label>
                            <input type="text" class="form-control" x-model="gmailForm.asunto" :class="{ 'is-invalid': erroresCorreo.asunto }" @input="erroresCorreo.asunto = false">
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
                    <div class="row g-0 ms-auto w-100 justify-content-end">
                    <div class="col-12 col-md-auto d-grid mb-1 mb-md-0">
                      <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">
                        <i class="ti ti-x"></i> Cancelar
                    </button>
                    </div>

                    <div class="col-12 col-md-auto d-grid ms-0 ms-md-2">
                      <button type="button" class="btn btn-success" @click="guardarGmail()" :disabled="guardando">
                        <template x-if="!guardando"><i class="ti ti-send "></i></template>
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
    </div>

</div>

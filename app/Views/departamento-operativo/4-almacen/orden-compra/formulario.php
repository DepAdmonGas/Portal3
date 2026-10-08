<div class="mt-3 pb-4" x-data="{ ...actions(), ...ordenCompraForm(<?= htmlspecialchars(json_encode($detalle), ENT_QUOTES, 'UTF-8') ?>) }">

<div class="row g-3">

<?php if ($permisos['es_direccion_op'] && $detalle['estatus'] === 0): ?>
<div class="col-12" x-show="form.proveedores && form.proveedores.length > 2" x-cloak>
<button type="button" class="btn btn-success float-end" @click="finalizarOrden()" :disabled="guardandoFirma">
<span x-show="!guardandoFirma"><i class="ti ti-check me-1"></i> Finalizar</span>
<span x-show="guardandoFirma" style="display: none;"><span class="spinner-border spinner-border-sm me-1"></span> Procesando...</span>
</button>
</div>
<?php endif; ?>

<!---------- INFORMACION GENERAL ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary">
<h5 class="mb-0 text-white d-flex align-items-center">
<i class="ti ti-info-circle me-2"></i> INFORMACIÓN GENERAL
</h5>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered mb-0 align-middle">
<tr>
<td colspan="3" class="text-center align-middle">Dep. Almacén</td>
<td rowspan="3" class="text-center align-middle">
<h5 class="mb-0">ORDEN DE COMPRA</h5>
</td>
<td class="text-center align-middle">Cargo:</td>
<td class="p-0">
<input type="text" class="form-control border-0 text-center align-middle" x-model="form.cargo" @change="editarOC(1, form.cargo)">
</td>
</tr>
<tr>
<td colspan="3" class="text-center align-middle">Ref. Operativa</td>
<td class="text-center align-middle">Fecha:</td>
<td class="p-0">
<input type="date" class="form-control border-0 text-center align-middle" x-model="form.fecha" @change="editarOC(2, form.fecha)">
</td>
</tr>
<tr>
<td class="text-center align-middle">Refacturación:</td>
<td class="p-0" colspan="2" >
<div class="input-group input-group-sm">
<input type="number" step="0.01" class="form-control border-0 text-center align-middle" x-model="form.porcentaje_total" @change="editarOC(3, form.porcentaje_total)">
<span class="input-group-text border-0 bg-transparent fw-bold">%</span>
</div>
</td>
<td class="text-center align-middle">No. de control:</td>
<td class="text-center align-middle" x-text="form.no_control"></td>
</tr>
</table>
</div>
</div>
</div>
</div>

<!---------- DATOS DE LA ESTACIÓN ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white d-flex align-items-center">
<i class="ti ti-gas-station me-2"></i> DATOS DE LA ESTACIÓN
</h5>
<button type="button" class="btn btn-success text-white" @click="abrirModalEstacion()">
<i class="ti ti-pencil me-1"></i> Asignar Estación
</button>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered table-striped mb-0 w-100">
<tr>
<th class="text-start align-middle" width="200px">Razón Social:</th>
<td class="fw-semibold" x-text="form.estacion.razon_social"></td>
</tr>
<tr>
<th class="text-start align-middle">RFC:</th>
<td x-text="form.estacion.rfc"></td>
</tr>
<tr>
<th class="text-start align-middle">Dirección:</th>
<td x-text="form.estacion.direccion"></td>
</tr>
</table>
</div>
</div>
</div>
</div>

<!---------- DATOS DEL PROVEEDOR ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary d-flex align-items-md-center justify-content-between flex-column flex-md-row gap-3">
<div class="d-flex align-items-center gap-3">
<i class="ti ti-truck fs-6 text-white"></i>
<div class="d-flex flex-column">
<h5 class="mb-1 text-white fw-bold">DATOS DEL PROVEEDOR</h5>
<div class="text-white-50 small" x-show="form.proveedores && form.proveedores.length <= 2">
Debe registrar <b>más de 2 proveedores</b> para habilitar el cuadro comparativo y refacturación (Actualmente registrados: <span class="fw-bold text-white" x-text="form.proveedores ? form.proveedores.length : 0"></span>).
</div>
</div>
</div>
<button type="button" class="btn btn-success text-white fw-semibold align-self-start align-self-md-center" @click="abrirModalProveedor()">
<i class="ti ti-plus me-1"></i> Nuevo
</button>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 align-middle">
<thead>
<tr>
<th class="text-center align-middle">Razón Social</th>
<th class="text-center align-middle">Dirección</th>
<th class="text-center align-middle">Contacto</th>
<th class="text-center align-middle">Email</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-pencil fs-6 text-warning"></i></th>
<th class="text-center align-middle" width="48px"><i class="ti ti-trash fs-6 text-danger"></i></th>
</tr>
</thead>
<tbody>
<template x-for="prov in form.proveedores" :key="prov.id">
<tr>
<td class="text-center align-middle" x-text="prov.razon_social"></td>
<td class="text-center align-middle" x-text="prov.direccion"></td>
<td class="text-center align-middle" x-text="prov.contacto"></td>
<td class="text-center align-middle" x-text="prov.email"></td>
<td class="text-center align-middle">
<button type="button" class="btn p-1 text-warning" @click="abrirModalEditarProveedor(prov)" title="Editar">
<i class="ti ti-pencil fs-6"></i>
</button>
</td>
<td class="text-center">
<button type="button" class="btn p-1 text-danger" @click="eliminarProveedor(prov.id, prov.razon_social)" title="Eliminar">
<i class="ti ti-trash fs-6"></i>
</button>
</td>
</tr>
</template>
<template x-if="!form.proveedores || form.proveedores.length === 0">
<tr>
<td colspan="6" class="text-center text-primary">
No se encontró información.
</td>
</tr>
</template>
</tbody>
</table>
</div>
</div>
</div>
</div>

<div class="col-12" x-show="form.proveedores && form.proveedores.length > 2" x-cloak>
<div class="row g-3">

<!---------- CUADRO COMPARATIVO DE PROVEEDORES ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white d-flex align-items-center">
<i class="ti ti-table me-2"></i> CUADRO COMPARATIVO DE PROVEEDORES
</h5>
<button type="button" class="btn btn-success text-white fw-semibold" @click="abrirModalArticulo()">
<i class="ti ti-plus me-1"></i> Nuevo Artículo
</button>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered mb-0 align-middle w-100">
<thead>
<tr class="text-center align-middle">
<th class="text-center align-middle" width="120px">Mejor Oferta</th>
<th class="text-start align-middle">Concepto</th>
<th class="text-center align-middle" width="85px">Unidades</th>
<th class="text-center align-middle" width="100px">Estatus</th>
<th class="text-end align-middle" width="125px">Precio Unitario</th>
<th class="text-end align-middle" width="125px">Subtotal</th>
<th class="text-end align-middle" width="75px">IVA</th>
<th class="text-end align-middle" width="160px">Total (Subtotal * IVA)</th>
<th class="text-end align-middle" width="130px">Total</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-trash text-danger fs-6"></i></th>
</tr>
</thead>

<!-- TBODY INDIVIDUAL POR CADA PROVEEDOR -->
<template x-for="(prov, idx) in form.proveedores" :key="prov.id">
<tbody>
<!-- ENCABEZADO CELESTE DEL PROVEEDOR -->
<tr>
<td class="text-center align-middle table-primary">
<div class="form-check d-flex justify-content-center m-0">
<input class="form-check-input pointer" 
type="radio" 
name="radioMejorOferta" 
:checked="prov.check_p == 1" 
@change="seleccionarProveedor(prov.id)"
style="transform: scale(1.2); border: 1px solid #454646 !important; cursor: pointer;">
</div>
</td>
<td colspan="9" class="fw-semibold text-center align-middle table-primary">
Nombre del proveedor: <span x-text="prov.razon_social"></span>
</td>
</tr>

<!-- CASO A: TIENE ARTÍCULOS REGISTRADOS -->
<template x-if="prov.articulos && prov.articulos.length > 0">
<template x-for="(art, aIdx) in prov.articulos" :key="art.id">
<tr>
<td class="text-center fw-semibold text-muted" x-text="aIdx + 1"></td>
<td x-text="art.concepto"></td>
<td class="text-center" x-text="art.unidades"></td>
<td class="text-center" x-text="art.estatus_r"></td>
<td class="text-end" x-text="'$ ' + Number(art.precio_unitario || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
<td class="text-end" x-text="'$ ' + Number(art.subtotal || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
<td class="text-end">16%</td>
<td class="text-end" x-text="'$ ' + Number(art.iva_fila || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
<td class="text-end fw-semibold" x-text="'$ ' + Number(art.total_fila || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
<td class="text-center">
<button type="button" class="btn btn-sm p-1 text-danger" @click="eliminarArticulo(art.id, art.concepto)" title="Eliminar Artículo">
    <i class="ti ti-trash fs-6"></i>
</button>
</td>
</tr>
</template>
</template>

<!-- CASO B: NO TIENE ARTÍCULOS REGISTRADOS (SOLO ESTA FILA) -->
<template x-if="!prov.articulos || prov.articulos.length === 0">
<tr>
<td colspan="10" class="text-center text-primary">
No se encontró información para mostrar
</td>
</tr>
</template>

<!-- FILAS DE TOTALES: SOLO VISIBLES SI EL PROVEEDOR TIENE ARTÍCULOS -->
<template x-if="prov.articulos && prov.articulos.length > 0">
<tr class="table-light">
<td colspan="6" class="text-end align-middle">SUMA</td>
<td colspan="4" class="align-middle text-end fw-semibold" x-text="'$ ' + Number(prov.suma || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
</tr>
</template>

<template x-if="prov.articulos && prov.articulos.length > 0">
<tr class="table-light">
<td colspan="6" class="text-end align-middle">DESCUENTO</td>
<td colspan="4" class="p-0 align-middle fw-semibold">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 25px !important;" x-model.number="prov.descuento" @change="actualizarCostos(prov.id, 1, prov.descuento)">
</div>
</td>
</tr>
</template>

<template x-if="prov.articulos && prov.articulos.length > 0">
<tr class="table-light">
<td colspan="6" class="text-end align-middle">ENVIO</td>
<td colspan="4" class="p-0 align-middle fw-semibold">
<div class="position-relative">
<span class="position-absolute top-50 start-0 translate-middle-y ps-3">$</span>
<input type="number" min="0" step="any" class="border-0 p-3 text-end w-100 bg-transparent" style="padding-left: 25px !important;" x-model.number="prov.envio_cp" @change="actualizarCostos(prov.id, 2, prov.envio_cp)">
</div>
</td>
</tr>
</template>

<template x-if="prov.articulos && prov.articulos.length > 0">
<tr class="table-light">
<th colspan="6" class="text-end align-middle">SUBTOTAL</th>
<th colspan="4" class="align-middle text-end fw-semibold" x-text="'$ ' + Number(prov.subtotal_neto || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></th>
</tr>
</template>

<template x-if="prov.articulos && prov.articulos.length > 0">
<tr class="table-light">
<th colspan="6" class="text-end align-middle">IVA</th>
<th colspan="4" class="align-middle text-end fw-semibold" x-text="'$ ' + Number(prov.total_iva || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></th>
</tr>
</template>

<template x-if="prov.articulos && prov.articulos.length > 0">
<tr class="table-dark">
<th colspan="6" class="text-end align-middle text-white">TOTAL A PAGAR</th>
<th colspan="4" class="align-middle text-end fw-semibold text-white" x-text="'$ ' + Number(prov.total_pagar || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></th>
</tr>
</template>
</tbody>
</template>
</table>
</div>
</div>
</div>
</div>

<!---------- DATOS DE REFACTURACIÓN Y PRORRATEO ---------->
<div class="col-12">
<div class="card">
<div class="card-header text-bg-primary d-flex align-items-center justify-content-between">
<h5 class="mb-0 text-white d-flex align-items-center">
<i class="ti ti-calculator me-2"></i> DATOS DE REFACTURACIÓN Y PRORRATEO
</h5>
<button type="button" class="btn btn-success text-white fw-semibold" @click="abrirModalRefacturacion()">
<i class="ti ti-plus me-1"></i> Nueva Refacturación
</button>
</div>
<div class="card-body p-0">
<div class="table-responsive">
<table class="table table-bordered mb-0 align-middle">
<thead>
<tr>
<th class="text-center align-middle">Prorrateo (Estación)</th>
<th class="text-center align-middle">Descripción</th>
<th class="text-center align-middle">Cantidad</th>
<th class="text-center align-middle">Importe</th>
<th class="text-center align-middle">Porcentaje</th>
<th class="text-center align-middle">Estación</th>
<th class="text-center align-middle">Almacén</th>
<th class="text-center align-middle">Total</th>
<th class="text-center align-middle" width="48px"><i class="ti ti-trash text-danger fs-6"></i></th>
</tr>
</thead>
<tbody>
<template x-for="rf in form.refacturacion.filas" :key="rf.id">
<tr>
<td class="text-center align-middle" x-text="rf.estacion"></td>
<td class="text-center align-middle" x-text="rf.descripcion"></td>
<td class="text-center align-middle" x-text="rf.cantidad"></td>
<td class="text-center align-middle" x-text="'$ ' + Number(rf.importe || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
<td class="text-center align-middle" x-text="Number(rf.porcentaje || 0).toFixed(0) + ' %'"></td>
<td class="text-center align-middle" x-text="rf.cantidadES"></td>
<td class="text-center align-middle" x-text="rf.cantidadAl"></td>
<td class="text-center align-middle fw-semibold" x-text="'$ ' + Number(rf.total || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
<td class="text-center align-middle">
<button type="button" class="btn btn-sm p-1 text-danger" @click="eliminarRefacturacion(rf.id, rf.descripcion)" title="Eliminar Refacturación">
    <i class="ti ti-trash fs-6"></i>
</button>
</td>
</tr>
</template>
<template x-if="!form.refacturacion.filas || form.refacturacion.filas.length === 0">
<tr><td colspan="9" class="text-center text-primary">No se encontró información</td></tr>
</template>
</tbody>
<tfoot x-show="form.refacturacion.filas && form.refacturacion.filas.length > 0">
<tr class="table-light">
<th colspan="5" class="text-end align-middle">SUMA</th>
<th colspan="4" class="text-end align-middle fw-semibold" x-text="'$ ' + Number(form.refacturacion.suma || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></th>
</tr>
<tr class="table-light">
<th colspan="5" class="text-end align-middle">DESCUENTO</th>
<th colspan="4" class="text-end align-middle fw-semibold" x-text="'$ ' + Number(form.refacturacion.descuento || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></th>
</tr>
<tr class="table-light">
<th colspan="5" class="text-end align-middle">ENVIO</th>
<th colspan="4" class="text-end align-middle fw-semibold" x-text="'$ ' + Number(form.refacturacion.envio || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></th>
</tr>
<tr class="table-light">
<th colspan="5" class="text-end align-middle">SUBTOTAL</th>
<th colspan="4" class="text-end align-middle fw-semibold" x-text="'$ ' + Number(form.refacturacion.subtotal_neto || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></th>
</tr>
<tr class="table-light">
<th colspan="5" class="text-end align-middle">IVA</th>
<th colspan="4" class="text-end align-middle fw-semibold" x-text="'$ ' + Number(form.refacturacion.iva || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></th>
</tr>
<tr class="table-dark">
<th colspan="5" class="text-end align-middle text-white">TOTAL</th>
<th colspan="4" class="text-end align-middle text-white" x-text="'$ ' + Number(form.refacturacion.total_pagar || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></th>
</tr>
</tfoot>
</table>
</div>
</div>
</div>
</div>

<!---------- FIRMA ---------->
<?php if ($permisos['es_direccion_op'] && $detalle['estatus'] === 0): ?>
<div class="col-md-6 d-flex">
<div class="card border-0 bg-white w-100 d-flex flex-column">

    <div class="card-header text-bg-primary py-3 border-0 flex-shrink-0">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:45px;height:45px;">
                    <i class="ti ti-signature fs-6"></i>
                </div>
                <div class="ms-3">
                    <h5 class="mb-0 text-white">FIRMA DE ELABORACIÓN</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="card-body p-3 flex-fill d-flex flex-column">
        <div id="signature-pad" class="signature-pad-wrapper flex-fill d-flex" style="border: 2px dashed #adb5bd; border-radius: 6px; cursor: crosshair;">
            <div class="signature-pad--body w-100 h-100 d-flex">
                <canvas 
                    id="canvas" 
                    style="
                        width: 100%; 
                        height: 100%; 
                        min-height: 200px; 
                        display: block; 
                        touch-action: none;
                    ">
                </canvas>
            </div>
        </div>
        <input type="hidden" name="firma_elaboro" id="firma_elaboro" value="">
    </div>

    <button 
        type="button" 
        class="btn bg-danger-subtle text-danger w-100 rounded-0 flex-shrink-0" 
        @click="limpiarFirma()">
        <i class="ti ti-eraser me-1"></i> Limpiar firma
    </button>

</div>
</div>
<?php endif; ?>

</div>
</div>

</div>

    <!-- MODAL SELECCIONAR ESTACIÓN -->
    <div class="modal fade" id="modalEstacion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"><i class="ti ti-gas-station me-2"></i> Asignar Estación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label fw-semibold">* Nombre de la estación:</label>
                    <select class="form-select" x-model="estacionSeleccionada">
                        <option value="">Selecciona una opción...</option>
                        <?php foreach ($estacionesCatalogo as $est): ?>
                            <option value="<?= $est->id ?>"><?= $est->localidad ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer">
                                        <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button>

                    <button type="button" class="btn btn-success" @click="guardarEstacion()" :disabled="!estacionSeleccionada"><i class="ti ti-check me-1"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL AGREGAR / EDITAR PROVEEDOR -->
    <div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary d-flex align-items-center">
                    <i :class="editandoProveedor ? 'ti ti-pencil' : 'ti ti-truck'" class="fs-6 text-white me-2"></i>
                    <h5 class="modal-title text-white mb-0" x-text="editandoProveedor ? 'Editar Proveedor' : 'Nuevo Proveedor'"></h5>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label mb-1">* Razón Social:</label>
                        <input type="text" class="form-control" x-model="formProv.RazonSocial" :style="errorsProv.RazonSocial ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsProv.RazonSocial = false">
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1">* Dirección:</label>
                        <textarea class="form-control" rows="2" x-model="formProv.Direccion" :style="errorsProv.Direccion ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsProv.Direccion = false"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label mb-1">* Contacto:</label>
                        <input type="text" class="form-control" x-model="formProv.Contacto" :style="errorsProv.Contacto ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsProv.Contacto = false">
                    </div>
                    <div>
                        <label class="form-label mb-1">* Email:</label>
                        <input type="email" class="form-control" x-model="formProv.Email" :style="errorsProv.Email ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsProv.Email = false">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button>
                    <button type="button" class="btn btn-success" @click="guardarProveedor()" :disabled="guardandoProveedor">
                        <template x-if="!guardandoProveedor"><i class="ti ti-check me-1"></i></template>
                        <template x-if="guardandoProveedor"><span class="spinner-border spinner-border-sm me-1"></span></template>
                        <span x-text="guardandoProveedor ? 'Guardando...' : (editandoProveedor ? 'Editar' : 'Guardar')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL AGREGAR ARTÍCULO -->
    <div class="modal fade" id="modalArticulo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"><i class="ti ti-table"></i> Nuevo Artículo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">* Proveedor:</label>
                        <select class="form-select" x-model="formArt.id_proveedor" :style="errorsArt.id_proveedor ? 'border: 2px solid #A52525 !important;' : ''" @change="errorsArt.id_proveedor = false">
                            <option value="">Selecciona un proveedor...</option>
                            <template x-for="p in form.proveedores" :key="p.id">
                                <option :value="p.id" x-text="p.razon_social"></option>
                            </template>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">* Concepto:</label>
                        <textarea class="form-control" rows="2" x-model="formArt.Concepto" :style="errorsArt.Concepto ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsArt.Concepto = false"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">* Unidades:</label>
                            <input type="number" step="any" class="form-control" x-model="formArt.Unidades" :style="errorsArt.Unidades ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsArt.Unidades = false">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Estatus:</label>
                            <select class="form-select" x-model="formArt.EstatusR">
                                <option value="Nuevo">Nuevo</option>
                                <option value="Usado">Usado</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-semibold">* Precio Unitario:</label>
                        <input type="number" step="0.01" class="form-control" x-model="formArt.PrecioUnitario" :style="errorsArt.PrecioUnitario ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsArt.PrecioUnitario = false">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i> Cancelar</button>
                    <button type="button" class="btn btn-success" @click="guardarArticulo()" :disabled="guardandoArticulo">
                        <span x-show="!guardandoArticulo"><i class="ti ti-check me-1"></i> Guardar</span>
                        <span x-show="guardandoArticulo"><span class="spinner-border spinner-border-sm me-1"></span> Guardando...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL AGREGAR REFACTURACIÓN -->
    <div class="modal fade" id="modalRefacturacion" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"><i class="ti ti-calculator"></i> Nueva Refacturación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">* Estación:</label>
                        <select class="form-select" x-model="formRef.Estacion" :style="errorsRef.Estacion ? 'border: 2px solid #A52525 !important;' : ''" @change="errorsRef.Estacion = false">
                            <option value="">Seleccione una estación...</option>
                            <?php foreach ($estacionesProrrateo as $esP): ?>
                                <option value="<?= $esP->id ?>"><?= $esP->nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">* Descripción:</label>
                        <textarea class="form-control" rows="2" x-model="formRef.Descripcion" :style="errorsRef.Descripcion ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsRef.Descripcion = false"></textarea>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">* Cantidad:</label>
                            <input type="number" step="any" class="form-control" x-model="formRef.Cantidad" :style="errorsRef.Cantidad ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsRef.Cantidad = false">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">* Importe:</label>
                            <input type="number" step="0.01" class="form-control" x-model="formRef.Importe" :style="errorsRef.Importe ? 'border: 2px solid #A52525 !important;' : ''" @input="errorsRef.Importe = false">
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Porcentaje:</label>
                            <input type="number" step="0.01" class="form-control" x-model="formRef.Porcentaje">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Estación:</label>
                            <input type="number" step="any" class="form-control" x-model="formRef.CantidadES">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Almacén:</label>
                            <input type="number" step="any" class="form-control" x-model="formRef.CantidadAl">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" @click="guardarRefacturacion()" :disabled="guardandoRefacturacion">
                        <span x-show="!guardandoRefacturacion"><i class="ti ti-check me-1"></i> Guardar</span>
                        <span x-show="guardandoRefacturacion"><span class="spinner-border spinner-border-sm me-1"></span> Guardando...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>
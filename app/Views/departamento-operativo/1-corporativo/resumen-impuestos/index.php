<?php if (!$idEstacion): ?>
<div class="row mt-4">
<div class="col-12">
<div class="alert alert-secondary border-0 text-center text-muted py-4">
Debes de seleccionar una estación del menú superior para poder visualizar el Resumen de Impuestos.
</div>
</div>
</div>
<?php else: ?>
<div id="resumen-impuestos-container"
data-id-estacion="<?= $idEstacion ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
style="display: none;"></div>

<div class="row pb-4" x-data="resumenImpuestosComponent()">
<div class="col-12">

<div x-show="cargando" class="text-center py-5">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Cargando...</span>
</div>
<p class="mt-2 text-muted">Cargando resumen de impuestos...</p>
</div>

<div x-show="!cargando" x-cloak>
<div class="d-flex justify-content-between align-items-center mt-3 mb-3">
<div></div>
<button type="button" class="btn btn-success" @click="abrirTotales()">
<i class="ti ti-hand-holding-dollar me-1"></i>Impuestos Totales
</button>
</div>

<div class="datatables">
<div class="table-responsive">
<table id="tabla-resumen-impuestos" class="table table-striped table-bordered mb-0 text-nowrap align-middle">

<tbody>
</tbody>
</table>
</div>
</div>

</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<div class="modal-header">
<h4 class="modal-title" x-text="'Detalle (' + detalleFecha + ')'"></h4>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="detalleData = null"></button>
</div>
<div class="modal-body">
<template x-if="detalleCargando">
<div class="text-center py-3">
<div class="spinner-border text-primary" style="width: 2rem; height: 2rem;" role="status"></div>
<p class="mt-2 text-muted small">Cargando detalle...</p>
</div>
</template>
<template x-if="!detalleCargando && detalleData">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Producto</th>
<th class="text-center align-middle">Precio al Público</th>
<th class="text-center align-middle">IEPS</th>
<th class="text-center align-middle">Precio Sin IVA</th>
<th class="text-center align-middle">IVA</th>
<th class="text-center align-middle">Volumen Vendido</th>
<th class="text-center align-middle">Importe Sin IVA</th>
<th class="text-center align-middle">IVA</th>
<th class="text-center align-middle">IEPS</th>
<th class="text-center align-middle">Total</th>
</tr>
</thead>
<tbody>
<template x-if="detalleData.items.length === 0">
<tr>
<th colspan="10" class="text-center text-secondary p-3">
<small>No se encontró información para mostrar</small>
</th>
</tr>
</template>
<template x-for="item in detalleData.items" :key="item.producto">
<tr>
<th class="align-middle text-center" x-text="item.producto"></th>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.precio_litro)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.ieps)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.precio_sin_iva)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.iva_unidad)"></td>
<td class="align-middle text-end" x-text="formatNum(item.volumen_vendido)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.importe_sin_iva)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.iva_total)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.ieps_total)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.total)"></td>
</tr>
</template>
<tr x-show="detalleData.items.length > 0">
<th colspan="5" class="align-middle text-end">Subtotal Combustibles</th>
<td class="align-middle text-end"><strong x-text="formatNum(detalleData.subtotal_combustibles.volumen)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(detalleData.subtotal_combustibles.importe_sin_iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(detalleData.subtotal_combustibles.iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(detalleData.subtotal_combustibles.ieps)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(detalleData.subtotal_combustibles.total)"></strong></td>
</tr>
<tr x-show="detalleData.items.length > 0">
<th colspan="6" class="align-middle text-end">Aceites</th>
<td class="align-middle text-end" x-text="'$ ' + formatNum(detalleData.aceites_sin_iva)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(detalleData.aceites_iva)"></td>
<td colspan="2" class="align-middle text-end" x-text="'$ ' + formatNum(detalleData.aceites_total)"></td>
</tr>
<tr x-show="detalleData.items.length > 0">
<th colspan="6" class="align-middle text-end">Total del día</th>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(detalleData.total_dia.importe_sin_iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(detalleData.total_dia.iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(detalleData.total_dia.ieps)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(detalleData.total_dia.total)"></strong></td>
</tr>
</tbody>
</table>
</div>
</template>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal" @click="detalleData = null">Cerrar</button>
</div>
</div>
</div>
</div>

<div class="modal fade" id="modalTotales" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
<div class="modal-dialog modal-xl">
<div class="modal-content">
<div class="modal-header">
<h4 class="modal-title">Resumen de Impuestos Totales</h4>
<button type="button" class="btn-close" data-bs-dismiss="modal" @click="totalesData = null"></button>
</div>
<div class="modal-body">
<template x-if="totalesCargando">
<div class="text-center py-3">
<div class="spinner-border text-primary" style="width: 2rem; height: 2rem;" role="status"></div>
<p class="mt-2 text-muted small">Cargando totales...</p>
</div>
</template>
<template x-if="!totalesCargando && totalesData">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th class="text-center align-middle">Producto</th>
<th class="text-center align-middle">Precio al Público</th>
<th class="text-center align-middle">IEPS</th>
<th class="text-center align-middle">Precio Sin IVA</th>
<th class="text-center align-middle">IVA</th>
<th class="text-center align-middle">Volumen Vendido</th>
<th class="text-center align-middle">Importe Sin IVA</th>
<th class="text-center align-middle">IVA</th>
<th class="text-center align-middle">IEPS</th>
<th class="text-center align-middle">Total</th>
</tr>
</thead>
<tbody>
<template x-if="totalesData.items.length === 0">
<tr>
<th colspan="10" class="text-center text-secondary p-3">
<small>No se encontró información para mostrar</small>
</th>
</tr>
</template>
<template x-for="item in totalesData.items" :key="item.producto">
<tr>
<th class="align-middle text-center" x-text="item.producto"></th>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.precio_publico)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.ieps)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.precio_sin_iva)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.iva_unidad)"></td>
<td class="align-middle text-end" x-text="formatNum(item.volumen_vendido)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.importe_sin_iva)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.iva_total)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.ieps_total)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(item.total)"></td>
</tr>
</template>
<tr x-show="totalesData.items.length > 0">
<th colspan="5" class="align-middle text-end">Subtotal Combustibles</th>
<td class="align-middle text-end"><strong x-text="formatNum(totalesData.subtotal.volumen)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totalesData.subtotal.importe_sin_iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totalesData.subtotal.iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totalesData.subtotal.ieps)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totalesData.subtotal.total)"></strong></td>
</tr>
<tr x-show="totalesData.items.length > 0">
<th colspan="6" class="align-middle text-end">Aceites</th>
<td class="align-middle text-end" x-text="'$ ' + formatNum(totalesData.aceites.sin_iva)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(totalesData.aceites.iva)"></td>
<td colspan="2" class="align-middle text-end" x-text="'$ ' + formatNum(totalesData.aceites.total)"></td>
</tr>
<tr x-show="totalesData.items.length > 0">
<th colspan="6" class="align-middle text-end">Total del mes</th>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totalesData.total.importe_sin_iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totalesData.total.iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totalesData.total.ieps)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totalesData.total.total)"></strong></td>
</tr>
</tbody>
</table>
</div>
</template>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-danger" data-bs-dismiss="modal" @click="totalesData = null">Cerrar</button>
</div>
</div>
</div>
</div>

</div>
</div>
<?php endif; ?>

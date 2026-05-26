<div id="container" class="mt-4 mb-4"
data-id-dia="<?= $idDia ?>"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-estado="<?= $estado ?>"
data-multiestacion="<?= $multiestacion ? 'true' : 'false' ?>"
x-data="impuestosComponent()">

<template x-if="loading">
<div class="text-center py-5">
<div class="spinner-border text-primary" role="status"></div>
<p class="mt-2 text-muted">Cargando impuestos...</p>
</div>
</template>

<template x-if="!loading">
<div class="row">
<div class="col-12">
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
<th class="text-center align-middle">Iimporte Sin IVA</th>
<th class="text-center align-middle">IVA</th>
<th class="text-center align-middle">IEPS</th>
<th class="text-center align-middle">Total</th>
</tr>
</thead>
<tbody>
<template x-if="items.length === 0">
<tr>
<th colspan="10" class="text-center text-secondary p-3">
<small>No se encontró información para mostrar</small>
</th>
</tr>
</template>
<template x-for="item in items" :key="item.producto">
<tr>
<th class="align-middle" x-text="item.producto"></th>
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
<tr x-show="items.length > 0">
<th colspan="5" class="align-middle text-end">Subtotal Combustibles</th>
<td class="align-middle text-end"><strong x-text="formatNum(subtotales.volumen)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(subtotales.importe_sin_iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(subtotales.iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(subtotales.ieps)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(subtotales.total)"></strong></td>
</tr>
<tr x-show="items.length > 0">
<th colspan="6" class="align-middle text-end">Aceites</th>
<td class="align-middle text-end" x-text="'$ ' + formatNum(aceitesSinIva)"></td>
<td class="align-middle text-end" x-text="'$ ' + formatNum(aceitesIva)"></td>
<td colspan="2" class="align-middle text-end" x-text="'$ ' + formatNum(aceitesTotal)"></td>
</tr>
<tr x-show="items.length > 0">
<th colspan="6" class="align-middle text-end">Total del día</th>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totales.importe_sin_iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totales.iva)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totales.ieps)"></strong></td>
<td class="align-middle text-end"><strong x-text="'$ ' + formatNum(totales.total)"></strong></td>
</tr>
</tbody>
</table>
</div>

</div>
</div>
</template>

</div>

<div id="container" class="mt-4 pb-4"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>">

<div id="despacho-ventas-empty-message" class="alert alert-secondary border-0 text-center text-muted py-4 mt-4"<?= $idEstacion ? ' style="display:none"' : '' ?>>
Debes de seleccionar una estación del menú superior para poder visualizar la información de Despacho vs Ventas.
</div>

<div id="despacho-ventas-content" class="mt-2"<?= $idEstacion ? '' : ' style="display:none"' ?>
x-data="despachoVentasComponent()"
data-id-estacion="<?= $idEstacion ?>">

<template x-if="loading">
<div class="text-center py-5">
<div class="spinner-border text-primary" role="status"></div>
<p class="mt-2 text-muted">Cargando información...</p>
</div>
</template>

<template x-if="!loading && error">
<div class="alert alert-danger">
<i class="ti ti-alert-circle me-1"></i> <span x-text="error"></span>
</div>
</template>

<template x-if="!loading && !error && dias.length > 0">
<div class="table-responsive">
<table class="table table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th rowspan="2" class="text-center align-middle" style="width: 90px;"></th>
<th rowspan="2" class="text-center align-middle">Fecha</th>
<template x-for="(prod, i) in productos" :key="prod">
<th :colspan="2" class="text-center text-white" :style="'background: ' + coloresProducto(i) + ';'" x-text="prod"></th>
</template>
<th rowspan="2" class="text-center align-middle">Total Litros</th>
<th rowspan="2" class="text-center align-middle">Total Pesos</th>
</tr>
<tr>
<template x-for="sh in subHeaders" :key="sh.label + sh.style">
<th class="text-center text-white" :style="'background: ' + sh.style + ';'" x-text="sh.label"></th>
</template>
</tr>
</thead>
<template x-for="(dia, idx) in dias" :key="'tbody-'+idx">
<tbody>
<template x-if="idx > 0">
<tr><td colspan="99" class="bg-white border-0"></td></tr>
</template>
<tr>
<th class="text-center table-info">Ventas</th>
<td rowspan="3" class="text-center align-middle fw-semibold" x-text="dia.fecha_formateada"></td>
<template x-for="pc in prodCols" :key="pc.key">
<td class="table-info text-end" x-text="formatValue(pc, dia.ventas[pc.key])"></td>
</template>
<td class="fw-semibold table-info text-end" x-text="formatNumber(dia.ventas.lt)"></td>
<td class="fw-semibold table-info text-end" x-text="formatMoney(dia.ventas.pt)"></td>
</tr>
<tr>
<th class="text-center">Despacho</th>
<template x-for="pc in prodCols" :key="'dp-'+pc.key">
<td class="p-0">
<input type="text" inputmode="decimal" class="border-0 p-3 text-end w-100 bg-transparent"
x-init="$el.value = formatInput(dia.despacho[pc.key])"
@focus="$el.value = (parseFloat(dia.despacho[pc.key]) || 0).toString(); $el.select()"
@blur="actualizarDespacho(dia, pc.col, $el)">
</td>
</template>
<td class="fw-semibold text-end" x-text="formatNumber(dia.despacho.lt)"></td>
<td class="fw-semibold text-end" x-text="formatMoney(dia.despacho.pt)"></td>
</tr>
<tr>
<th class="text-center table-success">Diferencia</th>
<template x-for="pc in prodCols" :key="pc.key">
<td :class="'text-end table-success ' + esNegativo(dia.diff[pc.key])" x-text="formatValue(pc, dia.diff[pc.key])"></td>
</template>
<td :class="'fw-semibold table-success text-end ' + esNegativo(dia.diff.lt)" x-text="formatNumber(dia.diff.lt)"></td>
<td :class="'fw-semibold table-success text-end ' + esNegativo(dia.diff.pt)" x-text="formatMoney(dia.diff.pt)"></td>
</tr>
</tbody>
</template>
<tfoot>
<tr><td colspan="99" class="bg-white border-0"></td></tr>
<template x-for="(row, idx) in totalRows" :key="'tf'+idx">
<tr>
<th class="text-center" :class="row.thClass" x-text="row.label"></th>
<template x-if="row.showDate">
<td :rowspan="3" class="text-center align-middle fw-semibold" x-text="row.fecha"></td>
</template>
<template x-for="pc in prodCols" :key="'ft-'+pc.key">
<td :class="'text-end ' + (row.type === 'diff' ? esNegativo(row.data[pc.key]) : '')" x-text="formatValue(pc, row.data[pc.key])"></td>
</template>
<td :class="'fw-semibold text-end ' + (row.type === 'diff'  ? esNegativo(row.data.lt) : '')" x-text="formatNumber(row.data.lt)"></td>
<td :class="'fw-semibold text-end ' + (row.type === 'diff' ? esNegativo(row.data.pt) : '')" x-text="formatMoney(row.data.pt)"></td>
</tr>
</template>
</tfoot>
</table>
</div>
</template>

<template x-if="!loading && !error && dias.length === 0">
<div class="alert alert-info border-0 text-center text-muted py-4 mt-4">
No se encontraron días registrados para el periodo seleccionado.
</div>
</template>

</div>
</div>

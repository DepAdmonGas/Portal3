<?php if (!$idEstacion): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes de seleccionar una estación del menú superior para poder visualizar la información del Concentrado de Ventas.
</div>
<?php else: ?>
<?php $numProd = count($productosList); ?>
<div id="container" class="mt-4 mb-4"
data-id-year="<?= $idYear ?>"
data-id-mes="<?= $idMes ?>"
data-num-productos="<?= $numProd ?>"
data-puede-descargar="<?= ($puedeDescargar ?? false) ? 'true' : 'false' ?>"
data-module-station-key="corte-diario"
x-data="concentradoVentasComponent()">

<template x-if="loading">
<div class="text-center py-5">
<div class="spinner-border text-primary" role="status">
<span class="visually-hidden">Cargando...</span>
</div>
<p class="mt-2 text-muted">Cargando concentrado de ventas...</p>
</div>
</template>

<template x-if="!loading && error">
<div class="alert alert-danger">
<i class="ti ti-alert-circle me-1"></i> <span x-text="error"></span>
</div>
</template>

<template x-if="!loading && !error">

<div class="row">
<div class="col-12 mb-4">

    <div class="table-responsive overflow-x-auto overflow-y-hidden">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">

<thead>
<tr>
<th class="text-center align-middle" rowspan="2">FECHA</th>

<?php foreach ($productosList as $i => $prod): ?>
<th class="text-center align-middle fw-semibold text-white" style="background-color: <?= $colores[$i] ?>;" colspan="2">
<p class="text-white"><?= $prod ?></p>
</th>
<?php endforeach; ?>
</tr>

<tr>
<?php foreach ($colores as $c): ?>
<td class="text-start fw-semibold text-white" style="background-color: <?= $c ?>;">
<p class="text-white">Litros</p>
</td>

<td class="text-end fw-semibold text-white" style="background-color: <?= $c ?>;">
<p class="text-white">Pesos</p>
</td>
<?php endforeach; ?>
</tr>
</thead>

<tbody>
<template x-for="(row, idx) in daily" :key="idx">
<tr>
<th class="text-start fw-normal" x-text="row.fecha"></th>

<?php foreach ($productosList as $prod): ?>
<td class="text-start"
x-text="formatear(getCelda(row, '<?= $prod ?>', 'TotalLitros'))">
</td>

<td class="text-end"
x-text="'$' + formatear(getCelda(row, '<?= $prod ?>', 'TotalPrecio'))">
</td>
<?php endforeach; ?>
</tr>
</template>

<template x-if="daily.length === 0">
<tr>
<td :colspan="<?= 1 + $numProd * 2 ?>"
class="text-center text-muted py-3">
No hay registros para este mes
</td>
</tr>
</template>
</tbody>

<tfoot>
<tr class="table-dark fw-semibold">
<td class="text-start">Total</td>

<?php foreach ($productosList as $prod): ?>
<td class="text-start"
x-text="formatear(totalProd('<?= $prod ?>', 'TotalLitros'))">
</td>

<td class="text-end"
x-text="'$' + formatear(totalProd('<?= $prod ?>', 'TotalPrecio'))">
</td>
<?php endforeach; ?>
</tr>
</tfoot>

</table>
</div>

</div>
</div>

</template>

</div>
<?php endif; ?>

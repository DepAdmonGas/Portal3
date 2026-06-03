<?php if (!$idEstacion): ?>
<div class="row mt-4 mb-5">
<div class="col-12">
<div class="alert alert-info text-center">
<i class="ti ti-info-circle fs-4"></i>
Debes de seleccionar una estación del menú superior para poder visualizar la información del Resumen Impuestos.
</div>
</div>
</div>
<?php else: ?>

<div class="col-12 mt-4 mb-4">
<div class="table-responsive">
<table class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr>
<th colspan="10" class="text-center align-middle">
<strong>Resumen de Impuestos</strong>
</th>
</tr>
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
<?php foreach ($items as $row): ?>
<tr>
<th class="text-center align-middle"><?= $row['producto'] ?></th>
<td class="text-center align-middle">$ <?= number_format($row['precio_litro'], 4) ?></td>
<td class="text-center align-middle"><?= number_format($row['ieps'], 4) ?></td>
<td class="text-center align-middle"><?= number_format($row['precio_sin_iva'], 4) ?></td>
<td class="text-end align-middle"><?= number_format($row['iva_unidad'], 4) ?></td>
<td class="text-end align-middle"><?= number_format($row['volumen_vendido'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($row['importe_sin_iva'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($row['iva_total'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($row['ieps_total'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($row['total'], 2) ?></td>
</tr>
<?php endforeach; ?>

<tr class="table-dark fw-semibold">
<td colspan="5" class="text-end align-middle">Subtotal Combustibles</td>
<td class="text-end align-middle"><?= number_format($combustibles['volumen'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($combustibles['importe_sin_iva'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($combustibles['iva'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($combustibles['ieps'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($combustibles['total'], 2) ?></td>
</tr>

<tr>
<th colspan="6" class="text-end align-middle">Aceites</th>
<td class="text-end align-middle"><?= number_format($aceites_sin_iva, 2) ?></td>
<td class="text-end align-middle"><?= number_format($aceites_iva, 2) ?></td>
<td></td>
<td class="text-end align-middle"><?= number_format($aceites_total, 2) ?></td>
</tr>

<tr class="table-dark fw-semibold">
<td colspan="6" class="text-end align-middle">Total del día</td>
<td class="text-end align-middle"><?= number_format($total_dia['importe_sin_iva'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($total_dia['iva'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($total_dia['ieps'], 2) ?></td>
<td class="text-end align-middle"><?= number_format($total_dia['total'], 2) ?></td>
</tr>
</tbody>
</table>
</div>
</div>

<div class="col-12">
<div class="table-responsive">
<table class="table table-bordered table-striped mb-0 text-nowrap align-middle">

<thead>
<tr>
<th colspan="15" class="text-center">
<strong>Resumen Monederos</strong>
</th>
</tr>
<tr >
<th class="text-center" colspan="9">Tarjetas</th>
<th class="text-center" colspan="6">Cartera de Clientes ATIO</th>
</tr>
<tr>
<th class="text-center" colspan="4">Tarjetas Bancarias</th>
<th class="text-center" colspan="5">Tarjetas (Otro)</th>
<th class="text-center" colspan="2">Crédito</th>
<th class="text-center" colspan="2">Débito</th>
<th class="text-center">Pagos</th>
<th class="text-center">Consumos</th>
</tr>
<tr>
<th class="text-center">BANCOMER</th>
<th class="text-center">AMEX</th>
<th class="text-center">INBURSA</th>
<th class="text-center">Total</th>
<th class="text-center">TICKETCARD</th>
<th class="text-center">G500 FLETT</th>
<th class="text-center">EFECTICARD</th>
<th class="text-center">SODEXO</th>
<th class="text-center">Total</th>
<th class="text-center">Pagos</th>
<th class="text-center">Consumos</th>
<th class="text-center">Pagos</th>
<th class="text-center">Consumos</th>
<th class="text-center">Total</th>
<th class="text-center">Total</th>
</tr>
</thead>

<tbody>
<tr class="table-dark ">
<td class="text-end align-middle">$<?= number_format($m['bancomer'], 2) ?></td>
<td class="text-end align-middle">$<?= number_format($m['amex'], 2) ?></td>
<td class="text-end align-middle">$<?= number_format($m['inburgas'], 2) ?></td>
<td class="text-end align-middle"><strong>$<?= number_format($m['total_tb'], 2) ?></strong></td>
<td class="text-end align-middle">$<?= number_format($m['ticketcard'], 2) ?></td>
<td class="text-end align-middle">$<?= number_format($m['g500fleet'], 2) ?></td>
<td class="text-end align-middle">$<?= number_format($m['efecticard'], 2) ?></td>
<td class="text-end align-middle">$<?= number_format($m['sodexo'], 2) ?></td>
<td class="text-end align-middle"><strong>$<?= number_format($m['total_otros'], 2) ?></strong></td>
<td class="text-end align-middle">$<?= number_format($m['pago_credito'], 2) ?></td>
<td class="text-end align-middle">$<?= number_format($m['consumo_credito'], 2) ?></td>
<td class="text-end align-middle">$<?= number_format($m['pago_debito'], 2) ?></td>
<td class="text-end align-middle">$<?= number_format($m['consumo_debito'], 2) ?></td>
<td class="text-end align-middle"><strong>$<?= number_format($m['total_pago'], 2) ?></strong></td>
<td class="text-end align-middle"><strong>$<?= number_format($m['total_consumo'], 2) ?></strong></td>
</tr>
</tbody>
</table>
</div>
</div>

<?php endif; ?>

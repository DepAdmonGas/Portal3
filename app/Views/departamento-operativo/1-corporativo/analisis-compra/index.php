<style>
#tabla-analisis-compra .col-notac {
width: 250px;
min-width: 250px;
}

#tabla-analisis-compra .col-status {
width: 230px;
min-width: 230px;
}

#tabla-analisis-compra .col-notac input {
width: 100%;
}

#tabla-analisis-compra .col-status select {
width: 100%;
}
</style>

<?php if (!$idEstacion): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes seleccionar una estación del menú superior para poder visualizar la información de Análisis de Compras.
</div>
<?php elseif ($multiestacion && $idEstacion === 8): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
Debes seleccionar una estación del menú superior para poder visualizar la información de Análisis de Compras.
</div>
<?php elseif (empty($rows)): ?>
<div class="alert alert-secondary border-0 text-center text-muted py-4 mt-4">
No se encontraron registros para el período seleccionado.
</div>
<?php else: ?>

<div id="container-analisis-compra" class="mb-4"
x-data="analisisCompra()">

<div class="row mt-2 mb-3 align-items-center">
    <!-- Texto de la izquierda -->
    <div class="col-12 col-md-auto">
        <h4 class="text-primary mb-0">Pick up G500 Network</h4>
    </div>
    
    <!-- Botón de la derecha -->
    <div class="col-12 col-md-auto ms-md-auto">
        <a href="/departamento-operativo/analisis-compra/<?= (int)$idYear ?>/<?= (int)$idMes ?>/excel"
           class="btn btn-success" title="Descargar el análisis de <?= htmlspecialchars($nombreEstacion, ENT_QUOTES, 'UTF-8') ?>">
            <i class="ti ti-file-spreadsheet me-1"></i> Descargar Excel
        </a>
    </div>
</div>

<div class="table-responsive pb-5" style="overflow-y: hidden; overflow-x: auto;">
<table id="tabla-analisis-compra" class="table table-striped table-bordered mb-0 text-nowrap align-middle">
<thead>
<tr> 
<th class="text-center">TAD</th>
<th class="text-center">Fecha</th>
<th class="text-center">No. Factura</th>
<th class="text-center">Litros factura</th>
<th class="text-center">Cuenta litros</th>
<th class="text-center">Merma cta litros</th>
<th class="text-center">Tolerancia .55%</th>
<th class="text-center">Producto</th>
<th class="text-center">Transporte</th>
<th class="text-center">Unidad</th>
<th class="text-center">Chofer</th>
<th class="text-center">Importe G500 Facturado</th>
<th class="text-center">Importe Transporte</th>
<th class="text-center">Precio Pickup</th>
<th class="text-center">Precio Pemex</th>
<th class="text-center">Diferencia</th>
<th class="text-center">Dif $ vs Pemex</th>
<th class="text-center">Importe merma total $</th>
<th class="text-center">Merma</th>
<th class="text-center">Importe Merma</th>
<th class="text-center col-notac">NOTA C</th>
<th class="text-center">Importe Nota</th>
<th class="text-center">Factura Transporte</th>
<th class="text-center">Monto factura</th>
<th class="text-center">Total a pagar transp.</th>
<th class="text-center col-status">Estado</th>
<th class="text-center" style="width:100px">PICKUP</th>
<th class="text-center" style="width:100px">PEMEX</th>
</tr>
</thead>

<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td class="text-center"><?= $r['tad'] ?></td>
<td><?= formatearFechaCorta($r['fecha']) ?></td>
<td class="text-center"><?= $r['no_factura'] ?></td>
<td class="text-end"><?= number_format($r['litros_facturados'], 2) ?></td>
<td class="text-end"><?= number_format($r['cuenta_litros'], 2) ?></td>
<td class="text-end"><?= number_format($r['merma_cuenta_litros'], 2) ?></td>
<td class="text-end"><?= number_format($r['tolerancia'], 2) ?></td>
<td class="text-center"><?= $r['producto'] ?></td>
<td class="text-center"><?= $r['transporte'] ?></td>
<td class="text-center"><?= $r['unidad'] ?></td>
<td class="text-center"><?= $r['chofer'] ?></td>
<td class="text-end">$ <?= number_format($r['importe_facturado'], 2) ?></td>
<td class="text-end">$ <?= number_format($r['importe_transporte'], 2) ?></td>
<td class="text-end">$ <?= number_format($r['precio_pickup'], 2) ?></td>
<td class="text-end">$ <?= number_format($r['precio_pemex'], 2) ?></td>
<td class="text-end">$ <?= number_format($r['diferencia'], 2) ?></td>
<td class="text-end">$ <?= number_format($r['diferencia_pemex'], 2) ?></td>
<td class="text-end">$ <?= number_format($r['importe_merma_total'], 2) ?></td>
<td class="text-end"><?= number_format($r['merma'], 2) ?></td>
<td class="text-end">$ <?= number_format($r['importe_merma'], 2) ?></td>

<td class="col-notac p-0">
<?php if ($puedeEditar): ?>
<input type="text" placeholder="Ingresa aqui tu nota..." class="form-control border-0 text-start p-3" value="<?= $r['notac'] ?>"
x-on:change="guardarNotac($event, '<?= $r['fecha'] ?>', '<?= $r['no_factura'] ?>')">
<?php else: ?>
<?= $r['notac'] ?>
<?php endif; ?>
</td>

<td class="text-end">$ <?= number_format($r['importe_nota'], 2) ?></td>
<td><?= $r['factura_transporte'] ?></td>
<td class="text-end">$ <?= number_format($r['monto_factura'], 2) ?></td>
<td class="text-end">$ <?= number_format($r['total_pagar_transporte'], 2) ?></td>

<td class="col-status p-0">
<?php if ($puedeEditar): ?>
<select class="form-select border-0 p-3" x-on:change="guardarStatus($event, '<?= $r['fecha'] ?>', '<?= $r['no_factura'] ?>')">
<option value="">Selecciona una opción...</option>
<option value="Pendiente"<?= $r['status'] === 'Pendiente' ? ' selected' : '' ?>>Pendiente</option>
<option value="Pagada"<?= $r['status'] === 'Pagada' ? ' selected' : '' ?>>Pagada</option>
</select>
<?php else: ?>
<?= $r['status'] ?>
<?php endif; ?>
</td>
<td class="text-end fw-semibold">$ <?= number_format($r['pickup'], 2) ?></td>
<td class="text-end fw-semibold">$ <?= number_format($r['pemex'], 2) ?></td>
</tr>
<?php endforeach; ?>
</tbody>

<tfoot class="table-dark">
<tr>
<th colspan="16" class="text-end text-white fw-semibold"></th>
<th class="text-end text-white fw-semibold">$ <?= number_format($totals['diferencia_pemex'], 2) ?></th>
<th colspan="2"></th>
<th class="text-end text-white fw-semibold">$ <?= number_format($totals['importe_merma'], 2) ?></th>
<th></th>
<th class="text-end text-white fw-semibold">$ <?= number_format($totals['importe_nota'], 2) ?></th>
<th colspan="3"></th>
<th></th>
<th class="text-end text-white fw-semibold">$ <?= number_format($totals['pickup'], 2) ?></th>
<th class="text-end text-white fw-semibold">$ <?= number_format($totals['pemex'], 2) ?></th>
</tr>
</tfoot>
</table>
</div>

</div>
<?php endif; ?>

<div class="col-12 mb-3">
    
<div class="card">

<div class="card-body">
<div class="row align-items-stretch g-3">

<!-- Columna de la Tabla / Datos -->
<div class="<?= (!empty($fila['archivo_existe']) && $fila['archivo_url'] !== '') ? 'col-lg-8' : 'col-lg-12' ?>">
<div class="table-responsive h-100">
<table class="table table-bordered align-middle text-center mb-0 h-100" style="width: 100%;">
<thead>
<tr class="text-center align-middle text-white">
<th class="text-white" style="background: #7668af;" colspan="4"><?= htmlspecialchars($fecha_tarjeta, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($fila['hora_display'], ENT_QUOTES, 'UTF-8') ?></th>
</tr>
</thead>
<tbody>
<tr class="text-center align-middle">
<td class="text-white" style="background: #7668af;" colspan="1">Tipo</td>
<td class="text-white" style="background: #7668af;" colspan="3"><?= htmlspecialchars($fila['embarque'], ENT_QUOTES, 'UTF-8') ?>
<label class="<?= $fila['ocultar_transporte'] ? 'd-none' : '' ?>">/ <?= htmlspecialchars($fila['transporte'], ENT_QUOTES, 'UTF-8') ?></label>
</td>
</tr>
<tr class="text-center align-middle">
<td class="text-white" style="background: #7668af;">Tanque</td>
<td class="text-white" style="background: #7668af;"><?= htmlspecialchars($fila['tanque'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="text-white" style="background: #7668af;">Producto</td>
<td class="text-white" style="background: #7668af;"><?= htmlspecialchars($fila['producto'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr class="text-center align-middle">
<td class="text-white" style="background: #7668af;">TAD</td>
<td class="text-white" style="background: #7668af;"><?= htmlspecialchars($fila['tad'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="text-white" style="background: #7668af;">Unidad</td>
<td class="text-white" style="background: #7668af;"><?= htmlspecialchars($fila['unidad'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr class="text-center align-middle">
<td class="text-white" style="background: #84b6f4;">Factura</td>
<td class="text-white" style="background: #84b6f4;">Tirilla de Descarga Neto</td>
<td class="text-white" style="background: #84b6f4;">Tirilla de Descarga Bruto</td>
<td class="text-white" style="background: #84b6f4;">Cuenta Litros a 20° C</td>
</tr>
<tr class="text-center align-middle">
<td class="fw-bold" rowspan="2"><?= htmlspecialchars($fila['litros'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="fw-bold"><?= htmlspecialchars($fila['descarga_neto'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="fw-bold"><?= htmlspecialchars($fila['descarga_bruto'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="fw-bold"><?= htmlspecialchars($fila['litros_c'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr class="text-center align-middle" style="background: #f4b084;">
<td style="background: #f4b084;"><?= htmlspecialchars($fila['res_neto'], ENT_QUOTES, 'UTF-8') ?></td>
<td style="background: #f4b084;"><?= htmlspecialchars($fila['res_bruto'], ENT_QUOTES, 'UTF-8') ?></td>
<td style="background: #f4b084;"><?= htmlspecialchars($fila['res_lts_c'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr class="text-center align-middle">
<td class="fw-bold bg-white" rowspan="2">Total Merma</td>
<td style="background: #f4b084;"><?= htmlspecialchars($fila['res_neto'], ENT_QUOTES, 'UTF-8') ?></td>
<td style="background: #f4b084;"><?= htmlspecialchars($fila['res_bruto'], ENT_QUOTES, 'UTF-8') ?></td>
<td style="background: #f4b084;"><?= htmlspecialchars($fila['res_lts_c'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr class="text-center align-middle">
<td style="background: #fcfcda;"><?= htmlspecialchars($fila['porcentaje_neto'], ENT_QUOTES, 'UTF-8') ?>%</td>
<td style="background: #fcfcda;"><?= htmlspecialchars($fila['porcentaje_bruto'], ENT_QUOTES, 'UTF-8') ?></td>
<td style="background: #fcfcda;"><?= htmlspecialchars($fila['porcentaje_lts_c'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr class="text-center align-middle">
<td class="fw-bold text-white" style="background: #84b6f4;">Tolerancia Permitida</td>
<td class="text-white" style="background: #84b6f4;"><?= htmlspecialchars($fila['tolerancia'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="text-white" style="background: #84b6f4;"><?= htmlspecialchars($fila['litros_c'], ENT_QUOTES, 'UTF-8') ?></td>
<td class="fw-bold text-white" style="background: #84b6f4;"><?= htmlspecialchars($fila['merma_value'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr class="text-center align-middle">
<td colspan="2" class="fw-bold text-white bg-success">Venta al momento</td>
<td colspan="2" class="fw-bold bg-white"><?= htmlspecialchars($fila['venta_momento'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr class="text-center align-middle">
<td colspan="2" class="fw-bold text-white bg-success">Folio de merma</td>
<td colspan="2" class="fw-bold bg-white"><?= htmlspecialchars($fila['folio_merma'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
<tr>
<td colspan="1" class="fw-bold bg-white text-start align-middle">Comentarios:</td>
<td colspan="3" class="fw-normal bg-white text-start align-middle"><?= htmlspecialchars($fila['comentario'], ENT_QUOTES, 'UTF-8') ?></td>
</tr>
</tbody>
</table>
</div>
</div>

<!-- Columna de la Imagen Adaptada al tamaño de la Tabla -->
<?php if (!empty($fila['archivo_existe']) && $fila['archivo_url'] !== ''): ?>
<div class="col-lg-4 d-flex">
<div class="p-3 border rounded bg-light w-100 d-flex flex-column">
<span class="d-block fw-semibold text-center">IMAGEN DE DESCARGA</span>
<hr>
<div class="flex-grow-1 overflow-hidden rounded position-relative w-100">
<img src="<?= htmlspecialchars($fila['archivo_url'], ENT_QUOTES, 'UTF-8') ?>"
alt="Imagen de descarga"
class="position-absolute top-0 start-0 w-100 h-100"
style="object-fit: cover; cursor: pointer;"
data-img-viewer="1"
title="Clic para ampliar imagen">
</div>
</div>
</div>
<?php endif; ?>

</div>
</div>

<!-- Botones de Acción -->
<?php if (!empty($tarjetaMostrarAcciones)): ?>
<div class="card-footer">
    <div class="row g-3">
        <div class="col-md-6">
        <?php if ($tarjetaEditable): ?>
            <button type="button" class="btn bg-warning text-white w-100" @click="abrirEditar(<?= (int)$fila['id_detalle'] ?>)">
                <i class="ti ti-pencil me-1"></i> Editar
            </button>
        <?php else: ?>
            <button type="button" class="btn bg-warning text-white w-100" disabled title="No tienes permisos para editar registros">
                <i class="ti ti-pencil me-1"></i> Editar
            </button>
        <?php endif; ?>
        </div>
        <div class="col-md-6">
        <?php if ($tarjetaEditable): ?>
            <button type="button" class="btn bg-danger text-white w-100" @click="confirmarEliminarDescarga(<?= (int)$fila['id_detalle'] ?>)">
                <i class="ti ti-trash me-1"></i> Eliminar
            </button>
        <?php else: ?>
            <button type="button" class="btn bg-danger text-white w-100" disabled title="No tienes permisos para eliminar registros">
                <i class="ti ti-trash me-1"></i> Eliminar
            </button>
        <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

</div>
</div>
<?php $editMode = !empty($salidaEdit); ?>
<section id="refacciones-salida-formulario" class="pb-4" data-salida-form data-mode="<?= $editMode ? 'pending-edit' : 'create' ?>" data-endpoint="<?= $escape($baseUrl) ?>/salidas/<?= $editMode ? 'actualizar-pendiente' : 'guardar' ?>" data-return-url="<?= $escape($baseUrl) ?>/salidas" data-origin-id="<?= (int) $salidaOriginId ?>" data-origin-name="<?= $escape($salidaOriginName) ?>">
<form id="form-refacciones-salida" novalidate>

<div class="d-flex justify-content-end mb-3">
<button class="btn btn-success" type="submit" data-salida-submit><i class="ti ti-check me-1"></i><?= $editMode ? 'Guardar cambios' : 'Finalizar' ?></button>
</div>


<input type="hidden" name="id_estacion_origen" value="<?= (int) $salidaOriginId ?>">
<div class="row g-3 align-items-stretch mb-3">
<div class="col-12 col-md-8">
<div class="card h-100">
<div class="card-header bg-primary"><h3 class="card-title text-white mb-0"><i class="ti ti-upload me-1"></i>DATOS DE SALIDA</h3></div>
<div class="card-body"><div class="row g-3">
<?php if ($editMode): ?>
<input type="hidden" name="id" value="<?= (int) $salidaRecord['id_movimiento'] ?>">
<div class="col-12"><label class="form-label mb-1">Refacción:</label><div class="form-control-plaintext pt-0"><?= $escape(($salidaRecord['sku'] ?? '') . ' · ' . ($salidaRecord['producto'] ?? '')) ?></div></div>
<div class="col-12 col-lg-6"><label class="form-label mb-1">Estación de origen:</label><div class="form-control-plaintext pt-0"><?= $escape($salidaRecord['origen']) ?></div></div>
<div class="col-12 col-lg-6"><label class="form-label mb-1">Estación de destino:</label><div class="form-control-plaintext pt-0"><?= $escape($salidaRecord['destino']) ?></div></div>
<div class="col-12 col-lg-6"><label class="form-label mb-1">Cantidad:</label><div class="form-control-plaintext pt-0"><?= $escape(rtrim(rtrim(number_format((float) $salidaRecord['cantidad'], 2, '.', ','), '0'), '.')) ?> <?= $escape($salidaRecord['unidad_medida'] ?? 'Pieza') ?></div></div>
<div class="col-12 col-lg-6"><label class="form-label mb-1">* Fecha de salida:</label><input class="form-control" type="date" name="fecha" value="<?= $escape(substr((string) ($salidaRecord['fecha'] ?? ''), 0, 10)) ?>"></div>
<div class="col-12"><label class="form-label mb-1">* Motivo:</label><textarea class="form-control" name="motivo" rows="5"></textarea></div>
<?php else: ?>
<div class="col-12">
<label class="form-label mb-1">* Producto:</label>
<select class="form-select" name="id_refaccion" data-salida-product>
<option value="">Selecciona una refacción</option>
<?php foreach ($salidaProductOptions as $product): ?>
<option value="<?= (int) $product['id'] ?>" data-stock="<?= $escape($product['existencia'] ?? 0) ?>"><?= $escape($product['sku'] . ' · ' . $product['nombre']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-12">
<label class="form-label mb-1">* Cantidad:</label>
<input class="form-control" type="number" name="cantidad" min="0.01" step="0.01" placeholder="Selecciona primero una refacción" data-salida-quantity disabled>
<div class="form-text" data-salida-stock>Selecciona una refacción para consultar su disponibilidad.</div>
<div class="small text-danger d-none" data-salida-stock-error></div>
</div>

<div class="col-12 col-lg-6"><label class="form-label mb-0">* Estación de origen:</label><div class="form-control-plaintext pt-0"><?= $escape($salidaOriginName) ?></div></div>

<div class="col-12 col-lg-6">
<label class="form-label mb-1">* Estación de destino:</label>
<select class="form-select" name="id_estacion_destino" data-salida-destination>
<option value="">Selecciona una estación o departamento</option>
<?php foreach ($locations as $locationId => $locationName): if ((int) $locationId === (int) $salidaOriginId) continue; ?>
<option value="<?= (int) $locationId ?>"><?= $escape($locationName) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-12"><label class="form-label mb-1">* Fecha de salida:</label><input class="form-control" type="date" name="fecha" value="<?= date('Y-m-d') ?>"></div>
<div class="col-12">
<label class="form-label mb-1">* Motivo:</label>
<textarea class="form-control" name="motivo" rows="5"></textarea>
</div>
<?php endif; ?>
</div></div>
</div>
</div>
<div class="col-12 col-md-4">
<div class="card h-100 d-flex flex-column">
<div class="card-header text-bg-primary py-3 border-0">
<div class="d-flex align-items-center gap-2">
<i class="ti ti-signature fs-6"></i>
<h5 class="mb-0 text-white">FIRMA DE QUIEN ELABORA</h5>
</div>
</div>
<div class="card-body p-3 d-flex flex-column">
<div data-signature-wrapper class="flex-grow-1 position-relative" style="border:2px dashed #adb5bd;border-radius:6px;cursor:crosshair;min-height:0">
<canvas data-signature-canvas aria-label="Firma de quien elabora" style="position:absolute;inset:0;width:100%;height:100%;display:block"></canvas>
</div>
<input type="hidden" name="firma_elabora" data-signature-value>
</div>
<button type="button" class="btn bg-danger-subtle text-danger w-100 rounded-top-0" style="border-bottom-left-radius:6px;border-bottom-right-radius:6px" data-signature-clear>
<i class="ti ti-eraser me-1"></i> Limpiar firma
</button>
</div>
</div>
</div>

</form>
</section>

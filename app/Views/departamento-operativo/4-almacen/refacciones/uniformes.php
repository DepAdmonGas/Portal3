<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h2 class="h5 mb-1">Uniformes</h2><p class="small text-muted mb-0">Catálogo de uniformes de Refacciones.</p></div>
    <?php if (!empty($permissions['crear'])): ?><button type="button" class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#modalRefaccionesProducto"><i class="ti ti-plus me-1"></i>Nuevo artículo</button><?php endif; ?>
</div>
<?php if ($items === []): ?>
    <section class="card"><div class="card-body text-center text-muted py-5"><i class="ti ti-package-off fs-8"></i><p class="mb-0 mt-2">Aún no hay artículos en este catálogo.</p></div></section>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($items as $row): ?><div class="col-12 col-md-6 col-xxl-4"><article class="card h-100 mb-0"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3"><span class="badge bg-dark-subtle text-dark"><?= $escape($row['sku']) ?></span><span class="small text-muted"><?= $escape($row['categoria'] ?? '') ?></span></div>
            <?php if (!empty($row['imagen'])): ?><img class="refacciones-product-image mb-3" src="<?= $escape(asset('uploads/archivos/refacciones/' . basename((string) $row['imagen']))) ?>" alt="Imagen de <?= $escape($row['nombre']) ?>" loading="lazy"><?php endif; ?>
            <h3 class="h6 mb-3"><?= $escape(trim(implode(' ', array_filter([$row['nombre'] ?? '', $row['medida'] ?? '', $row['modelo'] ?? '', $row['marca'] ?? ''])))) ?></h3>
            <dl class="row small mb-0"><dt class="col-5 text-muted">Medida / modelo</dt><dd class="col-7 text-end"><?= $escape(trim(($row['medida'] ?? '') . ' ' . ($row['modelo'] ?? '')) ?: '—') ?></dd><dt class="col-5 text-muted">Marca</dt><dd class="col-7 text-end"><?= $escape($row['marca'] ?: '—') ?></dd><dt class="col-5 text-muted">Existencia</dt><dd class="col-7 text-end fw-semibold"><?= $escape(rtrim(rtrim(number_format((float) $row['existencia'], 2, '.', ','), '0'), '.')) ?> <?= $escape($row['unidad_medida']) ?></dd></dl>
            <div class="small border-top mt-3 pt-2"><span class="text-muted">Talla:</span> <?= $escape($row['talla'] ?: '—') ?> <span class="text-muted ms-2">Color:</span> <?= $escape($row['color'] ?: '—') ?></div>
        </div></article></div><?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if (!empty($permissions['crear'])): ?>
<div class="modal fade" id="modalRefaccionesProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form data-refacciones-form novalidate data-endpoint="<?= $escape($baseUrl) ?>/productos/guardar" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="tipo_articulo" value="uniforme" data-product-type>
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"><i class="ti ti-shirt me-1"></i>Nuevo uniforme</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-sku">* SKU:</label><input id="refacciones-uniforme-sku" class="form-control" name="sku" maxlength="60" data-required></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-nombre">* Prenda:</label><input id="refacciones-uniforme-nombre" class="form-control" name="nombre" maxlength="250" data-name-part data-required></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-medida">* Medida:</label><input id="refacciones-uniforme-medida" class="form-control" name="medida" maxlength="100" data-name-part data-required></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-modelo">* Modelo:</label><input id="refacciones-uniforme-modelo" class="form-control" name="modelo" maxlength="150" data-name-part data-required></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-marca">* Marca:</label><input id="refacciones-uniforme-marca" class="form-control" name="marca" maxlength="150" data-name-part data-required></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-categoria">* Categoría:</label><input id="refacciones-uniforme-categoria" class="form-control" name="categoria" maxlength="120" data-required></div>
                        <div class="col-md-4"><label class="form-label" for="refacciones-uniforme-unidad">* Unidad de medida:</label><select id="refacciones-uniforme-unidad" class="form-select" name="unidad_medida" data-required><?php foreach (['Pieza', 'Litro', 'Kilo', 'Metro', 'Caja', 'Galón'] as $unidad): ?><option value="<?= $escape($unidad) ?>"><?= $escape($unidad) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-4"><label class="form-label" for="refacciones-uniforme-minimo">* Stock mínimo:</label><input id="refacciones-uniforme-minimo" class="form-control" type="number" name="stock_minimo" min="0" step="0.01" data-required></div>
                        <div class="col-md-4"><label class="form-label" for="refacciones-uniforme-costo">* Precio unitario:</label><input id="refacciones-uniforme-costo" class="form-control" type="number" name="costo_unitario" min="0" step="0.01" data-required></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-completo">Nombre completo:</label><input id="refacciones-uniforme-completo" class="form-control" data-name-complete readonly value="—" disabled></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-existencia">* Existencia inicial:</label><input id="refacciones-uniforme-existencia" class="form-control" type="number" name="existencia" min="0" step="0.01" data-required></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-ubicacion">* Ubicación inicial:</label><select id="refacciones-uniforme-ubicacion" class="form-select" name="id_estacion" data-required><option value="">Selecciona una ubicación</option><?php foreach ($locations as $id => $name): ?><option value="<?= (int) $id ?>" <?= (int) ($context['id'] ?? 0) === (int) $id ? 'selected' : '' ?>><?= $escape($name) ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-talla">* Talla:</label><input id="refacciones-uniforme-talla" class="form-control" name="talla" maxlength="40" data-required></div>
                        <div class="col-md-6"><label class="form-label" for="refacciones-uniforme-color">* Color:</label><input id="refacciones-uniforme-color" class="form-control" name="color" maxlength="80" data-required></div>
                        <div class="col-12"><label class="form-label" for="refacciones-uniforme-imagen">* Imagen de referencia (JPG o PNG):</label><input id="refacciones-uniforme-imagen" class="form-control" type="file" name="imagen" accept="image/jpeg,image/png,.jpg,.jpeg,.png" data-required></div>
                        <div class="col-12"><label class="form-label" for="refacciones-uniforme-descripcion">* Notas:</label><textarea id="refacciones-uniforme-descripcion" class="form-control" name="descripcion" rows="2" data-required></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancelar</button>
                    <button class="btn btn-success" type="submit"><i class="ti ti-check me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

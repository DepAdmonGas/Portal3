<style>
    .refacciones-product-info-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.refacciones-product-info-label {
    font-weight: 600;
    min-width: 110px; /* Ajusta este valor según lo largo que sea tu texto (ej. "Especificación") */
    white-space: nowrap; /* Evita que la etiqueta se corte a la mitad */
}

.refacciones-product-info-value {
    flex: 1;
    min-width: 0; /* Necesario para que text-truncate funcione correctamente dentro de flexbox */
}
</style>

<?php
$productJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$productImageUrl = static fn ($image): string => !empty($image) ? '/uploads/archivos/refacciones/' . rawurlencode(basename((string) $image)) : '';
?>
<section id="refacciones-productos" x-data="actions()" data-products="<?= $escape($productJson) ?>" data-base-url="<?= $escape($baseUrl) ?>" data-can-edit="<?= !empty($permissions['editar']) ? '1' : '0' ?>" data-can-delete="<?= !empty($permissions['eliminar']) ? '1' : '0' ?>" data-location="<?= $escape($productLocationLabel ?? 'Almacén central') ?>">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Presentación del catálogo">
            <button type="button" class="btn bg-primary-subtle text-primary refacciones-product-view" data-product-view="cards" aria-pressed="false"><i class="ti ti-layout-grid me-1"></i>Fichas <span class="ms-1" data-product-total>(<?= count($items) ?>)</span></button>
            <button type="button" class="btn bg-primary-subtle text-primary refacciones-product-view" data-product-view="table" aria-pressed="false"><i class="ti ti-list me-1"></i>Tabla</button>
        </div>
        <div class="input-group" data-product-card-search-control style="flex: 0 1 420px;">
            <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
            <input type="search" class="form-control" data-product-card-search placeholder="Buscar por SKU, nombre o categoría" aria-label="Buscar fichas de productos">
        </div>
        <div class="flex-grow-1" aria-hidden="true"></div>
        <?php if (!empty($permissions['crear'])): ?><button type="button" class="btn bg-primary-subtle text-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalRefaccionesProducto"><i class="ti ti-plus me-1"></i>Nuevo producto</button><?php endif; ?>
    </div>

        <div data-product-panel="cards">
            <div class="row" data-product-cards>
                <!-- Plantilla única de ficha: ajusta aquí clases, iconos, orden y etiquetas. -->
<template data-product-card-template>
    <div class="col-12 col-lg-6 col-xl-4 mb-4 d-flex align-items-stretch" data-product-card-item>
        <article class="card shadow-sm h-100 w-100 border-0 rounded-4 card-hover overflow-hidden position-relative refacciones-product-card d-flex flex-column" role="button" tabindex="0" data-product-detail aria-label="Ver detalle del producto">
            
            <!-- Imagen / Header de la tarjeta -->
            <div class="refacciones-product-card-image position-relative bg-light text-center p-3">
                <span class="badge bg-dark text-white position-absolute top-0 start-0 m-3 shadow-sm" data-product-card-sku></span>
                <img class="img-fluid rounded d-none" data-product-card-image alt="" loading="lazy" style="max-height: 150px; object-fit: contain;">
                <div class="refacciones-product-image-placeholder py-4" data-product-card-image-empty aria-hidden="true">
                    <i class="ti ti-package text-primary display-6"></i>
                </div>
            </div>

            <!-- Cuerpo de la tarjeta -->
            <div class="card-body p-3 d-flex flex-column gap-2">
                <div class="refacciones-product-info-row d-flex align-items-center gap-2">
                    <span class="refacciones-product-info-icon text-white"><i class="ti ti-package"></i></span>
                    <span class="refacciones-product-info-label fw-bold text-muted small">Nombre:</span>
                    <span class="refacciones-product-info-value text-truncate text-dark" data-product-card-value="nombre"></span>
                </div>
                <div class="refacciones-product-info-row d-flex align-items-center gap-2">
                    <span class="refacciones-product-info-icon text-white"><i class="ti ti-ruler-2"></i></span>
                    <span class="refacciones-product-info-label fw-bold text-muted small">Tamaño:</span>
                    <span class="refacciones-product-info-value text-truncate text-dark" data-product-card-value="tamano"></span>
                </div>
                <div class="refacciones-product-info-row d-flex align-items-center gap-2">
                    <span class="refacciones-product-info-icon text-white"><i class="ti ti-clipboard-text"></i></span>
                    <span class="refacciones-product-info-label fw-bold text-muted small">Especificación:</span>
                    <span class="refacciones-product-info-value text-truncate text-dark" data-product-card-value="especificacion"></span>
                </div>
                <div class="refacciones-product-info-row d-flex align-items-center gap-2">
                    <span class="refacciones-product-info-icon text-white"><i class="ti ti-award"></i></span>
                    <span class="refacciones-product-info-label fw-bold text-muted small">Marca:</span>
                    <span class="refacciones-product-info-value text-truncate text-dark" data-product-card-value="marca"></span>
                </div>
                <div class="refacciones-product-info-row d-flex align-items-center gap-2">
                    <span class="refacciones-product-info-icon text-white"><i class="ti ti-map-pin"></i></span>
                    <span class="refacciones-product-info-label fw-bold text-muted small">Ubicación:</span>
                    <span class="refacciones-product-info-value text-truncate text-dark" data-product-card-value="ubicacion">Almacén central</span>
                </div>
            </div>

            <!-- Pie de tarjeta -->
            <div class="card-footer bg-white border-0 px-3 pb-3 pt-0 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto">
                <span class="small">Ver detalle</span>
                <span class="icon-transition"><i class="ti ti-arrow-right fs-5"></i></span>
            </div>
        </article>
    </div>
</template>
            </div>
<div class="alert alert-secondary border-0 text-center text-muted py-4  d-none" data-product-empty>
    <i class="ti ti-package-off fs-8 d-block mb-2"></i>
    <span data-product-empty-message>Aún no hay artículos en este catálogo.</span>
</div>            <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Paginación de fichas">
                <span class="small text-muted" data-cards-count></span>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-light" data-cards-prev aria-label="Página anterior"><i class="ti ti-chevron-left"></i></button>
                    <button type="button" class="btn btn-light" data-cards-page disabled>1 / 1</button>
                    <button type="button" class="btn btn-light" data-cards-next aria-label="Página siguiente"><i class="ti ti-chevron-right"></i></button>
                </div>
            </nav>
        </div>

        <div data-product-panel="table" class="d-none">
            <div class="datatables">
                <div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
                    <table id="tabla-refacciones-productos" class="table table-striped table-bordered mb-0 align-middle text-center">
                        <thead><tr><th>Imagen</th><th>SKU</th><th class="text-start">Producto</th><th>Medida</th><th>Modelo</th><th>Marca</th><th>Categoría</th><th>Stock</th><th>Mínimo</th><th class="text-end">Precio</th><th class="text-center align-middle" width="48px"><i class="ti ti-dots-vertical fs-6"></i></th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $row): ?>
                                <?php $imageUrl = $productImageUrl($row['imagen'] ?? ''); ?>
                                <tr data-product-row-id="<?= (int) $row['id'] ?>">
                                    <td><?php if ($imageUrl !== ''): ?><img class="refacciones-product-thumb" src="<?= $escape($imageUrl) ?>" alt="" loading="lazy"><?php else: ?><span class="refacciones-product-thumb-placeholder"></span><?php endif; ?></td>
                                    <td><?= $escape($row['sku']) ?></td><td class="text-start"><?= $escape($row['nombre']) ?></td><td><?= $escape($row['medida'] ?: '—') ?></td><td><?= $escape($row['modelo'] ?: '—') ?></td><td><?= $escape($row['marca'] ?: '—') ?></td><td><?= $escape($row['categoria'] ?: '—') ?></td>
                                    <?php $stock = (float) $row['existencia']; $stockTexto = $stock === 0.0 ? '0' : rtrim(rtrim(number_format($stock, 2, '.', ','), '0'), '.'); $minimumText = rtrim(rtrim(number_format((float) $row['stock_minimo'], 2, '.', ','), '0'), '.'); ?>
                                    <td class="<?= $stock <= (float) $row['stock_minimo'] ? 'text-warning fw-semibold' : '' ?>"><?= $escape($stockTexto) ?> <?= $escape($row['unidad_medida']) ?></td><td><?= $escape($minimumText) ?></td><td class="text-end" data-order="<?= $escape($row['costo_unitario']) ?>">$<?= number_format((float) $row['costo_unitario'], 2) ?></td>
                                    <td class="text-center text-nowrap">
                                        <div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown" title="Opciones" aria-label="Acciones de <?= $escape($row['sku']) ?>"><i class="ti ti-dots-vertical fs-6"></i></a><div class="dropdown-menu">
                                            <a href="javascript:void(0)" class="dropdown-item pointer" data-product-detail="<?= (int) $row['id'] ?>"><i class="ti ti-eye me-1"></i>Detalle</a>
                                            <?php if (!empty($permissions['leer'])): ?><a href="javascript:void(0)" class="dropdown-item pointer" data-product-price-history="<?= (int) $row['id'] ?>"><i class="ti ti-history me-1"></i>Precios del producto</a><?php endif; ?>
                                            <?php if (!empty($permissions['editar'])): ?><a href="javascript:void(0)" class="dropdown-item pointer" data-product-edit="<?= (int) $row['id'] ?>"><i class="ti ti-pencil me-1"></i>Editar</a><?php endif; ?>
                                            <?php if (!empty($permissions['eliminar'])): ?><a href="javascript:void(0)" class="dropdown-item pointer text-danger" data-product-delete="<?= (int) $row['id'] ?>"><i class="ti ti-trash me-1"></i>Eliminar</a><?php endif; ?>
                                        </div></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
</section>

<?php if (!empty($permissions['leer'])): ?>
<div class="modal fade" id="modalRefaccionesHistorialPrecios" tabindex="-1" aria-hidden="true" data-price-history-endpoint="<?= $escape($baseUrl) ?>/productos/precios">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header bg-primary"><h5 class="modal-title text-white"><i class="ti ti-history me-1"></i>Precios del producto</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <div class="mb-3"><div class="fw-semibold" data-price-history-product></div><div class="small text-muted" data-price-history-sku></div></div>
            <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><thead><tr><th class="text-center">Fecha</th><th class="text-center">Precio anterior</th><th class="text-center">Precio nuevo</th></tr></thead><tbody data-price-history-rows><tr><td colspan="3" class="text-center text-muted">Cargando historial…</td></tr></tbody></table></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button></div>
    </div></div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalRefaccionesProductoDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header bg-primary"><h5 class="modal-title text-white"><i class="ti ti-eye me-1"></i>Detalle del producto</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body"><div class="row g-3 refacciones-product-detail-fields">
            <div class="col-md-6"><div class="form-label">SKU:</div><div class="refacciones-product-detail-value" data-detail-sku></div></div>
            <div class="col-md-6"><div class="form-label">Nombre:</div><div class="refacciones-product-detail-value" data-detail-name></div></div>
            <div class="col-md-6"><div class="form-label">Medida:</div><div class="refacciones-product-detail-value" data-detail-measure></div></div>
            <div class="col-md-6"><div class="form-label">Modelo:</div><div class="refacciones-product-detail-value" data-detail-model></div></div>
            <div class="col-md-6"><div class="form-label">Marca:</div><div class="refacciones-product-detail-value" data-detail-brand></div></div>
            <div class="col-md-6"><div class="form-label">Categoría:</div><div class="refacciones-product-detail-value" data-detail-category></div></div>
            <div class="col-md-4"><div class="form-label">Unidad de medida:</div><div class="refacciones-product-detail-value" data-detail-unit></div></div>
            <div class="col-md-4"><div class="form-label">Stock mínimo:</div><div class="refacciones-product-detail-value" data-detail-minimum></div></div>
            <div class="col-md-4"><div class="form-label">Precio unitario:</div><div class="refacciones-product-detail-value" data-detail-price></div></div>
           <div class="col-12"><div class="form-label">Nombre completo:</div><div class="refacciones-product-detail-value" data-detail-full-name></div><div class="form-text">Dato informativo generado con nombre, medida, modelo y marca.</div></div>
            <div class="col-12"><label class="form-label">Imagen de referencia:</label><div class="refacciones-product-detail-image-wrap"><img class="refacciones-product-detail-image d-none" data-detail-image alt=""><div class="refacciones-product-image-placeholder" data-detail-image-empty></div></div></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button><?php if (!empty($permissions['editar'])): ?><button type="button" class="btn bg-primary-subtle text-primary" data-detail-edit><i class="ti ti-edit me-1"></i>Editar</button><?php endif; ?><?php if (!empty($permissions['eliminar'])): ?><button type="button" class="btn btn-danger" data-detail-delete><i class="ti ti-trash me-1"></i>Eliminar</button><?php endif; ?></div>
    </div></div>
</div>

<?php if (!empty($permissions['crear']) || !empty($permissions['editar'])): ?>
<div class="modal fade" id="modalRefaccionesProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <form data-refacciones-form data-endpoint="<?= $escape($baseUrl) ?>/productos/guardar" data-update-endpoint="<?= $escape($baseUrl) ?>/productos/actualizar" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="tipo_articulo" value="refaccion" data-product-type><input type="hidden" name="id" value="" data-product-id>
            <div class="modal-header bg-primary"><h5 class="modal-title text-white" data-product-form-title><i class="ti ti-package me-1"></i>Nuevo producto</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="refacciones-producto-sku">* SKU:</label><input id="refacciones-producto-sku" class="form-control" name="sku" maxlength="60" data-required></div>
                <div class="col-md-6"><label class="form-label" for="refacciones-producto-nombre">* Nombre:</label><input id="refacciones-producto-nombre" class="form-control" name="nombre" maxlength="250" data-name-part data-required></div>
                <div class="col-md-6"><label class="form-label" for="refacciones-producto-medida">* Medida:</label><input id="refacciones-producto-medida" class="form-control" name="medida" maxlength="100" placeholder="Ej. 3/16, 1/4, 5mm" data-name-part data-required></div>
                <div class="col-md-6"><label class="form-label" for="refacciones-producto-modelo">* Modelo:</label><input id="refacciones-producto-modelo" class="form-control" name="modelo" maxlength="150" data-name-part data-required></div>
                <div class="col-md-6"><label class="form-label" for="refacciones-producto-marca">* Marca:</label><input id="refacciones-producto-marca" class="form-control" name="marca" maxlength="150" data-name-part data-required></div>
                <div class="col-md-6"><label class="form-label" for="refacciones-producto-categoria">* Categoría:</label><select id="refacciones-producto-categoria" class="form-select" name="categoria" data-required><option value="">Selecciona una categoría</option><?php foreach (($productCategories ?? []) as $categoria): ?><option value="<?= $escape($categoria) ?>"><?= $escape($categoria) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="refacciones-producto-unidad">* Unidad de medida:</label><select id="refacciones-producto-unidad" class="form-select" name="unidad_medida" data-required><?php foreach (['Pieza', 'Litro', 'Kilo', 'Metro', 'Caja', 'Galón'] as $unidad): ?><option value="<?= $escape($unidad) ?>"><?= $escape($unidad) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="refacciones-producto-minimo">* Stock mínimo:</label><input id="refacciones-producto-minimo" class="form-control" type="number" name="stock_minimo" min="0" step="0.01" data-required></div>
                <div class="col-md-4"><label class="form-label" for="refacciones-producto-costo">* Precio unitario:</label><input id="refacciones-producto-costo" class="form-control" type="number" name="costo_unitario" min="0" step="0.01" data-required></div>
                <div class="col-12"><label class="form-label" for="refacciones-producto-completo">Nombre completo:</label><input id="refacciones-producto-completo" class="form-control" data-name-complete readonly aria-describedby="refacciones-producto-ayuda" value="—" disabled><div id="refacciones-producto-ayuda" class="form-text">Se genera automáticamente con nombre, medida, modelo y marca.</div></div>
                <div class="col-12"><label class="form-label" for="refacciones-producto-imagen">* Imagen de referencia (JPG o PNG):</label><input id="refacciones-producto-imagen" class="form-control" type="file" name="imagen" accept="image/jpeg,image/png,.jpg,.jpeg,.png" data-required><div class="form-text d-none" data-current-image-help>Selecciona una imagen nueva sólo si deseas reemplazar la actual.</div></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancelar</button><button class="btn btn-success" type="submit"><i class="ti ti-check me-1"></i><span data-product-submit-label>Guardar</span></button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>

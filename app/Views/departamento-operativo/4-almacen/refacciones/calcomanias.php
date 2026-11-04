<style>
    .refacciones-product-info-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .refacciones-product-info-label {
        font-weight: 600;
        min-width: 110px;
        white-space: nowrap;
    }

    .refacciones-product-info-value {
        flex: 1;
        min-width: 0;
    }
</style>

<?php
$calcomaniaJson = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]';
$calcomaniaImageUrl = static fn ($image): string => !empty($image) ? '/uploads/archivos/refacciones/' . rawurlencode(basename((string) $image)) : '';
?>
<section id="refacciones-calcomanias" x-data="actions()" data-products="<?= $escape($calcomaniaJson) ?>" data-base-url="<?= $escape($baseUrl) ?>" data-can-edit="<?= !empty($permissions['editar']) ? '1' : '0' ?>" data-can-delete="<?= !empty($permissions['eliminar']) ? '1' : '0' ?>">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Presentación del catálogo">
            <button type="button" class="btn bg-primary-subtle text-primary refacciones-product-view" data-product-view="cards" aria-pressed="false"><i class="ti ti-layout-grid me-1"></i>Fichas <span class="ms-1" data-product-total>(<?= count($items) ?>)</span></button>
            <button type="button" class="btn bg-primary-subtle text-primary refacciones-product-view" data-product-view="table" aria-pressed="false"><i class="ti ti-list me-1"></i>Tabla</button>
        </div>
        <div class="input-group" data-product-card-search-control style="flex: 0 1 420px;">
            <span class="input-group-text"><i class="ti ti-search" aria-hidden="true"></i></span>
            <input type="search" class="form-control" data-product-card-search placeholder="Buscar por SKU, nombre o característica" aria-label="Buscar fichas de calcomanías">
        </div>
        <div class="flex-grow-1" aria-hidden="true"></div>
        <?php if (!empty($permissions['crear'])): ?><button type="button" class="btn bg-primary-subtle text-primary ms-auto" data-bs-toggle="modal" data-bs-target="#modalRefaccionesCalcomania"><i class="ti ti-plus me-1"></i>Nueva calcomanía</button><?php endif; ?>
    </div>

    <div data-product-panel="cards">
        <div class="row" data-product-cards>
            <template data-product-card-template>
                <div class="col-12 col-lg-6 col-xl-4 mb-4 d-flex align-items-stretch" data-product-card-item>
                    <article class="card shadow-sm h-100 w-100 border-0 rounded-4 card-hover overflow-hidden position-relative refacciones-product-card d-flex flex-column" role="button" tabindex="0" data-product-detail aria-label="Ver detalle de calcomanía">
                        <div class="refacciones-product-card-image position-relative bg-light text-center p-3">
                            <span class="badge bg-dark text-white position-absolute top-0 start-0 m-3 shadow-sm" data-product-card-sku></span>
                            <img class="img-fluid rounded d-none" data-product-card-image alt="" loading="lazy" style="max-height: 150px; object-fit: contain;">
                            <div class="refacciones-product-image-placeholder py-4" data-product-card-image-empty aria-hidden="true"><i class="ti ti-sticker text-primary display-6"></i></div>
                        </div>
                        <div class="card-body p-3 d-flex flex-column gap-2">
                            <div class="refacciones-product-info-row d-flex align-items-center gap-2"><span class="refacciones-product-info-icon text-white"><i class="ti ti-sticker"></i></span><span class="refacciones-product-info-label fw-bold text-muted small">Nombre:</span><span class="refacciones-product-info-value text-truncate text-dark" data-calcomania-value="nombre"></span></div>
                            <div class="refacciones-product-info-row d-flex align-items-center gap-2"><span class="refacciones-product-info-icon text-white"><i class="ti ti-ruler-2"></i></span><span class="refacciones-product-info-label fw-bold text-muted small">Dimensión:</span><span class="refacciones-product-info-value text-truncate text-dark" data-calcomania-value="dimension"></span></div>
                            <div class="refacciones-product-info-row d-flex align-items-center gap-2"><span class="refacciones-product-info-icon text-white"><i class="ti ti-palette"></i></span><span class="refacciones-product-info-label fw-bold text-muted small">Colores Pantone:</span><span class="refacciones-product-info-value text-truncate text-dark" data-calcomania-value="colores"></span></div>
                            <div class="refacciones-product-info-row d-flex align-items-center gap-2"><span class="refacciones-product-info-icon text-white"><i class="ti ti-sparkles"></i></span><span class="refacciones-product-info-label fw-bold text-muted small">Acabado:</span><span class="refacciones-product-info-value text-truncate text-dark" data-calcomania-value="acabado"></span></div>
                            <div class="refacciones-product-info-row d-flex align-items-center gap-2"><span class="refacciones-product-info-icon text-white"><i class="ti ti-layers-subtract"></i></span><span class="refacciones-product-info-label fw-bold text-muted small">Reproducción:</span><span class="refacciones-product-info-value text-truncate text-dark" data-calcomania-value="reproduccion"></span></div>
                        </div>
                        <div class="card-footer bg-white border-0 px-3 pb-3 pt-0 d-flex align-items-center justify-content-between text-primary fw-semibold mt-auto"><span class="small">Ver detalle</span><span class="icon-transition"><i class="ti ti-arrow-right fs-5"></i></span></div>
                    </article>
                </div>
            </template>
        </div>
        <div class="alert alert-secondary border-0 text-center text-muted py-4 d-none" data-product-empty><i class="ti ti-package-off fs-8 d-block mb-2"></i><span data-product-empty-message>Aún no hay calcomanías registradas.</span></div>
        <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Paginación de fichas">
            <span class="small text-muted" data-cards-count></span>
            <div class="btn-group btn-group-sm" role="group"><button type="button" class="btn btn-light" data-cards-prev aria-label="Página anterior"><i class="ti ti-chevron-left"></i></button><button type="button" class="btn btn-light" data-cards-page disabled>1 / 1</button><button type="button" class="btn btn-light" data-cards-next aria-label="Página siguiente"><i class="ti ti-chevron-right"></i></button></div>
        </nav>
    </div>

    <div data-product-panel="table" class="d-none">
        <div class="datatables"><div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
            <table id="tabla-refacciones-calcomanias" class="table table-striped table-bordered mb-0 align-middle text-center">
                <thead><tr><th>Imagen</th><th>SKU</th><th class="text-start">Nombre</th><th>Dimensión</th><th>Colores (Pantone)</th><th>Acabado</th><th>Reproducción</th><th class="align-middle" width="48px"><i class="ti ti-dots-vertical fs-6"></i></th></tr></thead>
                <tbody><?php foreach ($items as $row): ?>
                    <?php $imageUrl = $calcomaniaImageUrl($row['imagen'] ?? ''); ?>
                    <tr data-product-row-id="<?= (int) $row['id'] ?>">
                        <td><?php if ($imageUrl !== ''): ?><img class="refacciones-product-thumb" src="<?= $escape($imageUrl) ?>" alt="" loading="lazy"><?php else: ?><span class="refacciones-product-thumb-placeholder"></span><?php endif; ?></td>
                        <td><?= $escape($row['sku']) ?></td><td class="text-start"><?= $escape($row['nombre']) ?></td><td><?= $escape($row['dimension'] ?: '—') ?></td><td><?= $escape($row['colores_pantone'] ?: '—') ?></td><td><?= $escape($row['acabado'] ?: '—') ?></td><td><?= $escape($row['reproduccion'] ?: '—') ?></td>
                        <td class="text-center text-nowrap"><div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown" title="Opciones" aria-label="Acciones de <?= $escape($row['sku']) ?>"><i class="ti ti-dots-vertical fs-6"></i></a><div class="dropdown-menu"><a href="javascript:void(0)" class="dropdown-item pointer" data-product-detail="<?= (int) $row['id'] ?>"><i class="ti ti-eye me-1"></i>Detalle</a><?php if (!empty($permissions['editar'])): ?><a href="javascript:void(0)" class="dropdown-item pointer" data-product-edit="<?= (int) $row['id'] ?>"><i class="ti ti-pencil me-1"></i>Editar</a><?php endif; ?><?php if (!empty($permissions['eliminar'])): ?><a href="javascript:void(0)" class="dropdown-item pointer text-danger" data-product-delete="<?= (int) $row['id'] ?>"><i class="ti ti-trash me-1"></i>Eliminar</a><?php endif; ?></div></div></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table>
        </div></div>
    </div>
</section>

<div class="modal fade" id="modalRefaccionesCalcomaniaDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header bg-primary"><h5 class="modal-title text-white"><i class="ti ti-eye me-1"></i>Detalle de la calcomanía</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body"><div class="row g-3 refacciones-product-detail-fields">
            <div class="col-md-6"><div class="form-label">SKU:</div><div class="refacciones-product-detail-value" data-detail-sku></div></div>
            <div class="col-md-6"><div class="form-label">Nombre:</div><div class="refacciones-product-detail-value" data-detail-name></div></div>
            <div class="col-md-6"><div class="form-label">Dimensión:</div><div class="refacciones-product-detail-value" data-detail-dimension></div></div>
            <div class="col-md-6"><div class="form-label">Colores (código Pantone):</div><div class="refacciones-product-detail-value" data-detail-pantone></div></div>
            <div class="col-md-6"><div class="form-label">Acabado:</div><div class="refacciones-product-detail-value" data-detail-finish></div></div>
            <div class="col-md-6"><div class="form-label">Reproducción:</div><div class="refacciones-product-detail-value" data-detail-reproduction></div></div>
            <div class="col-12"><div class="form-label">Notas:</div><div class="refacciones-product-detail-value" data-detail-notes></div></div>
            <div class="col-12"><label class="form-label">Imagen de referencia:</label><div class="refacciones-product-detail-image-wrap"><img class="refacciones-product-detail-image d-none" data-detail-image alt=""><div class="refacciones-product-image-placeholder" data-detail-image-empty></div></div></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button><?php if (!empty($permissions['editar'])): ?><button type="button" class="btn bg-primary-subtle text-primary" data-detail-edit><i class="ti ti-edit me-1"></i>Editar</button><?php endif; ?><?php if (!empty($permissions['eliminar'])): ?><button type="button" class="btn btn-danger" data-detail-delete><i class="ti ti-trash me-1"></i>Eliminar</button><?php endif; ?></div>
    </div></div>
</div>

<?php if (!empty($permissions['crear']) || !empty($permissions['editar'])): ?>
<div class="modal fade" id="modalRefaccionesCalcomania" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <form data-refacciones-form data-catalog-section="refacciones-calcomanias" data-endpoint="<?= $escape($baseUrl) ?>/productos/guardar" data-update-endpoint="<?= $escape($baseUrl) ?>/productos/actualizar" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="tipo_articulo" value="calcomania" data-product-type><input type="hidden" name="id" value="" data-product-id>
            <div class="modal-header bg-primary"><h5 class="modal-title text-white" data-product-form-title><i class="ti ti-sticker me-1"></i>Nueva calcomanía</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body"><div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="refacciones-calcomania-sku">* SKU:</label><input id="refacciones-calcomania-sku" class="form-control" name="sku" maxlength="60" data-required></div>
                <div class="col-md-6"><label class="form-label" for="refacciones-calcomania-nombre">* Nombre:</label><input id="refacciones-calcomania-nombre" class="form-control" name="nombre" maxlength="250" data-required></div>
                <div class="col-12"><label class="form-label" for="refacciones-calcomania-dimension">* Dimensión:</label><input id="refacciones-calcomania-dimension" class="form-control" name="dimension" maxlength="100" placeholder="Ej. 10 × 15 cm" data-required></div>
                <div class="col-12"><label class="form-label" for="refacciones-calcomania-pantone">* Colores (código Pantone):</label><input id="refacciones-calcomania-pantone" class="form-control" name="colores_pantone" maxlength="250" placeholder="Ej. PANTONE 485 C" data-required><div class="form-text">Usa códigos del sistema Pantone. Para varios colores sepáralos con comas.</div></div>
                <div class="col-md-6"><label class="form-label" for="refacciones-calcomania-acabado">* Acabado:</label><select id="refacciones-calcomania-acabado" class="form-select" name="acabado" data-required><option value="">Selecciona una opción...</option><?php foreach (['Mate', 'Brillante', 'Reflejante'] as $acabado): ?><option value="<?= $escape($acabado) ?>"><?= $escape($acabado) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label" for="refacciones-calcomania-reproduccion">* Reproducción:</label><select id="refacciones-calcomania-reproduccion" class="form-select" name="reproduccion" data-required><option value="">Selecciona una opción...</option><?php foreach (['Estireno', 'Acrílico', 'Trovisel', 'Imán'] as $reproduccion): ?><option value="<?= $escape($reproduccion) ?>"><?= $escape($reproduccion) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label" for="refacciones-calcomania-imagen">* Imagen de referencia (JPG o PNG):</label><input id="refacciones-calcomania-imagen" class="form-control" type="file" name="imagen" accept="image/jpeg,image/png,.jpg,.jpeg,.png" data-required><div class="form-text d-none" data-current-image-help>Selecciona una imagen nueva sólo si deseas reemplazar la actual.</div></div>
                <div class="col-12"><label class="form-label" for="refacciones-calcomania-notas">Notas:</label><textarea id="refacciones-calcomania-notas" class="form-control" name="notas" rows="2" maxlength="2000"></textarea></div>
            </div></div>
            <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancelar</button><button class="btn btn-success" type="submit"><i class="ti ti-check me-1"></i><span data-product-submit-label>Guardar</span></button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>

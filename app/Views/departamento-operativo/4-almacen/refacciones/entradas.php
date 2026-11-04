<?php
$contextId = $context['id'] ?? null;
?>
<section id="refacciones-entradas" x-data="actions()"
         data-can-edit="<?= !empty($permissions['editar']) ? '1' : '0' ?>"
         data-can-delete="<?= !empty($permissions['eliminar']) ? '1' : '0' ?>"
         data-entry-context-id="<?= $contextId !== null ? (int) $contextId : '' ?>"
         data-entry-context-name="<?= $escape($context['nombre'] ?? '') ?>"
         data-entry-context-all="<?= $contextId === null && !empty($allowAllLocations) ? '1' : '0' ?>">
    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
        <?php if (!empty($permissions['crear'])): ?>
            <button type="button" class="btn bg-primary-subtle text-primary" data-bs-toggle="modal" data-bs-target="#modalRefaccionesEntrada">
                <i class="ti ti-plus me-1"></i>Nueva entrada
            </button>
        <?php endif; ?>
    </div>

    <div class="datatables">
        <div class="table-responsive overflow-x-auto overflow-y-hidden pb-3">
            <table id="tabla-refacciones-entradas" class="table table-striped table-bordered mb-0 align-middle">
                <thead>
                    <tr><th>#</th><th>Fecha</th><th>Producto</th><th>Estación</th><th>Cantidad</th><th>Proveedor</th><th>Referencia</th><th class="text-center align-middle" width="48px"><i class="ti ti-dots-vertical fs-6"></i></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $row): ?>
                        <?php $locationId = (int) ($row['id_estacion_destino'] ?? 0); ?>
                        <?php $entryJson = json_encode([
                            'id' => (int) $row['id'], 'folio' => (string) ($row['folio'] ?? ''), 'fecha' => substr((string) ($row['fecha'] ?? ''), 0, 10),
                            'id_refaccion' => (int) ($row['id_refaccion'] ?? 0), 'producto' => (string) ($row['productos'] ?? ''),
                            'id_estacion_destino' => $locationId, 'ubicacion' => (string) ($locations[$locationId] ?? ''),
                            'cantidad' => (float) ($row['cantidad'] ?? 0), 'proveedor' => (string) ($row['proveedor'] ?? ''),
                            'referencia' => (string) ($row['referencia'] ?? ''), 'notas' => (string) ($row['notas'] ?? ''),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}'; ?>
                        <tr data-entry-row="<?= (int) $row['id'] ?>" data-entry-detail-json="<?= $escape($entryJson) ?>">
                            <td class="text-center" data-order="<?= (int) $row['id'] ?>"><?= (int) $row['id'] ?></td>
                            <td class="text-center" data-order="<?= $escape(date('Y-m-d', strtotime((string) $row['fecha']))) ?>"><?= $escape(formatearFecha($row['fecha'])) ?></td>
                            <td><?= $escape($row['productos'] ?? '') ?></td>
                            <td class="text-center"><?= $escape($locations[$locationId] ?? '') ?></td>
                            <td class="text-center text-success fw-semibold" data-order="<?= $escape($row['cantidad'] ?? 0) ?>">+<?= $escape(rtrim(rtrim(number_format((float) ($row['cantidad'] ?? 0), 2, '.', ','), '0'), '.')) ?></td>
                            <td class="text-center"><?= $escape(trim((string) ($row['proveedor'] ?? '')) ?: '—') ?></td>
                            <td class="text-center"><?= $escape(trim((string) ($row['referencia'] ?? '')) ?: '—') ?></td>
                            <td class="text-center text-nowrap">
                                <div class="dropdown dropstart"><a href="javascript:void(0)" data-bs-toggle="dropdown" title="Opciones" aria-label="Acciones del folio <?= $escape($row['folio'] ?? '') ?>"><i class="ti ti-dots-vertical fs-6"></i></a><div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:void(0)" class="dropdown-item pointer" data-entry-detail="<?= (int) $row['id'] ?>"><i class="ti ti-eye me-1"></i>Detalle</a>
                                    <?php if (!empty($permissions['editar'])): ?><a href="javascript:void(0)" class="dropdown-item pointer" data-entry-edit="<?= (int) $row['id'] ?>"><i class="ti ti-pencil me-1"></i>Editar</a><?php endif; ?>
                                    <?php if (!empty($permissions['eliminar'])): ?><a href="javascript:void(0)" class="dropdown-item pointer text-danger" data-entry-delete="<?= (int) $row['id'] ?>"><i class="ti ti-trash me-1"></i>Eliminar</a><?php endif; ?>
                                </div></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php if (!empty($permissions['crear'])): ?>
<div class="modal fade" id="modalRefaccionesEntrada" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form data-entry-form data-endpoint="<?= $escape($baseUrl) ?>/movimientos/guardar" novalidate>
                <div class="modal-header bg-primary">
                    <h5 class="modal-title text-white"><i class="ti ti-download me-1"></i>Nueva entrada</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="tipo" value="entrada">
                    <input type="hidden" name="producto_id" value="" data-entry-product-id>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">* Refacción:</label>
                            <select class="form-select" data-entry-product data-required>
                                <option value="">Selecciona una refacción</option>
                                <?php foreach ($productOptions as $product): ?>
                                    <option value="<?= (int) $product['id'] ?>"><?= $escape($product['sku'] . ' · ' . $product['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($contextId === null && !empty($allowAllLocations)): ?>
                            <div class="col-12" data-entry-destinations-wrap>
                                <label class="form-label">* Estación destino <span class="text-muted small">(puedes seleccionar varias)</span>:</label>
                                <select class="form-select" name="destinos[]" data-entry-destinations multiple data-required>
                                    <?php foreach ($locations as $id => $name): ?><option value="<?= (int) $id ?>"><?= $escape($name) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 d-none" data-entry-single-edit-destination>
                                <label class="form-label">* Estación destino:</label>
                                <div class="mb-3" data-entry-single-edit-destination-name></div>
                                <label class="form-label">* Piezas:</label>
                                <input class="form-control" type="number" min="0.01" step="0.01" data-entry-edit-quantity data-required>
                            </div>
                        <?php else: ?>
                            <div class="col-12">
                                <label class="form-label">* Estación destino:</label>
                                <div><?= $escape($context['nombre'] ?? '') ?></div>
                                <input type="hidden" data-entry-fixed-destination name="destinos[]" value="<?= (int) $contextId ?>">
                            </div>
                        <?php endif; ?>
                        <div class="col-12 d-none" data-entry-quantities-section>
                            <label class="form-label mb-1">* Piezas por estación <span class="text-muted small">(asigna la cantidad a cada estación seleccionada)</span>:</label>
                            <div class="form-text mb-2">Indica la cantidad que recibirá cada ubicación.</div>
                            <div class="d-flex flex-column gap-2" data-entry-quantities></div>
                            <div class="small text-muted text-end mt-2">Total: <span data-entry-total>0</span> piezas</div>
                        </div>
                        <?php if ($contextId !== null): ?>
                            <div class="col-12">
                                <label class="form-label">* Piezas:</label>
                                <input class="form-control" type="number" min="0.01" step="0.01" data-entry-fixed-quantity data-required>
                                <input type="hidden" data-entry-fixed-quantity-value>
                            </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label">* Fecha:</label>
                            <input class="form-control" type="date" name="fecha" value="<?= date('Y-m-d') ?>" data-required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">* Proveedor:</label>
                            <select class="form-select" name="id_proveedor" data-entry-provider data-required>
                                <option value="">Selecciona un proveedor</option>
                                <?php foreach ($providerOptions as $provider): ?><option value="<?= (int) $provider['id'] ?>"><?= $escape($provider['razon_social']) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">* Referencia / factura:</label>
                            <input class="form-control" name="referencia" maxlength="120" data-required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea class="form-control" name="notas" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cancelar</button>
                    <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i>Guardar entrada</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalRefaccionesEntradaDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered"><div class="modal-content">
        <div class="modal-header bg-primary"><h5 class="modal-title text-white"><i class="ti ti-eye me-1"></i>Detalle de entrada</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><div class="form-label">Folio:</div><div class="refacciones-product-detail-value" data-entry-detail-folio></div></div>
            <div class="col-md-6"><div class="form-label">Fecha:</div><div class="refacciones-product-detail-value" data-entry-detail-date></div></div>
            <div class="col-md-6"><div class="form-label">Refacción:</div><div class="refacciones-product-detail-value" data-entry-detail-product></div></div>
            <div class="col-md-6"><div class="form-label">Estación destino:</div><div class="refacciones-product-detail-value" data-entry-detail-location></div></div>
            <div class="col-md-6"><div class="form-label">Cantidad:</div><div class="refacciones-product-detail-value" data-entry-detail-quantity></div></div>
            <div class="col-md-6"><div class="form-label">Proveedor:</div><div class="refacciones-product-detail-value" data-entry-detail-provider></div></div>
            <div class="col-12"><div class="form-label">Referencia / factura:</div><div class="refacciones-product-detail-value" data-entry-detail-reference></div></div>
            <div class="col-12"><div class="form-label">Notas:</div><div class="refacciones-product-detail-value" data-entry-detail-notes></div></div>
        </div></div>
        <div class="modal-footer">
            <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1"></i>Cerrar</button>
        </div>
    </div></div>
</div>

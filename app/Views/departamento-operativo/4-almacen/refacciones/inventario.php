<?php if (!empty($inventoryIsAll)): ?>
<div class="row justify-content-end mb-4">
    <div class="col-auto">
        <div class="d-inline-flex align-items-center bg-dark border rounded-pill  px-4 py-2">
            <span class="text-white small me-2">Valor total:</span>
            <span class="text-primary fw-semibold fs-5">$<?= $escape(number_format((float) $inventoryTotal, 2)) ?></span>
        </div>
    </div>
</div>
    <?php foreach ($inventoryGroups as $group): ?>
        <section class="card mb-4">
            <div class="card-header bg-primary d-flex flex-wrap align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2 text-white">
                    <i class="ti ti-gas-station fs-5 flex-shrink-0" aria-hidden="true"></i>
                    <div>
                        <h2 class="h5 mb-1 text-white"><?= $escape($group['name']) ?></h2>
                        <p class="small mb-0 text-white"><?= count(array_unique(array_column($group['rows'], 'id'))) ?> productos con existencia</p>
                    </div>
                </div>
                <div class="text-end text-white">
                    <div class="small text-white">Valor</div>
                    <strong class="fs-5 text-white">$<?= $escape(number_format($group['value'], 2)) ?></strong>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0" data-refacciones-inventory-table>
                        <thead><tr><th class="text-center">SKU</th><th>Producto</th><th class="text-center">Stock</th><th class="text-center">Precio</th><th class="text-center">Valor</th></tr></thead>
                        <tbody>
                            <?php foreach ($group['rows'] as $row): ?>
                                <tr>
                                    <td class="text-center"><?= $escape($row['sku']) ?></td>
                                    <td><?= $escape($row['nombre']) ?></td>
                                    <td class="text-center <?= (float) $row['stock_minimo'] > 0 && (float) ($row['existencia_total'] ?? $row['existencia']) <= (float) $row['stock_minimo'] ? 'text-warning fw-semibold' : '' ?>" data-order="<?= $escape($row['existencia']) ?>"><?= $escape(rtrim(rtrim(number_format((float) $row['existencia'], 2, '.', ','), '0'), '.')) ?></td>
                                    <td class="text-center">$<?= $escape(number_format((float) $row['costo_unitario'], 2)) ?></td>
                                    <td class="text-center fw-semibold">$<?= $escape(number_format((float) $row['existencia'] * (float) $row['costo_unitario'], 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
<?php else: ?>
<div class="row justify-content-end mb-4">
    <div class="col-auto">
        <div class="d-inline-flex align-items-center bg-dark border rounded-pill px-4 py-2">
            <span class="text-white small me-2">Valor total:</span>
            <span class="text-primary fw-semibold fs-5">$<?= $escape(number_format((float) $inventoryTotal, 2)) ?></span>
        </div>
    </div>
</div>
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle mb-0" data-refacciones-inventory-table>
            <thead><tr><th class="text-center">SKU</th><th>Producto</th><th class="text-center">Stock</th><th class="text-center">Precio</th><th class="text-center">Valor</th></tr></thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                    <tr>
                        <td class="text-center"><?= $escape($row['sku']) ?></td>
                        <td><?= $escape($row['nombre']) ?></td>
                        <td class="text-center <?= (float) $row['stock_minimo'] > 0 && (float) ($row['existencia_total'] ?? $row['existencia']) <= (float) $row['stock_minimo'] ? 'text-warning fw-semibold' : '' ?>" data-order="<?= $escape($row['existencia']) ?>"><?= $escape(rtrim(rtrim(number_format((float) $row['existencia'], 2, '.', ','), '0'), '.')) ?></td>
                        <td class="text-center">$<?= $escape(number_format((float) $row['costo_unitario'], 2)) ?></td>
                        <td class="text-center fw-semibold">$<?= $escape(number_format((float) $row['existencia'] * (float) $row['costo_unitario'], 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
$dashboardData = is_array($dashboardData ?? null) ? $dashboardData : [];
$categoryRows = is_array($dashboardData['stock_por_categoria'] ?? null) ? $dashboardData['stock_por_categoria'] : [];
$consumptionRows = is_array($dashboardData['consumo_por_ubicacion'] ?? null) ? $dashboardData['consumo_por_ubicacion'] : [];
$lowStockRows = is_array($dashboardData['stock_bajo_detalle'] ?? null) ? $dashboardData['stock_bajo_detalle'] : [];
$upcomingEvents = is_array($dashboardData['proximos_eventos'] ?? null) ? $dashboardData['proximos_eventos'] : [];
?>
<div class="row g-3 mb-4">
    <?php foreach ($dashboardCards as $card): ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card refacciones-metric refacciones-metric-<?= $escape($card['style']) ?> h-100 mb-0">
                <div class="card-body d-flex align-items-start justify-content-between">
                    <div><div class="refacciones-metric-label small mb-2"><?= $escape($card['label']) ?></div><div class="h3 mb-0"><?= $escape($card['value']) ?></div></div>
                    <span class="refacciones-metric-icon text-<?= $escape($card['iconColor']) ?>"><i class="<?= $escape($card['icon']) ?>"></i></span>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-6">
        <section class="card h-100 mb-0">
            <div class="card-header bg-primary text-white py-3 border-0">
                <div class="d-flex align-items-center gap-2"><i class="ti ti-chart-bar fs-5"></i><h2 class="h6 mb-0 text-white">Consumo por estación</h2></div>
            </div>
            <div class="card-body">
            <?php if (empty($consumptionRows)): ?>
                <div class="text-muted py-5 text-center">Aún no hay salidas finalizadas registradas.</div>
            <?php else: ?>
                <div id="refacciones-chart-consumo" class="refacciones-dashboard-chart"
                     data-series="<?= $escape(json_encode(array_column($consumptionRows, 'total'))) ?>"
                     data-labels="<?= $escape(json_encode(array_column($consumptionRows, 'nombre'), JSON_UNESCAPED_UNICODE)) ?>"></div>
            <?php endif; ?>
            </div>
        </section>
    </div>
    <div class="col-12 col-xl-6">
        <section class="card h-100 mb-0">
            <div class="card-header bg-primary text-white py-3 border-0">
                <div class="d-flex align-items-center gap-2"><i class="ti ti-chart-pie fs-5"></i><h2 class="h6 mb-0 text-white">Stock por categoría</h2></div>
            </div>
            <div class="card-body">
            <div id="refacciones-chart-categorias" class="refacciones-dashboard-chart"
                 data-series="<?= $escape(json_encode(array_column($categoryRows, 'total'))) ?>"
                 data-labels="<?= $escape(json_encode(array_column($categoryRows, 'categoria'), JSON_UNESCAPED_UNICODE)) ?>"></div>
            </div>
        </section>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <section class="card mb-0">
            <div class="card-header bg-primary text-white py-3 border-0">
                <div class="d-flex align-items-center gap-2"><i class="ti ti-alert-triangle fs-5"></i><h2 class="h6 mb-0 text-white">Productos con stock bajo el mínimo</h2></div>
            </div>
            <div class="card-body">
                <div class="datatables">
                    <div class="table-responsive overflow-x-auto pb-3">
                        <table id="tabla-refacciones-stock-bajo" class="table table-striped table-bordered mb-0 align-middle">
                            <thead><tr><th>Estación / Departamento</th><th>SKU</th><th>Producto</th><th>Categoría</th><th>Stock actual</th><th>Mínimo</th></tr></thead>
                            <tbody><?php foreach ($lowStockRows as $row): ?>
                                <tr>
                                    <td><?= $escape($row['ubicacion']) ?></td>
                                    <td class="small text-muted"><?= $escape($row['sku']) ?></td>
                                    <td><?= $escape($row['nombre']) ?></td>
                                    <td><?= $escape($row['categoria']) ?></td>
                                    <td class="text-warning fw-semibold"><?= $escape(rtrim(rtrim(number_format((float) $row['existencia'], 2, '.', ','), '0'), '.')) ?></td>
                                    <td><?= $escape(rtrim(rtrim(number_format((float) $row['minimo'], 2, '.', ','), '0'), '.')) ?></td>
                                </tr>
                            <?php endforeach; ?></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <section class="card mb-0">
            <div class="card-header bg-primary text-white py-3 border-0">
                <div class="d-flex align-items-center gap-2"><i class="ti ti-calendar-event fs-5"></i><h2 class="h6 mb-0 text-white">Próximos eventos</h2></div>
            </div>
            <div class="card-body">
                <div class="datatables">
                    <div class="table-responsive overflow-x-auto pb-3">
                        <table id="tabla-refacciones-proximos-eventos" class="table table-striped table-bordered mb-0 align-middle">
                            <thead><tr><th>Estación / Departamento</th><th>Fecha</th><th>Tipo</th><th>Evento</th></tr></thead>
                            <tbody>
                                <?php
                                $eventTypes = [
                                    'compra' => 'Compras programadas',
                                    'mantenimiento_externo' => 'Mantenimientos por proveedores externos',
                                    'prueba' => 'Registro, preparación y pruebas',
                                    'calibracion' => 'Calibraciones',
                                ];
                                foreach ($upcomingEvents as $row):
                                    $eventTimestamp = strtotime(trim((string) ($row['fecha'] ?? '') . ' ' . (string) ($row['hora'] ?? '')));
                                    $eventTimestamp = $eventTimestamp === false ? strtotime((string) ($row['fecha'] ?? '')) : $eventTimestamp;
                                    $eventDateOrder = $eventTimestamp === false ? '' : date('Y-m-d H:i:s', $eventTimestamp);
                                    $eventDateLabel = formatearFecha($row['fecha'] ?? '');
                                    if (!empty($row['hora']) && $eventTimestamp !== false) $eventDateLabel .= ' ' . date('g:i a', $eventTimestamp);
                                ?>
                                    <tr>
                                        <td class="text-center"><?= $escape($row['ubicacion'] ?? '') ?></td>
                                        <td class="text-center" data-order="<?= $escape($eventDateOrder) ?>"><?= $escape($eventDateLabel) ?></td>
                                        <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= $escape($eventTypes[$row['tipo'] ?? ''] ?? 'Evento') ?></span></td>
                                        <td><?= $escape($row['titulo'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<?php
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$periodo = in_array($data['periodo'] ?? '', ['mensual', 'semestral', 'anual'], true) ? $data['periodo'] : 'mensual';
$currentYear = (int) date('Y');
$year = (int) ($data['year'] ?? $currentYear);
if ($year < 2024 || $year > $currentYear) $year = $currentYear;
$month = (int) ($data['month'] ?? date('n'));
$semestre = (int) ($data['semestre'] ?? ($month > 6 ? 2 : 1));
$quantity = static function ($value): string {
    $formatted = number_format((float) ($value ?? 0), 2, '.', ',');
    return rtrim(rtrim($formatted, '0'), '.');
};
$rows = is_array($data['detalle'] ?? null) ? $data['detalle'] : [];
$totals = [
    'entradas' => (float) ($data['entradas'] ?? 0),
    'valor_entradas' => (float) ($data['valor_entradas'] ?? 0),
    'salidas' => (float) ($data['salidas'] ?? 0),
    'valor_salidas' => (float) ($data['valor_salidas'] ?? 0),
];
?>
<section id="refacciones-reportes" data-refacciones-reportes>
    <?php if (!empty($canSendReportEmail)): ?>
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-primary" data-report-send-email data-report-email-endpoint="<?= $escape($baseUrl) ?>/reportes/enviar-correo">
                <i class="ti ti-mail me-1" aria-hidden="true"></i>Enviar por correo
            </button>
        </div>
    <?php endif; ?>
    <form method="post" class="mb-4" data-report-filter data-report-endpoint="<?= $escape($baseUrl) ?>/reportes/consultar">
        <input type="hidden" value="<?= $escape($periodo) ?>" data-report-period-value>
        <input type="hidden" value="<?= $year ?>" data-report-year-value>
        <input type="hidden" value="<?= $month ?>" data-report-month-value>
        <div class="d-flex flex-wrap align-items-end gap-3 w-100">
            <div>
                <label class="form-label d-block">Período:</label>
                <div class="btn-group" role="group" aria-label="Período del reporte">
                    <?php foreach (['mensual' => 'Mensual', 'semestral' => 'Semestral', 'anual' => 'Anual'] as $key => $label): ?>
                        <button type="button" class="btn <?= $periodo === $key ? 'btn-primary' : 'btn-outline-primary' ?>" data-report-period="<?= $key ?>" aria-pressed="<?= $periodo === $key ? 'true' : 'false' ?>"><?= $label ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="ms-auto float-end <?= $periodo === 'mensual' ? '' : 'd-none' ?>" data-report-controls="mensual">
                <label class="form-label" for="reporte-periodo-mes">Mes del reporte:</label>
                <input class="form-control" type="month" id="reporte-periodo-mes" min="2024-01" max="<?= date('Y-m') ?>" value="<?= sprintf('%04d-%02d', $year, $month) ?>" data-report-month-picker>
            </div>
            <div class="d-flex align-items-end gap-2 ms-auto float-end <?= $periodo === 'semestral' ? '' : 'd-none' ?>" data-report-controls="semestral">
                <div>
                    <label class="form-label" for="reporte-year-semestral">Año:</label>
                    <select class="form-select" id="reporte-year-semestral" data-report-year>
                        <?php for ($optionYear = 2024; $optionYear <= $currentYear; $optionYear++): ?>
                            <option value="<?= $optionYear ?>" <?= $year === $optionYear ? 'selected' : '' ?>><?= $optionYear ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="reporte-semestre">Semestre:</label>
                    <select class="form-select" id="reporte-semestre" data-report-semester>
                        <option value="1" <?= $semestre === 1 ? 'selected' : '' ?>>1.º (enero – junio)</option>
                        <option value="2" <?= $semestre === 2 ? 'selected' : '' ?>>2.º (julio – diciembre)</option>
                    </select>
                </div>
            </div>
            <div class="ms-auto float-end <?= $periodo === 'anual' ? '' : 'd-none' ?>" data-report-controls="anual">
                <label class="form-label" for="reporte-year-anual">Año del reporte:</label>
                <select class="form-select" id="reporte-year-anual" data-report-year>
                    <?php for ($optionYear = 2024; $optionYear <= $currentYear; $optionYear++): ?>
                        <option value="<?= $optionYear ?>" <?= $year === $optionYear ? 'selected' : '' ?>><?= $optionYear ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <?php
        $metrics = [
            ['Entradas del período', $quantity($totals['entradas']), 'success', 'ti-download'],
            ['Valor de entradas', '$' . number_format($totals['valor_entradas'], 2), 'primary', 'ti-currency-dollar'],
            ['Salidas (consumo)', $quantity($totals['salidas']), 'secondary', 'ti-upload'],
            ['Valor del consumo', '$' . number_format($totals['valor_salidas'], 2), 'warning', 'ti-chart-bar'],
        ];
        foreach ($metrics as [$label, $value, $color, $icon]): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card refacciones-metric refacciones-metric-<?= $escape($color) ?> h-100 mb-0">
                    <div class="card-body d-flex align-items-center justify-content-between gap-3">
                        <div><div class="refacciones-metric-label small mb-2"><?= $escape($label) ?></div><div class="h3 mb-0" data-report-metric-value="<?= $escape(match ($label) { 'Entradas del período' => 'entradas', 'Valor de entradas' => 'valor_entradas', 'Salidas (consumo)' => 'salidas', default => 'valor_salidas' }) ?>"><?= $escape($value) ?></div></div>
                        <i class="ti <?= $escape($icon) ?> refacciones-metric-icon" aria-hidden="true"></i>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="card mb-0">
        <div class="card-header bg-primary text-white d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2"><i class="ti ti-report-analytics" aria-hidden="true"></i><h2 class="h5 mb-0 text-white">Detalle por producto</h2></div>
            <div class="small text-white" data-report-period-label>Período: <?= $escape($data['label'] ?? '') ?></div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle mb-0" data-refacciones-report-table>
                    <thead><tr>
                        <th scope="col" class="text-center">SKU</th>
                        <th scope="col">Producto</th>
                        <th scope="col" class="text-center">Entradas (pzas)</th>
                        <th scope="col" class="text-center">Valor entradas</th>
                        <th scope="col" class="text-center">Salidas (pzas)</th>
                        <th scope="col" class="text-center">Valor consumo</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td class="small text-muted text-center"><?= $escape($row['sku'] ?? '') ?></td>
                                <td><?= $escape($row['nombre'] ?? '') ?></td>
                                <td class="text-center"><?= $escape($quantity($row['entradas'] ?? 0)) ?></td>
                                <td class="text-center">$<?= $escape(number_format((float) ($row['valor_entradas'] ?? 0), 2)) ?></td>
                                <td class="text-center"><?= $escape($quantity($row['salidas'] ?? 0)) ?></td>
                                <td class="text-center fw-semibold">$<?= $escape(number_format((float) ($row['valor_salidas'] ?? 0), 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="fw-bold">
                        <th scope="row" class="text-center">Total</th>
                        <td></td>
                        <td class="text-center" data-report-total="entradas"><?= $escape($quantity($totals['entradas'])) ?></td>
                        <td class="text-center" data-report-total="valor_entradas">$<?= $escape(number_format($totals['valor_entradas'], 2)) ?></td>
                        <td class="text-center" data-report-total="salidas"><?= $escape($quantity($totals['salidas'])) ?></td>
                        <td class="text-center" data-report-total="valor_salidas">$<?= $escape(number_format($totals['valor_salidas'], 2)) ?></td>
                    </tr></tfoot>
                </table>
            </div>
        </div>
    </section>
</section>

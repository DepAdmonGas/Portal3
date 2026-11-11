<?php
$state = (string) ($state ?? 'idle');
$periodUiPreset = (string) ($periodUiPreset ?? $periodPreset ?? 'custom');
$sourceDailyContext = [];
if (($sourceWorkplaceId ?? null) !== null && ($sourceWorkDate ?? '') !== '') {
    $sourceDailyContext = [
        'date' => (string) $sourceWorkDate,
    ];
    if (($sourceWorkerId ?? null) !== null) {
        $sourceDailyContext['worker_id'] = (int) $sourceWorkerId;
    }
}
$periodYear = (int) ($periodYear ?? gmdate('Y'));
$periodMonth = (int) ($periodMonth ?? gmdate('n'));
$periodSummaryLabel = (string) ($periodLabel ?? 'Rango personalizado (UTC)');
if ($periodUiPreset === 'year') {
    $monthNames = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    $periodSummaryLabel = ($monthNames[$periodMonth] ?? 'Mes') . ' ' . $periodYear . ' (UTC)';
}
$previousPeriodHref = null;
try {
    $periodStartDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($fromDate ?? ''), new \DateTimeZone('UTC'));
    $periodEndDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($toDate ?? ''), new \DateTimeZone('UTC'));
    if ($periodStartDate !== false && $periodEndDate !== false && $periodStartDate <= $periodEndDate) {
        if (($periodPreset ?? '') === 'month') {
            $previousStart = $periodStartDate->modify('first day of this month')->modify('-1 month');
            $previousEnd = $previousStart->modify('last day of this month');
        } elseif (($periodPreset ?? '') === 'year' && (int) ($periodMonth ?? 0) > 1) {
            $previousStart = $periodStartDate->modify('first day of this month')->modify('-1 month');
            $previousEnd = $previousStart->modify('last day of this month');
        } else {
            $previousEnd = $periodStartDate->modify('-1 day');
            $periodDays = (int) $periodStartDate->diff($periodEndDate)->days + 1;
            $previousStart = $previousEnd->modify('-' . max(0, $periodDays - 1) . ' days');
        }
        $previousQuery = [
            'from_date' => $previousStart->format('Y-m-d'),
            'to_date' => $previousEnd->format('Y-m-d'),
        ];
        if (($workerId ?? '') !== '' && ($workerId ?? null) !== null) $previousQuery['worker_id'] = (int) $workerId;
        $previousPeriodHref = '/departamento-operativo/recursos-humanos/checador/reporte?'
            . http_build_query($previousQuery, '', '&', PHP_QUERY_RFC3986);
    }
} catch (\Throwable) {
    $previousPeriodHref = null;
}
?>
<div class="container-fluid mt-4 mb-5">
    <form id="punch-report-period-form" method="get" action="/departamento-operativo/recursos-humanos/checador/reporte" class="row g-2 align-items-end mb-2" data-checador-period-form>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label mb-1" for="punch-report-period">Periodo</label>
            <select class="form-select" id="punch-report-period" name="period">
                <?php foreach (['today' => 'Hoy', 'last7' => 'Últimos 7 días', 'month' => 'Mes actual', 'specific' => 'Mes y año específicos', 'custom' => 'Rango personalizado'] as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (($periodUiPreset ?? '') === $value || ($value === 'specific' && ($periodUiPreset ?? '') === 'year')) ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-3 col-lg-2 <?= ($periodUiPreset ?? '') === 'year' ? '' : 'd-none' ?>" data-period-month>
            <label class="form-label mb-1" for="punch-report-month">Mes</label>
            <select class="form-select" id="punch-report-month" name="month">
                <?php foreach ([1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'] as $monthValue => $monthName): ?>
                    <option value="<?= $monthValue ?>" <?= $periodMonth === $monthValue ? 'selected' : '' ?>><?= htmlspecialchars($monthName, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-sm-3 col-lg-2 <?= ($periodUiPreset ?? '') === 'year' ? '' : 'd-none' ?>" data-period-year>
            <label class="form-label mb-1" for="punch-report-year">Año</label>
            <input class="form-control" type="number" id="punch-report-year" name="year" min="2000" max="<?= (int) gmdate('Y') ?>" value="<?= $periodYear ?>" inputmode="numeric">
        </div>
        <div class="col-12 col-sm-6 col-lg-3 punch-report-worker-field">
            <label class="form-label mb-1" for="punch-report-worker">Colaborador</label>
            <select class="form-select" id="punch-report-worker" name="worker_id"
                data-search-url="/departamento-operativo/recursos-humanos/checador/reporte/colaboradores"
                aria-describedby="punch-report-worker-help">
                <option value="">Todos los colaboradores</option>
                <?php if (is_array($selectedWorkerOption ?? null)): ?>
                    <option value="<?= (int) $selectedWorkerOption['id'] ?>" selected><?= htmlspecialchars((string) $selectedWorkerOption['text'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endif; ?>
            </select>
            <span class="visually-hidden" id="punch-report-worker-help">Busca por nombre o apellido; el identificador se conserva de forma interna.</span>
        </div>
        <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#punch-report-filter-modal">
                <i class="ti ti-filter me-1" aria-hidden="true"></i>Más filtros
            </button>
            <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1" aria-hidden="true"></i>Consultar</button>
            <?php if ($previousPeriodHref !== null): ?><a class="btn btn-outline-secondary" href="<?= htmlspecialchars($previousPeriodHref, ENT_QUOTES, 'UTF-8') ?>">Periodo anterior</a><?php endif; ?>
        </div>
        <input type="hidden" name="capture_method" value="<?= htmlspecialchars((string) ($captureMethod ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="processing_status" value="<?= htmlspecialchars((string) ($processingStatus ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($sourceDailyContext !== []): ?>
            <input type="hidden" name="source_work_date" value="<?= htmlspecialchars((string) $sourceWorkDate, ENT_QUOTES, 'UTF-8') ?>">
            <?php if (($sourceWorkerId ?? null) !== null): ?><input type="hidden" name="source_worker_id" value="<?= (int) $sourceWorkerId ?>"><?php endif; ?>
        <?php endif; ?>
    </form>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-live="polite">
        <span class="small text-muted"><?= htmlspecialchars($periodSummaryLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <?php if (($fromDate ?? '') !== '' && ($toDate ?? '') !== ''): ?><span class="badge text-bg-light border">UTC <?= htmlspecialchars((string) $fromDate, ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars((string) $toDate, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        <?php if (($captureMethod ?? '') !== ''): ?><span class="badge text-bg-light border"><?= htmlspecialchars((string) ($captureMethodOptions[$captureMethod] ?? 'Método filtrado'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        <?php if (($processingStatus ?? '') !== ''): ?><span class="badge text-bg-light border"><?= htmlspecialchars((string) ($processingStatusOptions[$processingStatus] ?? 'Estado filtrado'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
    </div>

    <div class="modal fade" id="punch-report-filter-modal" tabindex="-1" aria-labelledby="punch-report-filter-modal-title" aria-hidden="true" data-checador-filter-modal>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="punch-report-filter-form" method="get" action="/departamento-operativo/recursos-humanos/checador/reporte" data-checador-filter-form>
                    <div class="modal-header modal-colored-header bg-primary text-white">
                        <h2 class="modal-title h5 text-white mb-0" id="punch-report-filter-modal-title">Filtros de consulta</h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar filtros"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="worker_id" name="worker_id" value="<?= htmlspecialchars((string) ($workerId ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6"><label class="form-label" for="from_date">Fecha inicial (UTC)</label><input class="form-control" id="from_date" name="from_date" type="date" value="<?= htmlspecialchars((string) ($fromDate ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                            <div class="col-12 col-md-6"><label class="form-label" for="to_date">Fecha final (UTC)</label><input class="form-control" id="to_date" name="to_date" type="date" value="<?= htmlspecialchars((string) ($toDate ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="capture_method">Método de captura</label>
                            <select class="form-select" id="capture_method" name="capture_method">
                                <option value="">Todos los tipos</option>
                                <?php foreach (($captureMethodOptions ?? []) as $methodCode => $methodLabel): ?>
                                    <option value="<?= htmlspecialchars((string) $methodCode, ENT_QUOTES, 'UTF-8') ?>" <?= ($captureMethod ?? '') === $methodCode ? 'selected' : '' ?>><?= htmlspecialchars((string) $methodLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="processing_status">Estado de procesamiento</label>
                            <select class="form-select" id="processing_status" name="processing_status">
                                <option value="">Todos los estados</option>
                                <?php foreach (($processingStatusOptions ?? []) as $statusCode => $statusLabel): ?>
                                    <option value="<?= htmlspecialchars((string) $statusCode, ENT_QUOTES, 'UTF-8') ?>" <?= ($processingStatus ?? '') === $statusCode ? 'selected' : '' ?>><?= htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" id="dt_search" name="dt_search" value="<?= htmlspecialchars((string) ($tableSearch ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="dt_order_column" name="dt_order_column" value="<?= (int) ($tableOrderColumn ?? 1) ?>">
                        <input type="hidden" id="dt_order_direction" name="dt_order_direction" value="<?= ($sort ?? 'asc') === 'desc' ? 'desc' : 'asc' ?>">
                        <input type="hidden" id="punch-report-clear-period" name="period" value="today">
                        <?php if ($sourceDailyContext !== []): ?>
                            <input type="hidden" name="source_work_date" value="<?= htmlspecialchars((string) $sourceWorkDate, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (($sourceWorkerId ?? null) !== null): ?>
                                <input type="hidden" name="source_worker_id" value="<?= (int) $sourceWorkerId ?>">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-checador-clear-filters>
                            <i class="ti ti-filter-off me-1" aria-hidden="true"></i>Limpiar filtros
                        </button>
                        <button class="btn btn-primary" type="submit">
                            <i class="ti ti-search me-1" aria-hidden="true"></i>Aplicar filtros
                        </button>
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($state === 'idle'): ?>
        <section class="card mb-4" aria-labelledby="punch-report-results-title"><div class="card-header bg-primary text-white d-flex align-items-center gap-2"><span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-fingerprint fs-5"></i></span><h2 class="h5 mb-0 text-white" id="punch-report-results-title">Marcaciones registradas</h2><span class="badge rounded-pill bg-white text-primary ms-auto">Horas en UTC</span></div><div class="card-body"><div class="text-muted small" role="note">Consulta de registros originales. La integridad no está verificada por Portal3; el reporte no evalúa jornadas.</div><p class="mb-0 mt-3">Selecciona una localidad autorizada y consulta el periodo.</p></div></section>
    <?php elseif ($state === 'error' || $state === 'unavailable'): ?>
        <div class="alert alert-danger" role="alert">
            <i class="ti ti-alert-circle me-1" aria-hidden="true"></i>
            <?= htmlspecialchars((string) ($stateMessage ?? 'La consulta no está disponible.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php elseif ($state === 'success'): ?>
        <section class="card" aria-labelledby="punch-report-results-title">
            <div class="card-header bg-primary text-white d-flex flex-wrap align-items-center gap-2 gap-md-3">
                <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-fingerprint fs-5"></i></span>
                <div>
                    <h2 class="h5 mb-0 text-white" id="punch-report-results-title">Marcaciones registradas</h2>
                    <span class="small text-white-50"><?= htmlspecialchars((string) ($selectedWorkplaceName ?: 'Localidad no disponible'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <span class="badge rounded-pill bg-white text-primary ms-auto">Horas en UTC</span>
                <?php if (!empty($canDownload)): ?>
                    <form id="punch-report-csv-form" method="get" action="/departamento-operativo/recursos-humanos/checador/reporte" class="ms-md-2" data-checador-csv-form>
                        <input type="hidden" name="from_date" value="<?= htmlspecialchars((string) ($fromDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="to_date" value="<?= htmlspecialchars((string) ($toDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="worker_id" value="<?= htmlspecialchars((string) ($workerId ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="capture_method" value="<?= htmlspecialchars((string) ($captureMethod ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="processing_status" value="<?= htmlspecialchars((string) ($processingStatus ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="dt_search" value="<?= htmlspecialchars((string) ($tableSearch ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="dt_order_column" value="<?= (int) ($tableOrderColumn ?? 1) ?>">
                        <input type="hidden" name="dt_order_direction" value="<?= ($sort ?? 'asc') === 'desc' ? 'desc' : 'asc' ?>">
                        <?php if ($sourceDailyContext !== []): ?><input type="hidden" name="source_work_date" value="<?= htmlspecialchars((string) $sourceWorkDate, ENT_QUOTES, 'UTF-8') ?>"><?php if (($sourceWorkerId ?? null) !== null): ?><input type="hidden" name="source_worker_id" value="<?= (int) $sourceWorkerId ?>"><?php endif; ?><?php endif; ?>
                        <button class="btn bg-white text-primary d-inline-flex align-items-center" type="submit" name="export" value="csv"><i class="ti ti-download me-1" aria-hidden="true"></i>Exportar CSV</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">Consulta registros originales en UTC; no convierte la hora del dispositivo ni evalúa jornadas. La integridad no está verificada por Portal3 y un periodo sin resultados no confirma ausencia laboral.</p>
                <div id="punch-report-ajax-error" class="alert alert-danger d-none" role="alert" aria-live="assertive">
                    La consulta no está disponible; no se interpreta como ausencia de marcaciones. Vuelve a consultar o inicia sesión nuevamente.
                </div>
                <div class="text-center py-4 d-none" id="punch-report-empty-state" role="status" aria-live="polite">
                    <i class="ti ti-clipboard-off fs-7 text-muted" aria-hidden="true"></i>
                    <p class="fw-semibold mb-1 mt-2" data-empty-title>Sin marcaciones en el periodo consultado</p>
                    <p class="small text-muted mb-0" data-empty-description>Prueba con otro periodo o ajusta los filtros.</p>
                </div>
                <div class="datatables" id="punch-report-table-wrap">
                    <div class="table-responsive overflow-x-auto pb-4">
                        <table id="checador-punch-table" class="table table-striped table-bordered mb-0 text-nowrap align-middle" width="100%" data-endpoint="/departamento-operativo/recursos-humanos/checador/reporte/data">
                            <caption class="visually-hidden">Listado paginado de marcaciones por localidad, fecha y hora UTC, colaborador, método de captura, origen y estado.</caption>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <div class="modal fade" id="checador-punch-detail-modal" tabindex="-1" aria-labelledby="checador-punch-detail-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary">
                        <div>
                            <h2 class="modal-title h5 text-white mb-1" id="checador-punch-detail-title">Detalle de marcación</h2>
                            <span class="small text-white-50">Información del registro recibido</span>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar detalle"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Identificador del registro</dt><dd class="col-sm-8 text-break"><code id="punch-detail-event-uuid">—</code></dd>
                            <dt class="col-sm-4">Estación</dt><dd class="col-sm-8" id="punch-detail-station">—</dd>
                            <dt class="col-sm-4">Colaborador</dt><dd class="col-sm-8" id="punch-detail-collaborator">—</dd>
                            <dt class="col-sm-4">Dispositivo</dt><dd class="col-sm-8" id="punch-detail-device">—</dd>
                            <dt class="col-sm-4">Método de registro</dt><dd class="col-sm-8" id="punch-detail-method">—</dd>
                            <dt class="col-sm-4">Hora original informada por el dispositivo</dt><dd class="col-sm-8 text-break" id="punch-detail-source-time">—</dd>
                            <dt class="col-sm-4">Zona horaria informada por el dispositivo</dt><dd class="col-sm-8" id="punch-detail-source-zone">—</dd>
                            <dt class="col-sm-4">Fecha y hora de marcación (UTC)</dt><dd class="col-sm-8" id="punch-detail-occurred">—</dd>
                            <dt class="col-sm-4">Fecha y hora de recepción (UTC)</dt><dd class="col-sm-8" id="punch-detail-received">—</dd>
                            <dt class="col-sm-4">Estado de la marcación</dt><dd class="col-sm-8" id="punch-detail-status">—</dd>
                            <dt class="col-sm-4">Integridad</dt><dd class="col-sm-8" id="punch-detail-integrity">—</dd>
                        </dl>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

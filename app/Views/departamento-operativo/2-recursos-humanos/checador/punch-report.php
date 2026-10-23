<?php
$state = (string) ($state ?? 'idle');
$sourceDailyContext = [];
if (($sourceWorkplaceId ?? null) !== null && ($sourceWorkDate ?? '') !== '') {
    $sourceDailyContext = [
        'workplace_id' => (int) $sourceWorkplaceId,
        'date' => (string) $sourceWorkDate,
    ];
    if (($sourceWorkerId ?? null) !== null) {
        $sourceDailyContext['worker_id'] = (int) $sourceWorkerId;
    }
}
$dailyReturnHref = '/departamento-operativo/recursos-humanos/checador'
    . ($sourceDailyContext === [] ? '' : '?' . http_build_query($sourceDailyContext));
$configurationHref = '/departamento-operativo/recursos-humanos/checador/configuracion'
    . (($selectedWorkplaceId ?? null) !== null ? '?' . http_build_query(['workplace_id' => (int) $selectedWorkplaceId]) : '');
$periodYear = (int) ($periodYear ?? gmdate('Y'));
$periodMonth = (int) ($periodMonth ?? gmdate('n'));
$periodQuery = static function (string $period, ?int $workplaceId) use ($periodYear, $periodMonth): string {
    $query = ['period' => $period];
    if ($workplaceId !== null) $query['workplace_id'] = $workplaceId;
    if ($period === 'year') {
        $query['year'] = $periodYear;
        $query['month'] = $periodMonth;
    }
    return '/departamento-operativo/recursos-humanos/checador/reporte?' . http_build_query($query);
};
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
        if (($selectedWorkplaceId ?? null) !== null) $previousQuery['workplace_id'] = (int) $selectedWorkplaceId;
        if (($workerId ?? '') !== '' && ($workerId ?? null) !== null) $previousQuery['worker_id'] = (int) $workerId;
        $previousPeriodHref = '/departamento-operativo/recursos-humanos/checador/reporte?'
            . http_build_query($previousQuery, '', '&', PHP_QUERY_RFC3986);
    }
} catch (\Throwable) {
    $previousPeriodHref = null;
}
?>
<div class="container-fluid mt-4 mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Reporte de marcaciones</h1>
            <p class="text-muted mb-0">Registros originales · consulta de solo lectura</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge rounded-pill text-bg-light border align-self-center">Registros originales<?= !empty($demoMode) ? ' · separados de Demo' : '' ?></span>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($configurationHref, ENT_QUOTES, 'UTF-8') ?>">Configuración</a>
            <a class="btn btn-outline-primary d-inline-flex align-items-center" href="<?= htmlspecialchars($dailyReturnHref, ENT_QUOTES, 'UTF-8') ?>">
                <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>
                <?= $sourceDailyContext === [] ? 'Volver a Checador' : 'Volver a consulta diaria' ?>
            </a>
        </div>
    </div>

    <nav class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Periodos rápidos del reporte UTC">
        <span class="small text-muted me-1">Periodo:</span>
        <?php foreach ([
            'today' => 'Hoy (UTC)',
            'last7' => 'Últimos 7 días',
            'month' => 'Mes actual',
            'year' => 'Año en curso · mensual',
        ] as $preset => $label): ?>
            <?php $active = ($periodPreset ?? '') === $preset; ?>
            <a class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-outline-secondary' ?>"
               href="<?= htmlspecialchars($periodQuery($preset, ($selectedWorkplaceId ?? null) !== null ? (int) $selectedWorkplaceId : null), ENT_QUOTES, 'UTF-8') ?>"
               <?= $active ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
        <span class="small text-muted ms-auto"><?= htmlspecialchars((string) ($periodLabel ?? 'Rango personalizado (UTC)'), ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($previousPeriodHref !== null): ?><a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($previousPeriodHref, ENT_QUOTES, 'UTF-8') ?>">Periodo anterior</a><?php endif; ?>
    </nav>
    <?php if (($periodPreset ?? '') === 'year' && ($yearSegmentLinks ?? []) !== []): ?>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Segmentos mensuales del año en curso">
            <span class="small text-muted">Un mes por consulta; no se calculan totales anuales:</span>
            <?php foreach ($yearSegmentLinks as $segmentLink): ?>
                <?php $segmentActive = (int) ($segmentLink['month'] ?? 0) === (int) ($periodMonth ?? 0); ?>
                <a class="btn btn-sm <?= $segmentActive ? 'btn-primary' : 'btn-outline-secondary' ?>"
                   href="<?= htmlspecialchars((string) $segmentLink['url'], ENT_QUOTES, 'UTF-8') ?>"
                   <?= $segmentActive ? 'aria-current="page"' : '' ?>><?= htmlspecialchars((string) $segmentLink['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="card mb-3" aria-labelledby="punch-report-filter-summary-title">
        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-1" id="punch-report-filter-summary-title">Filtros activos</h2>
                <p class="text-muted mb-0" aria-live="polite">
                    <?php if (($fromDate ?? '') !== '' && ($toDate ?? '') !== ''): ?>
                        UTC: <?= htmlspecialchars((string) $fromDate, ENT_QUOTES, 'UTF-8') ?> – <?= htmlspecialchars((string) $toDate, ENT_QUOTES, 'UTF-8') ?>
                    <?php else: ?>
                        Sin rango de fechas aplicado
                    <?php endif; ?>
                    <?php if (($workerId ?? '') !== '' && ($workerId ?? null) !== null): ?>
                        <span class="mx-1" aria-hidden="true">·</span>Colaborador: <?= htmlspecialchars((string) $workerId, ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#punch-report-filter-modal">
                    <i class="ti ti-filter me-1" aria-hidden="true"></i>Filtros
                </button>
                <?php if (!empty($canDownload)): ?>
                    <form id="punch-report-csv-form" method="get" action="/departamento-operativo/recursos-humanos/checador/reporte" data-checador-csv-form>
                        <input type="hidden" name="workplace_id" value="<?= ($selectedWorkplaceId ?? null) !== null ? (int) $selectedWorkplaceId : '' ?>">
                        <input type="hidden" name="from_date" value="<?= htmlspecialchars((string) ($fromDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="to_date" value="<?= htmlspecialchars((string) ($toDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="worker_id" value="<?= htmlspecialchars((string) ($workerId ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="dt_search" value="<?= htmlspecialchars((string) ($tableSearch ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="dt_order_column" value="<?= (int) ($tableOrderColumn ?? 1) ?>">
                        <input type="hidden" name="dt_order_direction" value="<?= ($sort ?? 'asc') === 'desc' ? 'desc' : 'asc' ?>">
                        <?php if ($sourceDailyContext !== []): ?>
                            <input type="hidden" name="source_workplace_id" value="<?= (int) $sourceWorkplaceId ?>">
                            <input type="hidden" name="source_work_date" value="<?= htmlspecialchars((string) $sourceWorkDate, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (($sourceWorkerId ?? null) !== null): ?>
                                <input type="hidden" name="source_worker_id" value="<?= (int) $sourceWorkerId ?>">
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="btn btn-outline-success d-inline-flex align-items-center" type="submit" name="export" value="csv">
                            <i class="ti ti-download me-1" aria-hidden="true"></i>Descargar CSV
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="modal fade" id="punch-report-filter-modal" tabindex="-1" aria-labelledby="punch-report-filter-modal-title" aria-hidden="true" data-checador-filter-modal>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="punch-report-filter-form" method="get" action="/departamento-operativo/recursos-humanos/checador/reporte" data-checador-filter-form>
                    <div class="modal-header modal-colored-header bg-primary text-white">
                        <h2 class="modal-title h5 text-white mb-0" id="punch-report-filter-modal-title">Filtros de consulta</h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar filtros"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="workplace_id" name="workplace_id" value="<?= ($selectedWorkplaceId ?? null) !== null ? (int) $selectedWorkplaceId : '' ?>">
                        <div class="mb-3">
                            <label class="form-label" for="from_date">Fecha inicial (UTC)</label>
                            <input class="form-control" id="from_date" name="from_date" type="date" value="<?= htmlspecialchars((string) ($fromDate ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="to_date">Fecha final (UTC)</label>
                            <input class="form-control" id="to_date" name="to_date" type="date" value="<?= htmlspecialchars((string) ($toDate ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div>
                            <label class="form-label" for="worker_id">Identificador del colaborador (opcional)</label>
                            <input class="form-control" id="worker_id" name="worker_id" type="number" min="1" step="1" value="<?= htmlspecialchars((string) ($workerId ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" aria-describedby="worker-id-help">
                            <div class="form-text" id="worker-id-help">Solo se buscarán registros con esa identificación confirmada.</div>
                        </div>
                        <input type="hidden" id="dt_search" name="dt_search" value="<?= htmlspecialchars((string) ($tableSearch ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" id="dt_order_column" name="dt_order_column" value="<?= (int) ($tableOrderColumn ?? 1) ?>">
                        <input type="hidden" id="dt_order_direction" name="dt_order_direction" value="<?= ($sort ?? 'asc') === 'desc' ? 'desc' : 'asc' ?>">
                        <?php if ($sourceDailyContext !== []): ?>
                            <input type="hidden" name="source_workplace_id" value="<?= (int) $sourceWorkplaceId ?>">
                            <input type="hidden" name="source_work_date" value="<?= htmlspecialchars((string) $sourceWorkDate, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if (($sourceWorkerId ?? null) !== null): ?>
                                <input type="hidden" name="source_worker_id" value="<?= (int) $sourceWorkerId ?>">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="submit" formnovalidate data-checador-clear-filters>
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

    <div class="alert alert-light border d-flex align-items-start gap-2 mb-4" role="note">
        <i class="ti ti-info-circle text-primary fs-5" aria-hidden="true"></i>
        <div><strong>Consulta de registros originales.</strong> El rango se interpreta en UTC (máximo 31 días). El reporte no convierte la hora del dispositivo ni calcula jornadas; una consulta sin filas no confirma ausencia de marcaciones.</div>
    </div>

    <?php if ($state === 'idle'): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="ti ti-clock text-muted fs-8" aria-hidden="true"></i>
                <p class="text-muted mb-0 mt-2">Selecciona una estación autorizada y un rango de fechas para consultar las marcaciones disponibles.</p>
            </div>
        </div>
    <?php elseif ($state === 'error' || $state === 'unavailable'): ?>
        <div class="alert alert-danger" role="alert">
            <i class="ti ti-alert-circle me-1" aria-hidden="true"></i>
            <?= htmlspecialchars((string) ($stateMessage ?? 'La consulta no está disponible.'), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php elseif ($state === 'success'): ?>
        <section class="card" aria-labelledby="punch-report-results-title">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h2 class="h5 mb-1" id="punch-report-results-title">Marcaciones observadas</h2>
                    <p class="small text-muted mb-0"><?= htmlspecialchars((string) ($selectedWorkplaceName ?: 'Estación no disponible'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <span class="badge rounded-pill text-bg-secondary">Hora en UTC · Integridad no verificada</span>
            </div>
            <div class="card-body">
                <div id="punch-report-ajax-error" class="alert alert-danger d-none" role="alert" aria-live="assertive">
                    La consulta no está disponible; no se interpreta como ausencia de marcaciones. Vuelve a consultar o inicia sesión nuevamente.
                </div>
                <div class="datatables" id="punch-report-table-wrap">
                    <div class="table-responsive overflow-x-auto pb-4">
                        <table id="checador-punch-table" class="table table-striped table-bordered mb-0 text-nowrap align-middle" width="100%" data-endpoint="/departamento-operativo/recursos-humanos/checador/reporte/data">
                            <caption class="visually-hidden">Listado paginado de marcaciones observadas. La consulta completa está disponible mediante los filtros y la paginación de esta pantalla.</caption>
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

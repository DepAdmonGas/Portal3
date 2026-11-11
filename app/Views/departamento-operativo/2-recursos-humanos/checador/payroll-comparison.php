<?php
declare(strict_types=1);

$e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$sourceLabel = static function (string $status): string {
    return match ($status) {
        'available' => 'Información disponible',
        'partial' => 'Información parcial; no se confirma cobertura completa',
        'unavailable', 'service_unavailable' => 'Fuente no disponible',
        'no_reference_found' => 'No se encontró una referencia documental en la consulta',
        'reference_found' => 'Referencia documental localizada',
        'ambiguous' => 'Referencias ambiguas; no se presenta una selección',
        'demo_mode_active' => 'No consultado: el modo demo está activo',
        'not_queried_read_only_contract' => 'No consultado para conservar el alcance de solo lectura',
        'not_queried' => 'No consultado',
        'not_existing_evaluation' => 'No hay una evaluación previa en la respuesta consultada',
        'existing_evaluation' => 'Evaluación administrativa existente',
        'SCHEDULED' => 'Horario definido por el servicio',
        'DAY_OFF' => 'Descanso explícito por el servicio',
        'UNSCHEDULED' => 'Sin asignación según el servicio',
        'UNKNOWN' => 'No determinado por el servicio',
        default => 'Estado no disponible',
    };
};

$recordedStatusLabel = static function (mixed $value): string {
    $status = is_string($value) ? strtoupper(trim($value)) : '';
    return match ($status) {
        'ACTIVE', 'AUTHORIZED', 'APPROVED' => 'Activa o autorizada según la fuente',
        'PENDING_REVIEW', 'REVIEW_REQUIRED' => 'Pendiente de revisión',
        'RESOLVED_PENDING_AUTHORIZATION' => 'Resuelta; pendiente de autorización',
        'REJECTED' => 'Rechazada según la fuente',
        'REVOKED' => 'Revocada según la fuente',
        'CANCELLED' => 'Cancelada según la fuente',
        'INCOMPLETE', 'DATA_INCOMPLETE' => 'Información incompleta',
        'UNKNOWN' => 'No determinado por la fuente',
        'FALTA' => 'Clasificación registrada por la fuente; no interpretada por Portal3',
        '' => 'No disponible',
        default => 'Estado registrado por la fuente; no interpretado por Portal3',
    };
};

$incidenceCategoryLabel = static function (mixed $value): string {
    $category = is_string($value) ? strtoupper(trim($value)) : '';
    return match ($category) {
        'VACATION' => 'Vacaciones',
        'PERMISSION' => 'Permiso',
        'DISABILITY', 'INCAPACITY' => 'Incapacidad',
        'AUTHORIZED_LATENESS' => 'Justificación de retardo',
        'AUTHORIZED_EARLY_DEPARTURE' => 'Justificación de salida anticipada',
        'OFFICIAL_MISSION' => 'Comisión oficial',
        '' => 'Tipo no disponible',
        default => 'Tipo registrado por la fuente',
    };
};

$workplaceId = isset($selectedWorkplaceId) ? (int) $selectedWorkplaceId : null;
$filters = is_array($filters ?? null) ? $filters : [];
$periodsList = is_array($periodsList ?? null) ? $periodsList : [];
$workerSelectOptions = is_array($workerSelectOptions ?? null) ? $workerSelectOptions : [];
$comparisonRows = is_array($comparisonRows ?? null) ? $comparisonRows : [];
$summaryMetrics = is_array($summaryMetrics ?? null) ? $summaryMetrics : [];
$resolvedPeriod = is_array($resolvedPeriod ?? null) ? $resolvedPeriod : null;
$selectedWorkerIdVal = isset($filters['worker_id']) && $filters['worker_id'] !== '' ? (int) $filters['worker_id'] : null;
$selectedYearVal = isset($filters['year']) && $filters['year'] !== '' ? (int) $filters['year'] : (int) date('Y');
$selectedPeriodVal = isset($filters['period_number']) && $filters['period_number'] !== '' ? (int) $filters['period_number'] : 1;
$selectedStatusVal = (string) ($filters['status'] ?? '');
?>

<div id="container" class="mt-4 mb-5">
    <section class="card" aria-labelledby="payroll-comparison-workspace-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2 gap-md-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-scale fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="payroll-comparison-workspace-heading">Comparación con Nómina</h2>
                <span class="small text-white-50">Consulta informativa de resultados de asistencia y referencias documentales de Nómina</span>
            </div>
        </div>

        <div class="card-body">
            <p class="text-muted">Consulta y compara los resultados de asistencia de Checador V2 con los registros documentales de Nómina para la localidad y periodo seleccionados.</p>

            <?php if (!empty($demoMode)): ?>
                <div class="alert alert-warning" role="status">
                    La comparación no consulta ni mezcla fuentes mientras el modo de demostración esté activo. Desactiva el modo demo por el mecanismo local autorizado para consultar fuentes reales.
                </div>
            <?php endif; ?>

            <?php if ($workplaceId === null): ?>
                <div class="alert alert-info" role="status">
                    <i class="ti ti-info-circle me-1" aria-hidden="true"></i>Selecciona una localidad en el selector institucional del encabezado para consultar la comparación.
                </div>
            <?php else: ?>
                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-warning" role="alert"><?= $e($errorMessage) ?></div>
                <?php endif; ?>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#checador-payroll-search-modal" id="open-payroll-search-modal-btn">
                            <i class="ti ti-filter fs-5" aria-hidden="true"></i>
                            <span>Buscar / Filtrar</span>
                        </button>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 small" aria-label="Criterios de consulta activos">
                        <?php if ($resolvedPeriod !== null): ?>
                            <span class="badge rounded-pill bg-light text-dark border py-2 px-3">
                                <i class="ti ti-calendar me-1 text-primary" aria-hidden="true"></i>
                                <?= $e($resolvedPeriod['label']) ?> <?= (int) $resolvedPeriod['number'] ?> (<?= $e($resolvedPeriod['year']) ?>)
                                <span class="text-muted ms-1">· <?= $e($resolvedPeriod['start_date']) ?> al <?= $e($resolvedPeriod['end_date']) ?></span>
                            </span>
                        <?php endif; ?>

                        <?php if ($selectedWorkerIdVal !== null): ?>
                            <?php
                            $workerLabel = 'Colaborador ID ' . $selectedWorkerIdVal;
                            foreach ($workerSelectOptions as $opt) {
                                if ((int) ($opt['worker_id'] ?? 0) === $selectedWorkerIdVal) {
                                    $workerLabel = $opt['full_name'] ?? $workerLabel;
                                    break;
                                }
                            }
                            ?>
                            <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle py-2 px-3">
                                <i class="ti ti-user me-1" aria-hidden="true"></i><?= $e($workerLabel) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($selectedStatusVal !== ''): ?>
                            <?php
                            $statusLabels = [
                                'COINCIDENCIA' => 'Coincidencia verificada',
                                'DIFERENCIA' => 'Diferencia comprobada',
                                'PENDIENTE' => 'Información pendiente',
                                'INSUFICIENTE' => 'Datos insuficientes',
                                'SIN_NOMINA' => 'Sin correspondencia de nómina',
                            ];
                            ?>
                            <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle py-2 px-3">
                                <i class="ti ti-tag me-1" aria-hidden="true"></i><?= $e($statusLabels[$selectedStatusVal] ?? $selectedStatusVal) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Modal Institucional de Búsqueda y Filtros -->
                <div class="modal fade" id="checador-payroll-search-modal" tabindex="-1" aria-labelledby="payroll-search-modal-title" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <form method="get" action="/departamento-operativo/recursos-humanos/checador/comparacion-nomina" class="modal-content" id="payroll-comparison-filter-form" aria-label="Filtros de consulta de comparación con nómina">
                            <input type="hidden" name="period_type" value="<?= $e($periodType ?? 'week') ?>">

                            <div class="modal-header modal-colored-header bg-primary text-white">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-scale fs-5"></i></span>
                                    <div>
                                        <h3 class="modal-title h5 text-white mb-0" id="payroll-search-modal-title">Buscar comparación con Nómina</h3>
                                        <span class="small text-white-50">Selecciona el periodo y los criterios para consultar la información de asistencia y nómina</span>
                                    </div>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>

                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold" for="comparison-year">Año</label>
                                        <select class="form-select" id="comparison-year" name="year">
                                            <?php for ($y = (int) date('Y'); $y >= 2024; $y--): ?>
                                                <option value="<?= $y ?>" <?= $selectedYearVal === $y ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold" for="comparison-period-select">Periodo de nómina (<?= $periodType === 'week' ? 'Semana' : 'Quincena' ?>)</label>
                                        <select class="form-select" id="comparison-period-select" name="period_number">
                                            <?php if ($periodsList === []): ?>
                                                <option value="1">Periodo 1</option>
                                            <?php else: ?>
                                                <?php foreach ($periodsList as $p): ?>
                                                    <option value="<?= (int) ($p['numero'] ?? 1) ?>"
                                                        data-start="<?= $e($p['inicio'] ?? '') ?>"
                                                        data-end="<?= $e($p['fin'] ?? '') ?>"
                                                        <?= $selectedPeriodVal === (int) ($p['numero'] ?? 0) ? 'selected' : '' ?>>
                                                        <?= $e($p['label'] ?? ('Periodo ' . ($p['numero'] ?? ''))) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <div class="p-2 bg-light rounded border text-muted small d-flex align-items-center gap-2" id="comparison-period-dates-box">
                                            <i class="ti ti-calendar text-primary fs-5" aria-hidden="true"></i>
                                            <span>Fechas del periodo: <strong id="comparison-period-dates-text"><?php if ($resolvedPeriod !== null): ?><?= $e($resolvedPeriod['start_date']) ?> al <?= $e($resolvedPeriod['end_date']) ?> (<?= $e($resolvedPeriod['label']) ?> <?= (int) $resolvedPeriod['number'] ?>)<?php else: ?>No disponible<?php endif; ?></strong></span>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <div class="select2-modal-field">
                                            <label class="form-label fw-semibold" for="comparison-worker-filter">Colaborador</label>
                                            <select class="form-select" id="comparison-worker-filter" name="worker_id">
                                                <option value="">Todos los colaboradores</option>
                                                <?php foreach ($workerSelectOptions as $option): if (!is_array($option)) continue; $optionId = (int) ($option['worker_id'] ?? 0); if ($optionId < 1) continue; ?>
                                                    <option value="<?= $optionId ?>" <?= $selectedWorkerIdVal === $optionId ? 'selected' : '' ?>>
                                                        <?= $e($option['full_name'] ?? ('Personal ' . $optionId)) ?><?php if (!empty($option['employee_number'])): ?> · N.º <?= $e($option['employee_number']) ?><?php else: ?> · ID <?= $optionId ?><?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold" for="comparison-status-filter">Estado de comparación</label>
                                        <select class="form-select" id="comparison-status-filter" name="status">
                                            <option value="">Todos los estados</option>
                                            <option value="COINCIDENCIA" <?= $selectedStatusVal === 'COINCIDENCIA' ? 'selected' : '' ?>>Coincidencia verificada</option>
                                            <option value="DIFERENCIA" <?= $selectedStatusVal === 'DIFERENCIA' ? 'selected' : '' ?>>Diferencia comprobada</option>
                                            <option value="PENDIENTE" <?= $selectedStatusVal === 'PENDIENTE' ? 'selected' : '' ?>>Información pendiente</option>
                                            <option value="INSUFICIENTE" <?= $selectedStatusVal === 'INSUFICIENTE' ? 'selected' : '' ?>>Datos insuficientes</option>
                                            <option value="SIN_NOMINA" <?= $selectedStatusVal === 'SIN_NOMINA' ? 'selected' : '' ?>>Sin correspondencia de nómina</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-outline-secondary" id="payroll-reset-filters-btn">
                                    <i class="ti ti-rotate me-1" aria-hidden="true"></i>Limpiar filtros
                                </button>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary incidence-filter-btn" id="payroll-submit-filters-btn">
                                        <i class="ti ti-search me-1" aria-hidden="true"></i>Consultar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($summaryMetrics !== []): ?>
                    <div class="d-flex flex-wrap gap-2 mb-4" aria-label="Resumen de comparación">
                        <span class="badge rounded-pill bg-light text-dark border py-2 px-3">Total consultados: <strong><?= (int) ($summaryMetrics['total_workers'] ?? 0) ?></strong></span>
                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle py-2 px-3">Coincidencias: <strong><?= (int) ($summaryMetrics['matches'] ?? 0) ?></strong></span>
                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle py-2 px-3">Diferencias: <strong><?= (int) ($summaryMetrics['differences'] ?? 0) ?></strong></span>
                        <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle py-2 px-3">Pendientes: <strong><?= (int) ($summaryMetrics['pending'] ?? 0) ?></strong></span>
                        <span class="badge rounded-pill bg-info-subtle text-info border border-info-subtle py-2 px-3">Sin recibo nómina: <strong><?= (int) ($summaryMetrics['no_payroll'] ?? 0) ?></strong></span>
                        <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle py-2 px-3">Datos insuficientes: <strong><?= (int) ($summaryMetrics['insufficient'] ?? 0) ?></strong></span>
                    </div>
                <?php endif; ?>

                <?php if ($comparisonRows === []): ?>
                    <div class="alert alert-secondary mb-0" role="status">
                        <i class="ti ti-info-circle me-1" aria-hidden="true"></i>No se encontraron registros de comparación para los filtros seleccionados. No se interpreta como ausencia ni como periodo completo. Esto no demuestra ausencia de asistencia.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="checador-payroll-table" class="table table-striped table-bordered align-middle mb-0 w-100">
                            <caption class="visually-hidden">Tabla de comparación informativa entre Checador V2 y registros de Nómina</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Colaborador</th>
                                    <th scope="col">Localidad</th>
                                    <th scope="col">Periodo</th>
                                    <th scope="col">Resultado Checador</th>
                                    <th scope="col">Resultado Nómina</th>
                                    <th scope="col">Diferencia verificada</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comparisonRows as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= $e($row['worker_name']) ?></div>
                                            <div class="small text-muted">
                                                <?php if (!empty($row['employee_number'])): ?>
                                                    N.º <?= $e($row['employee_number']) ?> ·
                                                <?php endif; ?>
                                                ID <?= (int) $row['worker_id'] ?>
                                                <?php if (!empty($row['puesto'])): ?>
                                                    · <?= $e($row['puesto']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?= $e($row['workplace_name']) ?></td>
                                        <td>
                                            <div class="small fw-semibold"><?= $e($row['period_label']) ?></div>
                                            <div class="small text-muted"><?= $e($row['period_dates']) ?></div>
                                        </td>
                                        <td>
                                            <span class="small"><?= $e($row['checador_summary']) ?></span>
                                        </td>
                                        <td>
                                            <span class="small"><?= $e($row['payroll_summary']) ?></span>
                                        </td>
                                        <td>
                                            <span class="small"><?= $e($row['difference_summary']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill <?= $e($row['comparison_status_badge']) ?>">
                                                <?= $e($row['comparison_status_label']) ?>
                                            </span>
                                        </td>
                                        <td class="table-actions-cell text-center">
                                            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#payroll-detail-modal-<?= (int) $row['worker_id'] ?>" aria-label="Ver detalle de comparación para <?= $e($row['worker_name']) ?>">
                                                <i class="ti ti-eye me-1" aria-hidden="true"></i>Detalle
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Modales de detalle de comparación (renderizados fuera de la tabla) -->
    <?php foreach ($comparisonRows as $row): ?>
        <div class="modal fade" id="payroll-detail-modal-<?= (int) $row['worker_id'] ?>" tabindex="-1" aria-labelledby="payroll-modal-title-<?= (int) $row['worker_id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <div>
                            <h3 class="modal-title h5 text-white mb-0" id="payroll-modal-title-<?= (int) $row['worker_id'] ?>">Detalle de comparación: <?= $e($row['worker_name']) ?></h3>
                            <span class="small text-white-50"><?= $e($row['workplace_name']) ?> · Periodo <?= $e($row['period_label']) ?> (<?= $e($row['period_dates']) ?>)</span>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <section class="card mb-3" aria-labelledby="modal-identity-heading-<?= (int) $row['worker_id'] ?>">
                            <div class="card-body">
                                <h4 class="h6 mb-2" id="modal-identity-heading-<?= (int) $row['worker_id'] ?>"><i class="ti ti-user me-1 text-primary" aria-hidden="true"></i>Identidad y adscripción</h4>
                                <dl class="row mb-0 small">
                                    <dt class="col-sm-4">Colaborador</dt><dd class="col-sm-8"><?= $e($row['worker_name']) ?></dd>
                                    <dt class="col-sm-4">Número de empleado</dt><dd class="col-sm-8"><?= !empty($row['employee_number']) ? $e($row['employee_number']) : 'No asignado' ?></dd>
                                    <dt class="col-sm-4">ID interno</dt><dd class="col-sm-8"><?= (int) $row['worker_id'] ?></dd>
                                    <dt class="col-sm-4">Puesto registrado</dt><dd class="col-sm-8"><?= !empty($row['puesto']) ? $e($row['puesto']) : 'No especificado' ?></dd>
                                    <dt class="col-sm-4">Localidad</dt><dd class="col-sm-8"><?= $e($row['workplace_name']) ?> (ID <?= (int) $row['workplace_id'] ?>)</dd>
                                    <dt class="col-sm-4">Integridad</dt><dd class="col-sm-8">No verificada por Portal3</dd>
                                </dl>
                            </div>
                        </section>

                        <section class="card mb-3" aria-labelledby="modal-payroll-heading-<?= (int) $row['worker_id'] ?>">
                            <div class="card-body">
                                <h4 class="h6 mb-2" id="modal-payroll-heading-<?= (int) $row['worker_id'] ?>"><i class="ti ti-file-text me-1 text-primary" aria-hidden="true"></i>Referencias de Nómina</h4>
                                <?php if (!empty($row['has_payroll_record']) && is_array($row['payroll_record'])): $pr = $row['payroll_record']; ?>
                                    <dl class="row mb-2 small">
                                        <dt class="col-sm-5">Registro documental interno</dt><dd class="col-sm-7">ID <?= (int) ($pr['record_id'] ?? 0) ?></dd>
                                        <dt class="col-sm-5">Periodo nómina</dt><dd class="col-sm-7"><?= (int) ($pr['period_year'] ?? 0) ?> · <?= $e($pr['period_type'] ?? '') ?> <?= (int) ($pr['period_number'] ?? 0) ?> · Mes <?= (int) ($pr['period_month'] ?? 0) ?></dd>
                                        <dt class="col-sm-5">Documentos registrados</dt>
                                        <dd class="col-sm-7">
                                            <?= !empty($pr['has_doc_nomina']) ? '<span class="badge bg-success-subtle text-success">Nómina: sí</span>' : '<span class="badge bg-secondary-subtle text-secondary">Nómina: no</span>' ?>
                                            <?= !empty($pr['has_firma']) ? '<span class="badge bg-success-subtle text-success">Firma: sí</span>' : '<span class="badge bg-secondary-subtle text-secondary">Firma: no</span>' ?>
                                            <?= !empty($pr['has_aguinaldo']) ? '<span class="badge bg-info-subtle text-info">Aguinaldo: sí</span>' : '' ?>
                                            <?= !empty($pr['is_original']) ? '<span class="badge bg-light text-dark border">Original</span>' : '' ?>
                                        </dd>
                                    </dl>
                                <?php else: ?>
                                    <p class="small text-muted mb-0">No se encontró una fila documental para esta combinación consultada. Esto no demuestra ausencia de asistencia ni permite inferir faltas, descuentos o consecuencias de nómina.</p>
                                <?php endif; ?>
                                <p class="small text-muted mb-0 mt-2 border-top pt-2"><i class="ti ti-lock me-1" aria-hidden="true"></i>Solo lectura de referencias documentales. Por protección de datos y contratos congelados, no se exponen importes salariales, deducciones ni cuentas bancarias.</p>
                            </div>
                        </section>

                        <section class="card mb-3" aria-labelledby="modal-checador-heading-<?= (int) $row['worker_id'] ?>">
                            <div class="card-body">
                                <h4 class="h6 mb-2" id="modal-checador-heading-<?= (int) $row['worker_id'] ?>"><i class="ti ti-fingerprint me-1 text-primary" aria-hidden="true"></i>Asistencia observada en Checador V2</h4>
                                <?php if (!empty($row['raw_punches']) && is_array($row['raw_punches'])): ?>
                                    <p class="small text-muted mb-2">Se observaron <?= count($row['raw_punches']) ?> marcaciones crudas durante el periodo:</p>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered small mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Fecha/Hora UTC</th>
                                                    <th>Dispositivo</th>
                                                    <th>Método</th>
                                                    <th>UUID</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($row['raw_punches'] as $event): ?>
                                                    <tr>
                                                        <td><?= $e($event['ocurrido_at_utc'] ?? '') ?></td>
                                                        <td><?= isset($event['id_dispositivo']) ? 'Disp. ' . (int) $event['id_dispositivo'] : '—' ?></td>
                                                        <td><?= $e(\App\Services\ChecadorPresentationFormatter::captureMethodLabel($event['metodo_captura'] ?? null)) ?></td>
                                                        <td><code><?= $e(substr((string) ($event['event_uuid'] ?? ''), 0, 8)) ?>…</code></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="small text-muted mb-0">No se observaron marcaciones crudas registradas para este trabajador en el periodo seleccionado.</p>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="card mb-3" aria-labelledby="modal-schedule-heading-<?= (int) $row['worker_id'] ?>">
                            <div class="card-body">
                                <h4 class="h6 mb-2" id="modal-schedule-heading-<?= (int) $row['worker_id'] ?>"><i class="ti ti-calendar-time me-1 text-primary" aria-hidden="true"></i>Horarios y Programación de Turnos</h4>
                                <?php if (!empty($row['shifts']) && is_array($row['shifts'])): ?>
                                    <ul class="small mb-0">
                                        <?php foreach ($row['shifts'] as $shift): ?>
                                            <li>
                                                <strong><?= $e($shift['dia_semana'] ?? '') ?>:</strong> <?= $e($shift['plantilla'] ?? '') ?> (<?= $e($shift['tipo_asignacion'] ?? '') ?>)
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="small text-muted mb-0">No hay turnos programados asignados directamente en el periodo consultado.</p>
                                <?php endif; ?>
                                <?php if (!empty($comparison['schedules']['days'])): ?>
                                    <?php foreach ($comparison['schedules']['days'] as $day): ?>
                                        <?php if (!empty($day['segments'])): ?>
                                            <?php foreach ($day['segments'] as $segment): ?>
                                                <?php $offsetTmp = (int) ($segment['end_day_offset'] ?? 0); ?>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="card mb-3" aria-labelledby="modal-incidences-heading-<?= (int) $row['worker_id'] ?>">
                            <div class="card-body">
                                <h4 class="h6 mb-2" id="modal-incidences-heading-<?= (int) $row['worker_id'] ?>"><i class="ti ti-alert-triangle me-1 text-primary" aria-hidden="true"></i>Incidencias y Evaluaciones</h4>
                                <?php if (!empty($row['incidences']) && is_array($row['incidences'])): ?>
                                    <ul class="small mb-0">
                                        <?php foreach ($row['incidences'] as $inc): ?>
                                            <li>
                                                <strong><?= $e($inc['type_label'] ?? $inc['type'] ?? '') ?>:</strong>
                                                <?= $e($inc['start_date'] ?? '') ?> a <?= $e($inc['end_date'] ?? '') ?> ·
                                                Estado: <?= $e($recordedStatusLabel($inc['status'] ?? null)) ?> ·
                                                <code><?= $e(substr((string) ($inc['uuid'] ?? ''), 0, 8)) ?>…</code>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="small text-muted mb-0">No hay incidencias registradas en el periodo para este trabajador.</p>
                                <?php endif; ?>
                                <p class="small text-muted mb-0 mt-2">No se ejecutó una evaluación de jornada en esta consulta para preservar el alcance de solo lectura.</p>
                            </div>
                        </section>

                        <section class="card border-primary" aria-labelledby="modal-assessment-heading-<?= (int) $row['worker_id'] ?>">
                            <div class="card-body bg-light">
                                <h4 class="h6 mb-2" id="modal-assessment-heading-<?= (int) $row['worker_id'] ?>"><i class="ti ti-info-circle me-1 text-primary" aria-hidden="true"></i>Dictamen de Comparación</h4>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge rounded-pill <?= $e($row['comparison_status_badge']) ?> fs-7">
                                        <?= $e($row['comparison_status_label']) ?>
                                    </span>
                                </div>
                                <p class="small mb-1"><strong>Diferencia verificada:</strong> <?= $e($row['difference_summary']) ?></p>
                                <p class="small text-muted mb-0">Esta consulta es informativa y de solo lectura. No modifica marcaciones, evaluaciones ni recibos, no cierra periodos y no establece consecuencias económicas.</p>
                            </div>
                        </section>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
declare(strict_types=1);

$e = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$sourceLabel = static function (string $status): string {
    return match ($status) {
        'available' => 'Información disponible',
        'partial' => 'Información parcial; no se confirma cobertura completa',
        'unavailable', 'service_unavailable' => 'Fuente no disponible',
        'contract_pending' => 'Lectura SQL de esta comparación pendiente de autorización contractual',
        'permission_denied' => 'Consulta no autorizada',
        'sql_read_failed', 'database_unavailable', 'schema_unavailable' => 'Error al consultar la fuente SQL',
        'result_limit_exceeded' => 'La consulta excede el límite seguro de resultados',
        'no_reference_found' => 'No se encontró una referencia documental en la consulta',
        'reference_found' => 'Referencia documental localizada',
        'ambiguous' => 'Referencias ambiguas; no se presenta una selección',
        'demo_mode_active' => 'Fuente no disponible para esta consulta',
        'not_queried' => 'No consultado',
        'no_persisted_journeys' => 'Sin jornadas persistidas para los filtros consultados',
        'no_persisted_journey_snapshot' => 'Sin jornada persistida; no se resolvió un horario para esta fecha',
        'ambiguous_journey_snapshot' => 'Varias jornadas persistidas para esta fecha; no se seleccionó un horario',
        'persisted_journey_snapshot' => 'Snapshot persistido de Jornada C01',
        'partial_persisted_journey_snapshots' => 'Snapshots C01 parciales; no se resolvió el resto del periodo',
        'schema_unavailable' => 'Proyección de jornadas no disponible',
        'persisted_data_inconsistent' => 'Datos persistidos inconsistentes; no se presentan como confirmados',
        'permission_denied' => 'Consulta no autorizada',
        'sql_unavailable' => 'Lectura SQL no disponible',
        'not_existing_evaluation' => 'No hay una evaluación persistida para los filtros consultados',
        'existing_evaluation' => 'Evaluación administrativa persistida',
        'CALCULATED' => 'Cálculo persistido',
        'REVIEW_REQUIRED' => 'Requiere revisión',
        'INCOMPLETE' => 'Resultado incompleto',
        'DATA_INCOMPLETE' => 'Resultado incompleto',
        'RESULT_NOT_AVAILABLE' => 'Resultado no disponible',
        'EXACT_PAIR' => 'Pareo exacto persistido',
        'AMBIGUOUS_PAIRING' => 'Pareo requiere revisión',
        'UNSCHEDULED_DAY' => 'Jornada sin horario informado',
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
$adjustmentTypeLabel = static function (mixed $value): string {
    $type = is_string($value) ? strtoupper(trim($value)) : '';
    return match ($type) {
        'JUSTIFIED_LATENESS' => 'Retardo justificado',
        'JUSTIFIED_EARLY_DEPARTURE' => 'Salida anticipada justificada',
        'AUTHORIZED_ABSENCE' => 'Ausencia autorizada según la evaluación',
        'MANUAL_ADJUSTMENT' => 'Ajuste administrativo registrado',
        '' => 'Tipo no disponible',
        default => 'Tipo de ajuste registrado por la fuente',
    };
};
$comparison = is_array($comparison ?? null) ? $comparison : null;
$workplaceId = isset($selectedWorkplaceId) ? (int) $selectedWorkplaceId : null;
$filters = is_array($filters ?? null) ? $filters : [];
?>

<div id="container" class="mt-4 mb-5">
    <section class="card" aria-labelledby="payroll-comparison-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-report-money fs-5"></i></span>
            <h1 class="h5 mb-0 text-white" id="payroll-comparison-heading">Comparación con Nómina</h1>
            <div class="dropdown ms-auto">
                <button type="button" class="btn btn-light dropdown-toggle text-dark" id="payroll-comparison-options" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Opciones de comparación"><i class="ti ti-dots-vertical fs-4" aria-hidden="true"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="payroll-comparison-options">
                    <li><button type="button" class="dropdown-item pointer" data-bs-toggle="modal" data-bs-target="#payroll-comparison-search" <?= empty($selectedWorkplaceId) ? 'disabled' : '' ?>><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar comparación</button></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <?php if (($selectedWorkplaceId ?? null) !== null): ?>
                <p class="small text-muted mb-3">Localidad: <?= $e($selectedWorkplaceName !== '' ? $selectedWorkplaceName : 'Localidad autorizada') ?> · ID canónico <?= (int) $selectedWorkplaceId ?>. La comparación es informativa y de solo lectura.</p>
            <?php endif; ?>

    <?php if (!empty($demoMode)): ?>
        <div class="alert alert-warning" role="status">La consulta no está disponible en este contexto.</div>
    <?php endif; ?>

    <?php if (($selectedWorkplaceId ?? null) === null): ?>
        <div class="alert alert-info" role="status">Selecciona una localidad en el selector institucional para continuar. Debe estar autorizada tanto para Checador como para Nómina.</div>
    <?php else: ?>
        <?php if (($workerOptionsMessage ?? '') !== ''): ?>
            <p class="small text-muted" role="status" data-payroll-roster-message><?= $e($workerOptionsMessage) ?></p>
        <?php endif; ?>
        <?php if (($errorMessage ?? '') !== ''): ?><div class="alert alert-warning" role="alert"><?= $e($errorMessage) ?></div><?php endif; ?>
        <?php if (empty($rosterOnly) && $comparison === null && empty($demoMode) && ($errorMessage ?? '') === ''): ?><p class="text-body-secondary mb-0" role="status">Usa «Buscar comparación» para seleccionar trabajador y periodo.</p><?php endif; ?>
    <?php endif; ?>

    <?php if ($comparison !== null): ?>
        <?php
        $worker = $comparison['worker'] ?? [];
        $period = $comparison['period'] ?? [];
        $attendance = $comparison['attendance'] ?? [];
        $payroll = $comparison['payroll'] ?? [];
        ?>
        <div class="alert alert-info" role="status">
            Consulta informativa y de solo lectura. No modifica marcaciones, evaluaciones ni recibos, no cierra periodos y no establece consecuencias económicas.
            Las fuentes pueden tener coberturas distintas; su ausencia o indisponibilidad no establece que el periodo o el padrón estén completos.
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <section class="card h-100" aria-labelledby="attendance-evidence-heading">
                    <div class="card-body">
                        <h2 class="h5" id="attendance-evidence-heading">Asistencia Checador V2</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Trabajador</dt><dd class="col-sm-8"><?= $e($worker['name'] ?? '') ?> · ID interno <?= (int) ($worker['id'] ?? 0) ?></dd>
                            <dt class="col-sm-4">Número de empleado</dt><dd class="col-sm-8"><?= $e($worker['employee_number'] ?? 'No disponible') ?></dd>
                            <dt class="col-sm-4">Localidad</dt><dd class="col-sm-8"><?= $e($comparison['workplace']['nombre'] ?? '') ?> · <?= (int) ($worker['workplace_id'] ?? 0) ?></dd>
                            <dt class="col-sm-4">Periodo</dt><dd class="col-sm-8"><?= $e($period['label'] ?? '') ?> <?= (int) ($period['number'] ?? 0) ?> · <?= (int) ($period['year'] ?? 0) ?></dd>
                            <dt class="col-sm-4">Fechas</dt><dd class="col-sm-8"><?= $e($period['start_date'] ?? '') ?> a <?= $e($period['end_date'] ?? '') ?></dd>
                            <dt class="col-sm-4">Fuente</dt><dd class="col-sm-8"><?= $e($sourceLabel((string) ($attendance['status'] ?? 'unavailable'))) ?></dd>
                            <dt class="col-sm-4">Integridad</dt><dd class="col-sm-8">No verificada por Portal3</dd>
                            <dt class="col-sm-4">Completitud</dt><dd class="col-sm-8">No establecida</dd>
                        </dl>
                        <?php if (($attendance['message'] ?? '') !== ''): ?><p class="small text-muted mt-2 mb-0"><?= $e($attendance['message']) ?></p><?php endif; ?>
                    </div>
                </section>
            </div>
            <div class="col-12 col-lg-6">
                <section class="card h-100" aria-labelledby="payroll-reference-heading">
                    <div class="card-body">
                        <h2 class="h5" id="payroll-reference-heading">Referencias de Nómina</h2>
                        <p class="mb-2"><?= $e($sourceLabel((string) ($payroll['status'] ?? 'unavailable'))) ?></p>
                        <?php if (($payroll['status'] ?? '') === 'reference_found'): ?>
                            <?php foreach (($payroll['references'] ?? []) as $reference): ?>
                                <dl class="row small mb-0">
                                    <dt class="col-sm-5">Referencia documental interna</dt><dd class="col-sm-7">Registro <?= (int) ($reference['record_id'] ?? 0) ?></dd>
                                    <dt class="col-sm-5">Clave de periodo</dt><dd class="col-sm-7"><?= (int) ($reference['period_year'] ?? 0) ?> · <?= $e($reference['period_type'] ?? '') ?> <?= (int) ($reference['period_number'] ?? 0) ?> · mes <?= (int) ($reference['period_month'] ?? 0) ?></dd>
                                    <dt class="col-sm-5">Documentos registrados</dt>
                                    <dd class="col-sm-7">
                                        <?= !empty($reference['documents']['payroll_document']) ? 'Nómina: sí' : 'Nómina: no registrada' ?> ·
                                        <?= !empty($reference['documents']['signature_document']) ? 'Firma: sí' : 'Firma: no registrada' ?> ·
                                        <?= !empty($reference['documents']['bonus_document']) ? 'Aguinaldo: sí' : 'Aguinaldo: no registrada' ?> ·
                                        <?= !empty($reference['documents']['original_reference']) ? 'Referencia original: sí' : 'Referencia original: no registrada' ?>
                                    </dd>
                                </dl>
                            <?php endforeach; ?>
                        <?php elseif (($payroll['status'] ?? '') === 'no_reference_found'): ?>
                            <p class="small text-muted mb-0">No se encontró una fila documental para esta combinación consultada. Esto no demuestra ausencia de asistencia ni permite inferir faltas, descuentos o consecuencias de nómina.</p>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <section class="card mb-4" aria-labelledby="raw-events-heading">
            <div class="card-body">
                <h2 class="h5" id="raw-events-heading">Marcaciones observadas</h2>
                <p class="small text-muted">Estado: <?= $e($sourceLabel((string) ($attendance['status'] ?? 'unavailable'))) ?>.<?php if (($attendance['status'] ?? '') === 'available' || ($attendance['status'] ?? '') === 'partial'): ?> Eventos observados consultados en vivo; no equivalen a un snapshot ni prueban que el periodo esté completo.<?php endif; ?></p>
                <?php if (($attendance['message'] ?? '') !== ''): ?><p class="small text-muted"><?= $e($attendance['message']) ?></p><?php endif; ?>
                <?php if (($attendance['events'] ?? []) === []): ?>
                    <div class="alert <?= ($attendance['status'] ?? '') === 'available' ? 'alert-secondary' : 'alert-warning' ?> mb-0" role="status">
                        <?php if (($attendance['status'] ?? '') === 'available'): ?>No se encontraron marcaciones observadas en la consulta disponible. Esto no confirma ausencia ni que el periodo esté completo.
                        <?php elseif (($attendance['status'] ?? '') === 'partial'): ?>La lectura de marcaciones quedó incompleta; no se presenta como periodo completo.
                        <?php else: ?>La lectura de marcaciones no está disponible; no se interpreta como ausencia laboral.<?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0" data-payroll-comparison-table>
                            <caption class="visually-hidden">Marcaciones V2 observadas para el trabajador y periodo seleccionado</caption>
                            <thead><tr><th scope="col">Día local consultado</th><th scope="col">Fecha y hora ocurrida (UTC)</th><th scope="col">Recepción (UTC)</th><th scope="col">Dispositivo</th><th scope="col">Método</th><th scope="col">Estado recibido</th><th scope="col">Evento</th></tr></thead>
                            <tbody>
                            <?php foreach ($attendance['events'] as $event): ?>
                                <tr>
                                    <td><?= $e($event['query_date_local'] ?? '') ?></td>
                                    <td><?= $e($event['ocurrido_at_utc'] ?? 'No determinada') ?></td>
                                    <td><?= $e($event['recibido_at_utc'] ?? 'No disponible') ?></td>
                                    <td><?= isset($event['id_dispositivo']) ? 'Dispositivo ' . (int) $event['id_dispositivo'] : 'No disponible' ?></td>
                                    <td><?= $e(\App\Services\ChecadorPresentationFormatter::captureMethodLabel($event['metodo_captura'] ?? null)) ?></td>
                                    <td><?= $e(\App\Services\ChecadorPresentationFormatter::processingStatusLabel($event['processing_status'] ?? null)) ?></td>
                                    <td><code><?= $e($event['event_uuid'] ?? '') ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="card mb-4" aria-labelledby="schedule-heading">
            <div class="card-body">
                <h2 class="h5" id="schedule-heading">Horario histórico aplicable</h2>
                <p class="small text-muted">Estado de la fuente: <?= $e($sourceLabel((string) ($comparison['schedules']['status'] ?? 'unavailable'))) ?>. Sólo se presenta el snapshot incluido en una Jornada C01 persistida; no se resuelve ni calcula un horario nuevo.</p>
                <p class="small text-muted">Zona horaria laboral: <?= $e($sourceLabel((string) ($comparison['workplace_timezones']['status'] ?? 'unavailable'))) ?>.</p>
                <?php if (($comparison['workplace_timezones']['days'] ?? []) !== []): ?>
                    <ul class="small">
                        <?php foreach ($comparison['workplace_timezones']['days'] as $timezoneDay): ?>
                            <li><?= $e($timezoneDay['date'] ?? '') ?> · <?= $e($timezoneDay['timezone'] ?? 'No disponible') ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (($comparison['schedules']['days'] ?? []) !== []): ?>
                    <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0" data-payroll-comparison-table>
                        <thead><tr><th scope="col">Fecha laboral</th><th scope="col">Resultado</th><th scope="col">Zona horaria</th><th scope="col">Snapshot de segmentos</th></tr></thead><tbody>
                        <?php foreach ($comparison['schedules']['days'] as $day): ?>
                            <tr>
                                <td><?= $e($day['date'] ?? '') ?></td>
                                <td><?= $e($sourceLabel((string) ($day['status'] ?? 'unavailable'))) ?></td>
                                <td><?= $e($day['timezone'] ?? 'No disponible') ?></td>
                                <td>
                                    <?php if (is_string($day['segments_snapshot_json'] ?? null) && trim($day['segments_snapshot_json']) !== ''): ?><code><?= $e($day['segments_snapshot_json']) ?></code>
                                    <?php elseif (($day['segments'] ?? []) === []): ?>No disponible en el snapshot persistido<?php else: ?>
                                        <?php foreach ($day['segments'] as $segment): ?>
                                            <?= (int) ($segment['ordinal'] ?? 0) ?>. <?= $e($segment['type'] ?? '') ?> <?= $e($segment['start_local'] ?? '') ?>–<?= $e($segment['end_local'] ?? '') ?><?php if ((int) ($segment['end_day_offset'] ?? 0) > 0): ?> (+<?= (int) $segment['end_day_offset'] ?> día)<?php endif; ?><br>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table></div>
                <?php else: ?><p class="mb-0 text-muted">No hay datos de horario consultables en esta respuesta.</p><?php endif; ?>
            </div>
        </section>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <section class="card h-100" aria-labelledby="incidences-heading">
                    <div class="card-body">
                        <h2 class="h5" id="incidences-heading">Incidencias registradas</h2>
                        <p class="small text-muted">Estado: <?= $e($sourceLabel((string) ($comparison['incidences']['status'] ?? 'unavailable'))) ?>.</p>
                        <?php if (($comparison['incidences']['items'] ?? []) === []): ?>
                            <p class="mb-0"><?php if (($comparison['incidences']['status'] ?? '') === 'available'): ?>No hay incidencias en el resultado consultado; esto no confirma ausencia fuera de esa respuesta.<?php else: ?>La consulta de incidencias no está disponible.<?php endif; ?></p>
                        <?php else: ?><ul class="mb-0">
                            <?php foreach ($comparison['incidences']['items'] as $item): ?>
                                <li><?= $e($incidenceCategoryLabel($item['category'] ?? null)) ?> · <?= $e($item['start_date'] ?? '') ?> a <?= $e($item['end_date'] ?? '') ?> · <?= $e($recordedStatusLabel($item['status'] ?? null)) ?> · revisión <?= (int) ($item['revision'] ?? 0) ?> · <code><?= $e($item['uuid'] ?? '') ?></code></li>
                            <?php endforeach; ?>
                        </ul><?php endif; ?>
                    </div>
                </section>
            </div>
            <div class="col-12 col-lg-6">
                <section class="card h-100" aria-labelledby="evaluation-heading">
                    <div class="card-body">
                        <h2 class="h5" id="evaluation-heading">Evaluaciones y ajustes administrativos vinculados</h2>
                <p class="small text-muted">Se presentan únicamente evaluaciones y ajustes persistidos con vínculos verificables; Portal3 no ejecuta evaluaciones ni modifica sus estados.</p>
                        <p class="small">Estado de la fuente: <?= $e($sourceLabel((string) ($comparison['evaluations']['status'] ?? 'unavailable'))) ?>.</p>
                        <?php if (($comparison['evaluations']['days'] ?? []) !== []): ?><ul class="small mb-2">
                            <?php foreach ($comparison['evaluations']['days'] as $day): ?>
                                <li><?= $e($day['date'] ?? '') ?> · <?= $e($sourceLabel((string) ($day['status'] ?? 'unavailable'))) ?><?php if (isset($day['revision'])): ?> · revisión <?= (int) $day['revision'] ?><?php endif; ?><?php if (($day['administrative_status'] ?? '') !== ''): ?> · estado administrativo <?= $e($recordedStatusLabel($day['administrative_status'])) ?><?php endif; ?><?php if (($day['human_review_status'] ?? '') !== ''): ?> · revisión humana <?= $e($recordedStatusLabel($day['human_review_status'])) ?><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul><?php endif; ?>
                        <p class="small mb-1">Ajustes vinculados: <?= $e($sourceLabel((string) ($comparison['adjustments']['status'] ?? 'unavailable'))) ?>.</p>
                        <?php if (($comparison['adjustments']['status'] ?? '') === 'contract_pending'): ?><p class="small text-muted mb-0">No se consultaron ajustes relacionados; el alcance SQL de esta comparación está pendiente de autorización.</p>
                        <?php elseif (($comparison['adjustments']['items'] ?? []) !== []): ?><ul class="small mb-0">
                            <?php foreach ($comparison['adjustments']['items'] as $adjustment): ?><li><code><?= $e($adjustment['uuid'] ?? '') ?></code> · <?= $e($adjustmentTypeLabel($adjustment['type'] ?? null)) ?> · <?= $e($recordedStatusLabel($adjustment['status'] ?? null)) ?></li><?php endforeach; ?>
                        </ul><?php else: ?><p class="small text-muted mb-0">No hay referencias de ajustes en las evaluaciones consultadas; no se infiere inexistencia fuera de esa respuesta.</p><?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <section class="card mb-4" aria-labelledby="journey-readonly-heading">
            <div class="card-body">
                <h2 class="h5" id="journey-readonly-heading">Jornadas persistidas</h2>
                <?php $journeys = is_array($comparison['journeys']['journeys'] ?? null) ? $comparison['journeys']['journeys'] : []; ?>
                <?php if ($journeys === []): ?>
                    <p class="mb-0" role="status"><?= $e($comparison['journeys']['message'] ?? 'No hay jornadas persistidas disponibles para esta consulta.') ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0" data-payroll-comparison-table>
                            <caption class="visually-hidden">Jornadas persistidas de la persona y periodo seleccionados</caption>
                            <thead><tr><th scope="col">Fecha laboral</th><th scope="col">Horario</th><th scope="col">Estado persistido</th><th scope="col">Tiempo informado</th><th scope="col">Revisiones</th><th scope="col">Trazabilidad</th></tr></thead>
                            <tbody>
                            <?php foreach ($journeys as $journey): if (!is_array($journey)) continue;
                                $context = is_array($journey['context'] ?? null) ? $journey['context'] : [];
                                $schedule = is_array($journey['schedule'] ?? null) ? $journey['schedule'] : [];
                                $calculation = is_array($journey['calculation'] ?? null) ? $journey['calculation'] : [];
                                $pairing = is_array($journey['pairing'] ?? null) ? $journey['pairing'] : [];
                                $seconds = ($calculation['estado_calculo'] ?? '') === 'CALCULATED' && is_numeric($calculation['segundos_trabajados_totales'] ?? null)
                                    ? (int) $calculation['segundos_trabajados_totales'] : null;
                                $duration = $seconds === null ? 'No disponible' : sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
                                $startUtc = trim((string) ($schedule['scheduled_start_utc'] ?? ''));
                                $endUtc = trim((string) ($schedule['scheduled_end_utc'] ?? ''));
                                $scheduleText = $startUtc !== '' && $endUtc !== '' ? $startUtc . ' – ' . $endUtc . ' UTC' : (string) ($schedule['estado_jornada'] ?? 'No disponible');
                                $provenance = is_array($journey['provenance'] ?? null) ? $journey['provenance'] : [];
                            ?>
                                <tr>
                                    <td><?= $e($context['fecha_local'] ?? 'No disponible') ?></td>
                                    <td><?= $e($scheduleText) ?></td>
                                    <td><span class="badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 bg-info-subtle border border-info-subtle text-body-emphasis"><i class="ti ti-info-circle" aria-hidden="true"></i><?= $e($sourceLabel((string) ($journey['journey_status'] ?? 'unavailable'))) ?></span></td>
                                    <td><?= $e($duration) ?></td>
                                    <td>P1 <?= $e($provenance['p1_revision'] ?? '—') ?> · P2 <?= $e($provenance['p2_revision'] ?? '—') ?></td>
                                    <td>L2 <?= $e($pairing['estado_completitud_l2'] ?? 'No disponible') ?> · <?= $e($provenance['p2_created_at_utc'] ?? $provenance['p1_created_at_utc'] ?? 'Fecha no disponible') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-2 mb-0">Estados, tiempos y revisiones se presentan como persistidos por la fuente; Portal3 no recalcula ni verifica completitud L2.</p>
                <?php endif; ?>
            </div>
        </section>

        <p class="small text-muted mb-0"><?= $e($comparison['comparison_limit'] ?? '') ?></p>
    <?php endif; ?>
        </div>
    </section>

    <?php if (($selectedWorkplaceId ?? null) !== null): ?>
    <div class="modal fade" id="payroll-comparison-search" tabindex="-1" aria-labelledby="payroll-comparison-search-title" aria-hidden="true" data-payroll-select2-modal>
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h2 class="modal-title fs-5 text-white" id="payroll-comparison-search-title"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar comparación</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form method="get" action="/departamento-operativo/recursos-humanos/checador/comparacion-nomina" data-payroll-comparison-form>
                    <input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>">
                    <input type="hidden" name="period_type" value="<?= $e($periodType ?? 'fortnight') ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="comparison-worker">Trabajador</label>
                                <div class="select2-modal-field w-100">
                                    <select class="form-select w-100" id="comparison-worker" name="worker_id" data-payroll-worker-select required>
                                        <option value="">Selecciona un trabajador</option>
                                        <?php foreach (is_array($workerOptions ?? null) ? $workerOptions : [] as $option): if (!is_array($option)) continue;
                                            $optionId = (int) ($option['worker_id'] ?? 0);
                                            $optionName = trim((string) ($option['full_name'] ?? ''));
                                            $employeeNumber = trim((string) ($option['employee_number'] ?? ''));
                                            if ($optionId < 1 || $optionName === '') continue;
                                            $optionLabel = $optionName . ($employeeNumber !== '' ? ' · ' . $employeeNumber : '');
                                        ?>
                                            <option value="<?= $optionId ?>" <?= (string) ($filters['worker_id'] ?? '') === (string) $optionId ? 'selected' : '' ?>><?= $e($optionLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="comparison-year">Año</label>
                                <input class="form-control" id="comparison-year" name="year" type="number" min="2024" max="<?= (int) date('Y') ?>" required value="<?= $e($filters['year'] ?? date('Y')) ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="comparison-period">Número de <?= $periodType === 'week' ? 'semana' : 'quincena' ?></label>
                                <input class="form-control" id="comparison-period" name="period_number" type="number" min="1" <?= $periodType === 'fortnight' ? 'max="24"' : '' ?> required value="<?= $e($filters['period_number'] ?? '') ?>">
                                <div class="form-text">Periodo <?= $periodType === 'week' ? 'semanal' : 'quincenal' ?> según la regla vigente de esta localidad.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <button type="reset" class="btn btn-outline-secondary"><i class="ti ti-eraser me-1" aria-hidden="true"></i>Limpiar</button>
                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <button type="submit" name="load_roster" value="1" formnovalidate class="btn btn-outline-primary"><i class="ti ti-users me-1" aria-hidden="true"></i>Cargar personal del periodo</button>
                            <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button>
                            <button type="submit" name="compare" value="1" class="btn btn-primary"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

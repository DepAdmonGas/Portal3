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
$returnQuery = $workplaceId !== null ? ['workplace_id' => $workplaceId] : [];
$returnHref = '/departamento-operativo/recursos-humanos/checador'
    . ($returnQuery === [] ? '' : '?' . http_build_query($returnQuery));
?>

<div id="container" class="mt-4 mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">Consulta informativa de evidencias históricas de asistencia y referencias documentales de Nómina.</p>
        <a class="btn btn-outline-secondary" href="<?= $e($returnHref) ?>">Volver a Checador</a>
    </div>

    <?php if (!empty($demoMode)): ?>
        <div class="alert alert-warning" role="status">
            La comparación no consulta ni mezcla fuentes mientras el modo de demostración esté activo. Desactiva el modo demo por el mecanismo local autorizado para consultar fuentes reales.
        </div>
    <?php endif; ?>

    <?php if (($selectedWorkplaceId ?? null) === null): ?>
        <div class="alert alert-info" role="status">Selecciona una localidad en el selector institucional para continuar. Debe estar autorizada tanto para Checador como para Nómina.</div>
    <?php else: ?>
        <section class="card mb-4" aria-labelledby="comparison-filter-heading">
            <div class="card-body">
                <h2 class="h5" id="comparison-filter-heading">Consultar un trabajador y periodo</h2>
                <p class="small text-muted">Localidad: <?= $e($selectedWorkplaceName !== '' ? $selectedWorkplaceName : 'Localidad autorizada') ?> · ID canónico <?= (int) $selectedWorkplaceId ?>. El selector de estación/localidad se conserva en el encabezado de Portal3.</p>
                <?php if (($errorMessage ?? '') !== ''): ?>
                    <div class="alert alert-warning" role="alert"><?= $e($errorMessage) ?></div>
                <?php endif; ?>
                <form method="get" action="/departamento-operativo/recursos-humanos/checador/comparacion-nomina" class="row g-3">
                    <input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>">
                    <input type="hidden" name="period_type" value="<?= $e($periodType ?? 'fortnight') ?>">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="comparison-worker">Trabajador canónico</label>
                        <input class="form-control" id="comparison-worker" name="worker_id" type="number" min="1" step="1" required value="<?= $e($filters['worker_id'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="comparison-year">Año</label>
                        <input class="form-control" id="comparison-year" name="year" type="number" min="2024" max="<?= (int) date('Y') ?>" required value="<?= $e($filters['year'] ?? date('Y')) ?>">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="comparison-period">Número de <?= $periodType === 'week' ? 'semana' : 'quincena' ?></label>
                        <input class="form-control" id="comparison-period" name="period_number" type="number" min="1" max="<?= $periodType === 'week' ? (int) \App\Services\RecibosNominaService::getTotalSemanasPeriodo((int) ($filters['year'] ?? date('Y'))) : 24 ?>" required value="<?= $e($filters['period_number'] ?? '') ?>">
                        <div class="form-text">El tipo de periodo se determina por la localidad con la regla vigente de Recibos de Nómina (<?= $periodType === 'week' ? 'semanal' : 'quincenal' ?>).</div>
                    </div>
                    <div class="col-12 col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary" type="submit">Consultar comparación</button>
                    </div>
                </form>
            </div>
        </section>
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
            La consulta en vivo no es un snapshot y no prueba que el periodo o el padrón estén completos.
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <section class="card h-100" aria-labelledby="attendance-evidence-heading">
                    <div class="card-body">
                        <h2 class="h5" id="attendance-evidence-heading">Asistencia Checador V2</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Trabajador</dt><dd class="col-sm-8"><?= $e($worker['name'] ?? '') ?> · ID interno <?= (int) ($worker['id'] ?? 0) ?></dd>
                            <dt class="col-sm-4">Número de empleado</dt><dd class="col-sm-8"><?= isset($worker['employee_number']) ? (int) $worker['employee_number'] : 'No disponible' ?></dd>
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
                <p class="small text-muted">Estado: <?= $e($sourceLabel((string) ($attendance['status'] ?? 'unavailable'))) ?>. Registros consultados en vivo; no equivalen a un snapshot ni prueban que no existan otras marcaciones.</p>
                <?php if (($attendance['events'] ?? []) === []): ?>
                    <div class="alert alert-secondary mb-0" role="status">No hay filas que mostrar para la consulta disponible, o la fuente no pudo consultarse. No se interpreta como ausencia ni como periodo completo.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <caption class="visually-hidden">Marcaciones V2 observadas para el trabajador y periodo seleccionado</caption>
                            <thead><tr><th scope="col">Fecha y hora ocurrida (UTC)</th><th scope="col">Recepción (UTC)</th><th scope="col">Dispositivo</th><th scope="col">Método</th><th scope="col">Estado recibido</th><th scope="col">Evento</th></tr></thead>
                            <tbody>
                            <?php foreach ($attendance['events'] as $event): ?>
                                <tr>
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
                <p class="small text-muted">Estado de la fuente: <?= $e($sourceLabel((string) ($comparison['schedules']['status'] ?? 'unavailable'))) ?>. Los horarios se leen del resolvedor existente; Portal3 no calcula jornada.</p>
                <p class="small text-muted">Zona horaria laboral: <?= $e($sourceLabel((string) ($comparison['workplace_timezones']['status'] ?? 'unavailable'))) ?>.</p>
                <?php if (($comparison['workplace_timezones']['days'] ?? []) !== []): ?>
                    <ul class="small">
                        <?php foreach ($comparison['workplace_timezones']['days'] as $timezoneDay): ?>
                            <li><?= $e($timezoneDay['date'] ?? '') ?> · <?= $e($timezoneDay['timezone'] ?? 'No disponible') ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (($comparison['schedules']['days'] ?? []) !== []): ?>
                    <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0">
                        <thead><tr><th scope="col">Fecha laboral</th><th scope="col">Resultado</th><th scope="col">Zona horaria</th><th scope="col">Segmentos resueltos</th></tr></thead><tbody>
                        <?php foreach ($comparison['schedules']['days'] as $day): ?>
                            <tr>
                                <td><?= $e($day['date'] ?? '') ?></td>
                                <td><?= $e($sourceLabel((string) ($day['status'] ?? 'unavailable'))) ?></td>
                                <td><?= $e($day['timezone'] ?? 'No disponible') ?></td>
                                <td>
                                    <?php if (($day['segments'] ?? []) === []): ?>No disponible<?php else: ?>
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
                            <p class="mb-0">No hay incidencias que mostrar en la respuesta consultada; esto no confirma ausencia de otros registros.</p>
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
                        <p class="small text-muted">Evaluación existente consultada mediante GET; no se ejecutó una evaluación nueva.</p>
                        <p class="small">Estado de la fuente: <?= $e($sourceLabel((string) ($comparison['evaluations']['status'] ?? 'unavailable'))) ?>.</p>
                        <?php if (($comparison['evaluations']['days'] ?? []) !== []): ?><ul class="small mb-2">
                            <?php foreach ($comparison['evaluations']['days'] as $day): ?>
                                <li><?= $e($day['date'] ?? '') ?> · <?= $e($sourceLabel((string) ($day['status'] ?? 'unavailable'))) ?><?php if (isset($day['revision'])): ?> · revisión <?= (int) $day['revision'] ?><?php endif; ?><?php if (($day['administrative_status'] ?? '') !== ''): ?> · estado administrativo <?= $e($recordedStatusLabel($day['administrative_status'])) ?><?php endif; ?><?php if (($day['human_review_status'] ?? '') !== ''): ?> · revisión humana <?= $e($recordedStatusLabel($day['human_review_status'])) ?><?php endif; ?></li>
                            <?php endforeach; ?>
                        </ul><?php endif; ?>
                        <p class="small mb-1">Ajustes vinculados: <?= $e($sourceLabel((string) ($comparison['adjustments']['status'] ?? 'unavailable'))) ?>.</p>
                        <?php if (($comparison['adjustments']['items'] ?? []) !== []): ?><ul class="small mb-0">
                            <?php foreach ($comparison['adjustments']['items'] as $adjustment): ?><li><code><?= $e($adjustment['uuid'] ?? '') ?></code> · <?= $e($adjustmentTypeLabel($adjustment['type'] ?? null)) ?> · <?= $e($recordedStatusLabel($adjustment['status'] ?? null)) ?></li><?php endforeach; ?>
                        </ul><?php else: ?><p class="small text-muted mb-0">No hay referencias de ajustes en las evaluaciones consultadas; no se infiere inexistencia fuera de esa respuesta.</p><?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <section class="card mb-4" aria-labelledby="journey-readonly-heading">
            <div class="card-body">
                <h2 class="h5" id="journey-readonly-heading">Jornadas evaluadas</h2>
                <p class="mb-0"><?= $e($comparison['journeys']['message'] ?? 'No se ejecutó una evaluación de jornada en esta consulta.') ?></p>
            </div>
        </section>

        <p class="small text-muted mb-0"><?= $e($comparison['comparison_limit'] ?? '') ?></p>
    <?php endif; ?>
</div>

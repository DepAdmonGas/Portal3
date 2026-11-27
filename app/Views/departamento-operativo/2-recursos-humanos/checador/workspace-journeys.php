<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$journeyRows = is_array($journeys ?? null) ? $journeys : [];
$journeyErrors = is_array($journeyErrors ?? null) ? $journeyErrors : [];
$workerNames = [];
foreach (is_array($workerSelectOptions ?? null) ? $workerSelectOptions : [] as $workerOption) {
    if (!is_array($workerOption)) continue;
    $optionWorkerId = (int) ($workerOption['worker_id'] ?? 0);
    $optionName = trim((string) ($workerOption['full_name'] ?? ''));
    if ($optionWorkerId > 0 && $optionName !== '' && preg_match('/^Trabajador\s+\d+$/u', $optionName) !== 1) {
        $workerNames[$optionWorkerId] = $optionName;
    }
}
$statusLabel = static fn(string $status): string => match ($status) {
    'CALCULATED' => 'Cálculo persistido',
    'REVIEW_REQUIRED', 'AMBIGUOUS_PAIRING', 'AMBIGUOUS' => 'Requiere revisión',
    'INCOMPLETE', 'DATA_INCOMPLETE' => 'Resultado incompleto',
    'UNKNOWN' => 'Resultado no concluyente',
    'UNSCHEDULED_DAY' => 'Sin horario asignado',
    'RESULT_NOT_AVAILABLE' => 'Pareo y cálculo no disponibles',
    'SCHEDULED' => 'Pareo persistido',
    'DAY_OFF' => 'Descanso programado',
    'UNSCHEDULED' => 'Sin asignación informada',
    default => 'Estado persistido no reconocido',
};
$segmentStatusLabel = static fn(string $status): string => match ($status) {
    'EXACT_PAIR' => 'Emparejamiento exacto',
    'PARTIAL_MISSING_IN' => 'Entrada no disponible',
    'PARTIAL_MISSING_OUT' => 'Salida no disponible',
    'AMBIGUOUS_PAIRING', 'AMBIGUOUS' => 'Requiere revisión por ambigüedad',
    'UNMATCHED_NO_PUNCHES' => 'Sin marcaciones emparejadas',
    'WORKPLACE_MISMATCH' => 'Localidad por revisar',
    'PENDING_SYNC_COMPLETION' => 'Sincronización pendiente',
    default => 'Estado de segmento no disponible',
};
$journeyDetails = [];
?>
<div id="container" class="mt-4 mb-5">
    <section class="card" aria-labelledby="journey-workspace-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-clock-hour-4 fs-5"></i></span>
            <h2 class="h5 mb-0 text-white" id="journey-workspace-heading">Jornadas laborales</h2>
            <span class="visually-hidden">Jornadas laborales consultadas</span>
            <div class="dropdown ms-auto">
                <button type="button" class="btn btn-light dropdown-toggle text-dark" id="journey-options" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Opciones de jornadas">
                    <i class="ti ti-dots-vertical fs-4" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="journey-options">
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#journey-search-modal" <?= empty($selectedWorkplaceId) ? 'disabled' : '' ?>><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar jornadas</button></li>
                </ul>
            </div>
        </div>
        <div class="card-body">
            <?php if (($state ?? '') === 'persisted_read_contract_unavailable'): ?>
                <div class="alert alert-warning d-flex align-items-start gap-2" role="status"><i class="ti ti-lock mt-1" aria-hidden="true"></i><span><?= $esc($stateMessage) ?></span></div>
            <?php elseif (in_array(($state ?? ''), ['filter_required', 'selection_required'], true)): ?>
                <div class="alert alert-info d-flex align-items-start gap-2" role="status"><i class="ti ti-info-circle mt-1" aria-hidden="true"></i><span><?= $esc($stateMessage) ?></span></div>
            <?php elseif (($state ?? '') !== 'success'): ?>
                <div class="alert alert-warning d-flex align-items-start gap-2" role="status"><i class="ti ti-alert-triangle mt-1" aria-hidden="true"></i><span><?= $esc(($stateMessage ?? '') !== '' ? $stateMessage : 'La lectura de la fuente no está disponible. No se confirma un resultado vacío.') ?></span></div>
            <?php elseif ($journeyRows === [] && $journeyErrors === [] && (int) ($excludedUnverifiedCount ?? 0) === 0): ?>
                <p class="text-body-secondary text-center py-3 mb-0" role="status"><i class="ti ti-info-circle text-primary me-1" aria-hidden="true"></i>No existen jornadas procesadas para los filtros seleccionados.</p>
            <?php else: ?>
                <?php if ((int) ($excludedUnverifiedCount ?? 0) > 0): ?><div class="alert alert-warning" role="status">Hay <?= (int) $excludedUnverifiedCount ?> registros cuyo alcance o integridad no pudo verificarse. No se muestran como resultados confirmados.</div><?php endif; ?>
                <?php if ($journeyRows === []): ?><p class="text-body-secondary" role="status">No hay resultados verificables para mostrar con los filtros seleccionados.</p><?php endif; ?>
                <?php if ($journeyRows !== []): ?>
                <div class="table-responsive">
                    <table id="journey-results-table" class="table table-striped table-bordered align-middle w-100" data-checador-journeys>
                        <caption class="visually-hidden">Resultados de Jornada por trabajador</caption>
                        <thead><tr><th scope="col">Trabajador</th><th scope="col">Fecha laboral</th><th scope="col">Horario efectivo</th><th scope="col">Resultado</th><th scope="col">Tiempo trabajado</th><th scope="col">Acciones</th></tr></thead>
                        <tbody>
                        <?php foreach ($journeyRows as $journeyKey => $journey): ?>
                            <?php
                            if (!is_array($journey)) continue;
                            $journeyId = (int) ($journey['id'] ?? $journeyKey);
                            $workerId = (int) ($journey['worker_id'] ?? 0);
                            $context = is_array($journey['context'] ?? null) ? $journey['context'] : [];
                            $schedule = is_array($journey['schedule'] ?? null) ? $journey['schedule'] : [];
                            $calculation = is_array($journey['calculation'] ?? null) ? $journey['calculation'] : [];
                            $pairing = is_array($journey['pairing'] ?? null) ? $journey['pairing'] : [];
                            $provenance = is_array($journey['provenance'] ?? null) ? $journey['provenance'] : [];
                            $source = is_array($journey['source_evidence'] ?? null) ? $journey['source_evidence'] : [];
                            $auditEvents = is_array($journey['evaluated_raw_events'] ?? null) ? $journey['evaluated_raw_events'] : [];
                            $eventCount = $journey['evaluated_raw_event_count'] ?? null;
                            $eventsComplete = false;
                            $segments = is_array($pairing['segments'] ?? null) ? $pairing['segments'] : [];
                            $additionalSegments = array_values(array_filter($segments, static fn(mixed $segment): bool => is_array($segment) && is_int($segment['ordinal'] ?? null) && $segment['ordinal'] >= 3));
                            $seconds = (($calculation['estado_calculo'] ?? '') === 'CALCULATED'
                                && is_int($calculation['segundos_trabajados_totales'] ?? null)
                                && $calculation['segundos_trabajados_totales'] >= 0)
                                ? $calculation['segundos_trabajados_totales'] : null;
                            $duration = $seconds === null ? 'No disponible' : sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
                            $scheduleLabel = match ((string) ($schedule['estado_jornada'] ?? '')) {
                                'SCHEDULED' => 'Horario informado', 'DAY_OFF' => 'Descanso programado', 'UNSCHEDULED' => 'Sin asignación informada', 'NEEDS_REVIEW' => 'Requiere revisión', default => 'No disponible',
                            };
                            $startUtc = trim((string) ($schedule['scheduled_start_utc'] ?? ''));
                            $endUtc = trim((string) ($schedule['scheduled_end_utc'] ?? ''));
                            $scheduleSummary = $startUtc !== '' && $endUtc !== ''
                                ? $startUtc . ' UTC – ' . $endUtc . ' UTC'
                                : $scheduleLabel;
                            $workDate = (string) ($context['fecha_local'] ?? $selectedDate ?? '');
                            $workerName = $workerNames[$workerId] ?? (trim((string) ($journey['worker_name'] ?? '')) ?: 'Nombre no disponible');
                            $calculationSegments = is_array($calculation['segments'] ?? null) ? $calculation['segments'] : [];
                            $journeyDetails[$journeyId] = compact('journeyId', 'workerId', 'workerName', 'journey', 'context', 'schedule', 'calculation', 'pairing', 'provenance', 'source', 'auditEvents', 'eventCount', 'eventsComplete', 'segments', 'additionalSegments', 'calculationSegments', 'seconds', 'duration', 'scheduleLabel', 'scheduleSummary', 'workDate');
                            ?>
                            <tr data-journey-row data-worker-id="<?= $workerId ?>" data-work-date="<?= $esc($workDate) ?>">
                                <th scope="row"><?= $esc($workerName) ?></th>
                                <td><?= $esc($workDate !== '' ? $workDate : 'No disponible') ?></td>
                                <td><?= $esc($scheduleSummary) ?></td>
                                <td><span class="badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 bg-info-subtle border border-info-subtle text-body-emphasis"><i class="ti ti-info-circle" aria-hidden="true"></i><?= $esc($statusLabel((string) ($journey['journey_status'] ?? ''))) ?></span></td>
                                <td><?= $esc($duration) ?><?php if ($seconds !== null): ?><span class="d-block small text-muted">Total persistido por API V2</span><?php endif; ?></td>
                                <td><div class="dropdown">
                                    <button type="button" class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones para <?= $esc($workerName) ?>"><i class="ti ti-dots-vertical" aria-hidden="true"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end"><li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#journey-detail-<?= $journeyId ?>"><i class="ti ti-eye me-1" aria-hidden="true"></i>Ver detalle y trazabilidad</button></li></ul>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php foreach ($journeyErrors as $workerId => $errorKind): ?>
                    <div class="alert alert-warning mt-3 d-flex align-items-start gap-2" role="status"><i class="ti ti-alert-triangle mt-1" aria-hidden="true"></i><span>Personal <?= (int) $workerId ?>: el resultado de Jornada no está disponible; no se presenta como cero horas.</span></div>
                <?php endforeach; ?>
                <?php if ($journeyRows !== []): ?>
                    <?php $pager = is_array($journeyPagination ?? null) ? $journeyPagination : []; $pageNow = max(1, (int) ($pager['page'] ?? 1)); $pageCount = max(1, (int) ($pager['total_pages'] ?? 1)); $pageQuery = ['workplace_id' => (int) ($selectedWorkplaceId ?? 0), 'start_date' => (string) ($startDate ?? ''), 'end_date' => (string) ($endDate ?? '')]; if (!empty($selectedWorkerId)) $pageQuery['worker_id'] = (int) $selectedWorkerId; ?>
                    <?php if ($pageCount > 1): ?><nav aria-label="Paginación de jornadas"><ul class="pagination justify-content-end mt-3 mb-0"><?php for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++): $pageHref = '?' . http_build_query($pageQuery + ['page' => $pageNumber], '', '&', PHP_QUERY_RFC3986); ?><li class="page-item <?= $pageNumber === $pageNow ? 'active' : '' ?>"><a class="page-link" href="<?= $esc($pageHref) ?>" <?= $pageNumber === $pageNow ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a></li><?php endfor; ?></ul></nav><?php endif; ?>
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="modal fade" id="journey-search-modal" tabindex="-1" aria-labelledby="journey-search-title" aria-hidden="true" data-journey-search-modal>
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="get" action="/departamento-operativo/recursos-humanos/checador/jornadas" data-journey-filter-form>
            <div class="modal-header bg-primary text-white"><h3 class="modal-title h5 text-white" id="journey-search-title"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar jornadas cargadas</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p class="small text-body-secondary">Consulta resultados persistidos dentro de la localidad autorizada. El rango máximo es de 31 fechas laborales.</p>
                <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                <div class="mb-3"><label for="journey-filter-worker" class="form-label">Trabajador</label><div class="select2-modal-field w-100"><select id="journey-filter-worker" name="worker_id" class="form-select w-100" data-journey-worker-select><option value="">Todos</option><?php foreach (is_array($workerSelectOptions ?? null) ? $workerSelectOptions : [] as $workerOption): if (!is_array($workerOption)) continue; $optionWorkerId = (int) ($workerOption['worker_id'] ?? 0); $optionName = trim((string) ($workerOption['full_name'] ?? '')); $employeeNumber = trim((string) ($workerOption['employee_number'] ?? '')); if ($optionWorkerId <= 0 || $optionName === '') continue; ?><option value="<?= $optionWorkerId ?>" <?= $optionWorkerId === (int) ($selectedWorkerId ?? 0) ? 'selected' : '' ?>><?= $esc($optionName . ($employeeNumber !== '' && $employeeNumber !== '0' ? ' · ' . $employeeNumber : '')) ?></option><?php endforeach; ?></select></div><?php if (trim((string) ($workerOptionsMessage ?? '')) !== ''): ?><p class="form-text mb-0" data-journey-worker-roster-status><?= $esc($workerOptionsMessage) ?></p><?php endif; ?></div>
                <div class="row g-3"><div class="col-md-6"><label for="journey-filter-start" class="form-label">Fecha inicial</label><input type="date" id="journey-filter-start" name="start_date" value="<?= $esc($startDate ?? '') ?>" class="form-control" required data-journey-date-start></div><div class="col-md-6"><label for="journey-filter-end" class="form-label">Fecha final</label><input type="date" id="journey-filter-end" name="end_date" value="<?= $esc($endDate ?? '') ?>" class="form-control" required data-journey-date-end></div></div>
            </div>
            <div class="modal-footer"><button type="reset" class="btn btn-light"><i class="ti ti-eraser me-1" aria-hidden="true"></i>Limpiar</button><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button><button type="submit" class="btn btn-primary"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar</button></div>
        </form>
    </div></div>
</div>

<?php foreach ($journeyDetails as $journeyDetail): extract($journeyDetail, EXTR_OVERWRITE); ?>
<div class="modal fade" id="journey-detail-<?= (int) $journeyId ?>" tabindex="-1" aria-labelledby="journey-detail-title-<?= (int) $journeyId ?>" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl"><div class="modal-content">
        <div class="modal-header bg-primary text-white"><h3 class="modal-title h5 text-white" id="journey-detail-title-<?= (int) $journeyId ?>"><i class="ti ti-clock-hour-4 me-1" aria-hidden="true"></i><?= $esc($workerName) ?> · Jornada</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <div class="row g-3 mb-4"><div class="col-sm-6 col-lg-3"><strong>Fecha laboral</strong><div><?= $esc($workDate ?: 'No disponible') ?></div></div><div class="col-sm-6 col-lg-3"><strong>Horario efectivo informado</strong><div><?= $esc($scheduleSummary) ?></div><div class="small text-muted"><?= $esc($scheduleLabel) ?></div></div><div class="col-sm-6 col-lg-3"><strong>Resultado</strong><div><span class="badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 bg-info-subtle border border-info-subtle text-body-emphasis"><i class="ti ti-info-circle" aria-hidden="true"></i><?= $esc($statusLabel((string) ($journey['journey_status'] ?? ''))) ?></span></div></div><div class="col-sm-6 col-lg-3"><strong>Tiempo trabajado informado</strong><div><?= $esc($duration) ?></div></div></div>
            <details id="journey-audit-detail-<?= (int) $journeyId ?>">
                <summary>Detalle de auditoría</summary>
                <p class="small text-muted mt-2">La información y las conclusiones se presentan tal como fueron recibidas; Portal3 no recalcula pareos ni horas. Nunca se activa automáticamente una FALTA en esta pantalla.</p>
                <dl class="row small mt-3 mb-3">
                    <dt class="col-sm-4">Personal canónico</dt><dd class="col-sm-8"><?= isset($context['id_personal']) ? (int) $context['id_personal'] : 'No disponible' ?></dd>
                    <dt class="col-sm-4">Localidad laboral</dt><dd class="col-sm-8"><?= isset($context['id_estacion']) ? (int) $context['id_estacion'] : 'No disponible' ?></dd>
                    <dt class="col-sm-4">Fecha laboral</dt><dd class="col-sm-8"><?= $esc($context['fecha_local'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">Zona horaria laboral</dt><dd class="col-sm-8"><?= $esc($context['timezone'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">Estado de horario</dt><dd class="col-sm-8"><?= $esc($schedule['estado_jornada'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">ID de Jornada</dt><dd class="col-sm-8"><?= (int) $journeyId ?></dd>
                    <dt class="col-sm-4">Estado de completitud L2 persistido</dt><dd class="col-sm-8"><?= $esc($provenance['l2_status_persisted'] ?? 'No disponible') ?> · no verificado de forma independiente</dd>
                    <dt class="col-sm-4">Revisión vigente P1</dt><dd class="col-sm-8"><?= $esc($journey['pairing_current_status'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">Versión del algoritmo P1</dt><dd class="col-sm-8"><?= $esc($provenance['p1_algorithm_version'] ?? $pairing['version_algoritmo'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">Revisión P1</dt><dd class="col-sm-8"><?= isset($pairing['revision_numero']) ? (int) $pairing['revision_numero'] : 'No aplica' ?></dd>
                    <dt class="col-sm-4">Creación P1 (UTC)</dt><dd class="col-sm-8"><?= $esc($provenance['p1_created_at_utc'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">Versión del algoritmo P2</dt><dd class="col-sm-8"><?= $esc($provenance['p2_algorithm_version'] ?? $calculation['version_algoritmo_calculo'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">Revisión P2</dt><dd class="col-sm-8"><?= isset($calculation['revision_numero']) ? (int) $calculation['revision_numero'] : 'No aplica' ?></dd>
                    <dt class="col-sm-4">ID de pareo P1 vinculado al P2 seleccionado</dt><dd class="col-sm-8"><?= isset($calculation['id_pareo_jornada']) ? (int) $calculation['id_pareo_jornada'] : 'No disponible' ?></dd>
                    <dt class="col-sm-4">Creación P2 (UTC)</dt><dd class="col-sm-8"><?= $esc($provenance['p2_created_at_utc'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">Estado de cálculo persistido</dt><dd class="col-sm-8 text-break"><?= $esc($calculation['estado_calculo'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">Horario UTC persistido</dt><dd class="col-sm-8 text-break"><?= $esc($schedule['scheduled_start_utc'] ?? 'No disponible') ?> – <?= $esc($schedule['scheduled_end_utc'] ?? 'No disponible') ?></dd>
                    <dt class="col-sm-4">Segmentos del snapshot</dt><dd class="col-sm-8"><pre class="small text-break mb-0"><?= $esc($schedule['payload_segmentos_json'] ?? 'No disponible') ?></pre></dd>
                </dl>
                <h4 class="h6">Segmentos P1 persistidos (<?= count($segments) ?>)</h4>
                <?php if ($segments === []): ?><p class="text-muted">No hay segmentos P1 disponibles para la revisión seleccionada.</p><?php else: ?><div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr><th>Ordinal</th><th>Estado persistido</th><th>Creación UTC</th></tr></thead><tbody><?php foreach ($segments as $segment): if (!is_array($segment)) continue; ?><tr><td><?= (int) ($segment['ordinal'] ?? 0) ?></td><td><?= $esc($segment['status'] ?? 'No disponible') ?></td><td><?= $esc($segment['created_at_utc'] ?? 'No disponible') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                <?php if ($additionalSegments !== []): ?><p class="small text-muted">Incluye segmentos adicionales persistidos: <?= count($additionalSegments) ?> (ordinal 3 o posterior).</p><?php endif; ?>
                <h4 class="h6 mt-3">Segmentos P2 persistidos (<?= count($calculationSegments) ?>)</h4>
                <?php if ($calculationSegments === []): ?><p class="text-muted">No hay segmentos P2 disponibles para el cálculo seleccionado.</p><?php else: ?><div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr><th>Ordinal</th><th>Tipo</th><th>Estado</th><th>Programados (s)</th><th>Trabajados (s)</th><th>Delta entrada (s)</th><th>Delta salida (s)</th><th>Fuera de horario (s)</th></tr></thead><tbody><?php foreach ($calculationSegments as $segment): if (!is_array($segment)) continue; ?><tr><td><?= (int) ($segment['ordinal_segmento'] ?? 0) ?></td><td><?= $esc($segment['tipo_segmento'] ?? 'No disponible') ?></td><td><?= $esc($segment['estado_segmento'] ?? 'No disponible') ?></td><?php foreach (['segundos_programados', 'segundos_trabajados', 'delta_llegada_segundos', 'delta_salida_segundos', 'segundos_candidatos_fuera_horario'] as $secondsField): ?><td><?= !array_key_exists($secondsField, $segment) || $segment[$secondsField] === null ? 'No disponible' : (int) $segment[$secondsField] ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                <details class="mt-3" id="journey-revision-history-<?= (int) $journeyId ?>"><summary>Historial de revisiones P1/P2 (<?= count($journey['pairing_history'] ?? []) + count($journey['calculation_history'] ?? []) ?>)</summary>
                    <?php foreach (['P1' => ($journey['pairing_history'] ?? []), 'P2' => ($journey['calculation_history'] ?? [])] as $revisionKind => $revisionRows): ?><h4 class="h6 mt-3">Revisiones <?= $revisionKind ?></h4><?php if ($revisionRows === []): ?><p class="text-muted">No hay revisiones persistidas para este resultado.</p><?php else: ?><div class="table-responsive"><table class="table table-sm table-bordered align-middle"><thead><tr><th>Revisión</th><?php if ($revisionKind === 'P2'): ?><th>ID pareo P1 vinculado</th><?php endif; ?><th>Estado persistido</th><th>Algoritmo</th><th>Fecha UTC</th><th>Referencia</th></tr></thead><tbody><?php foreach ($revisionRows as $revision): if (!is_array($revision)) continue; ?><tr><td><?= (int) ($revision['revision_numero'] ?? 0) ?></td><?php if ($revisionKind === 'P2'): ?><td><?= isset($revision['id_pareo_jornada']) ? (int) $revision['id_pareo_jornada'] : 'No disponible' ?></td><?php endif; ?><td><span class="badge rounded-pill fw-semibold d-inline-flex align-items-center gap-1 bg-info-subtle border border-info-subtle text-body-emphasis"><i class="ti ti-info-circle" aria-hidden="true"></i><?= $esc($revision[$revisionKind === 'P1' ? 'estado_pareo' : 'estado_calculo'] ?? 'No disponible') ?></span></td><td><?= $esc($revision[$revisionKind === 'P1' ? 'version_algoritmo' : 'version_algoritmo_calculo'] ?? 'No disponible') ?></td><td><?= $esc($revision['creado_at_utc'] ?? 'No disponible') ?></td><td class="text-break"><code><?= $esc($revision['fingerprint_ejecucion'] ?? 'No disponible') ?></code></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?><?php endforeach; ?>
                </details>
                <details class="mt-3" id="journey-audit-events-<?= (int) $journeyId ?>"><summary>Marcaciones asociadas al pareo (<?= count($auditEvents) ?>)</summary><p class="small text-muted mt-2">Se muestran únicamente las asociaciones persistidas. Este conjunto no acredita por sí solo la completitud L2.</p>
                    <?php if ($auditEvents === []): ?><p class="text-muted">No hay asociaciones de marcación disponibles para esta revisión.</p><?php else: ?><div class="table-responsive"><table class="table table-sm table-striped table-bordered align-middle"><thead><tr><th>Rol persistido</th><th>Evento</th><th>Fecha y hora UTC</th><th>Dispositivo</th><th>Método</th></tr></thead><tbody><?php foreach ($auditEvents as $auditEvent): if (!is_array($auditEvent)) continue; ?><tr><th><?= $esc($auditEvent['pairing_role'] ?? 'No disponible') ?></th><td class="text-break"><code><?= $esc($auditEvent['event_uuid'] ?? 'No disponible') ?></code></td><td><?= $esc($auditEvent['ocurrido_at_utc'] ?? 'No disponible') ?></td><td><?= is_int($auditEvent['id_dispositivo'] ?? null) && $auditEvent['id_dispositivo'] > 0 ? (int) $auditEvent['id_dispositivo'] : 'No disponible' ?></td><td><?= $esc(\App\Services\ChecadorPresentationFormatter::captureMethodLabel($auditEvent['metodo_captura'] ?? null)) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
                </details>
            </details>
        </div>
        <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger ms-auto" data-bs-dismiss="modal"><i class="ti ti-x me-1" aria-hidden="true"></i>Cerrar</button></div>
    </div></div>
</div>
<?php endforeach; ?>

<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$journeyRows = is_array($journeys ?? null) ? $journeys : [];
$journeyErrors = is_array($journeyErrors ?? null) ? $journeyErrors : [];
$statusLabel = static fn(string $status): string => match ($status) {
    'EVALUATED' => 'Resultado disponible',
    'DATA_INCOMPLETE' => 'Información insuficiente',
    'REVIEW_REQUIRED' => 'Requiere revisión',
    'AMBIGUOUS_PAIRING' => 'Requiere revisión por ambigüedad',
    'UNSCHEDULED_DAY' => 'Sin horario asignado',
    'DAY_OFF' => 'Descanso programado',
    default => 'Resultado no disponible',
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
?>
<div id="container" class="mt-4 mb-5">
    <?php require __DIR__ . '/_workspace-context.php'; ?>
    <section class="card" aria-labelledby="journey-workspace-heading">
        <div class="card-header"><h2 class="h5 mb-0" id="journey-workspace-heading">Jornadas laborales consultadas</h2></div>
        <div class="card-body">
            <p class="text-muted">Resultados informados por el servicio para la fecha laboral seleccionada. Portal3 no calcula pareos, horas ni conclusiones laborales. Nunca se activa automáticamente la FALTA en esta pantalla; sólo puede mostrarse desde la evaluación administrativa autorizada.</p>
            <?php if (($state ?? '') !== 'success'): ?>
                <div class="alert alert-warning" role="status"><?= $esc(($stateMessage ?? '') !== '' ? $stateMessage : 'La lectura de la fuente no está disponible. No se confirma un resultado vacío.') ?></div>
            <?php endif; ?>
            <?php if (($state ?? '') === 'success' && $journeyRows === [] && $journeyErrors === []): ?>
                <div class="alert alert-secondary" role="status">No hay resultados de Jornada incluidos en esta consulta. Esto no determina falta ni ausencia.</div>
            <?php else: ?>
                <?php if ($journeyRows !== []): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <caption class="visually-hidden">Resultados de Jornada por trabajador</caption>
                            <thead><tr><th scope="col">Trabajador</th><th scope="col">Fecha laboral</th><th scope="col">Horario informado</th><th scope="col">Resultado</th><th scope="col">Tiempo informado</th><th scope="col">Procedencia</th></tr></thead>
                            <tbody>
                            <?php foreach ($journeyRows as $workerId => $journey): ?>
                                <?php
                                if (!is_array($journey)) continue;
                                $context = is_array($journey['context'] ?? null) ? $journey['context'] : [];
                                $schedule = is_array($journey['schedule'] ?? null) ? $journey['schedule'] : [];
                                $calculation = is_array($journey['calculation'] ?? null) ? $journey['calculation'] : [];
                                $pairing = is_array($journey['pairing'] ?? null) ? $journey['pairing'] : [];
                                $provenance = is_array($journey['provenance'] ?? null) ? $journey['provenance'] : [];
                                $source = is_array($journey['source_evidence'] ?? null) ? $journey['source_evidence'] : [];
                                $auditEvents = is_array($journey['evaluated_raw_events'] ?? null) ? $journey['evaluated_raw_events'] : [];
                                $eventCount = $journey['evaluated_raw_event_count'] ?? null;
                                $eventsComplete = is_int($eventCount) && $eventCount === count($auditEvents) && ($journey['evaluated'] ?? false) === true;
                                $segments = is_array($pairing['segments'] ?? null) ? $pairing['segments'] : [];
                                $additionalSegments = array_values(array_filter($segments, static fn(mixed $segment): bool => is_array($segment) && is_int($segment['ordinal'] ?? null) && $segment['ordinal'] >= 3));
                                $seconds = (($calculation['estado_calculo'] ?? '') === 'CALCULATED'
                                    && is_int($calculation['segundos_trabajados_totales'] ?? null)
                                    && $calculation['segundos_trabajados_totales'] >= 0)
                                    ? $calculation['segundos_trabajados_totales'] : null;
                                $duration = $seconds === null ? 'No disponible' : sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
                                $scheduleLabel = match ((string) ($schedule['estado_jornada'] ?? '')) {
                                    'SCHEDULED' => 'Horario informado', 'DAY_OFF' => 'Descanso informado', 'UNSCHEDULED' => 'Sin asignación informada', 'NEEDS_REVIEW' => 'Requiere revisión', default => 'No disponible',
                                };
                                $policyRuleLabel = array_key_exists('policy_rule_version', $provenance)
                                    ? ($provenance['policy_rule_version'] === null
                                        ? 'No aplica'
                                        : (is_string($provenance['policy_rule_version']) && trim($provenance['policy_rule_version']) !== ''
                                            ? (string) $provenance['policy_rule_version'] : 'No disponible'))
                                    : 'No disponible';
                                ?>
                                <tr>
                                    <th scope="row">Personal <?= (int) $workerId ?></th>
                                    <td><?= $esc($context['fecha_local'] ?? $selectedDate ?? 'No disponible') ?></td>
                                    <td><?= $esc($scheduleLabel) ?></td>
                                    <td><?= $esc($statusLabel((string) ($journey['journey_status'] ?? ''))) ?></td>
                                    <td><?= $esc($duration) ?><?php if ($seconds !== null): ?><span class="d-block small text-muted">Informado por la evaluación</span><?php endif; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#journey-detail-<?= (int) $workerId ?>">Ver trazabilidad</button>
                                        <div class="modal fade" id="journey-detail-<?= (int) $workerId ?>" tabindex="-1" aria-labelledby="journey-detail-title-<?= (int) $workerId ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content">
                                                <div class="modal-header"><h3 class="modal-title h5" id="journey-detail-title-<?= (int) $workerId ?>">Trazabilidad de Jornada · personal <?= (int) $workerId ?></h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar detalle de Jornada"></button></div>
                                                <div class="modal-body">
                                                    <details id="journey-audit-detail-<?= (int) $workerId ?>">
                                                        <summary>Detalle de auditoría</summary>
                                                        <p class="small text-muted mt-2">Procedencia y evidencia recibidas de la API. No se activa automáticamente una falta ni se generan conclusiones de nómina.</p>
                                                        <dl class="row small mt-3 mb-3">
                                                            <dt class="col-sm-4">Personal canónico</dt><dd class="col-sm-8"><?= isset($context['id_personal']) ? (int) $context['id_personal'] : 'No disponible' ?></dd>
                                                            <dt class="col-sm-4">Localidad laboral</dt><dd class="col-sm-8"><?= isset($context['id_estacion']) ? (int) $context['id_estacion'] : 'No disponible' ?></dd>
                                                            <dt class="col-sm-4">Fecha laboral</dt><dd class="col-sm-8"><?= $esc($context['fecha_local'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Zona horaria laboral</dt><dd class="col-sm-8"><?= $esc($context['timezone'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Estado de horario</dt><dd class="col-sm-8"><?= $esc($schedule['estado_jornada'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Versión de plantilla</dt><dd class="col-sm-8"><?= isset($provenance['shift_template_version']) ? (int) $provenance['shift_template_version'] : 'No disponible' ?></dd>
                                                            <dt class="col-sm-4">Referencia de evidencia</dt><dd class="col-sm-8 text-break"><?= $esc($source['snapshot_audit_ref'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Evaluación de completitud</dt><dd class="col-sm-8"><?= $esc($source['window_level2_status'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Referencia de Nivel 2</dt><dd class="col-sm-8 text-break"><?= $esc($source['level2_evaluation_ref'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Política de Nivel 2</dt><dd class="col-sm-8 text-break"><?= $esc($provenance['level2_policy_ref'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Contrato de completitud</dt><dd class="col-sm-8 text-break"><?= $esc($provenance['source_completeness_contract_version'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Regla de política</dt><dd class="col-sm-8 text-break"><?= $esc($policyRuleLabel) ?></dd>
                                                            <dt class="col-sm-4">Respuesta generada (UTC)</dt><dd class="col-sm-8"><?= $esc($provenance['response_generated_at_utc'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Versión del algoritmo P1</dt><dd class="col-sm-8"><?= $esc($provenance['p1_algorithm_version'] ?? $pairing['algorithm_version'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Revisión P1</dt><dd class="col-sm-8"><?= isset($pairing['revision_numero']) ? (int) $pairing['revision_numero'] : 'No aplica' ?></dd>
                                                            <dt class="col-sm-4">Creación P1 (UTC)</dt><dd class="col-sm-8"><?= $esc($provenance['p1_created_at_utc'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Versión del algoritmo P2</dt><dd class="col-sm-8"><?= $esc($provenance['p2_algorithm_version'] ?? $calculation['version_algoritmo'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Revisión P2</dt><dd class="col-sm-8"><?= isset($calculation['revision_numero']) ? (int) $calculation['revision_numero'] : 'No aplica' ?></dd>
                                                            <dt class="col-sm-4">Creación P2 (UTC)</dt><dd class="col-sm-8"><?= $esc($provenance['p2_created_at_utc'] ?? 'No disponible') ?></dd>
                                                            <dt class="col-sm-4">Política de cálculo</dt><dd class="col-sm-8 text-break"><?= $esc($calculation['policy_rule_version'] ?? 'No disponible') ?></dd>
                                                        </dl>

                                                        <h4 class="h6">Segmentos informados por la Jornada</h4>
                                                        <?php if ($additionalSegments !== []): ?><p class="small text-muted">+<?= count($additionalSegments) ?> segmentos adicionales</p><?php endif; ?>
                                                        <?php if ($segments === []): ?><p class="text-muted">La respuesta no incluye segmentos para esta evaluación.</p><?php else: ?>
                                                            <div class="table-responsive"><table class="table table-sm table-bordered align-middle">
                                                                <caption class="visually-hidden">Segmentos de Jornada y referencias públicas de eventos</caption>
                                                                <thead><tr><th scope="col">Segmento</th><th scope="col">Estado</th><th scope="col">Evento de entrada</th><th scope="col">Evento de salida</th></tr></thead>
                                                                <tbody><?php foreach ($segments as $segment): if (!is_array($segment)) continue; ?>
                                                                    <tr><th scope="row"><?= isset($segment['ordinal']) && is_int($segment['ordinal']) ? (int) $segment['ordinal'] : 'No disponible' ?></th><td><?= $esc($segmentStatusLabel((string) ($segment['status'] ?? ''))) ?></td><td class="text-break"><code><?= $esc($segment['entry_event_uuid'] ?? 'No disponible') ?></code></td><td class="text-break"><code><?= $esc($segment['exit_event_uuid'] ?? 'No disponible') ?></code></td></tr>
                                                                <?php endforeach; ?></tbody>
                                                            </table></div>
                                                        <?php endif; ?>

                                                        <details class="mt-3" id="journey-audit-events-<?= (int) $workerId ?>">
                                                            <summary><?= $eventsComplete && $eventCount > 4 ? 'Ver todas las marcaciones (' . (int) $eventCount . ')' : 'Marcaciones evaluadas (' . (is_int($eventCount) ? (int) $eventCount : 'No disponible') . ')' ?></summary>
                                                            <p class="small text-muted mt-2">Eventos públicos devueltos por la evaluación. Cantidad informada: <?= is_int($eventCount) ? (int) $eventCount : 'No disponible' ?><?= $eventsComplete ? '' : ' · conjunto no confirmado' ?>.</p>
                                                            <?php if ($auditEvents === []): ?><p class="text-muted">No se recibió una lista evaluada de marcaciones para mostrar.</p><?php else: ?>
                                                                <div class="table-responsive"><table class="table table-sm table-striped table-bordered align-middle">
                                                                    <caption class="visually-hidden">Todas las marcaciones evaluadas por la API para personal <?= (int) $workerId ?></caption>
                                                                    <thead><tr><th scope="col">Clasificación</th><th scope="col">Evento</th><th scope="col">Fecha y hora UTC</th><th scope="col">Dispositivo</th><th scope="col">Método</th></tr></thead>
                                                                    <tbody><?php foreach ($auditEvents as $auditEvent): if (!is_array($auditEvent)) continue; ?>
                                                                        <tr>
                                                                            <th scope="row"><?= $esc(match ((string) ($auditEvent['pairing_role'] ?? '')) { 'ENTRY' => 'Entrada', 'EXIT' => 'Salida', 'SURPLUS' => 'Marcación adicional', 'UNMATCHED' => 'Sin emparejar', default => 'No disponible' }) ?></th>
                                                                            <td class="text-break"><code><?= $esc($auditEvent['event_uuid'] ?? 'No disponible') ?></code></td>
                                                                            <td><?= $esc($auditEvent['ocurrido_at_utc'] ?? 'No disponible') ?></td>
                                                                            <td><?= is_int($auditEvent['id_dispositivo'] ?? null) && $auditEvent['id_dispositivo'] > 0 ? (int) $auditEvent['id_dispositivo'] : 'No disponible' ?></td>
                                                                            <td><?= $esc(\App\Services\ChecadorPresentationFormatter::captureMethodLabel($auditEvent['metodo_captura'] ?? null)) ?></td>
                                                                        </tr>
                                                                    <?php endforeach; ?></tbody>
                                                                </table></div>
                                                            <?php endif; ?>
                                                        </details>
                                                    </details>
                                                </div>
                                                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button></div>
                                            </div></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <?php foreach ($journeyErrors as $workerId => $errorKind): ?>
                    <div class="alert alert-warning mt-3" role="status">Personal <?= (int) $workerId ?>: el resultado de Jornada no está disponible; no se presenta como cero horas.</div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

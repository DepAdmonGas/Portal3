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
?>
<div id="container" class="mt-4 mb-5">
    <?php require __DIR__ . '/_workspace-context.php'; ?>
    <section class="card" aria-labelledby="journey-workspace-heading">
        <div class="card-header"><h2 class="h5 mb-0" id="journey-workspace-heading">Jornadas laborales consultadas</h2></div>
        <div class="card-body">
            <p class="text-muted">Resultados informados por el servicio para la fecha laboral seleccionada. Portal3 no calcula pareos, horas ni conclusiones laborales.</p>
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
                                $context = is_array($journey['context'] ?? null) ? $journey['context'] : [];
                                $schedule = is_array($journey['schedule'] ?? null) ? $journey['schedule'] : [];
                                $calculation = is_array($journey['calculation'] ?? null) ? $journey['calculation'] : [];
                                $source = is_array($journey['source_evidence'] ?? null) ? $journey['source_evidence'] : [];
                                $events = is_array($journey['evaluated_raw_events'] ?? null) ? $journey['evaluated_raw_events'] : [];
                                $eventCount = $journey['evaluated_raw_event_count'] ?? null;
                                $eventsComplete = is_int($eventCount) && $eventCount === count($events) && ($journey['evaluated'] ?? false) === true;
                                $seconds = (($calculation['estado_calculo'] ?? '') === 'CALCULATED'
                                    && is_int($calculation['segundos_trabajados_totales'] ?? null)
                                    && $calculation['segundos_trabajados_totales'] >= 0)
                                    ? $calculation['segundos_trabajados_totales'] : null;
                                $duration = $seconds === null ? 'No disponible' : sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
                                $scheduleLabel = match ((string) ($schedule['estado_jornada'] ?? '')) {
                                    'SCHEDULED' => 'Horario informado', 'DAY_OFF' => 'Descanso informado', 'UNSCHEDULED' => 'Sin asignación informada', 'NEEDS_REVIEW' => 'Requiere revisión', default => 'No disponible',
                                };
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
                                        <dl class="row small mb-2">
                                            <dt class="col-sm-4">Zona laboral</dt><dd class="col-sm-8"><?= $esc($context['timezone'] ?? 'No disponible') ?></dd>
                                            <dt class="col-sm-4">Referencia de evidencia</dt><dd class="col-sm-8 text-break"><?= $esc($source['snapshot_audit_ref'] ?? 'No disponible') ?></dd>
                                            <dt class="col-sm-4">Eventos evaluados</dt><dd class="col-sm-8"><?= is_int($eventCount) ? $eventCount : 'No disponible' ?><?= $eventsComplete ? '' : ' · conjunto no confirmado' ?></dd>
                                            <dt class="col-sm-4">Revisión de cálculo</dt><dd class="col-sm-8"><?= isset($calculation['revision_numero']) ? (int) $calculation['revision_numero'] : 'No disponible' ?></dd>
                                        </dl>
                                        <?php if ($events !== []): ?><div class="table-responsive mt-2"><table class="table table-sm table-bordered align-middle mb-0"><caption class="visually-hidden">Marcaciones que el servicio incluyó en la evaluación</caption><thead><tr><th scope="col">Clasificación informada</th><th scope="col">Evento</th><th scope="col">Captura UTC</th><th scope="col">Dispositivo</th></tr></thead><tbody>
                                            <?php foreach ($events as $event): if (!is_array($event)) continue; ?>
                                                <tr><td><?= $esc(match ((string) ($event['pairing_role'] ?? '')) { 'ENTRY' => 'Entrada', 'EXIT' => 'Salida', 'SURPLUS' => 'Marcación adicional', 'UNMATCHED' => 'Sin emparejar', default => 'No disponible' }) ?></td><td class="text-break"><code><?= $esc($event['event_uuid'] ?? 'No disponible') ?></code></td><td><?= $esc($event['ocurrido_at_utc'] ?? 'No disponible') ?></td><td><?= isset($event['id_dispositivo']) ? (int) $event['id_dispositivo'] : 'No disponible' ?></td></tr>
                                            <?php endforeach; ?>
                                        </tbody></table></div><?php endif; ?>
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

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-body">
            <h1 class="h4">Checador</h1>
            <?php if (is_array($lifecycleFeedback ?? null)): ?>
                <div class="alert alert-<?= ($lifecycleFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
                    <?= htmlspecialchars((string) ($lifecycleFeedback['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if (is_array($evaluationFeedback ?? null)): ?>
                <div class="alert alert-<?= ($evaluationFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
                    <?= htmlspecialchars((string) ($evaluationFeedback['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <form method="get" action="/departamento-operativo/recursos-humanos/checador" class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label" for="workplace_id">Centro de trabajo</label>
                    <select class="form-select" id="workplace_id" name="workplace_id" required>
                        <option value="">Selecciona un centro de trabajo</option>
                        <?php foreach ($workplaces as $workplace): ?>
                            <option value="<?= (int) $workplace['id'] ?>" <?= (int) ($selectedWorkplaceId ?? 0) === (int) $workplace['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($workplace['nombre'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="date">Fecha local</label>
                    <input class="form-control" id="date" name="date" type="date" value="<?= htmlspecialchars((string) ($selectedDate ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="worker_id">Trabajador canónico (opcional)</label>
                    <input class="form-control" id="worker_id" name="worker_id" type="number" min="1" value="<?= $selectedWorkerId !== null ? (int) $selectedWorkerId : '' ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary" type="submit">Consultar</button>
                </div>
            </form>

            <?php if (($state ?? 'idle') === 'idle'): ?>
                <p class="text-muted mb-0">Selecciona un centro de trabajo y una fecha local para consultar las marcaciones observadas.</p>
            <?php elseif (($state ?? '') === 'timezone_unresolved'): ?>
                <div class="alert alert-warning" role="alert"><?= htmlspecialchars($stateMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif (($state ?? '') === 'snapshot_expired' || ($state ?? '') === 'snapshot_state_unavailable'): ?>
                <div class="alert alert-warning" role="alert"><?= htmlspecialchars($stateMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif (($state ?? '') === 'unavailable' || ($state ?? '') === 'error'): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($stateMessage, ENT_QUOTES, 'UTF-8') ?></div>
            <?php elseif (($state ?? '') === 'level2_error'): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Estado de la fuente no disponible</strong><br>
                    <?= htmlspecialchars($stateMessage, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php elseif (($state ?? '') === 'success'): ?>
                <div class="alert alert-info" role="status">
                    <strong>Marcaciones observadas</strong><br>
                    Esta consulta usa un conjunto estable de marcaciones observadas para la fecha seleccionada. Por sí sola no determina faltas, ausencias ni incidencias.
                </div>
                <?php if ($level2Meta): ?>
                    <?php
                    $level2Status = (string) $level2Meta['window_level2_status'];
                    $level2Message = match ($level2Status) {
                        'VERIFIED' => 'Fuente verificada para la ventana consultada.',
                        'INCOMPLETE' => 'La información de origen está incompleta para la ventana consultada.',
                        default => 'No es posible verificar completamente la fuente para la ventana consultada.',
                    };
                    ?>
                    <div class="alert alert-secondary" role="status">
                        <strong>Estado de la fuente: <?= htmlspecialchars($level2Status, ENT_QUOTES, 'UTF-8') ?></strong><br>
                        <?= htmlspecialchars($level2Message, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <?php if ($snapshotMeta): ?>
                    <p class="small text-muted">Lectura estable <?= htmlspecialchars((string) $snapshotMeta['status'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) $snapshotMeta['total_records'] ?> registros observados · <?= htmlspecialchars((string) $snapshotMeta['completeness_status'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($timezone): ?>
                    <dl class="row small">
                        <dt class="col-sm-3">Zona horaria API</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars((string) $timezone['timezone'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-3">Ventana UTC</dt>
                        <dd class="col-sm-9"><?= htmlspecialchars((string) $timezone['from_utc'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) $timezone['to_utc'], ENT_QUOTES, 'UTF-8') ?></dd>
                    </dl>
                <?php endif; ?>

                <?php if (($effectiveSchedules ?? []) !== [] || ($effectiveScheduleErrors ?? []) !== []): ?>
                    <section class="card border-secondary mb-4" aria-labelledby="effective-schedule-heading">
                        <div class="card-body">
                            <h2 class="h5" id="effective-schedule-heading">Horario efectivo</h2>
                            <p class="small text-muted">Contexto de horario devuelto por el resolvedor autorizado. No clasifica asistencia ni incidencias.</p>
                            <?php foreach (($effectiveSchedules ?? []) as $workerId => $schedule): ?>
                                <?php
                                $scheduleStatus = (string) ($schedule['estado_jornada'] ?? 'UNKNOWN');
                                $scheduleLabel = match ($scheduleStatus) {
                                    'SCHEDULED' => 'Horario definido',
                                    'DAY_OFF' => 'Descanso programado',
                                    'UNSCHEDULED' => 'Sin horario asignado',
                                    default => 'Horario no disponible',
                                };
                                $localSchedule = is_array($schedule['horario_local'] ?? null) ? $schedule['horario_local'] : null;
                                $segments = is_array($localSchedule['segmentos'] ?? null) ? $localSchedule['segmentos'] : [];
                                $origin = is_array($schedule['origen'] ?? null) ? $schedule['origen'] : [];
                                ?>
                                <div class="border rounded p-3 mb-3">
                                    <h3 class="h6 mb-2">Personal <?= (int) $workerId ?>: <?= htmlspecialchars($scheduleLabel, ENT_QUOTES, 'UTF-8') ?></h3>
                                    <?php if ($localSchedule): ?>
                                        <p class="small mb-2">Zona horaria: <?= htmlspecialchars((string) ($localSchedule['timezone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if ($segments !== []): ?>
                                            <ul class="list-group list-group-flush">
                                                <?php foreach ($segments as $segment): ?>
                                                    <li class="list-group-item px-0">
                                                        Segmento <?= (int) ($segment['ordinal'] ?? 0) ?>:
                                                        <?= htmlspecialchars((string) ($segment['hora_inicio_local'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                        – <?= htmlspecialchars((string) ($segment['hora_fin_local'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                        <?php if ((int) ($segment['dia_offset_fin'] ?? 0) > 0): ?>
                                                            (+<?= (int) $segment['dia_offset_fin'] ?> día<?= (int) $segment['dia_offset_fin'] === 1 ? '' : 's' ?>)
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($origin !== []): ?>
                                        <p class="small text-muted mt-2 mb-0">
                                            Origen: <?= htmlspecialchars((string) ($origin['clave_plantilla'] ?? 'asignación'), ENT_QUOTES, 'UTF-8') ?>
                                            <?php if (isset($origin['version_plantilla'])): ?>
                                                · versión <?= (int) $origin['version_plantilla'] ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php foreach (($effectiveScheduleErrors ?? []) as $workerId => $errorKind): ?>
                                <div class="alert alert-warning" role="status">
                                    Personal <?= (int) $workerId ?>: Horario no disponible.
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (($journeys ?? []) !== [] || ($journeyErrors ?? []) !== []): ?>
                    <section class="card border-primary mb-4" aria-labelledby="journey-heading">
                        <div class="card-body">
                            <h2 class="h5" id="journey-heading">Jornada observada</h2>
                            <p class="small text-muted">Resultado autoritativo devuelto por la API; Portal3 no recalcula pareos ni métricas.</p>
                            <?php foreach (($journeys ?? []) as $workerId => $journey): ?>
                                <?php
                                $context = is_array($journey['context'] ?? null) ? $journey['context'] : [];
                                $schedule = is_array($journey['schedule'] ?? null) ? $journey['schedule'] : [];
                                $source = is_array($journey['source_evidence'] ?? null) ? $journey['source_evidence'] : [];
                                $pairing = is_array($journey['pairing'] ?? null) ? $journey['pairing'] : null;
                                $calculation = is_array($journey['calculation'] ?? null) ? $journey['calculation'] : null;
                                $journeyStatus = (string) ($journey['journey_status'] ?? 'UNKNOWN');
                                $scheduleStatus = (string) ($schedule['estado_jornada'] ?? 'UNKNOWN');
                                $evaluatedEvents = is_array($journey['evaluated_raw_events'] ?? null)
                                    ? array_values($journey['evaluated_raw_events'])
                                    : null;
                                $primarySlots = [
                                    'SLOT_1' => ['label' => 'Entrada 1', 'event' => null],
                                    'SLOT_2' => ['label' => 'Salida 1', 'event' => null],
                                    'SLOT_3' => ['label' => 'Entrada 2', 'event' => null],
                                    'SLOT_4' => ['label' => 'Salida 2', 'event' => null],
                                ];
                                $additionalSegmentOrdinals = [];
                                $surplusEvents = [];
                                $unmatchedEvents = [];
                                $projectionContractComplete = $evaluatedEvents !== null
                                    && ($journey['evaluated'] ?? false) === true
                                    && is_int($journey['evaluated_raw_event_count'] ?? null)
                                    && $journey['evaluated_raw_event_count'] === count($evaluatedEvents);
                                foreach ($evaluatedEvents ?? [] as $projectionEvent) {
                                    if (!is_array($projectionEvent)) {
                                        $projectionContractComplete = false;
                                        continue;
                                    }
                                    $role = $projectionEvent['pairing_role'] ?? null;
                                    $ordinal = $projectionEvent['segment_ordinal'] ?? null;
                                    if (!is_string($role)
                                        || !in_array($role, ['ENTRY', 'EXIT', 'SURPLUS', 'UNMATCHED'], true)
                                        || !array_key_exists('segment_ordinal', $projectionEvent)
                                        || ($ordinal !== null && (!is_int($ordinal) || $ordinal < 1))
                                        || ($role === 'UNMATCHED' && $ordinal !== null)
                                        || ($role !== 'UNMATCHED' && $ordinal === null)) {
                                        $projectionContractComplete = false;
                                        continue;
                                    }
                                    if ($ordinal !== null && $ordinal >= 3) {
                                        $additionalSegmentOrdinals[$ordinal] = true;
                                    }
                                    if ($role === 'SURPLUS') {
                                        $surplusEvents[] = $projectionEvent;
                                    } elseif ($role === 'UNMATCHED') {
                                        $unmatchedEvents[] = $projectionEvent;
                                    } elseif ($ordinal === 1 || $ordinal === 2) {
                                        $slotKey = 'SLOT_' . (($ordinal - 1) * 2 + ($role === 'ENTRY' ? 1 : 2));
                                        if ($primarySlots[$slotKey]['event'] === null) {
                                            $primarySlots[$slotKey]['event'] = $projectionEvent;
                                        }
                                    }
                                }
                                foreach (($pairing['segments'] ?? []) as $pairingSegment) {
                                    $segmentOrdinal = is_array($pairingSegment) ? ($pairingSegment['ordinal'] ?? null) : null;
                                    if (is_int($segmentOrdinal) && $segmentOrdinal >= 3) {
                                        $additionalSegmentOrdinals[$segmentOrdinal] = true;
                                    }
                                }
                                ksort($additionalSegmentOrdinals);
                                $formatProjectionEvent = static function (array $event, string $timezone, string $workDate): array {
                                    $rawTimestamp = (string) ($event['ocurrido_at_utc'] ?? '');
                                    try {
                                        $displayDateTime = (new \DateTimeImmutable($rawTimestamp, new \DateTimeZone('UTC')))
                                            ->setTimezone(new \DateTimeZone($timezone));
                                        return [
                                            'time' => $displayDateTime->format('H:i'),
                                            'date' => $displayDateTime->format('Y-m-d'),
                                            'cross_date' => $displayDateTime->format('Y-m-d') !== $workDate,
                                        ];
                                    } catch (\Throwable) {
                                        return ['time' => $rawTimestamp !== '' ? $rawTimestamp : 'No disponible', 'date' => '', 'cross_date' => false];
                                    }
                                };
                                $ambiguityPresent = $journeyStatus === 'AMBIGUOUS_PAIRING'
                                    || (string) ($pairing['pairing_status'] ?? '') === 'AMBIGUOUS_PAIRING';
                                $journeyMessage = match ($scheduleStatus) {
                                    'UNSCHEDULED' => 'Sin horario efectivo asignado para esta fecha.',
                                    'DAY_OFF' => 'Descanso programado.',
                                    default => in_array((string) ($source['window_level2_status'] ?? ''), ['UNKNOWN', 'INCOMPLETE'], true)
                                        ? 'La información de origen requiere revisión antes de concluir la jornada.'
                                        : 'Resultado de jornada disponible.',
                                };
                                ?>
                                <div class="border rounded p-3 mb-3">
                                    <h3 class="h6">Personal <?= (int) $workerId ?></h3>
                                    <p class="mb-2"><strong><?= htmlspecialchars($journeyMessage, ENT_QUOTES, 'UTF-8') ?></strong></p>
                                    <dl class="row small mb-2">
                                        <dt class="col-sm-3">Estado Journey</dt>
                                        <dd class="col-sm-9"><?= htmlspecialchars($journeyStatus, ENT_QUOTES, 'UTF-8') ?></dd>
                                        <dt class="col-sm-3">Fuente</dt>
                                        <dd class="col-sm-9"><?= htmlspecialchars((string) ($source['window_level2_status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></dd>
                                        <dt class="col-sm-3">Zona horaria</dt>
                                        <dd class="col-sm-9"><?= htmlspecialchars((string) ($context['timezone'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                                    </dl>
                                    <section class="border rounded p-3 mt-3" aria-labelledby="projection-heading-<?= (int) $workerId ?>">
                                        <h4 class="h6" id="projection-heading-<?= (int) $workerId ?>">Proyección compacta de jornada</h4>
                                        <?php if (!$projectionContractComplete): ?>
                                            <div class="alert alert-warning mb-0" role="status">La clasificación autoritativa de marcaciones no está disponible; no se infieren posiciones por horario ni cronología.</div>
                                        <?php else: ?>
                                            <div class="row row-cols-1 row-cols-sm-2 g-2" role="list" aria-label="Entradas y salidas principales">
                                                <?php foreach ($primarySlots as $slot): ?>
                                                    <div class="col" role="listitem">
                                                        <div class="border rounded p-2 h-100">
                                                            <strong><?= htmlspecialchars($slot['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                            <?php if ($slot['event'] === null): ?>
                                                                <p class="small text-muted mb-0 mt-1">Sin evento asociado en esta proyección.</p>
                                                            <?php else: ?>
                                                                <?php $slotDisplay = $formatProjectionEvent($slot['event'], (string) ($context['timezone'] ?? ''), (string) ($context['fecha_local'] ?? '')); ?>
                                                                <p class="mb-0 mt-1"><span class="font-monospace"><?= htmlspecialchars((string) $slotDisplay['time'], ENT_QUOTES, 'UTF-8') ?></span></p>
                                                                <?php if ($slotDisplay['cross_date']): ?>
                                                                    <p class="small text-muted mb-0">Fecha local: <?= htmlspecialchars((string) $slotDisplay['date'], ENT_QUOTES, 'UTF-8') ?></p>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if ($ambiguityPresent): ?>
                                                <div class="alert alert-warning mt-3 mb-0" role="status">Marcación ambigua: la ambigüedad pertenece al segmento o a la Jornada; las etiquetas de cada evento permanecen sin mutación.</div>
                                            <?php endif; ?>
                                            <?php if (is_int($journey['evaluated_raw_event_count'] ?? null) && $journey['evaluated_raw_event_count'] > 4): ?>
                                                <p class="small mt-3 mb-0"><strong>Ver todas las marcaciones (<?= (int) $journey['evaluated_raw_event_count'] ?>)</strong></p>
                                            <?php endif; ?>
                                            <?php if ($additionalSegmentOrdinals !== []): ?>
                                                <p class="small mt-2 mb-0"><strong>+<?= count($additionalSegmentOrdinals) ?> segmentos adicionales</strong></p>
                                            <?php endif; ?>
                                            <?php if ($surplusEvents !== [] || $unmatchedEvents !== []): ?>
                                                <p class="small text-muted mt-2 mb-0">Las marcaciones adicionales y sin emparejar permanecen en el detalle completo.</p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </section>
                                    <details class="border rounded p-3 mt-3">
                                        <summary>Detalle de auditoría</summary>
                                        <dl class="row small mt-3 mb-0">
                                            <dt class="col-sm-4">Trabajador canónico</dt>
                                            <dd class="col-sm-8"><?= (int) ($context['id_personal'] ?? 0) ?></dd>
                                            <dt class="col-sm-4">Centro de trabajo</dt>
                                            <dd class="col-sm-8"><?= (int) ($context['id_estacion'] ?? 0) ?></dd>
                                            <dt class="col-sm-4">Fecha local</dt>
                                            <dd class="col-sm-8"><?= htmlspecialchars((string) ($context['fecha_local'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                                            <dt class="col-sm-4">Horario aplicado</dt>
                                            <dd class="col-sm-8"><?= htmlspecialchars((string) ($schedule['estado_jornada'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></dd>
                                            <dt class="col-sm-4">Plantilla / versión</dt>
                                            <dd class="col-sm-8"><?= htmlspecialchars((string) (($schedule['origen']['clave_plantilla'] ?? 'No aplica')), ENT_QUOTES, 'UTF-8') ?> / <?= isset($schedule['origen']['version_plantilla']) ? (int) $schedule['origen']['version_plantilla'] : 'No aplica' ?></dd>
                                            <dt class="col-sm-4">Estado de fuente</dt>
                                            <dd class="col-sm-8"><?= htmlspecialchars((string) ($source['window_level2_status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></dd>
                                            <dt class="col-sm-4">Referencia Level 2</dt>
                                            <dd class="col-sm-8"><?= htmlspecialchars((string) ($source['level2_evaluation_ref'] ?? 'No aplica'), ENT_QUOTES, 'UTF-8') ?></dd>
                                            <dt class="col-sm-4">Referencia de evidencia</dt>
                                            <dd class="col-sm-8"><?= htmlspecialchars((string) ($source['snapshot_audit_ref'] ?? 'No aplica'), ENT_QUOTES, 'UTF-8') ?></dd>
                                        </dl>
                                        <?php if ($pairing !== null): ?>
                                            <h4 class="h6 mt-3">Interpretación técnica</h4>
                                            <p class="small mb-1">Estado: <?= htmlspecialchars((string) ($pairing['pairing_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="small mb-1">Revisión de interpretación: <?= (int) ($pairing['revision_numero'] ?? 0) ?></p>
                                            <p class="small mb-1">Versión del algoritmo: <?= htmlspecialchars((string) ($pairing['algorithm_version'] ?? 'No aplica'), ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="small mb-1">Creada en UTC: <?= htmlspecialchars((string) ($pairing['creado_at_utc'] ?? 'No aplica'), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php else: ?>
                                            <p class="small mt-3 mb-1">Procedencia P1: No aplica; la evaluación de jornada no se ejecutó.</p>
                                        <?php endif; ?>
                                        <?php if ($calculation !== null): ?>
                                            <h4 class="h6 mt-3">Cálculo técnico</h4>
                                            <p class="small mb-1">Estado: <?= htmlspecialchars((string) ($calculation['estado_calculo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="small mb-1">Revisión de cálculo: <?= (int) ($calculation['revision_numero'] ?? 0) ?></p>
                                            <p class="small mb-1">Versión del cálculo: <?= htmlspecialchars((string) ($calculation['version_algoritmo'] ?? 'No aplica'), ENT_QUOTES, 'UTF-8') ?></p>
                                            <p class="small mb-1">Creada en UTC: <?= htmlspecialchars((string) ($calculation['creado_at_utc'] ?? 'No aplica'), ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php else: ?>
                                            <p class="small mb-1">Procedencia P2: No aplica; la evaluación de jornada no se ejecutó.</p>
                                        <?php endif; ?>
                                        <?php
                                        $auditProvenance = is_array($journey['provenance'] ?? null) ? $journey['provenance'] : [];
                                        $auditEvents = $journey['evaluated_raw_events'] ?? null;
                                        $auditEventCount = $journey['evaluated_raw_event_count'] ?? null;
                                        ?>
                                        <h4 class="h6 mt-3">Evidencia evaluada</h4>
                                        <?php if (is_array($auditEvents) && is_int($auditEventCount)): ?>
                                            <p class="small">Marcaciones consideradas: <?= $auditEventCount ?></p>
                                            <?php if ($auditEvents !== []): ?>
                                                <ul class="list-group list-group-flush small">
                                                    <?php foreach ($auditEvents as $auditEvent): ?>
                                                    <li class="list-group-item px-0">
                                                        <span class="font-monospace"><?= htmlspecialchars((string) ($auditEvent['event_uuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                            · <?= htmlspecialchars((string) match ((string) ($auditEvent['pairing_role'] ?? '')) {
                                                                'ENTRY' => 'Entrada',
                                                                'EXIT' => 'Salida',
                                                                'SURPLUS' => 'Marcación adicional',
                                                                'UNMATCHED' => 'Sin emparejar',
                                                                default => 'Clasificación no disponible',
                                                            }, ENT_QUOTES, 'UTF-8') ?>
                                                            · <?= htmlspecialchars((string) ($auditEvent['ocurrido_at_utc'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                            · <?= htmlspecialchars((string) ($auditEvent['metodo_captura'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                            <?php if (isset($auditEvent['id_dispositivo']) && (int) $auditEvent['id_dispositivo'] > 0): ?>
                                                                · dispositivo <?= (int) $auditEvent['id_dispositivo'] ?>
                                                            <?php else: ?>
                                                                · dispositivo no disponible
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p class="small mb-1">Marcaciones consideradas: No aplica; la evaluación de jornada no se ejecutó.</p>
                                        <?php endif; ?>
                                        <?php if ($pairing !== null && is_array($pairing['segments'] ?? null) && $pairing['segments'] !== []): ?>
                                            <h4 class="h6 mt-3">Vínculos de interpretación</h4>
                                            <ul class="list-group list-group-flush small">
                                                <?php foreach ($pairing['segments'] as $segment): ?>
                                                    <li class="list-group-item px-0">
                                                        Segmento <?= (int) ($segment['ordinal'] ?? 0) ?> · entrada <?= htmlspecialchars((string) ($segment['entry_event_uuid'] ?? 'No aplica'), ENT_QUOTES, 'UTF-8') ?> · salida <?= htmlspecialchars((string) ($segment['exit_event_uuid'] ?? 'No aplica'), ENT_QUOTES, 'UTF-8') ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                        <p class="small text-muted mt-3 mb-0">Generado por el API: <?= htmlspecialchars((string) ($auditProvenance['response_generated_at_utc'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?> · Versión de fuente: <?= htmlspecialchars((string) ($auditProvenance['source_completeness_contract_version'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?> · Regla administrativa: <?= $auditProvenance['policy_rule_version'] === null ? 'No aplica' : htmlspecialchars((string) $auditProvenance['policy_rule_version'], ENT_QUOTES, 'UTF-8') ?></p>
                                    </details>
                                </div>
                            <?php endforeach; ?>
                            <?php foreach (($journeyErrors ?? []) as $workerId => $errorKind): ?>
                                <div class="alert alert-warning" role="status">Personal <?= (int) $workerId ?>: el resultado de Jornada no está disponible (<?= htmlspecialchars((string) $errorKind, ENT_QUOTES, 'UTF-8') ?>).</div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php
                $administrativeWorkerIds = array_unique(array_merge(
                    array_map('intval', array_keys($administrativeIncidences ?? [])),
                    array_map('intval', array_keys($administrativeErrors ?? [])),
                    array_map('intval', array_keys($administrativeEvaluations ?? [])),
                    array_map('intval', array_keys($administrativeEvaluationErrors ?? [])),
                    array_map('intval', array_keys($journeys ?? []))
                ));
                sort($administrativeWorkerIds);
                ?>
                <?php if ($administrativeWorkerIds !== []): ?>
                    <section class="card border-secondary mb-4" aria-labelledby="administrative-heading">
                        <div class="card-body">
                            <h2 class="h5" id="administrative-heading">Contexto administrativo</h2>
                            <p class="small text-muted">Datos de lectura devueltos por la API Checador V2. No crean, autorizan ni resuelven incidencias ni producen consecuencias administrativas.</p>
                            <?php foreach ($administrativeWorkerIds as $workerId): ?>
                                <div class="border rounded p-3 mb-3">
                                    <h3 class="h6">Personal <?= (int) $workerId ?></h3>
                                    <?php if (isset($administrativeErrors[$workerId])): ?>
                                        <div class="alert alert-warning" role="status">La información administrativa no está disponible para esta consulta.</div>
                                    <?php else: ?>
                                        <?php $incidences = $administrativeIncidences[$workerId] ?? []; ?>
                                        <?php if ($incidences === []): ?>
                                            <p class="small text-muted">No hay incidencias administrativas registradas para el rango consultado.</p>
                                        <?php else: ?>
                                            <?php foreach ($incidences as $incidence): ?>
                                                <details class="border rounded p-3 mb-2">
                                                    <summary>
                                                        <?= htmlspecialchars((string) ($incidence['category'] ?? 'INCIDENCIA'), ENT_QUOTES, 'UTF-8') ?>
                                                        · <?= htmlspecialchars((string) ($incidence['current_status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?>
                                                        · revisión <?= (int) ($incidence['current_revision'] ?? 0) ?>
                                                    </summary>
                                                    <dl class="row small mt-3 mb-0">
                                                        <dt class="col-sm-4">Incidencia</dt>
                                                        <dd class="col-sm-8 font-monospace text-break"><?= htmlspecialchars((string) ($incidence['uuid_incidencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                                                        <dt class="col-sm-4">Periodo efectivo</dt>
                                                        <dd class="col-sm-8"><?= htmlspecialchars((string) ($incidence['date_range']['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars((string) ($incidence['date_range']['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                                                        <dt class="col-sm-4">Motivo vigente</dt>
                                                        <dd class="col-sm-8"><?= htmlspecialchars((string) ($incidence['revisions'][count($incidence['revisions']) - 1]['reason'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></dd>
                                                        <dt class="col-sm-4">Clasificación de fuente</dt>
                                                        <dd class="col-sm-8"><?= htmlspecialchars((string) ($incidence['source_classification'] ?? 'API_V2'), ENT_QUOTES, 'UTF-8') ?></dd>
                                                    </dl>
                                                    <h4 class="h6 mt-3">Historial append-only</h4>
                                                    <?php if (($incidence['revisions'] ?? []) === []): ?>
                                                        <p class="small text-muted">Historial no disponible.</p>
                                                    <?php else: ?>
                                                        <ol class="small mb-0">
                                                            <?php foreach ($incidence['revisions'] as $revision): ?>
                                                                <li class="mb-2">
                                                                    <strong>Revisión <?= (int) ($revision['revision_number'] ?? 0) ?> · <?= htmlspecialchars((string) ($revision['status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></strong><br>
                                                                    <?= htmlspecialchars((string) ($revision['reason'] ?? 'Sin motivo'), ENT_QUOTES, 'UTF-8') ?>
                                                                    · <?= htmlspecialchars((string) ($revision['created_at_utc'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ol>
                                                    <?php endif; ?>
                                                    <?php
                                                    $incidenceUuid = (string) ($incidence['uuid_incidencia'] ?? '');
                                                    $incidenceStatus = (string) ($incidence['current_status'] ?? 'UNKNOWN');
                                                    $incidenceRevision = (int) ($incidence['current_revision'] ?? 0);
                                                    $incidenceLifecycleKey = $lifecycleKeys[$incidenceUuid] ?? [];
                                                    $canResolve = !empty($lifecyclePermissions['resolve']) && !in_array($incidenceStatus, ['CANCELLED', 'REVOKED'], true);
                                                    $canAuthorize = !empty($lifecyclePermissions['authorize']) && !in_array($incidenceStatus, ['CANCELLED', 'REVOKED', 'REJECTED'], true);
                                                    $canReject = !empty($lifecyclePermissions['reject']) && !in_array($incidenceStatus, ['CANCELLED', 'REVOKED', 'REJECTED'], true);
                                                    $canRevoke = !empty($lifecyclePermissions['revoke']) && !in_array($incidenceStatus, ['CANCELLED', 'REVOKED'], true);
                                                    ?>
                                                    <?php if ($canResolve || $canAuthorize || $canReject || $canRevoke): ?>
                                                        <div class="border-top mt-3 pt-3" aria-label="Acciones administrativas">
                                                            <p class="small text-muted mb-2">Las acciones requieren confirmación y vuelven a consultar el estado canónico.</p>
                                                            <?php if ($canResolve): ?>
                                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/<?= rawurlencode($incidenceUuid) ?>/resolve" class="border rounded p-2 mb-2">
                                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                                    <input type="hidden" name="date" value="<?= htmlspecialchars((string) ($selectedDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input type="hidden" name="expected_current_revision" value="<?= $incidenceRevision ?>">
                                                                    <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars((string) ($incidenceLifecycleKey['resolve'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input type="hidden" name="resolution_action" value="CONFIRM_INCIDENCE_PREVALENCE">
                                                                    <label class="form-label small" for="resolve-notes-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>">Resolver / revisar (motivo)</label>
                                                                    <textarea class="form-control form-control-sm mb-2" id="resolve-notes-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>" name="resolution_notes" required></textarea>
                                                                    <label class="form-label small" for="resolve-evidence-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>">Evidencia (opcional)</label>
                                                                    <input class="form-control form-control-sm mb-2" id="resolve-evidence-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>" name="evidence_reference">
                                                                    <label class="small d-block mb-2"><input type="checkbox" name="confirm_action" value="1" required> Confirmo esta revisión administrativa.</label>
                                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Resolver / revisar</button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <?php if ($canAuthorize): ?>
                                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/<?= rawurlencode($incidenceUuid) ?>/authorize" class="border rounded p-2 mb-2">
                                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                                    <input type="hidden" name="date" value="<?= htmlspecialchars((string) ($selectedDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input type="hidden" name="expected_current_revision" value="<?= $incidenceRevision ?>">
                                                                    <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars((string) ($incidenceLifecycleKey['authorize'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <label class="form-label small" for="authorize-notes-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>">Autorizar (nota opcional)</label>
                                                                    <textarea class="form-control form-control-sm mb-2" id="authorize-notes-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>" name="action_notes"></textarea>
                                                                    <label class="small d-block mb-2"><input type="checkbox" name="confirm_action" value="1" required> Confirmo la autorización explícita.</label>
                                                                    <button type="submit" class="btn btn-sm btn-outline-success">Autorizar</button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <?php if ($canReject): ?>
                                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/<?= rawurlencode($incidenceUuid) ?>/reject" class="border rounded p-2 mb-2">
                                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                                    <input type="hidden" name="date" value="<?= htmlspecialchars((string) ($selectedDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input type="hidden" name="expected_current_revision" value="<?= $incidenceRevision ?>">
                                                                    <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars((string) ($incidenceLifecycleKey['reject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <label class="form-label small" for="reject-reason-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>">Rechazar (motivo)</label>
                                                                    <textarea class="form-control form-control-sm mb-2" id="reject-reason-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>" name="rejection_reason" required></textarea>
                                                                    <label class="small d-block mb-2"><input type="checkbox" name="confirm_action" value="1" required> Confirmo el rechazo explícito.</label>
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Rechazar</button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <?php if ($canRevoke): ?>
                                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/incidencias/<?= rawurlencode($incidenceUuid) ?>/revoke" class="border rounded p-2">
                                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                                    <input type="hidden" name="date" value="<?= htmlspecialchars((string) ($selectedDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <input type="hidden" name="expected_current_revision" value="<?= $incidenceRevision ?>">
                                                                    <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars((string) ($incidenceLifecycleKey['revoke'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                    <label class="form-label small" for="revoke-reason-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>">Revocar / cancelar (motivo)</label>
                                                                    <textarea class="form-control form-control-sm mb-2" id="revoke-reason-<?= htmlspecialchars($incidenceUuid, ENT_QUOTES, 'UTF-8') ?>" name="revocation_reason" required></textarea>
                                                                    <label class="small d-block mb-2"><input type="checkbox" name="confirm_action" value="1" required> Confirmo la revocación explícita.</label>
                                                                    <button type="submit" class="btn btn-sm btn-outline-warning">Revocar / cancelar</button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </details>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (isset($administrativeEvaluationErrors[$workerId])): ?>
                                        <div class="alert alert-warning" role="status">La evaluación administrativa no está disponible para esta consulta.</div>
                                    <?php elseif (isset($administrativeEvaluations[$workerId])): ?>
                                        <?php $evaluationData = $administrativeEvaluations[$workerId]; ?>
                                        <?php if (($evaluationData['evaluation_exists'] ?? false) !== true): ?>
                                            <p class="small text-muted">No hay evaluación administrativa registrada.</p>
                                        <?php else: ?>
                                            <?php $evaluation = is_array($evaluationData['evaluation'] ?? null) ? $evaluationData['evaluation'] : []; ?>
                                            <details class="border rounded p-3 mt-3">
                                                <summary>Evaluación administrativa existente</summary>
                                                <dl class="row small mt-3 mb-0">
                                                    <dt class="col-sm-4">Estado</dt>
                                                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($evaluation['administrative_status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></dd>
                                                    <dt class="col-sm-4">Revisión humana</dt>
                                                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($evaluation['human_review_status'] ?? 'UNKNOWN'), ENT_QUOTES, 'UTF-8') ?></dd>
                                                    <dt class="col-sm-4">Proveniencia</dt>
                                                    <?php $policyRuleVersion = array_key_exists('policy_rule_version', $evaluation) ? $evaluation['policy_rule_version'] : null; ?>
                                                    <dd class="col-sm-8 text-break"><?= htmlspecialchars((string) ($evaluation['fingerprint'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($evaluation['algorithm_version'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · política <?= $evaluation['policy_version'] === null ? 'No definida' : htmlspecialchars((string) $evaluation['policy_version'], ENT_QUOTES, 'UTF-8') ?> · regla <?= $policyRuleVersion === null ? 'No definida' : htmlspecialchars((string) $policyRuleVersion, ENT_QUOTES, 'UTF-8') ?></dd>
                                                    <dt class="col-sm-4">Referencia de cálculo</dt>
                                                    <dd class="col-sm-8 font-monospace"><?= (int) ($evaluation['associated_calculation_revision_id'] ?? 0) ?></dd>
                                                    <dt class="col-sm-4">Creada en UTC</dt>
                                                    <dd class="col-sm-8"><?= htmlspecialchars((string) ($evaluation['created_at_utc'] ?? ''), ENT_QUOTES, 'UTF-8') ?></dd>
                                                </dl>
                                            </details>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php
                                    $evaluationKeySet = $evaluationKeys[$workerId] ?? [];
                                    $expectedCalculationId = (int) ($evaluationKeySet['expected_calculation_revision_id'] ?? 0);
                                    $canEvaluate = !empty($evaluationPermissions['evaluate']);
                                    $canAuthorizeFalta = !empty($evaluationPermissions['authorize_falta']);
                                    ?>
                                    <?php if ($canEvaluate || $canAuthorizeFalta): ?>
                                        <div class="border-top mt-3 pt-3" aria-label="Evaluación administrativa">
                                            <p class="small text-muted mb-2">La evaluación consulta la Jornada y las incidencias persistidas por la API. No recalcula marcaciones, no modifica incidencias y no produce nómina.</p>
                                            <?php if ($canEvaluate): ?>
                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/evaluations/evaluate" class="border rounded p-2 mb-2">
                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                    <input type="hidden" name="date" value="<?= htmlspecialchars((string) ($selectedDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?php if ($expectedCalculationId > 0): ?><input type="hidden" name="expected_calculation_revision_id" value="<?= $expectedCalculationId ?>"><?php endif; ?>
                                                    <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars((string) ($evaluationKeySet['evaluate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <label class="small d-block mb-2"><input type="checkbox" name="confirm_evaluation" value="1" required> Confirmo ejecutar la evaluación administrativa para este trabajador y fecha.</label>
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">Ejecutar evaluación</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($canAuthorizeFalta): ?>
                                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/evaluations/evaluate" class="border border-warning rounded p-2">
                                                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                                    <input type="hidden" name="date" value="<?= htmlspecialchars((string) ($selectedDate ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?php if ($expectedCalculationId > 0): ?><input type="hidden" name="expected_calculation_revision_id" value="<?= $expectedCalculationId ?>"><?php endif; ?>
                                                    <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars((string) ($evaluationKeySet['authorize_falta'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="authorize_falta" value="1">
                                                    <p class="small mb-2"><strong>Autorización final de FALTA</strong>: requiere revisar el contexto mostrado y una confirmación humana explícita. Nunca se activa automáticamente.</p>
                                                    <label class="small d-block mb-2"><input type="checkbox" name="confirm_evaluation" value="1" required> Confirmo explícitamente autorizar la clasificación FALTA para este trabajador y fecha.</label>
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">Autorizar FALTA explícitamente</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($events === []): ?>
                    <p class="text-muted">No se observaron marcaciones en este conjunto consultado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Fecha UTC</th>
                                    <th>Personal</th>
                                    <th>Método</th>
                                    <th>Integridad</th>
                                    <th>Evento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                    <?php $integrity = (string) (($event['integrity']['status'] ?? 'UNVERIFIABLE')); ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($event['ocurrido_at_utc'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($event['id_personal_resuelto'] ?? 'No resuelto'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($event['metodo_captura'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($integrity, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($event['event_uuid'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($pagination && $paginationLinks): ?>
                    <nav aria-label="Paginación de marcaciones observadas">
                        <span class="small text-muted">Página <?= (int) $pagination['current_page'] ?> del conjunto estable</span>
                        <?php foreach ($paginationLinks as $link): ?>
                            <a class="btn btn-sm btn-outline-secondary ms-2" href="<?= htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="container" class="mt-4 mb-5">
    <?php
    $returnQuery = [];
    if (($selectedWorkplaceId ?? null) !== null) {
        $returnQuery['workplace_id'] = (int) $selectedWorkplaceId;
    }
    if (($selectedDate ?? '') !== '') {
        $returnQuery['date'] = (string) $selectedDate;
    }
    if (($selectedWorkerId ?? null) !== null) {
        $returnQuery['worker_id'] = (int) $selectedWorkerId;
    }
    $returnHref = '/departamento-operativo/recursos-humanos/checador'
        . ($returnQuery === [] ? '' : '?' . http_build_query($returnQuery));
    $configurationHref = '/departamento-operativo/recursos-humanos/checador/configuracion'
        . (($selectedWorkplaceId ?? null) !== null ? '?' . http_build_query(['workplace_id' => (int) $selectedWorkplaceId]) : '');
    $historyBaseQuery = [
        'schedule_history' => '1',
        'workplace_id' => $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '',
        'date' => (string) ($selectedDate ?? ''),
        'worker_id' => $selectedWorkerId !== null ? (int) $selectedWorkerId : '',
    ];
    $validityStatus = (string) ($validityCheck['status'] ?? 'not_checked');
    $validityMessage = match ($validityStatus) {
        'matched' => 'La asignación histórica coincide con el horario resuelto para esta fecha.',
        'ambiguous' => 'Más de una asignación cubre el mismo día de la semana. No se selecciona una automáticamente.',
        'incomplete' => 'La respuesta puede estar truncada; no se confirma una vigencia única.',
        'inconsistent' => 'La evidencia de vigencia no coincide con el horario resuelto. El horario no se presenta como definitivo.',
        'no_assignment' => 'El resolvedor no encontró asignación para este día; esto no clasifica asistencia ni ausencia.',
        'resolver_unknown_with_assignment' => 'Existe una asignación para la fecha, pero el resolvedor no pudo establecer el horario.',
        'history_unavailable' => 'No se pudo corroborar el horario con el historial de asignaciones.',
        'unknown' => 'El resolvedor no pudo determinar el horario con la información disponible.',
        default => 'Selecciona estación, trabajador y fecha para consultar la vigencia.',
    };
    $scheduleStatus = (string) ($effectiveSchedule['estado_jornada'] ?? 'UNKNOWN');
    $scheduleLabel = match ($scheduleStatus) {
        'SCHEDULED' => 'Horario definido por el resolvedor',
        'DAY_OFF' => 'Descanso explícito por el resolvedor',
        'UNSCHEDULED' => 'Sin asignación para el día según el resolvedor',
        default => 'Horario no determinado',
    };
    $effectiveOrigin = is_array($effectiveSchedule['origen'] ?? null) ? $effectiveSchedule['origen'] : [];
    $applicableAssignment = is_array($validityCheck['assignment'] ?? null) ? $validityCheck['assignment'] : null;
    $effectiveScheduleIsConfirmed = $validityStatus === 'matched';
    ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">Consulta de vigencias de asignaciones de horario. Es una lectura; no modifica horarios ni marcaciones.</p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="<?= htmlspecialchars($configurationHref, ENT_QUOTES, 'UTF-8') ?>">Configuración</a>
            <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($returnHref, ENT_QUOTES, 'UTF-8') ?>">Volver a Checador</a>
        </div>
    </div>

    <?php if (($demoMode ?? false) === true): ?>
        <div class="alert alert-warning" role="status">
            <strong>Modo de demostración activo.</strong>
            El demo no contiene asignaciones históricas; esta pantalla no consulta la API real y no presenta fixtures como vigencias persistidas.
        </div>
    <?php endif; ?>

    <section class="card mb-4" aria-labelledby="schedule-history-filter-heading">
        <div class="card-body">
            <h2 class="h5" id="schedule-history-filter-heading">Consultar vigencia</h2>
            <form method="get" action="/departamento-operativo/recursos-humanos/checador" class="row g-3">
                <input type="hidden" name="schedule_history" value="1">
                <input type="hidden" name="workplace_id" value="<?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '' ?>">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="schedule-history-worker">Trabajador canónico</label>
                    <input class="form-control" id="schedule-history-worker" name="worker_id" type="number" min="1" step="1" value="<?= $selectedWorkerId !== null ? (int) $selectedWorkerId : '' ?>" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="schedule-history-date">Fecha local consultada</label>
                    <input class="form-control" id="schedule-history-date" name="date" type="date" value="<?= htmlspecialchars((string) ($selectedDate ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end">
                    <button class="btn btn-primary" type="submit">Consultar vigencia</button>
                </div>
            </form>
            <p class="form-text mb-0 mt-2">
                Estación/localidad autorizada: <?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : 'Selecciona una en el selector institucional' ?>
                <?php if (($selectedWorkplaceName ?? '') !== ''): ?> · <?= htmlspecialchars((string) $selectedWorkplaceName, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
            </p>
        </div>
    </section>

    <?php if (($selectedWorkplaceId ?? null) === null): ?>
        <div class="alert alert-info" role="status">Selecciona una estación o localidad autorizada en el selector institucional.</div>
    <?php elseif (($selectedWorkerId ?? null) === null || ($selectedDate ?? '') === ''): ?>
        <div class="alert alert-info" role="status">Captura un trabajador y una fecha local para consultar sus vigencias.</div>
    <?php elseif (($effectiveScheduleError ?? null) !== null): ?>
        <div class="alert alert-warning" role="status">
            <strong>Horario efectivo no disponible.</strong><br>
            <?= htmlspecialchars((string) ($effectiveScheduleErrorMessage ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php elseif (is_array($effectiveSchedule)): ?>
        <section class="card mb-4" aria-labelledby="effective-history-heading">
            <div class="card-body">
                <h2 class="h5" id="effective-history-heading">Resultado para <?= htmlspecialchars((string) $selectedDate, ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-muted mb-2">Trabajador <?= (int) $selectedWorkerId ?> · <?= htmlspecialchars((string) ($selectedWorkplaceName !== '' ? $selectedWorkplaceName : 'Localidad ' . (int) $selectedWorkplaceId), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="alert alert-<?= $effectiveScheduleIsConfirmed ? 'success' : (($validityStatus === 'ambiguous' || $validityStatus === 'inconsistent') ? 'danger' : 'warning') ?>" role="status">
                    <strong><?= htmlspecialchars($validityMessage, ENT_QUOTES, 'UTF-8') ?></strong>
                    <div class="small mt-1">Respuesta del resolvedor: <?= htmlspecialchars($scheduleLabel, ENT_QUOTES, 'UTF-8') ?>.</div>
                </div>

                <?php if ($effectiveOrigin !== []): ?>
                    <dl class="row small mb-3">
                        <dt class="col-sm-4">ID de asignación resuelta</dt>
                        <dd class="col-sm-8"><?= isset($effectiveOrigin['id_asignacion']) ? (int) $effectiveOrigin['id_asignacion'] : 'No disponible' ?></dd>
                        <dt class="col-sm-4">Plantilla de origen</dt>
                        <dd class="col-sm-8">
                            <?= htmlspecialchars((string) ($effectiveOrigin['clave_plantilla'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?>
                            <?php if (isset($effectiveOrigin['version_plantilla'])): ?> · versión <?= (int) $effectiveOrigin['version_plantilla'] ?><?php endif; ?>
                            <?php if (isset($effectiveOrigin['id_plantilla'])): ?> · ID <?= (int) $effectiveOrigin['id_plantilla'] ?><?php endif; ?>
                        </dd>
                    </dl>
                <?php endif; ?>

                <?php if ($effectiveScheduleIsConfirmed && $scheduleStatus === 'SCHEDULED' && is_array($effectiveSchedule['horario_local'] ?? null)): ?>
                    <p class="small">Zona horaria laboral contractual: <?= htmlspecialchars((string) ($effectiveSchedule['horario_local']['timezone'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <caption class="visually-hidden">Segmentos del horario efectivo para la fecha consultada</caption>
                            <thead><tr><th scope="col">Segmento</th><th scope="col">Tipo</th><th scope="col">Inicio local</th><th scope="col">Fin local</th><th scope="col">Fin otro día</th></tr></thead>
                            <tbody>
                                <?php foreach (($effectiveSchedule['horario_local']['segmentos'] ?? []) as $segment): ?>
                                    <tr>
                                        <td><?= (int) ($segment['ordinal'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars((string) ($segment['tipo'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($segment['hora_inicio_local'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($segment['hora_fin_local'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) ($segment['dia_offset_fin'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($effectiveScheduleIsConfirmed && $scheduleStatus === 'DAY_OFF'): ?>
                    <p class="mb-0">La asignación coincidente registra un descanso explícito para el día consultado.</p>
                <?php elseif (!$effectiveScheduleIsConfirmed && is_array($effectiveSchedule['horario_local'] ?? null)): ?>
                    <p class="small text-muted mb-0">El resolvedor devolvió segmentos, pero la vigencia no pudo corroborarse. No se presentan como horario confirmado.</p>
                <?php endif; ?>

                <?php if ($applicableAssignment !== null): ?>
                    <div class="border rounded p-3 mt-3">
                        <h3 class="h6">Registro de vigencia relacionado</h3>
                        <p class="small mb-0">
                            Asignación <?= (int) ($applicableAssignment['id'] ?? 0) ?> ·
                            <?= htmlspecialchars((string) ($applicableAssignment['dia_semana'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
                            <?= ($applicableAssignment['id_plantilla'] ?? null) === null ? 'Descanso explícito (sin plantilla)' : 'Plantilla ID ' . (int) $applicableAssignment['id_plantilla'] ?> ·
                            Desde <?= htmlspecialchars((string) ($applicableAssignment['valido_desde'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (incl.) ·
                            Hasta <?= ($applicableAssignment['valido_hasta'] ?? null) === null ? 'abierta' : htmlspecialchars((string) $applicableAssignment['valido_hasta'], ENT_QUOTES, 'UTF-8') . ' (excl.)' ?> ·
                            Estado del registro: <?= (int) ($applicableAssignment['es_activo'] ?? 0) === 1 ? 'activo' : 'inactivo/histórico' ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (($historyError ?? null) !== null): ?>
        <div class="alert alert-warning" role="status">
            <strong>Historial de asignaciones no disponible.</strong><br>
            <?= htmlspecialchars((string) ($historyErrorMessage ?? ''), ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php elseif (($selectedWorkplaceId ?? null) !== null && ($selectedWorkerId ?? null) !== null && ($selectedDate ?? '') !== '' && ($demoMode ?? false) !== true): ?>
        <section class="card" aria-labelledby="assignment-history-heading">
            <div class="card-body">
                <h2 class="h5" id="assignment-history-heading">Historial de asignaciones</h2>
                <p class="small text-muted">Intervalos efectivos semiabiertos: inicio incluido y fin excluido. El listado es paginado; la API no entrega un total en este contrato.</p>
                <?php if (($historyItems ?? []) === []): ?>
                    <p class="mb-0">La API no devolvió filas para esta página. Esto no demuestra por sí solo que no exista horario ni sustituye la respuesta del resolvedor.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <caption class="visually-hidden">Historial de asignaciones de horario por trabajador y localidad</caption>
                            <thead>
                                <tr><th scope="col">Asignación</th><th scope="col">Día</th><th scope="col">Plantilla</th><th scope="col">Desde (incl.)</th><th scope="col">Hasta (excl.)</th><th scope="col">Estado</th><th scope="col">Registrada (UTC)</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historyItems as $assignment): ?>
                                    <?php $isRelated = (int) ($applicableAssignment['id'] ?? 0) > 0 && (int) $applicableAssignment['id'] === (int) $assignment['id']; ?>
                                    <tr<?= $isRelated ? ' class="table-primary"' : '' ?>>
                                        <td><?= (int) $assignment['id'] ?><?= $isRelated ? ' · aplicable' : '' ?></td>
                                        <td><?= htmlspecialchars((string) $assignment['dia_semana'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $assignment['id_plantilla'] === null ? 'Descanso explícito' : 'ID ' . (int) $assignment['id_plantilla'] ?></td>
                                        <td><?= htmlspecialchars((string) $assignment['valido_desde'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $assignment['valido_hasta'] === null ? 'Abierta' : htmlspecialchars((string) $assignment['valido_hasta'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $assignment['es_activo'] === 1 ? 'Activa en el registro' : 'Inactiva / histórica' ?></td>
                                        <td><?= htmlspecialchars((string) ($assignment['registrado_at_utc'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Paginación del historial de horarios">
                        <?php if (!empty($historyHasPrevious)): ?>
                            <?php $previousQuery = $historyBaseQuery + ['schedule_history_page' => $historyPage - 1]; ?>
                            <a class="btn btn-outline-secondary btn-sm" href="/departamento-operativo/recursos-humanos/checador?<?= htmlspecialchars(http_build_query($previousQuery), ENT_QUOTES, 'UTF-8') ?>">Anterior</a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <span class="small text-muted">Página <?= (int) $historyPage ?></span>
                        <?php if (!empty($historyHasNext)): ?>
                            <?php $nextQuery = $historyBaseQuery + ['schedule_history_page' => $historyPage + 1]; ?>
                            <a class="btn btn-outline-primary btn-sm" href="/departamento-operativo/recursos-humanos/checador?<?= htmlspecialchars(http_build_query($nextQuery), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

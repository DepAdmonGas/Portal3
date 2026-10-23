<div id="container" class="mt-4 mb-5">
    <?php
    $weekEnd = $weekDates !== [] ? (string) end($weekDates)['date'] : '';
    $dailyHref = '/departamento-operativo/recursos-humanos/checador' . (($selectedWorkplaceId ?? null) !== null ? '?' . http_build_query(['workplace_id' => (int) $selectedWorkplaceId, 'date' => $weekStart]) : '');
    $configurationHref = '/departamento-operativo/recursos-humanos/checador/configuracion'
        . (($selectedWorkplaceId ?? null) !== null ? '?' . http_build_query(['workplace_id' => (int) $selectedWorkplaceId]) : '');
    $matrixIncomplete = ($coverage ?? '') === 'partial';
    $validWeekDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $weekStart, new \DateTimeZone('UTC'));
    $previousWeek = $validWeekDate === false ? '' : $validWeekDate->modify('-7 days')->format('Y-m-d');
    $nextWeek = $validWeekDate === false ? '' : $validWeekDate->modify('+7 days')->format('Y-m-d');
    $workplaceZone = \App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone ?? null)
        ? new \DateTimeZone((string) $timezone)
        : null;
    $currentWeek = $workplaceZone === null
        ? null
        : \App\Services\ChecadorWeeklyScheduleService::monday((new \DateTimeImmutable('now', $workplaceZone))->format('Y-m-d'));
    ?>
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Programación semanal</h1>
            <p class="text-muted mb-0">Turnos y descansos por persona.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php if (($demoMode ?? false) === true): ?><span class="badge rounded-pill text-bg-secondary" title="Datos sintéticos aislados">Demo</span><?php endif; ?>
            <a class="btn btn-outline-primary btn-sm" href="<?= htmlspecialchars($configurationHref, ENT_QUOTES, 'UTF-8') ?>">Configuración</a>
            <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($dailyHref, ENT_QUOTES, 'UTF-8') ?>">Volver a asistencia diaria</a>
        </div>
    </header>

    <section class="card mb-3" aria-labelledby="week-filter-heading">
        <div class="card-body d-flex flex-wrap align-items-end justify-content-between gap-3">
            <h2 class="visually-hidden" id="week-filter-heading">Seleccionar semana</h2>
            <div class="d-flex flex-wrap gap-2 small" aria-live="polite">
                <span class="badge text-bg-light border"><strong class="text-body">Centro:</strong> <?= htmlspecialchars((string) (($selectedWorkplaceName ?? '') !== '' ? $selectedWorkplaceName : 'No seleccionado'), ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge text-bg-light border"><strong class="text-body">Semana:</strong> <?= htmlspecialchars((string) $weekStart, ENT_QUOTES, 'UTF-8') ?><?= $weekEnd !== '' ? ' — ' . htmlspecialchars($weekEnd, ENT_QUOTES, 'UTF-8') : '' ?></span>
                <span class="badge text-bg-light border"><strong class="text-body">Zona:</strong> <?= htmlspecialchars((string) ($timezone ?? 'No confirmada'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <form method="get" action="/departamento-operativo/recursos-humanos/checador" class="d-flex flex-wrap align-items-end gap-2">
                <input type="hidden" name="schedule_week" value="1">
                <input type="hidden" name="workplace_id" value="<?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '' ?>">
                <label for="checador-week-start" class="form-label mb-0">Semana desde el lunes</label>
                <div class="input-group w-auto">
                    <button class="btn btn-outline-secondary" type="submit" form="checador-week-previous" aria-label="Semana anterior" <?= $previousWeek === '' || $currentWeek === null || $previousWeek < $currentWeek ? 'disabled' : '' ?>><i class="ti ti-chevron-left" aria-hidden="true"></i></button>
                    <input type="date" class="form-control" id="checador-week-start" name="week_start" value="<?= htmlspecialchars((string) $weekStart, ENT_QUOTES, 'UTF-8') ?>" required>
                    <button class="btn btn-outline-secondary" type="submit" form="checador-week-next" aria-label="Semana siguiente" <?= $nextWeek === '' ? 'disabled' : '' ?>><i class="ti ti-chevron-right" aria-hidden="true"></i></button>
                </div>
                <?php if ($currentWeek !== null && $currentWeek !== $weekStart): ?><button class="btn btn-outline-secondary" type="submit" form="checador-week-current">Semana actual</button><?php endif; ?>
                <button class="btn btn-primary" type="submit">Consultar</button>
            </form>
        </div>
    </section>
    <?php if ($previousWeek !== '' && $currentWeek !== null && $previousWeek >= $currentWeek): ?><form id="checador-week-previous" method="get" action="/departamento-operativo/recursos-humanos/checador" class="d-none"><input type="hidden" name="schedule_week" value="1"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '' ?>"><input type="hidden" name="week_start" value="<?= htmlspecialchars($previousWeek, ENT_QUOTES, 'UTF-8') ?>"></form><?php endif; ?>
    <?php if ($nextWeek !== ''): ?><form id="checador-week-next" method="get" action="/departamento-operativo/recursos-humanos/checador" class="d-none"><input type="hidden" name="schedule_week" value="1"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '' ?>"><input type="hidden" name="week_start" value="<?= htmlspecialchars($nextWeek, ENT_QUOTES, 'UTF-8') ?>"></form><?php endif; ?>
    <?php if ($currentWeek !== null): ?><form id="checador-week-current" method="get" action="/departamento-operativo/recursos-humanos/checador" class="d-none"><input type="hidden" name="schedule_week" value="1"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '' ?>"><input type="hidden" name="week_start" value="<?= htmlspecialchars($currentWeek, ENT_QUOTES, 'UTF-8') ?>"></form><?php endif; ?>

    <?php if (($errorMessage ?? '') !== ''): ?><div class="alert alert-warning" role="status"><?= htmlspecialchars((string) $errorMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if (is_array($feedback ?? null)): ?><div class="alert alert-<?= htmlspecialchars(in_array(($feedback['type'] ?? ''), ['success', 'info'], true) ? (string) $feedback['type'] : 'warning', ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars((string) ($feedback['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if (($demoMode ?? false) === true): ?>
        <div class="alert alert-info py-2" role="status">Demostración local; esta información no modifica la programación operativa.</div>
    <?php endif; ?>
    <?php if ($matrixIncomplete): ?><div class="alert alert-warning py-2" role="status">La matriz tiene cobertura parcial: alguna lectura falló, se alcanzó el límite seguro de personal o no se pudo revisar todo el historial.</div><?php endif; ?>

    <section class="card mb-3" aria-labelledby="weekly-matrix-heading">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div><h2 class="h5 mb-1" id="weekly-matrix-heading">Turnos de la semana</h2>
                    <p class="text-muted mb-0">Un espacio sin asignación visible no equivale a descanso.</p></div>
                <span class="badge text-bg-light border"><?= count($scheduleRows ?? []) ?> persona<?= count($scheduleRows ?? []) === 1 ? '' : 's' ?></span>
            </div>
            <?php if (($scheduleRows ?? []) === []): ?>
                <p class="mb-0 text-muted">No hay personas con programación visible para esta semana.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <caption class="visually-hidden">Asignaciones de turno de lunes a domingo para la semana seleccionada</caption>
                        <thead><tr><th scope="col">Trabajador</th><?php foreach ($weekDates as $weekday): ?><th scope="col"><span><?= htmlspecialchars((string) $weekday['label'], ENT_QUOTES, 'UTF-8') ?></span><div class="small text-muted fw-normal"><?= htmlspecialchars((string) $weekday['date'], ENT_QUOTES, 'UTF-8') ?></div></th><?php endforeach; ?><th scope="col">Historial</th></tr></thead>
                        <tbody>
                        <?php foreach ($scheduleRows as $row): ?>
                            <tr>
                                <th scope="row"><?= htmlspecialchars((string) ($row['full_name'] ?? 'Nombre no disponible'), ENT_QUOTES, 'UTF-8') ?><div class="small text-muted">ID <?= (int) ($row['worker_id'] ?? 0) ?><?= !empty($row['employee_number']) ? ' · N.º ' . htmlspecialchars((string) $row['employee_number'], ENT_QUOTES, 'UTF-8') : '' ?></div></th>
                                <?php foreach ($weekDates as $weekday): ?>
                                    <?php $cell = $row['cells'][$weekday['date']] ?? ['status' => 'unassigned', 'label' => 'Sin asignación visible']; $template = is_array($cell['template'] ?? null) ? $cell['template'] : null; ?>
                                    <td class="text-center"<?= $template !== null ? ' title="' . htmlspecialchars(implode(' · ', array_map(static fn(array $s): string => (string) ($s['hora_inicio_local'] ?? '') . '–' . (string) ($s['hora_fin_local'] ?? '') . ((int) ($s['dia_offset_fin'] ?? 0) > 0 ? ' (+día)' : ''), $template['segmentos'] ?? [])), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                        <?php if (($cell['status'] ?? '') === 'rest'): ?><span class="badge text-bg-light border">Descanso</span>
                                        <?php elseif (($cell['status'] ?? '') === 'ambiguous'): ?><span class="badge text-bg-warning">Revisar conflicto</span>
                                        <?php elseif ($template !== null): ?>
                                            <span class="fw-semibold"><?= htmlspecialchars((string) ($template['nombre'] ?? 'Turno'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <div class="small text-muted"><?= htmlspecialchars(implode(' · ', array_map(static fn(array $s): string => (string) ($s['hora_inicio_local'] ?? '') . '–' . (string) ($s['hora_fin_local'] ?? '') . ((int) ($s['dia_offset_fin'] ?? 0) > 0 ? ' (+día)' : ''), $template['segmentos'] ?? [])), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php else: ?><span class="small text-muted"><?= htmlspecialchars((string) ($cell['label'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><a class="btn btn-sm btn-outline-secondary" href="/departamento-operativo/recursos-humanos/checador?<?= htmlspecialchars(http_build_query(['schedule_history' => '1', 'workplace_id' => (int) $selectedWorkplaceId, 'worker_id' => (int) $row['worker_id'], 'date' => (string) $weekStart]), ENT_QUOTES, 'UTF-8') ?>">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (($canAssign ?? false) === true && ($errorMessage ?? '') === '' && ($scheduleRows ?? []) !== []): ?>
        <section class="card mb-3" aria-labelledby="single-assignment-heading">
            <div class="card-body">
                <h2 class="h5" id="single-assignment-heading">Asignar un turno a un día</h2>
                <p class="small text-muted">La API sólo ofrece alta individual, no copia semanal ni asignación masiva. Cada envío crea vigencia para el día seleccionado ([fecha, día siguiente)); no cambia registros previos. Un turno recurrente o una sustitución existente requiere un flujo contractual adicional o la operación de cierre autorizada.</p>
                <form method="post" action="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignar" class="row g-3">
                    <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>">
                    <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) $weekStart, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="col-12 col-md-3"><label class="form-label" for="assignment-worker">Trabajador</label><select class="form-select" id="assignment-worker" name="worker_id" required><option value="">Selecciona</option><?php foreach ($scheduleRows as $row): ?><option value="<?= (int) $row['worker_id'] ?>"><?= htmlspecialchars((string) $row['full_name'], ENT_QUOTES, 'UTF-8') ?> · <?= (int) $row['worker_id'] ?></option><?php endforeach; ?></select></div>
                    <div class="col-12 col-md-3"><label class="form-label" for="assignment-date">Día de la semana</label><select class="form-select" id="assignment-date" name="target_date" required><?php foreach ($weekDates as $weekday): ?><option value="<?= htmlspecialchars((string) $weekday['date'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $weekday['label'] . ' · ' . (string) $weekday['date'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                    <div class="col-12 col-md-4"><label class="form-label" for="assignment-template">Turno o descanso</label><select class="form-select" id="assignment-template" name="template_id" required><option value="">Selecciona</option><?php foreach ($templates as $template): if ((int) ($template['es_activo'] ?? 0) !== 1) continue; ?><option value="<?= (int) $template['id'] ?>"><?= htmlspecialchars((string) $template['nombre'] . ' · v' . (int) $template['version'] . ' · ' . (string) $template['tipo_turno'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?><option value="REST">Descanso explícito</option></select></div>
                    <div class="col-12 col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit">Confirmar día</button></div>
                    <div class="col-12"><label class="form-check-label small"><input class="form-check-input me-1" type="checkbox" name="confirm_action" value="1" required> Confirmo crear esta asignación individual en la API para la fecha indicada.</label></div>
                </form>
            </div>
        </section>
    <?php elseif (($demoMode ?? false) !== true && empty($canAssign)): ?>
        <div class="alert alert-secondary py-2" role="status">La consulta está disponible; la asignación de turnos no está habilitada para este perfil.</div>
    <?php endif; ?>

    <details class="card" aria-labelledby="legal-reference-heading">
        <summary class="card-header h6 mb-0" id="legal-reference-heading">Referencias laborales informativas</summary>
        <div class="card-body">
            <p class="small text-muted">Valores generales por año; no certifican cumplimiento ni calculan horas o nómina.</p>
            <?php if (is_array($legalReference ?? null)): ?>
                <p class="mb-2">Año <?= (int) $legalReference['year'] ?>: jornada ordinaria máxima de referencia <strong><?= (int) $legalReference['ordinary_weekly_max_hours'] ?> h/semana</strong>; referencia de horas extraordinarias semanales <strong><?= (int) $legalReference['extraordinary_weekly_reference_hours'] ?> h</strong>.</p>
                <p class="small mb-2">Referencias generales diarias: diurna <?= htmlspecialchars((string) $dailyLimits['diurna'], ENT_QUOTES, 'UTF-8') ?> h (<?= htmlspecialchars((string) $dailyLimits['diurna_period'], ENT_QUOTES, 'UTF-8') ?>) · nocturna <?= htmlspecialchars((string) $dailyLimits['nocturna'], ENT_QUOTES, 'UTF-8') ?> h (<?= htmlspecialchars((string) $dailyLimits['nocturna_period'], ENT_QUOTES, 'UTF-8') ?>) · mixta <?= htmlspecialchars((string) $dailyLimits['mixta'], ENT_QUOTES, 'UTF-8') ?> h; si el periodo nocturno alcanza <?= (int) $dailyLimits['mixed_nocturnal_max_minutes_exclusive'] ?> min, se considera nocturna. La referencia extraordinaria para <?= (int) $legalReference['year'] ?> contempla hasta <?= (int) $legalReference['extraordinary_daily_max_hours'] ?> h por día, en no más de <?= (int) $legalReference['extraordinary_max_days_per_week'] ?> días por semana; el régimen adicional legal establece hasta <?= (int) $legalReference['additional_extraordinary_weekly_cap_hours'] ?> h semanales y un máximo diario conjunto de <?= (int) $legalReference['ordinary_plus_extraordinary_daily_cap_hours'] ?> h, sujeto a condiciones legales. Estos datos son referencias: no se clasifica la jornada por el nombre del turno ni se calculan horas extraordinarias o cumplimiento.</p>
                <ul class="small mb-0"><li><a href="https://sidof.segob.gob.mx/notas/docFuente/5781417" rel="noopener noreferrer" target="_blank">Reforma constitucional publicada en el DOF</a></li><li><a href="https://dof.gob.mx/nota_detalle_popup.php?codigo=5786537" rel="noopener noreferrer" target="_blank">Reforma a la Ley Federal del Trabajo publicada en el DOF</a></li><li><a href="https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf" rel="noopener noreferrer" target="_blank">Ley Federal del Trabajo vigente (Cámara de Diputados)</a></li></ul>
            <?php else: ?><p class="mb-0">No hay una referencia normativa versionada para este año en la implementación local.</p><?php endif; ?>
            <p class="small text-muted mt-2 mb-0">No se descuentan pausas ni se calculan consecuencias laborales automáticamente.</p>
        </div>
    </details>
</div>

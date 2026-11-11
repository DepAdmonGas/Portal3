<div id="container" class="mt-4 mb-5">
    <?php
    $weekEnd = $weekDates !== [] ? (string) end($weekDates)['date'] : '';
    $dailyHref = '/departamento-operativo/recursos-humanos/checador' . (($selectedWorkplaceId ?? null) !== null ? '?' . http_build_query(['workplace_id' => (int) $selectedWorkplaceId, 'date' => $weekStart]) : '');
    $configurationHref = '/departamento-operativo/recursos-humanos/checador/configuracion'
        . (($selectedWorkplaceId ?? null) !== null ? '?' . http_build_query(['workplace_id' => (int) $selectedWorkplaceId]) : '');
    $templatesHref = '/departamento-operativo/recursos-humanos/checador/horarios-turnos'
        . (($selectedWorkplaceId ?? null) !== null ? '?' . http_build_query(['workplace_id' => (int) $selectedWorkplaceId]) : '');
    $rosterCoverageState = (string) ($rosterCoverage ?? 'unknown');
    $matrixCoverageState = (string) ($matrixCoverage ?? 'unknown');
    $matrixIncomplete = $rosterCoverageState !== 'ready' || $matrixCoverageState !== 'ready';
    $scheduleRows = is_array($scheduleRows ?? null) ? $scheduleRows : [];
    $confirmedScheduleRead = $rosterCoverageState === 'ready' && $matrixCoverageState === 'ready';
    $validWeekDate = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $weekStart, new \DateTimeZone('UTC'));
    $previousWeek = $validWeekDate === false ? '' : $validWeekDate->modify('-7 days')->format('Y-m-d');
    $nextWeek = $validWeekDate === false ? '' : $validWeekDate->modify('+7 days')->format('Y-m-d');
    $workplaceZone = \App\Services\ChecadorOperationalPeriod::isIanaTimezone($timezone ?? null)
        ? new \DateTimeZone((string) $timezone)
        : null;
    $currentWeek = $workplaceZone === null
        ? null
        : \App\Services\ChecadorWeeklyScheduleService::monday((new \DateTimeImmutable('now', $workplaceZone))->format('Y-m-d'));
    $cleanTemplateName = static function (mixed $value): string {
        $rawName = trim(is_string($value) ? $value : '');
        $displayName = preg_replace('/^Legacy\s+[^—–-]+[—–-]\s*/iu', '', $rawName) ?? $rawName;
        $displayName = preg_replace('/(?<![\p{L}\p{N}])legacy(?![\p{L}\p{N}])/iu', '', $displayName) ?? $displayName;
        $displayName = preg_replace('/(?<![\p{L}\p{N}])v\d+(?![\p{L}\p{N}])/iu', '', $displayName) ?? $displayName;
        $displayName = preg_replace('/\s*(?:[—–-]\s*)?\b\d{1,2}:\d{2}(?::\d{2})?\s*[—–-]\s*\d{1,2}:\d{2}(?::\d{2})?(?:\s*\(\+?\d+\s*d[ií]a\))?\s*$/u', '', $displayName) ?? $displayName;
        $displayName = preg_replace('/\s*([·|])\s*(?:[·|]\s*)+/u', ' · ', $displayName) ?? $displayName;
        $displayName = preg_replace('/\s{2,}/u', ' ', $displayName) ?? $displayName;
        $displayName = trim(preg_replace('/[—–-]\s*$/u', '', $displayName) ?? $displayName);
        $displayName = trim($displayName, " \t\n\r\0\x0B·|—–-_");
        return $displayName !== '' ? $displayName : 'Turno sin nombre';
    };
    $templateOptionLabel = static function (array $template) use ($cleanTemplateName): string {
        $segments = is_array($template['segmentos'] ?? null) ? $template['segmentos'] : [];
        usort($segments, static fn(array $left, array $right): int => (int) ($left['ordinal'] ?? 0) <=> (int) ($right['ordinal'] ?? 0));
        $work = array_values(array_filter($segments, static fn(array $segment): bool => strtoupper((string) ($segment['tipo_segmento'] ?? '')) === 'WORK'));
        $breaks = array_values(array_filter($segments, static fn(array $segment): bool => strtoupper((string) ($segment['tipo_segmento'] ?? '')) === 'BREAK'));
        $formatEndpoint = static function (array $segment, string $timeKey, string $offsetKey): string {
            $time = substr((string) ($segment[$timeKey] ?? ''), 0, 5);
            $offset = (int) ($segment[$offsetKey] ?? 0);
            return $time . ($offset > 0 ? ' (+' . $offset . ' día)' : '');
        };
        $details = '';
        if ($work !== []) {
            $details = $formatEndpoint($work[0], 'hora_inicio_local', 'dia_offset_inicio')
                . '–' . $formatEndpoint($work[count($work) - 1], 'hora_fin_local', 'dia_offset_fin');
            if ($breaks !== []) {
                $details .= ' · descanso ' . $formatEndpoint($breaks[0], 'hora_inicio_local', 'dia_offset_inicio')
                    . '–' . $formatEndpoint($breaks[count($breaks) - 1], 'hora_fin_local', 'dia_offset_fin');
            }
        }
        $label = $cleanTemplateName($template['nombre'] ?? null);
        if ($details !== '') $label .= ' · ' . $details;
        return $label;
    };
    ?>
    <header class="d-flex justify-content-end align-items-center gap-2 mb-3">
        <?php if (($demoMode ?? false) === true): ?><span class="badge rounded-pill text-bg-secondary" title="Datos sintéticos aislados">Demo</span><?php endif; ?>
        <div class="dropdown">
            <button type="button" class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Opciones del módulo">
                <i class="ti ti-dots-vertical fs-4" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><a class="dropdown-item" href="<?= htmlspecialchars($configurationHref, ENT_QUOTES, 'UTF-8') ?>"><i class="ti ti-settings me-1" aria-hidden="true"></i>Configuración</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars($dailyHref, ENT_QUOTES, 'UTF-8') ?>"><i class="ti ti-calendar-check me-1" aria-hidden="true"></i>Asistencia del día</a></li>
                <li><a class="dropdown-item" href="<?= htmlspecialchars($templatesHref, ENT_QUOTES, 'UTF-8') ?>"><i class="ti ti-clock me-1" aria-hidden="true"></i><?= ($canManageTemplates ?? false) === true ? 'Administrar turnos' : 'Consultar Horarios y turnos' ?></a></li>
            </ul>
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
            <form method="get" action="/departamento-operativo/recursos-humanos/checador/programacion-semanal" class="d-flex flex-wrap align-items-end gap-2">
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
    <?php if ($previousWeek !== '' && $currentWeek !== null && $previousWeek >= $currentWeek): ?><form id="checador-week-previous" method="get" action="/departamento-operativo/recursos-humanos/checador/programacion-semanal" class="d-none"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '' ?>"><input type="hidden" name="week_start" value="<?= htmlspecialchars($previousWeek, ENT_QUOTES, 'UTF-8') ?>"></form><?php endif; ?>
    <?php if ($nextWeek !== ''): ?><form id="checador-week-next" method="get" action="/departamento-operativo/recursos-humanos/checador/programacion-semanal" class="d-none"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '' ?>"><input type="hidden" name="week_start" value="<?= htmlspecialchars($nextWeek, ENT_QUOTES, 'UTF-8') ?>"></form><?php endif; ?>
    <?php if ($currentWeek !== null): ?><form id="checador-week-current" method="get" action="/departamento-operativo/recursos-humanos/checador/programacion-semanal" class="d-none"><input type="hidden" name="workplace_id" value="<?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '' ?>"><input type="hidden" name="week_start" value="<?= htmlspecialchars($currentWeek, ENT_QUOTES, 'UTF-8') ?>"></form><?php endif; ?>

    <?php if (!empty($isDepartmentLocality)): ?>
        <div class="alert alert-warning py-3" role="status">
            <div class="d-flex align-items-center">
                <i class="ti ti-alert-triangle fs-4 me-2" aria-hidden="true"></i>
                <div>
                    <strong>Módulo no disponible para esta localidad.</strong>
                    <div class="small mt-1">La programación semanal de turnos está habilitada exclusivamente para estaciones de servicio autorizadas. Las operaciones de turnos para el departamento <strong><?= htmlspecialchars((string) ($selectedWorkplaceName ?? 'Autolavado'), ENT_QUOTES, 'UTF-8') ?></strong> no están disponibles en este módulo.</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (($errorMessage ?? '') !== ''): ?><div class="alert alert-warning" role="status"><?= htmlspecialchars((string) $errorMessage, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if (is_array($feedback ?? null)): ?><div class="alert alert-<?= htmlspecialchars(in_array(($feedback['type'] ?? ''), ['success', 'info'], true) ? (string) $feedback['type'] : 'warning', ENT_QUOTES, 'UTF-8') ?>" role="status"><?= htmlspecialchars((string) ($feedback['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if (($demoMode ?? false) === true): ?>
        <div class="alert alert-info py-2" role="status">Demostración local; esta información no modifica la programación operativa.</div>
    <?php endif; ?>
    <?php if ($matrixIncomplete && trim((string) ($errorMessage ?? '')) === ''): ?><div class="alert alert-warning py-2" role="status">Algunos horarios de esta semana no pudieron confirmarse. Sólo se habilitan cambios para fechas y trabajadores con información verificada.</div><?php endif; ?>

    <div data-schedule-week-ui data-workplace-id="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
        <ul class="nav nav-tabs" role="tablist" data-schedule-tabs>
            <li class="nav-item" role="presentation"><button class="nav-link active" id="habitual-schedule-tab" data-bs-toggle="tab" data-bs-target="#habitual-schedule-panel" type="button" role="tab" aria-controls="habitual-schedule-panel" aria-selected="true">Horario habitual del personal</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="temporary-schedule-tab" data-bs-toggle="tab" data-bs-target="#temporary-schedule-panel" type="button" role="tab" aria-controls="temporary-schedule-panel" aria-selected="false">Cambios temporales</button></li>
        </ul>
        <div class="row g-2 align-items-center my-3">
            <label class="col-auto form-label mb-0" for="habitual-worker-search">Buscar trabajador</label>
            <div class="col-12 col-md-4"><input class="form-control" type="search" id="habitual-worker-search" data-schedule-worker-search placeholder="Nombre o trabajador" aria-label="Buscar trabajador en la programación"></div>
        </div>
        <div class="tab-content overflow-x-hidden">
        <div class="tab-pane fade show active" id="habitual-schedule-panel" role="tabpanel" aria-labelledby="habitual-schedule-tab" tabindex="0">
        <section class="card mb-3" aria-labelledby="habitual-schedule-heading">
            <div class="card-header bg-primary text-white">
                <h2 class="h5 mb-0 text-white" id="habitual-schedule-heading"><i class="ti ti-calendar-repeat me-1" aria-hidden="true"></i>Horario habitual del personal</h2>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">El horario habitual se mantiene en las semanas siguientes. Registra sólo los días acreditados; los descansos deben seleccionarse explícitamente.</p>
                <?php if (($habitualScheduleRows ?? []) === []): ?>
                    <p class="mb-0 text-muted">No hay trabajadores disponibles para consultar en esta estación.</p>
                <?php else: ?>
                    <div class="table-responsive" data-schedule-matrix-panel>
                        <table class="table table-striped align-middle mb-0" aria-describedby="habitual-schedule-heading">
                            <caption class="visually-hidden">Horario habitual de lunes a domingo para el personal de la estación seleccionada</caption>
                            <thead><tr><th scope="col">Trabajador</th><?php foreach ($weekDates as $weekday): ?><th scope="col"><span><?= htmlspecialchars((string) $weekday['label'], ENT_QUOTES, 'UTF-8') ?></span><div class="small text-muted fw-normal"><?= htmlspecialchars((string) $weekday['date'], ENT_QUOTES, 'UTF-8') ?></div></th><?php endforeach; ?><th scope="col">Cobertura habitual</th><th scope="col" class="text-center text-nowrap"><span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i></th></tr></thead>
                            <tbody>
                            <?php foreach ($habitualScheduleRows as $row): ?>
                                <?php
                                $workerId = (int) ($row['worker_id'] ?? 0);
                                $workerName = (string) ($row['full_name'] ?? 'Nombre no disponible');
                                $habitualCoverage = is_array($row['habitual_coverage'] ?? null) ? $row['habitual_coverage'] : [];
                                $coverageStatus = (string) ($habitualCoverage['status'] ?? 'unknown');
                                $verifiedDates = is_array($row['verified_dates'] ?? null) ? array_values(array_filter($row['verified_dates'], 'is_string')) : [];
                                $hasVerifiedMembership = ($row['has_versioned_membership'] ?? false) === true;
                                $canManageWorker = ($canEditSchedules ?? false) === true && $workerId > 0 && $hasVerifiedMembership && $verifiedDates !== [];
                                $existingDays = [];
                                $replacementSources = [];
                                $verifiedDateSet = array_fill_keys($verifiedDates, true);
                                $verifiedDateCount = 0;
                                $partialMembershipOnly = true;
                                $hasHabitualAssignmentOnVerifiedDate = false;
                                foreach ($weekDates as $weekday) {
                                    $cell = $row['cells'][$weekday['date']] ?? [];
                                    $assignment = is_array($cell['assignment'] ?? null) ? $cell['assignment'] : null;
                                    $weekdayCode = (string) $weekday['weekday'];
                                    $cellStatus = (string) ($cell['status'] ?? 'unknown');
                                    $dateVerified = isset($verifiedDateSet[(string) $weekday['date']]);
                                    if ($dateVerified) {
                                        $verifiedDateCount++;
                                        if ($cellStatus === 'assigned' || $cellStatus === 'rest') {
                                            if ($assignment !== null && strtoupper((string) ($assignment['tipo_asignacion'] ?? 'HABITUAL')) !== 'TEMPORAL') {
                                                $hasHabitualAssignmentOnVerifiedDate = true;
                                            }
                                            $partialMembershipOnly = false;
                                        } elseif ($cellStatus !== 'unassigned') {
                                            $partialMembershipOnly = false;
                                        }
                                    } elseif ($cellStatus !== 'membership_unverified') {
                                        $partialMembershipOnly = false;
                                    }
                                    if ($assignment !== null && strtoupper((string) ($assignment['tipo_asignacion'] ?? 'HABITUAL')) !== 'TEMPORAL') {
                                        $existingDays[$weekdayCode] = ($cell['status'] ?? '') === 'rest' ? 'REST' : (string) ($assignment['id_plantilla'] ?? '');
                                        if ((int) ($assignment['es_activo'] ?? 0) === 1 && in_array(($cell['status'] ?? ''), ['assigned', 'rest'], true)) {
                                            $replacementSources[] = [
                                                'id' => (int) ($assignment['id'] ?? 0),
                                                'weekday' => $weekdayCode,
                                                'weekday_label' => (string) $weekday['label'],
                                                'label' => (string) ($cell['label'] ?? 'Descanso'),
                                                'valid_from' => (string) ($assignment['valido_desde'] ?? ''),
                                                'valid_to' => $assignment['valido_hasta'] ?? null,
                                            ];
                                        }
                                    }
                                }
                                $canReplace = $canManageWorker && $coverageStatus === 'complete' && $replacementSources !== [];
                                $canInitialOnPartialMembership = $canManageWorker
                                    && $coverageStatus === 'unknown'
                                    && ($row['schedule_read_status'] ?? '') === 'available'
                                    && $verifiedDateCount > 0
                                    && $partialMembershipOnly
                                    && !$hasHabitualAssignmentOnVerifiedDate;
                                $coverageLabel = (string) ($habitualCoverage['label'] ?? 'No confirmada');
                                $coverageClass = match ($coverageStatus) {
                                    'complete' => 'text-bg-success', 'partial' => 'text-bg-warning', 'unassigned' => 'text-bg-secondary', default => 'text-bg-light border text-body',
                                };
                                $habitualButtonData = htmlspecialchars(json_encode($existingDays, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');
                                $replacementSourcesData = htmlspecialchars(json_encode($replacementSources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr data-worker-name="<?= htmlspecialchars($workerName, ENT_QUOTES, 'UTF-8') ?>">
                                    <th scope="row"><?= htmlspecialchars($workerName, ENT_QUOTES, 'UTF-8') ?><div class="small text-muted">ID <?= $workerId ?><?= !empty($row['employee_number']) ? ' · N.º ' . htmlspecialchars((string) $row['employee_number'], ENT_QUOTES, 'UTF-8') : '' ?></div></th>
                                    <?php foreach ($weekDates as $weekday): ?>
                                        <?php $cell = $row['cells'][$weekday['date']] ?? ['status' => 'unassigned', 'label' => 'Sin asignación confirmada']; ?>
                                        <td class="text-center">
                                            <?php if (($cell['status'] ?? '') === 'rest'): ?><span class="badge text-bg-light border">Descanso</span>
                                            <?php elseif (($cell['status'] ?? '') === 'assigned' && is_array($cell['template'] ?? null)): ?><span class="fw-semibold"><?= htmlspecialchars($cleanTemplateName($cell['template']['nombre'] ?? null), ENT_QUOTES, 'UTF-8') ?></span><div class="small text-muted"><?= htmlspecialchars(implode(' · ', array_map(static fn(array $segment): string => (string) ($segment['hora_inicio_local'] ?? '') . '–' . (string) ($segment['hora_fin_local'] ?? '') . ((int) ($segment['dia_offset_fin'] ?? 0) > 0 ? ' (+día)' : ''), $cell['template']['segmentos'] ?? [])), ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php elseif (($cell['status'] ?? '') === 'unassigned'): ?><span class="small text-muted">Sin asignación</span>
                                            <?php elseif (($cell['status'] ?? '') === 'schedule_not_requested'): ?><span class="small text-muted">Selecciona para consultar</span>
                                            <?php elseif (($cell['status'] ?? '') === 'membership_unverified'): ?><span class="small text-muted">Pertenencia no confirmada</span>
                                            <?php elseif (($cell['status'] ?? '') === 'schedule_unavailable'): ?><span class="small text-muted">Horario no disponible</span>
                                            <?php else: ?><span class="small text-muted"><?= htmlspecialchars((string) ($cell['label'] ?? 'Sin asignación confirmada'), ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td><span class="badge <?= $coverageClass ?>"><?= htmlspecialchars($coverageLabel, ENT_QUOTES, 'UTF-8') ?></span><div class="small text-muted mt-1"><?= (int) ($habitualCoverage['covered_days'] ?? 0) ?>/<?= (int) ($habitualCoverage['total_days'] ?? 7) ?> días</div></td>
                                    <td class="text-center">
                                        <div class="dropdown dropstart">
                                            <button class="btn p-0 border-0 bg-transparent text-body" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de <?= htmlspecialchars($workerName, ENT_QUOTES, 'UTF-8') ?>"><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i></button>
                                            <ul class="dropdown-menu">
                                                <?php if ($canManageWorker && ($coverageStatus === 'unassigned' || $canInitialOnPartialMembership)): ?>
                                                    <li><button class="dropdown-item pointer" type="button" data-habitual-assignment-read-url="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignaciones/readback" data-habitual-worker-id="<?= $workerId ?>" data-habitual-worker-name="<?= htmlspecialchars($workerName, ENT_QUOTES, 'UTF-8') ?>" data-habitual-week-start="<?= htmlspecialchars((string) $weekStart, ENT_QUOTES, 'UTF-8') ?>" data-habitual-workplace-id="<?= (int) $selectedWorkplaceId ?>"><i class="ti ti-calendar-plus me-1" aria-hidden="true"></i>Asignar horario habitual</button></li>
                                                <?php elseif ($canManageWorker && $coverageStatus === 'partial'): ?>
                                                    <li><button class="dropdown-item pointer" type="button" data-habitual-assignment-read-url="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignaciones/readback" data-habitual-worker-id="<?= $workerId ?>" data-habitual-worker-name="<?= htmlspecialchars($workerName, ENT_QUOTES, 'UTF-8') ?>" data-habitual-week-start="<?= htmlspecialchars((string) $weekStart, ENT_QUOTES, 'UTF-8') ?>" data-habitual-workplace-id="<?= (int) $selectedWorkplaceId ?>"><i class="ti ti-calendar-plus me-1" aria-hidden="true"></i>Completar horario habitual</button></li>
                                                <?php elseif ($canReplace): ?>
                                                    <li><button class="dropdown-item pointer" type="button" data-bs-toggle="modal" data-bs-target="#habitual-schedule-replacement-modal" data-habitual-replacement-worker-id="<?= $workerId ?>" data-habitual-replacement-worker-name="<?= htmlspecialchars($workerName, ENT_QUOTES, 'UTF-8') ?>" data-habitual-replacement-sources="<?= $replacementSourcesData ?>"><i class="ti ti-calendar-repeat me-1" aria-hidden="true"></i>Cambiar horario habitual</button></li>
                                                <?php endif; ?>
                                                <li><a class="dropdown-item pointer" href="/departamento-operativo/recursos-humanos/checador/horarios-turnos?<?= htmlspecialchars(http_build_query(['worker_id' => $workerId, 'date' => (string) $weekStart]), ENT_QUOTES, 'UTF-8') ?>"><i class="ti ti-history me-1" aria-hidden="true"></i>Ver historial</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="small text-muted mt-2 mb-0">La ausencia de una asignación no significa descanso. Sólo se habilitan cambios para fechas con adscripción verificada y lectura completa.</p>
                    </div>
                    <p class="small text-muted mt-2 mb-0 d-none" data-schedule-no-results>No hay trabajadores que coincidan con la búsqueda.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php if (($canEditSchedules ?? false) === true && ($assignableWorkers ?? []) !== []): ?>
        <script type="application/json" id="habitual-week-dates"><?php
            $habitualWeekDates = [];
            foreach ($weekDates as $weekday) $habitualWeekDates[(string) $weekday['weekday']] = (string) $weekday['date'];
            echo json_encode($habitualWeekDates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?></script>
        <div class="modal fade" id="habitual-schedule-assignment-modal" data-assignment-read-url="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignaciones/readback" tabindex="-1" aria-labelledby="habitual-schedule-assignment-title" aria-hidden="true" x-data="habitualScheduleAssignment" x-init="initialize($el)">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white"><h2 class="modal-title text-white h5" id="habitual-schedule-assignment-title">Asignar horario habitual</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <form method="post" action="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignar" @submit.prevent="submit($event)">
                    <div class="modal-body">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>">
                        <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) $weekStart, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="tipo_asignacion" value="HABITUAL">
                        <input type="hidden" name="worker_id" x-model="workerId">
                        <p class="mb-3">Trabajador: <strong x-text="workerName"></strong></p>
                        <div class="mb-3">
                            <label class="form-label" for="habitual-effective-start-date">Fecha de inicio de vigencia</label>
                            <input class="form-control" type="date" id="habitual-effective-start-date" name="effective_start_date" min="<?= htmlspecialchars((string) $effectiveDateMin, ENT_QUOTES, 'UTF-8') ?>" max="<?= htmlspecialchars((string) $effectiveDateMax, ENT_QUOTES, 'UTF-8') ?>" @change="updateEffectiveStart()" required>
                            <div class="form-text" x-text="effectiveStartMessage">El patrón semanal se aplicará desde la fecha elegida; los días anteriores no generan asignaciones.</div>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($weekDates as $weekday): ?>
                                <?php $weekdayCode = (string) $weekday['weekday']; ?>
                                <div class="col-12 col-md-6"><label class="form-label" for="habitual-day-<?= htmlspecialchars($weekdayCode, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $weekday['label'], ENT_QUOTES, 'UTF-8') ?></label>
                                    <select class="form-select" id="habitual-day-<?= htmlspecialchars($weekdayCode, ENT_QUOTES, 'UTF-8') ?>" name="schedule[<?= htmlspecialchars($weekdayCode, ENT_QUOTES, 'UTF-8') ?>]" disabled>
                                        <option value="">Selecciona turno o descanso explícito</option>
                                        <?php foreach ($templates ?? [] as $template): if ((int) ($template['es_activo'] ?? 0) !== 1) continue; ?>
                        <option value="<?= (int) $template['id'] ?>"><?= htmlspecialchars($templateOptionLabel($template), ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                        <option value="REST">Descanso explícito</option>
                                    </select>
                                    <div class="form-text" data-habitual-day-effective>Selecciona una fecha de inicio.</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="habitual-assignment-confirm" name="confirm_action" value="1" required><label class="form-check-label" for="habitual-assignment-confirm">Confirmo los turnos o descansos seleccionados; cada día comienza en su primera fecha igual o posterior al inicio y se repite semanalmente.</label></div>
                        <div class="alert alert-danger mt-3 mb-0" role="alert" x-show="error" x-text="error"></div>
                        <div class="alert alert-success mt-3 mb-0" role="status" x-show="status" x-text="status"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" :disabled="loading"><span x-show="loading" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardar días seleccionados</button></div>
                </form>
            </div></div>
        </div>
    <?php endif; ?>

    <?php if (($canEditSchedules ?? false) === true): ?>
        <div class="modal fade" id="habitual-schedule-replacement-modal" tabindex="-1" aria-labelledby="habitual-schedule-replacement-title" aria-hidden="true" x-data="habitualScheduleReplacement" x-init="initialize($el)">
            <div class="modal-dialog modal-lg"><div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white"><h2 class="modal-title text-white h5" id="habitual-schedule-replacement-title">Cambiar horario habitual</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <form method="post" action="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignaciones/reemplazar">
                    <div class="modal-body">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>">
                        <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) $weekStart, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="worker_id" x-ref="workerId">
                        <input type="hidden" name="assignment_id" x-ref="assignmentId">
                        <input type="hidden" name="expected_valido_desde" x-ref="expectedFrom">
                        <input type="hidden" name="expected_valido_hasta" x-ref="expectedTo">
                        <input type="hidden" name="selected_date" x-ref="selectedDate">
                        <p>Trabajador: <strong x-text="workerName"></strong></p>
                        <div class="mb-3"><label class="form-label" for="habitual-replacement-source">Día y asignación vigente</label><select class="form-select" id="habitual-replacement-source" x-ref="source" @change="selectSource()" required><option value="">Selecciona</option></select></div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6"><label class="form-label" for="habitual-replacement-date">Fecha efectiva</label><input class="form-control" type="date" id="habitual-replacement-date" name="effective_close_date" x-ref="effectiveDate" @change="validateEffectiveDate()" required><div class="form-text" x-text="dateHint"></div></div>
                            <div class="col-12 col-md-6"><label class="form-label" for="habitual-replacement-template">Nuevo turno o descanso</label><select class="form-select" id="habitual-replacement-template" name="replacement_template_id" required><option value="">Selecciona</option><?php foreach ($templates ?? [] as $template): if ((int) ($template['es_activo'] ?? 0) !== 1) continue; ?><option value="<?= (int) $template['id'] ?>"><?= htmlspecialchars($templateOptionLabel($template), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?><option value="REST">Descanso explícito</option></select></div>
                            <div class="col-12"><label class="form-label" for="habitual-replacement-valid-to">Fin de vigencia (opcional)</label><input class="form-control" type="date" id="habitual-replacement-valid-to" name="replacement_valido_hasta"><div class="form-text">Déjalo vacío para que el nuevo horario habitual continúe hasta otro cambio autorizado.</div></div>
                        </div>
                        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="habitual-replacement-confirm" name="confirm_action" value="1" required><label class="form-check-label" for="habitual-replacement-confirm">Confirmo el reemplazo prospectivo; la versión anterior y su historial se conservarán.</label></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" :disabled="dateInvalid || submitting">Guardar cambio</button></div>
                </form>
            </div></div>
        </div>
    <?php endif; ?>

        <div class="tab-pane fade" id="temporary-schedule-panel" role="tabpanel" aria-labelledby="temporary-schedule-tab" tabindex="0">
    <section class="card mb-3" aria-labelledby="weekly-matrix-heading">
        <div class="card-header bg-primary text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div><h2 class="h5 mb-1 text-white" id="weekly-matrix-heading">Programación semanal / Cambios temporales</h2>
                    <p class="text-white-50 mb-0">Un cambio temporal sólo aplica en las fechas autorizadas.</p></div>
            </div>
            <?php if (($canEditSchedules ?? false) === true && ($assignableWorkers ?? []) !== []): ?>
                <button class="btn bg-white text-primary" type="button" data-bs-toggle="modal" data-bs-target="#temporary-schedule-assignment-modal">
                    <i class="ti ti-plus me-1" aria-hidden="true"></i>Nuevo cambio temporal
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($confirmedScheduleRead): ?>
                <div class="d-flex justify-content-end mb-2"><span class="badge text-bg-light border"><?= count($scheduleRows) ?> persona<?= count($scheduleRows) === 1 ? '' : 's' ?></span></div>
            <?php else: ?>
                <?php
                $rosterCoverageLabel = match ($rosterCoverageState) {
                    'ready' => 'confirmado',
                    'partial' => 'cobertura parcial',
                    'unavailable' => 'no disponible',
                    default => 'no confirmado',
                };
                $matrixCoverageLabel = match ($matrixCoverageState) {
                    'ready' => 'completa',
                    'partial' => 'cobertura parcial',
                    'not_evaluated' => 'no evaluada',
                    'unavailable' => 'no disponible',
                    default => 'no confirmada',
                };
                ?>
                <div class="d-flex flex-wrap gap-2 mb-2" role="status">
                    <span class="badge text-bg-light border">Padrón: <?= htmlspecialchars($rosterCoverageLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="badge text-bg-light border">Matriz semanal: <?= htmlspecialchars($matrixCoverageLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>
            <?php if ($scheduleRows === []): ?>
                <?php if (!$confirmedScheduleRead && $rosterCoverageState === 'partial'): ?>
                    <p class="mb-0 text-muted">No se puede confirmar el conjunto de personas para esta semana.</p>
                <?php elseif (!$confirmedScheduleRead && $matrixCoverageState === 'not_evaluated'): ?>
                    <p class="mb-0 text-muted">La matriz semanal no se evaluó; no se puede confirmar si existen asignaciones.</p>
                <?php elseif (!$confirmedScheduleRead && $matrixCoverageState === 'unavailable'): ?>
                    <p class="mb-0 text-muted">La matriz semanal no está disponible; no se puede confirmar si existen asignaciones.</p>
                <?php elseif ($confirmedScheduleRead): ?>
                    <p class="mb-0 text-muted">No hay asignaciones para el padrón con membresía canónica vigente. Esto no equivale a descanso ni ausencia.</p>
                <?php else: ?>
                    <p class="mb-0 text-muted">No se puede confirmar si existen asignaciones para esta semana.</p>
                <?php endif; ?>
            <?php else: ?>
                    <div class="table-responsive" data-schedule-matrix-panel>
                    <table class="table table-striped align-middle mb-0">
                        <caption class="visually-hidden">Asignaciones de turno de lunes a domingo para la semana seleccionada</caption>
                        <thead><tr><th scope="col">Trabajador</th><?php foreach ($weekDates as $weekday): ?><th scope="col"><span><?= htmlspecialchars((string) $weekday['label'], ENT_QUOTES, 'UTF-8') ?></span><div class="small text-muted fw-normal"><?= htmlspecialchars((string) $weekday['date'], ENT_QUOTES, 'UTF-8') ?></div></th><?php endforeach; ?><th scope="col">Historial</th></tr></thead>
                        <tbody>
                        <?php foreach ($scheduleRows as $row): ?>
                            <tr data-worker-name="<?= htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <th scope="row"><?= htmlspecialchars((string) ($row['full_name'] ?? 'Nombre no disponible'), ENT_QUOTES, 'UTF-8') ?><div class="small text-muted">ID <?= (int) ($row['worker_id'] ?? 0) ?><?= !empty($row['employee_number']) ? ' · N.º ' . htmlspecialchars((string) $row['employee_number'], ENT_QUOTES, 'UTF-8') : '' ?></div></th>
                                <?php foreach ($weekDates as $weekday): ?>
                                    <?php $cell = $row['cells'][$weekday['date']] ?? ['status' => 'unassigned', 'label' => 'Sin asignación visible']; $template = is_array($cell['template'] ?? null) ? $cell['template'] : null; ?>
                                    <td class="text-center"<?= $template !== null ? ' title="' . htmlspecialchars(implode(' · ', array_map(static fn(array $s): string => (string) ($s['hora_inicio_local'] ?? '') . '–' . (string) ($s['hora_fin_local'] ?? '') . ((int) ($s['dia_offset_fin'] ?? 0) > 0 ? ' (+día)' : ''), $template['segmentos'] ?? [])), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                        <?php if (($cell['status'] ?? '') === 'rest'): ?><span class="badge text-bg-light border">Descanso</span>
                                        <?php elseif (($cell['status'] ?? '') === 'ambiguous'): ?><span class="badge text-bg-warning">Revisar conflicto</span>
                                        <?php elseif ($template !== null): ?>
                                            <span class="fw-semibold"><?= htmlspecialchars($cleanTemplateName($template['nombre'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
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
                <p class="small text-muted mt-2 mb-0 d-none" data-schedule-no-results>No hay trabajadores que coincidan con la búsqueda.</p>
            <?php endif; ?>
        </div>
    </section>
        <?php if (($demoMode ?? false) !== true && empty($canAssign) && $confirmedScheduleRead): ?><div class="alert alert-secondary py-2" role="status">La consulta está disponible; este perfil no puede guardar cambios temporales.</div><?php endif; ?>
        </div>
        </div>

    <?php if (($canEditSchedules ?? false) === true && ($assignableWorkers ?? []) !== []): ?>
        <?php
        $habitualByWorker = [];
        foreach ($habitualScheduleRows ?? [] as $habitualRow) {
            $habitualByWorker[(string) ($habitualRow['worker_id'] ?? '')] = $habitualRow;
        }
        $temporaryHabitualReference = [];
        foreach ($assignableWorkers as $assignableWorker) {
            $referenceWorkerId = (string) ($assignableWorker['worker_id'] ?? '');
            $habitualRow = $habitualByWorker[$referenceWorkerId] ?? [];
            foreach ($weekDates as $referenceDate) {
                $civilDate = (string) ($referenceDate['date'] ?? '');
                $habitualCell = $habitualRow['cells'][$civilDate] ?? null;
                if (!is_array($habitualCell)) continue;
                $temporaryHabitualReference[$referenceWorkerId][$civilDate] = (string) ($habitualCell['label'] ?? 'Sin asignación habitual confirmada');
            }
        }
        ?>
        <script type="application/json" id="temporary-schedule-habitual-reference-data"><?= json_encode($temporaryHabitualReference, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        <div class="modal fade" id="temporary-schedule-assignment-modal" tabindex="-1" aria-labelledby="temporary-schedule-assignment-title" aria-hidden="true" x-data="temporaryScheduleAssignment" x-init="initialize($el)">
            <div class="modal-dialog"><div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white"><h2 class="modal-title text-white h5" id="temporary-schedule-assignment-title">Nuevo cambio temporal</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <form method="post" action="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignar" @submit.prevent="submit($event)">
                    <div class="modal-body">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(\App\Core\CsrfToken::token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>">
                        <input type="hidden" name="week_start" value="<?= htmlspecialchars((string) $weekStart, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="tipo_asignacion" value="TEMPORAL">
                        <div class="row g-3">
                            <div class="col-12"><label class="form-label" for="temporary-assignment-date">Fecha del cambio</label><select class="form-select" id="temporary-assignment-date" name="target_date" data-schedule-target-date required><option value="">Selecciona una fecha</option><?php foreach ($weekDates as $weekday): ?><option value="<?= htmlspecialchars((string) $weekday['date'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $weekday['label'] . ' · ' . (string) $weekday['date'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
                            <div class="col-12"><label class="form-label" for="temporary-assignment-worker">Trabajador</label><select class="form-select" id="temporary-assignment-worker" name="worker_id" data-schedule-worker-select required><option value="">Selecciona trabajador</option><?php foreach ($assignableWorkers as $assignableWorker): $verifiedDatesForWorker = is_array($assignableWorker['versioned_verified_dates'] ?? null) ? array_values(array_filter($assignableWorker['versioned_verified_dates'], 'is_string')) : []; $workerOptionLabel = trim((string) ($assignableWorker['full_name'] ?? 'Trabajador')); ?><option value="<?= (int) ($assignableWorker['worker_id'] ?? 0) ?>" data-worker-label="<?= htmlspecialchars($workerOptionLabel, ENT_QUOTES, 'UTF-8') ?>" data-verified-dates="<?= htmlspecialchars(implode(',', $verifiedDatesForWorker), ENT_QUOTES, 'UTF-8') ?>" disabled><?= htmlspecialchars($workerOptionLabel, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><div class="form-text" data-schedule-worker-status>Selecciona una fecha para consultar el personal con adscripción verificada.</div></div>
                            <div class="col-12"><label class="form-label" for="temporary-assignment-template">Turno o descanso explícito</label><select class="form-select" id="temporary-assignment-template" name="template_id" required><option value="">Selecciona</option><?php foreach ($templates ?? [] as $template): if ((int) ($template['es_activo'] ?? 0) !== 1) continue; ?><option value="<?= (int) $template['id'] ?>"><?= htmlspecialchars($templateOptionLabel($template), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?><option value="REST">Descanso explícito</option></select></div>
                        </div>
                        <div class="alert alert-light border mt-3 mb-0" role="status"><strong>Horario habitual vigente de referencia</strong><div class="small mt-1" x-text="habitualReference">Selecciona un trabajador y una fecha para consultar el horario habitual.</div></div>
                        <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="temporary-assignment-confirm" name="confirm_action" value="1" required><label class="form-check-label" for="temporary-assignment-confirm">Confirmo este cambio únicamente para la fecha seleccionada; después continúa el horario habitual vigente.</label></div>
                        <div class="alert alert-danger mt-3 mb-0" role="alert" x-show="error" x-text="error"></div>
                        <div class="alert alert-success mt-3 mb-0" role="status" x-show="status" x-text="status"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" :disabled="loading || completed"><span x-show="loading" class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Guardar cambio</button></div>
                </form>
            </div></div>
        </div>
    <?php endif; ?>

    </div>

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

<?php
declare(strict_types=1);

$config = is_array($configuration ?? null) ? $configuration : [];
$permissions = is_array($config['permissions'] ?? null) ? $config['permissions'] : [];
$apiTemplates = is_array($config['api_templates'] ?? null) ? $config['api_templates'] : [];
$apiHistory = is_array($config['api_history'] ?? null) ? $config['api_history'] : [];
$policy = is_array($config['policy'] ?? null) ? $config['policy'] : [];
$readerProfiles = is_array($config['reader_profiles'] ?? null) ? $config['reader_profiles'] : [];
$positions = is_array($config['positions'] ?? null) ? $config['positions'] : [];
$deviceAuth = is_array($config['device_auth'] ?? null) ? $config['device_auth'] : [];
$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$labelForStatus = static fn(string $status): string => match ($status) {
    'ready', 'current_catalog' => 'Disponible',
    'demo_fixture' => 'Demostración local',
    'demo_fixture_partial' => 'Demostración local · cobertura parcial',
    'partial' => 'Cobertura parcial',
    'possibly_partial' => 'Puede estar incompleto',
    'permission_denied' => 'Sin permiso de lectura',
    'station_scoped' => 'Perfil de estación autorizado',
    'not_applicable', 'department_has_no_legacy_station_mapping' => 'No aplica a departamentos',
    'not_read_in_demo' => 'No consultado en modo demo',
    'not_integrated' => 'No integrado',
    'mapping_or_module_scope_unavailable', 'mapping_unavailable', 'mapping_not_unique_or_inactive' => 'Mapeo no confirmado',
    'reader_profile_contract_unavailable' => 'Operación pendiente de integración',
    'not_used_as_v2_source' => 'No consultado como fuente V2',
    'unavailable' => 'No disponible',
    default => $status === '' ? 'No consultado' : 'Pendiente de consulta',
};
$scheduleStatusLabel = static fn(string $status): string => match ($status) {
    'SCHEDULED' => 'Con turno asignado',
    'DAY_OFF' => 'Día de descanso',
    'NEEDS_REVIEW', 'REVIEW_REQUIRED' => 'Requiere revisión',
    'NO_SCHEDULE', 'UNSCHEDULED' => 'Sin turno confirmado',
    default => 'No disponible',
};
$query = [];
if (($selectedWorkplaceId ?? null) !== null) $query['workplace_id'] = (int) $selectedWorkplaceId;
$url = static function (string $path, array $params = []): string {
    $filtered = array_filter($params, static fn(mixed $value): bool => $value !== null && $value !== '');
    return $path . ($filtered === [] ? '' : '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986));
};
$dailyHref = $url('/departamento-operativo/recursos-humanos/checador', $query + [
    'date' => ($date ?? '') !== '' ? (string) $date : null,
    'worker_id' => ($workerId ?? null) !== null ? (int) $workerId : null,
]);
$reportHref = $url('/departamento-operativo/recursos-humanos/checador/reporte', $query);
$weekStart = \App\Services\ChecadorWeeklyScheduleService::monday(
    ($date ?? '') !== '' ? (string) $date : gmdate('Y-m-d')
);
$weeklyHref = !empty($canReadScheduling)
    ? '/departamento-operativo/recursos-humanos/checador/programacion-semanal'
    : null;
$historyHref = !empty($canReadScheduling) && ($workerId ?? null) !== null && ($date ?? '') !== ''
    ? $url('/departamento-operativo/recursos-humanos/checador', $query + [
        'schedule_history' => '1', 'worker_id' => (int) $workerId, 'date' => (string) $date,
    ])
    : null;
$canReadPositions = !empty($permissions['positions_read']);
$canEditPolicy = !empty($permissions['policy_edit']);
$policyWritesAvailable = ($attendancePolicyWritesEnabled ?? false) && ($demoMode ?? false) !== true;
$canOpenPolicyEditor = $canEditPolicy && $policyWritesAvailable;
$canEditReaderProfile = !empty($permissions['reader_profile_edit']);
$canEditPositions = !empty($permissions['positions_edit']);
$m11WritesEnabled = !empty($m11ConfigurationWritesEnabled) && empty($demoMode);
$canEditStationTemplates = !empty($permissions['station_template_edit']);
$templateWritesEnabled = !empty($scheduleTemplateWritesEnabled) && empty($demoMode);
$canVersionStationTemplates = $canEditStationTemplates && $templateWritesEnabled && ($apiTemplates['status'] ?? '') === 'ready';
$policyData = is_array($policy['data'] ?? null) ? $policy['data'] : [];
$policyValues = is_array($policyData['policy'] ?? null) ? $policyData['policy'] : [];
$configurationFeedback = is_array($configurationFeedback ?? null) ? $configurationFeedback : [];
$workplaceName = (string) ($selectedWorkplaceName ?? '');
$displayTemplateName = static function (mixed $value): string {
    $name = trim((string) $value);
    return preg_replace('/^Legacy\s+[^—]+—\s*/u', '', $name) ?? $name;
};
$formatTemplateTime = static function (mixed $value): string {
    $time = trim((string) $value);
    return preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $time) === 1 ? substr($time, 0, 5) : '—';
};
$formatTemplateSegment = static function (array $segment) use ($formatTemplateTime): string {
    $type = strtoupper((string) ($segment['tipo_segmento'] ?? ''));
    $range = $formatTemplateTime($segment['hora_inicio_local'] ?? null)
        . '–' . $formatTemplateTime($segment['hora_fin_local'] ?? null);
    $label = $type === 'BREAK' ? 'Descanso · ' . $range : ($type === 'WORK' ? $range : 'Horario · ' . $range);
    $startDay = (int) ($segment['dia_offset_inicio'] ?? 0);
    $endDay = (int) ($segment['dia_offset_fin'] ?? 0);
    if ($endDay > $startDay) {
        $days = $endDay - $startDay;
        $label .= ' (+' . $days . ($days === 1 ? ' día)' : ' días)');
    }
    return $label;
};
$templateOperationTypeIsSupported = static fn(array $template): bool => in_array(
    strtoupper((string) ($template['tipo_turno'] ?? '')),
    ['REGULAR', 'NOCTURNO', 'MIXTO', 'ROTATIVO'],
    true
) && is_array($template['segments'] ?? null)
    && count($template['segments']) >= 1
    && count($template['segments']) <= 255;
?>

<div id="container" class="mt-4 mb-5">
    <?php if (($demoMode ?? false) === true): ?><p class="mb-3"><span class="badge rounded-pill text-bg-secondary" title="Los datos mostrados no son operativos">Demo</span></p><?php endif; ?>
    <?php if (($selectedWorkplaceId ?? null) === null): ?>
        <div class="alert alert-info" role="status">Selecciona un centro autorizado en el selector superior para consultar su configuración.</div>
    <?php elseif (($historyInputInvalid ?? false) === true): ?>
        <div class="alert alert-warning" role="status">El trabajador o la fecha no tienen un formato válido. No se consultó el historial.</div>
    <?php elseif ($config === []): ?>
        <div class="alert alert-warning" role="status">La configuración no está disponible. No se presentan valores como confirmados.</div>
    <?php endif; ?>

    <?php if (($demoMode ?? false) === true): ?>
        <div class="alert alert-info py-2" role="status">Demostración local. No consulta datos operativos ni el servicio externo.</div>
    <?php endif; ?>
    <?php if (($configurationFeedback['workplace_id'] ?? null) === ($selectedWorkplaceId ?? null) && ($configurationFeedback['message'] ?? '') !== ''): ?>
        <div class="alert alert-<?= ($configurationFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status"><?= $esc($configurationFeedback['message']) ?></div>
    <?php endif; ?>

    <section class="card mb-4" id="attendance-policy-v2" aria-labelledby="attendance-rules-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-clock-hour-4 fs-5"></i></span>
            <div><h2 class="h5 mb-0 text-white" id="attendance-rules-heading">Configuración de asistencia</h2><span class="small text-white-50">Retardos e incidencias</span></div>
        </div>
        <div class="card-body">
            <p class="small text-muted">Esta política no modifica por sí misma la evaluación oficial de asistencia.</p>
            <?php if (($policy['status'] ?? '') === 'permission_denied'): ?>
                <p class="text-muted mb-0">Tu perfil no tiene permiso específico para leer políticas de asistencia.</p>
            <?php elseif (($policy['status'] ?? '') === 'ready' && $policyData !== []): ?>
                <p class="small">Estado: <?= $esc($policyData['status'] ?? 'No disponible') ?> · Zona horaria: <?= $esc($policyData['timezone_id'] ?? 'No disponible') ?> · Fecha local actual: <?= $esc($policyData['current_local_date'] ?? 'No disponible') ?></p>
                <?php if ($policyValues !== []): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6"><div class="bg-primary-subtle rounded p-3 h-100"><span class="small text-primary">Tolerancia por retardo</span><div class="fs-5 fw-semibold text-primary"><?= (int) ($policyValues['retardo_tolerance_minutes'] ?? 0) ?> <span class="fs-6 fw-normal">minutos</span></div></div></div>
                        <div class="col-12 col-md-6"><div class="bg-primary-subtle rounded p-3 h-100"><span class="small text-primary">Plazo de incidencias</span><div class="fs-5 fw-semibold text-primary"><?= (int) ($policyValues['incidence_deadline_days'] ?? 0) ?> <span class="fs-6 fw-normal">días</span></div></div></div>
                    </div>
                    <p class="small text-muted">Vigente desde <?= $esc($policyValues['effective_from_local_date'] ?? '') ?></p>
                <?php else: ?><div class="alert alert-secondary py-2" role="status">No hay una configuración vigente para la fecha consultada. Los valores anteriores no se muestran como vigentes.</div><?php endif; ?>
            <?php else: ?><p class="text-muted mb-0">Estado de configuración: <?= $esc($labelForStatus((string) ($policy['status'] ?? 'unavailable'))) ?>.</p><?php endif; ?>
            <?php if ($canOpenPolicyEditor || $weeklyHref !== null): ?>
                <div class="dropdown mt-3">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Acciones</button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <?php if ($canOpenPolicyEditor): ?><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#policy-edit-modal"><i class="ti ti-edit me-2" aria-hidden="true"></i>Editar configuración</button><?php endif; ?>
                        <?php if ($weeklyHref !== null): ?><a class="dropdown-item" href="<?= $esc($weeklyHref) ?>"><i class="ti ti-clock-hour-4 me-2" aria-hidden="true"></i>Consultar horarios y turnos</a><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($canOpenPolicyEditor): ?>
                    <div class="modal fade" id="policy-edit-modal" tabindex="-1" aria-labelledby="policy-edit-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                        <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                            <div class="modal-header bg-primary"><h3 class="modal-title h5 text-white" id="policy-edit-title">Editar configuración de asistencia</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                            <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/politica-asistencia">
                                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>"><input type="hidden" name="idempotency_key" value="m11-policy-<?= bin2hex(random_bytes(16)) ?>">
                                <div class="modal-body"><p class="small text-muted">Agrega una vigencia futura. La política actual y sus versiones anteriores se conservan.</p>
                                    <label class="form-label" for="policy-effective-date">Fecha de vigencia</label><input class="form-control mb-3" id="policy-effective-date" type="date" name="effective_from_local_date" min="<?= $esc($policyData['current_local_date'] ?? '') ?>" required>
                                    <label class="form-label" for="policy-tolerance">Tolerancia por retardo (minutos)</label><input class="form-control mb-3" id="policy-tolerance" type="number" name="retardo_tolerance_minutes" min="0" max="120" step="1" required>
                                    <label class="form-label" for="policy-deadline">Plazo de incidencias (días)</label><input class="form-control mb-3" id="policy-deadline" type="number" name="incidence_deadline_days" min="0" max="30" step="1" required>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" id="policy-confirm" name="confirm_policy" value="1" required><label class="form-check-label" for="policy-confirm">Confirmo estos valores para la localidad seleccionada.</label></div>
                                </div><div class="modal-footer"><button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar nueva vigencia</button></div>
                            </form>
                        </div></div>
                    </div>
            <?php elseif ($canEditPolicy): ?><p class="small text-muted mt-3 mb-0">La edición de esta configuración no está disponible en este entorno.</p><?php endif; ?>
        </div>
    </section>

    <?php if ($canReadPositions): ?>
    <section class="card mb-4" id="people-positions" aria-labelledby="people-positions-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-users fs-5"></i></span>
            <div><h2 class="h5 mb-0 text-white" id="people-positions-heading">Personal y puestos</h2><span class="small text-white-50">Catálogo institucional</span></div>
            <?php if ($canEditPositions && $m11WritesEnabled): ?><button type="button" class="btn btn-light btn-sm text-primary ms-auto" data-bs-toggle="modal" data-bs-target="#position-create-modal"><i class="ti ti-plus me-1" aria-hidden="true"></i>Nuevo puesto</button><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (($positions['status'] ?? '') === 'current_catalog'): ?>
                <?php if ($canEditPositions && !$m11WritesEnabled): ?><p class="small text-muted mb-3">La administración de puestos no está disponible en este entorno; el catálogo permanece en consulta.</p><?php endif; ?>
                <?php if (($positions['items'] ?? []) === []): ?><p class="small text-muted mb-0">No hay puestos activos visibles.</p>
                <?php else: ?><div class="datatables"><div class="table-responsive-lg"><table id="m11-position-table" data-checador-configuration-datatable class="table table-sm table-striped table-bordered align-middle mb-0 w-100"><caption class="visually-hidden">Catálogo institucional de puestos</caption><thead><tr><th scope="col">Puesto</th><?php if ($canEditPositions): ?><th scope="col" class="text-center"><span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i></th><?php endif; ?></tr></thead><tbody><?php foreach ($positions['items'] as $position): ?><tr><td><?= $esc($position['name'] ?? '') ?></td><?php if ($canEditPositions): ?><td class="text-center"><?php if ($m11WritesEnabled): ?><div class="dropdown"><button class="btn btn-sm p-1 text-muted border-0 bg-transparent" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Acciones para el puesto <?= $esc($position['name'] ?? '') ?>"><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i></button><div class="dropdown-menu dropdown-menu-end"><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#position-edit-modal-<?= (int) $position['id'] ?>"><i class="ti ti-edit me-2" aria-hidden="true"></i>Editar</button></div></div><?php else: ?><span class="text-muted small">Solo consulta</span><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div></div>
                    <?php if ($canEditPositions && $m11WritesEnabled): ?>
                        <div class="modal fade" id="position-create-modal" tabindex="-1" aria-labelledby="position-create-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary"><h3 class="modal-title h5 text-white" id="position-create-title">Agregar puesto</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/puestos"><input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>"><div class="modal-body"><label class="form-label" for="position-name-new">Nombre del puesto</label><input class="form-control" id="position-name-new" name="name" maxlength="100" required><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="position-confirm-new" name="confirm_position" value="1" required><label class="form-check-label" for="position-confirm-new">Confirmo agregar este puesto al catálogo institucional.</label></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar puesto</button></div></form></div></div></div>
                        <?php foreach ($positions['items'] as $position): ?><div class="modal fade" id="position-edit-modal-<?= (int) $position['id'] ?>" tabindex="-1" aria-labelledby="position-edit-title-<?= (int) $position['id'] ?>" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary"><h3 class="modal-title h5 text-white" id="position-edit-title-<?= (int) $position['id'] ?>">Editar puesto</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/puestos"><input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>"><input type="hidden" name="position_id" value="<?= (int) $position['id'] ?>"><div class="modal-body"><label class="form-label" for="position-name-<?= (int) $position['id'] ?>">Nombre del puesto</label><input class="form-control" id="position-name-<?= (int) $position['id'] ?>" name="name" maxlength="100" value="<?= $esc($position['name'] ?? '') ?>" required><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="position-confirm-<?= (int) $position['id'] ?>" name="confirm_position" value="1" required><label class="form-check-label" for="position-confirm-<?= (int) $position['id'] ?>">Confirmo actualizar el puesto.</label></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div></form></div></div></div><?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php elseif (($positions['status'] ?? '') === 'not_read_in_demo'): ?><p class="small text-muted mb-0">El catálogo institucional no se consulta durante la demostración para evitar mezclar datos sintéticos y base real.</p>
            <?php else: ?><p class="small text-muted mb-0">Catálogo de puestos: <?= $esc($labelForStatus((string) ($positions['status'] ?? ''))) ?>.</p><?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="card mb-4" id="reader-access" aria-labelledby="reader-access-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-device-desktop fs-5"></i></span>
            <div><h2 class="h5 mb-0 text-white" id="reader-access-heading">Perfil del lector</h2><span class="small text-white-50">Usuario de la estación seleccionada</span></div>
        </div>
        <div class="card-body">
            <p class="small text-muted">Las credenciales no se consultan ni se muestran.</p>
            <?php if (empty($permissions['reader_profile_read'])): ?>
                <p class="mb-0">No tienes acceso a los perfiles del lector de esta localidad.</p>
            <?php elseif (($readerProfiles['status'] ?? '') === 'station_scoped'): ?>
                <?php if (($readerProfiles['items'] ?? []) === []): ?><p class="mb-0">No hay perfiles activos para la estación física vinculada al centro autorizado.</p>
                <?php else: ?>
                    <div class="table-responsive-lg"><table data-checador-configuration-datatable class="table table-sm table-striped table-bordered align-middle mb-0 w-100">
                        <caption class="visually-hidden">Perfiles de aplicación del lector para el centro autorizado</caption>
                        <thead><tr><th scope="col">Usuario de aplicación</th><?php if ($canEditReaderProfile): ?><th scope="col" class="text-center">Acciones</th><?php endif; ?></tr></thead>
                        <tbody><?php foreach ($readerProfiles['items'] as $profile): ?>
                            <tr><td><?= $esc($profile['usuario'] ?? '') ?></td>
                                <?php if ($canEditReaderProfile): ?><td class="text-center">
                                    <?php if ($m11WritesEnabled): ?>
                                        <div class="dropdown"><button class="btn btn-sm p-1 text-muted border-0 bg-transparent" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Acciones del perfil del lector"><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i></button>
                                            <div class="dropdown-menu dropdown-menu-end"><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#reader-profile-modal-<?= (int) $profile['id'] ?>"><i class="ti ti-edit me-2" aria-hidden="true"></i>Editar perfil</button></div>
                                        </div>
                                    <?php else: ?><span class="text-muted small">Solo consulta</span><?php endif; ?>
                                </td><?php endif; ?>
                            </tr>
                        <?php endforeach; ?></tbody>
                    </table></div>
                    <?php if ($canEditReaderProfile && $m11WritesEnabled): foreach ($readerProfiles['items'] as $profile): ?>
                        <div class="modal fade" id="reader-profile-modal-<?= (int) $profile['id'] ?>" tabindex="-1" aria-labelledby="reader-profile-title-<?= (int) $profile['id'] ?>" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                            <div class="modal-header bg-primary"><h3 class="modal-title h5 text-white" id="reader-profile-title-<?= (int) $profile['id'] ?>">Editar perfil del lector</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                            <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/perfiles-lector">
                                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>"><input type="hidden" name="profile_id" value="<?= (int) $profile['id'] ?>">
                                <div class="modal-body"><label class="form-label" for="reader-username-<?= (int) $profile['id'] ?>">Usuario del lector</label><input class="form-control mb-3" id="reader-username-<?= (int) $profile['id'] ?>" name="username" maxlength="120" autocomplete="username" value="<?= $esc($profile['usuario'] ?? '') ?>" required>
                                    <label class="form-label" for="reader-password-<?= (int) $profile['id'] ?>">Nueva credencial (opcional)</label><input class="form-control mb-3" id="reader-password-<?= (int) $profile['id'] ?>" name="new_password" type="password" maxlength="512" autocomplete="new-password">
                                    <label class="form-label" for="reader-password-confirm-<?= (int) $profile['id'] ?>">Confirmar nueva credencial</label><input class="form-control mb-3" id="reader-password-confirm-<?= (int) $profile['id'] ?>" name="new_password_confirmation" type="password" maxlength="512" autocomplete="new-password">
                                    <p class="small text-muted">Deja ambos campos vacíos para conservar la credencial existente. La credencial nunca se vuelve a mostrar.</p>
                                    <div class="form-check"><input class="form-check-input" type="checkbox" id="reader-profile-confirm-<?= (int) $profile['id'] ?>" name="confirm_reader_profile" value="1" required><label class="form-check-label" for="reader-profile-confirm-<?= (int) $profile['id'] ?>">Confirmo actualizar este perfil de la estación seleccionada.</label></div>
                                </div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar perfil</button></div>
                            </form>
                        </div></div></div>
                    <?php endforeach; endif; ?>
                <?php endif; ?>
            <?php else: ?><p class="mb-0">Perfil de lector: <?= $esc($labelForStatus((string) ($readerProfiles['status'] ?? 'unavailable'))) ?>.</p><?php endif; ?>
            <?php if ($canEditReaderProfile && !$m11WritesEnabled): ?><p class="small text-muted mt-3 mb-0">La edición del perfil no está disponible en este entorno; la información permanece en consulta.</p><?php endif; ?>
            <?php if (($temporaryAccessAvailable ?? false) === true): ?><div class="border rounded p-3 mt-3"><h3 class="h6">Acceso temporal autorizado</h3><p class="small text-muted mb-2">El acceso temporal aparece en la consulta diaria sólo cuando el perfil tiene autorización.</p><a class="btn btn-sm btn-outline-primary" href="<?= $esc($dailyHref) ?>#temporary-access-heading">Consultar sección autorizada</a></div><?php endif; ?>
        </div>
    </section>

    <?php if (!empty($permissions['device_read'])): ?>
    <section class="card mb-4" id="m11-devices" aria-labelledby="m11-devices-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3"><span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-device-desktop fs-5"></i></span><div><h2 class="h5 mb-0 text-white" id="m11-devices-heading">Administración de dispositivos</h2><span class="small text-white-50">Inventario autorizado · solo consulta</span></div></div>
        <div class="card-body">
            <p class="mb-2"><span class="badge text-bg-light border"><?= $esc($labelForStatus((string) ($deviceAuth['status'] ?? 'not_integrated'))) ?></span><?php if (isset($deviceAuth['completeness'])): ?> <span class="badge text-bg-secondary"><?= $esc($deviceAuth['completeness']) ?><?php if (isset($deviceAuth['read_at_utc'])): ?> · <?= $esc($deviceAuth['read_at_utc']) ?><?php endif; ?></span><?php endif; ?></p>
            <?php if (($deviceAuth['status'] ?? '') === 'ready'): ?>
                <?php if (($deviceAuth['items'] ?? []) === []): ?><p class="mb-0">La lectura autorizada no devolvió dispositivos para el centro seleccionado.</p>
                <?php else: ?><div class="table-responsive-lg"><table id="m11-devices-table" data-checador-configuration-datatable class="table table-sm table-striped table-bordered align-middle mb-0 w-100"><caption class="visually-hidden">Dispositivos administrados asignados al centro autorizado</caption><thead><tr><th scope="col">Identificador</th><th scope="col">Dispositivo</th><th scope="col">Estación física</th><th scope="col">Localidad canónica</th><th scope="col">Asignación vigente</th><th scope="col">Estado registrado</th><th scope="col">Última recepción</th></tr></thead><tbody>
                    <?php foreach ($deviceAuth['items'] as $deviceItem): ?><tr><td><?= (int) ($deviceItem['device']['id'] ?? 0) ?></td><td><?= $esc($deviceItem['device']['name'] ?? '') ?></td><td><?= $esc($deviceItem['physical_station']['name'] ?? '') ?></td><td><?= $esc($deviceItem['workplace']['name'] ?? $workplaceName) ?> · <?= (int) ($deviceItem['workplace']['id'] ?? $selectedWorkplaceId ?? 0) ?></td><td><?= $esc($deviceItem['assignment']['status'] ?? 'UNKNOWN') ?></td><td><?= $esc($deviceItem['device']['persisted_status'] ?? '') ?></td><td><?= $esc($deviceItem['last_communication']['last_server_received_at_utc'] ?? 'Sin recepción registrada') ?></td></tr><?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
            <?php else: ?><p class="mb-0">No se pudo completar la lectura autorizada del inventario de dispositivos.</p><?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="card mb-4" id="m11-station-readers" aria-labelledby="m11-station-readers-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3"><span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-map-pin fs-5"></i></span><div><h2 class="h5 mb-0 text-white" id="m11-station-readers-heading">Estaciones y lectores asignados</h2><span class="small text-white-50">Relación institucional verificada</span></div></div>
        <div class="card-body">
            <?php if (($selectedWorkplaceId ?? null) === null): ?><p class="mb-0">Selecciona una localidad autorizada para consultar su relación institucional.</p>
            <?php elseif (($deviceAuth['status'] ?? '') === 'ready' && ($deviceAuth['items'] ?? []) !== []): ?>
                <div class="table-responsive-lg"><table data-checador-configuration-datatable class="table table-sm table-striped table-bordered align-middle mb-0"><thead><tr><th scope="col">Localidad canónica</th><th scope="col">Estación física</th><th scope="col">Lector</th><th scope="col">Asignación</th><th scope="col">Vigente desde (UTC)</th><th scope="col">Vigente hasta (UTC)</th></tr></thead><tbody>
                    <?php foreach ($deviceAuth['items'] as $deviceItem): ?><tr><td><?= $esc($deviceItem['workplace']['name'] ?? $workplaceName) ?> · <?= (int) ($deviceItem['workplace']['id'] ?? $selectedWorkplaceId) ?></td><td><?= $esc($deviceItem['physical_station']['name'] ?? '') ?> · <?= (int) ($deviceItem['physical_station']['id'] ?? 0) ?></td><td><?= $esc($deviceItem['device']['name'] ?? '') ?></td><td><?= $esc($deviceItem['assignment']['status'] ?? 'UNKNOWN') ?></td><td><?= $esc($deviceItem['assignment']['valid_from_utc'] ?? '—') ?></td><td><?= $esc($deviceItem['assignment']['valid_to_utc'] ?? 'Sin fecha de término') ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            <?php elseif (($deviceAuth['status'] ?? '') === 'ready' && ($deviceAuth['completeness'] ?? '') === 'COMPLETE'): ?><p class="mb-0">La consulta completa no reportó lectores asignados a esta localidad.</p>
            <?php elseif (empty($permissions['device_read'])): ?><p class="mb-0">No tienes permiso para consultar la relación entre lectores y esta localidad.</p>
            <?php else: ?><p class="mb-0">La relación entre la localidad canónica y sus lectores no pudo confirmarse con la respuesta disponible.</p><?php endif; ?>
        </div>
    </section>

    <section class="card mb-4" id="m11-effective-permissions" aria-labelledby="m11-effective-permissions-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3"><span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-lock-check fs-5"></i></span><div><h2 class="h5 mb-0 text-white" id="m11-effective-permissions-heading">Permisos efectivos</h2><span class="small text-white-50">Usuario autenticado · alcance de la localidad seleccionada</span></div></div>
        <div class="card-body">
            <p>Permisos calculados por el servidor para <strong><?= $esc($workplaceName !== '' ? $workplaceName : 'la localidad seleccionada') ?></strong>. No se infieren del rol únicamente.</p>
            <div class="table-responsive-lg"><table data-checador-configuration-datatable class="table table-sm table-striped table-bordered align-middle mb-0"><thead><tr><th scope="col">Módulo</th><th scope="col">Acción</th><th scope="col">Alcance</th><th scope="col">Permiso efectivo</th></tr></thead><tbody>
                <?php $permissionLabels = [
                    'policy_read' => ['Configuración de asistencia', 'Consultar'], 'policy_edit' => ['Configuración de asistencia', 'Editar vigencias'],
                    'schedule_read' => ['Programación semanal', 'Consultar'], 'v2_schedule_edit' => ['Programación semanal', 'Editar asignaciones'],
                    'station_template_edit' => ['Plantillas de estación', 'Crear versiones'], 'reader_profile_read' => ['Perfil del lector', 'Consultar'],
                    'reader_profile_edit' => ['Perfil del lector', 'Editar'], 'positions_read' => ['Puestos', 'Consultar'],
                    'positions_create' => ['Puestos', 'Agregar'], 'positions_edit' => ['Puestos', 'Editar'],
                    'positions_delete' => ['Puestos', 'Eliminar'], 'device_read' => ['Dispositivos', 'Consultar'],
                ]; foreach ($permissionLabels as $permissionKey => [$moduleLabel, $actionLabel]): ?>
                    <tr><td><?= $esc($moduleLabel) ?></td><td><?= $esc($actionLabel) ?></td><td><?= $esc($workplaceName !== '' ? $workplaceName : 'Localidad seleccionada') ?></td><td><span class="badge <?= !empty($permissions[$permissionKey]) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= !empty($permissions[$permissionKey]) ? 'Permitido' : 'No permitido' ?></span></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
        </div>
    </section>

    <section class="card mb-4" id="m11-change-history" aria-labelledby="m11-change-history-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3"><span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-history fs-5"></i></span><div><h2 class="h5 mb-0 text-white" id="m11-change-history-heading">Historial de cambios</h2><span class="small text-white-50">Registros auditados del alcance autorizado</span></div></div>
        <div class="card-body"><p class="mb-0">El controlador actual no entrega una lectura contractual del historial de auditoría para esta pantalla. No se presentan cambios que no hayan sido consultados desde una fuente autorizada.</p></div>
    </section>

    <section class="card mb-4 mt-4" id="schedule-configuration" aria-labelledby="schedule-configuration-heading">
        <div class="card-header bg-primary text-white d-flex flex-wrap align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-clock-hour-4 fs-5"></i></span>
            <div><h2 class="h5 mb-0 text-white" id="schedule-configuration-heading">Horarios y turnos</h2><span class="small text-white-50">Turnos y vigencias disponibles</span></div>
            <?php if ($canEditStationTemplates && $templateWritesEnabled && ($apiTemplates['status'] ?? '') === 'ready'): ?><button type="button" class="btn btn-light btn-sm text-primary ms-auto text-nowrap" data-bs-toggle="modal" data-bs-target="#station-template-create-modal"><i class="ti ti-plus me-1" aria-hidden="true"></i>Nueva plantilla de estación</button><?php endif; ?>
        </div>
        <div class="card-body">
            <p class="small text-muted">Horarios disponibles para el centro seleccionado.</p>
            <?php if (($historyHref ?? null) !== null): ?>
                <p class="small">Consulta de vigencias para el colaborador y la fecha seleccionados:
                    <a href="<?= $esc($historyHref) ?>">Consultar vigencia histórica</a>
                </p>
            <?php else: ?>
                <p class="small text-muted">Para consultar el horario efectivo y su historial, selecciona un colaborador y una fecha en Asistencia del día.</p>
            <?php endif; ?>
            <?php if (($apiTemplates['status'] ?? '') === 'permission_denied'): ?>
                <div class="alert alert-warning mb-3" role="status">Tu perfil no cuenta con permiso de lectura para consultar el catálogo de turnos.</div>
            <?php elseif (in_array(($apiTemplates['status'] ?? ''), ['unavailable', 'not_requested'], true)): ?>
                <div class="alert alert-warning mb-3" role="status">El catálogo de turnos no está disponible; los registros anteriores no se usan como sustituto.</div>
            <?php else: ?>
                <p class="small text-muted mb-2"><span class="badge text-bg-light border"><?= $esc($labelForStatus((string) ($apiTemplates['status'] ?? ''))) ?></span></p>
                <?php if (($apiTemplates['items'] ?? []) === []): ?>
                    <p class="text-muted">No hay plantillas visibles en esta respuesta. Una lista vacía no permite concluir que no existan asignaciones.</p>
                <?php else: ?>
                    <div class="table-responsive-lg mb-4">
                        <table data-checador-configuration-datatable class="table table-striped table-bordered align-middle mb-0">
                            <caption class="visually-hidden">Turnos disponibles y sus bloques horarios</caption>
                            <thead><tr><th scope="col">Turno</th><th scope="col">Versión</th><th scope="col">Alcance</th><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">Bloques de horario</th><?php if ($canVersionStationTemplates): ?><th scope="col" class="text-center"><span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i></th><?php endif; ?></tr></thead>
                            <tbody><?php foreach (($apiTemplates['items'] ?? []) as $template): ?>
                                <?php
                                $templateScope = strtoupper((string) ($template['scope'] ?? ''));
                                $templateSegments = is_array($template['segments'] ?? null) ? $template['segments'] : [];
                                $templateEditable = $templateOperationTypeIsSupported($template);
                                $canVersionThisStationTemplate = $canVersionStationTemplates
                                    && $templateScope === 'STATION'
                                    && (int) ($template['id_estacion'] ?? 0) === (int) $selectedWorkplaceId
                                    && $templateEditable;
                                $canCustomizeGlobalTemplate = $canVersionStationTemplates
                                    && $templateScope === 'GLOBAL'
                                    && !is_array($template['station_customization'] ?? null)
                                    && $templateEditable;
                                $templateSegmentsJson = json_encode(array_map(static fn(array $segment): array => [
                                    'tipo_segmento' => strtoupper((string) ($segment['tipo_segmento'] ?? 'WORK')) === 'BREAK' ? 'BREAK' : 'WORK',
                                    'hora_inicio_local' => substr((string) ($segment['hora_inicio_local'] ?? ''), 0, 5),
                                    'hora_fin_local' => substr((string) ($segment['hora_fin_local'] ?? ''), 0, 5),
                                    'dia_offset_inicio' => (int) ($segment['dia_offset_inicio'] ?? 0),
                                    'dia_offset_fin' => (int) ($segment['dia_offset_fin'] ?? 0),
                                ], $templateSegments), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                                ?>
                                <tr>
                                    <th scope="row"><?= $esc($displayTemplateName($template['nombre'] ?? 'No disponible')) ?></th>
                                    <td><?= (int) ($template['version'] ?? 0) ?></td>
                                    <td><?php if (($template['scope'] ?? '') === 'STATION'): ?>Personalizada para este centro<?php else: ?>Global<?php if (is_array($template['station_customization'] ?? null)): ?><div class="small text-muted">Tiene adaptación de estación</div><?php endif; ?><?php endif; ?></td>
                                    <td><?= $esc(match ((string) ($template['tipo_turno'] ?? '')) { 'REGULAR' => 'Ordinario', 'NOCTURNO' => 'Nocturno', 'MIXTO' => 'Mixto', 'ROTATIVO' => 'Rotativo', 'SPECIAL' => 'Especial', 'REST' => 'Descanso', default => 'No disponible' }) ?></td>
                                    <td><?= !empty($template['es_activo']) ? 'Activa' : 'Inactiva' ?></td>
                                    <td><?php if (is_array($template['segments'] ?? null) && $template['segments'] !== []): ?>
                                        <ul class="mb-0 ps-3"><?php foreach ($template['segments'] as $segment): ?>
                                            <li><?= $esc($formatTemplateSegment($segment)) ?></li>
                                        <?php endforeach; ?></ul>
                                    <?php else: ?>Detalle no disponible<?php endif; ?></td>
                                    <?php if ($canVersionStationTemplates): ?><td class="text-center"><?php if ($canVersionThisStationTemplate || $canCustomizeGlobalTemplate): ?><div class="dropdown"><button class="btn btn-sm p-1 text-muted border-0 bg-transparent" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Acciones para <?= $esc($displayTemplateName($template['nombre'] ?? 'plantilla')) ?>"><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i></button><div class="dropdown-menu dropdown-menu-end"><?php if ($canVersionThisStationTemplate): ?><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#station-template-version-<?= (int) ($template['id'] ?? 0) ?>"><i class="ti ti-copy me-2" aria-hidden="true"></i>Nueva versión</button><?php elseif ($canCustomizeGlobalTemplate): ?><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#station-template-create-modal" data-schedule-template-prefill data-template-name="<?= $esc($displayTemplateName($template['nombre'] ?? '')) ?>" data-template-type="<?= $esc(strtoupper((string) ($template['tipo_turno'] ?? ''))) ?>" data-template-segments="<?= $esc($templateSegmentsJson === false ? '[]' : $templateSegmentsJson) ?>"><i class="ti ti-copy me-2" aria-hidden="true"></i>Personalizar para esta estación</button><?php endif; ?></div></div><?php endif; ?></td><?php endif; ?>
                                </tr>
                            <?php endforeach; ?></tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($canEditStationTemplates): ?>
                <?php if ($templateWritesEnabled && ($apiTemplates['status'] ?? '') === 'ready'): ?>
                    <div class="modal fade" id="station-template-create-modal" tabindex="-1" aria-labelledby="station-template-create-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header bg-primary"><h3 class="modal-title h5 text-white" id="station-template-create-title">Nueva plantilla para esta estación</h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="p-3">
                        <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/plantillas" data-schedule-template-form>
                            <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>"><input type="hidden" name="operation" value="create">
                            <div class="modal-body pt-0"><label class="form-label" for="station-template-name">Nombre</label><input class="form-control mb-3" id="station-template-name" name="template_name" maxlength="120" required><label class="form-label" for="station-template-type">Tipo de turno</label><select class="form-select mb-3" id="station-template-type" name="template_type" required><option value="REGULAR">Ordinario</option><option value="NOCTURNO">Nocturno</option><option value="MIXTO">Mixto</option><option value="ROTATIVO">Rotativo</option></select><h4 class="h6">Segmentos horarios</h4><div data-schedule-segment-container><div class="row g-2 align-items-end mb-2" data-schedule-segment-row><div class="col-12 col-md-3"><label class="form-label" for="create-segment-type-0">Tipo</label><select class="form-select" id="create-segment-type-0" name="segments[0][tipo_segmento]"><option value="WORK">Trabajo</option><option value="BREAK">Descanso</option></select></div><div class="col-6 col-md-3"><label class="form-label" for="create-segment-start-0">Inicio</label><input class="form-control" type="time" id="create-segment-start-0" name="segments[0][hora_inicio_local]" required></div><div class="col-6 col-md-3"><label class="form-label" for="create-segment-end-0">Fin</label><input class="form-control" type="time" id="create-segment-end-0" name="segments[0][hora_fin_local]" required></div><div class="col-6 col-md-1"><label class="form-label" for="create-segment-start-offset-0">Día ini.</label><select class="form-select" id="create-segment-start-offset-0" name="segments[0][dia_offset_inicio]"><option value="0">0</option><option value="1">+1</option></select></div><div class="col-6 col-md-1"><label class="form-label" for="create-segment-end-offset-0">Día fin</label><select class="form-select" id="create-segment-end-offset-0" name="segments[0][dia_offset_fin]"><option value="0">0</option><option value="1">+1</option></select></div><div class="col-12 col-md-1"><button type="button" class="btn btn-outline-danger btn-sm" data-schedule-remove-segment aria-label="Quitar segmento">−</button></div></div></div><button type="button" class="btn btn-outline-primary btn-sm" data-schedule-add-segment>Agregar segmento</button><div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="confirm_template" id="confirm-create-template" value="1" required><label class="form-check-label" for="confirm-create-template">Se creará una plantilla nueva y exclusiva para la estación seleccionada. La plantilla global y otras estaciones no se modificarán.</label></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" data-schedule-template-submit>Guardar plantilla</button></div>
                            <template data-schedule-segment-template><div class="row g-2 align-items-end mb-2" data-schedule-segment-row><div class="col-12 col-md-3"><label class="form-label" for="create-segment-type-__INDEX__">Tipo</label><select class="form-select" id="create-segment-type-__INDEX__" name="segments[__INDEX__][tipo_segmento]"><option value="WORK">Trabajo</option><option value="BREAK">Descanso</option></select></div><div class="col-6 col-md-3"><label class="form-label" for="create-segment-start-__INDEX__">Inicio</label><input class="form-control" type="time" id="create-segment-start-__INDEX__" name="segments[__INDEX__][hora_inicio_local]" required></div><div class="col-6 col-md-3"><label class="form-label" for="create-segment-end-__INDEX__">Fin</label><input class="form-control" type="time" id="create-segment-end-__INDEX__" name="segments[__INDEX__][hora_fin_local]" required></div><div class="col-6 col-md-1"><label class="form-label" for="create-segment-start-offset-__INDEX__">Día ini.</label><select class="form-select" id="create-segment-start-offset-__INDEX__" name="segments[__INDEX__][dia_offset_inicio]"><option value="0">0</option><option value="1">+1</option></select></div><div class="col-6 col-md-1"><label class="form-label" for="create-segment-end-offset-__INDEX__">Día fin</label><select class="form-select" id="create-segment-end-offset-__INDEX__" name="segments[__INDEX__][dia_offset_fin]"><option value="0">0</option><option value="1">+1</option></select></div><div class="col-12 col-md-1"><button type="button" class="btn btn-outline-danger btn-sm" data-schedule-remove-segment aria-label="Quitar segmento">−</button></div></div></template>
                        </form></div></div></div></div>
                    <?php foreach (($apiTemplates['items'] ?? []) as $template): if (($template['scope'] ?? '') !== 'STATION' || (int) ($template['id_estacion'] ?? 0) !== (int) $selectedWorkplaceId) continue; $segments = is_array($template['segments'] ?? null) ? $template['segments'] : []; ?>
                        <div class="modal fade" id="station-template-version-<?= (int) $template['id'] ?>" tabindex="-1" aria-labelledby="station-template-version-title-<?= (int) $template['id'] ?>" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header bg-primary"><h3 class="modal-title h5 text-white" id="station-template-version-title-<?= (int) $template['id'] ?>">Nueva versión · <?= $esc($displayTemplateName($template['nombre'] ?? '')) ?></h3><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="p-3"><form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/plantillas" data-schedule-template-form><input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>"><input type="hidden" name="workplace_id" value="<?= (int) $selectedWorkplaceId ?>"><input type="hidden" name="operation" value="version"><input type="hidden" name="template_key" value="<?= $esc($template['clave'] ?? '') ?>"><div class="modal-body pt-0"><label class="form-label" for="version-template-name-<?= (int) $template['id'] ?>">Nombre de la nueva versión</label><input class="form-control mb-3" id="version-template-name-<?= (int) $template['id'] ?>" name="template_name" maxlength="120" value="<?= $esc($template['nombre'] ?? '') ?>" required><label class="form-label" for="version-template-type-<?= (int) $template['id'] ?>">Tipo de turno</label><select class="form-select mb-3" id="version-template-type-<?= (int) $template['id'] ?>" name="template_type"><option value="REGULAR" <?= ($template['tipo_turno'] ?? '') === 'REGULAR' ? 'selected' : '' ?>>Ordinario</option><option value="NOCTURNO" <?= ($template['tipo_turno'] ?? '') === 'NOCTURNO' ? 'selected' : '' ?>>Nocturno</option><option value="MIXTO" <?= ($template['tipo_turno'] ?? '') === 'MIXTO' ? 'selected' : '' ?>>Mixto</option><option value="ROTATIVO" <?= ($template['tipo_turno'] ?? '') === 'ROTATIVO' ? 'selected' : '' ?>>Rotativo</option></select><div data-schedule-segment-container><?php foreach ($segments as $index => $segment): ?><div class="row g-2 align-items-end mb-2" data-schedule-segment-row><div class="col-12 col-md-3"><label class="form-label" for="version-segment-type-<?= (int) $template['id'] ?>-<?= (int) $index ?>">Tipo</label><select class="form-select" id="version-segment-type-<?= (int) $template['id'] ?>-<?= (int) $index ?>" name="segments[<?= (int) $index ?>][tipo_segmento]"><option value="WORK" <?= ($segment['tipo_segmento'] ?? '') === 'WORK' ? 'selected' : '' ?>>Trabajo</option><option value="BREAK" <?= ($segment['tipo_segmento'] ?? '') === 'BREAK' ? 'selected' : '' ?>>Descanso</option></select></div><div class="col-6 col-md-3"><label class="form-label" for="version-segment-start-<?= (int) $template['id'] ?>-<?= (int) $index ?>">Inicio</label><input class="form-control" type="time" id="version-segment-start-<?= (int) $template['id'] ?>-<?= (int) $index ?>" name="segments[<?= (int) $index ?>][hora_inicio_local]" value="<?= $esc(substr((string) ($segment['hora_inicio_local'] ?? ''), 0, 5)) ?>" required></div><div class="col-6 col-md-3"><label class="form-label" for="version-segment-end-<?= (int) $template['id'] ?>-<?= (int) $index ?>">Fin</label><input class="form-control" type="time" id="version-segment-end-<?= (int) $template['id'] ?>-<?= (int) $index ?>" name="segments[<?= (int) $index ?>][hora_fin_local]" value="<?= $esc(substr((string) ($segment['hora_fin_local'] ?? ''), 0, 5)) ?>" required></div><div class="col-6 col-md-1"><label class="form-label" for="version-start-offset-<?= (int) $template['id'] ?>-<?= (int) $index ?>">Día ini.</label><select class="form-select" id="version-start-offset-<?= (int) $template['id'] ?>-<?= (int) $index ?>" name="segments[<?= (int) $index ?>][dia_offset_inicio]"><option value="0" <?= (int) ($segment['dia_offset_inicio'] ?? 0) === 0 ? 'selected' : '' ?>>0</option><option value="1" <?= (int) ($segment['dia_offset_inicio'] ?? 0) === 1 ? 'selected' : '' ?>>+1</option></select></div><div class="col-6 col-md-1"><label class="form-label" for="version-end-offset-<?= (int) $template['id'] ?>-<?= (int) $index ?>">Día fin</label><select class="form-select" id="version-end-offset-<?= (int) $template['id'] ?>-<?= (int) $index ?>" name="segments[<?= (int) $index ?>][dia_offset_fin]"><option value="0" <?= (int) ($segment['dia_offset_fin'] ?? 0) === 0 ? 'selected' : '' ?>>0</option><option value="1" <?= (int) ($segment['dia_offset_fin'] ?? 0) === 1 ? 'selected' : '' ?>>+1</option></select></div><div class="col-12 col-md-1"><button type="button" class="btn btn-outline-danger btn-sm" data-schedule-remove-segment aria-label="Quitar segmento">−</button></div></div><?php endforeach; ?></div><button type="button" class="btn btn-outline-primary btn-sm" data-schedule-add-segment>Agregar segmento</button><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="confirm-template-version-<?= (int) $template['id'] ?>" name="confirm_template" value="1" required><label class="form-check-label" for="confirm-template-version-<?= (int) $template['id'] ?>">Confirmo crear una versión nueva. Las versiones anteriores se conservan.</label></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar versión</button></div>
                        <template data-schedule-segment-template><div class="row g-2 align-items-end mb-2" data-schedule-segment-row><div class="col-12 col-md-3"><label class="form-label" for="vtype-__INDEX__">Tipo</label><select class="form-select" id="vtype-__INDEX__" name="segments[__INDEX__][tipo_segmento]"><option value="WORK">Trabajo</option><option value="BREAK">Descanso</option></select></div><div class="col-6 col-md-3"><label class="form-label" for="vstart-__INDEX__">Inicio</label><input class="form-control" type="time" id="vstart-__INDEX__" name="segments[__INDEX__][hora_inicio_local]" required></div><div class="col-6 col-md-3"><label class="form-label" for="vend-__INDEX__">Fin</label><input class="form-control" type="time" id="vend-__INDEX__" name="segments[__INDEX__][hora_fin_local]" required></div><div class="col-6 col-md-1"><label class="form-label" for="vstartoff-__INDEX__">Día ini.</label><select class="form-select" id="vstartoff-__INDEX__" name="segments[__INDEX__][dia_offset_inicio]"><option value="0">0</option><option value="1">+1</option></select></div><div class="col-6 col-md-1"><label class="form-label" for="vendoff-__INDEX__">Día fin</label><select class="form-select" id="vendoff-__INDEX__" name="segments[__INDEX__][dia_offset_fin]"><option value="0">0</option><option value="1">+1</option></select></div><div class="col-12 col-md-1"><button type="button" class="btn btn-outline-danger btn-sm" data-schedule-remove-segment>−</button></div></div></template></form></div></div></div></div>
                    <?php endforeach; ?>
                <?php elseif (($apiTemplates['status'] ?? '') === 'ready'): ?><p class="small text-muted">La administración de plantillas está restringida al permiso explícito de estación y no se infiere del permiso de consulta.</p><?php endif; ?>
                <?php if ($canEditStationTemplates && !$templateWritesEnabled): ?><p class="small text-muted mt-2">La administración de plantillas está disponible solo en modo consulta en este entorno.</p><?php endif; ?>
            <?php endif; ?>

            <?php if (($apiHistory['status'] ?? '') !== 'not_requested'): ?>
                <h3 class="h6 mt-3">Horario efectivo e historial</h3>
                <p class="small text-muted"><span class="badge text-bg-light border"><?= $esc($labelForStatus((string) ($apiHistory['status'] ?? ''))) ?></span></p>
                <?php if (is_array($apiHistory['effective_schedule'] ?? null)): ?>
                    <p class="mb-2">Resultado de la consulta: <strong><?= $esc($scheduleStatusLabel((string) ($apiHistory['effective_schedule']['estado_jornada'] ?? ''))) ?></strong>.</p>
                    <?php $apiHistoryItems = $apiHistory['assignments'] ?? []; ?>
                    <?php if ($apiHistoryItems !== []): ?><div class="table-responsive-lg"><table data-checador-configuration-datatable class="table table-bordered align-middle mb-0">
                        <thead><tr><th scope="col">Día</th><th scope="col">Vigente desde</th><th scope="col">Vigente hasta</th><th scope="col">Plantilla</th><th scope="col">Activa</th></tr></thead>
                        <tbody><?php foreach ($apiHistoryItems as $assignment): ?><tr>
                            <td><?= $esc($assignment['dia_semana'] ?? '') ?></td><td><?= $esc($assignment['valido_desde'] ?? '') ?></td>
                            <td><?= $esc($assignment['valido_hasta'] ?? 'Sin fecha de término') ?></td><td><?= ($assignment['id_plantilla'] ?? null) === null ? 'Descanso' : 'Turno ' . (int) $assignment['id_plantilla'] ?></td>
                            <td><?= !empty($assignment['es_activo']) ? 'Sí' : 'No' ?></td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div><?php else: ?><p class="small text-muted mb-0">La respuesta no incluyó asignaciones visibles; no se infiere descanso ni ausencia.</p><?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </section>

</div>

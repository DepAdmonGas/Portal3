<?php
declare(strict_types=1);

$config = is_array($configuration ?? null) ? $configuration : [];
$permissions = is_array($config['permissions'] ?? null) ? $config['permissions'] : [];
$mapping = is_array($config['mapping'] ?? null) ? $config['mapping'] : [];
$apiTemplates = is_array($config['api_templates'] ?? null) ? $config['api_templates'] : [];
$apiHistory = is_array($config['api_history'] ?? null) ? $config['api_history'] : [];
$legacySchedules = is_array($config['legacy_schedules'] ?? null) ? $config['legacy_schedules'] : [];
$legacyRules = is_array($config['legacy_rules'] ?? null) ? $config['legacy_rules'] : [];
$legacyProfiles = is_array($config['legacy_profiles'] ?? null) ? $config['legacy_profiles'] : [];
$positions = is_array($config['positions'] ?? null) ? $config['positions'] : [];
$deviceAuth = is_array($config['device_auth'] ?? null) ? $config['device_auth'] : [];
$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$labelForStatus = static fn(string $status): string => match ($status) {
    'ready', 'current_catalog', 'legacy_catalog', 'legacy_configured', 'legacy_metadata_only', 'unique_numlista_mapping' => 'Disponible',
    'demo_fixture' => 'Demostración local',
    'demo_fixture_partial' => 'Demostración local · cobertura parcial',
    'partial' => 'Cobertura parcial',
    'possibly_partial' => 'Puede estar incompleto',
    'permission_denied' => 'Sin permiso de lectura',
    'legacy_unconfigured' => 'Sin configuración persistida',
    'not_applicable', 'department_has_no_legacy_station_mapping' => 'No aplica a departamentos',
    'not_read_in_demo' => 'No consultado en modo demo',
    'not_integrated' => 'No integrado',
    'mapping_or_module_scope_unavailable', 'mapping_unavailable', 'mapping_not_unique_or_inactive' => 'Mapeo no confirmado',
    'legacy_station_not_authorized' => 'Estación fuera del alcance de Biométricos',
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
    ? $url('/departamento-operativo/recursos-humanos/checador', $query + ['schedule_week' => '1', 'week_start' => $weekStart])
    : null;
$historyHref = !empty($canReadScheduling) && ($workerId ?? null) !== null && ($date ?? '') !== ''
    ? $url('/departamento-operativo/recursos-humanos/checador', $query + [
        'schedule_history' => '1', 'worker_id' => (int) $workerId, 'date' => (string) $date,
    ])
    : null;
$bioBase = '/departamento-operativo/recursos-humanos/biometricos/configuracion';
$canReadLegacy = !empty($permissions['read']);
$canReadPositions = !empty($permissions['positions_read']);
$mappedStation = ($mapping['physical_station_id'] ?? null) !== null ? (int) $mapping['physical_station_id'] : null;
$canOpenLegacyStationConfig = $canReadLegacy && $mappedStation !== null && !empty($mapping['legacy_scope_authorized']);
$canOpenPositions = $canReadPositions && $canReadLegacy;
$workplaceName = (string) ($selectedWorkplaceName ?? '');
?>

<div id="container" class="mt-4 mb-5">
    <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Configuración de Checador</h1>
            <p class="text-muted mb-0">Consulta de turnos, reglas y catálogos del centro autorizado.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if (($demoMode ?? false) === true): ?><span class="badge rounded-pill text-bg-secondary" title="Los datos mostrados no son operativos">Demo</span><?php endif; ?>
            <a class="btn btn-outline-primary btn-sm" href="<?= $esc($dailyHref) ?>">Volver a asistencia</a>
            <?php if ($weeklyHref !== null): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $esc($weeklyHref) ?>">Programación semanal</a><?php endif; ?>
        </div>
    </header>

    <div class="d-flex flex-wrap gap-3 small text-muted mb-3" aria-live="polite">
        <span><strong class="text-body">Centro autorizado:</strong> <?= $esc($workplaceName !== '' ? $workplaceName : 'Selecciona uno en el selector institucional') ?></span>
        <?php if (($selectedWorkplaceId ?? null) !== null): ?><span><strong class="text-body">Localidad canónica:</strong> <?= (int) $selectedWorkplaceId ?></span><?php endif; ?>
        <?php if (($workerId ?? null) !== null): ?><span><strong class="text-body">Trabajador:</strong> <?= (int) $workerId ?></span><?php endif; ?>
        <?php if (($date ?? '') !== ''): ?><span><strong class="text-body">Fecha local:</strong> <?= $esc($date) ?></span><?php endif; ?>
    </div>

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

    <div id="configuration-navigation" class="list-group list-group-horizontal-md flex-wrap mb-4" aria-label="Apartados de configuración">
        <a class="list-group-item list-group-item-action" href="#schedule-configuration">Turnos y horarios</a>
        <a class="list-group-item list-group-item-action" href="#attendance-rules">Reglas de asistencia</a>
        <a class="list-group-item list-group-item-action" href="#people-positions">Personal y puestos</a>
        <a class="list-group-item list-group-item-action" href="#reader-access">Lectores y accesos</a>
    </div>

    <section class="card mb-4" id="schedule-configuration" aria-labelledby="schedule-configuration-heading">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><h2 class="h5 mb-0" id="schedule-configuration-heading">Turnos y horarios</h2><span class="small text-muted">Catálogos y vigencias disponibles</span></div>
        </div>
        <div class="card-body">
            <p class="small text-muted">Los horarios actuales y los registros anteriores se muestran por separado; no se sustituyen entre sí.</p>
            <?php if (($historyHref ?? null) !== null): ?>
                <p class="small">Contexto para horario efectivo e historial: trabajador <?= (int) $workerId ?> · centro <?= (int) $selectedWorkplaceId ?> · <?= $esc($date) ?>.
                    <a href="<?= $esc($historyHref) ?>">Consultar vigencia histórica</a>
                </p>
            <?php else: ?>
                <p class="small text-muted">Para consultar horario efectivo e historial, indica trabajador y fecha en la consulta diaria. No se busca un horario sin identidad canónica.</p>
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
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-bordered align-middle mb-0">
                            <caption class="visually-hidden">Turnos disponibles y sus bloques horarios</caption>
                            <thead><tr><th scope="col">Turno</th><th scope="col">Versión</th><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">Bloques de horario</th></tr></thead>
                            <tbody><?php foreach (($apiTemplates['items'] ?? []) as $template): ?>
                                <tr>
                                    <th scope="row"><?= $esc($template['nombre'] ?? 'No disponible') ?><div class="small text-muted"><?= $esc($template['clave'] ?? '') ?></div></th>
                                    <td><?= (int) ($template['version'] ?? 0) ?></td>
                                    <td><?= $esc(match ((string) ($template['tipo_turno'] ?? '')) { 'REGULAR' => 'Ordinario', 'SPECIAL' => 'Especial', 'REST' => 'Descanso', default => 'No disponible' }) ?></td>
                                    <td><?= !empty($template['es_activo']) ? 'Activa' : 'Inactiva' ?></td>
                                    <td><?php if (is_array($template['segments'] ?? null) && $template['segments'] !== []): ?>
                                        <ul class="mb-0 ps-3"><?php foreach ($template['segments'] as $segment): ?>
                                            <li><?= $esc($segment['tipo_segmento'] ?? 'Segmento') ?> · <?= $esc($segment['hora_inicio_local'] ?? '—') ?>–<?= $esc($segment['hora_fin_local'] ?? '—') ?><?php if ((int) ($segment['dia_offset_fin'] ?? 0) > 0): ?> (+día)<?php endif; ?></li>
                                        <?php endforeach; ?></ul>
                                    <?php else: ?>Detalle no disponible<?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?></tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (($apiHistory['status'] ?? '') !== 'not_requested'): ?>
                <h3 class="h6 mt-3">Horario efectivo e historial</h3>
                <p class="small text-muted"><span class="badge text-bg-light border"><?= $esc($labelForStatus((string) ($apiHistory['status'] ?? ''))) ?></span></p>
                <?php if (is_array($apiHistory['effective_schedule'] ?? null)): ?>
                    <p class="mb-2">Resultado de la consulta: <strong><?= $esc($scheduleStatusLabel((string) ($apiHistory['effective_schedule']['estado_jornada'] ?? ''))) ?></strong>.</p>
                    <?php $apiHistoryItems = $apiHistory['assignments'] ?? []; ?>
                    <?php if ($apiHistoryItems !== []): ?><div class="table-responsive"><table class="table table-bordered align-middle mb-0">
                        <thead><tr><th scope="col">Día</th><th scope="col">Vigente desde</th><th scope="col">Vigente hasta</th><th scope="col">Plantilla</th><th scope="col">Activa</th></tr></thead>
                        <tbody><?php foreach ($apiHistoryItems as $assignment): ?><tr>
                            <td><?= $esc($assignment['dia_semana'] ?? '') ?></td><td><?= $esc($assignment['valido_desde'] ?? '') ?></td>
                            <td><?= $esc($assignment['valido_hasta'] ?? 'Sin fecha de término') ?></td><td><?= ($assignment['id_plantilla'] ?? null) === null ? 'Descanso' : 'Turno ' . (int) $assignment['id_plantilla'] ?></td>
                            <td><?= !empty($assignment['es_activo']) ? 'Sí' : 'No' ?></td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div><?php else: ?><p class="small text-muted mb-0">La respuesta no incluyó asignaciones visibles; no se infiere descanso ni ausencia.</p><?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <hr>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div><h3 class="h6 mb-1">Registros de horarios anteriores</h3><p class="small text-muted mb-0">Se conservan separados del horario vigente.</p></div>
                <?php if ($canOpenLegacyStationConfig && ($legacySchedules['status'] ?? '') === 'legacy_catalog'): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= $esc($bioBase . '/retardo-horarios-incidencias') ?>">Abrir configuración anterior</a>
                <?php endif; ?>
            </div>
            <?php if (($mapping['status'] ?? '') === 'unique_numlista_mapping'): ?>
                <p class="small text-muted">Centro físico relacionado: <?= (int) $mappedStation ?> · <?= $esc($mapping['name'] ?? '') ?>.</p>
                <?php if ($canOpenLegacyStationConfig): ?><p class="small text-muted">Estos registros conservan su propio centro seleccionado.</p>
                <?php else: ?><p class="small text-muted">No está autorizada la consulta de registros anteriores para este centro.</p><?php endif; ?>
            <?php else: ?>
                <p class="small text-muted">La relación con un centro físico no está confirmada; no se muestran registros anteriores.</p>
            <?php endif; ?>
            <?php if ($canOpenLegacyStationConfig && ($legacySchedules['status'] ?? '') === 'legacy_catalog' && ($legacySchedules['items'] ?? []) !== []): ?>
                <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0">
                    <thead><tr><th scope="col">Nombre</th><th scope="col">Hora de entrada</th><th scope="col">Hora de salida</th></tr></thead>
                    <tbody><?php foreach ($legacySchedules['items'] as $schedule): ?><tr><th scope="row"><?= $esc($schedule['title'] ?? '') ?></th><td><?= $esc($schedule['start'] ?? '') ?></td><td><?= $esc($schedule['end'] ?? '') ?></td></tr><?php endforeach; ?></tbody>
                </table></div>
            <?php elseif (!$canOpenLegacyStationConfig): ?>
                <p class="small text-muted mb-0">No está autorizada la consulta de estos registros para el centro seleccionado.</p>
            <?php elseif (($legacySchedules['status'] ?? '') !== 'legacy_catalog'): ?>
                <p class="small text-muted mb-0">Registros anteriores: <?= $esc($labelForStatus((string) ($legacySchedules['status'] ?? ''))) ?>.</p>
            <?php else: ?><p class="small text-muted mb-0">No hay horarios anteriores listados para esta estación.</p><?php endif; ?>
        </div>
    </section>

    <section class="card mb-4" id="attendance-rules" aria-labelledby="attendance-rules-heading">
        <div class="card-header"><h2 class="h5 mb-0" id="attendance-rules-heading">Reglas de asistencia</h2></div>
        <div class="card-body">
            <p class="small text-muted">Estos valores son referencias; no calculan retardos, faltas ni resultados de nómina.</p>
            <?php if ($canOpenLegacyStationConfig && ($legacyRules['status'] ?? '') === 'legacy_configured' && !empty($legacyRules['configured'])): ?>
                <dl class="row mb-3"><dt class="col-sm-5">Tolerancia registrada</dt><dd class="col-sm-7"><?= (int) ($legacyRules['retardo'] ?? 0) ?> minutos</dd><dt class="col-sm-5">Plazo de aclaración</dt><dd class="col-sm-7"><?= (int) ($legacyRules['incidencia'] ?? 0) ?> días</dd></dl>
                <p class="small text-muted">Fuente: configuración persistida de la estación física <?= $mappedStation !== null ? $mappedStation : 'no mapeada' ?>.</p>
            <?php elseif (!$canOpenLegacyStationConfig): ?>
                <p class="small text-muted">No está autorizada la consulta de reglas anteriores para el centro seleccionado.</p>
            <?php elseif (($legacyRules['status'] ?? '') === 'legacy_unconfigured'): ?>
                <div class="alert alert-secondary py-2 mb-3" role="status">No hay valores guardados para este centro. No se muestran valores predeterminados como si estuvieran configurados.</div>
            <?php else: ?><p class="small text-muted">Registros anteriores: <?= $esc($labelForStatus((string) ($legacyRules['status'] ?? ''))) ?>.</p><?php endif; ?>
                <?php if ($canOpenLegacyStationConfig && in_array(($legacyRules['status'] ?? ''), ['legacy_configured', 'legacy_unconfigured'], true)): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= $esc($bioBase . '/retardo-horarios-incidencias') ?>">Ver configuración anterior</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="card mb-4" id="people-positions" aria-labelledby="people-positions-heading">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><h2 class="h5 mb-0" id="people-positions-heading">Personal y puestos</h2><span class="small text-muted">Catálogo autorizado</span></div>
            <?php if ($canOpenPositions): ?><a class="btn btn-sm btn-outline-primary" href="<?= $esc($bioBase . '/puestos') ?>">Administrar puestos</a><?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (($positions['status'] ?? '') === 'current_catalog'): ?>
                <?php if (($positions['items'] ?? []) === []): ?><p class="small text-muted mb-0">No hay puestos activos visibles.</p>
                <?php else: ?><ul class="list-group list-group-flush"><?php foreach ($positions['items'] as $position): ?><li class="list-group-item px-0"><?= $esc($position['name'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php elseif (($positions['status'] ?? '') === 'not_read_in_demo'): ?><p class="small text-muted mb-0">El catálogo institucional no se consulta durante la demostración para evitar mezclar datos sintéticos y base real.</p>
            <?php else: ?><p class="small text-muted mb-0">Catálogo de puestos: <?= $esc($labelForStatus((string) ($positions['status'] ?? ''))) ?>.</p><?php endif; ?>
            <?php if (!$canReadPositions): ?><p class="small text-muted mt-2 mb-0">El catálogo de puestos no está disponible para este perfil.</p><?php endif; ?>
        </div>
    </section>

    <section class="card" id="reader-access" aria-labelledby="reader-access-heading">
        <div class="card-header"><h2 class="h5 mb-0" id="reader-access-heading">Lectores y accesos</h2></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <h3 class="h6">Perfiles de acceso anteriores</h3>
                    <p class="small text-muted">Se muestran únicamente datos de perfil permitidos. Las contraseñas nunca se muestran.</p>
                <?php if ($canOpenLegacyStationConfig && ($legacyProfiles['status'] ?? '') === 'legacy_metadata_only'): ?><p class="mb-2">Perfiles activos visibles: <?= (int) ($legacyProfiles['active_count'] ?? 0) ?></p>
                        <?php elseif (!$canOpenLegacyStationConfig): ?><p class="small mb-2">No está autorizada la consulta de perfiles para el centro seleccionado.</p>
                        <?php else: ?><p class="small mb-2">Estado: <?= $esc($labelForStatus((string) ($legacyProfiles['status'] ?? ''))) ?>.</p><?php endif; ?>
                        <?php if ($canOpenLegacyStationConfig): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $esc($bioBase . '/perfil') ?>">Abrir perfiles anteriores</a><?php endif; ?>
                        <?php if (!empty($permissions['legacy_create']) || !empty($permissions['legacy_edit']) || !empty($permissions['legacy_delete'])): ?><span class="small text-muted d-block mt-2">Las acciones conservan permisos independientes y validación del centro en servidor.</span><?php endif; ?>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="border rounded p-3 h-100">
                        <h3 class="h6">Acceso de lectores</h3>
                        <p class="mb-2"><span class="badge text-bg-light border"><?= $esc($labelForStatus((string) ($deviceAuth['status'] ?? 'not_integrated'))) ?></span></p>
                        <p class="small text-muted mb-0">La información de acceso del lector no está disponible en esta pantalla.</p>
                    </div>
                </div>
                <?php if (($temporaryAccessAvailable ?? false) === true): ?><div class="col-12">
                    <div class="border rounded p-3">
                        <h3 class="h6">Acceso temporal autorizado</h3>
                        <p class="small text-muted mb-2">El acceso temporal aparece en la consulta diaria sólo cuando el perfil tiene autorización.</p>
                        <a class="btn btn-sm btn-outline-primary" href="<?= $esc($dailyHref) ?>#temporary-access-heading">Consultar sección autorizada</a>
                    </div>
                </div><?php endif; ?>
            </div>
        </div>
    </section>
</div>

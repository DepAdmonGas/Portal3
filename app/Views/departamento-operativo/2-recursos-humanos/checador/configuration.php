<?php

declare(strict_types=1);

$config = is_array($configuration ?? null) ? $configuration : [];
$permissions = is_array($config['permissions'] ?? null) ? $config['permissions'] : [];
$readerProfiles = is_array($config['reader_profiles'] ?? null) ? $config['reader_profiles'] : [];
$positions = is_array($config['positions'] ?? null) ? $config['positions'] : [];
$attendancePolicyState = is_array($config['attendance_policy'] ?? null) ? $config['attendance_policy'] : [];
$attendancePolicy = is_array($attendancePolicyState['data'] ?? null) ? $attendancePolicyState['data'] : [];
$activeAttendancePolicy = is_array($attendancePolicy['policy'] ?? null) ? $attendancePolicy['policy'] : [];
$latestAttendancePolicy = is_array($attendancePolicy['latest_scheduled_policy'] ?? null) ? $attendancePolicy['latest_scheduled_policy'] : [];
$attendancePolicyFormDefaults = is_array($attendancePolicyDefaults ?? null) ? $attendancePolicyDefaults : [];
$attendancePolicyFeedback = is_array($attendancePolicyFeedback ?? null) ? $attendancePolicyFeedback : null;
$attendancePolicyFormValues = array_merge($attendancePolicyFormDefaults, is_array($attendancePolicyFeedback['form_values'] ?? null) ? $attendancePolicyFeedback['form_values'] : []);
$attendancePolicyHistory = [];
$policyHistorySource = is_array($attendancePolicy['policy_history'] ?? null)
    ? $attendancePolicy['policy_history'] : [$activeAttendancePolicy, $latestAttendancePolicy];
foreach ($policyHistorySource as $policyItem) {
    $policyDate = (string) ($policyItem['effective_from_local_date'] ?? '');
    if ($policyItem !== [] && $policyDate !== '' && !isset($attendancePolicyHistory[$policyDate])) {
        $actorId = (int) ($policyItem['actor_portal_user_id'] ?? 0);
        $policyItem['actor_name'] = (string) (($attendancePolicyActorNames[$actorId] ?? '') ?: 'No disponible');
        $attendancePolicyHistory[$policyDate] = $policyItem;
    }
}
$nextAttendancePolicy = [];
$currentPolicyDate = (string) ($attendancePolicy['current_local_date'] ?? '');
foreach ($attendancePolicyHistory as $policyItem) {
    $effectiveDate = (string) ($policyItem['effective_from_local_date'] ?? '');
    if (
        $effectiveDate !== '' && $effectiveDate > $currentPolicyDate
        && ($nextAttendancePolicy === [] || $effectiveDate < (string) ($nextAttendancePolicy['effective_from_local_date'] ?? ''))
    ) {
        $nextAttendancePolicy = $policyItem;
    }
}
$policyJson = static fn(array $policy): string => htmlspecialchars(
    json_encode($policy, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES,
    'UTF-8'
);
$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$labelForStatus = static fn(string $status): string => match ($status) {
    'ready', 'current_catalog', 'legacy_catalog', 'legacy_configured', 'legacy_metadata_only', 'unique_numlista_mapping' => 'Disponible',
    'demo_fixture' => 'Demostración local',
    'demo_fixture_partial' => 'Demostración local · cobertura parcial',
    'partial' => 'Cobertura parcial',
    'possibly_partial' => 'Puede estar incompleto',
    'permission_denied' => 'Sin permiso de lectura',
    'legacy_unconfigured' => 'Sin configuración persistida',
    'VERSIONED' => 'Versión vigente',
    'LEGACY_UNVERSIONED' => 'Referencia legacy · sin versión V2',
    'station_scoped' => 'Disponible en la localidad autorizada',
    'role_not_mapped' => 'Perfil sin identidad institucional mapeada',
    'policy_position_not_mapped' => 'Perfil sin identidad institucional mapeada',
    'not_applicable', 'department_has_no_legacy_station_mapping' => 'No aplica a departamentos',
    'not_read_in_demo' => 'No consultado en modo demo',
    'mapping_or_module_scope_unavailable', 'mapping_unavailable', 'mapping_not_unique_or_inactive' => 'Mapeo no confirmado',
    'legacy_station_not_authorized' => 'Estación fuera del alcance de Biométricos',
    'unavailable' => 'No disponible',
    default => $status === '' ? 'No consultado' : 'Pendiente de consulta',
};
$query = [];
$url = static function (string $path, array $params = []): string {
    $filtered = array_filter($params, static fn(mixed $value): bool => $value !== null && $value !== '');
    return $path . ($filtered === [] ? '' : '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986));
};
$dailyHref = $url('/departamento-operativo/recursos-humanos/checador/asistencia', $query + [
    'date' => ($date ?? '') !== '' ? (string) $date : null,
    'worker_id' => ($workerId ?? null) !== null ? (int) $workerId : null,
]);
$scheduleHistoryHref = !empty($canReadScheduling)
    ? '/departamento-operativo/recursos-humanos/checador/horarios-turnos'
    : null;
$canReadPositions = !empty($canReadPositionCatalog) && !empty($permissions['positions_read']);
$positionItemsJson = htmlspecialchars(
    json_encode(
        ($positions['status'] ?? '') === 'current_catalog' && $canReadPositions
            ? ($positions['items'] ?? [])
            : [],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '[]',
    ENT_QUOTES,
    'UTF-8'
);
$readerProfileItemsJson = htmlspecialchars(
    json_encode(
        in_array(($readerProfiles['status'] ?? ''), ['station_scoped', 'shared_station_reference'], true) && !empty($canReadReaderProfiles)
            ? array_map(static fn(array $item): array => [
                'id' => (int) ($item['id'] ?? 0),
                'usuario' => (string) ($item['usuario'] ?? ''),
                'shared' => !empty($item['shared']),
                'shared_from' => (string) ($item['shared_from'] ?? ''),
            ], $readerProfiles['items'] ?? [])
            : [],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?: '[]',
    ENT_QUOTES,
    'UTF-8'
);
?>

<div id="container" class="mt-4 mb-5">
    <?php if (($demoMode ?? false) === true): ?>
        <p class="mb-3"><span class="badge rounded-pill text-bg-secondary" title="Los datos mostrados no son operativos">Demo</span></p>
    <?php endif; ?>

    <?php if (($selectedWorkplaceId ?? null) === null): ?>
        <div class="alert alert-info" role="status">Selecciona un centro autorizado en el selector superior para consultar su configuración.</div>
    <?php elseif ($config === []): ?>
        <div class="alert alert-warning" role="status">La configuración no está disponible. No se presentan valores como confirmados.</div>
    <?php endif; ?>

    <?php if (($demoMode ?? false) === true): ?>
        <div class="alert alert-info py-2" role="status">Demostración local. No consulta datos operativos ni el servicio externo.</div>
    <?php endif; ?>
    <?php if (is_array($m11ConfigurationFeedback ?? null)): ?>
        <div class="alert alert-<?= ($m11ConfigurationFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status"><?= $esc($m11ConfigurationFeedback['message'] ?? 'No se confirmó el cambio.') ?></div>
    <?php endif; ?>

    <?php if ($scheduleHistoryHref !== null): ?>
        <p class="mb-3"><a class="btn btn-sm btn-outline-primary" href="<?= $esc($scheduleHistoryHref) ?>"><i class="ti ti-clock-hour-4 me-1" aria-hidden="true"></i>Horarios y turnos</a></p>
    <?php endif; ?>

    <section class="card mb-4" id="attendance-policy-v2" aria-labelledby="attendance-policy-v2-heading"
        x-data="attendancePolicyForm()"
        x-init="initialize($el)"
        @attendance-policy-confirmed.window="applyReadback($event.detail)"
        @attendance-policy-error.window="error = $event.detail; success = ''"
        data-current-policy="<?= $policyJson(($attendancePolicyState['status'] ?? '') === 'VERSIONED' ? $activeAttendancePolicy : []) ?>"
        data-next-policy="<?= $policyJson($nextAttendancePolicy) ?>"
        data-current-date="<?= $esc($attendancePolicy['current_local_date'] ?? '') ?>"
        data-history-complete="<?= !empty($attendancePolicy['policy_history_complete']) ? 'true' : 'false' ?>"
        data-history="<?= $policyJson(array_values($attendancePolicyHistory)) ?>"
        data-actor-names="<?= $policyJson($attendancePolicyActorNames ?? []) ?>">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-clock-hour-4 fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="attendance-policy-v2-heading">Configuración de asistencia</h2>
                <span class="small text-white-50">Retardos e incidencias</span>
            </div>
        </div>
        <div class="card-body">
            <div x-show="success" x-cloak class="alert alert-success py-2" role="status" x-text="success"></div>
            <div x-show="error" x-cloak class="alert alert-danger py-2" role="alert" x-text="error"></div>
            <?php if (is_array($attendancePolicyFeedback ?? null)): ?>
                <div class="alert alert-<?= ($attendancePolicyFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?> py-2" role="status"><?= ($attendancePolicyFeedback['type'] ?? '') === 'success' ? 'La configuración se guardó y quedó confirmada.' : $esc($attendancePolicyFeedback['message'] ?? 'No fue posible confirmar la configuración.') ?></div>
            <?php endif; ?>
            <?php if (($selectedWorkplaceId ?? null) === null): ?>
                <p class="small text-muted mb-0">Selecciona una localidad autorizada para consultar la configuración.</p>
            <?php elseif (empty($canReadAttendancePolicy)): ?>
                <p class="small text-muted mb-0">No tienes permiso para consultar la configuración de esta localidad.</p>
            <?php else: ?>
                <?php if (($attendancePolicyState['status'] ?? '') === 'unavailable'): ?>
                    <div class="alert alert-warning py-2" role="status">No fue posible consultar la configuración. Intenta de nuevo más tarde.</div>
                <?php endif; ?>

                <div class="row g-3">
                    <template x-if="current">
                        <div class="col-12 col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                    <div><span class="badge text-bg-primary">Configuración vigente</span>
                                        <p class="small text-muted mt-2 mb-0">Vigente desde <span x-text="current?.effective_from_local_date || '—'"></span></p>
                                    </div>
                                    <i class="ti ti-circle-check text-primary fs-5" aria-hidden="true"></i>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="bg-primary-subtle rounded p-3 h-100"><span class="small text-primary">Tolerancia</span>
                                            <div class="fs-5 fw-semibold text-primary"><span x-text="current?.retardo_tolerance_minutes ?? '—'"></span> <span class="fs-6 fw-normal">min</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-primary-subtle rounded p-3 h-100"><span class="small text-primary">Plazo de incidencias</span>
                                            <div class="fs-5 fw-semibold text-primary"><span x-text="current?.incidence_deadline_days ?? '—'"></span> <span class="fs-6 fw-normal">días</span></div>
                                        </div>
                                    </div>
                                </div>
                                <p class="small text-muted mt-2 mb-0" x-show="current?.timezone_id">Zona horaria: <span x-text="current?.timezone_id"></span></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="next">
                        <div class="col-12 col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                    <div><span class="badge text-bg-info">Próxima configuración</span>
                                        <p class="small text-muted mt-2 mb-0">Inicia el <span x-text="next?.effective_from_local_date || '—'"></span></p>
                                    </div>
                                    <i class="ti ti-calendar-event text-primary fs-5" aria-hidden="true"></i>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="bg-primary-subtle rounded p-3 h-100"><span class="small text-primary">Tolerancia</span>
                                            <div class="fs-5 fw-semibold text-primary"><span x-text="next?.retardo_tolerance_minutes ?? '—'"></span> <span class="fs-6 fw-normal">min</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="bg-primary-subtle rounded p-3 h-100"><span class="small text-primary">Plazo de incidencias</span>
                                            <div class="fs-5 fw-semibold text-primary"><span x-text="next?.incidence_deadline_days ?? '—'"></span> <span class="fs-6 fw-normal">días</span></div>
                                        </div>
                                    </div>
                                </div>
                                <p class="small text-muted mt-2 mb-0" x-show="next?.timezone_id">Zona horaria: <span x-text="next?.timezone_id"></span></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="!current && !next">
                        <div class="col-12">
                            <p class="alert alert-info py-2 mb-0" role="status">Aún no hay una configuración registrada para esta localidad.</p>
                        </div>
                    </template>
                </div>

                <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
                    <?php if (!empty($canEditAttendancePolicy) && !empty($attendancePolicyPostEnabled) && ($demoMode ?? false) !== true): ?>
                        <button type="button" class="btn btn-primary" id="attendance-policy-edit-button"><i class="ti ti-edit me-1" aria-hidden="true"></i>Editar configuración</button>
                    <?php elseif (!empty($canEditAttendancePolicy)): ?>
                        <span class="small text-muted align-self-center">La edición no está disponible en este entorno.</span>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-primary" @click="openHistoryModal()"><i class="ti ti-history me-1" aria-hidden="true"></i>Ver historial</button>
                </div>
            <?php endif; ?>

            <?php if (!empty($canEditAttendancePolicy) && !empty($attendancePolicyPostEnabled) && ($demoMode ?? false) !== true): ?>
                <div class="modal fade" id="attendance-policy-edit-modal" tabindex="-1" aria-labelledby="attendance-policy-edit-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-primary">
                                <h4 class="modal-title text-white" id="attendance-policy-edit-title"><i class="ti ti-edit me-1" aria-hidden="true"></i>Editar configuración</h4>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/politicas-asistencia"
                                aria-label="Editar configuración de asistencia" x-data="attendancePolicyEditor()" @submit.prevent="submit($event)" @attendance-policy-error.window="error = $event.detail" @attendance-policy-modal-reset.window="resetForm()">
                                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                <input type="hidden" name="idempotency_key" value="<?= $esc(($attendancePolicyFeedback['retry_idempotency_key'] ?? '') ?: ($attendancePolicyIdempotencyKey ?? '')) ?>">
                                <div class="modal-body">
                                    <div class="alert alert-danger py-2" x-show="error" x-cloak role="alert" x-text="error"></div>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label" for="attendance-policy-effective-from">Fecha de vigencia</label>
                                            <input class="form-control" type="date" id="attendance-policy-effective-from" name="effective_from_local_date" min="<?= $esc($attendancePolicyEffectiveFrom ?? '') ?>" value="<?= $esc($attendancePolicyFormValues['effective_from_local_date'] ?? ($attendancePolicyEffectiveFrom ?? '')) ?>" required>
                                            <div class="invalid-feedback" x-show="fieldErrors.effective_from_local_date" x-text="fieldErrors.effective_from_local_date" role="status"></div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="attendance-policy-tolerance">Tolerancia (minutos)</label>
                                            <input class="form-control" :class="fieldErrors.retardo_tolerance_minutes ? 'is-invalid' : ''" :aria-invalid="fieldErrors.retardo_tolerance_minutes ? 'true' : 'false'" type="number" id="attendance-policy-tolerance" name="retardo_tolerance_minutes" min="0" max="120" step="1" value="<?= $esc($attendancePolicyFormValues['retardo_tolerance_minutes'] ?? '') ?>" required>
                                            <div class="invalid-feedback" x-show="fieldErrors.retardo_tolerance_minutes" x-text="fieldErrors.retardo_tolerance_minutes" role="status"></div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label" for="attendance-policy-deadline">Plazo de incidencias (días naturales)</label>
                                            <input class="form-control" :class="fieldErrors.incidence_deadline_days ? 'is-invalid' : ''" :aria-invalid="fieldErrors.incidence_deadline_days ? 'true' : 'false'" type="number" id="attendance-policy-deadline" name="incidence_deadline_days" min="0" max="30" step="1" value="<?= $esc($attendancePolicyFormValues['incidence_deadline_days'] ?? '') ?>" required>
                                            <div class="invalid-feedback" x-show="fieldErrors.incidence_deadline_days" x-text="fieldErrors.incidence_deadline_days" role="status"></div>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="attendance-policy-confirm" name="confirm_policy" value="1" required>
                                                <label class="form-check-label" for="attendance-policy-confirm">Confirmo estos valores para la localidad seleccionada.</label>
                                                <div class="invalid-feedback d-block" x-show="fieldErrors.confirm_policy" x-text="fieldErrors.confirm_policy" role="status"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <span class="small text-muted me-auto" x-show="loading" role="status">Guardando cambios…</span>
                                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" :disabled="loading"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button>
                                    <button type="submit" class="btn btn-primary" :disabled="loading"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><span x-text="loading ? 'Guardando…' : 'Guardar cambios'"></span></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($canReadAttendancePolicy)): ?>
                <div class="modal fade" id="attendance-policy-history-modal" tabindex="-1" aria-labelledby="attendance-policy-history-title" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-primary">
                                <h4 class="modal-title text-white" id="attendance-policy-history-title"><i class="ti ti-history me-1" aria-hidden="true"></i>Historial de cambios</h4>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <?php if (array_key_exists('policy_history_complete', $attendancePolicy) && empty($attendancePolicy['policy_history_complete'])): ?>
                                    <div class="alert alert-warning py-2" role="status">No fue posible confirmar todos los cambios anteriores. Actualiza más tarde para consultar el historial completo.</div>
                                <?php endif; ?>
                                <div class="table-responsive">
                                    <table id="attendance-policy-history-table" class="table table-sm table-striped table-bordered align-middle mb-0 w-100">
                                        <caption class="visually-hidden">Historial de configuraciones de asistencia</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">Versión</th>
                                                <th scope="col">Vigencia</th>
                                                <th scope="col">Tolerancia</th>
                                                <th scope="col">Plazo</th>
                                                <th scope="col">Usuario</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-primary" data-bs-dismiss="modal"><i class="ti ti-check me-1" aria-hidden="true"></i>Cerrar</button></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($canReadPositions): ?>
        <section class="card mb-4" id="people-positions" aria-labelledby="people-positions-heading"
            x-data="m11PositionCatalog()" data-position-items="<?= $positionItemsJson ?>"
            data-save-url="/departamento-operativo/recursos-humanos/checador/configuracion/puestos"
            data-workplace-id="<?= $selectedWorkplaceId !== null ? (int) $selectedWorkplaceId : '' ?>"
            data-csrf-token="<?= $esc(\App\Core\CsrfToken::token()) ?>">
            <div class="card-header bg-primary text-white d-flex align-items-center gap-1 gap-md-3">
                <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-users fs-5"></i></span>
                <div>
                    <h2 class="h5 mb-0 text-white" id="people-positions-heading">Personal y puestos</h2>
                    <span class="small text-white-50">Catálogo institucional</span>
                </div>
                <?php if ($canReadPositions && !empty($canEditPositionCatalog) && !empty($m11ConfigurationPostEnabled) && ($demoMode ?? false) !== true): ?>
                    <button type="button" class="btn btn-light btn-sm text-primary ms-auto" @click="openCreate()">
                        <i class="ti ti-plus me-1" aria-hidden="true"></i>Nuevo
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($canReadPositions && ($positions['status'] ?? '') === 'current_catalog'): ?>
                    <?php if (empty($canEditPositionCatalog)): ?><p class="small text-muted mb-3">Catálogo de puestos disponible en modo de consulta.</p><?php endif; ?>
                    <?php if (!empty($canEditPositionCatalog) && (empty($m11ConfigurationPostEnabled) || ($demoMode ?? false) === true)): ?><p class="small text-muted mb-3">La edición no está disponible en este entorno.</p><?php endif; ?>
                    <div class="datatables">
                        <div class="table-responsive">
                            <table id="m11-position-table" class="table table-sm table-striped table-bordered align-middle mb-0 w-100">
                                <thead>
                                    <tr>
                                        <th scope="col">Puesto</th>
                                        <th scope="col" class="text-center align-middle"><span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                <?php elseif (!$canReadPositions): ?>
                    <p class="small text-muted mb-0">El catálogo institucional está disponible únicamente para Dirección de Operaciones.</p>
                <?php else: ?>
                    <p class="small text-muted mb-0">Catálogo de puestos: <?= $esc($labelForStatus((string) ($positions['status'] ?? ''))) ?>.</p>
                <?php endif; ?>
                <?php if ($canReadPositions && !empty($canEditPositionCatalog) && !empty($m11ConfigurationPostEnabled) && ($demoMode ?? false) !== true): ?>
                    <div class="alert alert-danger py-2 mt-3 mb-0" role="alert" x-show="error" x-cloak x-text="error"></div>
                    <div class="alert alert-success py-2 mt-3 mb-0" role="status" x-show="success" x-cloak x-text="success"></div>

                    <div class="modal fade" id="m11-position-modal" tabindex="-1" aria-labelledby="m11-position-modal-title" aria-hidden="true"
                        data-bs-backdrop="static" data-bs-keyboard="false" @m11-position-edit.window="openEdit($event.detail)">
                        <div class="modal-dialog modal-md modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-primary">
                                    <h4 class="modal-title text-white" id="m11-position-modal-title" x-text="mode === 'create' ? 'Nuevo puesto' : 'Editar puesto'"></h4>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" :disabled="loading"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label" for="m11-position-name">Nombre</label>
                                    <input class="form-control" id="m11-position-name" type="text" maxlength="100" required
                                        x-model="name" @input="fieldError = ''; error = ''" :class="fieldError ? 'is-invalid' : ''"
                                        :disabled="loading" autocomplete="off">
                                    <div class="invalid-feedback" x-show="fieldError" x-text="fieldError"></div>
                                    <div class="text-danger small mt-2" role="alert" x-show="error" x-text="error"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" :disabled="loading">
                                        <i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar
                                    </button>
                                    <button type="button" class="btn btn-success" @click="save()" :disabled="loading">
                                        <i class="ti ti-check me-1" aria-hidden="true"></i><span x-text="loading ? 'Guardando…' : 'Guardar'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card mb-4" id="reader-access" aria-labelledby="reader-access-heading"
        x-data="m11ReaderProfile()"
        data-reader-profiles="<?= $readerProfileItemsJson ?>"
        data-save-url="/departamento-operativo/recursos-humanos/checador/configuracion/perfiles-lector/actualizar"
        data-workplace-id="<?= (int) ($selectedWorkplaceId ?? 0) ?>"
        data-csrf-token="<?= $esc(\App\Core\CsrfToken::token()) ?>">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-device-desktop fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="reader-access-heading">Perfil del lector</h2>
                <span class="small text-white-50">Usuario de la estación seleccionada</span>
            </div>
        </div>
        <div class="card-body">
            <div class="alert alert-danger py-2 mb-3" role="alert" x-show="error" x-cloak x-text="error"></div>
            <div class="alert alert-success py-2 mb-3" role="status" x-show="success" x-cloak x-text="success"></div>
            <?php if (!empty($canReadReaderProfiles) && in_array(($readerProfiles['status'] ?? ''), ['station_scoped', 'shared_station_reference'], true)): ?>
                <?php if (!empty($readerProfiles['partial'])): ?><p class="small text-warning">La lista puede estar incompleta.</p><?php endif; ?>
                <?php if (($readerProfiles['items'] ?? []) === []): ?>
                    <p class="small mb-0"><?= ($readerProfiles['status'] ?? '') === 'shared_station_reference' ? 'Lector compartido no configurado.' : 'No hay perfiles activos en esta localidad.' ?></p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2" aria-label="Usuarios activos del lector">
                        <template x-for="profile in profiles" :key="profile.id">
                            <div class="bg-white border rounded p-3 d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                                <div>
                                    <span class="small text-muted d-block" x-text="profile.shared ? ('Lector compartido (' + (profile.shared_from || 'Palo Solo') + ')') : 'Usuario activo'"></span>
                                    <span class="fw-semibold text-body" x-text="profile.usuario"></span>
                                </div>
                                <?php if (!empty($canEditReaderProfiles) && !empty($m11ConfigurationPostEnabled) && ($demoMode ?? false) !== true): ?>
                                    <button type="button" class="btn btn-primary btn-sm align-self-start align-self-sm-center"
                                        @click="openEdit($event.currentTarget)" :data-profile-id="profile.id" :data-username="profile.usuario">
                                        <i class="ti ti-edit me-1" aria-hidden="true"></i>Editar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </template>
                    </div>
                <?php endif; ?>
            <?php elseif (empty($canReadReaderProfiles)): ?>
                <p class="small mb-0">No tienes acceso a los perfiles del lector de esta localidad.</p>
            <?php else: ?>
                <p class="small mb-0">Estado: <?= $esc($labelForStatus((string) ($readerProfiles['status'] ?? 'unavailable'))) ?>.</p>
            <?php endif; ?>

            <?php if (!empty($canEditReaderProfiles) && !empty($m11ConfigurationPostEnabled) && ($demoMode ?? false) !== true): ?>
                <div class="modal fade" id="m11-reader-profile-modal" tabindex="-1" aria-labelledby="m11-reader-profile-title" aria-hidden="true">
                    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-primary">
                                <h3 class="modal-title h5 text-white" id="m11-reader-profile-title"><i class="ti ti-device-desktop me-1" aria-hidden="true"></i>Editar perfil del lector</h3>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar" :disabled="loading"></button>
                            </div>
                            <form method="post" action="/departamento-operativo/recursos-humanos/checador/configuracion/perfiles-lector/actualizar"
                                aria-label="Editar perfil del lector" @submit.prevent="save($event)">
                                <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                <input type="hidden" name="profile_id" x-model="profileId">
                                <div class="modal-body">
                                    <div class="alert alert-danger py-2" role="alert" x-show="error" x-cloak x-text="error"></div>
                                    <label class="form-label" for="m11-reader-profile-username">Usuario</label>
                                    <input class="form-control" id="m11-reader-profile-username" name="username" type="text" maxlength="120" required x-model="username" @input="clearMessages()" :disabled="loading" autocomplete="off">
                                    <label class="form-label mt-3" for="m11-reader-profile-password">Nueva contraseña (opcional)</label>
                                    <input class="form-control" id="m11-reader-profile-password" name="new_password" type="password" maxlength="512" autocomplete="new-password" x-model="password" @input="clearMessages()" :disabled="loading">
                                    <div class="form-text">Deja vacío para conservar la contraseña actual.</div>
                                    <label class="form-label mt-3" for="m11-reader-profile-password-confirm">Confirmar nueva contraseña</label>
                                    <input class="form-control" id="m11-reader-profile-password-confirm" name="new_password_confirm" type="password" maxlength="512" autocomplete="new-password" x-model="passwordConfirm" @input="clearMessages()" :disabled="loading">
                                    <div class="text-danger small mt-2" role="alert" x-show="fieldError" x-text="fieldError"></div>
                                </div>
                                <div class="modal-footer">
                                    <span class="small text-muted me-auto" x-show="loading" role="status">Guardando cambios…</span>
                                    <button type="button" class="btn bg-danger-subtle text-danger" data-bs-dismiss="modal" :disabled="loading"><i class="ti ti-x me-1" aria-hidden="true"></i>Cancelar</button>
                                    <button type="submit" class="btn btn-primary" :disabled="loading"><i class="ti ti-device-floppy me-1" aria-hidden="true"></i><span x-text="loading ? 'Guardando…' : 'Guardar cambios'"></span></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (($temporaryAccessAvailable ?? false) === true): ?>
                <div class="border rounded p-3 mt-3">
                    <h3 class="h6">Acceso temporal autorizado</h3>
                    <p class="small text-muted mb-2">El acceso temporal aparece en la consulta diaria sólo cuando el perfil tiene autorización.</p>
                    <a class="btn btn-sm btn-outline-primary" href="<?= $esc($dailyHref) ?>#temporary-access-heading">Consultar sección autorizada</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (($canReadAdministrativeDevices ?? false) === true): ?>
    <section class="card mb-4" id="m11-devices" aria-labelledby="m11-devices-heading"
        data-devices-url="<?= $esc($administrativeDevicesDataUrl ?? '') ?>">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-device-desktop fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="m11-devices-heading">Administración de dispositivos</h2>
                <span class="small text-white-50">Inventario autorizado · solo consulta</span>
            </div>
        </div>
        <div class="card-body">
            <p class="small text-muted" id="m11-devices-status" role="status" aria-live="polite">Consultando inventario autorizado…</p>
            <div class="datatables">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered align-middle mb-0 w-100" id="m11-devices-table">
                        <thead>
                            <tr>
                                <th scope="col">Identificador</th>
                                <th scope="col">Dispositivo</th>
                                <th scope="col">Estación física</th>
                                <th scope="col">Localidad canónica</th>
                                <th scope="col">Asignación vigente</th>
                                <th scope="col">Estado registrado</th>
                                <th scope="col">Última recepción</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="card mb-4" id="m11-station-readers" aria-labelledby="m11-station-readers-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-map-pin fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="m11-station-readers-heading">Estaciones y lectores asignados</h2>
                <span class="small text-white-50">Relación institucional verificada</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (($selectedWorkplaceId ?? null) === null): ?>
                <p class="mb-0">Selecciona una localidad autorizada para consultar su relación institucional.</p>
            <?php elseif (in_array(($physicalStationMapping['status'] ?? ''), ['unique_numlista_mapping', 'department_shared_station'], true)): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <tbody>
                            <tr>
                                <th scope="row">Localidad canónica</th>
                                <td><?= $esc($selectedWorkplaceName) ?> · <?= (int) $selectedWorkplaceId ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Estación física</th>
                                <td><?= $esc((string) ($physicalStationMapping['name'] ?? 'Estación verificada')) ?> · <?= (int) ($physicalStationMapping['physical_station_id'] ?? 0) ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Referencia institucional</th>
                                <td><?= (int) ($physicalStationMapping['numlista'] ?? 0) ?><?= !empty($physicalStationMapping['department_numlista']) ? ' · Departamento ' . (int) $physicalStationMapping['department_numlista'] : '' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-3 mb-0"><?= ($physicalStationMapping['status'] ?? '') === 'department_shared_station' ? 'Departamento operativo con lector físico compartido en estación anfitriona.' : 'La relación con grupos institucionales se mostrará cuando exista una fuente institucional comprobable.' ?></p>
            <?php else: ?>
                <p class="mb-0">No se pudo confirmar de forma única la relación entre la localidad seleccionada y una estación física.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card mb-4" id="m11-effective-permissions" aria-labelledby="m11-effective-permissions-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-lock-check fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="m11-effective-permissions-heading">Permisos efectivos</h2>
                <span class="small text-white-50">Usuario autenticado · alcance de la localidad seleccionada</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (($effectivePermissionRead['status'] ?? '') === 'ready'): ?>
                <p>Permisos de <strong><?= $esc((string) ($effectivePermissionRead['user']['name'] ?? 'Usuario autenticado')) ?></strong> en <strong><?= $esc((string) ($effectivePermissionRead['scope']['workplace_name'] ?? $selectedWorkplaceName)) ?></strong>.</p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Módulo</th>
                                <th scope="col">Acción</th>
                                <th scope="col">Alcance</th>
                                <th scope="col">Permiso efectivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($effectivePermissionRead['items'] ?? [] as $permission): ?>
                                <tr>
                                    <td><?= $esc((string) ($permission['module'] ?? '')) ?></td>
                                    <td><?= $esc((string) ($permission['action'] ?? '')) ?></td>
                                    <td><?= $esc((string) ($permission['scope'] ?? '')) ?></td>
                                    <td><span class="badge <?= !empty($permission['allowed']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= !empty($permission['allowed']) ? 'Permitido' : 'No permitido' ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php elseif (($effectivePermissionRead['status'] ?? '') === 'permission_denied'): ?>
                <p class="mb-0">No tienes autorización para consultar este resumen.</p>
            <?php else: ?>
                <p class="mb-0">No fue posible verificar los permisos efectivos para la localidad seleccionada.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="card mb-4" id="m11-change-history" aria-labelledby="m11-change-history-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-3">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-history fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="m11-change-history-heading">Historial de cambios</h2>
                <span class="small text-white-50">Registros disponibles para el alcance autorizado</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (($m11AuditRead['status'] ?? '') === 'ready'): ?>
                <?php if (($m11HistoryEvents ?? []) === []): ?>
                    <p class="mb-0">No hay cambios auditados disponibles para esta localidad.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-bordered align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Fecha (UTC)</th>
                                    <th scope="col">Actor</th>
                                    <th scope="col">Localidad</th>
                                    <th scope="col">Operación</th>
                                    <th scope="col">Referencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($m11HistoryEvents as $event): ?>
                                    <tr>
                                        <td><?= $esc((string) ($event['timestamp'] ?? '')) ?></td>
                                        <td><?= $esc((string) ($event['actor_name'] ?? '') !== '' ? (string) $event['actor_name'] : 'Usuario #' . (int) ($event['actor_id'] ?? 0)) ?></td>
                                        <td><?= $esc((string) ($event['workplace_name'] ?? '')) ?></td>
                                        <td><?= $esc(trim((string) ($event['action'] ?? '') . ' · ' . (string) ($event['entity'] ?? ''), ' ·')) ?><?php if (($event['detail'] ?? '') !== ''): ?><span class="small text-muted d-block"><?= $esc((string) $event['detail']) ?></span><?php endif; ?></td>
                                        <td><?= $event['reference'] !== '' ? '#' . $esc((string) $event['reference']) : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <p class="small text-muted mt-3 mb-0">Este historial contiene sólo cambios registrados; los cambios de dispositivos y permisos no se muestran porque no tienen eventos de auditoría disponibles aquí.</p>
            <?php elseif (($m11AuditRead['status'] ?? '') === 'permission_denied'): ?>
                <p class="mb-0">No tienes autorización para consultar este historial.</p>
            <?php else: ?>
                <p class="mb-0">El historial de cambios no está disponible para lectura.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

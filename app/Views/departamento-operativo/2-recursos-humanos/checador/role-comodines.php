<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$coverageRows = is_array($coverageRows ?? null) ? $coverageRows : [];
$roles = is_array($roles ?? null) ? $roles : [];
$coverageReadState = (string) ($coverageReadState ?? 'unavailable');
$coverageReadMessage = is_string($coverageReadMessage ?? null) ? $coverageReadMessage : '';
$coverageWriterEnabled = !empty($coverageWriterEnabled);
$canRequestCoverage = !empty($canRequestCoverage);
$canReviewCoverage = !empty($canReviewCoverage);
$canCancelCoverage = !empty($canCancelCoverage);
$coverageWorkplaces = is_array($coverageWorkplaces ?? null) ? $coverageWorkplaces : [];
$coverageTemplates = is_array($coverageTemplates ?? null) ? $coverageTemplates : [];
$workplaces = is_array($workplaces ?? null) ? $workplaces : [];
$selectedWorkplaceId = (int) ($selectedWorkplaceId ?? 0);
$selectedWorkplaceName = (string) ($selectedWorkplaceName ?? '');
$coverageCreateUrl = (string) ($coverageCreateUrl ?? '');
$coverageWorkersUrl = (string) ($coverageWorkersUrl ?? '');
$coverageDetailUrl = (string) ($coverageDetailUrl ?? '');

$filterWorkers = [];
$filterWorkplaces = [];
$filterStatuses = [];
foreach ($workplaces as $workplace) {
    if (!is_array($workplace)) continue;
    $id = (int) ($workplace['id'] ?? 0);
    $name = trim((string) ($workplace['nombre'] ?? ''));
    if ($id > 0 && $name !== '') $filterWorkplaces[$id] = $name;
}
foreach ($coverageRows as $coverage) {
    if (!is_array($coverage)) continue;
    $workerId = (int) ($coverage['worker_id'] ?? 0);
    $workerName = trim((string) ($coverage['worker_label'] ?? ''));
    if ($workerId > 0 && $workerName !== '') $filterWorkers[$workerId] = $workerName;
    foreach ([['home_workplace_id', 'home_label'], ['coverage_workplace_id', 'coverage_label']] as [$idKey, $labelKey]) {
        $id = (int) ($coverage[$idKey] ?? 0);
        $label = trim((string) ($coverage[$labelKey] ?? ''));
        if ($id > 0 && $label !== '') $filterWorkplaces[$id] = $label;
    }
    $statusCode = strtoupper((string) ($coverage['status_code'] ?? ''));
    $statusLabel = trim((string) ($coverage['status_label'] ?? ''));
    if ($statusCode !== '' && $statusLabel !== '') $filterStatuses[$statusCode] = $statusLabel;
}
asort($filterWorkers, SORT_NATURAL | SORT_FLAG_CASE);
asort($filterWorkplaces, SORT_NATURAL | SORT_FLAG_CASE);
$filterWorkers = array_replace(array_fill_keys(array_keys($filterWorkers), ''), $filterWorkers);
?>
<div id="container" class="mt-4 mb-5" data-checador-role-comodines>
    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mb-3">
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#checador-comodines-search-modal">
            <i class="ti ti-filter me-1" aria-hidden="true"></i>Buscar / Filtrar
        </button>
        <?php if ($canRequestCoverage): ?>
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#checador-comodines-create-modal">
                <i class="ti ti-plus me-1" aria-hidden="true"></i>Nueva cobertura
            </button>
        <?php endif; ?>
    </div>

    <section class="card mb-3" aria-labelledby="checador-comodines-coverage-heading">
        <div class="card-header bg-primary text-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h3 class="h5 text-white mb-0" id="checador-comodines-coverage-heading">Coberturas registradas</h3>
                <span class="small text-white-50">Las coberturas autorizadas son temporales y no cambian la adscripción habitual.</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$coverageWriterEnabled): ?>
                <div class="alert alert-info py-2" role="status">Las operaciones administrativas de cobertura están desactivadas en este entorno.</div>
            <?php endif; ?>
            <?php if ($coverageReadState !== 'ready'): ?>
                <div class="alert alert-warning mb-0" role="status">
                    <?= $esc($coverageReadMessage !== '' ? $coverageReadMessage : 'No fue posible consultar las coberturas canónicas en este momento.') ?>
                </div>
            <?php elseif ($coverageRows === []): ?>
                <p class="text-body-secondary mb-0" role="status">Aún no hay coberturas registradas</p>
            <?php else: ?>
                <div class="datatables">
                    <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
                        <table class="table table-striped table-bordered text-nowrap align-middle" id="checador-role-comodines-canonical-table">
                            <caption class="visually-hidden">Solicitudes y coberturas dentro del alcance autorizado.</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Trabajador</th>
                                    <th scope="col">Localidad habitual</th>
                                    <th scope="col">Localidad de cobertura</th>
                                    <th scope="col">Periodo</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Fecha de solicitud</th>
                                    <th scope="col" class="text-center"><span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical" aria-hidden="true"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coverageRows as $coverage): ?>
                                    <?php
                                    $uuid = (string) ($coverage['coverage_uuid'] ?? '');
                                    $validUuid = preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $uuid) === 1;
                                    $statusCode = strtoupper((string) ($coverage['status_code'] ?? ''));
                                    $statusClass = match ($statusCode) {
                                        'REQUESTED' => 'text-bg-warning',
                                        'AUTHORIZED' => 'text-bg-success',
                                        'CANCELLED' => 'text-bg-secondary',
                                        'REJECTED' => 'text-bg-danger',
                                        default => 'text-bg-light border',
                                    };
                                    $actions = [];
                                    if ($validUuid) {
                                        $actions[] = ['kind' => 'detail', 'label' => 'Ver detalle e historial'];
                                        if ($canReviewCoverage && $statusCode === 'REQUESTED') $actions[] = ['kind' => 'authorize', 'label' => 'Aprobar'];
                                        if ($canCancelCoverage && $statusCode === 'AUTHORIZED') $actions[] = ['kind' => 'cancel', 'label' => 'Cancelar cobertura'];
                                    }
                                    ?>
                                    <tr data-worker-id="<?= (int) ($coverage['worker_id'] ?? 0) ?>"
                                        data-home-workplace-id="<?= (int) ($coverage['home_workplace_id'] ?? 0) ?>"
                                        data-coverage-workplace-id="<?= (int) ($coverage['coverage_workplace_id'] ?? 0) ?>"
                                        data-status-code="<?= $esc($statusCode) ?>"
                                        data-from-local="<?= $esc($coverage['from_local'] ?? '') ?>"
                                        data-to-local-exclusive="<?= $esc($coverage['to_local_exclusive'] ?? '') ?>">
                                        <td><?= $esc($coverage['worker_label'] ?? 'Trabajador no disponible') ?></td>
                                        <td><?= $esc($coverage['home_label'] ?? 'Localidad habitual no disponible') ?></td>
                                        <td><?= $esc($coverage['coverage_label'] ?? 'Localidad de cobertura no disponible') ?></td>
                                        <td data-order="<?= $esc($coverage['from_local'] ?? '') ?>"><?= $esc($coverage['period_label'] ?? 'Periodo no disponible') ?></td>
                                        <td><span class="badge <?= $esc($statusClass) ?>"><?= $esc($coverage['status_label'] ?? 'Estado no disponible') ?></span></td>
                                        <td data-order="<?= $esc($coverage['requested_at_utc'] ?? '') ?>"><?= $esc($coverage['requested_at_label'] ?? 'Fecha no disponible') ?></td>
                                        <td class="text-center">
                                            <?php if (count($actions) === 1): ?>
                                                <button class="btn btn-link text-body p-1" type="button" data-coverage-detail data-url="<?= $esc($coverageDetailUrl . '/' . rawurlencode($uuid)) ?>" aria-label="Ver detalle e historial">
                                                    <i class="ti ti-eye" aria-hidden="true"></i>
                                                </button>
                                            <?php elseif (count($actions) > 1): ?>
                                                <div class="dropdown dropstart">
                                                    <button class="btn btn-link text-body p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de la cobertura">
                                                        <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php foreach ($actions as $action): ?>
                                                            <?php if ($action['kind'] === 'detail'): ?>
                                                                <li><button class="dropdown-item" type="button" data-coverage-detail data-url="<?= $esc($coverageDetailUrl . '/' . rawurlencode($uuid)) ?>"><i class="ti ti-eye me-2" aria-hidden="true"></i>Ver detalle e historial</button></li>
                                                            <?php elseif ($action['kind'] === 'authorize'): ?>
                                                                <li><button class="dropdown-item" type="button" data-coverage-transition="authorize" data-uuid="<?= $esc($uuid) ?>"><i class="ti ti-check me-2" aria-hidden="true"></i>Aprobar</button></li>
                                                            <?php elseif ($action['kind'] === 'cancel'): ?>
                                                                <li><button class="dropdown-item text-danger" type="button" data-coverage-transition="cancel" data-uuid="<?= $esc($uuid) ?>"><i class="ti ti-x me-2" aria-hidden="true"></i>Cancelar cobertura</button></li>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($state === 'ready' && $roles !== []): ?>
        <section class="card" aria-labelledby="checador-comodines-legacy-heading">
            <div class="card-header bg-primary text-white">
                <h3 class="h5 text-white mb-0" id="checador-comodines-legacy-heading">Registros históricos (Legacy)</h3>
            </div>
            <div class="card-body">
                <p class="text-body-secondary">Estos registros históricos se muestran separados y no confirman una cobertura autorizada.</p>
                <div class="datatables">
                    <div class="table-responsive overflow-x-auto overflow-y-hidden pb-2">
                        <table class="table table-striped table-bordered text-nowrap align-middle" id="checador-role-comodines-legacy-table">
                            <caption class="visually-hidden">Registros históricos anteriores al control canónico de coberturas.</caption>
                            <thead><tr><th scope="col">Periodo</th><th scope="col">Estado registrado</th><th scope="col">Localidad</th><th scope="col">Trabajador</th><th scope="col">Días registrados</th><th scope="col">Información</th></tr></thead>
                            <tbody>
                                <?php foreach ($roles as $role): ?>
                                    <?php
                                    $identityLabel = (string) ($role['identity_label'] ?? 'Trabajador no identificable');
                                    $identityLabel = str_replace('Registro legacy sin vínculo canónico verificable', 'Registro histórico sin trabajador identificable', $identityLabel);
                                    $programmingLabel = match ((string) ($role['programming_label'] ?? '')) {
                                        'Programación validada estructuralmente' => 'Información registrada',
                                        'Programación incompleta o legacy', 'Programación legacy no validada' => 'Información incompleta o sin verificar',
                                        default => 'Información no disponible',
                                    };
                                    ?>
                                    <tr>
                                        <td><?= $esc($role['period_label'] ?? 'Periodo no disponible') ?></td>
                                        <td><?= $esc($role['status_label'] ?? 'Estado no disponible') ?></td>
                                        <td><?= $esc($role['station_label'] ?? 'Localidad no disponible') ?></td>
                                        <td><?= $esc($identityLabel) ?></td>
                                        <td><?= $esc($role['days_label'] ?? 'Sin días registrados') ?></td>
                                        <td><?= $esc($programmingLabel) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<div class="modal fade" id="checador-comodines-search-modal" tabindex="-1" aria-labelledby="checador-comodines-search-heading" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="checador-comodines-search-heading">Buscar / Filtrar</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="checador-comodines-filter-from">Periodo desde</label><input class="form-control" type="date" id="checador-comodines-filter-from"></div>
                <div class="col-md-6"><label class="form-label" for="checador-comodines-filter-to">Periodo hasta (incluido)</label><input class="form-control" type="date" id="checador-comodines-filter-to"></div>
                <div class="col-12"><label class="form-label" for="checador-comodines-filter-worker">Trabajador</label><div class="select2-modal-field"><select class="form-select" id="checador-comodines-filter-worker"><option value="">Todos los trabajadores</option><?php foreach ($filterWorkers as $id => $name): ?><option value="<?= (int) $id ?>"><?= $esc($name) ?></option><?php endforeach; ?></select></div></div>
                <div class="col-md-6"><label class="form-label" for="checador-comodines-filter-workplace">Localidad</label><div class="select2-modal-field"><select class="form-select" id="checador-comodines-filter-workplace"><option value="">Todas las localidades autorizadas</option><?php foreach ($filterWorkplaces as $id => $name): ?><option value="<?= (int) $id ?>"><?= $esc($name) ?></option><?php endforeach; ?></select></div></div>
                <div class="col-md-6"><label class="form-label" for="checador-comodines-filter-status">Estado</label><select class="form-select" id="checador-comodines-filter-status"><option value="">Todos los estados</option><?php foreach ($filterStatuses as $code => $label): ?><option value="<?= $esc($code) ?>"><?= $esc($label) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="alert d-none mt-3 mb-0" role="status" data-comodines-filter-message></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-comodines-filter-clear>Limpiar</button><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" data-comodines-filter-apply>Consultar</button></div>
    </div></div>
</div>

<?php if ($canRequestCoverage): ?>
<div class="modal fade" id="checador-comodines-create-modal" tabindex="-1" aria-labelledby="checador-comodines-create-heading" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <form id="checador-comodines-create-form" data-create-url="<?= $esc($coverageCreateUrl) ?>" data-workers-url="<?= $esc($coverageWorkersUrl) ?>">
            <div class="modal-header"><h2 class="modal-title fs-5" id="checador-comodines-create-heading">Nueva cobertura</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" id="checador-comodines-membership-id" value="">
                <input type="hidden" id="checador-comodines-home-workplace-id" value="">
                <input type="hidden" id="checador-comodines-csrf" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label" for="checador-comodines-worker">Trabajador</label><div class="select2-modal-field"><select class="form-select" id="checador-comodines-worker" data-select2-ajax required><option value="">Busca por nombre, ID o número</option></select></div><div class="form-text" id="checador-comodines-worker-message">Selecciona el periodo para consultar los candidatos activos y su elegibilidad.</div></div>
                    <div class="col-md-6"><label class="form-label" for="checador-comodines-target">Localidad de cobertura</label><div class="select2-modal-field"><select class="form-select" id="checador-comodines-target" required><option value="">Selecciona una localidad autorizada</option><?php foreach ($coverageWorkplaces as $workplace): ?><option value="<?= (int) ($workplace['id'] ?? 0) ?>"><?= $esc($workplace['nombre'] ?? '') ?></option><?php endforeach; ?></select></div><div class="form-text">La cobertura no modifica la adscripción habitual ni amplía permisos del Encargado.</div></div>
                    <div class="col-md-3"><label class="form-label" for="checador-comodines-from">Desde (incluido)</label><input class="form-control" id="checador-comodines-from" type="date" required></div>
                    <div class="col-md-3"><label class="form-label" for="checador-comodines-to">Hasta (sin incluir)</label><input class="form-control" id="checador-comodines-to" type="date" required></div>
                    <div class="col-12"><label class="form-label" for="checador-comodines-reason">Motivo</label><textarea class="form-control" id="checador-comodines-reason" rows="2" maxlength="500" required></textarea></div>
                    <div class="col-12"><h3 class="h6 mb-2">Patrón semanal</h3><p class="form-text">Elige trabajo con una plantilla/version o descanso explícito para cada día.</p><div class="row g-2">
                        <?php foreach ([['LUNES','Lunes'],['MARTES','Martes'],['MIERCOLES','Miércoles'],['JUEVES','Jueves'],['VIERNES','Viernes'],['SABADO','Sábado'],['DOMINGO','Domingo']] as [$dayCode,$dayName]): ?>
                            <div class="col-md-6"><label class="form-label" for="checador-comodines-day-<?= $esc($dayCode) ?>"><?= $esc($dayName) ?></label><div class="select2-modal-field"><select class="form-select" id="checador-comodines-day-<?= $esc($dayCode) ?>" data-coverage-day="<?= $esc($dayCode) ?>" required><option value="">Selecciona trabajo o descanso</option><option value="DAY_OFF">Descanso explícito</option><?php foreach ($coverageTemplates as $template): ?><option value="<?= (int) ($template['id'] ?? 0) ?>:<?= (int) ($template['version'] ?? 0) ?>"><?= $esc($template['nombre'] ?? 'Turno') ?> · versión <?= (int) ($template['version'] ?? 0) ?></option><?php endforeach; ?></select></div></div>
                        <?php endforeach; ?>
                    </div></div>
                </div>
                <div id="checador-comodines-create-message" class="alert d-none mt-3 mb-0" role="status"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit" data-checador-comodines-submit><i class="ti ti-send me-1" aria-hidden="true"></i>Guardar solicitud</button></div>
        </form>
    </div></div>
</div>
<?php endif; ?>

<div class="modal fade" id="checador-comodines-detail-modal" tabindex="-1" aria-labelledby="checador-comodines-detail-heading" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="checador-comodines-detail-heading">Detalle e historial de cobertura</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div><div class="modal-body"><div class="alert alert-info d-none" role="status" data-coverage-detail-message></div><dl class="row mb-0" data-coverage-detail-content></dl></div></div></div>
</div>

<?php if ($canReviewCoverage || $canCancelCoverage): ?>
<div class="modal fade" id="checador-comodines-transition-modal" tabindex="-1" aria-labelledby="checador-comodines-transition-heading" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content"><form id="checador-comodines-transition-form" data-base-url="<?= $esc($coverageDetailUrl) ?>" data-csrf="<?= $esc(\App\Core\CsrfToken::token()) ?>">
        <div class="modal-header"><h2 class="modal-title fs-5" id="checador-comodines-transition-heading">Confirmar operación</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body"><input type="hidden" data-coverage-transition-uuid><input type="hidden" data-coverage-transition-action><label class="form-label" for="checador-comodines-transition-reason">Motivo</label><textarea class="form-control" id="checador-comodines-transition-reason" rows="3" maxlength="500" required></textarea><div class="alert d-none mt-3 mb-0" role="status" data-coverage-transition-message></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" data-coverage-transition-submit>Confirmar</button></div>
    </form></div></div>
</div>
<?php endif; ?>

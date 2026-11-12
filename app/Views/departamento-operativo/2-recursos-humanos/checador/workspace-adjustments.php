<?php
declare(strict_types=1);

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$evaluations = is_array($administrativeEvaluations ?? null) ? $administrativeEvaluations : [];
$evaluationErrors = is_array($administrativeEvaluationErrors ?? null) ? $administrativeEvaluationErrors : [];
$evaluationPermissions = is_array($evaluationPermissions ?? null) ? $evaluationPermissions : [];
$evaluationKeys = is_array($evaluationKeys ?? null) ? $evaluationKeys : [];
$evaluationFeedback = is_array($evaluationFeedback ?? null) ? $evaluationFeedback : null;
$adjustmentCount = 0;
foreach ($evaluations as $evaluationResult) {
    $evaluation = is_array($evaluationResult['evaluation'] ?? null) ? $evaluationResult['evaluation'] : [];
    $adjustmentCount += count(is_array($evaluation['linked_adjustments'] ?? null) ? $evaluation['linked_adjustments'] : []);
}
$evaluationStatusLabel = static fn(string $status): string => match ($status) {
    'REVIEW_REQUIRED', 'OVERLAP_CONFLICT' => 'Requiere revisión humana',
    'DATA_INCOMPLETE' => 'Información incompleta',
    'VACATION_APPLIED' => 'Vacaciones registradas en la respuesta de evaluación',
    'FALTA' => 'FALTA autorizada explícitamente',
    default => 'Resultado no disponible',
};
$reviewStatusLabel = static fn(string $status): string => match ($status) {
    'APPROVED' => 'Revisión aprobada',
    'REJECTED' => 'Revisión rechazada',
    'PENDING', 'REVIEW_REQUIRED' => 'Pendiente de revisión',
    'NOT_REQUIRED' => 'No requiere revisión',
    default => 'No disponible',
};
?>
<div id="container" class="mt-4 mb-5">
    <section class="card mb-4" aria-labelledby="administrative-adjustments-list-heading">
        <div class="card-header bg-primary text-white d-flex align-items-center gap-2 gap-md-3 flex-wrap">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-adjustments fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0 text-white" id="administrative-adjustments-list-heading">Inventario de ajustes administrativos</h2>
                <span class="small text-white-50">Consulta de ajustes registrados y su trazabilidad</span>
            </div>
            <?php if (!empty($canReadAdministrativeAdjustments)): ?>
                <button type="button" class="btn bg-white text-primary ms-auto" data-bs-toggle="modal" data-bs-target="#checador-adjustment-search-modal">
                    <i class="ti ti-search me-1" aria-hidden="true"></i>Buscar / Filtrar
                </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($canReadAdministrativeAdjustments)): ?>
                <div class="alert alert-warning mb-0" role="status">No tienes permiso de lectura para consultar el inventario de ajustes administrativos en esta localidad.</div>
            <?php else: ?>
                <?php $adjustmentFilterDate = is_string($selectedDate ?? null) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $selectedDate) === 1 ? $selectedDate : date('Y-m-d'); ?>
                <div id="checador-adjustments-read" data-workplace-id="<?= (int) ($selectedWorkplaceId ?? 0) ?>"
                    data-default-date="<?= $esc($adjustmentFilterDate) ?>"
                    data-roster-status="<?= $esc($adjustmentRosterStatus ?? 'unavailable') ?>"
                    data-list-url="/departamento-operativo/recursos-humanos/checador/ajustes/data"
                    data-detail-url="/departamento-operativo/recursos-humanos/checador/ajustes/detalle">
                    <div id="adjustments-read-feedback" class="alert d-none" role="status" aria-live="polite"></div>
                    <div class="table-responsive adjustments-table-wrap"><table id="checador-adjustments-table" class="table table-striped table-bordered align-middle mb-0 w-100">
                        <caption class="visually-hidden">Ajustes administrativos persistidos para la localidad autorizada</caption>
                        <thead><tr><th scope="col">Fecha</th><th scope="col">Colaborador</th><th scope="col">Localidad</th><th scope="col">Tipo</th><th scope="col">Motivo</th><th scope="col">Estado</th><th scope="col">Responsable</th><th scope="col" class="text-center">Acción</th></tr></thead>
                    </table></div>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php if (!empty($canReadAdministrativeAdjustments)): ?>
        <div class="modal fade" id="checador-adjustment-search-modal" tabindex="-1" aria-labelledby="checador-adjustment-search-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white">
                    <div><h2 class="modal-title h5 text-white mb-0" id="checador-adjustment-search-title"><i class="ti ti-search me-1" aria-hidden="true"></i>Buscar ajustes administrativos</h2>
                        <span class="small text-white-50">Selecciona los criterios para consultar los ajustes registrados.</span></div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="checador-adjustments-filters" class="adjustments-filter-form">
                    <div class="modal-body">
                        <div id="adjustments-search-feedback" class="alert d-none" role="status" aria-live="polite"></div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6 adjustments-filter-field"><label class="form-label" for="adjustments-workplace">Localidad autorizada</label>
                                <input id="adjustments-workplace" class="form-control" value="<?= $esc($selectedWorkplaceName ?? 'Localidad autorizada') ?>" readonly aria-readonly="true"></div>
                            <div class="col-12 col-md-6 adjustments-filter-field"><label class="form-label" for="adjustments-worker">Colaborador</label><select id="adjustments-worker" class="form-select"><option value="">Todos los colaboradores</option>
                                <?php foreach (($workerSelectOptions ?? []) as $workerOption): if (!is_array($workerOption) || (int) ($workerOption['worker_id'] ?? 0) < 1) continue; ?>
                                    <option value="<?= (int) $workerOption['worker_id'] ?>" <?= (int) ($selectedWorkerId ?? 0) === (int) $workerOption['worker_id'] ? 'selected' : '' ?>><?= $esc($workerOption['full_name'] ?? ('Personal #' . (int) $workerOption['worker_id'])) ?></option>
                                <?php endforeach; ?>
                            </select><small id="adjustments-roster-feedback" class="form-text"><?php if (($adjustmentRosterStatus ?? '') === 'ready'): ?>Padrón autorizado validado para la fecha seleccionada.<?php elseif (in_array((string) ($adjustmentRosterStatus ?? ''), ['partial', 'current_roster_only'], true)): ?>La lista puede estar incompleta para la fecha seleccionada.<?php else: ?>No se confirmó el padrón autorizado para la fecha seleccionada.<?php endif; ?></small></div>
                            <div class="col-12 col-md-6 adjustments-filter-field"><label class="form-label" for="adjustments-type">Tipo de ajuste</label><select id="adjustments-type" class="form-select"><option value="">Todos los tipos</option>
                                <?php foreach (['OVERRIDE_STATUS', 'INTERPRETED_ENTRY_TIME', 'INTERPRETED_EXIT_TIME', 'JUSTIFY_LATENESS'] as $adjustmentType): ?><option value="<?= $esc($adjustmentType) ?>"><?= $esc(\App\Services\ChecadorPresentationFormatter::administrativeAdjustmentTypeLabel($adjustmentType)) ?></option><?php endforeach; ?>
                            </select></div>
                            <div class="col-12 col-md-6 adjustments-filter-field"><label class="form-label" for="adjustments-status">Estado</label><select id="adjustments-status" class="form-select"><option value="">Todos los estados</option>
                                <?php foreach (['ACTIVE', 'SUPERSEDED', 'CANCELLED'] as $adjustmentStatus): ?><option value="<?= $esc($adjustmentStatus) ?>"><?= $esc(\App\Services\ChecadorPresentationFormatter::administrativeAdjustmentStatusLabel($adjustmentStatus)) ?></option><?php endforeach; ?>
                            </select></div>
                            <div class="col-12 col-md-6 adjustments-filter-field"><label class="form-label" for="adjustments-date-from">Fecha inicial</label><input id="adjustments-date-from" type="date" class="form-control" value="<?= $esc($adjustmentFilterDate) ?>" required></div>
                            <div class="col-12 col-md-6 adjustments-filter-field"><label class="form-label" for="adjustments-date-to">Fecha final</label><input id="adjustments-date-to" type="date" class="form-control" value="<?= $esc($adjustmentFilterDate) ?>" required></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" id="adjustments-reset-filters"><i class="ti ti-refresh me-1" aria-hidden="true"></i>Limpiar filtros</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary adjustments-filter-btn"><i class="ti ti-search me-1" aria-hidden="true"></i>Consultar ajustes</button>
                    </div>
                </form>
            </div></div>
        </div>
        <div class="modal fade" id="checador-adjustment-detail-modal" tabindex="-1" aria-labelledby="checador-adjustment-detail-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header modal-colored-header bg-primary text-white"><h2 class="modal-title h5 text-white mb-0" id="checador-adjustment-detail-title">Detalle del ajuste administrativo</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body"><div id="adjustment-detail-feedback" class="alert d-none" role="status"></div><dl id="adjustment-detail-fields" class="row mb-3"></dl>
                    <h3 class="h6">Historial de versiones</h3><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Revisión</th><th>Estado</th><th>Creado (UTC)</th><th>Responsable</th></tr></thead><tbody id="adjustment-detail-history"></tbody></table></div>
                    <h3 class="h6 mt-3">Evaluaciones relacionadas</h3><div id="adjustment-linked-evaluations" class="small text-muted">Sin vínculos consultados.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button></div>
            </div></div>
        </div>
    <?php endif; ?>
    <section class="card" aria-labelledby="adjustment-workspace-heading">
        <div class="card-header bg-light text-primary d-flex align-items-center gap-2">
            <span class="rounded-circle bg-white text-primary d-inline-flex align-items-center justify-content-center p-2" aria-hidden="true"><i class="ti ti-clipboard-check fs-5"></i></span>
            <div>
                <h2 class="h5 mb-0" id="adjustment-workspace-heading">Evaluaciones administrativas</h2>
                <span class="small text-muted">Consulta de evaluaciones y referencias vinculadas</span>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted">El resultado proviene del servicio institucional; Portal3 no recalcula marcaciones ni resultados de nómina.</p>
            <?php if ($evaluationFeedback !== null
                && (int) ($evaluationFeedback['workplace_id'] ?? 0) === (int) ($selectedWorkplaceId ?? 0)
                && (string) ($evaluationFeedback['date'] ?? '') === (string) ($selectedDate ?? '')): ?>
                <div class="alert alert-<?= ($evaluationFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
                    <?= $esc($evaluationFeedback['message'] ?? 'La evaluación no pudo confirmarse.') ?>
                </div>
            <?php endif; ?>
            <?php if (($state ?? '') !== 'success'): ?>
                <?php
                $evaluationStateMessage = ($state ?? '') === 'snapshot_state_unavailable'
                    ? 'Para consultar evaluaciones, abre primero una asistencia diaria autorizada.'
                    : (($stateMessage ?? '') !== '' ? $stateMessage : 'La consulta no está disponible; no se confirma un resultado vacío.');
                ?>
                <div class="alert <?= ($state ?? '') === 'snapshot_state_unavailable' ? 'alert-info' : 'alert-warning' ?> py-2 small" role="status"><?= $esc($evaluationStateMessage) ?></div>
            <?php endif; ?>
            <?php foreach ($evaluationErrors as $workerId => $kind): ?><div class="alert alert-warning" role="status">No fue posible consultar las referencias administrativas de personal <?= (int) $workerId ?>.</div><?php endforeach; ?>
            <?php if (($state ?? '') === 'success' && $evaluations === [] && $evaluationErrors === []): ?><div class="alert alert-info py-2 mb-3" role="status">La fuente no devolvió evaluaciones para esta consulta. No se infiere que no existan otros registros.</div><?php elseif (($state ?? '') === 'success' && $adjustmentCount === 0 && $evaluationErrors === []): ?><p class="alert alert-secondary py-2 mb-3" role="status">Las evaluaciones disponibles no incluyen referencias de ajustes para esta consulta.</p><?php endif; ?>
            <?php foreach ($evaluations as $workerId => $result): ?>
                <?php
                if (!is_array($result)) continue;
                $evaluation = is_array($result['evaluation'] ?? null) ? $result['evaluation'] : null;
                $adjustments = is_array($evaluation['linked_adjustments'] ?? null) ? $evaluation['linked_adjustments'] : [];
                $evaluationExists = ($result['evaluation_exists'] ?? false) === true && $evaluation !== null;
                $policyRuleVersionLabel = $evaluation !== null && array_key_exists('policy_rule_version', $evaluation)
                    ? ($evaluation['policy_rule_version'] === null
                        ? 'No aplica'
                        : (is_string($evaluation['policy_rule_version']) && trim($evaluation['policy_rule_version']) !== ''
                            ? (string) $evaluation['policy_rule_version'] : 'No disponible'))
                    : 'No disponible';
                $evaluationKeySet = is_array($evaluationKeys[$workerId] ?? null) ? $evaluationKeys[$workerId] : [];
                $expectedCalculationId = (int) ($evaluationKeySet['expected_calculation_revision_id'] ?? 0);
                $canEvaluate = !empty($evaluationPermissions['evaluate']) && is_string($evaluationKeySet['evaluate'] ?? null);
                $canAuthorizeFalta = !empty($evaluationPermissions['authorize_falta']) && is_string($evaluationKeySet['authorize_falta'] ?? null);
                $feedbackMatchesWorker = $evaluationFeedback !== null
                    && (int) ($evaluationFeedback['worker_id'] ?? 0) === (int) $workerId;
                $retryKey = $feedbackMatchesWorker ? (string) ($evaluationFeedback['retry_idempotency_key'] ?? '') : '';
                ?>
                <div class="border rounded p-3 mb-3">
                    <h3 class="h6">Personal <?= (int) $workerId ?> · <?= $esc($result['work_date'] ?? $selectedDate ?? 'Fecha no disponible') ?></h3>
                    <?php if (!$evaluationExists): ?>
                        <p class="text-muted">La fuente no devolvió una evaluación administrativa existente para esta consulta.</p>
                    <?php else: ?>
                        <p class="mb-2"><strong>Resultado:</strong> <?= $esc($evaluationStatusLabel((string) ($evaluation['administrative_status'] ?? ''))) ?></p>
                        <p class="small text-muted mb-2"><strong>Revisión:</strong> <?= $esc($reviewStatusLabel((string) ($evaluation['human_review_status'] ?? ''))) ?> · revisión <?= (int) ($evaluation['revision_number'] ?? 0) ?></p>
                        <?php if ($adjustments === []): ?><p class="small mb-2">No se devolvieron referencias de ajustes enlazadas a esta evaluación.</p><?php else: ?>
                            <div class="table-responsive"><table class="table table-striped table-bordered align-middle mb-0"><caption class="visually-hidden">Referencias de ajustes enlazadas a la evaluación administrativa de personal <?= (int) $workerId ?></caption><thead><tr><th scope="col">Tipo</th><th scope="col">Estado</th><th scope="col">Revisión</th><th scope="col">Referencia del ajuste</th><th scope="col">Referencia lógica</th></tr></thead><tbody>
                                <?php foreach ($adjustments as $adjustment): ?><tr>
                                    <td><?= $esc(\App\Services\ChecadorPresentationFormatter::administrativeAdjustmentTypeLabel($adjustment['adjustment_type'] ?? null)) ?></td>
                                    <td><?= $esc(\App\Services\ChecadorPresentationFormatter::administrativeAdjustmentStatusLabel($adjustment['adjustment_status'] ?? null)) ?></td>
                                    <td><?= (int) ($adjustment['revision_number'] ?? 0) ?></td>
                                    <td class="text-break"><code><?= $esc($adjustment['uuid_ajuste'] ?? 'No disponible') ?></code></td>
                                    <td class="text-break"><code><?= $esc($adjustment['uuid_ajuste_logico'] ?? 'No disponible') ?></code></td>
                                </tr><?php endforeach; ?>
                            </tbody></table></div>
                            <p class="small text-muted mt-2 mb-0">Estas referencias muestran únicamente los ajustes enlazados que devolvió la evaluación; no entrega aquí el historial completo de revisiones de cada ajuste.</p>
                        <?php endif; ?>
                        <details class="mt-2"><summary class="small">Procedencia de la evaluación</summary><dl class="row small mt-2 mb-0">
                            <dt class="col-sm-4">Identificador de cálculo</dt><dd class="col-sm-8 text-break"><code><?= $esc($evaluation['associated_calculation_revision_id'] ?? 'No disponible') ?></code></dd>
                            <dt class="col-sm-4">Creada (UTC)</dt><dd class="col-sm-8"><?= $esc($evaluation['created_at_utc'] ?? 'No disponible') ?></dd>
                            <dt class="col-sm-4">Versión de reglas</dt><dd class="col-sm-8 text-break"><?= $esc($evaluation['algorithm_version'] ?? 'No disponible') ?> · <?= $esc($evaluation['policy_version'] ?? 'No disponible') ?></dd>
                            <dt class="col-sm-4">Regla de política</dt><dd class="col-sm-8 text-break"><?= $esc($policyRuleVersionLabel) ?></dd>
                        </dl></details>
                    <?php endif; ?>

                    <?php if ($canEvaluate || $canAuthorizeFalta): ?>
                        <div class="border-top mt-3 pt-3">
                            <p class="small text-muted">La evaluación consume la Jornada e incidencias de la fuente autorizada. No recalcula marcaciones, no modifica incidencias ni produce resultados de nómina.</p>
                            <?php if ($canEvaluate): ?>
                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/evaluations/evaluate" class="border rounded p-2 mb-2">
                                    <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                    <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                    <input type="hidden" name="snapshot_ref" value="<?= $esc($snapshotRef ?? '') ?>">
                                    <?php if ($expectedCalculationId > 0): ?><input type="hidden" name="expected_calculation_revision_id" value="<?= $expectedCalculationId ?>"><?php endif; ?>
                                    <input type="hidden" name="idempotency_key" value="<?= $esc($retryKey !== '' && ($evaluationFeedback['action'] ?? '') === 'evaluate' ? $retryKey : ($evaluationKeySet['evaluate'] ?? '')) ?>">
                                    <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_evaluation" value="1" required> Confirmo ejecutar la evaluación para este trabajador y fecha.</label>
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Ejecutar evaluación</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($canAuthorizeFalta): ?>
                                <form method="post" action="/departamento-operativo/recursos-humanos/checador/evaluations/evaluate" class="border border-warning rounded p-2">
                                    <input type="hidden" name="_csrf_token" value="<?= $esc(\App\Core\CsrfToken::token()) ?>">
                                    <input type="hidden" name="workplace_id" value="<?= (int) ($selectedWorkplaceId ?? 0) ?>">
                                    <input type="hidden" name="worker_id" value="<?= (int) $workerId ?>">
                                    <input type="hidden" name="date" value="<?= $esc($selectedDate ?? '') ?>">
                                    <input type="hidden" name="snapshot_ref" value="<?= $esc($snapshotRef ?? '') ?>">
                                    <?php if ($expectedCalculationId > 0): ?><input type="hidden" name="expected_calculation_revision_id" value="<?= $expectedCalculationId ?>"><?php endif; ?>
                                    <input type="hidden" name="idempotency_key" value="<?= $esc($retryKey !== '' && ($evaluationFeedback['action'] ?? '') === 'authorize_falta' ? $retryKey : ($evaluationKeySet['authorize_falta'] ?? '')) ?>">
                                    <input type="hidden" name="authorize_falta" value="1">
                                    <p class="small mb-2"><strong>Autorización final de FALTA</strong>: acción independiente que requiere confirmación humana explícita; nunca se activa automáticamente.</p>
                                    <label class="form-check small mb-2"><input class="form-check-input" type="checkbox" name="confirm_evaluation" value="1" required> Confirmo explícitamente autorizar FALTA para este trabajador y fecha.</label>
                                    <button type="submit" class="btn btn-sm btn-outline-warning">Autorizar FALTA explícitamente</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="alert alert-light border mb-0 adjustments-final-falta-note" role="note">La autorización final de FALTA permanece separada de la evaluación y requiere permiso específico y confirmación humana.</div>
        </div>
    </section>
</div>

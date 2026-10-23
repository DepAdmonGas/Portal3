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
    <?php require __DIR__ . '/_workspace-context.php'; ?>
    <section class="card" aria-labelledby="adjustment-workspace-heading">
        <div class="card-header"><h2 class="h5 mb-0" id="adjustment-workspace-heading">Ajustes administrativos</h2></div>
        <div class="card-body">
            <p class="text-muted">Consulta las evaluaciones y referencias disponibles. Los resultados provienen del servicio; Portal3 no determina faltas ni consecuencias de nómina.</p>
            <?php if ($evaluationFeedback !== null
                && (int) ($evaluationFeedback['workplace_id'] ?? 0) === (int) ($selectedWorkplaceId ?? 0)
                && (string) ($evaluationFeedback['date'] ?? '') === (string) ($selectedDate ?? '')): ?>
                <div class="alert alert-<?= ($evaluationFeedback['type'] ?? '') === 'success' ? 'success' : 'warning' ?>" role="status">
                    <?= $esc($evaluationFeedback['message'] ?? 'La evaluación no pudo confirmarse.') ?>
                </div>
            <?php endif; ?>
            <?php if (($state ?? '') !== 'success'): ?><div class="alert alert-warning" role="status"><?= $esc(($stateMessage ?? '') !== '' ? $stateMessage : 'La consulta estable no está disponible; no se confirma un resultado vacío.') ?></div><?php endif; ?>
            <?php foreach ($evaluationErrors as $workerId => $kind): ?><div class="alert alert-warning" role="status">No fue posible consultar las referencias administrativas de personal <?= (int) $workerId ?>.</div><?php endforeach; ?>
            <?php if (($state ?? '') === 'success' && $evaluations === [] && $evaluationErrors === []): ?><div class="alert alert-info" role="status">La fuente no devolvió evaluaciones para esta consulta. No se infiere que no existan otros registros.</div><?php elseif (($state ?? '') === 'success' && $adjustmentCount === 0 && $evaluationErrors === []): ?><p class="alert alert-secondary" role="status">Las evaluaciones disponibles no incluyen referencias de ajustes para esta consulta.</p><?php endif; ?>
            <?php foreach ($evaluations as $workerId => $result): ?>
                <?php
                if (!is_array($result)) continue;
                $evaluation = is_array($result['evaluation'] ?? null) ? $result['evaluation'] : null;
                $adjustments = is_array($evaluation['linked_adjustments'] ?? null) ? $evaluation['linked_adjustments'] : [];
                $evaluationExists = ($result['evaluation_exists'] ?? false) === true && $evaluation !== null;
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
                        <?php endif; ?>
                        <details class="mt-2"><summary class="small">Procedencia de la evaluación</summary><dl class="row small mt-2 mb-0">
                            <dt class="col-sm-4">Identificador de cálculo</dt><dd class="col-sm-8 text-break"><code><?= $esc($evaluation['associated_calculation_revision_id'] ?? 'No disponible') ?></code></dd>
                            <dt class="col-sm-4">Creada (UTC)</dt><dd class="col-sm-8"><?= $esc($evaluation['created_at_utc'] ?? 'No disponible') ?></dd>
                            <dt class="col-sm-4">Versión de reglas</dt><dd class="col-sm-8 text-break"><?= $esc($evaluation['algorithm_version'] ?? 'No disponible') ?> · <?= $esc($evaluation['policy_version'] ?? 'No disponible') ?></dd>
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
            <div class="alert alert-light border mb-0" role="note">La autorización final de FALTA permanece separada de la evaluación y requiere permiso específico y confirmación humana.</div>
        </div>
    </section>
</div>

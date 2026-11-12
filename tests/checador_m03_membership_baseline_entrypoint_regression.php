<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routes = file_get_contents($root . '/routes/web.php') ?: '';
$controller = file_get_contents($root . '/app/Controllers/ControlDocumentosPersonalController.php') ?: '';
$view = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/control-documentos-personal/index.php') ?: '';
$script = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/control-documentos-personal.actions.init.js') ?: '';
$previewService = file_get_contents($root . '/app/Services/WorkerWorkplaceAssignmentBaselinePreviewService.php') ?: '';
$importService = file_get_contents($root . '/app/Services/WorkerWorkplaceAssignmentBaselineImportService.php') ?: '';

$checks = [
    'preview and import are authenticated Portal3 routes' => str_contains($routes, "Route::auth(['ControlDocumentosPersonalController', 'previewChecadorMembershipBaseline'])")
        && str_contains($routes, "Route::auth(['ControlDocumentosPersonalController', 'importChecadorMembershipBaseline'])"),
    'both handlers require current RH edit ACL, active module access, and the authenticated actor' => str_contains($controller, 'ModuleStationService::isAvailable(\'control-documentos-personal\')')
        && str_contains($controller, "empty(\$permissions['puedeEditar'])")
        && str_contains($controller, '$actorId !== (int) Auth::id()'),
    'baseline writes require the preproduction identity gate while preview remains separately readable' => str_contains($controller, 'WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportEnabled()')
        && str_contains($importService, 'WorkerWorkplaceAssignmentHistoryWriteGate::baselineImportEnabled()')
        && str_contains($controller, 'WorkerWorkplaceAssignmentHistoryWriteGate::enabled()') === false
        && str_contains($view, 'baselinePreview?.import_enabled'),
    'all authorized physical localities use the full canonical candidate set without worker-specific exceptions' => str_contains($controller, 'candidate_worker_ids')
        && str_contains($controller, 'candidate_count')
        && !str_contains($controller, 'CHECADOR_BASELINE_EXPECTED_WORKER_IDS')
        && !str_contains($controller, 'CHECADOR_SELECTIVE_BASELINE_WORKER_IDS')
        && !str_contains($controller, '2026-11-06T06:00:00Z'),
    'all localities require an explicit future UTC cutoff, reason, and selected canonical station context' => str_contains($controller, 'private function requireSelectedBaselineStation')
        && str_contains($controller, 'private function baselineCutoff')
        && str_contains($controller, '$validFromUtc = $this->baselineCutoff')
        && str_contains($controller, "'workplace_id' => (int) \$workplaceId"),
    'preview uses only unique authorized physical-station mappings into canonical localities' => str_contains($previewService, 'ModuleStationService::getAvailableStations(self::SOURCE_MODULE_KEY)')
        && str_contains($previewService, "where('numlista', \$numlista)->count() !== 1")
        && str_contains($previewService, "Estacion::query()->where('numlista', \$numlista)->count() !== 1")
        && str_contains($previewService, 'authorizedStationWorkplaceIds'),
    'candidate source remains active op_rh_personal rows lacking op_rh_personal_localidad_version history' => str_contains($previewService, "Capsule::table('op_rh_personal as p')")
        && str_contains($previewService, "Capsule::table('op_rh_personal_localidad_version')")
        && str_contains($previewService, '!isset($workersWithHistory[(int) $row->worker_id])'),
    'department context is rejected even when its numeric identifier overlaps a locality' => str_contains($controller, '($context[\'id_depto\'] ?? null) !== null')
        && str_contains($controller, '(int) ($context[\'id_estacion\'] ?? 0) !== $workplaceId'),
    'preview returns active, missing, source and candidate fingerprints without database writes' => str_contains($controller, "'active_worker_count' =>")
        && str_contains($controller, "'active_worker_ids' =>")
        && str_contains($controller, "'active_workers_with_membership_ids' =>")
        && str_contains($controller, "'candidate_fingerprint' =>")
        && str_contains($controller, "'source_fingerprint' =>")
        && str_contains($controller, "'database_writes' => false"),
    'partial worker selection is rejected by both authenticated handlers' => substr_count($controller, "array_key_exists('selected_worker_ids', \$input)") === 2
        && str_contains($controller, "array_key_exists('full_candidate_fingerprint', \$input)")
        && !str_contains($importService, '$selectedWorkerIds')
        && !str_contains($importService, '$expectedFullCandidateFingerprint'),
    'import is bound to the actor session, locality, cutoff, freshness, and both fingerprints' => str_contains($controller, "Session::set(self::CHECADOR_BASELINE_PREVIEW_SESSION_KEY")
        && str_contains($controller, 'time() - (int) ($sessionPreview[\'created_at\'] ?? 0) > 900')
        && str_contains($controller, 'hash_equals((string) ($sessionPreview[\'candidate_fingerprint\'] ?? \'\'), $candidateFingerprint)')
        && str_contains($controller, 'hash_equals((string) ($sessionPreview[\'source_fingerprint\'] ?? \'\'), $sourceFingerprint)')
        && str_contains($controller, "(string) (\$sessionPreview['valid_from_utc'] ?? '') !== \$validFromUtc"),
    'every import requires explicit RH confirmation for the complete previewed roster' => str_contains($controller, '!$confirmRoster')
        && str_contains($controller, 'Confirma que revisaste el conjunto completo'),
    'import reuses the locked canonical importer and never creates schedules' => str_contains($controller, 'WorkerWorkplaceAssignmentBaselineImportService())->importFromPreview(')
        && str_contains($controller, "'reason' => \$reason")
        && str_contains($controller, "\$sessionPreview['reason'] ?? ''")
        && !str_contains($controller, 'op_rh_v2_turnos_asignaciones'),
    'the UI action requires RH edit access and an authorized selected station' => str_contains($view, 'canRunChecadorBaseline()')
        && str_contains($view, 'baselineWorkplaceIds')
        && str_contains($view, 'checadorMembershipBaselineModal'),
    'the modal displays the full candidate set, exclusion counts, fingerprints, explicit reason, and UTC cutoff' => str_contains($view, 'baselineEffectiveFromUtc')
        && str_contains($view, 'baselinePreview?.candidate_worker_ids')
        && str_contains($view, 'baselinePreview?.candidate_fingerprint')
        && str_contains($view, 'excluded_candidates_by_reason')
        && str_contains($view, 'baselinePreview?.reason')
        && str_contains($view, 'Motivo RH')
        && str_contains($view, 'Confirmo que revisé el padrón completo'),
    'the existing Control Documentos Alpine/Axios transport posts locality, explicit reason, cutoff, and confirmation' => str_contains($script, "axios.post('/departamento-operativo/recursos-humanos/control-documentos-personal/checador-membership-preview'")
        && str_contains($script, "axios.post('/departamento-operativo/recursos-humanos/control-documentos-personal/checador-membership-import'")
        && str_contains($script, 'valid_from_utc: this.baselineEffectiveFromUtc')
        && str_contains($script, 'reason: this.baselineReasonText.trim()')
        && str_contains($script, 'confirm_roster: this.confirmBaselineRoster')
        && !str_contains($script, '2026-11-06T06:00:00Z')
        && !str_contains($script, 'selected_worker_ids')
        && str_contains($script, 'if (this.baselineBusy')
        && str_contains($script, 'confirmation.isConfirmed'),
];

$failed = 0;
foreach ($checks as $label => $pass) {
    echo ($pass ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$pass) $failed++;
}
echo 'CHECADOR_M03_MEMBERSHIP_BASELINE_ENTRYPOINT: ' . (count($checks) - $failed) . ' PASS / ' . $failed . ' FAIL' . PHP_EOL;
exit($failed === 0 ? 0 : 1);

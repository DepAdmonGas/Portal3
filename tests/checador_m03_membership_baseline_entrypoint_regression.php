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
    'Interlomas keeps the exact approved candidate set and physical-to-canonical reference' => str_contains($controller, 'CHECADOR_BASELINE_LOCALITY_ID = 1')
        && str_contains($controller, 'CHECADOR_BASELINE_EXPECTED_WORKER_IDS')
        && str_contains($controller, "['numlista'] ?? 0) === 2")
        && str_contains($controller, 'candidate_worker_ids'),
    'the approved UTC cutoff is fixed and cannot be overridden by the browser' => str_contains($controller, "CHECADOR_BASELINE_VALID_FROM_UTC = '2026-11-06T06:00:00Z'")
        && str_contains($controller, "\$requestedCutoff !== self::CHECADOR_BASELINE_VALID_FROM_UTC"),
    'other stations require explicit future UTC and the selected station context' => str_contains($controller, 'private function requireSelectedBaselineStation')
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
    'Autolavado selection is limited to current preview candidates and binds full and selected fingerprints' => str_contains($controller, 'CHECADOR_SELECTIVE_BASELINE_LOCALITY_ID = 9')
        && str_contains($controller, 'CHECADOR_SELECTIVE_BASELINE_WORKER_IDS = [310, 512]')
        && substr_count($controller, 'array_diff($selectedWorkerIds, self::CHECADOR_SELECTIVE_BASELINE_WORKER_IDS)') === 2
        && str_contains($controller, "\$candidate['in_review_scope'] = in_array(")
        && str_contains($controller, "'review_workers' => \$reviewWorkers")
        && str_contains($controller, "'membership_status_label' =>")
        && str_contains($previewService, "'active_workers' =>")
        && str_contains($previewService, "'worker_memberships' =>")
        && str_contains($controller, "'selected_worker_ids' => \$requiresWorkerSelection ? \$selectedIds : null")
        && str_contains($controller, "'full_candidate_fingerprint' => \$fullCandidateFingerprint")
        && str_contains($previewService, 'selectCandidateRows')
        && str_contains($importService, 'selectCandidateRows')
        && str_contains($importService, '$expectedFullCandidateFingerprint'),
    'import is bound to the actor session, locality, cutoff, freshness, and both fingerprints' => str_contains($controller, "Session::set(self::CHECADOR_BASELINE_PREVIEW_SESSION_KEY")
        && str_contains($controller, 'time() - (int) ($sessionPreview[\'created_at\'] ?? 0) > 900')
        && str_contains($controller, 'hash_equals((string) ($sessionPreview[\'candidate_fingerprint\'] ?? \'\'), $candidateFingerprint)')
        && str_contains($controller, 'hash_equals((string) ($sessionPreview[\'source_fingerprint\'] ?? \'\'), $sourceFingerprint)')
        && str_contains($controller, "(string) (\$sessionPreview['valid_from_utc'] ?? '') !== \$validFromUtc"),
    'non-Interlomas import requires RH confirmation while Interlomas remains on its preapproved set and cutoff' => str_contains($controller, '!$confirmRoster')
        && str_contains($controller, 'count($expectedIds)')
        && str_contains($controller, 'CHECADOR_BASELINE_VALID_FROM_UTC'),
    'import reuses the locked canonical importer and never creates schedules' => str_contains($controller, 'WorkerWorkplaceAssignmentBaselineImportService())->importFromPreview(')
        && str_contains($controller, "'reason' => \$reason")
        && str_contains($controller, "\$sessionPreview['reason'] ?? ''")
        && !str_contains($controller, 'op_rh_v2_turnos_asignaciones'),
    'the UI action requires RH edit access and an authorized selected station' => str_contains($view, 'canRunChecadorBaseline()')
        && str_contains($view, 'baselineWorkplaceIds')
        && str_contains($view, 'checadorMembershipBaselineModal'),
    'the modal displays the effective UTC cutoff, candidate list and fingerprints before confirmation' => str_contains($view, 'baselineEffectiveFromUtc')
        && str_contains($view, 'baselinePreview?.candidate_worker_ids')
        && str_contains($view, 'baselinePreview?.candidate_fingerprint')
        && str_contains($view, 'baselineSelectedWorkerIds')
        && str_contains($view, ':disabled="!candidate.in_review_scope"')
        && str_contains($view, 'Fuera de esta revisión (sin cambios)')
        && str_contains($view, 'Estado actual de las dos trabajadoras de esta revisión')
        && str_contains($view, 'Motivo RH')
        && str_contains($view, 'selection_effect')
        && str_contains($view, 'Confirmo el conjunto seleccionado'),
    'the existing Control Documentos Alpine/Axios transport posts selected locality, cutoff and confirmation' => str_contains($script, "axios.post('/departamento-operativo/recursos-humanos/control-documentos-personal/checador-membership-preview'")
        && str_contains($script, "axios.post('/departamento-operativo/recursos-humanos/control-documentos-personal/checador-membership-import'")
        && str_contains($script, 'valid_from_utc: this.baselineEffectiveFromUtc')
        && str_contains($script, 'confirm_roster: this.confirmBaselineRoster')
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

<?php

declare(strict_types=1);

$access = file_get_contents(__DIR__ . '/../app/Services/ChecadorAccessService.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$reader = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$view = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$historyView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-history.php');
$templateJs = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js');
$pageScripts = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$checks = [
    'Palo Solo and Autolavado use their canonical locality IDs' => str_contains($access, 'PALO_SOLO_LOCALITY_ID = 2') && str_contains($access, 'AUTOLAVADO_LOCALITY_ID = 9'),
    'M04 read exception requires effective scheduling read and explicit Autolavado department scope' => str_contains($access, 'function authorizeM04TemplateRead') && str_contains($access, 'ChecadorV2PermissionService::SCHEDULE_READ') && str_contains($access, 'in_array(self::AUTOLAVADO_LOCALITY_ID, $departmentIds, true)'),
    'Encargado Autolavado administration still requires Palo Solo home scope and specific template grant' => str_contains($access, 'function authorizeStationTemplateAdministration') && str_contains($access, 'ChecadorV2PermissionService::STATION_TEMPLATE_ADMIN') && str_contains($access, 'in_array(self::PALO_SOLO_LOCALITY_ID, $stationIds, true)'),
    'M04 and M03 preserve an explicitly selected department context without changing M03 physical-station behavior' => str_contains($controller, 'if ($isDepartmentSelected && ($scheduleHistoryMode || $scheduleWeekMode))') && str_contains($controller, 'M03 renders its guarded') && str_contains($controller, 'department-unavailable state') && str_contains($controller, 'department-level read exceptions.'),
    'M04 card uses the specific Autolavado template-read authorization' => str_contains($controller, '$m04TemplateRead') && str_contains($controller, 'authorizeM04TemplateRead($workplaceId)') && str_contains($controller, "'available' => \$m04TemplateRead"),
    'M03 card remains unavailable for departments unless physical scheduling authorization allows it' => str_contains($controller, "'available' => \$scheduleRead") && str_contains($controller, "'La programación semanal no está habilitada para este departamento.'"),
    'disabled cards display their escaped, module-specific availability message' => str_contains($view, '$unavailableText = (string) ($module[\'unavailable\']') && str_contains($view, 'htmlspecialchars($unavailableText, ENT_QUOTES, \'UTF-8\')'),
    'authorized manager receives an empty DataTable shell after a complete empty Autolavado catalog read' => str_contains($historyView, '($scheduleTemplates ?? []) === [] && (empty($canManageStationTemplates) || $catalogStatus !== \'received\')') && str_contains($historyView, 'id="m04-template-table"'),
    'M04 create readback is checked against the selected canonical station' => str_contains($reader, '(string) ($data[\'scope\'] ?? \'\') !== \'STATION\'') && str_contains($reader, '(int) ($readback[\'data\'][\'id_estacion\'] ?? 0) !== $workplaceId'),
    'confirmed turn refresh warning prevents duplicate submission and is visible outside the modal' => str_contains($templateJs, 'No lo guardes de nuevo; actualiza el catálogo') && str_contains($historyView, 'x-show="refreshWarning"'),
    'department M04 does not query assignment history or effective schedule' => str_contains($controller, '$workplaceId !== null && !ChecadorAccessService::isDepartmentLocality($workplaceId)') && str_contains($controller, '!ChecadorAccessService::isDepartmentLocality($workplaceId) && !$demoMode'),
    'Autolavado global-template customization is rejected in Portal3' => str_contains($controller, 'ChecadorAccessService::isDepartmentLocality((int) ($workplaceId ?? 0))'),
    'template creation and version readback preserve the selected canonical locality' => str_contains($reader, 'getShiftTemplateVersion($user, $templateKey, $newVersion, $workplaceId)') && str_contains($reader, 'getShiftTemplateVersion($user, $key, 1, $workplaceId)'),
    'M04 edit request keeps the selected locality and confirms the API version before success' => str_contains($pageScripts, 'createShiftTemplateVersion($user, $key, $expectedVersion') && str_contains($pageScripts, '], $workplaceId);') && str_contains($pageScripts, 'authorizeStationTemplateAdministration($workplaceId)'),
    'current and retry version readbacks both retain Autolavado canonical locality 9' => str_contains($controller, '$reader->getShiftTemplateVersion($user, $key, $expectedVersion, $workplaceId)') && str_contains($controller, '$reader->getShiftTemplateVersion($user, $key, $expectedVersion + 1, $workplaceId)'),
    'edit refresh updates the clicked Autolavado row and reloads the selected catalog only as fallback' => str_contains($templateJs, "this.openEdit(JSON.parse(editLink.dataset.m04EditTemplate || '{}'), editLink.closest('tr'))") && str_contains($templateJs, 'updateTemplateRow(body.template, this.editRowNode)') && str_contains($templateJs, 'window.location.reload()'),
    'M04 requests the current template editor script instead of the stale cached release' => str_contains($pageScripts, 'checador.schedule-history.templates.init.js?v=20261106-1'),
];
$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    $failed += $passed ? 0 : 1;
}
echo 'M04_AUTOLAVADO_SCOPE: ' . (count($checks) - $failed) . '/' . count($checks) . PHP_EOL;
exit($failed === 0 ? 0 : 1);

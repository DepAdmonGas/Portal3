<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php');
$service = file_get_contents($root . '/app/Services/ChecadorDailyReadService.php');
$view = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-history.php');
$js = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js');
$routes = file_get_contents($root . '/routes/web.php');
$checks = [
    'authenticated Portal3 route' => str_contains($routes, "'/recursos-humanos/checador/programacion-semanal/plantillas-locales/personalizar-global', Route::auth(['ChecadorController', 'customizeGlobalScheduleTemplate'])"),
    'write gate and station permission enforced' => str_contains($controller, 'ChecadorDailyReadService::scheduleApiPostOperationsEnabled()') && str_contains($controller, 'authorizeStationTemplateAdministration($workplaceId)'),
    'server confirms exact visible global source' => str_contains($controller, '$visibleSource') && str_contains($controller, '$sourceVersion !==') && str_contains($controller, "'GLOBAL'"),
    'server requires API readback before success' => str_contains($controller, 'customizeGlobalShiftTemplate(') && str_contains($controller, "'template' => \$result['data']"),
    'same delegated API writer used' => str_contains($service, "'api/v2/shift-templates'") && str_contains($service, "'derived_from_global' => ['clave' => \$sourceKey, 'version' => \$sourceVersion]"),
    'UI tells user global source remains unchanged' => str_contains($view, 'El horario global original y las asignaciones actuales permanecerán sin cambios.'),
    'global action and local edit remain distinct' => str_contains($view, 'data-m04-customize-template') && str_contains($view, 'data-m04-edit-template'),
    'Alpine sends through local BFF and reads back result' => str_contains($js, 'this.rootElement.dataset.customizeUrl') && str_contains($js, 'La personalización local'),
    'no direct browser API call added' => !str_contains($js, 'api/v2/shift-templates'),
    'table formatter is reused by personalize/edit modal payloads' => str_contains($view, '$displayTemplateName') && substr_count($view, "'name' => \$displayTemplateName") >= 3,
    'dynamic rows and modals use the same conservative formatter' => str_contains($js, 'cleanTemplateName(value)') && str_contains($js, 'this.editName = this.cleanTemplateName(template.name)') && str_contains($js, 'this.name = this.cleanTemplateName(source.name)') && str_contains($js, 'this.cleanTemplateName(confirmed.nombre)'),
    'actions header is icon-only visually and accessible by name' => str_contains($view, 'data-m04-actions-header aria-label="Acciones"') && str_contains($view, '<span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i>'),
    'DataTables action column is centered, compact, and not sortable/searchable' => str_contains($js, 'targets: actionColumnIndex, orderable: false, searchable: false, className: \'text-center text-nowrap\''),
];
$pass = 0;
foreach ($checks as $label => $ok) { echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . PHP_EOL; $pass += $ok ? 1 : 0; }
echo 'M04_GLOBAL_TEMPLATE_CUSTOMIZATION_REGRESSION: ' . $pass . '/' . count($checks) . PHP_EOL;
exit($pass === count($checks) ? 0 : 1);

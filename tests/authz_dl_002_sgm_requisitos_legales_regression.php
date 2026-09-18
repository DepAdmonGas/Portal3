<?php
declare(strict_types=1);

function assert_sgm(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }

$controller = file_get_contents(__DIR__ . '/../app/Controllers/SgmNormatividadController.php');
$route = file_get_contents(__DIR__ . '/../routes/web.php');
$view = file_get_contents(__DIR__ . '/../app/Views/sgm/normatividad/requisito-legal.php');
$js = file_get_contents(__DIR__ . '/../public/assets/js/requisitoslegales/detalle.datatable.init.js');
$docs = file_get_contents(__DIR__ . '/../app/Controllers/DocumentosRegistrosController.php');
$eval = file_get_contents(__DIR__ . '/../app/Controllers/EvaluacionRequisitosLegalesController.php');

$tests = [
    'SGM route is module-specific' => str_contains($route, '/normatividad-aplicable-mediciones/requisitos-legales/download'),
    'SGM action checks sgm permission first' => str_contains($controller, "validaPermiso('sgm', 'descargar')"),
    'SGM action derives matrix by matrix_id' => str_contains($controller, "filter_input(INPUT_GET, 'matrix_id', FILTER_VALIDATE_INT)") && str_contains($controller, 'RequisitosLegalesMatriz::with'),
    'SGM action restricts variants' => str_contains($controller, "['acuse', 'requisito']"),
    'SGM action selects persisted columns' => str_contains($controller, '$matrix->acusepdf') && str_contains($controller, '$matrix->requisitolegalpdf'),
    'SGM action checks station context' => str_contains($controller, "getContext('sgm')") && str_contains($controller, '$matrix?->calendario?->id_estacion'),
    'SGM action rejects client module authority' => !str_contains($controller, "\$_GET['module']"),
    'SGM view no longer calls generic requisitos download' => !str_contains($view, "download('requisitos-legales'"),
    'SGM datatable uses canonical matrix and variant' => str_contains($js, 'matrix_id=${row.id}') && str_contains($js, 'variant=acuse') && str_contains($js, 'variant=requisito'),
    'generic route remains fail-closed for unresolved type' => str_contains(file_get_contents(__DIR__ . '/../app/Services/SensitiveDownloadAuthorizationService.php'), 'PERSONAL_DOCUMENTS'),
    'alternate PDF route 2 enforces SASISOPA download permission' => str_contains($docs, "validaPermiso('sasisopa', 'descargar')"),
    'alternate PDF route 3 enforces SASISOPA download permission' => str_contains($eval, "validaPermiso('sasisopa', 'descargar')"),
];
foreach ($tests as $name => $ok) { assert_sgm($ok, $name); echo "PASS {$name}\n"; }
printf("RESULT: %d PASS / 0 FAIL / 0 SKIPPED\n", count($tests));

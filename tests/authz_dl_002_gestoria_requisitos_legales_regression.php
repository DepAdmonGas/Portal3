<?php
declare(strict_types=1);
function gestoria_dl_assert(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$controller = file_get_contents(__DIR__ . '/../app/Controllers/GestoriaPermisosController.php');
$route = file_get_contents(__DIR__ . '/../routes/web.php');
$view = file_get_contents(__DIR__ . '/../app/Views/gestoria/permisos.php');
$generic = file_get_contents(__DIR__ . '/../app/Services/SensitiveDownloadAuthorizationService.php');
$tests = [
    'Gestoria canonical route exists' => str_contains($route, '/permisos/requisitos-legales/download'),
    'Gestoria download permission is checked' => str_contains($controller, "validaPermiso('gestoria', 'descargar')"),
    'server-side station context is used' => str_contains($controller, '$this->estacionId()'),
    'canonical matrix resource is loaded' => str_contains($controller, 'RequisitosLegalesMatriz::with') && str_contains($controller, "filter_input(INPUT_GET, 'matrix_id', FILTER_VALIDATE_INT)"),
    'variants are strict' => str_contains($controller, "['acuse', 'requisito']"),
    'persisted reference is selected' => str_contains($controller, '$matrix->acusepdf') && str_contains($controller, '$matrix->requisitolegalpdf'),
    'central storage resolver is reused' => str_contains($controller, 'RequisitosLegalesStorageService::resolveReadablePath'),
    'view no longer calls generic requisitos download' => !str_contains($view, "download('requisitos-legales'"),
    'view sends matrix id and variant' => str_contains($view, 'matrix_id=') && str_contains($view, 'variant=acuse') && str_contains($view, 'variant=requisito'),
    'generic requisitos remains default deny' => str_contains($generic, 'PERSONAL_DOCUMENTS'),
    'client filename is not authority' => !str_contains($controller, "\$_GET['file']"),
    'client station is not authority' => !str_contains($controller, "\$_GET['id_estacion']"),
];
foreach ($tests as $name => $ok) { gestoria_dl_assert($ok, $name); echo "PASS {$name}\n"; }
printf("RESULT: %d PASS / 0 FAIL / 0 SKIPPED\n", count($tests));

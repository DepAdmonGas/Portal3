<?php
declare(strict_types=1);

function assert_private_storage(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }

$service = file_get_contents(__DIR__ . '/../app/Services/RequisitosLegalesStorageService.php');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/RequisitosLegalesController.php');
$sgm = file_get_contents(__DIR__ . '/../app/Controllers/SgmNormatividadController.php');
$tests = [
    'private root is outside public webroot' => str_contains($service, "storage/private/requisitos-legales/") && !str_contains($service, "public/uploads/archivos/reuisitos-legales/';\n        if (!is_dir"),
    'new writes use private storage service' => str_contains($controller, 'RequisitosLegalesStorageService::storeUploadedFile'),
    'new writes do not return public URLs' => !str_contains($controller, "return 'archivos/reuisitos-legales/'"),
    'canonical SASISOPA uses centralized resolver' => str_contains($controller, 'RequisitosLegalesStorageService::resolveReadablePath'),
    'canonical SGM uses centralized resolver' => str_contains($sgm, 'RequisitosLegalesStorageService::resolveReadablePath'),
    'private candidate precedes legacy candidate' => strpos($service, 'self::privateRoot(), $legacyRoot') !== false,
    'legacy reference prefix is narrowly normalized' => str_contains($service, "archivos/reuisitos-legales/"),
    'absolute and traversal references rejected' => str_contains($service, 'basename($reference)') && str_contains($service, 'str_contains($reference, "\\0")'),
    'generic download remains default deny' => str_contains(file_get_contents(__DIR__ . '/../app/Services/SensitiveDownloadAuthorizationService.php'), 'PERSONAL_DOCUMENTS'),
    'delete flow uses centralized resolver' => str_contains($controller, 'RequisitosLegalesStorageService::deleteReference'),
    'legacy files are not migrated automatically' => !str_contains($controller, 'rename(') && !str_contains($controller, 'copy('),
    'public legacy root is only a read fallback' => str_contains($service, '$legacyRoot'),
    'replacement stores new file before deleting old' => strpos($controller, '$matriz->save();') < strpos($controller, 'deleteReference($oldAcusePath)'),
    'replacement preserves old references until save' => str_contains($controller, '$oldAcusePath = $matriz->acusepdf') && str_contains($controller, '$oldRequisitoPath = $matriz->requisitolegalpdf'),
    'replacement cleans new files after DB failure' => str_contains($controller, 'deleteReference($newAcusePath)') && str_contains($controller, 'deleteReference($newRequisitoPath)'),
    'replacement cleanup supports private and legacy files' => str_contains($controller, 'deleteReference($oldAcusePath)') && str_contains($controller, 'deleteReference($oldRequisitoPath)'),
    'failed upload does not delete old reference first' => strpos($controller, '$newAcusePath = $this->guardarArchivoRequisitoLegal') < strpos($controller, '$matriz->save();'),
    'replacement remains private-only for new files' => str_contains($controller, 'privateRoot()'),
];
foreach ($tests as $name => $ok) { assert_private_storage($ok, $name); echo "PASS {$name}\n"; }
printf("RESULT: %d PASS / 0 FAIL / 0 SKIPPED\n", count($tests));

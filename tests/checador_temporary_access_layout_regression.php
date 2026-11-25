<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
\App\Core\Session::init();
$temporaryAccessPanel = ['state'=>'success','requests'=>[],'can_edit'=>false];
$selectedWorkplaceName = 'Interlomas';
$selectedWorkplaceId = 1;
$demoMode = false;
$realApiPostOperationsEnabled = false;
ob_start();
include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php';
$html = (string) ob_get_clean();
$assert = static function(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); echo ($ok?'PASS ':'FAIL ').$message.PHP_EOL; };
$assert(str_contains($html, 'Solicitudes de acceso temporal') && str_contains($html, 'Volver al Checador'), 'attendance PIN workspace retains its existing heading and return navigation');
$assert(str_contains($html, 'No hay solicitudes pendientes para esta estación.') && !str_contains($html, 'PIN de configuración'), 'successful empty attendance queue is retained and administrative PIN is absent');
$assert(!str_contains($html, 'admin-pin') && !str_contains($html, 'Historial de autorizaciones') && !str_contains($html, 'configuration-pin-title'), 'administrative PIN forms, cards and history no longer live in temporary access');
echo "RESULT: 3 PASS / 0 FAIL".PHP_EOL;

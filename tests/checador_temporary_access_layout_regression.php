<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
\App\Core\Session::init();
$temporaryAccessPanel = ['state'=>'success','requests'=>[],'can_edit'=>false];
$selectedWorkplaceName = 'Interlomas';
$selectedWorkplaceId = 1;
$demoMode = false;
$realApiPostOperationsEnabled = false;
$administrativePinPanel = ['ok'=>true, 'state'=>'ready', 'can_revoke'=>true, 'devices'=>[], 'history'=>[[
    'issued_at_utc'=>'2026-11-25 12:00:00', 'actor_name'=>'Encargado', 'device_name'=>'Lector',
    'expires_at_utc'=>'2026-11-25 12:05:00', 'session_state'=>null, 'state'=>'PENDING',
    'authorization_id'=>'12345678-1234-4234-8234-123456789abc',
]]];
ob_start();
include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php';
$html = (string) ob_get_clean();
$assert = static function(bool $ok,string $message):void { if(!$ok)throw new RuntimeException($message); echo ($ok?'PASS ':'FAIL ').$message.PHP_EOL; };
$assert(str_contains($html, 'Solicitudes de acceso temporal') && str_contains($html, 'Volver al Checador'), 'attendance PIN workspace retains its existing heading and return navigation');
$assert(str_contains($html, 'PIN de acceso del personal')
    && str_contains($html, 'Administrar PIN')
    && str_contains($html, 'Pendientes:')
    && str_contains($html, 'data-bs-target="#personnel-pin-modal"'), 'attendance PIN card and request navigation are available again');
$assert(str_contains($html, 'No hay solicitudes pendientes para esta estación.') && str_contains($html, 'PIN de configuración'), 'successful empty attendance queue and configuration PIN card are retained');
$assert(str_contains($html, 'configuration-pin-heading') && str_contains($html, 'personnel-pin-title'), 'both PIN cards use existing components');
$assert(str_contains($html, 'id="pin-history"') && str_contains($html, 'id="pin-history-heading"')
    && str_contains($html, 'Historial de autorizaciones') && !str_contains($html, 'pin-history-accordion')
    && !str_contains($html, 'data-bs-target="#pin-history"') && !str_contains($html, 'Historial de PIN de configuración')
    && str_contains($html, 'Lector') && str_contains($html, '/admin-pin/12345678-1234-4234-8234-123456789abc/revoke'),
    'history is always visible with its record and revocation action, without an accordion or duplicated heading');
echo "RESULT: 5 PASS / 0 FAIL".PHP_EOL;

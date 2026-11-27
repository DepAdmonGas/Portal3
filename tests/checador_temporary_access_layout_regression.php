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
$assert(str_contains($html, 'Solicitudes de acceso temporal') && !str_contains($html, 'Volver al Checador'), 'attendance PIN workspace retains its heading without redundant return button');
$assert(str_contains($html, 'PIN de acceso del personal')
    && str_contains($html, 'Administrar PIN')
    && str_contains($html, 'Solicitudes:')
    && str_contains($html, 'data-bs-target="#personnel-pin-modal"'), 'attendance PIN card and request navigation are available again');
$assert(str_contains($html, 'No se devolvieron solicitudes para esta estación.') && str_contains($html, 'PIN de configuración'), 'successful empty API queue and configuration PIN card are retained');
$assert(str_contains($html, 'configuration-pin-heading') && str_contains($html, 'personnel-pin-title'), 'both PIN cards use existing components');
$assert(str_contains($html, 'id="pin-history"') && str_contains($html, 'id="pin-history-heading"')
    && str_contains($html, 'Historial de autorizaciones') && !str_contains($html, 'pin-history-accordion')
    && !str_contains($html, 'data-bs-target="#pin-history"') && !str_contains($html, 'Historial de PIN de configuración')
    && str_contains($html, 'Lector') && str_contains($html, '/admin-pin/12345678-1234-4234-8234-123456789abc/revoke'),
    'history is always visible with its record and revocation action, without an accordion or duplicated heading');
$requestRow = [
    'request_uuid'=>'12345678-1234-4234-8234-123456789abc', 'id_personal_resuelto'=>565,
    'nombre_trabajador'=>'Trabajador de prueba', 'id_estacion'=>1, 'dispositivo_nombre'=>'Lector Interlomas',
    'reason'=>'FINGERPRINT_NOT_RECOGNIZED', 'requested_at_utc'=>'2026-11-26 16:00:00',
    'request_expires_at_utc'=>'2026-11-26 16:30:00',
];
$temporaryAccessPanel = ['state'=>'success','can_edit'=>false,'requests'=>[$requestRow]];
$temporaryAccessPostOperationsEnabled = false;
$selectedDate = '2026-11-26';
ob_start();
include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php';
$queueHtml = (string) ob_get_clean();
$assert(str_contains($queueHtml, 'Fecha de solicitud (UTC)')
    && str_contains($queueHtml, 'id="temporary-access-requests-table"')
    && str_contains($queueHtml, 'Vencimiento de solicitud (UTC)')
    && str_contains($queueHtml, 'Trabajador de prueba')
    && str_contains($queueHtml, 'ID RH: 565')
    && !str_contains($queueHtml, 'Estado informado')
    && !str_contains($queueHtml, 'No incluido en esta lectura'),
    'queue displays only contract-returned request fields and does not present an invented or unavailable status');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$temporaryAccessScriptBlock = substr($controllerSource, strpos($controllerSource, "'scripts' => [", strpos($controllerSource, "'temporaryAccessPanel' =>")));
$assert(str_contains($temporaryAccessScriptBlock, '/assets/libs/datatables.net/js/jquery.dataTables.min.js')
    && str_contains($temporaryAccessScriptBlock, '/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js')
    && str_contains($temporaryAccessScriptBlock, '/assets/js/departamento-operativo/2-recursos-humanos/checador.temporary-access.init.js?v=20261126-1'),
    'M08 loads the existing local DataTables 1.13.5 assets before its initializer');
$temporaryAccessPanel['can_edit'] = true;
$temporaryAccessPostOperationsEnabled = true;
$temporaryAccessPanel['cancelable_uuid'] = $requestRow['request_uuid'];
ob_start();
include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php';
$actionHtml = (string) ob_get_clean();
$assert(substr_count($actionHtml, 'data-bs-toggle="dropdown"') === 1
    && str_contains($actionHtml, 'data-bs-auto-close="outside"')
    && str_contains($actionHtml, 'ti-dots-vertical')
    && str_contains($actionHtml, '/12345678-1234-4234-8234-123456789abc/authorize')
    && str_contains($actionHtml, '/12345678-1234-4234-8234-123456789abc/reject')
    && str_contains($actionHtml, '/12345678-1234-4234-8234-123456789abc/cancel'),
    'authorized request actions use one institutional dropdown and retain the existing three POST operations');
$assert(substr_count($actionHtml, 'name="_csrf_token"') >= 3
    && substr_count($actionHtml, 'name="confirm_action"') >= 3
    && str_contains($actionHtml, 'name="workplace_id" value="1"'),
    'request action dropdown retains CSRF, explicit confirmation and selected station fields');
echo "RESULT: 9 PASS / 0 FAIL".PHP_EOL;

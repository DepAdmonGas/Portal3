<?php

declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
\App\Core\Session::init();
use App\Services\ChecadorV2PermissionService;

$renderTemporary = static function (array $panel): string {
    $temporaryAccessPanel = $panel;
    $selectedWorkplaceName = 'Esmegas <Centro>';
    $selectedWorkplaceId = 6;
    $demoMode = false;
    $realApiPostOperationsEnabled = false;
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php';
    return (string) ob_get_clean();
};
$renderAdmin = static function (array $adminPanel, ?array $adminFeedback = null): string {
    $administrativePinPanel = $adminPanel;
    $administrativePinFeedback = $adminFeedback;
    $selectedWorkplaceId = 6;
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-administrative-pin.php';
    return (string) ob_get_clean();
};
$assert = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
    echo "PASS {$message}\n";
};
$request = ['request_uuid'=>'12345678-1234-4234-8234-123456789abc', 'nombre_trabajador'=>'Trabajador <Prueba>', 'dispositivo_nombre'=>'Lector', 'reason'=>'FINGERPRINT_NOT_RECOGNIZED', 'requested_at_utc'=>'2026-11-25 12:00:00', 'request_expires_at_utc'=>'2026-11-25 12:30:00'];
$queue = $renderTemporary(['state'=>'success', 'requests'=>[$request], 'can_edit'=>true]);
$assert(str_contains($queue, '15 minutos · Un solo uso'), 'configuration PIN card displays the fifteen-minute validity');
$assert(str_contains($queue, 'Solicitudes de acceso temporal') && str_contains($queue, 'Trabajador &lt;Prueba&gt;')
    && str_contains($queue, 'Vencimiento de solicitud (UTC)') && !str_contains($queue, 'No incluido en esta lectura'), 'attendance PIN request workspace renders the contract-backed queue fields without an invented status');
$assert(str_contains($queue, 'PIN de configuración') && str_contains($queue, 'PIN de acceso del personal'), 'both PIN cards appear in the temporary access workspace');
$emptyQueue = $renderTemporary(['state'=>'success', 'requests'=>[]]);
$assert(str_contains($emptyQueue, 'No se devolvieron solicitudes para esta estación.'), 'successful empty attendance request queue remains distinct');
$errorQueue = $renderTemporary(['state'=>'error', 'message'=>'Error de consulta', 'requests'=>[]]);
$assert(str_contains($errorQueue, 'Error de consulta') && !str_contains($errorQueue, 'No se devolvieron solicitudes para esta estación.'), 'attendance PIN read failure remains visible and is not represented as empty');

$devices = [['device_id'=>2,'device_name'=>'Lector local','device_active'=>true,'credential_active'=>true,'biometric_grant_active'=>true,'pending_pin'=>null,'session'=>null]];
$ready = $renderAdmin(['ok'=>true,'state'=>'ready','can_issue'=>true,'can_revoke'=>true,'secret_configured'=>true,'devices'=>$devices,'history'=>[]]);
$assert(str_contains($ready, 'PIN de configuración') && str_contains($ready, 'action="/departamento-operativo/recursos-humanos/checador/configuracion/admin-pin"') && str_contains($ready, 'name="_csrf_token"') && str_contains($ready, 'name="confirm_action"') && str_contains($ready, 'value="2"'), 'authorized configuration view renders scoped CSRF-protected and confirmed SQL issue form');
$pending = $renderAdmin(['ok'=>true,'state'=>'ready','can_issue'=>false,'can_revoke'=>true,'secret_configured'=>true,'devices'=>[['device_id'=>2,'device_name'=>'Lector local','device_active'=>true,'credential_active'=>true,'biometric_grant_active'=>true,'pending_pin'=>['authorization_id'=>'12345678-1234-4234-8234-123456789abc','expires_at_utc'=>'2026-11-25 12:05:00'],'session'=>null]],'history'=>[]]);
$assert(str_contains($pending, '/configuracion/admin-pin/12345678-1234-4234-8234-123456789abc/revoke') && str_contains($pending, 'Confirmo revocar el PIN pendiente.'), 'pending PIN revocation returns through the Checador configuration route');
$activeSession = $renderAdmin(['ok'=>true,'state'=>'ready','can_issue'=>false,'can_revoke'=>true,'secret_configured'=>true,'devices'=>[['device_id'=>2,'device_name'=>'Lector local','device_active'=>true,'credential_active'=>true,'biometric_grant_active'=>true,'pending_pin'=>null,'session'=>['session_id'=>'12345678-1234-4234-8234-123456789abc','state'=>'ACTIVE','expires_at_utc'=>'2026-11-25 12:05:00']]],'history'=>[]]);
$assert(str_contains($activeSession, '/configuracion/admin-session/12345678-1234-4234-8234-123456789abc/revoke'), 'administrative session revocation uses the configuration route');
$readOnly = $renderAdmin(['ok'=>true,'state'=>'ready','can_issue'=>false,'can_revoke'=>false,'secret_configured'=>true,'devices'=>$devices,'history'=>[]]);
$assert(str_contains($readOnly, 'La emisión requiere permisos de edición') && !str_contains($readOnly, 'method="post"'), 'read-only profiles cannot issue or revoke administrative PINs');
$unavailable = $renderAdmin(['ok'=>false,'state'=>'unavailable','can_issue'=>false,'devices'=>[],'history'=>[]]);
$assert(str_contains($unavailable, 'No fue posible consultar el estado') && str_contains($unavailable, 'El historial no está disponible'), 'SQL unavailability is distinguished from successful empty history');
$historyEmpty = $renderAdmin(['ok'=>true,'state'=>'ready','can_issue'=>false,'can_revoke'=>false,'secret_configured'=>false,'devices'=>[],'history'=>[]]);
$assert(str_contains($historyEmpty, 'No hay autorizaciones de configuración registradas.'), 'successful empty admin history has a neutral empty state');
$flash = $renderAdmin(['ok'=>true,'state'=>'ready','can_issue'=>false,'can_revoke'=>false,'secret_configured'=>true,'devices'=>[],'history'=>[]], ['type'=>'success','message'=>'PIN generated','pin'=>'012345','expires_at_utc'=>'2026-11-25 12:05:00.000000','device_name'=>'Lector local']);
$assert(str_contains($flash, '<code>012345</code>') && str_contains($flash, 'No volverá a mostrarse.'), 'one-time administrative PIN is shown only in the scoped post/redirect feedback');
$denied = $renderAdmin(['ok'=>false,'state'=>'denied','can_issue'=>false,'devices'=>[],'history'=>[]]);
$assert(trim($denied) === '', 'administrative PIN panel is hidden when Checador role or station authorization is denied');

$service = file_get_contents(__DIR__ . '/../app/Services/ChecadorAdministrativePinSqlService.php') ?: '';
$assert(str_contains($service, 'self::plusSeconds($now,900)')
    && str_contains($service, "self::plusSeconds((string)\$readback->issued_at_utc,900)"),
    'SQL issuance and readback require a fifteen-minute PIN lifetime');
$temporaryView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php') ?: '';
$assert(str_contains($temporaryView, 'Vigencia: quince minutos. Un solo uso.'), 'configuration PIN modal displays the fifteen-minute validity');
$limitLock = strpos($service, "\$phase='limiter_lock'");
$pinLock = strpos($service, "\$phase='pending_pin_lock'");
$sessionLock = strpos($service, "\$phase='session_lock'");
$assert($limitLock !== false && $pinLock !== false && $sessionLock !== false && $limitLock < $pinLock && $pinLock < $sessionLock, 'issuance retains the device, limiter, PIN and session lock order');
$assert(str_contains($service, 'insertOrIgnore([') && str_contains($service, "'failed_attempts'=>0") && !str_contains($service, "LIMIT_TABLE)->where('device_id',\$deviceId)->update"), 'issuance creates a missing limiter without resetting existing attempts');
$assert(!str_contains($service, 'DEVICE_BIOMETRIC_ADMIN'), 'administrative PIN permissions stay separate from biometric administration');
$assert(str_contains($service, 'session_last_activity_at_utc') && str_contains($service, "\$sessionState==='ACTIVE'"), 'history derives session expiry from absolute and idle limits');
$assert(ChecadorV2PermissionService::effectiveGrantDecision(['active'=>1,'granted'=>0],['active'=>1,'granted'=>1]) === false, 'individual denial continues to override position capability');
$assert(ChecadorV2PermissionService::effectiveGrantDecision(null,['active'=>1,'granted'=>1]) === true, 'position capability applies when no individual decision exists');
$assert(str_contains($service, 'ChecadorAccessService::authorize($workplaceId)') && str_contains($service, 'station_workplaces') && str_contains($service, "authorizeTemporaryAccess(\$workplaceId, 'editar')"), 'administrative PIN authorization inherits Checador edit and locality ACL');
$configView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/configuration.php') ?: '';
$assert(str_contains($configView, "require __DIR__ . '/workspace-administrative-pin.php'") && strpos($configView, "id=\"m11-reader-link\"") < strpos($configView, "require __DIR__ . '/workspace-administrative-pin.php'"), 'configuration renders the administrative PIN panel immediately alongside device linking');
$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$assert(str_contains($controller, "'administrativePinPanel' => \$administrativePinPanel") && str_contains($controller, "readForWorkplace(\$user, \$workplaceId)") && str_contains($controller, "'/departamento-operativo/recursos-humanos/checador/configuracion'") && str_contains($controller, "readForWorkplace(\$currentUser, \$requestedWorkplace)"), 'both pages load the SQL panel');
$routes = file_get_contents(__DIR__ . '/../routes/web.php') ?: '';
$assert(str_contains($routes, '/recursos-humanos/checador/configuracion/admin-pin') && !str_contains($routes, '/recursos-humanos/checador/acceso-temporal/admin-pin'), 'administrative PIN mutations are routed only through Checador configuration');

echo "RESULT: 14 PASS / 0 FAIL\n";

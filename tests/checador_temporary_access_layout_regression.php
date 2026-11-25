<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
\App\Core\Session::init();
$render = static function (array $panel, array $adminPanel = [], ?array $adminFeedback = null): string {
    $temporaryAccessPanel = $panel;
    $administrativePinPanel = $adminPanel;
    $administrativePinFeedback = $adminFeedback;
    $selectedWorkplaceName = 'Esmegas <Centro>';
    $selectedWorkplaceId = 6;
    $demoMode = false;
    $realApiPostOperationsEnabled = false;
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php';
    return (string) ob_get_clean();
};
$assert = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); echo "PASS {$message}\n"; };
$request = ['request_uuid'=>'12345678-1234-4234-8234-123456789abc', 'nombre_trabajador'=>'Trabajador <Prueba>', 'dispositivo_nombre'=>'Lector', 'reason'=>'FINGERPRINT_NOT_RECOGNIZED', 'requested_at_utc'=>'2026-11-25 12:00:00', 'request_expires_at_utc'=>'2026-11-25 12:30:00'];
$view = $render(['state'=>'success', 'requests'=>[$request], 'can_edit'=>true]);
$assert(str_contains($view, 'PIN de acceso del personal') && str_contains($view, 'PIN de configuración') && str_contains($view, 'row row-cols-1 row-cols-md-2 g-3') && substr_count($view, 'card bg-body border rounded-3 shadow-sm card-hover h-100 mb-0') === 2, 'two responsive institutional cards render');
$assert(str_contains($view, 'Esmegas &lt;Centro&gt;') && str_contains($view, 'Trabajador &lt;Prueba&gt;') && str_contains($view, 'Solicitudes pendientes: <strong>1</strong>'), 'real projection is escaped and counted');
$assert(str_contains($view, 'href="#temporary-access-requests"') && str_contains($view, 'Volver al Checador') && !str_contains($view, 'Volver a asistencia diaria'), 'navigation targets the queue and Checador');
$assert(str_contains($view, 'Historial de autorizaciones') && str_contains($view, 'historial de PIN de configuración no está disponible') && substr_count($view, '<table') === 1 && !str_contains($view, '<code>12345678-1234-4234-8234-123456789abc</code>'), 'admin history distinguishes unavailable from empty and the request UUID is not rendered as data');
$assert((bool) preg_match('/<button[^>]*disabled[^>]*>\s*<i[^>]*><\/i>Generar PIN de configuración/s', $view) && !str_contains($view, '<form'), 'configuration generation and request writes remain closed');
$empty = $render(['state'=>'success', 'requests'=>[]]);
$assert(str_contains($empty, 'Solicitudes pendientes: <strong>0</strong>') && str_contains($empty, 'text-body-secondary text-center py-3 mb-0') && str_contains($empty, 'No hay solicitudes pendientes para esta estación.'), 'successful empty queue uses the institutional empty state');
$error = $render(['state'=>'error', 'message'=>'Error de consulta', 'requests'=>[]]);
$assert(str_contains($error, 'Error de consulta') && str_contains($error, 'Solicitudes pendientes: no disponibles') && !str_contains($error, 'Solicitudes pendientes: <strong>0</strong>'), 'failed queue is distinct from empty');
$adminReady = $render(['state'=>'success','requests'=>[]], [
    'ok'=>true,'state'=>'ready','can_issue'=>true,'can_revoke'=>true,'secret_configured'=>true,
    'devices'=>[['device_id'=>2,'device_name'=>'Lector local','device_active'=>true,'credential_active'=>true,'biometric_grant_active'=>true,'pending_pin'=>null,'session'=>null]],
    'history'=>[],
]);
$assert(str_contains($adminReady,'action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/admin-pin"') && str_contains($adminReady,'name="_csrf_token"') && str_contains($adminReady,'name="confirm_action"') && str_contains($adminReady,'value="2"'), 'ready authorized station renders a scoped, CSRF-protected confirmed issue form');
$adminUnavailable = $render(['state'=>'success','requests'=>[]], ['ok'=>false,'can_issue'=>false,'devices'=>[],'history'=>[]]);
$assert(!str_contains($adminUnavailable,'admin-pin"') && str_contains($adminUnavailable,'La emisión no está disponible'), 'missing schema or secret keeps issuance unavailable');
$adminReadOnly = $render(['state'=>'success','requests'=>[]], ['ok'=>true,'can_issue'=>false,'can_revoke'=>false,'secret_configured'=>true,'devices'=>[],'history'=>[]]);
$assert(str_contains($adminReadOnly,'La cuenta tiene acceso de consulta') && !str_contains($adminReadOnly,'action="/departamento-operativo/recursos-humanos/checador/acceso-temporal/admin-pin"'), 'read-only profiles cannot issue or revoke configuration PINs');
$flash = $render(['state'=>'success','requests'=>[]], [], ['type'=>'success','message'=>'PIN generated','pin'=>'012345','expires_at_utc'=>'2026-11-25 12:05:00.000000','device_name'=>'Lector local']);
$assert(str_contains($flash,'<code>012345</code>') && str_contains($flash,'no volverá a mostrarse') === false, 'one-time PIN feedback is rendered without persisting it in history');
echo "RESULT: 11 PASS / 0 FAIL\n";

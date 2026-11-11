<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ChecadorController;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) {
        echo "PASS {$message}\n";
        $passes++;
    } else {
        echo "FAIL {$message}\n";
        $fails++;
    }
};

$controller = new ReflectionClass(ChecadorController::class);
$invoke = static function (string $name, mixed ...$arguments) use ($controller): mixed {
    $method = $controller->getMethod($name);
    $method->setAccessible(true);
    return $method->invoke(null, ...$arguments);
};

$uuid = 'c71e8400-e29b-41d4-a716-446655440001';
$pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->modify('+10 minutes')
    ->format('Y-m-d H:i:s');
$feedback = [
    'user_id' => 19,
    'workplace_id' => 1,
    'request_uuid' => $uuid,
    'action' => 'authorize',
    'type' => 'success',
    'message' => 'Operación de prueba completada.',
    'temporary_pin' => $pin,
    'pin_expires_at_utc' => $expiresAt,
];

$_SESSION = ['checador_temporary_access_feedback' => $feedback];
$m07State = $invoke('temporaryAccessFeedbackForWorkspace', 'temporary-access', ['request_uuid' => $uuid], 19, 1, true);
$assert(($m07State['feedback']['temporary_pin'] ?? null) === $pin
    && is_string($m07State['consume_hash'] ?? null),
    'M07 presents only a live, authorized, operation-bound synthetic PIN');

$invoke('discardExpiredTemporaryAccessPinFeedback');
$otherWorkspaceState = $invoke('temporaryAccessFeedbackForWorkspace', 'daily-attendance', ['request_uuid' => $uuid], 19, 1, true);
$assert(($otherWorkspaceState['feedback'] ?? null) === null
    && isset($_SESSION['checador_temporary_access_feedback']),
    'Other workspaces neither expose nor consume live PIN feedback');

$htmlPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php';
$render = static function (array $presentedFeedback) use ($htmlPath): string {
    $temporaryAccessFeedback = $presentedFeedback;
    $temporaryAccessPanel = ['state' => 'error', 'message' => 'La cola sintética no se consultó.'];
    $canViewTemporaryPin = true;
    $demoMode = false;
    $temporaryAccessPostOperationsEnabled = true;
    $selectedWorkplaceName = 'Escenario aislado';
    $selectedWorkplaceId = 1;
    $selectedDate = '2026-10-28';
    $selectedWorkerId = null;
    ob_start();
    require $htmlPath;
    return (string) ob_get_clean();
};
$renderedHtml = $render($m07State['feedback']);
$assert(str_contains($renderedHtml, htmlspecialchars($pin, ENT_QUOTES, 'UTF-8'))
    && !str_contains($renderedHtml, 'api/v2/temporary-access'),
    'Isolated M07 view renders the synthetic PIN as escaped text without browser API calls');

$invoke('consumeTemporaryAccessFeedbackAfterRender', $m07State['consume_hash']);
$reloadState = $invoke('temporaryAccessFeedbackForWorkspace', 'temporary-access', ['request_uuid' => $uuid], 19, 1, true);
$assert(!isset($_SESSION['checador_temporary_access_feedback'])
    && ($reloadState['feedback'] ?? null) === null,
    'Successful render consumes the exact feedback and reload does not disclose it again');

$_SESSION = ['checador_temporary_access_feedback' => $feedback];
$wrongOperation = $invoke('temporaryAccessFeedbackForWorkspace', 'temporary-access', ['request_uuid' => 'c71e8400-e29b-41d4-a716-446655440002'], 19, 1, true);
$wrongUser = $invoke('temporaryAccessFeedbackForWorkspace', 'temporary-access', ['request_uuid' => $uuid], 20, 1, true);
$wrongWorkplace = $invoke('temporaryAccessFeedbackForWorkspace', 'temporary-access', ['request_uuid' => $uuid], 19, 2, true);
$assert(($wrongOperation['feedback'] ?? null) === null
    && ($wrongUser['feedback'] ?? null) === null
    && ($wrongWorkplace['feedback'] ?? null) === null
    && isset($_SESSION['checador_temporary_access_feedback']),
    'Different operation, user/session identity, or workplace cannot recover or consume feedback');

$savedSession = $_SESSION;
$_SESSION = [];
$differentSession = $invoke('temporaryAccessFeedbackForWorkspace', 'temporary-access', ['request_uuid' => $uuid], 19, 1, true);
$assert(($differentSession['feedback'] ?? null) === null,
    'A separate PHP session cannot recover the synthetic feedback');
$_SESSION = $savedSession;

$deniedFeedback = $feedback;
$deniedFeedback['type'] = 'error';
$deniedFeedback['message'] = 'La API no autorizó la operación.';
unset($deniedFeedback['temporary_pin'], $deniedFeedback['pin_expires_at_utc']);
$_SESSION = ['checador_temporary_access_feedback' => $deniedFeedback];
$deniedState = $invoke('temporaryAccessFeedbackForWorkspace', 'temporary-access', ['request_uuid' => $uuid], 19, 1, false);
$assert(($deniedState['feedback']['message'] ?? null) === $deniedFeedback['message']
    && !array_key_exists('temporary_pin', $deniedState['feedback'] ?? [])
    && is_string($deniedState['consume_hash'] ?? null),
    'Authorization failure renders only sanitized error feedback and cannot disclose a PIN');

$expiredFeedback = $feedback;
$expiredFeedback['pin_expires_at_utc'] = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->modify('-1 minute')
    ->format('Y-m-d H:i:s');
$_SESSION = ['checador_temporary_access_feedback' => $expiredFeedback];
$expiredState = $invoke('temporaryAccessFeedbackForWorkspace', 'temporary-access', ['request_uuid' => $uuid], 19, 1, true);
$assert(($expiredState['feedback'] ?? null) === null
    && is_string($expiredState['consume_hash'] ?? null),
    'Expired synthetic PIN is never presented and can be removed after the M07 render');
$invoke('consumeTemporaryAccessFeedbackAfterRender', $expiredState['consume_hash']);
$assert(!isset($_SESSION['checador_temporary_access_feedback']),
    'Expired feedback is cleared only after its matching M07 response render');

$_SESSION = ['checador_temporary_access_feedback' => $expiredFeedback];
$invoke('discardExpiredTemporaryAccessPinFeedback');
$assert(!isset($_SESSION['checador_temporary_access_feedback']),
    'An unrelated Checador workspace may clear only an already-expired PIN value');

$errorFeedback = [
    'user_id' => 19,
    'workplace_id' => 1,
    'request_uuid' => $uuid,
    'action' => 'authorize',
    'type' => 'error',
    'message' => 'La acción no pudo confirmarse.',
];
$_SESSION = ['checador_temporary_access_feedback' => $errorFeedback];
$errorState = $invoke('temporaryAccessFeedbackForWorkspace', 'temporary-access', ['request_uuid' => $uuid], 19, 1, false);
$assert(($errorState['feedback']['message'] ?? null) === $errorFeedback['message']
    && !array_key_exists('temporary_pin', $errorState['feedback'] ?? []),
    'Redirect/error feedback is operation-scoped and contains no secret');

$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php') ?: '';
$sessionSource = file_get_contents(__DIR__ . '/../app/Core/Session.php') ?: '';
$actionStart = strpos($controllerSource, 'private function handleTemporaryAccessAction(');
$actionEnd = strpos($controllerSource, '/** Create an incidence', $actionStart === false ? 0 : $actionStart);
$actionBlock = $actionStart !== false && $actionEnd !== false
    ? substr($controllerSource, $actionStart, $actionEnd - $actionStart)
    : '';
$redirectStart = strpos($actionBlock, '$redirect =');
$redirectEnd = $redirectStart === false ? false : strpos($actionBlock, ';', $redirectStart);
$redirectBlock = $redirectStart !== false && $redirectEnd !== false
    ? substr($actionBlock, $redirectStart, $redirectEnd - $redirectStart)
    : '';
$assert(str_contains($controllerSource, "header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0'")
    && str_contains($controllerSource, "header('Pragma: no-cache'")
    && str_contains($controllerSource, "header('Expires: 0'")
    && str_contains($controllerSource, "header('Surrogate-Control: no-store'")
    && str_contains($serviceSource, "'POST',\n            'api/v2/temporary-access/requests/'")
    && !str_contains($controllerSource, 'error_log('),
    'M07/redirect cache policy is no-store and Portal3 does not log PIN response data');
$assert(str_contains($controllerSource, "authorizeTemporaryAccess(\$requestedWorkplace, 'crear')")
    && str_contains($controllerSource, "Request::all()")
    && str_contains($controllerSource, "'temporary_pin' => \$data['temporary_pin']")
    && str_contains($serviceSource, "\$data['pin_expires_at_utc'] = \$expiresAt;")
    && str_contains($serviceSource, "'api/v2/temporary-access/requests/' . rawurlencode(\$requestUuid) . '/' . \$action"),
    'Portal3 keeps its existing action gate while the service validates the API response expiry contract');
$assert(str_contains($sessionSource, 'session_start();')
    && !str_contains($controllerSource, 'session_write_close(')
    && str_contains($controllerSource, 'View::render($viewPath, $viewData, \'departamento-operativo\');'),
    'Native session lock remains active while the one-time M07 render is consumed');
$readAclPosition = strpos($controllerSource, "authorizeTemporaryAccess(\$requestedWorkplace, 'leer')");
$feedbackLookupPosition = strpos($controllerSource, 'temporaryAccessFeedbackForWorkspace(');
$assert($readAclPosition !== false && $feedbackLookupPosition !== false && $readAclPosition < $feedbackLookupPosition,
    'M07 read ACL is checked before session feedback can be presented or consumed');
$assert($redirectBlock !== ''
    && !str_contains($redirectBlock, 'temporary_pin')
    && !str_contains($actionBlock, 'Logger::')
    && !str_contains($actionBlock, 'error_log('),
    'Redirect URLs and supervisor action logs do not carry the PIN');

$_SESSION = [];
printf("CHECADOR_M07_PIN_FEEDBACK_HARDENING_RESULT: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

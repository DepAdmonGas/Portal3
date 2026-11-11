<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

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

$environmentKeys = ['APP_ENV', 'CHECADOR_API_REAL_POSTS_ENABLED', 'CHECADOR_TEMPORARY_ACCESS_POSTS_ENABLED', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO', 'CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'];
$originalEnvironment = [];
foreach ($environmentKeys as $key) {
    $originalEnvironment[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
}
$setEnvironment = static function (string $key, ?string $value): void {
    if ($value === null) {
        unset($_ENV[$key]);
        putenv($key);
        return;
    }
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
};
$restoreEnvironment = static function () use ($environmentKeys, $originalEnvironment): void {
    foreach ($environmentKeys as $key) {
        [$wasSet, $envValue, $processValue] = $originalEnvironment[$key];
        if ($wasSet) {
            $_ENV[$key] = $envValue;
        } else {
            unset($_ENV[$key]);
        }
        if ($processValue === false) {
            putenv($key);
        } else {
            putenv($key . '=' . $processValue);
        }
    }
};

$makeService = static function (array $responses, ?array &$history = null): ChecadorDailyReadService {
    $history ??= [];
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => 'https://checador.test/', 'http_errors' => false]),
        static fn(Usuario $user): string => 'test-only-server-token',
        'https://checador.test/'
    );
};

$user = new Usuario();
$user->id = 19;
$uuid = 'c71e8400-e29b-41d4-a716-446655440001';
$expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+15 minutes')->format('Y-m-d H:i:s');
$queueRow = [
    'request_uuid' => $uuid,
    'id_personal_resuelto' => 558,
    'nombre_trabajador' => 'Trabajador de prueba',
    'id_estacion' => 1,
    'dispositivo_nombre' => 'Lector Estación 1',
    'reason' => 'FINGERPRINT_NOT_RECOGNIZED',
    'requested_at_utc' => '2026-10-22 15:00:00',
    'request_expires_at_utc' => '2026-10-22 15:30:00',
];

try {
    $setEnvironment('APP_ENV', 'dev');
    $setEnvironment('CHECADOR_API_REAL_POSTS_ENABLED', 'true');
    $setEnvironment('CHECADOR_TEMPORARY_ACCESS_POSTS_ENABLED', 'false');
    $setEnvironment('CHECADOR_ADVANCED_DEMO_ENABLED', 'false');
    $setEnvironment('CHECADOR_ADVANCED_DEMO_SCENARIO', null);

    $history = [];
    $service = $makeService([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 200,
            'status' => 'OK',
            'data' => [$queueRow],
        ], JSON_THROW_ON_ERROR)),
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 200,
            'status' => 'AUTHORIZED',
            'request_uuid' => $uuid,
            'temporary_pin' => '004281',
            'pin_expires_at_utc' => $expiresAt,
            'mensaje' => 'PIN temporal emitido.',
        ], JSON_THROW_ON_ERROR)),
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 200,
            'status' => 'REJECTED',
            'request_uuid' => $uuid,
            'mensaje' => 'Solicitud rechazada.',
        ], JSON_THROW_ON_ERROR)),
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 200,
            'status' => 'CANCELLED',
            'request_uuid' => $uuid,
            'mensaje' => 'Solicitud cancelada.',
        ], JSON_THROW_ON_ERROR)),
    ], $history);

    $queue = $service->listTemporaryAccessRequests($user);
    $assert(($queue['ok'] ?? false) === true
        && count($queue['requests'] ?? []) === 1
        && ($queue['requests'][0]['id_estacion'] ?? null) === 1,
        'TAP01 pending queue is parsed from the frozen top-level data contract');
    $queueRequest = $history[0]['request'] ?? null;
    $assert($queueRequest instanceof RequestInterface
        && $queueRequest->getMethod() === 'GET'
        && (string) $queueRequest->getUri() === 'https://checador.test/api/v2/temporary-access/requests'
        && $queueRequest->hasHeader('Authorization'),
        'TAP02 queue uses the server-side authenticated API endpoint');

    $authorized = $service->temporaryAccessAction($user, $uuid, 'authorize');
    $authorizeRequest = $history[1]['request'] ?? null;
    $assert(($authorized['ok'] ?? false) === true
        && ($authorized['data']['temporary_pin'] ?? null) === '004281'
        && ($authorized['data']['pin_expires_at_utc'] ?? null) === $expiresAt,
        'TAP03 authorize accepts only the contract-shaped six-digit one-time PIN');
    $assert($authorizeRequest instanceof RequestInterface
        && $authorizeRequest->getMethod() === 'POST'
        && str_ends_with((string) $authorizeRequest->getUri(), '/api/v2/temporary-access/requests/' . $uuid . '/authorize')
        && !$authorizeRequest->hasHeader('Idempotency-Key'),
        'TAP04 authorize uses the frozen path without inventing an idempotency contract');

    $rejected = $service->temporaryAccessAction($user, $uuid, 'reject');
    $cancelled = $service->temporaryAccessAction($user, $uuid, 'cancel');
    $assert(($rejected['data']['status'] ?? null) === 'REJECTED'
        && ($cancelled['data']['status'] ?? null) === 'CANCELLED',
        'TAP05 reject/cancel accept only their expected API terminal states');

    $malformed = $makeService([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 200,
            'status' => 'OK',
            'data' => [array_merge($queueRow, ['request_uuid' => 'not-a-uuid'])],
        ], JSON_THROW_ON_ERROR)),
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 200,
            'status' => 'AUTHORIZED',
            'request_uuid' => $uuid,
            'temporary_pin' => '1234',
            'pin_expires_at_utc' => $expiresAt,
        ], JSON_THROW_ON_ERROR)),
    ]);
    $badQueue = $malformed->listTemporaryAccessRequests($user);
    $badPin = $malformed->temporaryAccessAction($user, $uuid, 'authorize');
    $assert(($badQueue['error']['kind'] ?? null) === 'api_unavailable'
        && ($badPin['error']['kind'] ?? null) === 'api_unavailable'
        && !isset($badPin['data']['temporary_pin']),
        'TAP06 malformed UUID or PIN fails closed and never returns an invalid code');

    $denied = $makeService([new Response(403, ['Content-Type' => 'application/json'], json_encode([
        'code' => 403,
        'status' => 'FUNCTION_PERMISSION_DENIED',
        'message' => 'Denied',
    ], JSON_THROW_ON_ERROR))]);
    $deniedQueue = $denied->listTemporaryAccessRequests($user);
    $assert(($deniedQueue['error']['kind'] ?? null) === 'api_authorization', 'TAP07 API authorization denial is not converted into an empty queue');

    $setEnvironment('APP_ENV', 'dev');
    $setEnvironment('CHECADOR_ADVANCED_DEMO_ENABLED', 'true');
    $setEnvironment('CHECADOR_ADVANCED_DEMO_SCENARIO', 'ready');
    $setEnvironment('CHECADOR_ADVANCED_DEMO_REFERENCE_DATE', ChecadorDailyReadService::DEMO_REFERENCE_DATE);
    $demoHistory = [];
    $demo = $makeService([], $demoHistory);
    $demoQueue = $demo->listTemporaryAccessRequests($user);
    $demoAction = $demo->temporaryAccessAction($user, $uuid, 'authorize');
    $assert(($demoQueue['error']['kind'] ?? null) === 'demo_operation_blocked'
        && ($demoAction['error']['kind'] ?? null) === 'demo_operation_blocked'
        && $demoHistory === [],
        'TAP08 demo mode blocks temporary access reads/writes without any outbound request');

    $setEnvironment('CHECADOR_ADVANCED_DEMO_ENABLED', 'false');
    $setEnvironment('CHECADOR_API_REAL_POSTS_ENABLED', 'false');
    $setEnvironment('CHECADOR_TEMPORARY_ACCESS_POSTS_ENABLED', 'true');
    $scopedHistory = [];
    $scopedService = $makeService([
        new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'code' => 200,
            'status' => 'CANCELLED',
            'request_uuid' => $uuid,
            'mensaje' => 'Solicitud cancelada.',
        ], JSON_THROW_ON_ERROR)),
    ], $scopedHistory);
    $scopedCancel = $scopedService->temporaryAccessAction($user, $uuid, 'cancel');
    $scopedPost = $scopedHistory[0]['request'] ?? null;
    $assert(ChecadorDailyReadService::temporaryAccessPostOperationsEnabled()
        && !ChecadorDailyReadService::realApiPostOperationsEnabled()
        && !ChecadorDailyReadService::scheduleApiPostOperationsEnabled()
        && ($scopedCancel['data']['status'] ?? null) === 'CANCELLED'
        && $scopedPost instanceof RequestInterface
        && $scopedPost->getMethod() === 'POST',
        'TAP09 isolated M07 development gate permits its API transition without enabling general or schedule POSTs');

    $setEnvironment('APP_ENV', 'production');
    $assert(!ChecadorDailyReadService::temporaryAccessPostOperationsEnabled(),
        'TAP10 isolated M07 gate remains disabled outside local development');

    $setEnvironment('APP_ENV', 'dev');
    $setEnvironment('CHECADOR_TEMPORARY_ACCESS_POSTS_ENABLED', 'false');
    $setEnvironment('CHECADOR_API_REAL_POSTS_ENABLED', 'true');

    $controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
    $accessService = file_get_contents(__DIR__ . '/../app/Services/ChecadorAccessService.php') ?: '';
    $view = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-temporary-access.php') ?: '';
    $routes = file_get_contents(__DIR__ . '/../routes/web.php') ?: '';
    $assert(str_contains($controller, '$isOperationsDirector = $currentUser instanceof \\App\\Models\\Usuario')
        && str_contains($controller, '&& (int) $currentUser->id_puesto === 13;')
        && str_contains($controller, 'if ($isOperationsDirector)')
        && str_contains($controller, "static fn(array \$entry): bool => (\$entry['label'] ?? '') !== 'PIN temporal'")
        && str_contains($controller, "'href' => \$base . '/acceso-temporal'")
        && str_contains($controller, "'available' => \$temporaryRead"),
        'TAP15 the PIN card is omitted only for Dirección or profiles without read ACL; the authorized route remains intact');
    $assert(str_contains($controller, "'authorize' => 'crear'")
        && str_contains($controller, "'reject' => 'editar'")
        && str_contains($controller, "'cancel' => 'eliminar'")
        && str_contains($controller, "authorizeTemporaryAccess(\$workplaceId, \$requiredPermission)")
        && str_contains($controller, "authorizeTemporaryAccess(\$requestedWorkplace, 'leer')")
        && str_contains($controller, 'temporaryAccessPostOperationsEnabled()')
        && str_contains($accessService, "getTemporaryAccessPermissions")
        && str_contains($accessService, "station_workplaces")
        && str_contains($accessService, "'crear' => (int) (\$permissions['crear'] ?? 0) === 1 || \$legacyEdit")
        && str_contains($accessService, "'eliminar' => (int) (\$permissions['eliminar'] ?? 0) === 1 || \$legacyEdit"),
        'TAP11 Portal3 rechecks each operation-specific ACL, preserves legacy edit compatibility, and retains station isolation');
    $assert(str_contains($routes, 'temporaryAccessAuthorize')
        && str_contains($routes, 'temporaryAccessReject')
        && str_contains($routes, 'temporaryAccessCancel')
        && str_contains($routes, 'Route::auth'),
        'TAP10 routes use the existing authenticated and CSRF-protected POST wrapper');
    $assert(str_contains($controller, "temporaryAccessFeedbackForWorkspace(")
        && str_contains($controller, "consumeTemporaryAccessFeedbackAfterRender(")
        && str_contains($controller, "'request_uuid' => self::safeTemporaryAccessRequestUuid(\$requestUuid)")
        && str_contains($controller, "'temporary_pin' => \$data['temporary_pin']")
        && str_contains($controller, "workspace !== 'temporary-access'")
        && str_contains($view, 'name="_csrf_token"')
        && str_contains($view, 'name="confirm_action"')
        && !str_contains($view, 'api/v2/temporary-access'),
        'TAP11 PIN feedback is M07/request-scoped and removed after render; CSRF/confirmation remain and browser never calls API');
    $assert(!str_contains($controller, "'api/v2/temporary-access/requests'"), 'TAP12 endpoint paths remain confined to the API service, not controller or browser');
    $assert(substr_count($controller, 'temporaryAccessRequestBelongsToLocality(') >= 3
        && str_contains($accessService, '$mapped = self::mapStationIdsToUniqueLocalities([$requestStationId])')
        && str_contains($accessService, "Estacion::query()->where('numlista', \$numlista)->count() !== 1")
        && str_contains($accessService, "RhLocalidad::query()->where('numlista', \$numlista)->count() !== 1"),
        'TAP13 physical station request IDs are matched to canonical localities only by unique numlista mapping');
    $assert(str_contains($view, 'Sin información de estado')
        && str_contains($view, "'FINGERPRINT_NOT_RECOGNIZED' => 'No se reconoció la huella'")
        && !str_contains($view, "<div class=\"small text-muted\">ID <?= (int) (\$request['id_personal_resuelto']")
        && !str_contains($view, "<code><?= \$esc(\$request['request_uuid']"),
        'TAP14 queue status and reason are presented in Spanish without exposing worker or request identifiers');
} finally {
    $restoreEnvironment();
}

printf("CHECADOR_TEMPORARY_ACCESS_PORTAL_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

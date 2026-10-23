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

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$mockEnvironmentKeys = ['APP_ENV', 'CHECADOR_API_REAL_POSTS_ENABLED'];
$mockEnvironmentOriginal = [];
foreach ($mockEnvironmentKeys as $key) {
    $mockEnvironmentOriginal[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
}
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'true'; // All outgoing requests below use explicit Guzzle MockHandlers.

$makeClient = static function (array $responses, array &$history): Client {
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    return new Client(['base_uri' => 'https://api-v2-mock.invalid/', 'handler' => $stack, 'http_errors' => false]);
};
$jsonResponse = static fn(int $status, array $payload): Response => new Response(
    $status,
    ['Content-Type' => 'application/json'],
    json_encode($payload, JSON_THROW_ON_ERROR)
);
$user = new Usuario();
$user->id = 12345;
$input = [
    'worker_id' => 558,
    'workplace_id' => 1,
    'category' => 'VACATION',
    'start_date' => '2026-10-20',
    'end_date' => '2026-10-21',
    'reason' => 'Solicitud autorizada de prueba',
    'evidence_reference' => 'FOLIO-26-1',
];
$createdData = [
    'uuid_incidencia' => 'INC-CREATE-TEST-001',
    'worker_id' => 558,
    'workplace_id' => 1,
    'category' => 'VACATION',
    'current_revision' => 1,
    'status' => 'PENDING_APPROVAL',
    'created_at_utc' => '2026-10-19T16:00:00Z',
];
$detailData = [
    'uuid_incidencia' => 'INC-CREATE-TEST-001',
    'worker_id' => 558,
    'workplace_id' => 1,
    'category' => 'VACATION',
    'scope' => 'WORKER',
    'date_range' => ['start_date' => '2026-10-20', 'end_date' => '2026-10-21'],
    'time_interval' => null,
    'current_status' => 'PENDING_APPROVAL',
    'current_revision' => 1,
    'revisions' => [[
        'revision_number' => 1,
        'status' => 'PENDING_APPROVAL',
        'reason' => 'Solicitud autorizada de prueba',
        'evidence_reference' => 'FOLIO-26-1',
        'created_by_user_id' => 12345,
        'approved_by_user_id' => null,
        'created_at_utc' => '2026-10-19T16:00:00Z',
    ]],
];

$history = [];
$service = new ChecadorDailyReadService(
    $makeClient([
        $jsonResponse(201, ['status' => 'success', 'data' => $createdData]),
        $jsonResponse(200, ['status' => 'success', 'data' => $detailData]),
    ], $history),
    static fn(Usuario $identity): string => 'mock-server-side-jwt',
    'https://api-v2-mock.invalid/'
);
$create = $service->createIncidence($user, $input, 'portal3-create-exact-key-01');
$readback = $service->readIncidence($user, 'INC-CREATE-TEST-001');
$postRequest = $history[0]['request'] ?? null;
$postBody = is_object($postRequest) ? json_decode((string) $postRequest->getBody(), true) : null;
$assert(($create['ok'] ?? false) === true
    && ($create['data']['current_revision'] ?? 0) === 1
    && ($readback['ok'] ?? false) === true
    && ($readback['data']['revisions'][0]['status'] ?? '') === 'PENDING_APPROVAL',
    'mocked create response is normalized and canonical detail/history can be read back');
$assert(count($history) === 2
    && is_object($postRequest)
    && $postRequest->getMethod() === 'POST'
    && $postRequest->getUri()->getPath() === '/api/v2/incidences'
    && $postRequest->getHeaderLine('Idempotency-Key') === 'portal3-create-exact-key-01'
    && $postRequest->getHeaderLine('Authorization') === 'Bearer mock-server-side-jwt',
    'creation uses the dedicated server-side API endpoint and mandatory idempotency/JWT headers');
$assert(is_array($postBody)
    && ($postBody['worker_id'] ?? 0) === 558
    && ($postBody['workplace_id'] ?? 0) === 1
    && ($postBody['scope'] ?? '') === 'WORKER'
    && array_key_exists('time_interval', $postBody) && $postBody['time_interval'] === null
    && ($postBody['evidence_reference'] ?? '') === 'FOLIO-26-1'
    && !array_key_exists('created_by_user_id', $postBody)
    && !array_key_exists('actor_user_id', $postBody),
    'payload preserves canonical tuple and fixed WORKER scope without client-supplied actor');

foreach ([
    'category' => ['category' => 'UNSUPPORTED'],
    'invalid_calendar_date' => ['start_date' => '2026-02-30'],
    'reversed_range' => ['end_date' => '2026-10-19'],
    'empty_reason' => ['reason' => '  '],
    'invalid_evidence_type' => ['evidence_reference' => ['not-a-string']],
] as $case => $changes) {
    $invalidHistory = [];
    $invalidClient = $makeClient([], $invalidHistory);
    $invalidService = new ChecadorDailyReadService($invalidClient, static fn(Usuario $identity): string => 'unused', 'https://api-v2-mock.invalid/');
    $invalidInput = array_merge($input, $changes);
    $invalidKey = $case === 'bad_key' ? 'key with spaces' : 'portal3-create-invalid-' . $case;
    $invalid = $invalidService->createIncidence($user, $invalidInput, $invalidKey);
    $assert(($invalid['error']['kind'] ?? '') === 'bad_request' && $invalidHistory === [],
        "{$case} is rejected before outbound HTTP");
}
$keyHistory = [];
$keyService = new ChecadorDailyReadService($makeClient([], $keyHistory), static fn(Usuario $identity): string => 'unused', 'https://api-v2-mock.invalid/');
$badKey = $keyService->createIncidence($user, $input, 'key with spaces');
$assert(($badKey['error']['kind'] ?? '') === 'bad_request' && $keyHistory === [], 'malformed idempotency key is rejected before outbound HTTP');

foreach ([
    [403, ['error' => ['code' => 'FUNCTION_PERMISSION_DENIED']], 'api_authorization'],
    [409, ['error' => ['code' => 'IDEMPOTENCY_CONFLICT']], 'idempotency_conflict'],
] as [$status, $payload, $expectedKind]) {
    $errorHistory = [];
    $errorService = new ChecadorDailyReadService($makeClient([$jsonResponse($status, $payload)], $errorHistory), static fn(Usuario $identity): string => 'mock-jwt', 'https://api-v2-mock.invalid/');
    $errorResult = $errorService->createIncidence($user, $input, 'portal3-create-error-key');
    $assert(($errorResult['error']['kind'] ?? '') === $expectedKind && count($errorHistory) === 1,
        "HTTP {$status} remains a fail-closed {$expectedKind} result");
}

$badResponseHistory = [];
$badResponseService = new ChecadorDailyReadService(
    $makeClient([$jsonResponse(201, ['status' => 'success', 'data' => array_merge($createdData, ['workplace_id' => 2])])], $badResponseHistory),
    static fn(Usuario $identity): string => 'mock-jwt',
    'https://api-v2-mock.invalid/'
);
$badResponse = $badResponseService->createIncidence($user, $input, 'portal3-create-bad-response');
$assert(($badResponse['error']['kind'] ?? '') === 'administrative_unavailable', 'mismatched canonical tuple in HTTP 201 is rejected');

foreach ($mockEnvironmentOriginal as $key => [$wasInEnv, $envValue, $processValue]) {
    if ($wasInEnv) $_ENV[$key] = $envValue; else unset($_ENV[$key]);
    if ($processValue === false) putenv($key); else putenv($key . '=' . $processValue);
}

$envKeys = ['APP_ENV', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO'];
$savedEnv = [];
foreach ($envKeys as $key) {
    $savedEnv[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
}
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'true';
$_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] = 'ready';
$demoHistory = [];
$demoTokenCalls = 0;
$demoService = new ChecadorDailyReadService(
    $makeClient([], $demoHistory),
    static function (Usuario $identity) use (&$demoTokenCalls): string { $demoTokenCalls++; return 'must-not-be-generated'; },
    'https://api-v2-mock.invalid/'
);
$demoResult = $demoService->createIncidence($user, $input, 'portal3-create-demo-blocked');
foreach ($savedEnv as $key => [$hadEnv, $envValue, $processValue]) {
    if ($hadEnv) $_ENV[$key] = $envValue; else unset($_ENV[$key]);
    if ($processValue === false) putenv($key); else putenv($key . '=' . $processValue);
}
$assert(($demoResult['error']['kind'] ?? '') === 'demo_write_blocked' && $demoHistory === [] && $demoTokenCalls === 0,
    'development demo mode blocks creation before JWT generation or HTTP');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
$access = file_get_contents($root . '/app/Services/ChecadorAccessService.php') ?: '';
$routes = file_get_contents($root . '/routes/web.php') ?: '';
$view = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php') ?: '';
$assert(str_contains($routes, "'/recursos-humanos/checador/incidencias/crear'")
    && str_contains($routes, "['ChecadorController', 'createIncidence']"), 'authenticated POST route is registered for creation');
$assert(str_contains($access, "INCIDENCE_MODULE_KEY = 'checador-v2-incidencias'")
    && str_contains($access, 'empty($permissions[\'crear\'])')
    && str_contains($access, 'empty($permissions[\'leer\'])'), 'dedicated create ACL and canonical readback ACL are required');
$assert(str_contains($controller, 'authorizeIncidenceCreate($workplaceId)')
    && str_contains($controller, 'readIncidence($user, (string) $createdData[\'uuid_incidencia\'])')
    && str_contains($controller, "(string) (\$readData['scope'] ?? '') === 'WORKER'")
    && str_contains($controller, "(int) (\$firstRevision['revision_number'] ?? 0) === 1")
    && str_contains($controller, "'retry_idempotency_key' => \$idempotencyKey")
    && str_contains($controller, "(string) (\$input['confirm_create'] ?? '') !== '1'"),
    'controller rechecks create ACL and verifies canonical tuple/history after POST');
$assert(str_contains($view, 'name="_csrf_token"')
    && str_contains($view, 'name="idempotency_key"')
    && str_contains($view, 'name="confirm_create"'),
    'Bootstrap form includes CSRF, idempotency and explicit confirmation with Spanish labels');

echo "\nRESULT: {$passes} PASS / {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

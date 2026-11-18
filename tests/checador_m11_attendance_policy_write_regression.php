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

$keys = ['APP_ENV', 'CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED', 'CHECADOR_M11_CONFIGURATION_POSTS_ENABLED', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_API_REAL_POSTS_ENABLED'];
$original = [];
foreach ($keys as $key) $original[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
$restore = static function () use ($keys, $original): void {
    foreach ($keys as $key) {
        [$wasSet, $value, $processValue] = $original[$key];
        if ($wasSet) $_ENV[$key] = $value; else unset($_ENV[$key]);
        if ($processValue === false) putenv($key); else putenv($key . '=' . $processValue);
    }
};
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED'] = 'true';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';
unset($_ENV['CHECADOR_API_REAL_POSTS_ENABLED']);
putenv('CHECADOR_API_REAL_POSTS_ENABLED');

$makeClient = static function (array $responses, array &$history): Client {
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    return new Client(['base_uri' => 'https://api-v2-mock.invalid/', 'handler' => $stack, 'http_errors' => false]);
};
$json = static fn(int $status, array $payload): Response => new Response(
    $status,
    ['Content-Type' => 'application/json'],
    json_encode($payload, JSON_THROW_ON_ERROR)
);
$user = new Usuario();
$user->id = 234;
$policy = [
    'id' => 81,
    'workplace_id' => 1,
    'policy_version' => 3,
    'effective_from_local_date' => '2026-11-19',
    'retardo_tolerance_minutes' => 30,
    'incidence_deadline_days' => 6,
    'timezone_id' => 'America/Mexico_City',
    'actor_portal_user_id' => 234,
    'source' => 'PORTAL3_ADMIN',
];
$readPayload = ['status' => 'success', 'data' => [
    'workplace_id' => 1,
    'effective_date' => '2026-11-19',
    'current_local_date' => '2026-11-18',
    'timezone_id' => 'America/Mexico_City',
    'status' => 'VERSIONED',
    'policy' => $policy,
    'latest_scheduled_policy' => $policy,
]];
$input = [
    'workplace_id' => 1,
    'effective_from_local_date' => '2026-11-19',
    'retardo_tolerance_minutes' => '30',
    'incidence_deadline_days' => '6',
];
$key = 'm11-policy-same-request-01';

try {
    $history = [];
    $service = new ChecadorDailyReadService(
        $makeClient([$json(201, ['status' => 'success', 'data' => $policy]), $json(200, $readPayload)], $history),
        static fn(Usuario $identity): string => 'isolated-mock-jwt',
        'https://api-v2-mock.invalid/'
    );
    $created = $service->createAttendancePolicy($user, $input, $key);
    $readback = $service->readAttendancePolicy($user, 1, '2026-11-19');
    $post = $history[0]['request'] ?? null;
    $body = is_object($post) ? json_decode((string) $post->getBody(), true) : null;
    $assert(($created['ok'] ?? false) === true && ($readback['data']['policy']['id'] ?? 0) === 81,
        'isolated API success is confirmed by the exact policy readback');
    $assert(is_object($post) && $post->getMethod() === 'POST'
        && $post->getUri()->getPath() === '/api/v2/attendance-policies'
        && $post->getHeaderLine('Idempotency-Key') === $key
        && $post->getHeaderLine('Authorization') === 'Bearer isolated-mock-jwt'
        && ($body['workplace_id'] ?? 0) === 1
        && ($body['effective_from_local_date'] ?? '') === '2026-11-19'
        && ($body['retardo_tolerance_minutes'] ?? -1) === 30
        && ($body['incidence_deadline_days'] ?? -1) === 6,
        'isolated request sends the expected V2 payload and reuses the caller idempotency key');

    $conflictHistory = [];
    $conflictService = new ChecadorDailyReadService(
        $makeClient([$json(409, ['error' => ['code' => 'IDEMPOTENCY_CONFLICT']])], $conflictHistory),
        static fn(Usuario $identity): string => 'isolated-mock-jwt',
        'https://api-v2-mock.invalid/'
    );
    $conflict = $conflictService->createAttendancePolicy($user, $input, $key);
    $retryHistory = [];
    $retryService = new ChecadorDailyReadService(
        $makeClient([$json(201, ['status' => 'success', 'data' => $policy])], $retryHistory),
        static fn(Usuario $identity): string => 'isolated-mock-jwt',
        'https://api-v2-mock.invalid/'
    );
    $retryService->createAttendancePolicy($user, $input, $key);
    $assert(($conflict['error']['kind'] ?? '') === 'idempotency_conflict'
        && ($conflict['error']['status'] ?? 0) === 409
        && ($conflict['error']['api_code'] ?? '') === 'IDEMPOTENCY_CONFLICT'
        && ($conflictHistory[0]['request']->getHeaderLine('Idempotency-Key') ?? '') === $key
        && ($retryHistory[0]['request']->getHeaderLine('Idempotency-Key') ?? '') === $key,
        'API conflict stays distinguishable and a retry carries the same idempotency key');

    $blockedHistory = [];
    $blockedService = new ChecadorDailyReadService(
        $makeClient([], $blockedHistory),
        static fn(Usuario $identity): string => 'isolated-mock-jwt',
        'https://api-v2-mock.invalid/'
    );
    $unrelatedPost = $blockedService->createIncidence($user, [
        'worker_id' => 12, 'workplace_id' => 1, 'category' => 'VACATION',
        'start_date' => '2026-11-20', 'end_date' => '2026-11-20', 'reason' => 'isolated test',
    ], 'not-m11-policy-write');
    $assert(($unrelatedPost['error']['kind'] ?? '') === 'api_post_disabled' && $blockedHistory === [],
        'the policy-specific opt-in does not enable unrelated API V2 POST operations');

    $_ENV['APP_ENV'] = 'prod';
    $_ENV['CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED'] = 'true';
    $_ENV['CHECADOR_M11_CONFIGURATION_POSTS_ENABLED'] = 'true';
    $_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
    $assert(ChecadorDailyReadService::attendancePolicyPostOperationsEnabled()
        && ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled()
        && !ChecadorDailyReadService::realApiPostOperationsEnabled(),
        'production M11 gates require their dedicated flags while the global real-POST gate stays closed');
    $_ENV['CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED'] = 'false';
    $_ENV['CHECADOR_M11_CONFIGURATION_POSTS_ENABLED'] = 'false';
    $assert(!ChecadorDailyReadService::attendancePolicyPostOperationsEnabled()
        && !ChecadorDailyReadService::m11ConfigurationPostOperationsEnabled(),
        'production M11 write gates fail closed when either dedicated opt-in is absent');
} finally {
    $restore();
}

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$controllerStart = strpos($controller, 'public function saveAttendancePolicy(): void');
$controllerEnd = strpos($controller, 'private function renderScheduleHistory(', $controllerStart ?: 0);
$method = $controllerStart === false ? '' : substr($controller, $controllerStart, $controllerEnd === false ? null : $controllerEnd - $controllerStart);
$javascript = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance-policy.init.js') ?: '';
$view = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/configuration.php') ?: '';
$assert(strpos($method, 'readAttendancePolicy($user, $workplaceId, $effectiveDate)') < strpos($method, 'createAttendancePolicy($user, [')
    && str_contains($method, 'policy_date_conflict') && str_contains($method, 'retry_idempotency_key'),
    'controller reads the requested date before POST, blocks duplicate/conflicting versions and preserves retry identity on uncertainty');
$assert(str_contains(file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php') ?: '', '$attendancePolicyWrite && self::attendancePolicyPostOperationsEnabled()'),
    'central API POST gate honors only the dedicated M11 attendance-policy operation gate for that route');
$assert(str_contains($method, "'api_http_status' => \$apiStatus") && str_contains($method, "'api_error_code' => \$created['error']['api_code'] ?? null")
    && str_contains($javascript, 'body.api_error_code'),
    'API HTTP status and sanitized error code reach the interface without exposing credentials');
$assert(str_contains($method, 'authorizeAttendancePolicy($workplaceId, \'editar\')')
    && str_contains($method, 'authorizeAttendancePolicy($workplaceId, \'leer\')')
    && str_contains($javascript, 'new FormData(form)')
    && str_contains($javascript, "Accept: 'application/json'")
    && str_contains($javascript, 'consult_url')
    && str_contains($view, 'Consultar estado antes de reintentar'),
    'save path retains server read/edit authorization and exposes a read-before-retry path for browser submissions');

printf("CHECADOR_M11_ATTENDANCE_POLICY_WRITE_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

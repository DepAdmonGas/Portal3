<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$keys = ['APP_ENV', 'CHECADOR_API_REAL_POSTS_ENABLED', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO'];
$previous = [];
foreach ($keys as $key) {
    $previous[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
}
$set = static function (string $key, ?string $value): void {
    if ($value === null) {
        unset($_ENV[$key]);
        putenv($key);
        return;
    }
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
};
$restore = static function () use ($keys, $previous): void {
    foreach ($keys as $key) {
        [$wasSet, $envValue, $processValue] = $previous[$key];
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

$set('APP_ENV', 'dev');
$set('CHECADOR_API_REAL_POSTS_ENABLED', null);
$set('CHECADOR_ADVANCED_DEMO_ENABLED', 'false');
$set('CHECADOR_ADVANCED_DEMO_SCENARIO', null);

$networkCalls = 0;
$tokenCalls = 0;
$history = [];
$stack = HandlerStack::create(static function () use (&$networkCalls): never {
    $networkCalls++;
    throw new RuntimeException('Unexpected network operation in gate regression.');
});
$stack->push(Middleware::history($history));
$service = new ChecadorDailyReadService(
    new Client(['handler' => $stack, 'base_uri' => 'https://must-not-connect.invalid/', 'http_errors' => false]),
    static function (Usuario $user) use (&$tokenCalls): string {
        $tokenCalls++;
        return 'test-token';
    },
    'https://must-not-connect.invalid/'
);
$user = new Usuario();
$user->id = 123;
$uuid = 'c71e8400-e29b-41d4-a716-446655440001';

try {
    $set('APP_ENV', 'dev');
    $set('CHECADOR_API_REAL_POSTS_ENABLED', null);
    $set('CHECADOR_ADVANCED_DEMO_ENABLED', 'false');
    $set('CHECADOR_ADVANCED_DEMO_SCENARIO', null);
    $assert(!ChecadorDailyReadService::realApiPostOperationsEnabled(), 'real API POST gate defaults closed in local development');

    $postCalls = [
        $service->createDailyPunchSnapshot($user, 1, [
            'from_utc' => '2026-10-19T06:00:00Z',
            'to_utc' => '2026-10-20T06:00:00Z',
        ], 'snapshot-request-1'),
        $service->evaluateJourney($user, 558, 1, '2026-10-19'),
        $service->createIncidence($user, [
            'worker_id' => 558,
            'workplace_id' => 1,
            'category' => 'VACATION',
            'start_date' => '2026-10-20',
            'end_date' => '2026-10-20',
            'reason' => 'test-only payload',
        ], 'gate-create-1'),
        $service->lifecycleAction($user, 'inc-1', 'resolve', 1, 'gate-lifecycle-1', ['resolution_notes' => 'test-only payload']),
        $service->executeAdministrativeEvaluation($user, 558, 1, '2026-10-19', 'gate-evaluation-1', 1),
        $service->temporaryAccessAction($user, $uuid, 'authorize'),
    ];
    $assert(count($postCalls) === 6
        && array_reduce($postCalls, static fn(bool $ok, array $result): bool => $ok
            && ($result['ok'] ?? true) === false
            && ($result['error']['kind'] ?? '') === 'api_post_disabled', true),
        'snapshot, Journey, incidence, lifecycle, admin evaluation and temporary access POST operations fail closed');
    $assert($networkCalls === 0 && $tokenCalls === 0 && $history === [], 'closed POST gate runs before JWT creation and HTTP transport');

    $set('CHECADOR_API_REAL_POSTS_ENABLED', 'true');
    $assert(ChecadorDailyReadService::realApiPostOperationsEnabled(), 'explicit opt-in opens the gate only in a local development environment');
    $set('APP_ENV', 'production');
    $assert(!ChecadorDailyReadService::realApiPostOperationsEnabled(), 'the local gate remains closed in production even if its flag is present');
    $set('APP_ENV', 'preproduction');
    $assert(!ChecadorDailyReadService::realApiPostOperationsEnabled(), 'the local gate remains closed in preproduction even if its flag is present');
} finally {
    $restore();
}

$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php') ?: '';
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php') ?: '';
$assert(strpos($serviceSource, "if (strtoupper(\$method) === 'POST' && !self::realApiPostOperationsEnabled())") !== false
    && str_contains($controllerSource, "'api_post_disabled' =>")
    && str_contains($viewSource, 'Las operaciones POST reales de Checador están deshabilitadas'),
    'POST gate is enforced centrally, reported in Spanish and surfaced in the UI');
$assert(str_contains($controllerSource, '$apiPostOperationsEnabled = $demoMode || $realApiPostOperationsEnabled')
    && str_contains($serviceSource, 'if ($this->demoScenario !== null)')
    && str_contains($serviceSource, '$this->demoResponse($method, $path'),
    'demo responses remain on their separate local adapter and do not use the real API POST opt-in');

printf("CHECADOR_REAL_API_POST_GATE_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

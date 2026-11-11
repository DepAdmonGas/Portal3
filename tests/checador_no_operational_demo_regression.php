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
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    if ($condition) {
        echo "PASS {$label}\n";
        $passes++;
    } else {
        echo "FAIL {$label}\n";
        $fails++;
    }
};

$keys = ['APP_ENV', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO'];
$controllerSource = (string) file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$rosterSource = (string) file_get_contents(__DIR__ . '/../app/Services/ChecadorAttendanceRosterService.php');
$attendanceView = (string) file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php');
$original = [];
foreach ($keys as $key) {
    $original[$key] = [
        'env' => array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
        'process' => getenv($key),
    ];
}
$set = static function (string $key, string $value): void {
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
};

try {
    // Deliberately misconfigure the opt-in flag as true under production.
    $set('APP_ENV', 'production');
    $set('CHECADOR_ADVANCED_DEMO_ENABLED', 'true');
    $set('CHECADOR_ADVANCED_DEMO_SCENARIO', 'ready');

    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, [], json_encode(['status' => 'success', 'data' => [
            'workplace_id' => 14,
            'local_date' => '2026-10-19',
            'timezone' => 'America/Mexico_City',
            'from_utc' => '2026-10-19T06:00:00Z',
            'to_utc' => '2026-10-20T06:00:00Z',
        ]], JSON_THROW_ON_ERROR)),
    ]));
    $stack->push(Middleware::history($history));
    $client = new Client(['base_uri' => 'https://checador.test/', 'handler' => $stack, 'http_errors' => false]);
    $tokenCalls = 0;
    $service = new ChecadorDailyReadService(
        $client,
        static function (Usuario $user) use (&$tokenCalls): string {
            $tokenCalls++;
            return 'test-bearer-token';
        },
        'https://checador.test/'
    );
    $user = new Usuario();
    $user->id = 77;
    $result = $service->resolveWorkplaceLocalDay($user, 14, '2026-10-19');

    $assert(ChecadorDailyReadService::demoModeEnabled() === false
        && $service->isDemoMode() === false
        && ChecadorDailyReadService::configuredDemoScenario() === null,
        'production rejects the demo flag and does not configure a synthetic scenario');
    $assert(str_contains($rosterSource, '$demoMode && ChecadorDailyReadService::demoModeEnabled()')
        && str_contains($controllerSource, 'isset($observedIds[$profileWorkerId])')
        && str_contains($controllerSource, "'full_name' => 'Nombre no disponible'")
        && !str_contains($attendanceView, 'Modo demostración')
        && !str_contains($attendanceView, '>Demo</span>'),
        'HTTP attendance cannot use synthetic roster rows or emit demo labels; empty snapshots stay empty');
    $assert(($result['ok'] ?? false) === true
        && ($result['data']['timezone'] ?? '') === 'America/Mexico_City'
        && $tokenCalls === 1
        && count($history) === 1
        && $history[0]['request']->getMethod() === 'GET'
        && str_contains((string) $history[0]['request']->getUri(), '/api/v2/workplaces/14/timezone'),
        'real-data boundary sends the authenticated timezone request and accepts its mocked API response');

    $failureStack = HandlerStack::create(new MockHandler([
        new Response(503, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR)),
    ]));
    $failureHistory = [];
    $failureStack->push(Middleware::history($failureHistory));
    $failureService = new ChecadorDailyReadService(
        new Client(['base_uri' => 'https://checador.test/', 'handler' => $failureStack, 'http_errors' => false]),
        static fn (Usuario $user): string => 'test-bearer-token',
        'https://checador.test/'
    );
    $failure = $failureService->resolveWorkplaceLocalDay($user, 14, '2026-10-19');
    $assert(($failure['ok'] ?? true) === false
        && ($failure['error']['kind'] ?? '') !== ''
        && count($failureHistory) === 1,
        'API failure remains an error and is not replaced by a synthetic response');
} finally {
    foreach ($original as $key => $values) {
        if ($values['env'] === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $values['env'];
        }
        if ($values['process'] === false) {
            putenv($key);
        } else {
            putenv($key . '=' . $values['process']);
        }
    }
}

printf("CHECADOR_NO_OPERATIONAL_DEMO: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

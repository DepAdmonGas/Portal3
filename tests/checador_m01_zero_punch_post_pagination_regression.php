<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\ChecadorController;
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
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . PHP_EOL;
    $condition ? $passes++ : $fails++;
};

$history = [];
$stack = HandlerStack::create(new MockHandler([
    new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'code' => 200,
        'status' => 'OK',
        'data' => [],
        'pagination' => [
            'current_page' => 1,
            'per_page' => 200,
            'total_records' => 0,
            'total_pages' => 0,
        ],
    ], JSON_THROW_ON_ERROR)),
]));
$stack->push(Middleware::history($history));
$reader = new ChecadorDailyReadService(
    new Client(['handler' => $stack, 'http_errors' => false]),
    static fn (Usuario $user): string => 'test-only-token',
    'https://checador.test/'
);
$user = new Usuario();
$user->id = 123;
$timezone = [
    'ok' => true,
    'data' => [
        'workplace_id' => 1,
        'local_date' => '2026-11-16',
        'timezone' => 'America/Mexico_City',
        'from_utc' => '2026-11-16T06:00:00Z',
        'to_utc' => '2026-11-17T06:00:00Z',
    ],
];
$workerCatalog = [
    'status' => 'unavailable',
    'coverage' => 'unknown',
    'workers' => [],
    'message' => '',
];
$method = new ReflectionMethod(ChecadorController::class, 'readDailyRawAttendance');
$result = $method->invoke(
    null,
    $reader,
    $user,
    1,
    '2026-11-16',
    null,
    false,
    [1],
    $workerCatalog,
    $timezone
);

$request = $history[0]['request'] ?? null;
$assert($request instanceof RequestInterface, 'daily attendance produces one HTTP request');
$assert($request instanceof RequestInterface && $request->getMethod() === 'GET',
    'daily attendance executes a read-only HTTP method');
$assert($request instanceof RequestInterface && str_contains($request->getUri()->getPath(), 'punch-events'),
    'daily attendance executes the existing punch endpoint');
$assert(($result['state'] ?? '') === 'success'
    && ($result['attendance_coverage']['api_records_read'] ?? null) === 0
    && ($result['attendance_coverage']['message'] ?? '') !== '',
    'valid zero-event response completes post-pagination attendance processing');
$assert(in_array(($result['scheduled_comodines']['status'] ?? ''), ['not_authorized', 'not_queried'], true),
    'Comodines authorization is evaluated without skipping the post-pagination flow');

printf("CHECADOR_M01_ZERO_PUNCH_POST_PAGINATION: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

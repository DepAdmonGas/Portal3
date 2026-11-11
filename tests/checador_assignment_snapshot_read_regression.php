<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Services/ChecadorDailyReadService.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

putenv('CHECADOR_ADVANCED_DEMO_ENABLED=0');
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = '0';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$item = static fn(int $id): array => [
    'id' => $id, 'id_personal' => 1006, 'id_estacion' => 1, 'id_plantilla' => null,
    'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'LUNES', 'valido_desde' => '2026-11-06',
    'valido_hasta' => null, 'es_activo' => 1, 'registrado_at_utc' => '2026-11-05 12:00:00',
];
$payload = static function (int $page, array $items, int $total, int $pages, string $token): array {
    return ['status' => 'success', 'data' => [
        'page' => $page, 'per_page' => 2, 'items' => $items,
        'pagination' => [
            'total_records' => $total, 'total_pages' => $pages, 'has_more' => $page < $pages,
            'read_snapshot' => $token, 'expires_at_utc' => '2026-11-05T12:05:00.000000Z',
        ],
    ]];
};
$response = static fn(array $data, int $status = 200): Response => new Response($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
$user = new Usuario();
$user->id = 33;
$token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$seenRequests = [];
$mock = new MockHandler([
    $response($payload(1, [$item(21), $item(20)], 3, 2, $token)),
    $response($payload(2, [$item(19)], 3, 2, $token)),
]);
$stack = HandlerStack::create($mock);
$stack->push(GuzzleHttp\Middleware::history($seenRequests));
$service = new ChecadorDailyReadService(new Client(['handler' => $stack]), static fn(Usuario $u): string => 'test-jwt', 'http://127.0.0.1:8081/');
$result = $service->getAllShiftAssignmentsSnapshot($user, 1006, 1, null, 2);
$assert(($result['ok'] ?? false) === true && ($result['data']['complete'] ?? false) === true, 'Multi-page read should be complete.');
$assert(array_column($result['data']['items'], 'id') === [21, 20, 19], 'Pages should retain deterministic order.');
$assert(count($seenRequests) === 2, 'Multi-page read should make two server requests.');
$assert($seenRequests[0]['request']->getHeaderLine('X-Read-Snapshot') === '', 'Initial request must not carry a token.');
$assert($seenRequests[1]['request']->getHeaderLine('X-Read-Snapshot') === $token, 'Continuation must carry the opaque token server-to-server.');

$emptyToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$emptyStack = HandlerStack::create(new MockHandler([$response($payload(1, [], 0, 0, $emptyToken))]));
$emptyService = new ChecadorDailyReadService(new Client(['handler' => $emptyStack]), static fn(Usuario $u): string => 'test-jwt', 'http://127.0.0.1:8081/');
$empty = $emptyService->getAllShiftAssignmentsSnapshot($user, 1006, 1, null, 2);
$assert(($empty['ok'] ?? false) === true && $empty['data']['complete'] === true && $empty['data']['items'] === [], 'Explicit zero-row snapshot should be complete.');

$dupToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$dupStack = HandlerStack::create(new MockHandler([
    $response($payload(1, [$item(21), $item(20)], 3, 2, $dupToken)),
    $response($payload(2, [$item(20)], 3, 2, $dupToken)),
]));
$dupService = new ChecadorDailyReadService(new Client(['handler' => $dupStack]), static fn(Usuario $u): string => 'test-jwt', 'http://127.0.0.1:8081/');
$duplicate = $dupService->getAllShiftAssignmentsSnapshot($user, 1006, 1, null, 2);
$assert(($duplicate['ok'] ?? true) === false, 'Duplicate assignment IDs must fail closed.');

echo "PASS: multi-page aggregation, token confinement, explicit empty result, and duplicate rejection\n";

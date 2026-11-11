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

$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';
$user = new Usuario();
$user->id = 999;
$requests = [];
$history = Middleware::history($requests);
$body = ['status' => 'success', 'data' => [
    'workplace_id' => 1,
    'query' => ['date_from' => '2026-11-06', 'date_to' => '2026-11-06', 'worker_id' => null, 'adjustment_type' => null, 'status' => null],
    'as_of_utc' => '2026-11-06T18:00:00Z', 'total_records' => 0, 'page' => 1, 'per_page' => 25, 'total_pages' => 0, 'adjustments' => [],
]];
$stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR))]));
$stack->push($history);
$service = new ChecadorDailyReadService(new Client(['handler' => $stack, 'base_uri' => 'https://api.example/', 'http_errors' => false]), static fn(Usuario $user): string => 'delegated-test-jwt', 'https://api.example/');
$result = $service->readAdministrativeAdjustments($user, ['workplace_id' => 1, 'date_from' => '2026-11-06', 'date_to' => '2026-11-06', 'page' => 1, 'per_page' => 25]);
$assertions = [
    ($result['ok'] ?? false) === true && ($result['data']['adjustments'] ?? null) === [] && ($result['data']['total_records'] ?? null) === 0,
    (string) ($requests[0]['request']->getUri()->getPath() ?? '') === '/api/v2/administrative-adjustments',
    $requests[0]['request']->getHeaderLine('Authorization') === 'Bearer delegated-test-jwt',
];
$detailBody = ['status' => 'success', 'data' => [
    'adjustment' => ['uuid_ajuste' => 'adj-1', 'uuid_ajuste_logico' => 'logical-1', 'revision_number' => 1, 'status' => 'ACTIVE', 'adjustment_type' => 'JUSTIFY_LATENESS', 'worker_id' => 41, 'workplace_id' => 1, 'work_date_local' => '2026-11-06', 'workplace_timezone' => 'America/Mexico_City', 'motive' => 'Motivo persistido', 'interpreted_entry_at_utc' => null, 'interpreted_exit_at_utc' => null, 'created_at_utc' => '2026-11-06T12:00:00Z', 'created_by_user_id' => 8, 'approved_by_user_id' => 8, 'previous_revision_uuid' => null],
    'revision_history' => [['uuid_ajuste' => 'adj-1', 'uuid_ajuste_logico' => 'logical-1', 'revision_number' => 1, 'status' => 'ACTIVE', 'adjustment_type' => 'JUSTIFY_LATENESS', 'worker_id' => 41, 'workplace_id' => 1, 'work_date_local' => '2026-11-06', 'workplace_timezone' => 'America/Mexico_City', 'motive' => 'Motivo persistido', 'interpreted_entry_at_utc' => null, 'interpreted_exit_at_utc' => null, 'created_at_utc' => '2026-11-06T12:00:00Z', 'created_by_user_id' => 8, 'approved_by_user_id' => 8, 'previous_revision_uuid' => null]],
    'linked_evaluations' => [],
]];
$detailStack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode($detailBody, JSON_THROW_ON_ERROR))]));
$detailRequests = [];
$detailStack->push(Middleware::history($detailRequests));
$detailService = new ChecadorDailyReadService(new Client(['handler' => $detailStack, 'base_uri' => 'https://api.example/', 'http_errors' => false]), static fn(Usuario $user): string => 'delegated-test-jwt', 'https://api.example/');
$detail = $detailService->readAdministrativeAdjustmentDetail($user, 'adj-1');
$assertions[] = ($detail['ok'] ?? false) === true && ($detail['data']['adjustment']['uuid_ajuste'] ?? '') === 'adj-1' && ($detail['data']['linked_evaluations'] ?? null) === [];
$assertions[] = (string) ($detailRequests[0]['request']->getUri()->getPath() ?? '') === '/api/v2/administrative-adjustments/adj-1';
$invalidStack = HandlerStack::create(new MockHandler([]));
$invalidService = new ChecadorDailyReadService(new Client(['handler' => $invalidStack, 'base_uri' => 'https://api.example/']), static fn(Usuario $user): string => 'delegated-test-jwt', 'https://api.example/');
$invalid = $invalidService->readAdministrativeAdjustments($user, ['workplace_id' => 1, 'date_from' => '2026-11-01', 'date_to' => '2026-12-02']);
$assertions[] = ($invalid['ok'] ?? true) === false && ($invalid['error']['status'] ?? 0) === 400;

$failed = count(array_filter($assertions, static fn(bool $passed): bool => !$passed));
echo sprintf("ADJUSTMENT_API_READ_SERVICE_RESULT: %d/%d PASS\n", count($assertions) - $failed, count($assertions));
exit($failed === 0 ? 0 : 1);

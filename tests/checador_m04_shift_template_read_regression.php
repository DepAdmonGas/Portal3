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

$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_ADVANCED_DEMO_ENABLED=false');

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};
$base = 'https://checador.test/';
$user = new Usuario();
$user->id = 91;
$make = static function (array $responses, ?array &$history = null) use ($base): ChecadorDailyReadService {
    $history ??= [];
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static fn (Usuario $identity): string => 'test-only-token',
        $base
    );
};
$json = static fn (int $status, array $payload): Response => new Response(
    $status,
    ['Content-Type' => 'application/json'],
    json_encode($payload, JSON_THROW_ON_ERROR)
);

$emptyHistory = [];
$emptyCatalog = $make([$json(200, ['status' => 'success', 'data' => [
    'page' => 1, 'per_page' => 200, 'items' => [],
]])], $emptyHistory)->getShiftTemplates($user, 1, 200, 1);
$emptyRequest = $emptyHistory[0]['request'] ?? null;
$assert(($emptyCatalog['ok'] ?? false) === true && ($emptyCatalog['data']['items'] ?? null) === [],
    'valid empty catalog remains distinct from an unavailable catalog');
$assert($emptyRequest instanceof RequestInterface && $emptyRequest->getMethod() === 'GET'
    && (string) $emptyRequest->getUri() === 'https://checador.test/api/v2/shift-templates?page=1&per_page=200&station_id=1',
    'M04 list read sends canonical station_id=1 and the V2 pagination contract');

$denied = $make([$json(403, ['status' => 'error', 'error' => ['code' => 'UNAUTHORIZED_STATION_ACCESS']])])
    ->getShiftTemplates($user, 1, 200, 1);
$assert(($denied['ok'] ?? true) === false && ($denied['error']['status'] ?? 0) === 403
    && ($denied['diagnostic']['operation'] ?? '') === 'm04_shift_templates_list'
    && ($denied['diagnostic']['api_error_code'] ?? '') === 'UNAUTHORIZED_STATION_ACCESS',
    'HTTP authorization failures retain a sanitized operation, status and API error code');

$invalidContract = $make([$json(200, ['status' => 'success', 'data' => ['items' => []]])])
    ->getShiftTemplates($user, 1, 200, 1);
$assert(($invalidContract['error']['kind'] ?? '') === 'schedule_templates_unavailable'
    && ($invalidContract['error']['status'] ?? 0) === 502,
    'malformed catalog response is a contract failure, not an empty catalog');

$versionMissing = $make([$json(404, ['status' => 'error', 'error' => ['code' => 'NOT_FOUND']])])
    ->getShiftTemplateVersion($user, 'DAY', 1, 1);
$assert(($versionMissing['error']['status'] ?? 0) === 404
    && ($versionMissing['diagnostic']['operation'] ?? '') === 'm04_shift_template_version',
    'version detail failures retain their HTTP status and distinct operation');

$service = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php') ?: '';
$assert(str_contains($service, "'m04_shift_templates_list'")
    && str_contains($service, "'m04_shift_template_version'")
    && str_contains($service, 'shiftTemplateContractFailure'),
    'M04 list, version and contract failures use the existing sanitized server diagnostic logger');

echo "\n{$passes} PASS, {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

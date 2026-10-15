<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
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

$user = new Usuario();
$user->id = 999;
$base = 'https://checador.test/';
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$routeSource = file_get_contents(__DIR__ . '/../routes/web.php');

$make = static function (array $responses, ?array &$history = null, ?string &$token = null) use ($base): ChecadorDailyReadService {
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $history ??= [];
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(
        new \GuzzleHttp\Client(['handler' => $stack, 'base_uri' => 'https://checador.test/', 'http_errors' => false]),
        static function (Usuario $user) use (&$token): string {
            $token = 'portal3-test-jwt';
            return $token;
        },
        $base
    );
};

$timezoneResponse = new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'status' => 'success',
    'data' => [
        'workplace_id' => 1,
        'local_date' => '2026-10-12',
        'timezone' => 'America/Mexico_City',
        'from_utc' => '2026-10-12T06:00:00Z',
        'to_utc' => '2026-10-13T06:00:00Z',
    ],
], JSON_THROW_ON_ERROR));
$snapshotResponse = new Response(201, ['Content-Type' => 'application/json'], json_encode([
    'status' => 'success',
    'data' => [
        'snapshot' => [
            'snapshot_token' => 'snap_server_only',
            'snapshot_audit_ref' => 'audit_server_only',
            'status' => 'READY',
            'created_at' => '2026-10-12T06:00:00Z',
            'expires_at' => '2026-10-13T06:00:00Z',
            'total_records' => 0,
        ],
        'completeness' => [
            'level_1_api_snapshot' => true,
            'level_2_ingestion_completeness' => false,
            'status' => 'LEVEL_1_COMPLETE_ONLY',
        ],
    ],
], JSON_THROW_ON_ERROR));
$pageResponse = static fn(array $items = [], ?string $nextCursor = null, bool $hasMore = false): Response => new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'status' => 'success',
    'data' => [
        'items' => $items,
        'pagination' => ['next_cursor' => $nextCursor, 'has_more' => $hasMore, 'page_size' => count($items)],
    ],
], JSON_THROW_ON_ERROR));

// P401-P413: creation, frozen UTC window, server-side JWT, and first page.
$history = [];
$token = null;
$service = $make([$timezoneResponse, $snapshotResponse, $pageResponse()], $history, $token);
$tz = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-12');
$snapshot = $service->createDailyPunchSnapshot($user, 1, $tz['data'], 'p04-create-1');
$page = $service->readDailyPunchSnapshotPage($user, $snapshot['data']['snapshot_token']);
$createRequest = $history[1]['request'] ?? null;
$pageRequest = $history[2]['request'] ?? null;
$createBody = $createRequest instanceof RequestInterface ? json_decode((string) $createRequest->getBody(), true) : [];
$assert($snapshot['ok'] === true, 'P401 authorized user can create daily snapshot');
$assert(str_contains($controllerSource, 'http_response_code(403)'), 'P402 unauthorized Portal3 user is denied before API call');
$assert(str_contains($controllerSource, 'authorize($requestedWorkplace)'), 'P403 out-of-scope workplace is denied before snapshot creation');
$assert(count($history) === 3 && str_contains((string) $history[0]['request']->getUri(), '/api/v2/workplaces/1/timezone'), 'P404 timezone endpoint is called before snapshot creation');
$assert(($createBody['from'] ?? '') === '2026-10-12T06:00:00Z', 'P405 snapshot uses exact API from_utc');
$assert(($createBody['to'] ?? '') === '2026-10-13T06:00:00Z', 'P406 snapshot uses exact API to_utc');
$assert(($createBody['station_id'] ?? null) === 1, 'P407 snapshot uses canonical authorized workplace ID');
$assert($token === 'portal3-test-jwt' && isset($createRequest->getHeader('Authorization')[0]), 'P408 Portal3 JWT is generated server-side');
$assert($createRequest instanceof RequestInterface && !$createRequest->hasHeader('X-Device-Id') && !$createRequest->hasHeader('X-Credential-Key'), 'P409 Device Auth is absent');
$assert(!preg_match('/fetch\(|axios\.|XMLHttpRequest|api\/v2/i', $viewSource), 'P410 browser direct API is absent');
$assert(!str_contains($viewSource, 'snapshot_token') && str_contains($controllerSource, 'saveSnapshotState'), 'P411 snapshot token is not exposed unnecessarily');
$assert($snapshot['data']['status'] === 'READY' && $snapshot['data']['completeness_status'] === 'LEVEL_1_COMPLETE_ONLY', 'P412 snapshot metadata is parsed exactly');
$assert($page['ok'] === true && ($page['events'] ?? null) === [], 'P413 first snapshot page is rendered as an observed set');

// P414-P415: cursor paging remains inside the same server-owned snapshot.
$history = [];
$service = $make([$pageResponse([['event_uuid' => 'e1']], 'cursor-page-2', true), $pageResponse([], null, false)], $history);
$first = $service->readDailyPunchSnapshotPage($user, 'snap_same', null, 50);
$second = $service->readDailyPunchSnapshotPage($user, 'snap_same', $first['pagination']['next_cursor'], 50);
$assert($first['ok'] && $second['ok'] && str_contains((string) $history[1]['request']->getUri(), '/read-snapshots/snap_same/events'), 'P414 next page uses the same snapshot token/context');
$assert(count($history) === 2 && !str_contains((string) $history[1]['request']->getUri(), 'read-snapshots/create'), 'P415 no new snapshot is created for the next page');

// P416-P419: expiration and API errors remain explicit, never empty-result.
$expired = $make([new Response(404, [], json_encode(['status' => 'SNAPSHOT_EXPIRED'], JSON_THROW_ON_ERROR))]);
$invalid = $make([new Response(422, [], json_encode(['status' => 'INVALID_CURSOR'], JSON_THROW_ON_ERROR))]);
$denied = $make([new Response(403, [], json_encode(['status' => 'SNAPSHOT_SCOPE_CHANGED'], JSON_THROW_ON_ERROR))]);
$failed = $make([new Response(500, [], json_encode(['status' => 'INTERNAL_ERROR'], JSON_THROW_ON_ERROR))]);
$assert(($expired->readDailyPunchSnapshotPage($user, 'expired')['error']['kind'] ?? '') === 'snapshot_expired', 'P416 expired snapshot renders an explicit expiration state');
$assert(($invalid->readDailyPunchSnapshotPage($user, 'invalid')['error']['kind'] ?? '') === 'snapshot_invalid', 'P417 invalid snapshot cursor is not an empty result');
$assert(($denied->readDailyPunchSnapshotPage($user, 'denied')['error']['kind'] ?? '') === 'snapshot_authorization', 'P418 API authorization failure is not an empty result');
$assert(($failed->readDailyPunchSnapshotPage($user, 'failed')['error']['kind'] ?? '') === 'api_unavailable', 'P419 API failure remains unavailable, not empty');

// P420-P424 and P431: observed-only wording and integrity preservation.
$assert(!str_contains($viewSource, 'SIN MARCACIÓN'), 'P420 successful empty snapshot does not render SIN MARCACIÓN');
$assert(str_contains($viewSource, 'Nunca se activa automáticamente'), 'P421 successful empty snapshot does not render automatic FALTA');
$assert(!str_contains($viewSource, 'AUSENTE'), 'P422 successful empty snapshot does not render AUSENTE');
$assert(!preg_match('/n[oó]mina|payroll|descuento/i', $viewSource), 'P423 no payroll conclusion is rendered');
$assert(str_contains($viewSource, "['integrity']['status']") || str_contains($viewSource, "['integrity']['status'] ??"), 'P424 integrity status is preserved');
$assert(str_contains($viewSource, 'conjunto estable de marcaciones observadas') && str_contains($viewSource, 'no determina faltas'), 'P431 source-completeness notice is rendered');

// P425-P430 and P432: Portal3 boundary, fail-closed scope, and P03 preservation.
$assert(str_contains($controllerSource, 'ChecadorAccessService::authorize($requestedWorkplace)'), 'P425 current Portal3 authorization is revalidated on page requests');
$assert(str_contains($controllerSource, "workplace_id'] ?? 0") && str_contains($controllerSource, 'snapshot_ref'), 'P426 snapshot state cannot broaden workplace scope');
$assert(str_contains($controllerSource, 'resolveWorkplaceLocalDay') && str_contains($controllerSource, 'timezone_unresolved'), 'P427 date before timezone authority fails before snapshot creation');
$assert(!str_contains($serviceSource, 'op_rh_v2_'), 'P428 Portal3 does not read raw V2 tables directly');
$assert(str_contains($serviceSource, 'readObservedPunches'), 'P429 ordinary P03 raw-read functionality remains intact');
$assert(str_contains($routeSource, '/departamento-operativo/recursos-humanos/checador') && str_contains($controllerSource, 'createDailyPunchSnapshot'), 'P430 P03 route remains the same while Level 1 is added');
$assert(str_contains($controllerSource, "'snapshot_expired'") && str_contains($controllerSource, 'snapshotRefProvided') && str_contains($controllerSource, 'snapshot_state_unavailable'), 'P432 expiration does not silently substitute a different snapshot');

printf("CHECADOR_P04_LEVEL1_SNAPSHOT_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

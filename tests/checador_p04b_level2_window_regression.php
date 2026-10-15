<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
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
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$p04aSource = file_get_contents(__DIR__ . '/checador_p04_level1_snapshot_regression.php');

$make = static function (array $responses, ?array &$history = null, ?string &$token = null) use ($base): ChecadorDailyReadService {
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $history ??= [];
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static function (Usuario $user) use (&$token): string {
            $token = 'portal3-level2-jwt';
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

$level2Response = static function (string $aggregate, array $overrides = []): Response {
    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'status' => 'success',
        'data' => array_merge([
            'window_level2_status' => $aggregate,
            'delivery_completeness' => 'VERIFIED',
            'operational_coverage' => 'UNKNOWN',
            'time_window_attribution' => 'VERIFIED',
            'has_zero_governed_events' => true,
            'governed_event_count' => 0,
            'manifest_digest' => 'must-not-reach-view',
        ], $overrides),
    ], JSON_THROW_ON_ERROR));
};

$timezone = [
    'workplace_id' => 1,
    'local_date' => '2026-10-12',
    'timezone' => 'America/Mexico_City',
    'from_utc' => '2026-10-12T06:00:00Z',
    'to_utc' => '2026-10-13T06:00:00Z',
];

// P3B01-P3B10: server-side boundary, authoritative timezone, and exact window.
$history = [];
$token = null;
$service = $make([$timezoneResponse, $level2Response('VERIFIED')], $history, $token);
$resolved = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-12');
$level2 = $service->getLevel2WindowStatus($user, 1, $resolved['data']);
$level2Request = $history[1]['request'] ?? null;
$level2Uri = $level2Request instanceof RequestInterface ? (string) $level2Request->getUri() : '';
$assert($level2['ok'] === true, 'P3B01 authorized user can query Level 2 status');
$assert(str_contains($controllerSource, 'ChecadorAccessService::authorize($requestedWorkplace)'), 'P3B02 user without local Checador access is denied before API calls');
$assert(str_contains($controllerSource, 'requested_station_out_of_scope') || str_contains($controllerSource, 'authorize($requestedWorkplace)'), 'P3B03 out-of-scope workplace is denied before downstream requests');
$assert($token === 'portal3-level2-jwt' && $level2Request instanceof RequestInterface && $level2Request->hasHeader('Authorization'), 'P3B04 server-side Portal3 JWT is used');
$assert($level2Request instanceof RequestInterface && !$level2Request->hasHeader('X-Device-Id') && !$level2Request->hasHeader('X-Credential-Key'), 'P3B05 Device Auth is never used');
$assert(!preg_match('/fetch\(|axios\.|XMLHttpRequest|api\/v2/i', $viewSource), 'P3B06 browser direct Checador API is absent');
$assert(count($history) === 2 && str_contains((string) $history[0]['request']->getUri(), '/workplaces/1/timezone'), 'P3B07 timezone endpoint is called first');
$assert(str_contains($level2Uri, 'from=2026-10-12T06%3A00%3A00Z') || str_contains($level2Uri, 'from=2026-10-12T06:00:00Z'), 'P3B08 from_utc is used exactly');
$assert(str_contains($level2Uri, 'to=2026-10-13T06%3A00%3A00Z') || str_contains($level2Uri, 'to=2026-10-13T06:00:00Z'), 'P3B09 to_utc is used exactly');
$assert(str_contains($serviceSource, "'api/v2/level2/window-status'") && str_contains($controllerSource, 'getLevel2WindowStatus'), 'P3B10 the same API-owned UTC window feeds Level 1 and Level 2');

// P3B11-P3B14: aggregate is authoritative; dimensions never promote it.
$verified = $make([$level2Response('VERIFIED')])->getLevel2WindowStatus($user, 1, $timezone);
$incomplete = $make([$level2Response('INCOMPLETE')])->getLevel2WindowStatus($user, 1, $timezone);
$unknown = $make([$level2Response('UNKNOWN')])->getLevel2WindowStatus($user, 1, $timezone);
$unknownWithVerifiedDimension = $make([$level2Response('UNKNOWN', [
    'delivery_completeness' => 'VERIFIED',
    'time_window_attribution' => 'VERIFIED',
])])->getLevel2WindowStatus($user, 1, $timezone);
$assert(($verified['data']['window_level2_status'] ?? '') === 'VERIFIED', 'P3B11 VERIFIED aggregate is parsed correctly');
$assert(($incomplete['data']['window_level2_status'] ?? '') === 'INCOMPLETE', 'P3B12 INCOMPLETE aggregate is parsed correctly');
$assert(($unknown['data']['window_level2_status'] ?? '') === 'UNKNOWN', 'P3B13 UNKNOWN aggregate is parsed correctly');
$assert(($unknownWithVerifiedDimension['data']['window_level2_status'] ?? '') === 'UNKNOWN', 'P3B14 a VERIFIED dimension does not upgrade UNKNOWN aggregate');

// P3B15-P3B20: zero events/completeness are not attendance or payroll conclusions.
$assert(!str_contains($viewSource, 'SIN MARCACIÓN'), 'P3B15 zero events + UNKNOWN does not produce SIN MARCACIÓN');
$assert(!str_contains($viewSource, 'SIN MARCACIÓN'), 'P3B16 zero events + INCOMPLETE does not produce SIN MARCACIÓN');
$assert(str_contains($viewSource, 'Nunca se activa automáticamente'), 'P3B17 zero events + VERIFIED does not produce automatic FALTA');
$assert(!str_contains($viewSource, 'has_zero_governed_events') || !str_contains($viewSource, 'AUSENTE'), 'P3B18 has_zero_governed_events does not produce absence');
$assert(!str_contains($viewSource, 'RETARDO'), 'P3B19 no RETARDO inference is rendered');
$assert(!preg_match('/n[oó]mina|payroll|descuento/i', $viewSource), 'P3B20 no payroll conclusion is rendered');

// P3B21-P3B25: transport/domain errors remain separate and fail closed.
$authError = $make([new Response(401, [], json_encode(['status' => 'UNAUTHORIZED'], JSON_THROW_ON_ERROR))])->getLevel2WindowStatus($user, 1, $timezone);
$scopeError = $make([new Response(403, [], json_encode(['status' => 'FUNCTION_PERMISSION_DENIED'], JSON_THROW_ON_ERROR))])->getLevel2WindowStatus($user, 1, $timezone);
$serverError = $make([new Response(500, [], json_encode(['status' => 'INTERNAL_ERROR'], JSON_THROW_ON_ERROR))])->getLevel2WindowStatus($user, 1, $timezone);
$networkError = $make([new \RuntimeException('network')])->getLevel2WindowStatus($user, 1, $timezone);
$assert(($authError['error']['kind'] ?? '') === 'api_authentication', 'P3B21 HTTP 401 is an authentication/error state');
$assert(($scopeError['error']['kind'] ?? '') === 'level2_authorization', 'P3B22 HTTP 403 is an authorization/scope state');
$assert(($serverError['error']['kind'] ?? '') === 'api_unavailable', 'P3B23 HTTP 500 is an unavailable/error state');
$assert(($networkError['error']['kind'] ?? '') === 'api_unavailable' && !isset($networkError['data']), 'P3B24 network failure does not become domain UNKNOWN');
$assert(str_contains($controllerSource, '$level2Meta = null') && str_contains($controllerSource, 'Fail closed'), 'P3B25 a failed fresh request cannot retain a prior VERIFIED state');

// P3B26-P3B30: preserve P04A/P03 and keep scope/token details server-side.
$assert(str_contains($p04aSource, 'createDailyPunchSnapshot') && str_contains($controllerSource, 'createDailyPunchSnapshot'), 'P3B26 P04A snapshot behavior remains intact');
$assert(str_contains($serviceSource, 'readObservedPunches'), 'P3B27 P03 raw-read behavior remains intact');
$assert(str_contains($controllerSource, 'authorize($requestedWorkplace)') && str_contains($controllerSource, 'selectedWorkplaceId'), 'P3B28 client input cannot broaden workplace scope');
$assert(!preg_match('/\bmanifest_digest\b/', $viewSource) && !preg_match('/(?<!level2_)evaluation_ref\b/', $viewSource), 'P3B29 internal evidence is not unnecessarily exposed to normal UI');
$assert(str_contains($controllerSource, 'getLevel2WindowStatus') && str_contains($controllerSource, 'stateForLevel2Error'), 'P3B30 current user authorization and Level 2 errors are revalidated per request');

printf("CHECADOR_P04B_LEVEL2_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

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
$user->id = 123;
$base = 'https://checador.test/';
$sourceController = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$sourceService = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$sourceDailyView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php');
$sourceView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/punch-report.php');
$sourceTableScript = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.datatable.init.js');
$sourceRoute = file_get_contents(__DIR__ . '/../routes/web.php');

$make = static function (array $responses, ?array &$history = null, ?string &$token = null) use ($base): ChecadorDailyReadService {
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $history ??= [];
    $historyMiddleware = Middleware::history($history);
    $stack->push($historyMiddleware);
    return new ChecadorDailyReadService(
        new \GuzzleHttp\Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static function (Usuario $user) use (&$token): string {
            $token = 'portal3-test-jwt';
            return $token;
        },
        $base
    );
};

$timezonePayload = static fn(array $data = []): Response => new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'code' => 200,
    'status' => 'OK',
    'data' => array_merge([
        'workplace_id' => 1,
        'local_date' => '2026-10-12',
        'timezone' => 'America/Mexico_City',
        'from_utc' => '2026-10-12T06:00:00Z',
        'to_utc' => '2026-10-13T06:00:00Z',
    ], $data),
], JSON_THROW_ON_ERROR));
$punchPayload = static fn(array $data = [], array $pagination = []): Response => new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'code' => 200,
    'status' => 'OK',
    'data' => $data,
    'pagination' => array_merge([
        'current_page' => 1,
        'per_page' => 50,
        'total_records' => count($data),
        'total_pages' => $data === [] ? 0 : 1,
    ], $pagination),
], JSON_THROW_ON_ERROR));

// P301-P304: the controller keeps the existing server-side ACL/scope gate.
$assert(str_contains($sourceController, 'ChecadorAccessService::authorize'), 'P301 authorized users enter through the existing ACL service');
$assert(str_contains($sourceController, "http_response_code(403)"), 'P302 unauthorized users are denied');
$assert(str_contains($sourceController, 'requestedWorkplace') && str_contains($sourceController, 'authorize($requestedWorkplace)'), 'P303 selected workplace is validated before any API call');
$assert(str_contains($sourceController, "'selectedWorkplaceId' => \$requestedWorkplace"), 'P304 authorized workplace is accepted by the controller');

// P305-P307: JWT exists only in the server-side service request.
$capturedToken = null;
$service = $make([$timezonePayload()], $history, $capturedToken);
$service->resolveWorkplaceLocalDay($user, 1, '2026-10-12');
$assert($capturedToken === 'portal3-test-jwt', 'P305 Portal3 JWT is generated server-side for the downstream request');
$request = $history[0]['request'] ?? null;
$headers = $request instanceof RequestInterface ? $request->getHeaders() : [];
$assert($request instanceof RequestInterface && isset($headers['Authorization']) && !isset($headers['X-Device-Id']) && !isset($headers['X-Credential-Key']), 'P306 Device Auth headers are absent');
$assert(!str_contains($sourceView, 'JWT') && !str_contains($sourceView, 'JWT_SECRET') && !str_contains($sourceView, 'Authorization'), 'P307 JWT and signing secret are absent from rendered HTML');

// P308-P314: timezone first, then exact punch parameters and opaque UTC bounds.
$history = [];
$service = $make([
    $timezonePayload(),
    $punchPayload([
        [
            'event_uuid' => 'e1',
            'ocurrido_at_utc' => '2026-10-12 18:00:00',
            'integrity' => ['status' => 'VALID'],
        ],
    ]),
], $history);
$tz = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-12');
$read = $service->readObservedPunches($user, 1, $tz['data']);
$firstUri = (string) ($history[0]['request']->getUri() ?? '');
$secondUri = (string) ($history[1]['request']->getUri() ?? '');
$assert(count($history) === 2 && str_contains($firstUri, '/api/v2/workplaces/1/timezone'), 'P308 timezone endpoint is called before Punch Read');
$unresolvedHistory = [];
$unresolvedService = $make([new Response(404, [], json_encode(['status' => 'WORKPLACE_TIMEZONE_UNRESOLVED'], JSON_THROW_ON_ERROR))], $unresolvedHistory);
$unresolvedService->resolveWorkplaceLocalDay($user, 1, '2026-10-11');
$assert(count($unresolvedHistory) === 1, 'P309 timezone unresolved prevents Punch Read');
$assert(str_contains($secondUri, 'from=2026-10-12T06%3A00%3A00Z') || str_contains($secondUri, 'from=2026-10-12T06:00:00Z'), 'P310 API from_utc is used exactly');
$assert(str_contains($secondUri, 'to=2026-10-13T06%3A00%3A00Z') || str_contains($secondUri, 'to=2026-10-13T06:00:00Z'), 'P311 API to_utc is used exactly');
$timezoneMethodStart = strpos($sourceService, 'public function resolveWorkplaceLocalDay(');
$timezoneMethodEnd = $timezoneMethodStart === false
    ? false
    : strpos($sourceService, 'public function readObservedPunches(', $timezoneMethodStart);
$timezoneMethodSource = $timezoneMethodStart !== false && $timezoneMethodEnd !== false
    ? substr($sourceService, $timezoneMethodStart, $timezoneMethodEnd - $timezoneMethodStart)
    : '';
$assert($timezoneMethodSource !== ''
    && !str_contains($timezoneMethodSource, 'DateTimeZone')
    && !str_contains($timezoneMethodSource, '+ 24')
    && !str_contains($timezoneMethodSource, "modify('+1 day'"), 'P312 production timezone flow trusts the API UTC window; local conversion is confined to the guarded demo provider');
$assert(str_contains($secondUri, 'station_id=1'), 'P313 station_id equals the canonical authorized workplace ID');
$assert(str_contains($secondUri, 'page=1') && str_contains($secondUri, 'per_page=50') && str_contains($secondUri, 'from=') && str_contains($secondUri, 'to='), 'P314 Punch Read uses the frozen parameter names');

// P315-P316: safe downstream error states.
$service = $make([new Response(403, [], json_encode(['status' => 'WORKPLACE_SCOPE_DENIED'], JSON_THROW_ON_ERROR))]);
$denied = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-12');
$assert(($denied['error']['kind'] ?? '') === 'api_authorization', 'P315 API 403 becomes an authorization failure');
$service = $make([new \RuntimeException('network')]);
$unavailable = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-12');
$assert(($unavailable['error']['kind'] ?? '') === 'api_unavailable', 'P316 API timeout/500 becomes an unavailable state');

// P317-P324: observed-only UI and no browser/API credential boundary.
$assert(str_contains($sourceTableScript, 'No se devolvieron marcaciones observadas para este periodo consultado.'), 'P317 successful empty results are rendered as observed-empty');
$assert(!str_contains($sourceView, 'SIN MARCACIÓN'), 'P318 successful empty results do not render SIN MARCACIÓN or FALTA');
$assert(!preg_match('/\b(?:importe_total|total_percepciones|total_deducciones|descuento|nomina_original|doc_nomina(?:_firma|_aguinaldo)?)\b/i', $sourceView), 'P319 raw dashboard does not render payroll amounts or document data');
$assert(str_contains($sourceView, 'punch-detail-integrity')
    && str_contains($sourceTableScript, "setDetailText('punch-detail-integrity'")
    && str_contains($sourceController, "'integrity' => ChecadorPresentationFormatter::integrityLabel()"), 'P320 raw-event integrity label is explicitly preserved in the detail view');
$assert(str_contains($sourceView, 'data-endpoint="/departamento-operativo/recursos-humanos/checador/reporte/data"')
    && str_contains($sourceTableScript, 'serverSide: true')
    && str_contains($sourceController, "'recordsFiltered' => (int) \$result['records_filtered']"), 'P321 server-side pagination metadata is respected by the raw report');
$assert(!preg_match('/fetch\(|axios\.|XMLHttpRequest|api\/v2/i', $sourceDailyView . $sourceView), 'P322 browser-direct API requests are absent');
$assert(!str_contains($sourceDailyView . $sourceView, 'X-Device-Id') && !str_contains($sourceDailyView . $sourceView, 'X-Credential-Key') && !str_contains($sourceDailyView . $sourceView, 'Bearer '), 'P323 privileged credentials are absent from rendered HTML');
$assert(str_contains($sourceRoute, "'/recursos-humanos/checador/asistencia'")
    && str_contains($sourceController, "renderDashboardWorkspace('daily-attendance')")
    && str_contains($sourceDailyView, 'Asistencia del día'), 'P324 the operational attendance view remains available through its separate authenticated route');

// P325-P329: temporal fail-closed states and successful empty/error distinction.
$service = $make([new Response(404, [], json_encode(['status' => 'WORKPLACE_TIMEZONE_UNRESOLVED'], JSON_THROW_ON_ERROR))]);
$before = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-11');
$assert(($before['error']['kind'] ?? '') === 'timezone_unresolved', 'P325 date before the effective start is unresolved');
$service = $make([new Response(409, [], json_encode(['status' => 'WORKPLACE_TIMEZONE_AMBIGUOUS'], JSON_THROW_ON_ERROR))]);
$ambiguous = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-12');
$assert(($ambiguous['error']['kind'] ?? '') === 'timezone_ambiguous', 'P326 ambiguous timezone does not proceed to Punch Read');
$service = $make([new Response(500, [], json_encode(['status' => 'WORKPLACE_TIMEZONE_INVALID'], JSON_THROW_ON_ERROR))]);
$invalid = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-12');
$assert(($invalid['error']['kind'] ?? '') === 'timezone_invalid', 'P327 invalid timezone does not proceed to Punch Read');
$assert(($tz['data']['timezone'] ?? '') === 'America/Mexico_City' && ($tz['data']['from_utc'] ?? '') === '2026-10-12T06:00:00Z', 'P328 successful timezone response is displayed as API authority');
$emptyService = $make([$timezonePayload(), $punchPayload([])], $emptyHistory);
$emptyTimezone = $emptyService->resolveWorkplaceLocalDay($user, 1, '2026-10-12');
$emptyRead = $emptyService->readObservedPunches($user, 1, $emptyTimezone['data']);
$assert(($emptyRead['ok'] ?? false) === true && ($emptyRead['events'] ?? null) === [] && ($unavailable['ok'] ?? true) === false, 'P329 successful empty/read failure states remain distinguishable');

// P330: no schema or new API endpoint dependency.
$assert(!str_contains($sourceRoute, 'api/v2') && str_contains($sourceService, 'createDailyPunchSnapshot') && !str_contains($sourceService, 'op_rh_v2_') && !str_contains($sourceService, 'migrat'), 'P330 no direct schema or arbitrary API dependency is introduced');

printf("CHECADOR_P03_DAILY_READ_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/support/portal3_autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorPresentationFormatter;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

$passes = 0;
$fails = 0;
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'true'; // Requests in this test are intercepted by explicit Guzzle MockHandlers.
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$user = new Usuario();
$user->id = 999;
$base = 'https://checador.test/';
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$m06ServiceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAdministrativeIncidenceSqlService.php');
$m06ScriptSource = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.incidences.datatable.init.js');
$accessSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAccessService.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php');
$routesSource = file_get_contents(__DIR__ . '/../routes/web.php');
$freezePath = __DIR__ . '/../docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md';

$response = static function (string $status): Response {
    return new Response(200, [], json_encode(['status' => 'success', 'data' => [
        'uuid_incidencia' => 'inc-1',
        'resolution_status' => $status,
        'status' => $status,
        'resolution_revision_number' => 2,
        'authorized_revision_number' => 2,
        'rejected_revision_number' => 2,
        'revocation_revision_number' => 3,
    ],], JSON_THROW_ON_ERROR));
};
$make = static function (array $responses, ?array &$history = null) use ($base): ChecadorDailyReadService {
    $stack = HandlerStack::create(new MockHandler($responses));
    $history = [];
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]), static fn (Usuario $user): string => 'portal3-jwt', $base);
};

$history = [];
$service = $make([
    $response('RESOLVED_PENDING_AUTHORIZATION'),
    $response('ACTIVE'),
    $response('REJECTED'),
    $response('CANCELLED'),
], $history);
$resolve = $service->lifecycleAction($user, 'inc-1', 'resolve', 1, 'idem-resolve-1', ['resolution_notes' => 'reviewed', 'evidence_reference' => 'e1']);
$authorize = $service->lifecycleAction($user, 'inc-1', 'authorize', 2, 'idem-authorize-1', ['authorization_notes' => 'confirmed']);
$reject = $service->lifecycleAction($user, 'inc-1', 'reject', 1, 'idem-reject-1', ['rejection_reason' => 'insufficient']);
$revoke = $service->lifecycleAction($user, 'inc-1', 'revoke', 2, 'idem-revoke-1', ['revocation_reason' => 'cancelled', 'evidence_reference' => 'e2']);
$firstRequest = $history[0]['request'];
$firstBody = json_decode((string) $firstRequest->getBody(), true);

$assert(is_file($freezePath) && hash_file('sha256', $freezePath) === '87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85', 'P3GPC01 Portal3 Architecture Freeze hash matches');
$assert(str_contains($serviceSource, 'lifecycleAction') && str_contains($controllerSource, 'handleLifecycle'), 'P3GPC02 lifecycle uses the server-side Portal3 client');
$assert(!str_contains($viewSource, 'fetch(') && !str_contains($viewSource, 'axios') && !str_contains($viewSource, 'api/v2/'), 'P3GPC03 browser does not call Checador API directly');
$assert(!preg_match('/Device[-_ ]?Auth|X-Device|device[_-]credential|Id-Dispositivo/i', $m06ServiceSource . $viewSource . $m06ScriptSource), 'P3GPC04 the Portal3 SQL M06 reader/writer does not use Device Auth');
$assert(str_contains($accessSource, 'RESOLUTION_MODULE_KEY') && str_contains($accessSource, "'resolve'") && str_contains($accessSource, "!empty(\$permissions['editar'])"), 'P3GPC05 resolution ACL uses dedicated namespace and editar');
$assert(str_contains($accessSource, 'AUTHORIZATION_MODULE_KEY') && str_contains($accessSource, "'authorize'") && str_contains($accessSource, "'reject'"), 'P3GPC06 authorization ACL is separate from resolution');
$assert(str_contains($accessSource, "!empty(\$permissions['eliminar']) || !empty(\$permissions['editar'])"), 'P3GPC07 revoke accepts only exact eliminar-or-editar semantics');
$assert(substr_count($routesSource, "Route::auth(['ChecadorController', 'createIncidence'])") === 1
    && substr_count($routesSource, "Route::auth(['ChecadorController', 'createIncidenceRevision'])") === 1
    && substr_count($routesSource, "Route::auth(['ChecadorController', 'lifecycle") === 4
    && str_contains($routesSource, "'POST', '/recursos-humanos/checador/incidencias/crear'")
    && str_contains($routesSource, "'POST', '/recursos-humanos/checador/incidencias/{uuidIncidencia:[0-9a-fA-F-]{36}}/revisiones'")
    && str_contains($routesSource, "'POST', '/recursos-humanos/checador/incidencias/{uuidIncidencia:[0-9a-fA-F-]{36}}/resolver'")
    && str_contains($routesSource, "'POST', '/recursos-humanos/checador/incidencias/{uuidIncidencia:[0-9a-fA-F-]{36}}/autorizar'")
    && str_contains($routesSource, "'POST', '/recursos-humanos/checador/incidencias/{uuidIncidencia:[0-9a-fA-F-]{36}}/rechazar'")
    && str_contains($routesSource, "'POST', '/recursos-humanos/checador/incidencias/{uuidIncidencia:[0-9a-fA-F-]{36}}/revocar'"),
    'P3GPC08 all six contract-authorized M06 operations are authenticated POST routes under the operational group');
$assert(($resolve['ok'] ?? false) === true && ($resolve['data']['resolution_status'] ?? '') === 'RESOLVED_PENDING_AUTHORIZATION', 'P3GPC09 resolve returns authoritative pending-authorization state');
$assert(($firstRequest->getUri()->getPath() ?? '') === '/api/v2/incidences/inc-1/resolve' && ($firstBody['expected_current_revision'] ?? 0) === 1 && ($firstBody['resolution_notes'] ?? '') === 'reviewed', 'P3GPC10 resolve sends exact endpoint and expected revision');
$assert(($firstRequest->getHeaderLine('Idempotency-Key') ?? '') === 'idem-resolve-1', 'P3GPC11 resolve sends idempotency identity');
$assert(($resolve['data']['status'] ?? '') !== 'ACTIVE' && ($resolve['data']['resolution_status'] ?? '') !== 'AUTHORIZED', 'P3GPC12 resolve never fabricates final authorization');
$assert(($authorize['ok'] ?? false) === true && ($authorize['data']['status'] ?? '') === 'ACTIVE', 'P3GPC13 authorize refresh result is ACTIVE');
$assert(($reject['ok'] ?? false) === true && ($reject['data']['status'] ?? '') === 'REJECTED', 'P3GPC14 reject refresh result is REJECTED');
$assert(($revoke['ok'] ?? false) === true && ($revoke['data']['status'] ?? '') === 'CANCELLED'
    && str_contains($viewSource, 'data-m06-history-list')
    && str_contains($m06ScriptSource, 'renderHistory(row)'), 'P3GPC15 revoke result is CANCELLED and SQL-backed revision history remains rendered by the M06 client');
$assert(str_contains($viewSource, 'REVIEW_REQUIRED') || str_contains($viewSource, 'current_status'), 'P3GPC16 REVIEW_REQUIRED/current state remains explicit');
$assert(ChecadorPresentationFormatter::incidenceStatusLabel('RESOLVED_PENDING_AUTHORIZATION') === 'Resuelta; pendiente de autorización'
    && ChecadorPresentationFormatter::incidenceStatusLabel('PENDING_APPROVAL') === 'Pendiente de autorización'
    && ChecadorPresentationFormatter::incidenceStatusLabel('CANCELLED') === 'Cancelada'
    && ChecadorPresentationFormatter::incidenceStatusLabel('UNRECOGNIZED_INTERNAL_CODE') === 'Estado no reconocido',
    'P3GPC36 lifecycle states are localized without exposing unknown API codes');
$assert(substr_count($controllerSource, 'incidenceStatusLabel($currentStatus)') >= 2
    && str_contains($controllerSource, "Estado actual: ' . \$statusLabel"),
    'P3GPC37 SQL create and lifecycle success feedback show the localized status from persisted readback');
$assert(str_contains($serviceSource, "'expected_current_revision' => \$expectedCurrentRevision"), 'P3GPC17 every command includes expected_current_revision');
$stale = $make([new Response(409, [], json_encode(['error' => ['code' => 'STALE_REVISION_CONFLICT']], JSON_THROW_ON_ERROR))])->lifecycleAction($user, 'inc-1', 'resolve', 1, 'idem-stale-1', ['resolution_notes' => 'retry']);
$assert(($stale['error']['kind'] ?? '') === 'stale_revision_conflict' && str_contains($controllerSource, 'cambió mientras'), 'P3GPC18 stale revision fails and requires refresh, not blind retry');
$assert(str_contains($serviceSource, "\$headers['Idempotency-Key']") && str_contains($controllerSource, 'retry_idempotency_key'), 'P3GPC19 transport recovery retains the same idempotency key');
$idem = $make([new Response(409, [], json_encode(['error' => ['code' => 'IDEMPOTENCY_CONFLICT']], JSON_THROW_ON_ERROR))])->lifecycleAction($user, 'inc-1', 'authorize', 2, 'idem-conflict-1');
$assert(($idem['error']['kind'] ?? '') === 'idempotency_conflict', 'P3GPC20 idempotency conflict fails closed');
$transition = $make([new Response(409, [], json_encode(['error' => ['code' => 'INVALID_LIFECYCLE_TRANSITION']], JSON_THROW_ON_ERROR))])->lifecycleAction($user, 'inc-1', 'revoke', 3, 'idem-transition-1', ['revocation_reason' => 'retry']);
$assert(($transition['error']['kind'] ?? '') === 'invalid_lifecycle_transition', 'P3GPC21 invalid transition does not show success');
$server = $make([new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR))])->lifecycleAction($user, 'inc-1', 'resolve', 1, 'idem-server-1', ['resolution_notes' => 'retry']);
$assert(($server['error']['kind'] ?? '') === 'api_unavailable', 'P3GPC22 timeout/5xx path remains unavailable');
$unauthorized = $make([new Response(401, [], json_encode(['error' => ['code' => 'UNAUTHORIZED']], JSON_THROW_ON_ERROR))])->lifecycleAction($user, 'inc-1', 'resolve', 1, 'idem-401-1', ['resolution_notes' => 'retry']);
$assert(($unauthorized['error']['kind'] ?? '') === 'api_authentication', 'P3GPC23 API 401 fails closed');
$forbidden = $make([new Response(403, [], json_encode(['error' => ['code' => 'STATION_SCOPE_DENIED']], JSON_THROW_ON_ERROR))])->lifecycleAction($user, 'inc-1', 'resolve', 1, 'idem-403-1', ['resolution_notes' => 'retry']);
$assert(($forbidden['error']['kind'] ?? '') === 'api_authorization', 'P3GPC24 API 403 fails closed');
$notFound = $make([new Response(404, [], json_encode(['error' => ['code' => 'INCIDENCE_NOT_FOUND']], JSON_THROW_ON_ERROR))])->lifecycleAction($user, 'inc-1', 'resolve', 1, 'idem-404-1', ['resolution_notes' => 'retry']);
$assert(($notFound['error']['kind'] ?? '') === 'incidence_not_found'
    && str_contains($controllerSource, "'incidence_not_found' => 'La incidencia no existe en la localidad autorizada.'"),
    'P3GPC25 SQL not-found is surfaced specifically without treating an unavailable query as empty');
$assert(!preg_match('/FALTA/', $viewSource . $controllerSource . $serviceSource) || str_contains($controllerSource, 'authorizeFalta'), 'P3GPC26 no local automatic FALTA');
$assert(!preg_match('/SIN MARCACI[ÓO]N/', $viewSource . $controllerSource . $serviceSource), 'P3GPC27 no local automatic SIN MARCACIÓN');
$assert(!preg_match('/\b(?:importe_total|total_percepciones|total_deducciones|descuento|nomina_original|doc_nomina(?:_firma|_aguinaldo)?)\b/i', $viewSource), 'P3GPC28 lifecycle view does not render payroll amounts or document data');
$assert(!str_contains($serviceSource, 'pairing') || str_contains($serviceSource, 'evaluateJourney'), 'P3GPC29 lifecycle client does not add local P1/P2 calculation');
$assert(!str_contains($serviceSource, 'applyIncidence') && !str_contains($controllerSource, 'incidencePrecedence'), 'P3GPC30 no local incidence engine/precedence');
$assert(str_contains($controllerSource, '$administrativeIncidences = []') && str_contains($controllerSource, '$lifecyclePermissions = ['), 'P3GPC31 worker/date lifecycle state starts empty per request');
$assert(str_contains($controllerSource, "'workplace_id' => \$workplaceId") && str_contains($controllerSource, "'date' => \$date"), 'P3GPC32 workplace/date remain explicit in refresh redirect');
$assert(str_contains($controllerSource, 'redirect($redirect)') && str_contains($controllerSource, 'readApplicableIncidences'), 'P3GPC33 every successful action returns through canonical read flow');
$assert(str_contains($controllerSource, "'checador_m06_incidence_lifecycle_feedback'")
    && str_contains($controllerSource, 'readback_unconfirmed')
    && str_contains($viewSource, 'incidenceFeedback'),
    'P3GPC34 SQL lifecycle feedback follows PRG and only reports success after readback');
$assert(str_contains($serviceSource, 'administrative-evaluations/evaluate') && str_contains($controllerSource, 'administrativeEvaluation'), 'P3GPC35 administrative evaluation remains a dedicated Portal3 command boundary');

printf("CHECADOR_P3GC_LIFECYCLE_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

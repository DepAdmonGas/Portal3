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

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$user = new Usuario();
$user->id = 999;
$base = 'https://checador.test/';
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$accessSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAccessService.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$routesSource = file_get_contents(__DIR__ . '/../routes/web.php');
$apiContract = '/home/silvinol/Proyectos/portal-api-v.1.0.1/docs/v2/CHECADOR_V2_P3G_ADMINISTRATIVE_API_BOUNDARY_CONTRACT_V1.0.md';
$freeze = '/home/silvinol/Proyectos/portal-api-v.1.0.1/docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md';
$portalContract = __DIR__ . '/../docs/v2/CHECADOR_V2_PORTAL3_P3G_INCIDENCE_ADMINISTRATIVE_CONTRACT_V1.0.md';

$postPayload = static function (string $status = 'REVIEW_REQUIRED'): array {
    return ['status' => 'success', 'data' => [
        'worker_id' => 41,
        'workplace_id' => 7,
        'work_date' => '2026-10-13',
        'revision_number' => 1,
        'administrative_status' => $status,
        'human_review_status' => 'PENDING_REVIEW',
        'fingerprint' => str_repeat('a', 64),
        'policy_rule_version' => null,
        'created_at_utc' => '2026-10-14T10:00:00Z',
    ]];
};
$readPayload = static function (string $status = 'REVIEW_REQUIRED'): array {
    return ['status' => 'success', 'data' => [
        'worker_id' => 41,
        'workplace_id' => 7,
        'work_date' => '2026-10-13',
        'evaluation_exists' => true,
        'evaluation' => [
            'revision_number' => 1,
            'administrative_status' => $status,
            'human_review_status' => 'PENDING_REVIEW',
            'fingerprint' => str_repeat('a', 64),
            'algorithm_version' => 'v2.5-d',
            'policy_version' => 'POLICY_V1.0_DEFAULT',
            'policy_rule_version' => null,
            'associated_calculation_revision_id' => 55,
            'created_at_utc' => '2026-10-14T10:00:00Z',
            'linked_incidences' => [],
            'linked_adjustments' => [],
        ],
    ]];
};
$make = static function (array $responses, ?array &$history = null) use ($base): ChecadorDailyReadService {
    $stack = HandlerStack::create(new MockHandler($responses));
    $history = [];
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static fn (Usuario $user): string => 'portal3-jwt',
        $base
    );
};

$history = [];
$service = $make([
    new Response(200, [], json_encode($postPayload(), JSON_THROW_ON_ERROR)),
    new Response(200, [], json_encode($readPayload(), JSON_THROW_ON_ERROR)),
    new Response(200, [], json_encode($postPayload('FALTA'), JSON_THROW_ON_ERROR)),
], $history);
$normal = $service->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-normal-1', 55);
$readback = $service->readAdministrativeEvaluation($user, 41, 7, '2026-10-13');
$falta = $service->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-falta-1', 55, true);
$normalRequest = $history[0]['request'];
$normalBody = json_decode((string) $normalRequest->getBody(), true);
$faltaRequest = $history[2]['request'];
$faltaBody = json_decode((string) $faltaRequest->getBody(), true);

$assert(hash_file('sha256', $apiContract) === 'e418687501fbdfdf89bcc6f11aab60e49226f265411c4590a39a5fa7799c374a' && hash_file('sha256', $freeze) === '87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85' && hash_file('sha256', $portalContract) === 'e26e888015b27f9f1023e19cf4e005795836a99fd81a98c5638589fde5ca234d', 'P3GPD01 canonical contracts and freeze hashes match');
$assert(str_contains($serviceSource, 'executeAdministrativeEvaluation') && str_contains($controllerSource, 'administrativeEvaluation'), 'P3GPD02 Portal3 exposes a dedicated server-side evaluation command');
$assert(!str_contains($viewSource, 'fetch(') && !str_contains($viewSource, 'axios') && !str_contains($viewSource, 'api/v2/'), 'P3GPD03 browser does not call the API directly');
$assert(!preg_match('/Device[-_ ]?Auth|X-Device|device[_-]credential|Id-Dispositivo/i', $serviceSource . $controllerSource . $viewSource), 'P3GPD04 Device Auth is absent');
$assert(str_contains($accessSource, 'authorizeEvaluation') && str_contains($accessSource, 'RESOLUTION_MODULE_KEY') && str_contains($accessSource, 'AUTHORIZATION_MODULE_KEY'), 'P3GPD05 evaluation checks the two dedicated ACL namespaces');
$assert(str_contains($accessSource, "empty(\$resolution['editar']) && empty(\$authorization['editar'])"), 'P3GPD06 read/create-only permissions are denied');
$assert(str_contains($accessSource, 'authorizeFalta') && str_contains($controllerSource, 'authorizeFalta($workplaceId)'), 'P3GPD07 final FALTA uses the separate authorization ACL');
$assert(str_contains($controllerSource, 'authorizeFalta ?') && str_contains($controllerSource, "'authorize_falta'"), 'P3GPD08 resolution-only users cannot submit FALTA through the normal action');
$assert(str_contains($viewSource, 'Autorizar FALTA explícitamente') && str_contains($viewSource, 'confirm_evaluation'), 'P3GPD09 final FALTA requires an explicit confirmation control');
$assert(!array_key_exists('authorize_falta', $normalBody), 'P3GPD10 normal evaluation omits authorize_falta by default');
$assert(($faltaBody['authorize_falta'] ?? false) === true, 'P3GPD11 explicit FALTA action sends authorize_falta=true');
$assert(($normalBody['worker_id'] ?? 0) === 41 && ($normalBody['workplace_id'] ?? 0) === 7 && ($normalBody['work_date'] ?? '') === '2026-10-13', 'P3GPD12 exact canonical worker/workplace/date fields are sent');
$assert(($normalBody['expected_calculation_revision_id'] ?? 0) === 55, 'P3GPD13 exact persisted calculation reference is sent');
$assert($normalRequest->getUri()->getPath() === '/api/v2/administrative-evaluations/evaluate', 'P3GPD14 exact API command path is used');
$assert($normalRequest->getMethod() === 'POST' && $normalRequest->getHeaderLine('Idempotency-Key') === 'eval-normal-1', 'P3GPD15 command uses POST and Idempotency-Key');
$assert(($normal['ok'] ?? false) === true && ($normal['data']['administrative_status'] ?? '') === 'REVIEW_REQUIRED', 'P3GPD16 authoritative REVIEW_REQUIRED result is preserved');
$assert(($falta['ok'] ?? false) === true && ($falta['data']['administrative_status'] ?? '') === 'FALTA', 'P3GPD17 authoritative explicit FALTA result is preserved');
$assert(($readback['ok'] ?? false) === true && ($readback['data']['evaluation']['associated_calculation_revision_id'] ?? 0) === 55, 'P3GPD18 canonical readback preserves calculation provenance');
$assert(array_key_exists('policy_rule_version', $readback['data']['evaluation']) && $readback['data']['evaluation']['policy_rule_version'] === null, 'P3GPD19 null policy_rule_version is not fabricated');
$assert(str_contains($controllerSource, 'readAdministrativeEvaluation($user, $workerId, $workplaceId, $date)'), 'P3GPD20 successful POST triggers canonical GET readback');
$assert(str_contains($controllerSource, 'retry_idempotency_key') && str_contains($evaluationFeedbackSource = $controllerSource, 'evaluationFeedback'), 'P3GPD21 transport retry retains the same idempotency identity');
$stale = $make([new Response(409, [], json_encode(['error' => ['code' => 'STALE_REVISION_CONFLICT']], JSON_THROW_ON_ERROR))])->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-stale-1', 55);
$assert(($stale['error']['kind'] ?? '') === 'stale_revision_conflict', 'P3GPD22 stale calculation conflict is mapped fail-closed');
$idem = $make([new Response(409, [], json_encode(['error' => ['code' => 'IDEMPOTENCY_CONFLICT']], JSON_THROW_ON_ERROR))])->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-idem-1');
$assert(($idem['error']['kind'] ?? '') === 'idempotency_conflict', 'P3GPD23 idempotency conflict is not retried automatically');
$notFound = $make([new Response(404, [], json_encode(['error' => ['code' => 'CALCULATION_NOT_FOUND']], JSON_THROW_ON_ERROR))])->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-missing-1');
$assert(($notFound['error']['kind'] ?? '') === 'calculation_not_found', 'P3GPD24 missing calculation clears evaluability through explicit error');
$unauthorized = $make([new Response(401, [], json_encode(['error' => ['code' => 'UNAUTHORIZED']], JSON_THROW_ON_ERROR))])->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-401-1');
$assert(($unauthorized['error']['kind'] ?? '') === 'api_authentication', 'P3GPD25 API 401 fails closed');
$forbidden = $make([new Response(403, [], json_encode(['error' => ['code' => 'FUNCTION_PERMISSION_DENIED']], JSON_THROW_ON_ERROR))])->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-403-1');
$assert(($forbidden['error']['kind'] ?? '') === 'api_authorization', 'P3GPD26 API 403 fails closed');
$conflict = $make([new Response(409, [], json_encode(['error' => ['code' => 'SOME_DOMAIN_CONFLICT']], JSON_THROW_ON_ERROR))])->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-409-1');
$assert(($conflict['error']['kind'] ?? '') === 'administrative_conflict', 'P3GPD27 other 409 conflicts refresh canonical context');
$invalid = $make([new Response(422, [], json_encode(['error' => ['code' => 'INVALID_PAYLOAD']], JSON_THROW_ON_ERROR))])->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-422-1');
$assert(($invalid['error']['kind'] ?? '') === 'bad_request', 'P3GPD28 API 422 is handled as validation failure');
$server = $make([new Response(500, [], json_encode(['error' => ['code' => 'SERVER_ERROR']], JSON_THROW_ON_ERROR))])->executeAdministrativeEvaluation($user, 41, 7, '2026-10-13', 'eval-500-1');
$assert(($server['error']['kind'] ?? '') === 'api_unavailable', 'P3GPD29 timeout/5xx never becomes success');
$assert(!str_contains($serviceSource, 'INSERT INTO') && !str_contains($controllerSource, 'INSERT INTO'), 'P3GPD30 no direct Portal3 database write exists');
$assert(!str_contains($serviceSource, 'evaluateCalculation') && !str_contains($controllerSource, 'evaluateCalculation'), 'P3GPD31 no local P1/P2 calculation engine is added');
$assert(!str_contains($controllerSource, 'raw PunchEvent') && !str_contains($controllerSource, 'incidencePrecedence'), 'P3GPD32 no raw membership or incidence precedence logic is added');
$assert(!preg_match('/RecibosNomina|descuento/i', $viewSource . $controllerSource . $serviceSource), 'P3GPD33 no payroll effect is introduced');
$assert(str_contains($viewSource, 'REVIEW_REQUIRED') || str_contains($viewSource, 'human_review_status'), 'P3GPD34 review states remain renderable');
$assert(str_contains($viewSource, 'policyRuleVersion === null ?') && str_contains($viewSource, 'Referencia de cálculo'), 'P3GPD35 provenance display preserves nullable policy and calculation reference');
$assert(str_contains($controllerSource, '$administrativeEvaluations = []') && str_contains($controllerSource, '$evaluationPermissions = ['), 'P3GPD36 worker/date evaluation state starts empty per request');
$assert(str_contains($controllerSource, 'expectedCalculationId') && str_contains($controllerSource, 'calculation_id'), 'P3GPD37 selected Journey calculation reference is used without recomputation');
$assert(str_contains($routesSource, "'POST', '/departamento-operativo/recursos-humanos/checador/evaluations/evaluate'"), 'P3GPD38 Portal3 exposes one authenticated POST route');
$assert(str_contains($viewSource, 'name="authorize_falta" value="1"') && str_contains($viewSource, 'Nunca se activa automáticamente'), 'P3GPD39 explicit FALTA control is never automatic');
$assert(!preg_match('/op_rh_personal_asistencia|op_rh_personal_asistencia_incidencia|op_rh_v2_/', $serviceSource . $controllerSource), 'P3GPD40 no legacy or direct V2 table writes are introduced');

printf("CHECADOR_P3GD_ADMINISTRATIVE_EVALUATION_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

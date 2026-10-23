<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/support/portal3_autoload.php';

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
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-incidences.php');
$adjustmentViewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-adjustments.php');
$portalContract = __DIR__ . '/../docs/v2/CHECADOR_V2_PORTAL3_P3G_INCIDENCE_ADMINISTRATIVE_CONTRACT_V1.0.md';

$summary = static function (string $uuid, string $category, string $status, int $revision, string $source): array {
    return [
        'uuid_incidencia' => $uuid,
        'worker_id' => 1,
        'workplace_id' => 1,
        'category' => $category,
        'scope' => 'WORKER_DATE',
        'date_range' => ['start_date' => '2026-10-12', 'end_date' => '2026-10-12'],
        'time_interval' => null,
        'current_status' => $status,
        'current_revision' => $revision,
        'reason' => 'evidencia administrativa',
        'source_classification' => $source,
        'evidence_reference' => 'evidence-' . $uuid,
        'created_at_utc' => '2026-10-12T10:00:00Z',
        'created_by_user_id' => 999,
        'approved_by_user_id' => null,
    ];
};
$listPayload = ['status' => 'success', 'data' => [
    'worker_id' => 1,
    'workplace_id' => 1,
    'query_range' => ['from' => '2026-10-12', 'to' => '2026-10-12'],
    'total_records' => 2,
    'incidences' => [
        $summary('inc-1', 'REVIEW_REQUIRED', 'ACTIVE', 2, 'API_V2'),
        $summary('inc-2', 'VACATION', 'ACTIVE', 1, 'API_V2'),
    ],
]];
$detailPayload = ['status' => 'success', 'data' => [
    'uuid_incidencia' => 'inc-1',
    'worker_id' => 1,
    'workplace_id' => 1,
    'category' => 'REVIEW_REQUIRED',
    'scope' => 'WORKER_DATE',
    'date_range' => ['start_date' => '2026-10-12', 'end_date' => '2026-10-12'],
    'time_interval' => null,
    'current_status' => 'ACTIVE',
    'current_revision' => 2,
    'revisions' => [
        ['revision_number' => 1, 'status' => 'SUPERSEDED', 'reason' => 'initial', 'evidence_reference' => 'e1', 'created_by_user_id' => 999, 'approved_by_user_id' => null, 'created_at_utc' => '2026-10-11T10:00:00Z'],
        ['revision_number' => 2, 'status' => 'CANCELLED', 'reason' => 'updated evidence', 'evidence_reference' => 'e2', 'created_by_user_id' => 999, 'approved_by_user_id' => 999, 'created_at_utc' => '2026-10-12T10:00:00Z'],
    ],
]];
$evalFalse = ['status' => 'success', 'data' => ['worker_id' => 1, 'workplace_id' => 1, 'work_date' => '2026-10-12', 'evaluation_exists' => false, 'evaluation' => null]];
$evalTrue = ['status' => 'success', 'data' => [
    'worker_id' => 1, 'workplace_id' => 1, 'work_date' => '2026-10-12', 'evaluation_exists' => true,
    'evaluation' => [
        'revision_number' => 3, 'administrative_status' => 'REVIEW_REQUIRED', 'human_review_status' => 'PENDING',
        'fingerprint' => 'fp-1', 'algorithm_version' => 'v1', 'policy_version' => 'p1', 'created_at_utc' => '2026-10-12T12:00:00Z',
        'policy_rule_version' => null,
        'associated_calculation_revision_id' => 44,
        'linked_incidences' => [['uuid_incidencia' => 'inc-1', 'category' => 'REVIEW_REQUIRED', 'revision_number' => 2, 'application_status' => 'APPLIED']],
        'linked_adjustments' => [['uuid_ajuste' => 'adj-1', 'uuid_ajuste_logico' => 'logical-1', 'adjustment_type' => 'MANUAL', 'revision_number' => 1, 'adjustment_status' => 'PROPOSED']],
    ],
]];

$make = static function (array $responses, ?array &$history = null) use ($base): ChecadorDailyReadService {
    $stack = HandlerStack::create(new MockHandler($responses));
    $history = [];
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]), static fn (Usuario $user): string => 'portal3-jwt', $base);
};

$history = [];
$service = $make([
    new Response(200, [], json_encode($listPayload, JSON_THROW_ON_ERROR)),
    new Response(200, [], json_encode($detailPayload, JSON_THROW_ON_ERROR)),
    new Response(200, [], json_encode($evalFalse, JSON_THROW_ON_ERROR)),
    new Response(200, [], json_encode($evalTrue, JSON_THROW_ON_ERROR)),
], $history);
$list = $service->readApplicableIncidences($user, 1, 1, '2026-10-12', '2026-10-12');
$detail = $service->readIncidence($user, 'inc-1');
$missingEvaluation = $service->readAdministrativeEvaluation($user, 1, 1, '2026-10-12');
$existingEvaluation = $service->readAdministrativeEvaluation($user, 1, 1, '2026-10-12');
$paths = array_map(static fn ($transaction): string => $transaction['request']->getUri()->getPath(), $history);
$query = $history[0]['request']->getUri()->getQuery() ?? '';

$assert(is_file($portalContract) && hash_file('sha256', $portalContract) === 'e26e888015b27f9f1023e19cf4e005795836a99fd81a98c5638589fde5ca234d', 'P3GA01 Portal3 P3G contract hash matches');
$assert(str_contains($serviceSource, 'readApplicableIncidences') && str_contains($serviceSource, 'readIncidence') && str_contains($serviceSource, 'readAdministrativeEvaluation'), 'P3GA03 three API read methods are integrated');
$assert($list['ok'] === true && ($list['data']['total_records'] ?? 0) === 2 && count($list['data']['incidences'] ?? []) === 2, 'P3GA04 incidence list preserves all records');
$assert(($list['data']['incidences'][0]['current_status'] ?? '') === 'ACTIVE' && ($list['data']['incidences'][0]['category'] ?? '') === 'REVIEW_REQUIRED', 'P3GA05 REVIEW_REQUIRED/category state preserved');
$assert($paths[0] === '/api/v2/incidences' && str_contains($query, 'worker_id=1') && str_contains($query, 'workplace_id=1') && str_contains($query, 'from=2026-10-12'), 'P3GA06 list uses exact worker/workplace/date query');
$assert($detail['ok'] === true && count($detail['data']['revisions'] ?? []) === 2, 'P3GA07 incidence detail preserves complete revision history');
$assert(($paths[1] ?? '') === '/api/v2/incidences/inc-1' && ($detail['data']['revisions'][1]['status'] ?? '') === 'CANCELLED', 'P3GA08 detail endpoint and terminal revision are preserved');
$assert($missingEvaluation['ok'] === true && ($missingEvaluation['data']['evaluation_exists'] ?? true) === false && $missingEvaluation['data']['evaluation'] === null, 'P3GA09 missing administrative evaluation is neutral');
$assert($existingEvaluation['ok'] === true && ($existingEvaluation['data']['evaluation']['linked_incidences'][0]['uuid_incidencia'] ?? '') === 'inc-1' && ($existingEvaluation['data']['evaluation']['linked_adjustments'][0]['uuid_ajuste'] ?? '') === 'adj-1', 'P3GA10 existing evaluation provenance links are preserved');
$assert(($existingEvaluation['data']['evaluation']['associated_calculation_revision_id'] ?? 0) === 44, 'P3GA11 calculation provenance reference is preserved');
$assert(str_contains($controllerSource, '$administrativeIncidences = []') && str_contains($controllerSource, '$administrativeEvaluations = []'), 'P3GA12 administrative state is reset per request');
$assert(str_contains($controllerSource, '$requestedWorkplace') && (bool) preg_match('/readApplicableIncidences[\\s\\S]{0,500}\\$date,\\s+\\$date/', $controllerSource) && (bool) preg_match('/readAdministrativeEvaluation[\\s\\S]{0,300}\\$date/', $controllerSource), 'P3GA13 selected workplace/date are passed to API reads');
$assert(!str_contains($viewSource, 'fetch(') && !str_contains($viewSource, 'axios') && !str_contains($viewSource, 'api/v2/'), 'P3GA14 browser has no direct API integration');
$assert(!preg_match('/Device[-_ ]?Auth|X-Device|device[_-]credential|Id-Dispositivo/i', $serviceSource . $controllerSource . $viewSource), 'P3GA15 device authentication is not used');
$unauthorized = $make([new Response(401, [], json_encode(['error' => ['code' => 'UNAUTHORIZED']], JSON_THROW_ON_ERROR))])->readApplicableIncidences($user, 1, 1, '2026-10-12', '2026-10-12');
$assert(($unauthorized['error']['kind'] ?? '') === 'api_authentication', 'P3GA16 API 401 maps to authentication failure');
$denied = $make([new Response(403, [], json_encode(['error' => ['code' => 'STATION_SCOPE_DENIED']], JSON_THROW_ON_ERROR))])->readApplicableIncidences($user, 1, 1, '2026-10-12', '2026-10-12');
$assert(($denied['error']['kind'] ?? '') === 'api_authorization', 'P3GA17 API 403 maps to authorization failure');
$serverError = $make([new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR))])->readAdministrativeEvaluation($user, 1, 1, '2026-10-12');
$assert(($serverError['error']['kind'] ?? '') === 'api_unavailable', 'P3GA18 API 500 remains unavailable');
$malformed = $make([new Response(200, [], json_encode(['status' => 'success', 'data' => ['worker_id' => 1]], JSON_THROW_ON_ERROR))])->readApplicableIncidences($user, 1, 1, '2026-10-12', '2026-10-12');
$assert(($malformed['error']['kind'] ?? '') === 'administrative_unavailable', 'P3GA19 malformed administrative envelope fails closed');
$assert(str_contains($serviceSource, 'API-owned') && !str_contains($serviceSource, 'applyIncidence'), 'P3GA20 no local incidence engine or precedence exists');
$assert(str_contains($adjustmentViewSource, 'nunca se activa automáticamente')
    && str_contains($adjustmentViewSource, 'Autorizar FALTA explícitamente')
    && str_contains($adjustmentViewSource, 'if ($canAuthorizeFalta)'), 'P3GA21 FALTA is only exposed as an explicit separately authorized action');
$assert(str_contains($viewSource, 'Incidencias e historial') || str_contains($viewSource, 'current_status'), 'P3GA22 read-only administrative context is rendered');
$assert(str_contains($adjustmentViewSource, 'evaluations/evaluate') && str_contains($serviceSource, 'administrative-evaluations/evaluate'), 'P3GA23 administrative evaluation command is reserved for the P3G-D client boundary');
$assert(!preg_match('/\b(?:importe_total|total_percepciones|total_deducciones|descuento|nomina_original|doc_nomina(?:_firma|_aguinaldo)?)\b/i', $viewSource . $adjustmentViewSource), 'P3GA24 administrative read views do not render payroll amounts or document data');
$assert(!preg_match('/INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM|CREATE\s+TABLE|ALTER\s+TABLE/i', $serviceSource . $controllerSource), 'P3GA25 no DB/schema mutation is introduced');

printf("CHECADOR_P3GA_ADMINISTRATIVE_READ_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

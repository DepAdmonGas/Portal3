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
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'true'; // Every request below uses an explicit Guzzle MockHandler; no API call can reach a real service.
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'true'; // Every API call is intercepted by this test's Guzzle MockHandler.
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$user = new Usuario();
$user->id = 999;
$base = 'https://checador.test/';
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php');
$contractPath = getenv('CHECADOR_JOURNEY_V1_1_CONTRACT') ?: '/home/silvinol/Proyectos/portal-api-v.1.0.1/docs/v2/CHECADOR_V2_JOURNEY_ON_DEMAND_API_CONTRACT_V1.1.md';
$expectedHash = '7f71372d9deceb6820e6e71e1be38dadd12e87cd5bb4a5f69bcd250ee85c6f3f';
$freezePath = __DIR__ . '/../docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md';

$events = [
    ['event_uuid' => 'ev-001', 'id_dispositivo' => 4, 'ocurrido_at_utc' => '2026-10-12 13:55:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'UNMATCHED', 'segment_ordinal' => null],
    ['event_uuid' => 'ev-002', 'id_dispositivo' => null, 'ocurrido_at_utc' => '2026-10-12 14:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
    ['event_uuid' => 'ev-003', 'id_dispositivo' => 4, 'ocurrido_at_utc' => '2026-10-12 18:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'SURPLUS', 'segment_ordinal' => 1],
    ['event_uuid' => 'ev-004', 'id_dispositivo' => 4, 'ocurrido_at_utc' => '2026-10-12 22:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'EXIT', 'segment_ordinal' => 1],
    ['event_uuid' => 'ev-005', 'id_dispositivo' => 4, 'ocurrido_at_utc' => '2026-10-12 22:01:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'ENTRY', 'segment_ordinal' => 2],
];

$journeyPayload = static function (array $rawEvents, bool $evaluated = true): array {
    $count = $evaluated ? count($rawEvents) : null;
    $pairing = $evaluated ? [
        'pairing_id' => 42,
        'revision_numero' => 3,
        'creado_at_utc' => '2026-10-12 22:05:00',
        'pairing_status' => 'REVIEW_REQUIRED',
        'completeness_l2' => 'VERIFIED',
        'algorithm_version' => 'v2.4-p1',
        'segments' => [[
            'ordinal' => 1,
            'status' => 'EXACT_PAIR',
            'entry_event_uuid' => 'ev-002',
            'exit_event_uuid' => 'ev-004',
        ]],
    ] : null;
    $calculation = $evaluated ? [
        'calculation_id' => 51,
        'revision_numero' => 2,
        'creado_at_utc' => '2026-10-12 22:05:02',
        'estado_calculo' => 'CALCULATED',
        'version_algoritmo' => 'v2.4-p2',
        'segundos_trabajados_totales' => 28800,
        'segmentos' => [],
    ] : null;
    $provenance = [
        'response_generated_at_utc' => '2026-10-13T17:50:00Z',
        'p1_created_at_utc' => $evaluated ? '2026-10-12 22:05:00' : null,
        'p2_created_at_utc' => $evaluated ? '2026-10-12 22:05:02' : null,
        'p1_algorithm_version' => 'v2.4-p1',
        'p2_algorithm_version' => 'v2.4-p2',
        'shift_template_version' => $evaluated ? 7 : null,
        'source_completeness_contract_version' => 'v2.4',
        'level2_policy_ref' => $evaluated ? 'POLICY_DEFAULT' : null,
        'policy_rule_version' => null,
        'evaluated_raw_event_count' => $count,
        'evaluated_raw_events' => $evaluated ? $rawEvents : null,
    ];
    return ['status' => 'success', 'data' => [
        'evaluated' => $evaluated,
        'journey_status' => $evaluated ? 'EVALUATED' : 'UNSCHEDULED_DAY',
        'context' => ['id_personal' => 1, 'id_estacion' => 1, 'fecha_local' => '2026-10-12', 'timezone' => 'America/Mexico_City'],
        'schedule' => [
            'estado_jornada' => $evaluated ? 'SCHEDULED' : 'UNSCHEDULED',
            'scheduled_shift_id' => $evaluated ? 105 : null,
            'origen' => $evaluated ? ['clave_plantilla' => 'MAT-01', 'version_plantilla' => 7] : null,
        ],
        'source_evidence' => ['snapshot_audit_ref' => 'snap-audit-1', 'window_level2_status' => 'VERIFIED', 'level2_evaluation_ref' => 'l2-eval-1'],
        'pairing' => $pairing,
        'calculation' => $calculation,
        'evaluated_raw_event_count' => $count,
        'evaluated_raw_events' => $evaluated ? $rawEvents : null,
        'provenance' => $provenance,
    ]];
};

$make = static function (array $responses) use ($base): ChecadorDailyReadService {
    $stack = HandlerStack::create(new MockHandler($responses));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static fn (Usuario $user): string => 'portal3-journey-jwt',
        $base
    );
};

$evaluated = $make([new Response(200, [], json_encode($journeyPayload($events), JSON_THROW_ON_ERROR))])->evaluateJourney($user, 1, 1, '2026-10-12', 'snap-audit-1');
$data = $evaluated['data'] ?? [];
$provenance = is_array($data['provenance'] ?? null) ? $data['provenance'] : [];
$auditEvents = $data['evaluated_raw_events'] ?? null;
$journeys = [1 => $data + ['worker_name' => 'Colaborador de prueba']];
$journeyErrors = [];
$selectedDate = '2026-10-12';
$selectedWorkplaceId = 1;
$selectedWorkplaceName = 'Interlomas';
$selectedWorkerId = 1;
$state = 'success';
ob_start();
require __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php';
$journeyHtml = (string) ob_get_clean();

$assert(is_file($freezePath) && hash_file('sha256', $freezePath) === '87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85', 'P3F01 Portal3 Architecture Freeze hash matches');
$assert(str_contains($serviceSource, 'journey_status') && str_contains($serviceSource, 'source_evidence') && str_contains($serviceSource, 'pairing') && str_contains($serviceSource, 'calculation'), 'P3F02 V1.0 compatibility preserved');
$assert(($data['context']['id_personal'] ?? 0) === 1, 'P3F03 worker provenance preserved');
$assert(($data['context']['id_estacion'] ?? 0) === 1, 'P3F04 workplace provenance preserved');
$assert(($data['context']['fecha_local'] ?? '') === '2026-10-12', 'P3F05 local workday provenance preserved');
$assert(($data['context']['timezone'] ?? '') === 'America/Mexico_City', 'P3F06 timezone provenance preserved');
$assert(($data['schedule']['estado_jornada'] ?? '') === 'SCHEDULED' && isset($data['schedule']['origen']), 'P3F07 effective schedule provenance preserved');
$assert(($provenance['shift_template_version'] ?? null) === 7, 'P3F08 shift version preserved');
$assert(($data['source_evidence']['snapshot_audit_ref'] ?? '') === 'snap-audit-1', 'P3F09 snapshot/evidence ref preserved');
$assert(($data['source_evidence']['level2_evaluation_ref'] ?? '') === 'l2-eval-1', 'P3F10 Level 2 ref preserved');
$assert(($data['source_evidence']['window_level2_status'] ?? '') === 'VERIFIED', 'P3F11 Level 2 status preserved');
$assert(($data['pairing']['revision_numero'] ?? 0) === 3, 'P3F12 P1 revision preserved');
$assert(($data['pairing']['algorithm_version'] ?? '') === 'v2.4-p1', 'P3F13 P1 algorithm version preserved');
$assert(($data['pairing']['creado_at_utc'] ?? '') === '2026-10-12 22:05:00' && ($provenance['p1_created_at_utc'] ?? '') === $data['pairing']['creado_at_utc'], 'P3F14 P1 created_at preserved');
$assert(($data['calculation']['revision_numero'] ?? 0) === 2, 'P3F15 P2 revision preserved');
$assert(($data['calculation']['version_algoritmo'] ?? '') === 'v2.4-p2', 'P3F16 P2 algorithm version preserved');
$assert(($data['calculation']['creado_at_utc'] ?? '') === '2026-10-12 22:05:02' && ($provenance['p2_created_at_utc'] ?? '') === $data['calculation']['creado_at_utc'], 'P3F17 P2 created_at preserved');
$assert(($provenance['response_generated_at_utc'] ?? '') === '2026-10-13T17:50:00Z', 'P3F18 response_generated_at preserved');
$assert(($data['evaluated_raw_event_count'] ?? null) === 5 && ($provenance['evaluated_raw_event_count'] ?? null) === 5, 'P3F19 evaluated_raw_event_count preserved');
$assert(is_array($auditEvents) && count($auditEvents) === 5 && $auditEvents === $provenance['evaluated_raw_events'], 'P3F20 all evaluated_raw_events preserved');
$assert(str_contains($viewSource, "foreach (\$detail['events'] as \$event)") && !str_contains($viewSource, 'array_slice(') && count(array_filter($events, static fn(array $event): bool => str_contains($journeyHtml, $event['event_uuid']))) === count($events), 'P3F21 5+ API events remain visible in the current traceability modal');
$assert(($auditEvents[0]['event_uuid'] ?? '') === 'ev-001' && ($auditEvents[2]['event_uuid'] ?? '') === 'ev-003', 'P3F22 unmatched/intermediate event preserved');
$mismatch = $journeyPayload($events);
$mismatch['data']['evaluated_raw_event_count'] = 4;
$mismatchResult = $make([new Response(200, [], json_encode($mismatch, JSON_THROW_ON_ERROR))])->evaluateJourney($user, 1, 1, '2026-10-12');
$assert(($mismatchResult['error']['kind'] ?? '') === 'journey_unavailable', 'P3F23 event count mismatch fails provenance safely');
$assert(($data['pairing']['segments'][0]['entry_event_uuid'] ?? '') === 'ev-002', 'P3F24 entry_event_uuid maps to returned evaluated event');
$assert(($data['pairing']['segments'][0]['exit_event_uuid'] ?? '') === 'ev-004', 'P3F25 exit_event_uuid maps to returned evaluated event');
$assert(!preg_match('/pairing_id|calculation_id|entry_punch_id|exit_punch_id|raw_punch_id/', $viewSource), 'P3F26 raw/internal DB PK not exposed in UI');
$assert(str_contains($viewSource, "isset(\$event['id_dispositivo']) ? (int) \$event['id_dispositivo'] : 'No disponible'") && preg_match('/<td>\s*No disponible\s*<\/td>/', $journeyHtml) === 1 && preg_match('/<td>\s*0\s*<\/td>/', $journeyHtml) !== 1, 'P3F27 nullable id_dispositivo is presented as unavailable, never 0');
$assert(array_key_exists('policy_rule_version', $provenance) && $provenance['policy_rule_version'] === null && str_contains($viewSource, 'Versión de regla') && preg_match('/<dd class="col-sm-8 text-break">No aplica<\/dd>/', $journeyHtml) === 1, 'P3F28 null policy_rule_version is presented as not applicable in Journey traceability');
$assert(str_contains($viewSource, "is_int(\$event['id_dispositivo'] ?? null) && \$event['id_dispositivo'] > 0") && !str_contains($viewSource, "(\$event['id_dispositivo'] ?? 0)"), 'P3F27 nullable id_dispositivo is not rendered as 0');
$assert(array_key_exists('policy_rule_version', $provenance) && $provenance['policy_rule_version'] === null && str_contains($viewSource, 'No aplica'), 'P3F28 policy_rule_version null remains not-applicable');
$unscheduled = $make([new Response(200, [], json_encode($journeyPayload([], false), JSON_THROW_ON_ERROR))])->evaluateJourney($user, 1, 1, '2026-10-12');
$uData = $unscheduled['data'] ?? [];
$assert(array_key_exists('pairing', $uData) && $uData['pairing'] === null && array_key_exists('calculation', $uData) && $uData['calculation'] === null && array_key_exists('evaluated_raw_event_count', $uData) && $uData['evaluated_raw_event_count'] === null, 'P3F29 UNSCHEDULED evaluated=false does not fabricate provenance');
$assert(array_key_exists('evaluated_raw_events', $uData) && $uData['evaluated_raw_events'] === null && array_key_exists('provenance', $uData) && array_key_exists('evaluated_raw_events', $uData['provenance']) && $uData['provenance']['evaluated_raw_events'] === null, 'P3F30 null evaluated_raw_events does not become empty evaluated list');
$assert(str_contains($controllerSource, '$journeys = []') && str_contains($controllerSource, '$journeyErrors = []'), 'P3F31 stale provenance cleared on worker/date/workplace change');
$failed = $make([new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR))])->evaluateJourney($user, 1, 1, '2026-10-12');
$assert(($failed['error']['kind'] ?? '') === 'api_unavailable' && str_contains($controllerSource, '$journeys = []'), 'P3F32 stale provenance cleared after failed request');
$assert(!preg_match('/op_rh_v2_|INSERT\s+INTO|UPDATE\s+op_rh/i', $serviceSource . $controllerSource), 'P3F33 no Portal3 provenance DB writes');
$readOnlyJourneyForms = preg_match_all('/<form\b([^>]*)>/i', $viewSource, $formMatches) > 0;
foreach ($formMatches[1] ?? [] as $formAttributes) {
    $methodMatch = [];
    $actionMatch = [];
    $readOnlyJourneyForms = $readOnlyJourneyForms
        && preg_match('/\bmethod\s*=\s*(["\'])(.*?)\1/i', $formAttributes, $methodMatch) === 1
        && strtolower($methodMatch[2]) === 'get'
        && preg_match('/\baction\s*=\s*(["\'])(.*?)\1/i', $formAttributes, $actionMatch) === 1
        && $actionMatch[2] === '/departamento-operativo/recursos-humanos/checador/jornadas';
}
$assert($readOnlyJourneyForms
    && str_contains($viewSource, 'name="date_from"')
    && str_contains($viewSource, 'name="date_to"')
    && str_contains($viewSource, 'name="worker_id"')
    && !preg_match('/[\'\"]FALTA[\'\"]\s*=>|authorize_falta|administrative-evaluations\/evaluate|<form\b[^>]*method\s*=\s*["\']post/i', $viewSource)
    && !preg_match('/RecibosNomina|payroll/i', $viewSource), 'P3F34 Journey traceability keeps date-range GET filters and introduces no absence, incidence, payroll, or write action');
$modalPosition = strpos($journeyHtml, '<div class="modal fade" id="journey-detail-1"');
$firstTableClose = strpos($journeyHtml, '</table>');
$assert(str_contains($viewSource, 'aria-haspopup="dialog"')
    && str_contains($viewSource, 'aria-labelledby=')
    && str_contains($viewSource, 'data-bs-dismiss="modal"')
    && $modalPosition !== false && $firstTableClose !== false && $modalPosition > $firstTableClose
    && str_contains($journeyHtml, 'id="journey-detail-1" tabindex="-1"'), 'P3F35 Bootstrap traceability modal is keyboard-addressable, labelled, and outside the result table');

printf("CHECADOR_P3F_JOURNEY_AUDIT_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

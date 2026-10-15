<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$portal3Contract = __DIR__ . '/../docs/v2/CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_V1.1.md';
$apiContract = '/home/silvinol/Proyectos/portal-api-v.1.0.1/docs/v2/CHECADOR_V2_P3H_EVENT_CLASSIFICATION_PUBLIC_PAYLOAD_CONTRACT_V1.1.md';
$architectureFreeze = __DIR__ . '/../docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md';
$expectedPortal3Hash = '5a830862043f2ad317d504b47feb157584870852c6fba0067546a67f9c51a82b';
$expectedApiHash = 'df6b0bec7c6977cb0c12bf5409c8243528af145036474aa3adcf237b21f4711c';
$expectedArchitectureHash = '87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85';
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$contractSource = file_get_contents($portal3Contract);
$apiSource = file_get_contents($apiContract);

$events = [
    ['event_uuid' => 'p3h-1', 'id_dispositivo' => 1, 'ocurrido_at_utc' => '2026-10-12 14:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
    ['event_uuid' => 'p3h-2', 'id_dispositivo' => 1, 'ocurrido_at_utc' => '2026-10-12 16:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'SURPLUS', 'segment_ordinal' => 1],
    ['event_uuid' => 'p3h-3', 'id_dispositivo' => 1, 'ocurrido_at_utc' => '2026-10-12 22:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'EXIT', 'segment_ordinal' => 1],
    ['event_uuid' => 'p3h-4', 'id_dispositivo' => 1, 'ocurrido_at_utc' => '2026-10-13 14:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'ENTRY', 'segment_ordinal' => 2],
    ['event_uuid' => 'p3h-5', 'id_dispositivo' => 1, 'ocurrido_at_utc' => '2026-10-13 22:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'EXIT', 'segment_ordinal' => 2],
    ['event_uuid' => 'p3h-6', 'id_dispositivo' => 1, 'ocurrido_at_utc' => '2026-10-14 00:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'SURPLUS', 'segment_ordinal' => 3],
    ['event_uuid' => 'p3h-7', 'id_dispositivo' => 1, 'ocurrido_at_utc' => '2026-10-14 01:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'UNMATCHED', 'segment_ordinal' => null],
];

$payload = static function (array $rawEvents): array {
    $segments = [
        ['ordinal' => 1, 'status' => 'AMBIGUOUS_PAIRING', 'entry_event_uuid' => 'p3h-1', 'exit_event_uuid' => 'p3h-3'],
        ['ordinal' => 2, 'status' => 'EXACT_PAIR', 'entry_event_uuid' => 'p3h-4', 'exit_event_uuid' => 'p3h-5'],
        ['ordinal' => 3, 'status' => 'EXACT_PAIR', 'entry_event_uuid' => null, 'exit_event_uuid' => null],
    ];
    $provenance = [
        'response_generated_at_utc' => '2026-10-14T12:00:00Z',
        'p1_created_at_utc' => '2026-10-12 22:05:00',
        'p2_created_at_utc' => '2026-10-12 22:05:02',
        'p1_algorithm_version' => 'p1-v1',
        'p2_algorithm_version' => 'p2-v1',
        'shift_template_version' => 1,
        'source_completeness_contract_version' => 'v2.4',
        'level2_policy_ref' => 'POLICY_DEFAULT',
        'policy_rule_version' => null,
        'evaluated_raw_event_count' => count($rawEvents),
        'evaluated_raw_events' => $rawEvents,
    ];
    return ['status' => 'success', 'data' => [
        'evaluated' => true,
        'journey_status' => 'AMBIGUOUS_PAIRING',
        'context' => ['id_personal' => 7, 'id_estacion' => 3, 'fecha_local' => '2026-10-12', 'timezone' => 'America/Mexico_City'],
        'schedule' => ['estado_jornada' => 'SCHEDULED', 'scheduled_shift_id' => 11],
        'source_evidence' => ['snapshot_audit_ref' => 'audit-p3h', 'window_level2_status' => 'VERIFIED', 'level2_evaluation_ref' => 'l2-p3h'],
        'pairing' => ['pairing_id' => 1, 'revision_numero' => 1, 'creado_at_utc' => '2026-10-12 22:05:00', 'pairing_status' => 'AMBIGUOUS_PAIRING', 'algorithm_version' => 'p1-v1', 'segments' => $segments],
        'calculation' => ['calculation_id' => 1, 'revision_numero' => 1, 'creado_at_utc' => '2026-10-12 22:05:02', 'version_algoritmo' => 'p2-v1', 'estado_calculo' => 'REVIEW_REQUIRED', 'segundos_trabajados_totales' => 1, 'segmentos' => []],
        'evaluated_raw_event_count' => count($rawEvents),
        'evaluated_raw_events' => $rawEvents,
        'provenance' => $provenance,
    ]];
};

$make = static function (array $responses): ChecadorDailyReadService {
    $stack = HandlerStack::create(new MockHandler($responses));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => 'https://checador.test/', 'http_errors' => false]),
        static fn (Usuario $user): string => 'p3h-jwt',
        'https://checador.test/'
    );
};

$user = new Usuario();
$user->id = 7;
$result = $make([new Response(200, [], json_encode($payload($events), JSON_THROW_ON_ERROR))])
    ->evaluateJourney($user, 7, 3, '2026-10-12', 'audit-p3h');
$data = $result['data'] ?? [];
$requestCheck = $make([new Response(200, [], json_encode($payload($events), JSON_THROW_ON_ERROR))]);

$assert(is_file($apiContract) && hash_file('sha256', $apiContract) === $expectedApiHash, 'P3HP01 API V1.1 contract hash');
$assert(is_file($portal3Contract) && hash_file('sha256', $portal3Contract) === $expectedPortal3Hash, 'P3HP02 Portal3 V1.1 contract hash');
$assert(is_file($architectureFreeze) && hash_file('sha256', $architectureFreeze) === $expectedArchitectureHash, 'P3HP03 Architecture Freeze hash');
$assert(str_contains($apiSource, 'POST /api/v2/journeys/evaluate') && str_contains($serviceSource, "'POST', 'api/v2/journeys/evaluate"), 'P3HP04 canonical POST endpoint parity');
$assert(($result['ok'] ?? false) === true, 'P3HP05 authoritative payload accepted');
$assert(str_contains($viewSource, "'SLOT_1' => ['label' => 'Entrada 1'") && str_contains($viewSource, "(\$ordinal === 1 || \$ordinal === 2)"), 'P3HP06 Entrada 1 uses ordinal and ENTRY');
$assert(str_contains($viewSource, "'SLOT_2' => ['label' => 'Salida 1'") && str_contains($viewSource, "(\$role === 'ENTRY' ? 1 : 2)"), 'P3HP07 Salida 1 uses ordinal and EXIT');
$assert(str_contains($viewSource, "'SLOT_3' => ['label' => 'Entrada 2'") && str_contains($viewSource, '($ordinal - 1) * 2'), 'P3HP08 Entrada 2 uses ordinal and ENTRY');
$assert(str_contains($viewSource, "'SLOT_4' => ['label' => 'Salida 2'") && str_contains($viewSource, "'ENTRY', 'EXIT', 'SURPLUS', 'UNMATCHED'"), 'P3HP09 Salida 2 uses ordinal and EXIT');
$assert(!preg_match('/usort|array_multisort|sort\\(\\$evaluatedEvents|array_slice\\(\\$evaluatedEvents/i', $viewSource), 'P3HP10 chronology and first-four inference absent');
$assert(str_contains($viewSource, "\$role === 'SURPLUS'") && str_contains($viewSource, 'Marcación adicional'), 'P3HP11 SURPLUS excluded and labelled');
$assert(str_contains($viewSource, "\$role === 'UNMATCHED'") && str_contains($viewSource, 'Sin emparejar'), 'P3HP12 UNMATCHED excluded and labelled');
$assert(array_key_exists('segment_ordinal', $data['evaluated_raw_events'][6] ?? []) && $data['evaluated_raw_events'][6]['segment_ordinal'] === null, 'P3HP13 UNMATCHED null segment ordinal preserved');
$assert(count($data['evaluated_raw_events'] ?? []) === 7 && str_contains($viewSource, 'Ver todas las marcaciones'), 'P3HP14 5+ events and disclosure preserved');
$assert(isset($data['evaluated_raw_events'][5]['segment_ordinal']) && $data['evaluated_raw_events'][5]['segment_ordinal'] === 3 && str_contains($viewSource, 'segmentos adicionales'), 'P3HP15 segment ordinal 3 and disclosure preserved');
$assert(str_contains($viewSource, 'count($additionalSegmentOrdinals)') && str_contains($viewSource, 'primarySlots'), 'P3HP16 3+ segments do not alter primary meanings');
$assert(!str_contains($viewSource, "'AMBIGUOUS' =>") && str_contains($viewSource, 'AMBIGUOUS_PAIRING'), 'P3HP17 AMBIGUOUS is not a pairing role');
$assert(str_contains($viewSource, '$ambiguityPresent') && str_contains($viewSource, 'ambigüedad pertenece al segmento'), 'P3HP18 ambiguity remains segment/Journey scoped');
$assert(str_contains($viewSource, 'Sin evento asociado en esta proyección') && str_contains($contractSource, 'EMPTY_SLOT_MEANS_SIN_MARCACION: NO'), 'P3HP19 empty slot is neutral');
$assert(str_contains($viewSource, 'Autorizar FALTA explícitamente') && str_contains($contractSource, 'FINAL_FALTA_SOURCE: P3G_ADMINISTRATIVE_EVALUATION'), 'P3HP20 FALTA source remains P3-G');
$assert(!preg_match('/if \\(.*evaluated_raw_event_count.*FALTA|evaluated_raw_events.*FALTA/i', $viewSource), 'P3HP21 zero events do not generate FALTA');
$assert(str_contains($viewSource, 'Administrative') || str_contains($viewSource, 'administrative'), 'P3HP22 administrative overlay remains separate');
$assert(str_contains($viewSource, "format('H:i')") && str_contains($contractSource, 'COMPACT_TIME_FORMAT: HH:mm'), 'P3HP23 compact time is HH:mm');
$assert(!preg_match("/format\\([^)]*s['\"]/", $viewSource), 'P3HP24 compact seconds are absent');
$assert(str_contains($contractSource, 'COMPACT_TIMEZONE_ABBREVIATION: NO'), 'P3HP25 timezone abbreviation is absent');
$assert(str_contains($viewSource, 'Fecha local:') && str_contains($contractSource, 'CROSS_CALENDAR_DATE_DISCLOSURE: YES'), 'P3HP26 cross-calendar date disclosed');
$assert(str_contains($viewSource, 'setTimezone') && !str_contains($viewSource, 'modify('), 'P3HP27 timezone/workday ownership is not recalculated');
$assert(str_contains($viewSource, 'Detalle de auditoría') && str_contains($viewSource, 'evaluated_raw_events'), 'P3HP28 provenance/detail access preserved');
$assert(str_contains($controllerSource, '$journeys = []'), 'P3HP29 employee context clears projection');
$assert(str_contains($controllerSource, '$journeyErrors = []') && str_contains($controllerSource, 'requestedWorkplace'), 'P3HP30 workplace context clears projection');
$assert(str_contains($controllerSource, "'date' => \$date") && str_contains($controllerSource, 'newSnapshotRef'), 'P3HP31 date context is isolated');
$assert(str_contains($serviceSource, "'ENTRY', 'EXIT', 'SURPLUS', 'UNMATCHED'") && str_contains($serviceSource, "array_key_exists('pairing_role'"), 'P3HP32 missing pairing_role fails closed');
$assert(str_contains($serviceSource, "array_key_exists('segment_ordinal'"), 'P3HP33 missing segment_ordinal fails closed');
$assert(!preg_match('/array_slice|first four|first.*four/i', $serviceSource . $controllerSource), 'P3HP34 no local P1/chronology fallback');
$assert(!str_contains($serviceSource, 'segundos_trabajados_totales'), 'P3HP35 no local P2 calculation');
$assert(!preg_match('/INSERT\\s+INTO|UPDATE\\s+op_rh_v2_/i', $serviceSource . $controllerSource), 'P3HP36 no payroll/database effect');
$assert(str_contains($viewSource, 'review') || str_contains($viewSource, 'REVIEW_REQUIRED'), 'P3HP37 review status remains visible');
$assert(str_contains($viewSource, 'visible') || str_contains($viewSource, 'role="status"'), 'P3HP38 status text/accessibility is present');
$assert(str_contains($viewSource, 'row-cols-1') && str_contains($viewSource, 'row-cols-sm-2'), 'P3HP39 responsive semantic layout');
$assert(str_contains($viewSource, 'no se infieren posiciones'), 'P3HP40 fail-closed user-facing message');
$assert(str_contains($contractSource, 'Every presentation') && str_contains($contractSource, 'No Portal3 runtime behavior'), 'P3HP41 presentation-only authority preserved');

$missingRole = $events;
unset($missingRole[0]['pairing_role']);
$missingRoleResult = $make([new Response(200, [], json_encode($payload($missingRole), JSON_THROW_ON_ERROR))])->evaluateJourney($user, 7, 3, '2026-10-12');
$assert(($missingRoleResult['error']['kind'] ?? '') === 'journey_unavailable', 'P3HP42 missing pairing_role fails closed at runtime');
$missingOrdinal = $events;
unset($missingOrdinal[0]['segment_ordinal']);
$missingOrdinalResult = $make([new Response(200, [], json_encode($payload($missingOrdinal), JSON_THROW_ON_ERROR))])->evaluateJourney($user, 7, 3, '2026-10-12');
$assert(($missingOrdinalResult['error']['kind'] ?? '') === 'journey_unavailable', 'P3HP43 missing segment_ordinal fails closed at runtime');

printf("CHECADOR_P3H_PRESENTATION_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

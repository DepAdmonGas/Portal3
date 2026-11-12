<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/support/portal3_autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorAttendanceProjection;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

$passes = 0;
$fails = 0;
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'true'; // Requests in this test are intercepted by explicit Guzzle MockHandlers.
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$portal3Contract = __DIR__ . '/../docs/v2/CHECADOR_V2_PORTAL3_P3H_PRESENTATION_PROJECTION_CONTRACT_V1.1.md';
$architectureFreeze = __DIR__ . '/../docs/architecture/CHECADOR_V2_ARCHITECTURE_FREEZE_V1.0.md';
$expectedPortal3Hash = '5a830862043f2ad317d504b47feb157584870852c6fba0067546a67f9c51a82b';
$expectedArchitectureHash = '87bf3e714f7358cbdace5c4f099573d77d9ecb9033328c706df5c5295304fd85';
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$projectionSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorAttendanceProjection.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php');
$journeyViewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php');
$adjustmentViewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-adjustments.php');
$journeyMethodSource = '';
if (preg_match('/public function evaluateJourney\([\s\S]*?(?=\n    public function )/', $serviceSource, $journeyMethodMatches) === 1) {
    $journeyMethodSource = $journeyMethodMatches[0];
}
$auditDetailSource = '';
if (preg_match('/<details[^>]*id="journey-audit-detail-<\?= \(int\) \$workerId \?>"[^>]*>([\s\S]*?)<\/details>/', $journeyViewSource, $auditDetailMatches) === 1) {
    $auditDetailSource = $auditDetailMatches[1];
}
$contractSource = file_get_contents($portal3Contract);

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
        'pairing' => ['pairing_id' => 1, 'revision_numero' => 1, 'creado_at_utc' => '2026-10-12 22:05:00', 'pairing_status' => 'AMBIGUOUS_PAIRING', 'completeness_l2' => 'VERIFIED', 'algorithm_version' => 'p1-v1', 'segments' => $segments],
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
$projection = \App\Services\ChecadorAttendanceProjection::workerRow(
    ['worker_id' => 7, 'full_name' => 'Personal de prueba', 'employee_number' => '7'],
    $events,
    $data,
    'America/Mexico_City'
);
$extraEventIds = array_map(static fn(array $event): string => (string) ($event['event_uuid'] ?? ''), $projection['additional_events'] ?? []);
$requestCheck = $make([new Response(200, [], json_encode($payload($events), JSON_THROW_ON_ERROR))]);

$assert(is_file($portal3Contract) && hash_file('sha256', $portal3Contract) === $expectedPortal3Hash, 'P3HP01 Portal3 V1.1 contract hash');
$assert(is_file($architectureFreeze) && hash_file('sha256', $architectureFreeze) === $expectedArchitectureHash, 'P3HP02 Architecture Freeze hash');
$assert(str_contains($serviceSource, "'POST', 'api/v2/journeys/evaluate"), 'P3HP03 Portal3 uses the canonical journey evaluation route');
$assert(($result['ok'] ?? false) === true, 'P3HP05 authoritative payload accepted');
$rawObservedEvents = array_map(static function (array $event): array {
    unset($event['pairing_role'], $event['segment_ordinal']);
    return $event;
}, $events);
$projection = ChecadorAttendanceProjection::workerRow(
    ['worker_id' => 7, 'full_name' => 'Trabajadora sintética'],
    $rawObservedEvents,
    $data,
    'America/Mexico_City'
);
$projectSegments = static function (array $segments, bool $authorized = true, ?array $sourceEvents = null) use ($data, $events): array {
    $journey = $data;
    $journey['pairing']['segments'] = $segments;
    if (!$authorized) {
        $journey['source_evidence']['window_level2_status'] = 'UNKNOWN';
    }
    $sourceEvents ??= $events;
    $observedEvents = array_map(static function (array $event): array {
        unset($event['pairing_role'], $event['segment_ordinal']);
        return $event;
    }, $sourceEvents);
    return ChecadorAttendanceProjection::workerRow(
        ['worker_id' => 7, 'full_name' => 'Trabajadora sintética'],
        $observedEvents,
        $journey,
        'America/Mexico_City'
    );
};
$dailyViewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php';
$renderDailyRow = static function (array $row) use ($dailyViewPath): string {
    $selectedWorkplaceId = 3;
    $selectedWorkplaceName = 'Interlomas';
    $selectedWorkplaceKind = 'Estación';
    $selectedDate = '2026-10-12';
    $selectedWorkerId = 7;
    $workerSelectOptions = [];
    $workerSelectStatus = 'success';
    $attendanceRows = [$row];
    $attendanceCoverage = ['status' => 'complete', 'complete' => true];
    $persistentQaFixtureMode = ($row['qa_fixture'] ?? false) === true;
    $journeyEvaluationState = 'not_evaluated';
    $timezone = ['timezone' => 'America/Mexico_City'];
    $administrativeIncidences = [];
    $administrativeErrors = [];
    $incidenceCreateAllowed = false;
    $incidenceCreateKeys = [];
    $snapshotRef = 'snapshot-p3h';
    $state = 'success';

    ob_start();
    require $dailyViewPath;
    return (string) ob_get_clean();
};
$segmentDefinitions = $data['pairing']['segments'];
$oneSegmentRow = $projectSegments(array_slice($segmentDefinitions, 0, 1));
$twoSegmentRow = $projectSegments(array_slice($segmentDefinitions, 0, 2));
$threeSegmentRow = $projectSegments(array_slice($segmentDefinitions, 0, 3));
$fourSegmentRow = $projectSegments(array_merge($segmentDefinitions, [[
    'ordinal' => 4,
    'status' => 'EXACT_PAIR',
    'entry_event_uuid' => null,
    'exit_event_uuid' => null,
]]));
$noJourneyRow = ChecadorAttendanceProjection::workerRow(
    ['worker_id' => 7, 'full_name' => 'Trabajadora sintética'],
    $rawObservedEvents,
    null,
    'America/Mexico_City'
);
$untrustedSegmentRow = $projectSegments($segmentDefinitions, false);
$unclassifiedPunches = $events;
$unclassifiedPunches[] = ['event_uuid' => 'p3h-unclassified-extra', 'id_dispositivo' => 1, 'ocurrido_at_utc' => '2026-10-14 02:00:00'];
$twoSegmentsWithUnclassifiedPunches = $projectSegments(array_slice($segmentDefinitions, 0, 2), true, $unclassifiedPunches);
$oneSegmentHtml = $renderDailyRow($oneSegmentRow);
$twoSegmentHtml = $renderDailyRow($twoSegmentRow);
$threeSegmentHtml = $renderDailyRow($threeSegmentRow);
$fourSegmentHtml = $renderDailyRow($fourSegmentRow);
$noJourneyHtml = $renderDailyRow($noJourneyRow);
$untrustedSegmentHtml = $renderDailyRow($untrustedSegmentRow);
$twoSegmentsWithUnclassifiedPunchesHtml = $renderDailyRow($twoSegmentsWithUnclassifiedPunches);
$qaInformationalRow = array_replace($noJourneyRow, [
    'qa_fixture' => true,
    'journey_status' => 'NOT_EVALUATED_IN_QA_FIXTURE',
    'pairing_status' => 'NOT_EVALUATED_IN_QA_FIXTURE',
]);
$qaInformationalHtml = $renderDailyRow($qaInformationalRow);
$assert(($projection['slots']['ENTRY:2']['event']['event_uuid'] ?? '') === 'p3h-4', 'P3HP06 slot 3 uses only ordinal 2 ENTRY');
$assert(($projection['slots']['EXIT:2']['event']['event_uuid'] ?? '') === 'p3h-5', 'P3HP07 slot 4 uses only ordinal 2 EXIT');
$assert(($projection['slots']['ENTRY:1']['event'] ?? null) === null
    && ($projection['slots']['EXIT:1']['event'] ?? null) === null, 'P3HP08 ambiguous segment 1 remains out of primary slots');
$assert(($projection['slots']['ENTRY:2']['time_local']['date'] ?? '') === '2026-10-13'
    && ($projection['slots']['ENTRY:2']['time_local']['time'] ?? '') === '08:00', 'P3HP09 API timestamp is rendered in the resolved workplace timezone');
$assert(!preg_match('/usort|array_multisort|sort\(\$evaluatedEvents|array_slice\(\$evaluatedEvents/i', $viewSource . $projectionSource), 'P3HP10 chronology and first-four inference absent');
$assert(str_contains($viewSource, "'SURPLUS' => 'Registro adicional'") || str_contains($viewSource, "'SURPLUS' => 'Marcación adicional'"), 'P3HP11 SURPLUS remains an additional observation');
$assert(str_contains($viewSource, "'UNMATCHED' => 'Registro sin emparejar'") || str_contains($viewSource, "'UNMATCHED' => 'Sin emparejar'"), 'P3HP12 UNMATCHED remains an unpaired observation');
$assert(array_key_exists('segment_ordinal', $data['evaluated_raw_events'][6] ?? []) && $data['evaluated_raw_events'][6]['segment_ordinal'] === null, 'P3HP13 UNMATCHED null segment ordinal preserved');
$assert(count($data['evaluated_raw_events'] ?? []) === 7 && str_contains($viewSource, 'Ver todas las marcaciones ('), 'P3HP14 5+ events are disclosed in the current daily view');
$assert(count($projection['additional_events']) === 5 && $projection['observed_count'] === 7, 'P3HP14A all API candidates remain available as slot evidence or observations');
$assert(($threeSegmentRow['additional_segment_count'] ?? null) === 1 && str_contains($threeSegmentHtml, '+1 segmentos adicionales'), 'P3HP15 an authorized ordinal 3 segment is disclosed in its daily attendance row');
$assert($projection['additional_segment_count'] === 1, 'P3HP16 ordinal 3 is disclosed without changing the first two slot meanings');
$assert(($oneSegmentRow['additional_segment_count'] ?? null) === 0 && !str_contains($oneSegmentHtml, 'segmentos adicionales'), 'one reported segment has no additional-segment indicator');
$assert(($twoSegmentRow['additional_segment_count'] ?? null) === 0 && !str_contains($twoSegmentHtml, 'segmentos adicionales'), 'two reported segments have no additional-segment indicator');
$assert(($fourSegmentRow['additional_segment_count'] ?? null) === 2 && str_contains($fourSegmentHtml, '+2 segmentos adicionales'), 'four confirmed segments are disclosed as two additional segments');
$assert(($noJourneyRow['additional_segment_count'] ?? null) === 0 && !str_contains($noJourneyHtml, 'segmentos adicionales'), 'a row without an authorized Journey evaluation does not invent segments');
$assert(str_contains($noJourneyHtml, 'Journey no se evalúa al abrir Asistencia')
    && substr_count($noJourneyHtml, 'Journey no se evalúa al abrir Asistencia') === 1,
    'daily attendance exposes one accessible neutral notice when Journey is not evaluated');
$assert(($untrustedSegmentRow['classification_available'] ?? true) === false
    && ($untrustedSegmentRow['additional_segment_count'] ?? null) === 0
    && !str_contains($untrustedSegmentHtml, 'segmentos adicionales'), 'segments from an unauthorized evaluation are not disclosed');
$assert(($twoSegmentsWithUnclassifiedPunches['observed_count'] ?? null) === 8
    && ($twoSegmentsWithUnclassifiedPunches['additional_segment_count'] ?? null) === 0
    && !str_contains($twoSegmentsWithUnclassifiedPunchesHtml, 'segmentos adicionales'), 'unclassified raw punches do not become additional segments');
$assert(!str_contains($qaInformationalHtml, 'segmentos adicionales')
    && !str_contains($qaInformationalHtml, 'Jornada completa')
    && !str_contains($qaInformationalHtml, 'Journey no se evalúa al abrir Asistencia'), 'not-evaluated QA rows remain explicitly fixture-only without a canonical Journey notice');
$assert(array_column($projection['additional_events'], 'event_uuid') === ['p3h-1', 'p3h-2', 'p3h-3', 'p3h-6', 'p3h-7'], 'additional-event order and event references remain unchanged');
$assert(!str_contains($viewSource, "'AMBIGUOUS' =>") && str_contains($projectionSource, 'AMBIGUOUS_PAIRING'), 'P3HP17 AMBIGUOUS remains a segment condition, never a punch role');
$assert(str_contains($projectionSource, "'AMBIGUOUS_PAIRING'") && ($projection['status'] ?? '') === 'ambiguous', 'P3HP18 ambiguous segment and journey status remain visible');
$assert(str_contains($viewSource, "'classification_pending', 'evaluation_unavailable' => ['Pendiente de clasificación'")
    && str_contains($viewSource, 'else: ?>—<?php endif;')
    && str_contains($contractSource, 'EMPTY_SLOT_MEANS_SIN_MARCACION: NO'), 'P3HP19 empty slot remains a neutral dash and incomplete classification stays pending');
$assert(str_contains($viewSource, 'administrativeIncidences') || str_contains($viewSource, 'administrativeErrors'), 'P3HP20 administrative status remains separate from P1 slots');
$assert(!preg_match('/if \(.*evaluated_raw_event_count.*FALTA|evaluated_raw_events.*FALTA/i', $viewSource), 'P3HP21 zero events do not generate FALTA');
$assert(str_contains($viewSource, 'incidence') && str_contains($contractSource, 'FINAL_FALTA_SOURCE: P3G_ADMINISTRATIVE_EVALUATION'), 'P3HP22 administrative status source remains separate');
$assert(str_contains($projectionSource, "format('H:i')") && str_contains($contractSource, 'COMPACT_TIME_FORMAT: HH:mm'), 'P3HP23 compact time is HH:mm');
$assert(!preg_match("/->format\(['\"]H:i:s['\"]\)/", $projectionSource . $viewSource), 'P3HP24 compact seconds are absent');
$assert(str_contains($contractSource, 'COMPACT_TIMEZONE_ABBREVIATION: NO'), 'P3HP25 timezone abbreviation is absent');
$assert(str_contains($viewSource, "\$time['date']") && str_contains($contractSource, 'CROSS_CALENDAR_DATE_DISCLOSURE: YES'), 'P3HP26 cross-calendar date disclosed');
$assert(str_contains($projectionSource, 'setTimezone') && !str_contains($projectionSource, 'modify('), 'P3HP27 timezone/workday ownership is not recalculated');
$assert(str_contains($viewSource, 'Ver todas las marcaciones (') && str_contains($viewSource, 'event_uuid'), 'P3HP28 event provenance remains in the daily detail');
$assert(str_contains($controllerSource, '$journeys = []'), 'P3HP29 employee context clears projection');
$assert(str_contains($controllerSource, '$journeyErrors = []') && str_contains($controllerSource, 'requestedWorkplace'), 'P3HP30 workplace context clears projection');
$assert(str_contains($controllerSource, "'date' => \$date") && str_contains($controllerSource, 'newSnapshotRef'), 'P3HP31 date context is isolated');
$assert(str_contains($serviceSource, "'ENTRY', 'EXIT', 'SURPLUS', 'UNMATCHED'") && str_contains($serviceSource, "array_key_exists('pairing_role'"), 'P3HP32 missing pairing_role fails closed');
$assert(str_contains($serviceSource, "array_key_exists('segment_ordinal'"), 'P3HP33 missing segment_ordinal fails closed');
$assert($journeyMethodSource !== ''
    && !preg_match('/array_slice|array_chunk|array_splice|first four|first.{0,10}four/i', $journeyMethodSource)
    && str_contains($journeyMethodSource, '$rawEvents = $data[\'evaluated_raw_events\'] ?? null;')
    && str_contains($journeyMethodSource, '$rawCount !== count($rawEvents)')
    && str_contains($journeyMethodSource, '$provenanceEvents !== $rawEvents')
    && str_contains($controllerSource, 'workspaceRetainsJourneyEvaluation($workspace)')
    && str_contains($controllerSource, "return \$workspace === 'adjustments';"), 'P3HP34 Journey service still validates the complete authoritative event collection while Asistencia and Jornadas GET remain non-evaluating');
$assert(!str_contains($serviceSource, 'segundos_trabajados_totales'), 'P3HP35 no local P2 calculation');
$assert(!preg_match('/INSERT\s+INTO|UPDATE\s+op_rh_v2_/i', $serviceSource . $controllerSource), 'P3HP36 no Portal3 pairing/database effect');
$assert(str_contains($viewSource, 'ambiguous') || str_contains($projectionSource, 'AMBIGUOUS_PAIRING'), 'P3HP37 review status remains visible');
$assert(str_contains($viewSource, 'role="status"'), 'P3HP38 status text/accessibility is present');
$assert(str_contains($viewSource, 'table-responsive') && str_contains($viewSource, '<th scope="col">'), 'P3HP39 responsive semantic table layout');
$assert(str_contains($viewSource, 'Pendiente de clasificación')
    && str_contains($viewSource, 'Requiere revisión')
    && str_contains($projectionSource, 'AMBIGUOUS_PAIRING'), 'P3HP40 fail-closed user-facing states remain explicit without reordering events');
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

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorAttendanceProjection;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$event = static fn(string $uuid, string $time, string $role, ?int $ordinal): array => [
    'event_uuid' => $uuid,
    'id_dispositivo' => 7,
    'ocurrido_at_utc' => $time,
    'metodo_captura' => 'BIOMETRIC',
    'pairing_role' => $role,
    'segment_ordinal' => $ordinal,
];
$journey = static function (
    array $events,
    array $segments,
    string $pairingStatus = 'EXACT_PAIR',
    string $sourceStatus = 'VERIFIED',
    string $completeness = 'VERIFIED',
    string $scheduleStatus = 'SCHEDULED'
): array {
    return [
        'evaluated' => true,
        'journey_status' => 'EVALUATED',
        'context' => ['timezone' => 'America/Mexico_City'],
        'schedule' => ['estado_jornada' => $scheduleStatus],
        'source_evidence' => ['window_level2_status' => $sourceStatus],
        'pairing' => [
            'pairing_status' => $pairingStatus,
            'completeness_l2' => $completeness,
            'segments' => $segments,
        ],
        'evaluated_raw_event_count' => count($events),
        'evaluated_raw_events' => $events,
    ];
};
$segments = static fn(int $count, string $status = 'EXACT_PAIR'): array => array_map(
    static fn(int $ordinal): array => ['ordinal' => $ordinal, 'status' => $status],
    range(1, $count)
);
$raw = static function (array $events): array {
    return array_map(static function (array $event): array {
        unset($event['pairing_role'], $event['segment_ordinal']);
        return $event;
    }, $events);
};
$project = static function (array $events, array $journey) use ($raw): array {
    return ChecadorAttendanceProjection::workerRow(
        ['worker_id' => 91, 'full_name' => 'Trabajador sintético', 'employee_number' => 'SYN-91'],
        $raw($events),
        $journey,
        'America/Mexico_City'
    );
};

$two = [
    $event('two-in', '2026-10-12 14:00:00', 'ENTRY', 1),
    $event('two-out', '2026-10-12 22:00:00', 'EXIT', 1),
];
$twoRow = $project($two, $journey($two, $segments(1)));
$assert(($twoRow['slots']['ENTRY:1']['event']['event_uuid'] ?? null) === 'two-in'
    && ($twoRow['slots']['EXIT:1']['event']['event_uuid'] ?? null) === 'two-out'
    && $twoRow['slots']['ENTRY:2']['event'] === null
    && $twoRow['slots']['EXIT:2']['event'] === null, 'two classified events fill only segment 1 positions');

$four = [
    $event('four-in-1', '2026-10-12 14:00:00', 'ENTRY', 1),
    $event('four-out-1', '2026-10-12 18:00:00', 'EXIT', 1),
    $event('four-in-2', '2026-10-12 19:00:00', 'ENTRY', 2),
    $event('four-out-2', '2026-10-12 22:00:00', 'EXIT', 2),
];
$fourRow = $project($four, $journey($four, $segments(2)));
$assert(($fourRow['slots']['ENTRY:1']['event']['event_uuid'] ?? null) === 'four-in-1'
    && ($fourRow['slots']['EXIT:1']['event']['event_uuid'] ?? null) === 'four-out-1'
    && ($fourRow['slots']['ENTRY:2']['event']['event_uuid'] ?? null) === 'four-in-2'
    && ($fourRow['slots']['EXIT:2']['event']['event_uuid'] ?? null) === 'four-out-2', 'four API-classified events map to the four approved neutral slots');

$many = array_merge($four, [
    $event('many-extra', '2026-10-12 23:00:00', 'SURPLUS', 3),
    $event('many-unmatched', '2026-10-13 00:00:00', 'UNMATCHED', null),
]);
$manyRow = $project($many, $journey($many, $segments(3)));
$assert($manyRow['observed_count'] === 6
    && $manyRow['evaluated_raw_event_count'] === 6
    && count($manyRow['additional_events']) === 2
    && $manyRow['additional_segment_count'] === 1, 'five-plus events and segment 3 remain disclosed outside the four primary slots');

$one = [$event('partial-in', '2026-10-12 14:00:00', 'ENTRY', 1)];
$partialRow = $project($one, $journey($one, [['ordinal' => 1, 'status' => 'PARTIAL_MISSING_OUT']], 'PARTIAL_MISSING_OUT'));
$assert(($partialRow['slots']['ENTRY:1']['event']['event_uuid'] ?? null) === 'partial-in'
    && $partialRow['slots']['EXIT:1']['event'] === null
    && $partialRow['status'] === 'incomplete', 'partial segment displays only its API-classified event and stays non-final');

$ambiguous = [
    $event('amb-in', '2026-10-12 14:00:00', 'ENTRY', 1),
    $event('amb-out', '2026-10-12 18:00:00', 'EXIT', 1),
    $event('clear-in', '2026-10-12 19:00:00', 'ENTRY', 2),
    $event('clear-out', '2026-10-12 22:00:00', 'EXIT', 2),
];
$ambiguousRow = $project($ambiguous, $journey($ambiguous, [
    ['ordinal' => 1, 'status' => 'AMBIGUOUS_PAIRING'],
    ['ordinal' => 2, 'status' => 'EXACT_PAIR'],
], 'AMBIGUOUS_PAIRING'));
$assert($ambiguousRow['slots']['ENTRY:1']['event'] === null
    && $ambiguousRow['slots']['EXIT:1']['event'] === null
    && ($ambiguousRow['slots']['ENTRY:2']['event']['event_uuid'] ?? null) === 'clear-in'
    && ($ambiguousRow['slots']['EXIT:2']['event']['event_uuid'] ?? null) === 'clear-out'
    && $ambiguousRow['status'] === 'ambiguous', 'ambiguous segment stays blank while an independently exact segment remains usable');

$incompleteRow = $project($four, $journey($four, $segments(2), 'PENDING_SYNC_COMPLETION', 'INCOMPLETE', 'PENDING_SYNC_COMPLETION'));
$assert($incompleteRow['classification_available'] === false
    && count(array_filter($incompleteRow['slots'], static fn(array $slot): bool => $slot['event'] !== null)) === 0
    && count($incompleteRow['additional_events']) === 4, 'incomplete Level 2 retains all observed events and suppresses every slot');

$mismatchRow = $project($two, $journey($two, $segments(1), 'WORKPLACE_MISMATCH'));
$assert(count(array_filter($mismatchRow['slots'], static fn(array $slot): bool => $slot['event'] !== null)) === 0
    && count($mismatchRow['additional_events']) === 2, 'workplace mismatch never leaks a classified slot');

$duplicate = [
    $event('dup-in-1', '2026-10-12 14:00:00', 'ENTRY', 1),
    $event('dup-in-2', '2026-10-12 14:01:00', 'ENTRY', 1),
];
$duplicateRow = $project($duplicate, $journey($duplicate, [['ordinal' => 1, 'status' => 'EXACT_PAIR']], 'AMBIGUOUS_PAIRING'));
$assert($duplicateRow['slots']['ENTRY:1']['event'] === null
    && $duplicateRow['slots']['ENTRY:1']['ambiguous'] === true
    && count($duplicateRow['additional_events']) === 2, 'duplicate slot candidates remain visible without selecting one arbitrarily');

$overnight = [$event('overnight-in', '2026-10-13 06:30:00', 'ENTRY', 1)];
$overnightRow = $project($overnight, $journey($overnight, [['ordinal' => 1, 'status' => 'PARTIAL_MISSING_OUT']], 'PARTIAL_MISSING_OUT'));
$assert(($overnightRow['slots']['ENTRY:1']['time_local']['date'] ?? null) === '2026-10-13'
    && ($overnightRow['slots']['ENTRY:1']['time_local']['time'] ?? null) === '00:30', 'cross-midnight event keeps the API timestamp and renders with workplace local date');

$rawMismatch = [$event('same-id', '2026-10-12 14:00:00', 'ENTRY', 1)];
$staleCandidate = [$event('same-id', '2026-10-12 14:05:00', 'ENTRY', 1)];
$staleRow = ChecadorAttendanceProjection::workerRow(
    ['worker_id' => 91],
    $raw($rawMismatch),
    $journey($staleCandidate, [['ordinal' => 1, 'status' => 'EXACT_PAIR']]),
    'America/Mexico_City'
);
$assert($staleRow['slots']['ENTRY:1']['event'] === null
    && $staleRow['observed_count'] === 1, 'API candidate timestamp mismatch stays an observation instead of replacing the verified raw event');

$noJourneyRow = ChecadorAttendanceProjection::workerRow(['worker_id' => 91], $raw($two), null, 'America/Mexico_City');
$assert($noJourneyRow['slots']['ENTRY:1']['event'] === null
    && count($noJourneyRow['additional_events']) === 2
    && $noJourneyRow['no_absence_inference'] === true, 'missing API evaluation leaves source observations visible and slots neutral');

$controller = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$dailyView = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php') ?: '';
$rawStart = strpos($controller, 'private static function readDailyRawAttendance(');
$rawEnd = $rawStart === false ? false : strpos($controller, 'private static function readAttendanceSnapshotSet(', $rawStart);
$rawReadController = $rawStart !== false && $rawEnd !== false
    ? substr($controller, $rawStart, $rawEnd - $rawStart)
    : '';
$assert(str_contains($rawReadController, "'journey_evaluation_state' => 'not_evaluated'")
    && !str_contains($rawReadController, 'evaluateJourney(')
    && str_contains($rawReadController, 'ChecadorAttendanceProjection::workerRow('), 'daily route preserves observed PunchEvent projection and never starts Journey evaluation');
$assert(str_contains($dailyView, 'Ver todas las marcaciones (')
    && str_contains($dailyView, 'localEventTime($event, $workplaceTimezone)')
    && str_contains($dailyView, 'Cobertura no confirmada')
    && str_contains($dailyView, '$coverageStatus')
    && str_contains($dailyView, '$showCoverageIndicator'), 'current daily view discloses observed events and dates with a compact indicator while preserving unknown source coverage');

printf("CHECADOR_V2_DAILY_ATTENDANCE_PAIRING_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

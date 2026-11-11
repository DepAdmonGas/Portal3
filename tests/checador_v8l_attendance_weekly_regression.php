<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorAttendanceProjection;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorLaborPolicy;
use App\Services\ChecadorWeeklyScheduleService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$week = ChecadorWeeklyScheduleService::dates('2026-10-19');
$assert(count($week) === 7
    && ($week[0]['weekday'] ?? '') === 'LUNES'
    && ($week[6]['date'] ?? '') === '2026-10-25', 'weekly grid is a Monday-to-Sunday calendar week');
$nowUtc = new DateTimeImmutable('2026-01-05 02:00:00', new DateTimeZone('UTC'));
$assert(ChecadorWeeklyScheduleService::isCurrentOrFutureWeek('2025-12-29', 'America/Los_Angeles', $nowUtc)
    && !ChecadorWeeklyScheduleService::isCurrentOrFutureWeek('2025-12-29', 'Pacific/Kiritimati', $nowUtc), 'week boundary uses the canonical workplace timezone, not server-local time');

$matrix = ChecadorWeeklyScheduleService::matrix(
    [['worker_id' => 7, 'full_name' => 'Trabajadora de prueba']],
    [7 => [
        ['id' => 1, 'id_personal' => 7, 'id_estacion' => 3, 'id_plantilla' => 5, 'dia_semana' => 'LUNES', 'valido_desde' => '2026-10-19', 'valido_hasta' => '2026-10-20', 'es_activo' => 1],
        ['id' => 2, 'id_personal' => 7, 'id_estacion' => 3, 'id_plantilla' => null, 'dia_semana' => 'MARTES', 'valido_desde' => '2026-10-20', 'valido_hasta' => '2026-10-21', 'es_activo' => 1],
    ]],
    [5 => ['nombre' => 'Turno verificado', 'segmentos' => [['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '22:00', 'hora_fin_local' => '06:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 1]]]],
    '2026-10-19'
);
$cells = $matrix['workers'][0]['cells'] ?? [];
$assert(($cells['2026-10-19']['status'] ?? '') === 'assigned'
    && ($cells['2026-10-19']['template']['segmentos'][0]['dia_offset_fin'] ?? null) === 1, 'overnight template detail and its next-day offset remain visible');
$assert(($cells['2026-10-20']['status'] ?? '') === 'rest'
    && ($cells['2026-10-21']['status'] ?? '') === 'unassigned', 'explicit rest stays distinct from no assignment');
$recurrentAndTemporary = ChecadorWeeklyScheduleService::matrix(
    [['worker_id' => 7, 'full_name' => 'QA habitual'] ],
    [7 => [
        ['id' => 10, 'tipo_asignacion' => 'HABITUAL', 'es_activo' => 1, 'dia_semana' => 'MIERCOLES', 'valido_desde' => '2026-11-04', 'valido_hasta' => null, 'id_plantilla' => 5],
        ['id' => 11, 'tipo_asignacion' => 'HABITUAL', 'es_activo' => 1, 'dia_semana' => 'JUEVES', 'valido_desde' => '2026-11-05', 'valido_hasta' => null, 'id_plantilla' => null],
        ['id' => 12, 'tipo_asignacion' => 'TEMPORAL', 'es_activo' => 1, 'dia_semana' => 'MIERCOLES', 'valido_desde' => '2026-11-04', 'valido_hasta' => '2026-11-05', 'id_plantilla' => 6],
    ]],
    [5 => ['nombre' => 'Habitual recurrente'], 6 => ['nombre' => 'Excepción temporal']],
    '2026-11-02'
);
$nextWeekProjection = ChecadorWeeklyScheduleService::matrix(
    [['worker_id' => 7, 'full_name' => 'QA habitual'] ],
    [7 => [
        ['id' => 10, 'tipo_asignacion' => 'HABITUAL', 'es_activo' => 1, 'dia_semana' => 'MIERCOLES', 'valido_desde' => '2026-11-04', 'valido_hasta' => null, 'id_plantilla' => 5],
        ['id' => 11, 'tipo_asignacion' => 'HABITUAL', 'es_activo' => 1, 'dia_semana' => 'JUEVES', 'valido_desde' => '2026-11-05', 'valido_hasta' => null, 'id_plantilla' => null],
        ['id' => 12, 'tipo_asignacion' => 'TEMPORAL', 'es_activo' => 1, 'dia_semana' => 'MIERCOLES', 'valido_desde' => '2026-11-04', 'valido_hasta' => '2026-11-05', 'id_plantilla' => 6],
    ]],
    [5 => ['nombre' => 'Habitual recurrente'], 6 => ['nombre' => 'Excepción temporal']],
    '2026-11-09'
);
$assert(($recurrentAndTemporary['workers'][0]['cells']['2026-11-04']['template']['nombre'] ?? '') === 'Excepción temporal'
    && ($recurrentAndTemporary['habitual_workers'][0]['cells']['2026-11-04']['template']['nombre'] ?? '') === 'Habitual recurrente',
    'a temporary exception overrides only the effective projection while the habitual card keeps its recurring baseline');
$assert(($nextWeekProjection['workers'][0]['cells']['2026-11-11']['template']['nombre'] ?? '') === 'Habitual recurrente'
    && ($nextWeekProjection['workers'][0]['cells']['2026-11-12']['status'] ?? '') === 'rest'
    && ($nextWeekProjection['workers'][0]['cells']['2026-11-12']['assignment_type'] ?? '') === 'HABITUAL',
    'a later week automatically returns to the unchanged recurring habitual shift and explicit recurring day off');
$closedReplacementProjection = ChecadorWeeklyScheduleService::matrix(
    [['worker_id' => 1007, 'full_name' => 'QA replacement history']],
    [1007 => [
        ['id' => 33, 'tipo_asignacion' => 'HABITUAL', 'es_activo' => 0, 'dia_semana' => 'LUNES', 'valido_desde' => '2026-11-09', 'valido_hasta' => '2026-11-16', 'id_plantilla' => 13],
        ['id' => 41, 'tipo_asignacion' => 'HABITUAL', 'es_activo' => 1, 'dia_semana' => 'LUNES', 'valido_desde' => '2026-11-16', 'valido_hasta' => null, 'id_plantilla' => 1],
    ]],
    [13 => ['nombre' => 'Turno previo'], 1 => ['nombre' => 'Turno reemplazo']],
    '2026-11-09'
);
$replacementCells = $closedReplacementProjection['workers'][0]['cells'] ?? [];
$replacementWeekProjection = ChecadorWeeklyScheduleService::matrix(
    [['worker_id' => 1007, 'full_name' => 'QA replacement history']],
    [1007 => [
        ['id' => 33, 'tipo_asignacion' => 'HABITUAL', 'es_activo' => 0, 'dia_semana' => 'LUNES', 'valido_desde' => '2026-11-09', 'valido_hasta' => '2026-11-16', 'id_plantilla' => 13],
        ['id' => 41, 'tipo_asignacion' => 'HABITUAL', 'es_activo' => 1, 'dia_semana' => 'LUNES', 'valido_desde' => '2026-11-16', 'valido_hasta' => null, 'id_plantilla' => 1],
    ]],
    [13 => ['nombre' => 'Turno previo'], 1 => ['nombre' => 'Turno reemplazo']],
    '2026-11-16'
);
$replacementEffectiveCells = $replacementWeekProjection['workers'][0]['cells'] ?? [];
$assert(($replacementCells['2026-11-09']['template']['nombre'] ?? '') === 'Turno previo'
    && ($replacementEffectiveCells['2026-11-16']['template']['nombre'] ?? '') === 'Turno reemplazo',
    'a versioned replacement keeps its closed predecessor visible before the exclusive end and selects the successor from its effective date');
$coverageDates = ChecadorWeeklyScheduleService::dates('2026-11-02');
$verifiedWeekDates = array_column($coverageDates, 'date');
$completeCells = [];
$partialCells = [];
$unassignedCells = [];
foreach ($coverageDates as $index => $day) {
    $completeCells[$day['date']] = ['status' => $index === 6 ? 'rest' : 'assigned'];
    $partialCells[$day['date']] = ['status' => $index < 3 ? ($index === 2 ? 'rest' : 'assigned') : 'unassigned'];
    $unassignedCells[$day['date']] = ['status' => 'unassigned'];
}
$completeCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => $verifiedWeekDates, 'schedule_read_status' => 'available', 'cells' => $completeCells,
], $coverageDates);
$partialCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => $verifiedWeekDates, 'schedule_read_status' => 'available', 'cells' => $partialCells,
], $coverageDates);
$unassignedCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => $verifiedWeekDates, 'schedule_read_status' => 'available', 'cells' => $unassignedCells,
], $coverageDates);
$unknownCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => [$verifiedWeekDates[0]], 'schedule_read_status' => 'available', 'cells' => $unassignedCells,
], $coverageDates);
$assert(($completeCoverage['status'] ?? '') === 'complete' && ($completeCoverage['covered_days'] ?? 0) === 7,
    'seven habitual work/rest days count as complete coverage');
$assert(($partialCoverage['status'] ?? '') === 'partial' && ($partialCoverage['covered_days'] ?? 0) === 3
    && count($partialCoverage['missing_days'] ?? []) === 4,
    'a verified mix of explicit habitual days and missing days is partial coverage');
$assert(($unassignedCoverage['status'] ?? '') === 'unassigned' && ($unassignedCoverage['assignable'] ?? false) === true,
    'seven verified empty days are unassigned rather than an explicit rest');
$assert(($unknownCoverage['status'] ?? '') === 'unknown' && ($unknownCoverage['assignable'] ?? true) === false,
    'incomplete membership prevents a definitive coverage result and disables assignment');
$partialReadMatrix = ChecadorWeeklyScheduleService::matrix(
    [['worker_id' => 8, 'verified_dates' => $verifiedWeekDates, 'schedule_read_status' => 'partial']],
    [8 => [['es_activo' => 1, 'dia_semana' => 'LUNES', 'valido_desde' => '2026-11-02', 'valido_hasta' => null, 'id_plantilla' => 5]]],
    [5 => ['nombre' => 'Turno visible']],
    '2026-11-02'
);
$assert(($partialReadMatrix['workers'][0]['cells']['2026-11-03']['status'] ?? '') === 'schedule_unavailable'
    && ($partialReadMatrix['habitual_workers'][0]['habitual_coverage']['status'] ?? '') === 'unknown',
    'a capped assignment page leaves absent weekdays unconfirmed instead of treating them as unassigned');
$ambiguous = ChecadorWeeklyScheduleService::matrix(
    [['worker_id' => 7]],
    [7 => [
        ['es_activo' => 1, 'dia_semana' => 'LUNES', 'valido_desde' => '2026-10-19', 'valido_hasta' => null, 'id_plantilla' => 5],
        ['es_activo' => 1, 'dia_semana' => 'LUNES', 'valido_desde' => '2026-10-19', 'valido_hasta' => null, 'id_plantilla' => 6],
    ]],
    [],
    '2026-10-19'
);
$assert(($ambiguous['workers'][0]['cells']['2026-10-19']['status'] ?? '') === 'ambiguous', 'overlapping active assignments are flagged for review, never arbitrarily selected');

$partiallyVerified = ChecadorWeeklyScheduleService::matrix(
    [['worker_id' => 7, 'full_name' => 'Trabajadora de prueba', 'verified_dates' => ['2026-10-21', '2026-10-22']]],
    [7 => [
        ['id' => 3, 'id_personal' => 7, 'id_estacion' => 3, 'id_plantilla' => 5, 'dia_semana' => 'LUNES', 'valido_desde' => '2026-10-19', 'valido_hasta' => '2026-10-20', 'es_activo' => 1],
        ['id' => 4, 'id_personal' => 7, 'id_estacion' => 3, 'id_plantilla' => 5, 'dia_semana' => 'MIERCOLES', 'valido_desde' => '2026-10-21', 'valido_hasta' => '2026-10-22', 'es_activo' => 1],
    ]],
    [5 => ['nombre' => 'Turno verificado', 'segmentos' => [['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00', 'hora_fin_local' => '16:00']]]],
    '2026-10-19'
);
$partialCells = $partiallyVerified['workers'][0]['cells'] ?? [];
$assert(($partialCells['2026-10-19']['status'] ?? '') === 'membership_unverified'
    && ($partialCells['2026-10-19']['label'] ?? '') === 'Pertenencia no confirmada'
    && ($partialCells['2026-10-21']['status'] ?? '') === 'assigned', 'partial weekly membership shows confirmed days and masks unverified days without inventing an empty schedule');

$scheduleUnavailable = ChecadorWeeklyScheduleService::matrix(
    [['worker_id' => 7, 'verified_dates' => ['2026-10-19'], 'schedule_read_status' => 'unavailable']],
    [],
    [],
    '2026-10-19'
);
$assert(($scheduleUnavailable['workers'][0]['cells']['2026-10-19']['status'] ?? '') === 'schedule_unavailable', 'a failed assignment read is not represented as an unassigned schedule');

$rosterSource = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAttendanceRosterService.php') ?: '';
$weeklyRosterStart = strpos($rosterSource, 'public function workersForScheduleWeek(');
$weeklyRosterEnd = $weeklyRosterStart === false ? false : strpos($rosterSource, 'private static function isCivilDate(', $weeklyRosterStart);
$weeklyRosterMethod = $weeklyRosterStart !== false && $weeklyRosterEnd !== false
    ? substr($rosterSource, $weeklyRosterStart, $weeklyRosterEnd - $weeklyRosterStart)
    : '';
$assert($weeklyRosterMethod !== ''
    && str_contains($weeklyRosterMethod, 'resolveActiveStationIdForLocality($workplaceId)')
    && str_contains($weeklyRosterMethod, '->where(\'id_estacion\', $physicalStationId)')
    && str_contains($weeklyRosterMethod, '->where(\'workplace_id\', $workplaceId)')
    && strpos($weeklyRosterMethod, '->where(\'id_estacion\', $physicalStationId)') < strpos($weeklyRosterMethod, '->limit(self::MAX_ROSTER_ROWS + 1)')
    && str_contains($weeklyRosterMethod, '$resolver->resolveAssignments($workerId, $effectiveAt)'), 'weekly roster scopes physical and versioned candidates to the authorized locality before its safety limit and resolves each date');
$assert($weeklyRosterMethod !== ''
    && str_contains($weeklyRosterMethod, 'if (!$currentBelongs && $verifiedDates === [])')
    && str_contains($weeklyRosterMethod, "\$currentBelongs ? 'current_roster' : 'week_membership'")
    && str_contains($weeklyRosterMethod, 'versioned_verified_dates'),
    'current station candidates remain a separate reference roster while weekly membership provenance is preserved');
$assert(str_contains(file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '', 'workersForScheduleWeek('), 'weekly schedule uses the locality-scoped per-week roster');
$controllerSource = file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '';
$assert(str_contains($controllerSource, 'verified_dates\'] ?? []) === [])')
    && str_contains($controllerSource, 'assignmentApiRecordCount'),
    'the controller avoids assignment API reads without any date-verified membership and reports API record counts separately');

$weeklyViewData = [
    'weekDates' => $week,
    'scheduleRows' => $matrix['workers'],
    'habitualScheduleRows' => $matrix['habitual_workers'],
    'selectedWorkplaceId' => 3,
    'selectedWorkplaceName' => 'Centro de prueba',
    'weekStart' => '2026-10-19',
    'timezone' => 'America/Mexico_City',
    'coverage' => 'ready',
    'errorMessage' => '',
    'feedback' => null,
    'templates' => [['id' => 5, 'nombre' => 'Turno verificado', 'version' => 1, 'tipo_turno' => 'REGULAR', 'es_activo' => 1]],
    'canAssign' => false,
    'demoMode' => false,
    'legalReference' => ChecadorLaborPolicy::referenceForDate('2026-10-19'),
    'dailyLimits' => ChecadorLaborPolicy::generalDailyReferences(),
];
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$renderedScheduleWeek = '';
$scheduleWeekRenderError = null;
ob_start();
try {
    extract($weeklyViewData, EXTR_SKIP);
    include dirname(__DIR__) . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php';
    $renderedScheduleWeek = (string) ob_get_clean();
} catch (Throwable $exception) {
    $scheduleWeekRenderError = $exception::class;
    if (ob_get_level() > 0) ob_end_clean();
} finally {
    restore_error_handler();
}
$assert($scheduleWeekRenderError === null
    && !preg_match('/<h1\b/i', $renderedScheduleWeek)
    && str_contains($renderedScheduleWeek, 'Horario habitual del personal')
    && str_contains($renderedScheduleWeek, 'Programación semanal / Cambios temporales')
    && str_contains($renderedScheduleWeek, 'no se duplican cada semana')
    && str_contains($renderedScheduleWeek, 'Turno verificado')
    && substr_count($renderedScheduleWeek, '<caption class="visually-hidden">Horario de lunes a domingo para la semana consultada</caption>') === 2
    && str_contains(file_get_contents(dirname(__DIR__) . '/app/Views/layouts/departamento-operativo.php') ?: '', '<h4 class="fw-semibold mt-3"><?= $title; ?></h4>'),
    'weekly screen renders its matrix without a duplicate page title or PHP view warnings');

$events = [
    ['event_uuid' => 'entry-1', 'ocurrido_at_utc' => '2026-10-20 05:00:00', 'metodo_captura' => 'BIOMETRIC', 'id_dispositivo' => 2, 'pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
    ['event_uuid' => 'exit-1', 'ocurrido_at_utc' => '2026-10-20 12:00:00', 'metodo_captura' => 'BIOMETRIC', 'id_dispositivo' => 2, 'pairing_role' => 'EXIT', 'segment_ordinal' => 1],
    ['event_uuid' => 'entry-2', 'ocurrido_at_utc' => '2026-10-20 13:00:00', 'metodo_captura' => 'BIOMETRIC', 'id_dispositivo' => 2, 'pairing_role' => 'ENTRY', 'segment_ordinal' => 2],
    ['event_uuid' => 'exit-2', 'ocurrido_at_utc' => '2026-10-20 15:00:00', 'metodo_captura' => 'BIOMETRIC', 'id_dispositivo' => 2, 'pairing_role' => 'EXIT', 'segment_ordinal' => 2],
    ['event_uuid' => 'extra', 'ocurrido_at_utc' => '2026-10-20 16:00:00', 'metodo_captura' => 'BIOMETRIC', 'id_dispositivo' => 2, 'pairing_role' => 'SURPLUS', 'segment_ordinal' => 1],
    ['event_uuid' => 'unmatched', 'ocurrido_at_utc' => '2026-10-20 17:00:00', 'metodo_captura' => 'BIOMETRIC', 'id_dispositivo' => 2, 'pairing_role' => 'UNMATCHED', 'segment_ordinal' => null],
];
$rawEvents = array_map(static function (array $event): array {
    unset($event['pairing_role'], $event['segment_ordinal']);
    return $event;
}, $events);
$projection = ChecadorAttendanceProjection::workerRow(
    ['worker_id' => 7, 'full_name' => 'Trabajadora de prueba', 'employee_number' => '700'],
    $rawEvents,
    [
        'evaluated' => true,
        'journey_status' => 'EVALUATED',
        'schedule' => ['estado_jornada' => 'SCHEDULED'],
        'source_evidence' => ['window_level2_status' => 'VERIFIED'],
        'pairing' => ['pairing_status' => 'EXACT_PAIR', 'completeness_l2' => 'VERIFIED', 'segments' => [
            ['ordinal' => 1, 'status' => 'EXACT_PAIR'],
            ['ordinal' => 2, 'status' => 'EXACT_PAIR'],
        ]],
        'evaluated_raw_event_count' => count($events),
        'evaluated_raw_events' => $events,
    ],
    'America/Mexico_City'
);
$assert(($projection['slots']['ENTRY:1']['event']['event_uuid'] ?? '') === 'entry-1'
    && ($projection['slots']['EXIT:1']['event']['event_uuid'] ?? '') === 'exit-1'
    && ($projection['slots']['ENTRY:2']['event']['event_uuid'] ?? '') === 'entry-2'
    && ($projection['slots']['EXIT:2']['event']['event_uuid'] ?? '') === 'exit-2', 'only explicit P3-H role and ordinal populate the four neutral entry/exit segments');
$assert($projection['observed_count'] === 6 && count($projection['additional_events']) === 2
    && $projection['no_absence_inference'] === true, 'SURPLUS and UNMATCHED plus every event remain preserved without an absence inference');
$assert(($projection['slots']['ENTRY:1']['time_local']['date'] ?? '') === '2026-10-19'
    && ($projection['slots']['ENTRY:1']['time_local']['time'] ?? '') === '23:00', 'UTC event time is displayed in workplace IANA time across the local date boundary');
$withoutJourney = ChecadorAttendanceProjection::workerRow(
    ['worker_id' => 7],
    [$events[0]],
    null,
    'America/Mexico_City'
);
$assert($withoutJourney['status'] === 'classification_pending'
    && count($withoutJourney['additional_events']) === 1
    && $withoutJourney['slots']['ENTRY:1']['event'] === null, 'raw events remain visible while unclassified events are not promoted into P3-H slots');
$duplicateEvents = [$events[0], array_merge($events[0], ['event_uuid' => 'entry-1-duplicate'])];
$duplicateRawEvents = array_map(static function (array $event): array {
    unset($event['pairing_role'], $event['segment_ordinal']);
    return $event;
}, $duplicateEvents);
$duplicate = ChecadorAttendanceProjection::workerRow(
    ['worker_id' => 7],
    $duplicateRawEvents,
    [
        'evaluated' => true,
        'journey_status' => 'EVALUATED',
        'schedule' => ['estado_jornada' => 'SCHEDULED'],
        'source_evidence' => ['window_level2_status' => 'VERIFIED'],
        'pairing' => ['pairing_status' => 'AMBIGUOUS_PAIRING', 'completeness_l2' => 'VERIFIED', 'segments' => [
            ['ordinal' => 1, 'status' => 'EXACT_PAIR'],
        ]],
        'evaluated_raw_event_count' => count($duplicateEvents),
        'evaluated_raw_events' => $duplicateEvents,
    ],
    'America/Mexico_City'
);
$assert(($duplicate['slots']['ENTRY:1']['ambiguous'] ?? false) === true
    && $duplicate['slots']['ENTRY:1']['event'] === null
    && count($duplicate['additional_events']) === 2, 'ambiguous duplicate candidates stay out of the slot and both originals remain visible');

$expectedLimits = [2026 => [48, 9], 2027 => [46, 9], 2028 => [44, 10], 2029 => [42, 11], 2030 => [40, 12]];
foreach ($expectedLimits as $year => [$ordinary, $extraordinary]) {
    $reference = ChecadorLaborPolicy::referenceForDate($year . '-06-01');
    $assert(($reference['ordinary_weekly_max_hours'] ?? null) === $ordinary
        && ($reference['extraordinary_weekly_reference_hours'] ?? null) === $extraordinary,
        "legal weekly references are versioned for {$year}");
}
$daily = ChecadorLaborPolicy::generalDailyReferences();
$assert($daily['diurna'] === 8.0 && $daily['nocturna'] === 7.0 && $daily['mixta'] === 7.5
    && $daily['mixed_nocturnal_max_minutes_exclusive'] === 210
    && $daily['applies_automatically'] === false, 'daily legal references include the night-period threshold and are not applied automatically');

$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_ADVANCED_DEMO_ENABLED=false');
$jsonResponse = static fn(int $status, array $body): Response => new Response(
    $status,
    ['Content-Type' => 'application/json'],
    json_encode($body, JSON_THROW_ON_ERROR)
);
$requestHistory = [];
$stack = HandlerStack::create(new MockHandler([
    $jsonResponse(200, ['status' => 'success', 'data' => ['page' => 1, 'per_page' => 100, 'items' => [
        ['id' => 5, 'clave' => 'DAY', 'version' => 1, 'nombre' => 'Diurno', 'tipo_turno' => 'REGULAR', 'es_activo' => 1],
    ]]]),
    $jsonResponse(200, ['status' => 'success', 'data' => [
        'id' => 5, 'clave' => 'DAY', 'version' => 1, 'nombre' => 'Diurno', 'tipo_turno' => 'REGULAR', 'es_activo' => 1,
        'segmentos' => [['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00:00', 'hora_fin_local' => '16:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0]],
    ]]),
    $jsonResponse(201, ['status' => 'success', 'data' => [
        'id' => 99, 'id_personal' => 7, 'id_estacion' => 3, 'id_plantilla' => 5, 'dia_semana' => 'LUNES',
        'tipo_asignacion' => 'HABITUAL', 'valido_desde' => '2026-10-19', 'valido_hasta' => '2026-10-20',
        'creado_at' => '2026-10-18 12:00:00', 'registrado_at_utc' => '2026-10-18 18:00:00',
    ]]),
]));
$stack->push(Middleware::history($requestHistory));
$scheduleReader = new ChecadorDailyReadService(
    new Client(['handler' => $stack, 'base_uri' => 'https://checador.mock/', 'http_errors' => false]),
    static fn(Usuario $identity): string => 'mock-token',
    'https://checador.mock/'
);
$scheduleUser = new Usuario();
$scheduleUser->id = 7;
$templateList = $scheduleReader->getShiftTemplates($scheduleUser, 1, 100);
$template = $scheduleReader->getShiftTemplateVersion($scheduleUser, 'DAY', 1);
// This isolated test enables the development-only POST gate only while a
// Guzzle MockHandler is installed; no network transport is available here.
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'true';
putenv('APP_ENV=dev');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=true');
$assignment = $scheduleReader->createShiftAssignment($scheduleUser, [
    'id_personal' => 7, 'id_estacion' => 3, 'id_plantilla' => 5, 'dia_semana' => 'LUNES',
    'valido_desde' => '2026-10-19', 'valido_hasta' => '2026-10-20',
], 'schedule-v8l-001');
$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
$postRequest = $requestHistory[2]['request'] ?? null;
$postBody = $postRequest instanceof RequestInterface
    ? json_decode((string) $postRequest->getBody(), true)
    : null;
$assert(($templateList['ok'] ?? false) && ($template['ok'] ?? false)
    && ($template['data']['segmentos'][0]['dia_offset_fin'] ?? null) === 0,
    'shift catalog and version detail are read from contract-shaped API responses');
$assert(($assignment['ok'] ?? false) && ($assignment['data']['id'] ?? 0) === 99
    && $postRequest instanceof RequestInterface && $postRequest->getMethod() === 'POST'
    && str_contains((string) $postRequest->getUri(), '/api/v2/shift-assignments')
    && ($postRequest->getHeaderLine('Idempotency-Key') ?? '') === 'schedule-v8l-001'
    && ($postBody['valido_hasta'] ?? null) === '2026-10-20',
    'individual shift assignment uses the frozen API endpoint, validity window and idempotency key');
$invalidRequestHistory = [];
$invalidStack = HandlerStack::create(new MockHandler([]));
$invalidStack->push(Middleware::history($invalidRequestHistory));
$invalidScheduleReader = new ChecadorDailyReadService(
    new Client(['handler' => $invalidStack, 'base_uri' => 'https://checador.mock/', 'http_errors' => false]),
    static fn(Usuario $identity): string => 'mock-token',
    'https://checador.mock/'
);
$invalidAssignment = $invalidScheduleReader->createShiftAssignment($scheduleUser, [
    'id_personal' => 7, 'id_estacion' => 3, 'id_plantilla' => 5, 'dia_semana' => 'LUNES',
    'valido_desde' => '2026-10-20', 'valido_hasta' => '2026-10-19',
], 'schedule-v8l-invalid');
$assert(($invalidAssignment['error']['kind'] ?? '') === 'bad_request' && $invalidRequestHistory === [],
    'invalid or reversed assignment validity fails before any HTTP write');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
$view = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/daily-attendance.php') ?: '';
$weeklyView = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php') ?: '';
$routes = file_get_contents($root . '/routes/web.php') ?: '';
$roster = file_get_contents($root . '/app/Services/ChecadorAttendanceRosterService.php') ?: '';
$access = file_get_contents($root . '/app/Services/ChecadorAccessService.php') ?: '';
$routeAuth = file_get_contents($root . '/app/Core/Route.php') ?: '';
$assert(str_contains($controller, 'resolveWorkplaceLocalDay($currentUser, $workplaceId, $weekStart)')
    && str_contains($controller, 'resolveWorkplaceLocalDay($user, $workplaceId, $targetDate)')
    && !str_contains($controller, "isCurrentOrFutureWeek(\$weekStart, 'America/Mexico_City')"), 'weekly view and assignment use API-resolved Workplace timezone rather than a hard-coded timezone');
$assert(str_contains($routes, "'/recursos-humanos/checador/programacion-semanal/asignar'")
    && !str_contains($routes, "'/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignar'"), 'POST route is relative to the existing departamento-operativo route group');
$assert(str_contains($roster, "where('estado', 1)")
    && str_contains($roster, 'WorkerWorkplaceAssignmentResolver')
    && !preg_match('/where\([\'\"]id_estacion[\'\"],\s*\$workplaceId/', $roster), 'daily roster uses active Portal3 personnel plus canonical workplace resolution, not legacy station ID equality');
$assert(preg_match('/if \(!\$demoMode && \$requestedWorker !== null[\s\S]{0,600}\$administrativeReadWorkerIds\[\$requestedWorker\] = true;/', $controller) === 1
    && !preg_match('/if \(!\$demoMode && \$requestedWorker !== null\)\s*\{\s*\$canonicalWorkerIds\[\$requestedWorker\] = true;/', $controller),
    'real browser worker filters require an observed event or canonically authorized current roster membership');
$assert(str_contains($access, 'authorizeScheduling(?int $requestedLocalityId, string $action)')
    && str_contains($access, "getPermiso((int) \$user->id, 'biometricos')"), 'weekly scheduling rechecks read/edit permission and Checador station scope');
$historyAccessPosition = strpos($controller, "\$scheduleAccess = ChecadorAccessService::authorizeScheduling(\$workplaceId, 'leer')");
$historyReadPosition = strpos($controller, "\$effectiveSchedule = null;", $historyAccessPosition === false ? 0 : $historyAccessPosition);
$assert($historyAccessPosition !== false && $historyReadPosition !== false && $historyAccessPosition < $historyReadPosition
    && str_contains($weeklyView, '$currentWeek = $workplaceZone === null')
    && str_contains($weeklyView, ': null'), 'weekly and historical schedule links require read access; current week is withheld when the workplace timezone is unavailable');
$assert(str_contains($routes, "Route::auth(['ChecadorController', 'createScheduleAssignment'])")
    && str_contains($routeAuth, "['auth', 'csrf']"), 'individual assignment POST remains behind the existing authentication and CSRF middleware');
$assert(str_contains($weeklyView, 'no se duplican cada semana')
    && str_contains($weeklyView, 'Nuevo cambio temporal')
    && str_contains($weeklyView, 'El cambio temporal sólo sustituye el horario durante la fecha seleccionada')
    && str_contains($weeklyView, 'Asignar horario habitual')
    && str_contains($weeklyView, 'value="TEMPORAL"')
    && str_contains($controller, 'Request::expectsJson()')
    && str_contains($controller, "'target_date' => \$targetDate")
    && str_contains($controller, 'scheduleApiPostOperationsEnabled()'), 'UI distinguishes recurring habitual assignments from optional date-specific changes and the controller retains the schedule-only write gate');
$assert(str_contains($view, '1.ª entrada') && str_contains($view, '1.ª salida')
    && str_contains($view, '2.ª entrada') && str_contains($view, '2.ª salida')
    && str_contains($controller, "'segundos_trabajados_totales'")
    && str_contains($controller, "'estado_calculo'")
    && str_contains($controller, "'CALCULATED'")
    && !str_contains($view, 'Crear incidencia'), 'daily attendance remains a four-slot read view and administrative actions stay in their own workspace');

printf("CHECADOR_V8L_ATTENDANCE_WEEKLY: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

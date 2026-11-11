<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Auth;
use App\Core\Session;
use App\Core\View;
use App\Controllers\ChecadorController;
use App\Services\ChecadorAttendanceProjection;
use App\Services\ChecadorPersistentQaFixtureService;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

Database::initialize();
$db = Capsule::connection();
$identity = $db->selectOne('SELECT DATABASE() AS database_name, @@hostname AS server_host, @@port AS server_port');
$assert(($identity->database_name ?? '') === 'bd_admongas'
    && strtolower((string) ($identity->server_host ?? '')) === 'fedora'
    && (int) ($identity->server_port ?? 0) === 3306,
    'read-only integration is restricted to Fedora local bd_admongas');

$countsBefore = $db->selectOne('SELECT COUNT(*) AS row_count, COUNT(DISTINCT event_uuid) AS uuid_count FROM op_rh_v2_marcaciones_crudas');
$rawUuidSnapshotBefore = array_map(
    static fn(object $row): string => (string) $row->event_uuid,
    $db->select('SELECT event_uuid FROM op_rh_v2_marcaciones_crudas ORDER BY event_uuid')
);
$rawUuidDigestBefore = hash('sha256', implode("\n", $rawUuidSnapshotBefore));
$assert((int) ($countsBefore->row_count ?? 0) === 89 && (int) ($countsBefore->uuid_count ?? 0) === 89,
    'precondition must preserve the 89 existing unique raw events');

$fixture = ChecadorPersistentQaFixtureService::fixtureDefinition();
$expected = [];
foreach ($fixture['workers'] ?? [] as $workerId => $definition) {
    foreach ($definition['events'] ?? [] as $event) {
        $expected[(string) ($event['event_uuid'] ?? '')] = [
            'worker_id' => (int) $workerId,
            'occurred_at_utc' => (string) ($event['occurred_at_utc'] ?? ''),
        ];
    }
}
$uuids = array_keys($expected);
$assert(count($uuids) === 12 && !in_array('', $uuids, true), 'fixture must reference exactly the 12 existing QA UUIDs');
$placeholders = implode(',', array_fill(0, count($uuids), '?'));
$sourceRows = $db->select(
    'SELECT event_uuid, id_personal_resuelto, id_estacion_resuelta, ocurrido_at_utc
     FROM op_rh_v2_marcaciones_crudas WHERE event_uuid IN (' . $placeholders . ') ORDER BY event_uuid',
    $uuids
);
$assert(count($sourceRows) === 12, 'all 12 fixture references must resolve to existing raw events');
foreach ($sourceRows as $row) {
    $uuid = (string) $row->event_uuid;
    $assert(isset($expected[$uuid])
        && (int) $row->id_personal_resuelto === $expected[$uuid]['worker_id']
        && (int) $row->id_estacion_resuelta === 1
        && (string) $row->ocurrido_at_utc === $expected[$uuid]['occurred_at_utc'],
        'fixture reference must match persisted worker, locality, and UTC occurrence for ' . $uuid);
}

$service = new ChecadorPersistentQaFixtureService();
$read = $service->read(1, '2026-10-28');
$assert(($read['ok'] ?? false) === true, 'active Interlomas QA cohort and canonical timezone must resolve');
$assert(count($read['attendance_rows'] ?? []) === 46
    && count($read['worker_options'] ?? []) === 46
    && count($read['events'] ?? []) === 12,
    'fixture overlay must return the complete roster and exactly the 12 existing linked events');
$read28Uuids = array_column($read['events'] ?? [], 'event_uuid');
sort($uuids);
sort($read28Uuids);
$assert($read28Uuids === $uuids,
    '28 October continues to return exactly its original 12 event identifiers');
$assert(count($read['attendance_roster']['workers'] ?? []) === 46
    && ($read['attendance_roster']['status'] ?? '') === 'partial'
    && ($read['attendance_roster']['coverage'] ?? '') === 'current_roster_historical_unverified',
    'the explicit historical QA fixture must retain the 46-worker current roster as an unverified historical reference');
$newDateFixture = $fixture['additional_daily_dates']['2026-10-29'] ?? [];
$newDateExpectedCounts = [1004 => 2, 1005 => 4, 1006 => 1, 1007 => 2, 1008 => 3];
$newDateExpectedUuids = [];
foreach ($newDateFixture['workers'] ?? [] as $workerId => $definition) {
    foreach ($definition['events'] ?? [] as $event) {
        $newDateExpectedUuids[] = (string) ($event['event_uuid'] ?? '');
    }
}
$newDatePlaceholders = implode(',', array_fill(0, count($newDateExpectedUuids), '?'));
$newDatePersistedRows = $newDateExpectedUuids === [] ? [] : $db->select(
    'SELECT event_uuid FROM op_rh_v2_marcaciones_crudas WHERE event_uuid IN (' . $newDatePlaceholders . ')',
    $newDateExpectedUuids
);
$read29 = $service->read(1, '2026-10-29');
$unconfirmedWorkerIds = array_values(array_diff(array_map('intval', array_keys($read29['attendance_rows'] ?? [])), array_keys($newDateExpectedCounts)));
$unconfirmedWorkerId = $unconfirmedWorkerIds[0] ?? 0;
$unconfirmedRows = array_intersect_key($read29['attendance_rows'] ?? [], array_fill_keys($unconfirmedWorkerIds, true));
$assert(($read29['ok'] ?? false) === true
    && count($read29['attendance_roster']['workers'] ?? []) === 46
    && ($read29['attendance_roster']['status'] ?? '') === 'partial'
    && ($read29['attendance_roster']['coverage'] ?? '') === 'current_roster_historical_unverified'
    && count($read29['worker_options'] ?? []) === 46
    && count($read29['attendance_rows'] ?? []) === 46
    && count($read29['events'] ?? []) === 12
    && count($newDateExpectedUuids) === 12
    && $newDatePersistedRows === [],
    '29 October fixture reads the actual local roster/timezone and config-backed synthetic events without raw-table rows');
$originalUuids = array_fill_keys(array_map('strtolower', $uuids), true);
$newDateUuidSet = array_map('strtolower', $newDateExpectedUuids);
$assert(count(array_unique($newDateUuidSet)) === 12
    && count(array_intersect_key(array_fill_keys($newDateUuidSet, true), $originalUuids)) === 0,
    '29 October UUIDs are unique and do not reuse any 28 October fixture UUID');
$assert(($read29['attendance_coverage']['complete'] ?? true) === false
    && ($read29['journeys'] ?? null) === []
    && count($read29['effective_schedules'] ?? []) === 5
    && ($read29['administrative_incidences'] ?? null) === []
    && ($read29['attendance_rows'][1004]['status'] ?? '') === 'classification_pending'
    && count($unconfirmedWorkerIds) === 41
    && $unconfirmedWorkerId > 0
    && count($unconfirmedRows) === 41
    && array_reduce($unconfirmedRows, static fn(bool $valid, array $row): bool => $valid
        && ($row['status'] ?? '') === 'classification_pending'
        && ($row['schedule'] ?? null) === null
        && ($row['no_absence_inference'] ?? false) === true, true),
    '29 October observations remain unclassified, with only five scoped QA schedules and no journeys, absences, or incidences');
foreach ($newDateExpectedCounts as $workerId => $eventCount) {
    $row = $read29['attendance_rows'][$workerId] ?? null;
    $qaMetadata = is_array($row['qa_scenario_metadata'] ?? null) ? $row['qa_scenario_metadata'] : [];
    $assert(is_array($row)
        && (int) ($row['observed_count'] ?? 0) === $eventCount
        && ($row['classification_available'] ?? true) === false
        && ($row['qa_source_classification'] ?? '') === 'SYNTHETIC_QA_FIXTURE',
        '29 October worker ' . $workerId . ' retains the requested count as unclassified QA observations');
    $assert(($qaMetadata['qa_workplace_id'] ?? null) === 1
        && ($qaMetadata['qa_local_date'] ?? null) === '2026-10-29'
        && ($qaMetadata['qa_worker_id'] ?? null) === $workerId
        && ($qaMetadata['qa_fixture_version'] ?? null) === 'CHECADOR_M02_QA_20261029_V1'
        && ($qaMetadata['qa_event_set_closed'] ?? false) === true
        && ($qaMetadata['qa_schedule_source_classification'] ?? null) === 'SYNTHETIC_QA_FIXTURE'
        && ($qaMetadata['qa_schedule_source_date'] ?? null) === '2026-10-29'
        && ($qaMetadata['qa_informational_state'] ?? null) === ($fixture['additional_daily_dates']['2026-10-29']['workers'][$workerId]['qa_informational_state'] ?? null)
        && ($row['qa_scenario_event_set_closed'] ?? false) === true
        && ($row['journey_status'] ?? '') === 'NOT_EVALUATED_IN_QA_FIXTURE'
        && ($row['pairing_status'] ?? '') === 'NOT_EVALUATED_IN_QA_FIXTURE'
        && ($row['source_level2_status'] ?? '') === 'NOT_EVALUATED_IN_QA_FIXTURE'
        && ($row['status'] ?? '') === 'classification_pending'
        && ($row['no_absence_inference'] ?? false) === true,
        '29 October worker ' . $workerId . ' binds informational QA state to a closed local scenario while canonical fields stay unmodified');
}
$expectedSlotTimes = [
    1004 => ['ENTRY:1' => '07:59', 'EXIT:1' => '17:03'],
    1005 => ['ENTRY:1' => '07:53', 'EXIT:1' => '13:02', 'ENTRY:2' => '14:01', 'EXIT:2' => '17:11'],
    1006 => ['ENTRY:1' => '08:14'],
    1007 => ['ENTRY:1' => '09:12', 'EXIT:1' => '18:03'],
    1008 => ['ENTRY:1' => '08:06', 'EXIT:1' => '13:09', 'ENTRY:2' => '14:07'],
];
$expectedSchedules = [
    1004 => '08:00–17:00',
    1005 => '08:00–13:00; 14:00–17:00',
    1006 => '08:00–17:00',
    1007 => '08:00–17:00',
    1008 => '08:00–13:00; 14:00–17:00',
];
foreach ($expectedSlotTimes as $workerId => $slots) {
    $row = $read29['attendance_rows'][$workerId] ?? [];
    $qaMetadata = is_array($row['qa_scenario_metadata'] ?? null) ? $row['qa_scenario_metadata'] : [];
    $expectedQaEventUuids = [];
    foreach (array_keys($slots) as $slotKey) {
        $eventUuid = $row['slots'][$slotKey]['event']['event_uuid'] ?? null;
        if (is_string($eventUuid) && $eventUuid !== '') $expectedQaEventUuids[] = $eventUuid;
    }
    $metadataQaEventUuids = is_array($qaMetadata['qa_event_uuids'] ?? null)
        ? array_values(array_filter($qaMetadata['qa_event_uuids'], 'is_string'))
        : [];
    sort($expectedQaEventUuids);
    sort($metadataQaEventUuids);
    $assert($metadataQaEventUuids === $expectedQaEventUuids
        && ($qaMetadata['qa_schedule_segments'] ?? null) === ($fixture['additional_daily_dates']['2026-10-29']['workers'][$workerId]['schedule_segments'] ?? null),
        '29 October worker ' . $workerId . ' QA metadata binds the informational state to the exact event UUIDs and synthetic schedule');
}
foreach ($expectedSlotTimes as $workerId => $slots) {
    foreach ($slots as $slotKey => $time) {
        $event = $read29['attendance_rows'][$workerId]['slots'][$slotKey]['event'] ?? null;
        $projectedTime = $read29['attendance_rows'][$workerId]['slots'][$slotKey]['time_local']['time'] ?? null;
        $assert(is_array($event)
            && $projectedTime === $time
            && !array_key_exists('pairing_role', $event)
            && !array_key_exists('segment_ordinal', $event),
            '29 October worker ' . $workerId . ' displays the explicit ' . $slotKey . ' observation at ' . $time . ' without canonical pairing fields');
    }
    $schedule = $read29['attendance_rows'][$workerId]['schedule'] ?? null;
    $segments = $schedule['horario_local']['segmentos'] ?? [];
    $assert(is_array($schedule)
        && ($schedule['source_classification'] ?? '') === 'SYNTHETIC_QA_FIXTURE'
        && ($schedule['horario_local']['fecha_local'] ?? '') === '2026-10-29'
        && ($read29['attendance_rows'][$workerId]['schedule_source_date'] ?? '') === '2026-10-29'
        && ($read29['attendance_rows'][$workerId]['schedule_source_classification'] ?? '') === 'SYNTHETIC_QA_FIXTURE'
        && count($segments) === (($workerId === 1005 || $workerId === 1008) ? 2 : 1),
        '29 October schedule for worker ' . $workerId . ' stays explicitly date-scoped and internally marked as synthetic QA');
}
$expectedOct28ScheduleDates = array_map(
    static fn(array $schedule): string => (string) ($schedule['horario_local']['fecha_local'] ?? ''),
    $read['effective_schedules'] ?? []
);
$assert(count($expectedOct28ScheduleDates) === 5
    && count(array_unique($expectedOct28ScheduleDates)) === 1
    && reset($expectedOct28ScheduleDates) === '2026-10-28'
    && ($service->read(1, '2026-10-30')['ok'] ?? true) === false
    && ($service->read(2, '2026-10-29')['ok'] ?? true) === false,
    '28 October retains its separately dated five schedules and no fixture schedule is available for 30 October');
$read29Again = $service->read(1, '2026-10-29');
$assert(array_column($read29['events'], 'event_uuid') === array_column($read29Again['events'] ?? [], 'event_uuid'),
    'reloading the 29 October fixture produces the same stable event identifiers without inserting duplicates');
$incidenceRead = $service->readIncidences(1, '2026-10-28');
$assert(($incidenceRead['ok'] ?? false) === true
    && count($incidenceRead['worker_options'] ?? []) === 46
    && ($incidenceRead['worker_options_status'] ?? '') === 'partial'
    && ($incidenceRead['attendance_roster']['coverage'] ?? '') === 'current_roster_historical_unverified'
    && array_filter($incidenceRead['administrative_incidences'] ?? []) !== [],
    'the explicit Incidences fixture must preserve existing QA data and offer the 46 current workers with historical coverage marked partial');

$canonicalProfiles = [];
foreach ($read['attendance_roster']['workers'] as $profile) {
    $canonicalProfiles[(int) $profile['worker_id']] = $profile;
}
$fixtureRows = array_filter($read['attendance_rows'], static fn(array $row): bool => ($row['qa_fixture'] ?? false) === true);
$unchangedRows = array_filter($read['attendance_rows'], static fn(array $row): bool => ($row['qa_fixture'] ?? false) !== true);
$assert(count($fixtureRows) === 5 && count($unchangedRows) === 41,
    'exactly five fixture rows must overlay the 46-worker roster while the other 41 remain untouched');
$workerIds = array_map(static fn(array $row): int => (int) $row['worker_id'], $read['attendance_rows']);
$assert(count($workerIds) === count(array_unique($workerIds)) && count(array_unique($workerIds)) === 46,
    'the full attendance result must contain no duplicate worker rows');
$optionsById = [];
foreach ($read['worker_options'] as $option) {
    $optionsById[(int) $option['worker_id']] = $option;
}
foreach ($read['attendance_rows'] as $workerId => $row) {
    $canonical = $canonicalProfiles[(int) $workerId] ?? null;
    $assert(is_array($canonical)
        && ($row['employee_number'] ?? null) === ($canonical['employee_number'] ?? null)
        && ($optionsById[(int) $workerId]['employee_number'] ?? null) === ($canonical['employee_number'] ?? null),
        'employee IDs/numbers must remain tied to the canonical roster for worker ' . $workerId);
    if (!in_array((int) $workerId, [1004, 1005, 1006, 1007, 1008], true)) {
        $assert(($row['full_name'] ?? null) === ($canonical['full_name'] ?? null)
            && !array_key_exists('qa_fixture', $row)
            && (int) ($row['observed_count'] ?? -1) === 0,
            'non-fixture worker ' . $workerId . ' must retain the canonical identity and unmodified empty projection');
    }
}
$expectedEventCounts = [1004 => 2, 1005 => 4, 1006 => 1, 1007 => 2, 1008 => 3];
foreach ($expectedEventCounts as $workerId => $eventCount) {
    $row = $read['attendance_rows'][$workerId] ?? null;
    $assert(is_array($row)
        && ($row['qa_fixture'] ?? false) === true
        && ($row['source_level2_status'] ?? '') !== 'VERIFIED'
        && ($row['classification_available'] ?? true) === false
        && (int) ($row['observed_count'] ?? 0) === $eventCount,
        'scenario count, synthetic marker, and noncanonical state must be preserved for worker ' . $workerId);
    $assert(($read['journeys'][$workerId]['evaluated'] ?? true) === false,
        'fixture scenarios must not claim an evaluated canonical Journey');
}
$assert(($read['attendance_rows'][1004]['slots']['ENTRY:1']['time_local']['time'] ?? null) === '08:00'
    && ($read['attendance_rows'][1004]['slots']['EXIT:1']['time_local']['time'] ?? null) === '17:00'
    && ($read['attendance_rows'][1006]['slots']['EXIT:1']['event'] ?? null) === null,
    'fixture schedule mapping must display local time and preserve the incomplete scenario');
$filtered = $service->read(1, '2026-10-28', 1004);
$assert(($filtered['ok'] ?? false) === true
    && array_keys($filtered['attendance_rows'] ?? []) === [1004]
    && count($filtered['worker_options'] ?? []) === 46,
    'worker filter must limit the table while retaining the complete active roster selector');
$employeeNumberMatches = array_values(array_filter(
    $read['worker_options'],
    static fn(array $option): bool => (string) ($option['employee_number'] ?? '') === '990101'
));
$employeeNumberRead = count($employeeNumberMatches) === 1
    ? $service->read(1, '2026-10-28', (int) $employeeNumberMatches[0]['worker_id'])
    : ['ok' => false, 'attendance_rows' => []];
$assert(count($employeeNumberMatches) === 1
    && (int) $employeeNumberMatches[0]['worker_id'] === 1004
    && count($employeeNumberRead['attendance_rows'] ?? []) === 1,
    'employee number 990101 must resolve to exactly one worker row');
$cleared = $service->read(1, '2026-10-28', null);
$assert(count($cleared['attendance_rows'] ?? []) === 46,
    'clearing the worker filter must restore all 46 authorized workers');
$assert(($service->read(2, '2026-10-28')['ok'] ?? true) === false
    && ($service->read(1, '2026-10-30')['ok'] ?? true) === false
    && ($service->read(1, '2026-10-28', 999999)['ok'] ?? true) === false
    && ($service->read(1, '2026-10-29', 999999)['ok'] ?? true) === false,
    'fixture data must reject other localities, other dates, and out-of-roster worker identities');

$renderPartial = static function (string $view, array $data): string {
    ob_start();
    View::renderPartial($view, $data);
    return (string) ob_get_clean();
};
$viewBase = [
    'selectedWorkplaceId' => 1,
    'selectedWorkplaceName' => 'Interlomas',
    'selectedWorkplaceKind' => 'Estación',
    'selectedDate' => '2026-10-28',
    'selectedWorkerId' => null,
    'state' => 'success',
    'stateMessage' => '',
    'persistentQaFixtureMode' => true,
    'attendanceRows' => array_values($read['attendance_rows']),
    'workerSelectOptions' => $read['worker_options'],
    'workerDisplayNames' => $read['worker_display_names'],
    'workerSelectStatus' => 'ready',
    'timezone' => $read['timezone'],
    'administrativeIncidences' => $read['administrative_incidences'],
    'administrativeErrors' => [],
    'journeys' => $read['journeys'],
    'journeyErrors' => [],
    'dashboardHref' => '/departamento-operativo/recursos-humanos/checador',
    'workspaceContext' => ['date' => '2026-10-28'],
];
$dailyHtml = $renderPartial(
    'departamento-operativo/2-recursos-humanos/checador/daily-attendance',
    $viewBase + ['snapshotRef' => null]
);
$dailyHtml29 = $renderPartial(
    'departamento-operativo/2-recursos-humanos/checador/daily-attendance',
    array_replace($viewBase, [
        'selectedDate' => '2026-10-29',
        'attendanceRows' => array_values($read29['attendance_rows']),
        'workerSelectOptions' => $read29['worker_options'],
        'workerDisplayNames' => $read29['worker_display_names'],
        'timezone' => $read29['timezone'],
        'administrativeIncidences' => $read29['administrative_incidences'],
        'journeys' => $read29['journeys'],
        'workspaceContext' => ['date' => '2026-10-29'],
    ]) + ['snapshotRef' => null]
);
$dailyBody29 = preg_match('/<table[^>]*id="checador-daily-attendance-table".*?<tbody>(.*?)<\/tbody>/s', $dailyHtml29, $tableBody29Match) === 1
    ? $tableBody29Match[1]
    : '';
$assert(preg_match_all('/<tr\b/', $dailyBody29) === 46
    && substr_count($dailyHtml29, '<option value="') === 47
    && substr_count($dailyHtml29, 'data-bs-target="#daily-attendance-detail-') === 46
    && substr_count($dailyHtml29, 'Ver todas las marcaciones (2)') === 2
    && substr_count($dailyHtml29, 'Ver todas las marcaciones (4)') === 1
    && substr_count($dailyHtml29, 'Ver todas las marcaciones (1)') === 1
    && substr_count($dailyHtml29, 'Ver todas las marcaciones (3)') === 1
    && str_contains($dailyHtml29, 'Pendiente de clasificación')
    && str_contains($dailyHtml29, '2026-10-29 07:59')
    && str_contains($dailyHtml29, '2026-10-29 17:03')
    && !preg_match('/\bQA\b|sint[eé]tic|simulad/i', $dailyHtml29),
    '29 October attendance renders the 46-worker roster, 12 requested event details, stable worker filters, and no QA label');
$daily29Dom = new DOMDocument();
@$daily29Dom->loadHTML('<?xml encoding="UTF-8">' . $dailyHtml29);
$daily29XPath = new DOMXPath($daily29Dom);
$slotIndexes = ['ENTRY:1' => 0, 'EXIT:1' => 1, 'ENTRY:2' => 2, 'EXIT:2' => 3];
foreach ($expectedSlotTimes as $workerId => $slots) {
    $workerName = (string) ($read29['worker_display_names'][$workerId] ?? '');
    $rows = $daily29XPath->query('//table[@id="checador-daily-attendance-table"]/tbody/tr');
    $workerRowNode = null;
    if ($rows !== false) {
        foreach ($rows as $candidateRow) {
            $name = trim((string) ($daily29XPath->query('./th[@scope="row"]', $candidateRow)->item(0)?->textContent ?? ''));
            if (str_contains($name, $workerName)) {
                $workerRowNode = $candidateRow;
                break;
            }
        }
    }
    foreach ($slots as $slotKey => $time) {
        $slotIndex = $slotIndexes[$slotKey] ?? -1;
        $cells = $workerRowNode === null || $slotIndex < 0 ? false : $daily29XPath->query('./td', $workerRowNode);
        $cell = $cells === false ? null : $cells->item($slotIndex + 1);
        $assert($workerRowNode !== null
            && $cell !== null
            && trim((string) $cell->textContent) === $time,
            '29 October rendered table cell for ' . $workerName . ' ' . $slotKey . ' visibly contains ' . $time);
    }
    $scheduleCell = $workerRowNode === null ? null : $daily29XPath->query('./td[1]', $workerRowNode)->item(0);
    $modalNodes = $daily29XPath->query('//div[contains(concat(" ", normalize-space(@class), " "), " modal ")][.//h2[contains(., "' . $workerName . '")]]');
    $modalScheduleNode = $modalNodes === false || $modalNodes->length !== 1
        ? null
        : $daily29XPath->query('.//span[normalize-space(.)="Horario efectivo"]/following-sibling::strong[1]', $modalNodes->item(0))->item(0);
    $expectedSchedule = $expectedSchedules[$workerId] ?? '';
    $assert($scheduleCell !== null
        && trim((string) $scheduleCell->textContent) === $expectedSchedule
        && $modalScheduleNode !== null
        && trim((string) $modalScheduleNode->textContent) === $expectedSchedule,
        '29 October schedule for ' . $workerName . ' matches between table and detail (table='
            . trim((string) ($scheduleCell?->textContent ?? '')) . ', modal='
            . trim((string) ($modalScheduleNode?->textContent ?? '')) . ', matches=' . (int) ($modalNodes?->length ?? 0) . ')');
    $expectedInformationalLabels = [
        1004 => 'Posiciones completas',
        1005 => 'Posiciones completas',
        1006 => 'Registro parcial',
        1007 => 'Horario por revisar',
        1008 => 'Registro parcial',
    ];
    $statusNode = $workerRowNode === null ? null : $daily29XPath->query('./td[6]/span[contains(concat(" ", normalize-space(@class), " "), " badge ")]', $workerRowNode)->item(0);
    $assert($statusNode !== null
        && trim((string) $statusNode->textContent) === ($expectedInformationalLabels[$workerId] ?? '')
        && !str_contains((string) ($modalNodes?->item(0)?->textContent ?? ''), $expectedInformationalLabels[$workerId] ?? '\0'),
        '29 October table shows the authorized information-only label for ' . $workerName . ' while its detail state remains unchanged');
}
$qaWorkerIds = array_fill_keys(array_keys($expectedSlotTimes), true);
$qaWorkerNames = array_values(array_intersect_key($read29['worker_display_names'], $qaWorkerIds));
$rosterRows = $daily29XPath->query('//table[@id="checador-daily-attendance-table"]/tbody/tr');
$otherWorkersRemainPending = true;
if ($rosterRows !== false) {
    foreach ($rosterRows as $rosterRow) {
        $rowId = (int) ($rosterRow->getAttribute('data-worker-id') ?: 0);
        $rowName = trim((string) ($daily29XPath->query('./th[@scope="row"]', $rosterRow)->item(0)?->textContent ?? ''));
        if (isset($qaWorkerIds[$rowId])
            || count(array_filter($qaWorkerNames, static fn(string $name): bool => $name !== '' && str_contains($rowName, $name))) > 0) continue;
        $rowStatus = trim((string) ($daily29XPath->query('./td[6]', $rosterRow)->item(0)?->textContent ?? ''));
        if ($rowStatus !== 'Pendiente de clasificación') $otherWorkersRemainPending = false;
    }
}
$assert($otherWorkersRemainPending,
    'the other 41 roster rows retain their neutral pending state without invented incomplete, review, or absence labels');
$canonicalGuardRow = $read29['attendance_rows'][1004];
$canonicalGuardRow['status'] = 'evaluated';
$canonicalGuardRow['journey_status'] = 'EVALUATED';
$canonicalGuardRow['pairing_status'] = 'EXACT_PAIR';
$canonicalGuardRow['source_level2_status'] = 'VERIFIED';
$canonicalGuardRow['classification_available'] = true;
$canonicalGuardHtml = $renderPartial(
    'departamento-operativo/2-recursos-humanos/checador/daily-attendance',
    array_replace($viewBase, [
        'selectedDate' => '2026-10-29',
        'attendanceRows' => [$canonicalGuardRow],
        'workerSelectOptions' => $read29['worker_options'],
        'workerDisplayNames' => $read29['worker_display_names'],
        'timezone' => $read29['timezone'],
        'administrativeIncidences' => [],
    ]) + ['snapshotRef' => null]
);
$assert(str_contains($canonicalGuardHtml, '>Clasificada</span>')
    && !str_contains($canonicalGuardHtml, 'Posiciones completas'),
    'a valid canonical evaluation takes precedence over stale informational QA metadata');
$misScopedQaHtml = $renderPartial(
    'departamento-operativo/2-recursos-humanos/checador/daily-attendance',
    array_replace($viewBase, [
        'selectedDate' => '2026-10-28',
        'attendanceRows' => [$read29['attendance_rows'][1004]],
        'workerSelectOptions' => $read29['worker_options'],
        'workerDisplayNames' => $read29['worker_display_names'],
        'timezone' => $read29['timezone'],
    ]) + ['snapshotRef' => null]
);
$assert(!str_contains($misScopedQaHtml, 'Posiciones completas')
    && !str_contains($misScopedQaHtml, 'Registro parcial')
    && !str_contains($misScopedQaHtml, 'Horario por revisar'),
    'informational QA status is suppressed when the requested date does not match the fixture scope');
$qaDisabledHtml = $renderPartial(
    'departamento-operativo/2-recursos-humanos/checador/daily-attendance',
    array_replace($viewBase, [
        'selectedDate' => '2026-10-29',
        'persistentQaFixtureMode' => false,
        'attendanceRows' => [$read29['attendance_rows'][1004]],
        'workerSelectOptions' => $read29['worker_options'],
        'workerDisplayNames' => $read29['worker_display_names'],
        'timezone' => $read29['timezone'],
    ]) + ['snapshotRef' => null]
);
$assert(!str_contains($qaDisabledHtml, 'Posiciones completas')
    && !str_contains($qaDisabledHtml, 'Registro parcial')
    && !str_contains($qaDisabledHtml, 'Horario por revisar'),
    'informational QA status is not presented when the locally authorized fixture mode is disabled');
$tableHead = preg_match('/<thead>(.*?)<\/thead>/s', $dailyHtml, $headMatch) === 1 ? $headMatch[1] : '';
$assert(substr_count($tableHead, '<th scope="col"') === 9,
    'attendance fixture rendering must preserve the nine-column table');
foreach ($read['worker_options'] as $workerOption) {
    $assert(str_contains($dailyHtml, (string) $workerOption['full_name'])
        && str_contains($dailyHtml, 'value="' . (int) $workerOption['worker_id'] . '"'),
        'attendance table and worker selector must render each display alias');
}
$dailyBody = preg_match('/<table[^>]*id="checador-daily-attendance-table".*?<tbody>(.*?)<\/tbody>/s', $dailyHtml, $tableBodyMatch) === 1
    ? $tableBodyMatch[1]
    : '';
$assert(substr_count($dailyHtml, 'data-bs-target="#daily-attendance-detail-') === 46
    && preg_match_all('/<tr\b/', $dailyBody) === 46
    && substr_count($dailyHtml, '<option value="') === 47
    && str_contains($dailyHtml, 'Jornada completa')
    && str_contains($dailyHtml, 'Jornada incompleta')
    && str_contains($dailyHtml, 'Requiere revisión')
    && str_contains($dailyHtml, '08:00–17:00')
    && str_contains($dailyHtml, 'text-bg-success')
    && str_contains($dailyHtml, 'text-bg-info')
    && str_contains($dailyHtml, 'text-bg-warning')
    && !str_contains($dailyHtml, 'qa_fixture=1'),
    'attendance DataTables fragment must render all 46 roster rows, all 46 filter choices, and one detail action per worker');
$modalDesignCounts = [
    substr_count($dailyHtml, 'class="modal-header bg-primary align-items-start"'),
    substr_count($dailyHtml, 'class="modal-dialog modal-dialog-scrollable modal-xl"'),
    substr_count($dailyHtml, 'class="modal-footer justify-content-end gap-2"'),
    substr_count($dailyHtml, 'class="btn bg-danger-subtle text-danger"'),
    substr_count($dailyHtml, 'btn-close btn-close-white'),
    substr_count($dailyHtml, 'Ver incidencias</a>'),
];
$assert($modalDesignCounts[0] === 46
    && $modalDesignCounts[1] === 46
    && $modalDesignCounts[2] === 46
    && $modalDesignCounts[3] === 46
    && $modalDesignCounts[4] >= 46
    && $modalDesignCounts[5] === 46,
    'each detail modal must use the institutional header, scrollable XL layout, and right-aligned actions: ' . json_encode($modalDesignCounts));
$assert(!preg_match('/\bQA\b|sint[eé]tic|simulad/i', $dailyHtml),
    'attendance presentation must not expose QA, synthetic, or simulated labels');
$assert(str_contains($dailyHtml, '2026-10-28 08:00')
    && str_contains($dailyHtml, '2026-10-28 17:00')
    && str_contains($dailyHtml, 'Marcación observada'),
    'attendance detail must retain the selected worker marks and standard operational state');
$assert(substr_count($dailyHtml, 'Ver todas las marcaciones (2)') === 2
    && substr_count($dailyHtml, 'Ver todas las marcaciones (4)') === 1
    && substr_count($dailyHtml, 'Ver todas las marcaciones (1)') === 1
    && substr_count($dailyHtml, 'Ver todas las marcaciones (3)') === 1
    && substr_count($dailyHtml, 'Marcaciones observadas del colaborador') === 5,
    'the five scenario details must render their existing 2/4/1/2/3 events, totaling all 12 marks');
$filteredRead = $service->read(1, '2026-10-28', 1004);
$filteredHtml = $renderPartial(
    'departamento-operativo/2-recursos-humanos/checador/daily-attendance',
    array_replace($viewBase, [
        'attendanceRows' => array_values($filteredRead['attendance_rows']),
        'workerSelectOptions' => $filteredRead['worker_options'],
        'selectedWorkerId' => 1004,
    ])
);
$filteredBody = preg_match('/<table[^>]*id="checador-daily-attendance-table".*?<tbody>(.*?)<\/tbody>/s', $filteredHtml, $filteredBodyMatch) === 1
    ? $filteredBodyMatch[1]
    : '';
$assert(substr_count($filteredHtml, 'data-bs-target="#daily-attendance-detail-') === 1
    && preg_match_all('/<tr\b/', $filteredBody) === 1
    && substr_count($filteredHtml, '<option value="') === 47
    && str_contains($filteredHtml, 'value="1004" selected'),
    'filter rendering must show one worker row while retaining all 46 worker options plus Todos');

$journeyHtml = $renderPartial(
    'departamento-operativo/2-recursos-humanos/checador/workspace-journeys',
    $viewBase
);
$assert(substr_count($journeyHtml, 'Sin evaluación · escenario QA local') === 5
    && substr_count($journeyHtml, 'Escenario sintético QA local; no representa una evaluación oficial de Journey.') === 5
    && str_contains($journeyHtml, 'Marcaciones observadas')
    && str_contains($journeyHtml, '5542134f-f576-4f65-a8c5-7cd3327c1298'),
    'Journey QA rows remain neutral, disclose their synthetic provenance, and retain event traceability');
$assert(str_contains($journeyHtml, 'Alicia Montalvo')
    && str_contains($journeyHtml, 'Elisa Navarro'),
    'Journey view must use the same local display aliases');
$assert(!str_contains($journeyHtml, 'Jornada completa')
    && !str_contains($journeyHtml, 'Jornada incompleta')
    && !str_contains($journeyHtml, 'Requiere revisión')
    && !preg_match('/\bP1\b|\bP2\b|\bEVALUATED\b/i', $journeyHtml),
    'unevaluated Journey fixtures never present their scenario labels as canonical results');
$assert(!str_contains($journeyHtml, 'P1') && !str_contains($journeyHtml, 'P2')
    && !str_contains($journeyHtml, 'EVALUATED'),
    'Journey rendering must not claim canonical P1/P2 results');

$incidenceHtml = $renderPartial(
    'departamento-operativo/2-recursos-humanos/checador/workspace-incidences',
    array_replace($viewBase, [
        'incidenceCreateAllowed' => true,
        'workerSelectOptions' => $incidenceRead['worker_options'],
        'workerDisplayNames' => $incidenceRead['worker_display_names'],
        'administrativeIncidences' => $incidenceRead['administrative_incidences'],
        'workerSelectStatus' => $incidenceRead['worker_options_status'],
        'attendanceRoster' => $incidenceRead['attendance_roster'],
    ])
);
$incidenceDom = new DOMDocument();
@$incidenceDom->loadHTML($incidenceHtml);
$incidenceXPath = new DOMXPath($incidenceDom);
$incidenceWorkerOptions = $incidenceXPath->query('//select[@id="checador-incidence-filter-worker"]/option');
$assert($incidenceWorkerOptions !== false && $incidenceWorkerOptions->length === 47,
    'Incidences Select2 source markup must contain the all-workers option and all 46 roster options');
$assert(str_contains($incidenceHtml, 'Desviación de horario')
    && str_contains($incidenceHtml, 'Diego Arriaga')
    && str_contains($incidenceHtml, 'Elisa Navarro')
    && str_contains($incidenceHtml, 'Carla Montoya')
    && str_contains($incidenceHtml, 'Requiere revisión')
    && str_contains($incidenceHtml, 'La entrada se registró después del inicio programado.'),
    'Incidence rendering must present the scenario in normal operational language');
$fixtureIncidenceCount = array_sum(array_map('count', $read['administrative_incidences']));
$assert(substr_count($incidenceHtml, 'class="modal-header bg-primary align-items-start"') === $fixtureIncidenceCount
    && substr_count($incidenceHtml, 'class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"') === $fixtureIncidenceCount
    && substr_count($incidenceHtml, 'class="modal-footer justify-content-end gap-2"') === $fixtureIncidenceCount
    && substr_count($incidenceHtml, 'class="btn bg-danger-subtle text-danger"') >= $fixtureIncidenceCount
    && substr_count($incidenceHtml, 'btn-close btn-close-white') >= $fixtureIncidenceCount
    && substr_count($incidenceHtml, 'data-bs-target="#incidence-history-') === $fixtureIncidenceCount,
    'incidence details must retain their open controls and render the institutional responsive modal shell');
$assert(!str_contains($incidenceHtml, 'Historial de revisiones no disponible'),
    'fixture details without revision history must not invent a history message');
$diegoIncidenceContext = $renderPartial(
    'departamento-operativo/2-recursos-humanos/checador/workspace-incidences',
    array_replace($viewBase, ['selectedWorkerId' => 1007])
);
$diegoContextDom = new \DOMDocument();
@$diegoContextDom->loadHTML($diegoIncidenceContext);
$diegoContextText = (new \DOMXPath($diegoContextDom))->query('//p[@aria-live="polite"]')->item(0)?->textContent ?? '';
$diegoEmployeeNumber = (string) ($optionsById[1007]['employee_number'] ?? '');
$assert(str_contains($diegoContextText, 'Diego Arriaga')
    && $diegoEmployeeNumber !== ''
    && str_contains($diegoIncidenceContext, 'N.º ' . htmlspecialchars($diegoEmployeeNumber, ENT_QUOTES, 'UTF-8'))
    && str_contains($diegoContextText, '28/10/2026')
    && !str_contains($diegoContextText, 'Todos los colaboradores')
    && !str_contains($diegoIncidenceContext, 'Las acciones disponibles dependen de los permisos de este perfil'),
    'selected incidence context must show Diego and the selected date compactly, with employee number secondary and no stale all-workers label');
$assert(!preg_match('/\bQA\b|sint[eé]tic|simulad/i', $incidenceHtml),
    'Incidence presentation must not expose QA, synthetic, or simulated labels');
$assert(!str_contains($incidenceHtml, 'action="/departamento-operativo/recursos-humanos/checador/incidencias/crear"'),
    'fixture incidence rendering must not expose create operations');

// Exercise the actual GET controller and full view/layout render with the
// read-only local fixture. The demo date override avoids a network timezone
// dependency while preserving the production date-resolution branch shape.
$controllerEnv = [
    'APP_ENV' => 'dev',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => 'localhost',
    'DB_DATABASE' => 'bd_admongas',
    'CHECADOR_M02_PERSISTENT_QA_FIXTURES_ENABLED' => 'true',
    'CHECADOR_ADVANCED_DEMO_ENABLED' => 'true',
    'CHECADOR_ADVANCED_DEMO_SCENARIO' => 'ready',
    'CHECADOR_ADVANCED_DEMO_REFERENCE_DATE' => '2026-10-29',
];
$controllerPreviousEnv = [];
foreach ($controllerEnv as $key => $value) {
    $controllerPreviousEnv[$key] = $_ENV[$key] ?? null;
    $_ENV[$key] = $value;
}
$previousGet = $_GET;
$previousSession = $_SESSION ?? null;
$previousRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
$previousRequestUri = $_SERVER['REQUEST_URI'] ?? null;
$previousHost = $_SERVER['HTTP_HOST'] ?? null;
$_SERVER['HTTP_HOST'] = 'portal3.test';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/departamento-operativo/recursos-humanos/checador/asistencia';
$_SESSION = [];
Session::set('usuario', ['id' => 33, 'id_estacion' => 1]);
Session::set('module_context', ['checador' => ['id_estacion' => 1, 'id_depto' => null]]);
Auth::forget();
$controller = new ChecadorController(static fn(): DateTimeImmutable => new DateTimeImmutable('2026-10-29 16:00:00', new DateTimeZone('UTC')));
$renderControllerGet = static function (array $query) use ($controller): string {
    $_GET = $query;
    http_response_code(200);
    ob_start();
    $controller->dailyAttendance();
    return (string) ob_get_clean();
};
$defaultGetHtml = $renderControllerGet([]);
$explicit29GetHtml = $renderControllerGet(['date' => '2026-10-29']);
$explicit28GetHtml = $renderControllerGet(['date' => '2026-10-28']);
$aliciaGetHtml = $renderControllerGet(['date' => '2026-10-29', 'worker_id' => '1004']);
$brunoGetHtml = $renderControllerGet(['date' => '2026-10-29', 'worker_id' => '1005']);
$assert(str_contains($defaultGetHtml, 'value="2026-10-29"')
    && str_contains($defaultGetHtml, 'Alicia Montalvo')
    && str_contains($defaultGetHtml, '2026-10-29 07:59')
    && str_contains($defaultGetHtml, '08:00–17:00')
    && str_contains($defaultGetHtml, '08:00–13:00; 14:00–17:00')
    && str_contains($explicit29GetHtml, 'value="2026-10-29"')
    && str_contains($explicit29GetHtml, 'Elisa Navarro')
    && str_contains($explicit29GetHtml, '2026-10-29 14:07')
    && str_contains($explicit29GetHtml, '08:00–17:00')
    && str_contains($explicit29GetHtml, '08:00–13:00; 14:00–17:00'),
    'controller GET without date and GET date=2026-10-29 both render the 29 October fixture and five explicit schedules through the real view and layout');
$assert(str_contains($explicit28GetHtml, 'value="2026-10-28"')
    && str_contains($explicit28GetHtml, '2026-10-28 08:00')
    && !str_contains($explicit28GetHtml, '2026-10-29 07:59')
    && !str_contains($defaultGetHtml, '2026-10-28 08:00'),
    'controller renders for 28 and 29 remain strictly separated by selected date');
$aliciaFilteredBody = preg_match('/<table[^>]*id="checador-daily-attendance-table".*?<tbody>(.*?)<\/tbody>/s', $aliciaGetHtml, $aliciaBodyMatch) === 1 ? $aliciaBodyMatch[1] : '';
$brunoFilteredBody = preg_match('/<table[^>]*id="checador-daily-attendance-table".*?<tbody>(.*?)<\/tbody>/s', $brunoGetHtml, $brunoBodyMatch) === 1 ? $brunoBodyMatch[1] : '';
$assert(preg_match_all('/<tr\b/', $aliciaFilteredBody) === 1
    && str_contains($aliciaFilteredBody, 'Alicia Montalvo')
    && str_contains($aliciaFilteredBody, '07:59')
    && preg_match_all('/<tr\b/', $brunoFilteredBody) === 1
    && str_contains($brunoFilteredBody, 'Bruno Cervantes')
    && str_contains($brunoFilteredBody, '17:11'),
    'controller GET worker filters render Alicia and Bruno separately with their selected day events');
$controllerBody = preg_match('/<table[^>]*id="checador-daily-attendance-table".*?<tbody>(.*?)<\/tbody>/s', $defaultGetHtml, $controllerBodyMatch) === 1
    ? $controllerBodyMatch[1]
    : '';
$attendanceInitializer = (string) file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.attendance.datatable.init.js');
$assert(preg_match_all('/<tr\b/', $controllerBody) === 46
    && str_contains($attendanceInitializer, 'serverSide: false')
    && str_contains($attendanceInitializer, 'pageLength: 10')
    && str_contains($attendanceInitializer, "order: [[0, 'asc']]")
    && !str_contains($attendanceInitializer, 'searching: false'),
    'controller HTML contains all 46 DataTables rows and retains client-side paging, ordering, and search configuration');
foreach ($controllerPreviousEnv as $key => $value) {
    if ($value === null) unset($_ENV[$key]); else $_ENV[$key] = $value;
}
$_GET = $previousGet;
if ($previousSession === null) unset($_SESSION); else $_SESSION = $previousSession;
if ($previousRequestMethod === null) unset($_SERVER['REQUEST_METHOD']); else $_SERVER['REQUEST_METHOD'] = $previousRequestMethod;
if ($previousRequestUri === null) unset($_SERVER['REQUEST_URI']); else $_SERVER['REQUEST_URI'] = $previousRequestUri;
if ($previousHost === null) unset($_SERVER['HTTP_HOST']); else $_SERVER['HTTP_HOST'] = $previousHost;
Auth::forget();

$localEnvironment = [
    'APP_ENV' => 'dev',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => 'localhost',
    'DB_DATABASE' => 'bd_admongas',
    'CHECADOR_M02_PERSISTENT_QA_FIXTURES_ENABLED' => 'true',
];
$previousHost = $_SERVER['HTTP_HOST'] ?? null;
$previousEnv = [];
foreach ($localEnvironment as $key => $value) {
    $previousEnv[$key] = $_ENV[$key] ?? null;
    $_ENV[$key] = $value;
}
$_SERVER['HTTP_HOST'] = 'portal3.test';
$fixtureRouter = new ChecadorPersistentQaFixtureService();
$enabledFixtureValue = $_ENV['CHECADOR_M02_PERSISTENT_QA_FIXTURES_ENABLED'];
$_ENV['CHECADOR_M02_PERSISTENT_QA_FIXTURES_ENABLED'] = 'false';
$fixtureDisabled = !$fixtureRouter->shouldServe('daily-attendance', 1, '2026-10-28', []);
$_ENV['CHECADOR_M02_PERSISTENT_QA_FIXTURES_ENABLED'] = $enabledFixtureValue;
$assert($fixtureRouter->shouldServe('daily-attendance', 1, '2026-10-28', [])
    && $fixtureRouter->shouldServe('daily-attendance', 1, '2026-10-29', [])
    && $fixtureRouter->shouldServe('journeys', 1, '2026-10-28', ['worker_id' => '1004'])
    && $fixtureRouter->shouldServe('incidences', 1, '2026-10-28', ['worker_id' => '1008'])
    && $fixtureRouter->shouldServe('incidences', 1, '2026-10-28', [])
    && $fixtureRouter->shouldServe('incidences', 1, '2026-10-28', ['worker_id' => '1010'])
    && !$fixtureRouter->shouldServe('journeys', 1, '2026-10-28', ['worker_id' => '1010'])
    && !$fixtureRouter->shouldServe('journeys', 1, '2026-10-29', ['worker_id' => '1004'])
    && !$fixtureRouter->shouldServe('incidences', 1, '2026-10-29', [])
    && !$fixtureRouter->shouldServe('adjustments', 1, '2026-10-28', [])
    && !$fixtureRouter->shouldServe('daily-attendance', 2, '2026-10-28', [])
    && !$fixtureRouter->shouldServe('daily-attendance', 1, '2026-10-30', [])
    && $fixtureDisabled,
    'local fixture routing adds 29 October only to attendance and keeps Incidences/Journeys limited to 28 October');
foreach ($previousEnv as $key => $value) {
    if ($value === null) {
        unset($_ENV[$key]);
    } else {
        $_ENV[$key] = $value;
    }
}
if ($previousHost === null) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $previousHost;
}

$assert(ChecadorPersistentQaFixtureService::enabled($localEnvironment, 'portal3.test')
    && !ChecadorPersistentQaFixtureService::enabled(array_merge($localEnvironment, ['APP_ENV' => 'prod']), 'portal3.test')
    && !ChecadorPersistentQaFixtureService::enabled(array_merge($localEnvironment, ['DB_DATABASE' => 'bd_admongas_v2_preprod']), 'portal3.test')
    && !ChecadorPersistentQaFixtureService::enabled($localEnvironment, 'portal3.example.com'),
    'fixture execution must be unavailable outside local development, local database, and local host');

$countsAfter = $db->selectOne('SELECT COUNT(*) AS row_count, COUNT(DISTINCT event_uuid) AS uuid_count FROM op_rh_v2_marcaciones_crudas');
$rawUuidSnapshotAfter = array_map(
    static fn(object $row): string => (string) $row->event_uuid,
    $db->select('SELECT event_uuid FROM op_rh_v2_marcaciones_crudas ORDER BY event_uuid')
);
$assert((int) ($countsAfter->row_count ?? 0) === 89 && (int) ($countsAfter->uuid_count ?? 0) === 89,
    'read-only fixture integration must leave all 89 raw events intact');
$assert(count($rawUuidSnapshotAfter) === 89
    && hash_equals($rawUuidDigestBefore, hash('sha256', implode("\n", $rawUuidSnapshotAfter))),
    'the exact 89 original raw-event UUIDs remain unchanged after both fixture dates are read');

echo "PASS: 28 October QA data remains on its 12 persisted UUIDs; 29 October adds 12 config-backed events; MariaDB remains 89 rows / 89 UUIDs with no writes.\n";

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// The view's existing CSRF forms render tokens using the standard PHP session.
$_SESSION = [];

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;

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

$keys = ['APP_ENV', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO', 'CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'];
$original = [];
foreach ($keys as $key) {
    $original[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
}

$setConfig = static function (string $environment, ?string $enabled, ?string $scenario = null): void {
    $_ENV['APP_ENV'] = $environment;
    if ($enabled === null) {
        unset($_ENV['CHECADOR_ADVANCED_DEMO_ENABLED']);
    } else {
        $_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = $enabled;
    }
    if ($scenario === null) {
        unset($_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO']);
    } else {
        $_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] = $scenario;
    }
    if (in_array(strtolower($environment), ['dev', 'development', 'local'], true) && $enabled !== null) {
        $_ENV['CHECADOR_ADVANCED_DEMO_REFERENCE_DATE'] = ChecadorDailyReadService::DEMO_REFERENCE_DATE;
    } else {
        unset($_ENV['CHECADOR_ADVANCED_DEMO_REFERENCE_DATE']);
    }
};

$networkCalls = 0;
$tokenCalls = 0;
$guardedClient = new Client([
    'base_uri' => 'https://must-not-connect.invalid/',
    'handler' => static function () use (&$networkCalls): never {
        $networkCalls++;
        throw new RuntimeException('Unexpected outbound HTTP in demo mode.');
    },
    'http_errors' => false,
]);
$user = new Usuario();
$user->id = 999;
$makeService = static function (string $scenario) use ($guardedClient, &$tokenCalls): ChecadorDailyReadService {
    $_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] = $scenario;
    return new ChecadorDailyReadService(
        $guardedClient,
        static function (Usuario $user) use (&$tokenCalls): string {
            $tokenCalls++;
            return 'test-token-must-not-be-used';
        },
        'https://must-not-connect.invalid/'
    );
};

try {
    $setConfig('dev', null);
    $assert(ChecadorDailyReadService::demoModeEnabled() === false, 'demo mode is disabled by default');

    $setConfig('preproduction', 'true');
    $assert(ChecadorDailyReadService::demoModeEnabled() === false, 'preproduction cannot activate synthetic responses');
    $setConfig('production', 'true');
    $assert(ChecadorDailyReadService::demoModeEnabled() === false, 'production cannot activate synthetic responses');
    $setConfig('testing', 'true');
    $assert(ChecadorDailyReadService::demoModeEnabled() === false, 'test environment is not treated as development runtime');

    $setConfig('dev', 'true', 'ready');
    $service = $makeService('ready');
    $timezone = $service->resolveWorkplaceLocalDay($user, 14, '2026-10-19');
    $assert($service->isDemoMode() && $service->demoScenario() === 'ready', 'explicit local development configuration selects the allowlisted demo scenario');
    $assert(($timezone['ok'] ?? false) === true
        && ($timezone['data']['timezone'] ?? '') === 'America/Mexico_City'
        && ($timezone['data']['from_utc'] ?? '') === '2026-10-19T06:00:00Z'
        && ($timezone['data']['to_utc'] ?? '') === '2026-10-20T06:00:00Z', 'valid synthetic workplace timezone returns exact day boundaries');
    $snapshot = $service->createDailyPunchSnapshot($user, 14, $timezone['data'] ?? [], 'demo-create-001');
    $level2 = $service->getLevel2WindowStatus($user, 14, $timezone['data'] ?? []);
    $page = $service->readDailyPunchSnapshotPage($user, (string) ($snapshot['data']['snapshot_token'] ?? ''));
    $readyDemoEvents = $page['events'] ?? [];
    $assert(($snapshot['ok'] ?? false) === true
        && ($snapshot['data']['status'] ?? '') === 'READY'
        && ($snapshot['data']['completeness_status'] ?? '') === 'LEVEL_1_COMPLETE_ONLY', 'synthetic Nivel 1 snapshot is available without overstating Nivel 2 completeness');
    $assert(($level2['data']['window_level2_status'] ?? '') === 'VERIFIED', 'verified Nivel 2 scenario follows the existing response contract');
    $assert(($page['ok'] ?? false) === true
        && count($page['events'] ?? []) === 3
        && ($page['events'][0]['id_estacion_resuelta'] ?? 0) === 14
        && array_key_exists('id_personal_resuelto', $page['events'][0] ?? [])
        && ($page['events'][0]['id_personal_resuelto'] ?? 0) === 900001
        && ($page['events'][0]['integrity']['status'] ?? '') === 'UNVERIFIABLE'
        && array_key_exists('ocurrido_at_utc', $page['events'][2] ?? [])
        && $page['events'][2]['id_personal_resuelto'] === null
        && $page['events'][2]['ocurrido_at_utc'] === null, 'record fixture preserves a canonical synthetic worker, unresolved identity/time, selected station, and unverified integrity');

    $expectedSchedules = [
        'ready' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'day_schedule' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'night_schedule' => ['SCHEDULED', '22:00:00', '06:00:00', 1],
        'day_off' => ['DAY_OFF', null, null, null],
        'unscheduled' => ['UNSCHEDULED', null, null, null],
        'journey_with_punches' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'journey_insufficient' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'journey_review' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'two_punches' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'four_punches' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'many_punches' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'missing_entry' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'missing_exit' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'ambiguous_pairing' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'overnight_journey' => ['SCHEDULED', '22:00:00', '06:00:00', 1],
        'three_segments' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
        'journey_not_evaluated' => ['SCHEDULED', '08:00:00', '17:00:00', 0],
    ];
    $journeyFixtures = [];
    $scheduleFixtures = [];
    $eventFixtures = [];
    foreach ($expectedSchedules as $scenario => [$expectedStatus, $expectedStart, $expectedEnd, $expectedOffset]) {
        $service = $makeService($scenario);
        $scenarioTimezone = $service->resolveWorkplaceLocalDay($user, 14, '2026-10-19');
        $scenarioSnapshot = $service->createDailyPunchSnapshot($user, 14, $scenarioTimezone['data'] ?? [], 'demo-v3a-' . $scenario);
        $scenarioPage = $service->readDailyPunchSnapshotPage($user, (string) ($scenarioSnapshot['data']['snapshot_token'] ?? ''));
        $schedule = $service->getEffectiveSchedule($user, 900001, 14, '2026-10-19');
        $journey = $service->evaluateJourney(
            $user,
            900001,
            14,
            '2026-10-19',
            (string) ($scenarioSnapshot['data']['snapshot_audit_ref'] ?? '')
        );
        $scheduleData = $schedule['data'] ?? [];
        $scheduleFixtures[$scenario] = $scheduleData;
        $segment = $scheduleData['horario_local']['segmentos'][0] ?? [];
        $journeyData = $journey['data'] ?? [];
        $journeyFixtures[$scenario] = $journeyData;
        $eventFixtures[$scenario] = $scenarioPage['events'] ?? [];
        $assert(($scenarioPage['ok'] ?? false) === true
            && ($schedule['ok'] ?? false) === true
            && ($scheduleData['id_personal'] ?? 0) === 900001
            && ($scheduleData['id_estacion'] ?? 0) === 14
            && ($scheduleData['fecha_local'] ?? '') === '2026-10-19'
            && ($scheduleData['estado_jornada'] ?? '') === $expectedStatus
            && ($expectedStart === null || (($segment['hora_inicio_local'] ?? '') === $expectedStart
                && ($segment['hora_fin_local'] ?? '') === $expectedEnd
                && ($segment['dia_offset_fin'] ?? null) === $expectedOffset)), "{$scenario} schedule follows the effective-schedule response contract");
        $assert(($journey['ok'] ?? false) === true
            && ($journeyData['context']['id_personal'] ?? 0) === 900001
            && ($journeyData['context']['id_estacion'] ?? 0) === 14
            && ($journeyData['context']['fecha_local'] ?? '') === '2026-10-19'
            && ($journeyData['context']['timezone'] ?? '') === 'America/Mexico_City'
            && array_key_exists('level2_evaluation_ref', $journeyData['source_evidence'] ?? [])
            && $journeyData['source_evidence']['level2_evaluation_ref'] === null
            && ($journeyData['source_evidence']['snapshot_audit_ref'] ?? '') === ($scenarioSnapshot['data']['snapshot_audit_ref'] ?? ''), "{$scenario} Journey preserves worker, station, workday, timezone and snapshot provenance");
    }
    $assert(($journeyFixtures['ready']['evaluated'] ?? false) === true
        && count($journeyFixtures['ready']['evaluated_raw_events'] ?? []) === 2
        && ($journeyFixtures['ready']['pairing']['segments'][0]['entry_event_uuid'] ?? '') === 'DEMO-CHECADOR-V3A-DAY-ENTRY'
        && ($journeyFixtures['ready']['pairing']['segments'][0]['exit_event_uuid'] ?? '') === 'DEMO-CHECADOR-V3A-DAY-EXIT', 'ready fixture returns its pre-defined entry/exit result and P3-F references');
    $assert(($readyDemoEvents[0]['ocurrido_at_utc'] ?? '') === '2026-10-19 14:00:00'
        && ($readyDemoEvents[1]['ocurrido_at_utc'] ?? '') === '2026-10-19 23:00:00'
        && ($timezone['data']['timezone'] ?? '') === 'America/Mexico_City', 'fixture timestamps remain UTC and agree with the selected workplace timezone');
    $assert(($journeyFixtures['night_schedule']['evaluated'] ?? true) === false
        && ($journeyFixtures['night_schedule']['journey_status'] ?? '') === 'DATA_INCOMPLETE'
        && ($journeyFixtures['night_schedule']['schedule']['horario_local']['segmentos'][0]['dia_offset_fin'] ?? 0) === 1
        && ($eventFixtures['night_schedule'][0]['ocurrido_at_utc'] ?? '') === '2026-10-20 04:00:00', 'night schedule retains cross-midnight workday and UTC event timestamp without inventing an exit');
    $assert(($journeyFixtures['day_off']['journey_status'] ?? '') === 'DAY_OFF'
        && ($journeyFixtures['day_off']['evaluated'] ?? true) === false
        && ($journeyFixtures['unscheduled']['journey_status'] ?? '') === 'UNSCHEDULED_DAY'
        && ($journeyFixtures['unscheduled']['evaluated'] ?? true) === false, 'day off and unscheduled remain distinct and do not infer absence');
    $assert(($journeyFixtures['journey_insufficient']['journey_status'] ?? '') === 'DATA_INCOMPLETE'
        && ($journeyFixtures['journey_insufficient']['source_evidence']['window_level2_status'] ?? '') === 'INCOMPLETE'
        && array_key_exists('evaluated_raw_events', $journeyFixtures['journey_insufficient'])
        && $journeyFixtures['journey_insufficient']['evaluated_raw_events'] === null, 'incomplete Journey preserves its source state and null evaluated evidence');
    $assert(($journeyFixtures['journey_review']['journey_status'] ?? '') === 'REVIEW_REQUIRED'
        && ($journeyFixtures['journey_review']['pairing']['pairing_status'] ?? '') === 'AMBIGUOUS_PAIRING'
        && ($journeyFixtures['journey_review']['calculation']['estado_calculo'] ?? '') === 'REVIEW_REQUIRED', 'review Journey uses the fixture result without local pairing calculations');

    $expectedP3hRoles = [
        'two_punches' => [
            'DEMO-CHECADOR-V3B-TWO-ENTRY' => ['ENTRY', 1],
            'DEMO-CHECADOR-V3B-TWO-EXIT' => ['EXIT', 1],
        ],
        'four_punches' => [
            'DEMO-CHECADOR-V3B-FOUR-EXIT-2' => ['EXIT', 2],
            'DEMO-CHECADOR-V3B-FOUR-ENTRY-1' => ['ENTRY', 1],
            'DEMO-CHECADOR-V3B-FOUR-EXIT-1' => ['EXIT', 1],
            'DEMO-CHECADOR-V3B-FOUR-ENTRY-2' => ['ENTRY', 2],
        ],
        'many_punches' => [
            'DEMO-CHECADOR-V3B-MANY-ENTRY-1' => ['ENTRY', 1],
            'DEMO-CHECADOR-V3B-MANY-SURPLUS' => ['SURPLUS', 1],
            'DEMO-CHECADOR-V3B-MANY-UNMATCHED' => ['UNMATCHED', null],
            'DEMO-CHECADOR-V3B-MANY-EXIT-1' => ['EXIT', 1],
            'DEMO-CHECADOR-V3B-MANY-ENTRY-2' => ['ENTRY', 2],
            'DEMO-CHECADOR-V3B-MANY-EXIT-2' => ['EXIT', 2],
        ],
        'missing_entry' => ['DEMO-CHECADOR-V3B-MISSING-ENTRY-EXIT' => ['EXIT', 1]],
        'missing_exit' => ['DEMO-CHECADOR-V3B-MISSING-EXIT-ENTRY' => ['ENTRY', 1]],
        'ambiguous_pairing' => [
            'DEMO-CHECADOR-V3B-AMBIGUOUS-ENTRY' => ['ENTRY', 1],
            'DEMO-CHECADOR-V3B-AMBIGUOUS-EXIT' => ['EXIT', 1],
        ],
        'overnight_journey' => [
            'DEMO-CHECADOR-V3B-OVERNIGHT-ENTRY' => ['ENTRY', 1],
            'DEMO-CHECADOR-V3B-OVERNIGHT-EXIT' => ['EXIT', 1],
        ],
        'three_segments' => [
            'DEMO-CHECADOR-V3B-SEGMENT-1-ENTRY' => ['ENTRY', 1],
            'DEMO-CHECADOR-V3B-SEGMENT-1-EXIT' => ['EXIT', 1],
            'DEMO-CHECADOR-V3B-SEGMENT-2-ENTRY' => ['ENTRY', 2],
            'DEMO-CHECADOR-V3B-SEGMENT-2-EXIT' => ['EXIT', 2],
            'DEMO-CHECADOR-V3B-SEGMENT-3-ENTRY' => ['ENTRY', 3],
            'DEMO-CHECADOR-V3B-SEGMENT-3-EXIT' => ['EXIT', 3],
        ],
    ];
    foreach ($expectedP3hRoles as $scenario => $expectedRoles) {
        $journeyData = $journeyFixtures[$scenario] ?? [];
        $actualRoles = [];
        foreach ($journeyData['evaluated_raw_events'] ?? [] as $evaluatedEvent) {
            $actualRoles[$evaluatedEvent['event_uuid']] = [
                $evaluatedEvent['pairing_role'] ?? null,
                $evaluatedEvent['segment_ordinal'] ?? null,
            ];
        }
        $assert(($journeyData['evaluated'] ?? false) === true
            && $actualRoles === $expectedRoles
            && ($journeyData['provenance']['evaluated_raw_events'] ?? null) === ($journeyData['evaluated_raw_events'] ?? null)
            && ($journeyData['evaluated_raw_event_count'] ?? null) === count($expectedRoles), "{$scenario} fixture preserves exact P3-H roles, ordinals, event references and P3-F provenance");

    }

    $fourPunchEvents = $journeyFixtures['four_punches']['evaluated_raw_events'] ?? [];
    $assert(($fourPunchEvents[0]['event_uuid'] ?? '') === 'DEMO-CHECADOR-V3B-FOUR-EXIT-2'
        && ($fourPunchEvents[0]['pairing_role'] ?? '') === 'EXIT'
        && ($fourPunchEvents[0]['segment_ordinal'] ?? 0) === 2,
        'four-punch fixture deliberately puts a later slot first; role and ordinal determine compact position');
    $manyRoles = array_column($journeyFixtures['many_punches']['evaluated_raw_events'] ?? [], 'pairing_role');
    $assert(count($manyRoles) === 6 && $manyRoles[1] === 'SURPLUS' && $manyRoles[2] === 'UNMATCHED', 'five-plus fixture retains surplus and unmatched events in the complete collection');
    $assert(array_key_exists('entry_event_uuid', $journeyFixtures['missing_entry']['pairing']['segments'][0] ?? [])
        && $journeyFixtures['missing_entry']['pairing']['segments'][0]['entry_event_uuid'] === null
        && array_key_exists('exit_event_uuid', $journeyFixtures['missing_exit']['pairing']['segments'][0] ?? [])
        && $journeyFixtures['missing_exit']['pairing']['segments'][0]['exit_event_uuid'] === null,
        'missing positions remain null associations without synthetic timestamps');
    $assert(($journeyFixtures['ambiguous_pairing']['pairing']['pairing_status'] ?? '') === 'AMBIGUOUS_PAIRING'
        && ($journeyFixtures['ambiguous_pairing']['journey_status'] ?? '') === 'REVIEW_REQUIRED', 'ambiguity stays at pairing/Journey scope, not as a punch role');
    $assert(($journeyFixtures['overnight_journey']['evaluated_raw_events'][0]['ocurrido_at_utc'] ?? '') === '2026-10-20 04:00:00'
        && ($journeyFixtures['overnight_journey']['evaluated_raw_events'][1]['ocurrido_at_utc'] ?? '') === '2026-10-20 12:00:00'
        && ($scheduleFixtures['overnight_journey']['horario_local']['segmentos'][0]['dia_offset_fin'] ?? 0) === 1,
        'overnight fixture retains UTC instants and contractual cross-midnight schedule offset');
    $assert(($journeyFixtures['journey_not_evaluated']['evaluated'] ?? true) === false
        && array_key_exists('evaluated_raw_events', $journeyFixtures['journey_not_evaluated'])
        && $journeyFixtures['journey_not_evaluated']['evaluated_raw_events'] === null
        && array_key_exists('evaluated_raw_events', $journeyFixtures['journey_not_evaluated']['provenance'] ?? [])
        && $journeyFixtures['journey_not_evaluated']['provenance']['evaluated_raw_events'] === null,
        'no-evaluation fixture keeps evidence null and does not fabricate a classified event collection');

    foreach (['level2_verified' => 'VERIFIED', 'level2_incomplete' => 'INCOMPLETE', 'level2_unknown' => 'UNKNOWN'] as $scenario => $expected) {
        $service = $makeService($scenario);
        $timezone = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-19');
        $level2 = $service->getLevel2WindowStatus($user, 1, $timezone['data'] ?? []);
        $assert(($level2['ok'] ?? false) === true
            && ($level2['data']['window_level2_status'] ?? '') === $expected, "Nivel 2 {$expected} scenario remains distinct and contract-valid");
    }

    $service = $makeService('empty');
    $timezone = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-19');
    $snapshot = $service->createDailyPunchSnapshot($user, 1, $timezone['data'] ?? [], 'demo-empty-001');
    $page = $service->readDailyPunchSnapshotPage($user, (string) ($snapshot['data']['snapshot_token'] ?? ''));
    $assert(($snapshot['data']['total_records'] ?? -1) === 0
        && ($page['ok'] ?? false) === true
        && ($page['events'] ?? null) === [], 'empty query is a valid empty observed set rather than an attendance conclusion');

    $service = $makeService('timezone_unavailable');
    $timezoneFailure = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-19');
    $assert(($timezoneFailure['error']['kind'] ?? '') === 'timezone_unresolved', 'timezone unavailable scenario preserves the existing unresolved state');

    $service = $makeService('snapshot_expired');
    $timezone = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-19');
    $snapshot = $service->createDailyPunchSnapshot($user, 1, $timezone['data'] ?? [], 'demo-expired-001');
    $expiredPage = $service->readDailyPunchSnapshotPage($user, (string) ($snapshot['data']['snapshot_token'] ?? ''));
    $assert(($snapshot['ok'] ?? false) === true
        && ($expiredPage['error']['kind'] ?? '') === 'snapshot_expired', 'available Nivel 1 snapshot can independently reach the explicit expired state');

    $service = $makeService('service_unavailable');
    $unavailable = $service->resolveWorkplaceLocalDay($user, 1, '2026-10-19');
    $assert(($unavailable['error']['kind'] ?? '') === 'api_unavailable'
        && ($unavailable['error']['status'] ?? 0) === 503, 'service outage remains an unavailable error, not UNKNOWN or an empty query');

    $service = $makeService('ready');
    $unsupported = $service->executeAdministrativeEvaluation($user, 900001, 14, '2026-10-19', 'demo-no-admin-write');
    $assert(($unsupported['error']['kind'] ?? '') === 'api_unavailable', 'administrative writes and unsupported API operations are blocked inside demo mode');
    $unsupportedRawRead = $service->readObservedPunches($user, 1, ['from_utc' => '2026-10-19T06:00:00Z', 'to_utc' => '2026-10-20T06:00:00Z']);
    $assert(($unsupportedRawRead['error']['kind'] ?? '') === 'api_unavailable', 'the separate raw-read path is not substituted by advanced demo fixtures');

    $incidenceScenarios = [
        'incidence_none' => [0, null, null],
        'incidence_active' => [1, 'ACTIVE', 'VACATION'],
        'incidence_pending_review' => [1, 'PENDING_REVIEW', 'AUTHORIZED_LATENESS'],
        'incidence_rejected' => [1, 'REJECTED', 'PERMISSION'],
        'incidence_revoked' => [1, 'REVOKED', 'VACATION'],
        'incidence_history' => [1, 'ACTIVE', 'PERMISSION'],
        'incidence_conflict' => [2, 'ACTIVE', 'VACATION'],
        'incidence_evaluation_unavailable' => [1, 'ACTIVE', 'VACATION'],
    ];
    $incidenceReadData = [];
    foreach ($incidenceScenarios as $scenario => [$expectedCount, $expectedStatus, $expectedCategory]) {
        $service = $makeService($scenario);
        $listResult = $service->readApplicableIncidences($user, 900001, 14, '2026-10-19', '2026-10-19');
        $details = [];
        foreach (($listResult['data']['incidences'] ?? []) as $summary) {
            $detailResult = $service->readIncidence($user, (string) $summary['uuid_incidencia']);
            if (($detailResult['ok'] ?? false) === true) {
                $details[] = array_merge($detailResult['data'], [
                    'source_classification' => $summary['source_classification'],
                    'summary_reason' => $summary['reason'],
                    'summary_evidence_reference' => $summary['evidence_reference'],
                ]);
            }
        }
        $evaluation = $service->readAdministrativeEvaluation($user, 900001, 14, '2026-10-19');
        $incidenceReadData[$scenario] = ['list' => $listResult, 'details' => $details, 'evaluation' => $evaluation];
        $first = $listResult['data']['incidences'][0] ?? [];
        $assert(($listResult['ok'] ?? false) === true
            && ($listResult['data']['total_records'] ?? -1) === $expectedCount
            && count($details) === $expectedCount
            && ($expectedStatus === null || (($first['current_status'] ?? '') === $expectedStatus
                && ($first['category'] ?? '') === $expectedCategory
                && ($first['worker_id'] ?? 0) === 900001
                && ($first['workplace_id'] ?? 0) === 14
                && ($first['date_range']['start_date'] ?? '') === '2026-10-19')),
            "{$scenario} incidence list/detail matches the existing P3-G read contract and demo context");
    }
    $assert(($incidenceReadData['incidence_none']['evaluation']['data']['evaluation_exists'] ?? true) === false
        && ($incidenceReadData['incidence_history']['details'][0]['current_revision'] ?? 0) === 3
        && count($incidenceReadData['incidence_history']['details'][0]['revisions'] ?? []) === 3
        && ($incidenceReadData['incidence_history']['details'][0]['revisions'][2]['approved_by_user_id'] ?? null) === null,
        'no-incidence and multi-revision fixtures preserve neutral evaluation and append-only provenance without inventing approvers');
    $conflictEvaluation = $incidenceReadData['incidence_conflict']['evaluation']['data']['evaluation'] ?? [];
    $assert(($conflictEvaluation['administrative_status'] ?? '') === 'REVIEW_REQUIRED'
        && ($conflictEvaluation['human_review_status'] ?? '') === 'PENDING'
        && count($conflictEvaluation['linked_incidences'] ?? []) === 2
        && ($conflictEvaluation['linked_adjustments'] ?? null) === [], 'conflicting synthetic evidence remains pending review with explicit links and no fabricated adjustment');
    $evaluationUnavailable = $incidenceReadData['incidence_evaluation_unavailable']['evaluation'];
    $assert(($evaluationUnavailable['ok'] ?? true) === false
        && ($evaluationUnavailable['error']['kind'] ?? '') === 'api_unavailable', 'unavailable administrative evaluation is an error, not an empty or successful evaluation');
    $service = $makeService('incidence_service_unavailable');
    $incidenceReadError = $service->readApplicableIncidences($user, 900001, 14, '2026-10-19', '2026-10-19');
    $incidenceDetailError = $service->readIncidence($user, 'DEMO-INCIDENCE-ACTIVE-20261019');
    $evaluationReadError = $service->readAdministrativeEvaluation($user, 900001, 14, '2026-10-19');
    $assert(($incidenceReadError['error']['kind'] ?? '') === 'api_unavailable'
        && ($incidenceDetailError['error']['kind'] ?? '') === 'api_unavailable'
        && ($evaluationReadError['error']['kind'] ?? '') === 'api_unavailable', 'P3-G read service outage remains explicit and does not become an empty result');
    $lifecycleDemoUser = new Usuario();
    $lifecycleDemoUser->id = random_int(2000000000, 2099999999);
    $simulatedLifecycle = $makeService('incidence_active')->lifecycleAction(
        $lifecycleDemoUser,
        'DEMO-INCIDENCE-ACTIVE-20261019',
        'revoke',
        1,
        'demo-revoke-001',
        ['revocation_reason' => 'revoke fixture'],
        14
    );
    $assert(($simulatedLifecycle['ok'] ?? false) === true
        && ($simulatedLifecycle['data']['status'] ?? '') === 'CANCELLED', 'approved lifecycle POST is simulated locally and never sent to the API');

    $demoViewData = [
        'demoMode' => true,
        'demoScenario' => 'ready',
        'workplaces' => [['id' => 14, 'nombre' => 'Centro de demostración']],
        'selectedWorkplaceId' => 14,
        'selectedDate' => '2026-10-19',
        'selectedWorkerId' => null,
        'demoWorkerFilterNoMatches' => false,
        'state' => 'success',
        'stateMessage' => '',
        'timezone' => [
            'timezone' => 'America/Mexico_City',
            'from_utc' => '2026-10-19T06:00:00Z',
            'to_utc' => '2026-10-20T06:00:00Z',
        ],
        'snapshotMeta' => ['status' => 'READY', 'total_records' => count($readyDemoEvents), 'completeness_status' => 'LEVEL_1_COMPLETE_ONLY'],
        'level2Meta' => ['window_level2_status' => 'VERIFIED'],
        'events' => $readyDemoEvents,
        'pagination' => null,
        'paginationLinks' => [],
        'effectiveSchedules' => [900001 => $scheduleFixtures['ready']],
        'effectiveScheduleErrors' => [],
        'journeys' => [900001 => $journeyFixtures['ready']],
        'journeyErrors' => [],
        'administrativeIncidences' => [],
        'administrativeErrors' => [],
        'administrativeEvaluations' => [],
        'administrativeEvaluationErrors' => [],
        'lifecyclePermissions' => [],
        'lifecycleKeys' => [],
        'lifecycleFeedback' => null,
        'evaluationPermissions' => [],
        'evaluationKeys' => [],
        'evaluationFeedback' => null,
    ];
    extract($demoViewData, EXTR_OVERWRITE);
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
    $renderedDemoView = (string) ob_get_clean();
    $readyDemoChecks = [
        'workplace timezone' => str_contains($renderedDemoView, 'Zona horaria laboral'),
        'Level 1 label' => str_contains($renderedDemoView, 'Lectura estable disponible'),
        'Level 2 label' => str_contains($renderedDemoView, 'Cobertura de los datos de origen:'),
        'schedule label' => str_contains($renderedDemoView, 'Horario efectivo'),
        'no schedule demo suffix' => !str_contains($renderedDemoView, 'Horario efectivo · demostración'),
        'journey label' => str_contains($renderedDemoView, 'Jornada observada'),
        'no journey demo suffix' => !str_contains($renderedDemoView, 'Jornada observada · demostración'),
        'fixture provenance' => str_contains($renderedDemoView, 'Generado por el fixture de demostración'),
        'schedule start' => str_contains($renderedDemoView, '08:00'),
        'schedule end' => str_contains($renderedDemoView, '17:00'),
        'administrative authorization hidden' => !str_contains($renderedDemoView, 'Autorizar falta'),
        'evaluation action hidden' => !str_contains($renderedDemoView, 'Enviar evaluación'),
        'unresolved identity' => str_contains($renderedDemoView, 'Identidad sin resolver'),
        'unresolved punch time' => str_contains($renderedDemoView, 'Hora de marcación no resuelta'),
        'canonical identity filter notice' => str_contains($renderedDemoView, 'En esta demostración se filtra por identidad canónica resuelta'),
    ];
    $missingReadyDemoChecks = array_keys(array_filter($readyDemoChecks, static fn(bool $passed): bool => !$passed));
    $assert($missingReadyDemoChecks === [], 'ready demo view renders supported states and explicit unresolved/unsupported fields in Spanish'
        . ($missingReadyDemoChecks === [] ? '' : '; missing: ' . implode(', ', $missingReadyDemoChecks)));

    $p3gViewPhrases = [
        'incidence_none' => ['trabajador sin incidencias', 'El escenario sintético no incluye una evaluación administrativa.'],
        'incidence_active' => ['Vacaciones', 'Activa', 'Escenario sintético', 'Centro de trabajo 14', 'Fecha local 2026-10-19'],
        'incidence_pending_review' => ['Justificación de retardo', 'Pendiente de revisión'],
        'incidence_rejected' => ['Permiso', 'Rechazada'],
        'incidence_revoked' => ['Vacaciones', 'Revocada', 'Historial de revisiones'],
        'incidence_history' => ['Revisión 3 · Activa', 'Evidencia:'],
        'incidence_conflict' => ['Evaluación administrativa existente en el escenario sintético', 'Requiere revisión', 'Pendiente de revisión'],
    ];
    foreach ($p3gViewPhrases as $scenario => $requiredPhrases) {
        $demoScenario = $scenario;
        $administrativeIncidences = [900001 => $incidenceReadData[$scenario]['details'] ?? []];
        $administrativeErrors = [];
        $administrativeEvaluations = [900001 => $incidenceReadData[$scenario]['evaluation']['data'] ?? []];
        $administrativeEvaluationErrors = [];
        $lifecyclePermissions = ['resolve' => true, 'authorize' => true, 'reject' => true, 'revoke' => true];
        $evaluationPermissions = ['evaluate' => true, 'authorize_falta' => true];
        ob_start();
        include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
        $p3gView = (string) ob_get_clean();
        $phrasesPresent = true;
        foreach ($requiredPhrases as $phrase) {
            if (!str_contains($p3gView, $phrase)) {
                $phrasesPresent = false;
                break;
            }
        }
        $hasLifecycleForms = !in_array($scenario, ['incidence_none', 'incidence_revoked'], true);
        $actionsMatch = $hasLifecycleForms
            ? str_contains($p3gView, '/incidencias/DEMO-INCIDENCE-')
                && str_contains($p3gView, 'Demostración sintética: estas acciones sólo cambian el historial local de demostración')
            : !str_contains($p3gView, '/incidencias/DEMO-INCIDENCE-');
        $assert($phrasesPresent
            && str_contains($p3gView, 'Contexto administrativo · demostración')
            && $actionsMatch
            && str_contains($p3gView, 'evaluations/evaluate')
            && str_contains($p3gView, 'Demostración: el resultado es predefinido y temporal. No se consulta el servicio ni se calculan faltas, incidencias o nómina.')
            && str_contains($p3gView, 'Probar evaluación sintética')
            && str_contains($p3gView, 'Probar autorización sintética de FALTA')
            && !preg_match('/>\s*(?:ACTIVE|PENDING_REVIEW|REJECTED|REVOKED|REVIEW_REQUIRED)\s*</', $p3gView),
            "{$scenario} administrative data and authorized P3-G-D controls are localized and explicitly synthetic");
    }

    $demoScenario = 'incidence_active';
    $administrativeIncidences = [900001 => $incidenceReadData['incidence_active']['details'] ?? []];
    $lifecyclePermissions = ['resolve' => false, 'authorize' => false, 'reject' => false, 'revoke' => false];
    $evaluationPermissions = ['evaluate' => false, 'authorize_falta' => false];
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
    $unauthorizedDemoView = (string) ob_get_clean();
    $assert(!str_contains($unauthorizedDemoView, '/incidencias/DEMO-INCIDENCE-')
        && !str_contains($unauthorizedDemoView, 'evaluations/evaluate'), 'demo mode does not render P3-G-C or P3-G-D forms when every real ACL capability is denied');
    $demoScenario = 'incidence_service_unavailable';
    $administrativeIncidences = [];
    $administrativeErrors = [900001 => 'api_unavailable'];
    $administrativeEvaluations = [];
    $administrativeEvaluationErrors = [900001 => 'api_unavailable'];
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
    $p3gErrorView = (string) ob_get_clean();
    $assert(str_contains($p3gErrorView, 'La lectura de incidencias no está disponible para esta consulta en el escenario sintético.')
        && str_contains($p3gErrorView, 'La evaluación administrativa no está disponible para esta consulta en el escenario sintético.')
        && !str_contains($p3gErrorView, 'api_unavailable'), 'P3-G service read error is localized and never represented as an empty list');
    $demoScenario = 'ready';

    $expectedViewStates = [
        'day_schedule' => ['Horario definido', 'Resultado disponible en el escenario'],
        'night_schedule' => ['22:00:00', '06:00:00', 'Información insuficiente', '2026-10-20 04:00:00'],
        'day_off' => ['Descanso programado'],
        'unscheduled' => ['Sin horario asignado'],
        'journey_with_punches' => ['08:00', '17:00'],
        'journey_insufficient' => ['Información insuficiente', 'Incompleta según el escenario'],
        'journey_review' => ['Requiere revisión', 'Interpretación ambigua', 'Marcación adicional'],
        'two_punches' => ['Resultado disponible en el escenario'],
        'four_punches' => ['Entrada 1', 'Salida 1', 'Entrada 2', 'Salida 2'],
        'many_punches' => ['Ver todas las marcaciones (6)', 'Marcación adicional', 'Sin emparejar'],
        'missing_entry' => ['Segmento incompleto: falta entrada', 'Sin evento asociado en esta proyección'],
        'missing_exit' => ['Segmento incompleto: falta salida', 'Sin evento asociado en esta proyección'],
        'ambiguous_pairing' => ['Requiere revisión', 'Marcación ambigua'],
        'overnight_journey' => ['Fecha laboral: 2026-10-19', '22:00', '06:00', 'Fecha local: 2026-10-20'],
        'three_segments' => ['+1 segmentos adicionales', 'Ver todas las marcaciones (6)'],
        'journey_not_evaluated' => ['Evaluación no disponible', 'no cuenta con una evaluación de Jornada disponible'],
    ];
    foreach ($expectedViewStates as $scenario => $requiredPhrases) {
        $demoScenario = $scenario;
        $selectedWorkerId = null;
        $demoWorkerFilterNoMatches = false;
        $events = $eventFixtures[$scenario];
        $snapshotMeta = ['status' => 'READY', 'total_records' => count($events), 'completeness_status' => 'LEVEL_1_COMPLETE_ONLY'];
        $level2Meta = ['window_level2_status' => $journeyFixtures[$scenario]['source_evidence']['window_level2_status'] ?? 'UNKNOWN'];
        $effectiveSchedules = [900001 => $scheduleFixtures[$scenario]];
        $effectiveScheduleErrors = [];
        $journeys = [900001 => $journeyFixtures[$scenario]];
        $journeyErrors = [];
        ob_start();
        include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
        $scenarioView = (string) ob_get_clean();
        $allPhrasesPresent = true;
        foreach ($requiredPhrases as $phrase) {
            if (!str_contains($scenarioView, $phrase)) {
                $allPhrasesPresent = false;
                break;
            }
        }
        $assert($allPhrasesPresent
            && str_contains($scenarioView, 'demostración')
            && !preg_match('/\b(?:UNKNOWN|INCOMPLETE|REVIEW_REQUIRED|UNSCHEDULED_DAY)\b/', $scenarioView), "{$scenario} view renders the expected Spanish fixture state without raw codes");
    }

    $invalidTimezoneJourney = $journeyFixtures['overnight_journey'];
    $invalidTimezoneJourney['context']['timezone'] = 'NOT-A-WORKPLACE-ZONE';
    $demoScenario = 'overnight_journey';
    $effectiveSchedules = [900001 => $scheduleFixtures['overnight_journey']];
    $journeys = [900001 => $invalidTimezoneJourney];
    $events = $eventFixtures['overnight_journey'];
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
    $invalidTimezoneView = (string) ob_get_clean();
    $assert(str_contains($invalidTimezoneView, 'Hora local no disponible')
        && str_contains($invalidTimezoneView, 'Timestamp original (UTC):')
        && str_contains($invalidTimezoneView, '2026-10-20 12:00:00')
        && !preg_match('/<span class="font-monospace">06:00<\/span>/', $invalidTimezoneView),
        'invalid timezone preserves the original UTC timestamp without presenting it as local time');

    $invalidTimestampJourney = $journeyFixtures['overnight_journey'];
    $invalidTimestampJourney['evaluated_raw_events'][1]['ocurrido_at_utc'] = 'tomorrow';
    $invalidTimestampJourney['provenance']['evaluated_raw_events'] = $invalidTimestampJourney['evaluated_raw_events'];
    $journeys = [900001 => $invalidTimestampJourney];
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
    $invalidTimestampView = (string) ob_get_clean();
    $assert(str_contains($invalidTimestampView, 'Hora local no disponible')
        && str_contains($invalidTimestampView, 'Timestamp original (UTC):')
        && str_contains($invalidTimestampView, 'tomorrow'),
        'malformed UTC event timestamp is preserved as source data and not parsed into an invented local time');

    foreach ($expectedP3hRoles as $scenario => $expectedRoles) {
        $demoScenario = $scenario;
        $events = $eventFixtures[$scenario];
        $snapshotMeta = ['status' => 'READY', 'total_records' => count($events), 'completeness_status' => 'LEVEL_1_COMPLETE_ONLY'];
        $level2Meta = ['window_level2_status' => $journeyFixtures[$scenario]['source_evidence']['window_level2_status'] ?? 'UNKNOWN'];
        $effectiveSchedules = [900001 => $scheduleFixtures[$scenario]];
        $effectiveScheduleErrors = [];
        $journeys = [900001 => $journeyFixtures[$scenario]];
        $journeyErrors = [];
        ob_start();
        include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
        $scenarioView = (string) ob_get_clean();
        $allEventReferencesVisible = true;
        foreach ($expectedRoles as $eventUuid => $_classification) {
            if (!str_contains($scenarioView, $eventUuid)) {
                $allEventReferencesVisible = false;
                break;
            }
        }
        $assert($allEventReferencesVisible && str_contains($scenarioView, 'Detalle de auditoría'), "{$scenario} retains all synthetic event references in existing P3-F detail");
        if ($scenario === 'four_punches') {
            preg_match_all(
                '/<strong>(Entrada 1|Salida 1|Entrada 2|Salida 2)<\/strong>.*?<span class="font-monospace">([^<]+)<\/span>/s',
                $scenarioView,
                $slotMatches,
                PREG_SET_ORDER
            );
            $slotTimes = [];
            foreach ($slotMatches as $slotMatch) {
                $slotTimes[$slotMatch[1]] = $slotMatch[2];
            }
            ksort($slotTimes);
            $assert($slotTimes === [
                'Entrada 1' => '08:00',
                'Entrada 2' => '13:00',
                'Salida 1' => '12:00',
                'Salida 2' => '14:00',
            ], 'four-punch compact positions follow role and ordinal rather than event array order');
        }
        if ($scenario === 'overnight_journey') {
            $assert(str_contains($scenarioView, '>22:00</span>')
                && str_contains($scenarioView, '>06:00</span>')
                && str_contains($scenarioView, 'Fecha local: 2026-10-20'), 'overnight compact projection uses workplace local time and discloses next calendar date');
        }
        if ($scenario === 'journey_not_evaluated') {
            $assert(str_contains($scenarioView, 'Sin evento asociado en esta proyección')
                && !preg_match('/\b(?:FALTA|AUSENTE|SIN MARCACIÓN)\b/', $scenarioView), 'unevaluated empty projection is neutral and does not infer absence');
        }
    }

    $selectedWorkerId = 558;
    $events = array_values(array_filter(
        $readyDemoEvents,
        static fn(array $event): bool => (int) ($event['id_personal_resuelto'] ?? 0) === $selectedWorkerId
    ));
    $demoWorkerFilterNoMatches = $readyDemoEvents !== [] && $events === [];
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
    $filteredDemoView = (string) ob_get_clean();
    $assert(str_contains($filteredDemoView, 'No hay marcaciones con identidad resuelta que coincidan con el trabajador seleccionado')
        && str_contains($filteredDemoView, '0 coincidencias en esta página para el trabajador seleccionado'), 'worker filter does not attribute unresolved demo events and explains the empty match');
    $syntheticResolvedEvents = [$readyDemoEvents[0]];
    $syntheticResolvedEvents[0]['id_personal_resuelto'] = 900001;
    $selectedWorkerId = 900001;
    $events = array_values(array_filter(
        $syntheticResolvedEvents,
        static fn(array $event): bool => (int) ($event['id_personal_resuelto'] ?? 0) === $selectedWorkerId
    ));
    $demoWorkerFilterNoMatches = false;
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
    $matchingDemoView = (string) ob_get_clean();
    $assert(count($events) === 1
        && str_contains($matchingDemoView, '1 coincidencia en esta página para el trabajador seleccionado')
        && str_contains($matchingDemoView, 'Personal 900001'), 'demo worker filter retains a canonically matched event without adding an API lookup');
    foreach (['INCOMPLETE' => 'Incompleta según el escenario', 'UNKNOWN' => 'No confirmada según el escenario'] as $status => $label) {
        $selectedWorkerId = null;
        $events = $readyDemoEvents;
        $demoWorkerFilterNoMatches = false;
        $level2Meta = ['window_level2_status' => $status];
        ob_start();
        include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
        $statusDemoView = (string) ob_get_clean();
        $assert(str_contains($statusDemoView, 'Cobertura de los datos de origen: ' . $label)
            && !str_contains($statusDemoView, $status), "Nivel 2 {$status} is presented in Spanish without the technical code");
    }
    $journeys = [];
    $journeyErrors = [900001 => 'api_unavailable'];
    ob_start();
    include __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php';
    $journeyErrorView = (string) ob_get_clean();
    $assert(str_contains($journeyErrorView, 'el escenario de Jornada no está disponible o no coincide con el contrato.')
        && !str_contains($journeyErrorView, 'api_unavailable'), 'demo Journey failures are presented without raw technical error codes');
    $assert(str_contains($renderedDemoView, 'Completa en la demostración; origen real no verificado')
        && str_contains($renderedDemoView, 'La demostración no confirma la integridad de datos reales.'), 'synthetic Level 2 never claims real or cryptographic verification');
    $assert(!preg_match('/\b(?:UNKNOWN|RECEIVED|INCOMPLETE|PENDING_MATCH)\b/', $renderedDemoView), 'ready demo view does not expose raw technical status codes');

    $controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
    $viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php') ?: '';
    $aclPosition = strpos($controllerSource, 'ChecadorAccessService::authorize($requestedWorkplace)');
    $demoPosition = strpos($controllerSource, 'ChecadorDailyReadService::demoModeEnabled()');
    $assert($aclPosition !== false && $demoPosition !== false && $aclPosition < $demoPosition, 'existing Portal3 station authorization executes before demo activation');
    $readSuccessPosition = strpos($controllerSource, '$events = $readResult[\'events\'];');
    $assert($readSuccessPosition !== false
        && str_contains(substr($controllerSource, $readSuccessPosition, 700), 'if ($demoMode && $requestedWorker !== null)')
        && str_contains(substr($controllerSource, $readSuccessPosition, 700), "\$event['id_personal_resuelto']"), 'demo worker filter uses only resolved canonical event identity');
    $schedulePosition = $readSuccessPosition === false ? false : strpos($controllerSource, 'getEffectiveSchedule(', $readSuccessPosition);
    $journeyPosition = $readSuccessPosition === false ? false : strpos($controllerSource, 'evaluateJourney(', $readSuccessPosition);
    $assert($schedulePosition !== false && $journeyPosition !== false
        && str_contains($controllerSource, 'readApplicableIncidences(')
        && str_contains($controllerSource, 'readIncidence(')
        && str_contains($controllerSource, 'readAdministrativeEvaluation(')
        && str_contains($controllerSource, 'readApplicableIncidences(')
        && str_contains($controllerSource, 'if ($demoMode && $requestedWorker !== null)'), 'P3-G reads reuse the existing service after canonical identity and station authorization in demo and live mode');
    $assert(str_contains($viewSource, 'Demo · ')
        && !str_contains($viewSource, 'Modo de demostración activo')
        && !str_contains($viewSource, 'Bloques fuera de la demostración')
        && str_contains($viewSource, 'No verificada por Portal3')
        && str_contains($viewSource, "!empty(\$lifecyclePermissions['resolve'])")
        && str_contains($viewSource, "!empty(\$lifecyclePermissions['authorize'])")
        && str_contains($viewSource, "!empty(\$lifecyclePermissions['reject'])")
        && str_contains($viewSource, "!empty(\$lifecyclePermissions['revoke'])")
        && str_contains($viewSource, 'Contexto administrativo')
        && str_contains($viewSource, 'Identidad sin resolver')
        && str_contains($viewSource, 'no filtra las páginas de eventos'), 'advanced view marks synthetic data, renders unsupported areas and unresolved fields clearly in Spanish');
    $assert($networkCalls === 0 && $tokenCalls === 0, 'demo transport makes no outbound API call and does not generate a downstream JWT');
} finally {
    foreach ($keys as $key) {
        if ($original[$key] === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $original[$key];
        }
    }
}

if (isset($lifecycleDemoUser) && $lifecycleDemoUser instanceof Usuario) {
    $runtimeUid = function_exists('posix_geteuid') ? (string) posix_geteuid() : 'current-runtime';
    $demoStateDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'portal3-checador-demo-v4b-' . $runtimeUid;
    $stateKey = hash('sha256', (string) ((int) $lifecycleDemoUser->id) . "\0incidence_active");
    foreach ([$stateKey . '.json', $stateKey . '.lock'] as $filename) {
        $path = $demoStateDirectory . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path) && !is_link($path)) @unlink($path);
    }
}

printf("CHECADOR_ADVANCED_DEMO_SOURCE: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

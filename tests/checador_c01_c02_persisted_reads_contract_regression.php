<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorPersistedIncidenceReadService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passes++ : $fails++;
};

$previous = [$_ENV['APP_ENV'] ?? null, getenv('APP_ENV')];
$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');
$base = 'https://c01-c02.invalid/';
$user = new Usuario();
$user->id = 33;
$makeReader = static function (array $responses, array &$history) use ($base): ChecadorDailyReadService {
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static fn (Usuario $actor): string => 'test-user-token',
        $base
    );
};
$response = static fn (array $data): Response => new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'status' => 'success', 'data' => $data,
], JSON_THROW_ON_ERROR));

$journey = [
    'evaluation_ref' => str_repeat('a', 64), 'worker_id' => 1004, 'workplace_id' => 1,
    'work_date' => '2026-10-28', 'status' => 'CALCULATED', 'segments' => [], 'raw_punches' => [], 'incidences' => [],
];
$journeyData = [
    'query' => ['workplace_id' => 1, 'from' => '2026-10-28', 'to' => '2026-10-28', 'worker_id' => 1004,
        'status' => null, 'page' => 1, 'per_page' => 50],
    'pagination' => ['page' => 1, 'per_page' => 50, 'total_records' => 1, 'total_pages' => 1,
        'has_more' => false, 'complete' => false, 'consistency' => 'PER_PAGE_READ'],
    'projection_status' => 'PARTIAL',
    'evaluation_coverage' => [
        'scheduled_journeys' => 1, 'evaluated_journeys' => 1, 'not_evaluated_journeys' => 0,
        'status' => 'EVALUATED', 'evidence' => 'CANONICAL_SCHEDULE_AND_LATEST_PERSISTED_CALCULATION_COUNTS',
    ],
    'journeys' => [$journey],
];
$history = [];
$reader = $makeReader([$response($journeyData)], $history);
$read = $reader->readPersistedJourneyPage($user, [
    'workplace_id' => 1, 'from' => '2026-10-28', 'to' => '2026-10-28', 'worker_id' => 1004, 'page' => 1, 'per_page' => 50,
]);
$request = $history[0]['request'] ?? null;
$assert(($read['ok'] ?? false) && $read['data']['projection_status'] === 'PARTIAL'
    && $request?->getMethod() === 'GET'
    && str_contains((string)$request?->getUri(), '/api/v2/journeys/persisted')
    && $request?->getHeaderLine('Authorization') === 'Bearer test-user-token',
    'C01 accepts an explicit partial persisted projection through authenticated GET only');

$badJourneyData = $journeyData;
$badJourneyData['query']['worker_id'] = 512;
$history = [];
$reader = $makeReader([$response($badJourneyData)], $history);
$badRead = $reader->readPersistedJourneyPage($user, [
    'workplace_id' => 1, 'from' => '2026-10-28', 'to' => '2026-10-28', 'worker_id' => 1004, 'page' => 1, 'per_page' => 50,
]);
$assert(!($badRead['ok'] ?? true) && ($badRead['error']['kind'] ?? '') === 'api_unavailable',
    'C01 rejects mismatched echoed identity instead of presenting another worker scope');

$incidence = [
    'uuid' => 'inc-1', 'worker_id' => 1004, 'workplace_id' => 1, 'category' => 'PERMISSION', 'current_status' => 'AUTHORIZED',
    'date_range' => ['start_date' => '2026-10-28', 'end_date' => '2026-10-28'],
    'detail_source' => 'P3G_INCIDENCE_DETAIL_ENDPOINT', 'punch_source' => 'M05_PUNCH_EVENTS_ENDPOINT',
    'applications' => [], 'evaluations' => [],
];
$incidenceData = [
    'query' => ['workplace_id' => 1, 'from' => '2026-10-28', 'to' => '2026-10-28', 'worker_id' => 1004,
        'category' => null, 'status' => null, 'page' => 1, 'per_page' => 50],
    'pagination' => ['page' => 1, 'per_page' => 50, 'total_records' => 1, 'total_pages' => 1,
        'has_more' => false, 'complete' => true, 'consistency' => 'PER_PAGE_READ'],
    'projection_status' => 'COMPLETE', 'incidences' => [$incidence],
];
$history = [];
$reader = $makeReader([$response($incidenceData)], $history);
$read = $reader->readIncidenceCollectionPage($user, [
    'workplace_id' => 1, 'from' => '2026-10-28', 'to' => '2026-10-28', 'worker_id' => 1004, 'page' => 1, 'per_page' => 50,
]);
$request = $history[0]['request'] ?? null;
$assert(($read['ok'] ?? false) && $request?->getMethod() === 'GET'
    && str_contains((string)$request?->getUri(), '/api/v2/workplaces/1/incidences')
    && $request?->getHeaderLine('Authorization') === 'Bearer test-user-token',
    'C02 uses the collision-free scoped collection route with delegated user authentication');

$badIncidenceData = $incidenceData;
$badIncidenceData['pagination']['complete'] = false;
$history = [];
$reader = $makeReader([$response($badIncidenceData)], $history);
$badRead = $reader->readIncidenceCollectionPage($user, [
    'workplace_id' => 1, 'from' => '2026-10-28', 'to' => '2026-10-28', 'worker_id' => 1004, 'page' => 1, 'per_page' => 50,
]);
$assert(!($badRead['ok'] ?? true) && ($badRead['error']['kind'] ?? '') === 'api_unavailable',
    'C02 rejects inconsistent completion metadata rather than silently claiming full coverage');

$collectionItem = [
    'uuid' => 'inc-1', 'worker_id' => 1004, 'worker_name' => 'Worker test', 'employee_number' => null,
    'workplace_id' => 1, 'workplace_name' => 'Workplace test', 'category' => 'PERMISSION', 'scope' => 'WORKER',
    'date_range' => ['start_date' => '2026-10-28', 'end_date' => '2026-10-28'],
    'current_status' => 'AUTHORIZED', 'current_revision' => 1, 'created_at_utc' => '2026-10-28T12:00:00Z',
    'updated_at_utc' => '2026-10-28T12:00:00Z', 'source_classification' => 'READ_ONLY_ADAPTER',
    'detail_source' => 'P3G_INCIDENCE_DETAIL_ENDPOINT', 'punch_source' => 'M05_PUNCH_EVENTS_ENDPOINT',
    'applications' => [], 'evaluations' => [], 'shift_name' => 'Sin turno vinculado', 'shift_hours' => '--',
    'timezone' => 'America/Mexico_City', 'projection_state' => 'COMPLETE',
];
$collectionPage = $incidenceData;
$collectionPage['query']['per_page'] = 200; // The production adapter requests 200 rows per page.
$collectionPage['pagination']['per_page'] = 200;
$collectionPage['incidences'] = [$collectionItem];
$detail = [
    'uuid_incidencia' => 'inc-1', 'worker_id' => 1004, 'workplace_id' => 1, 'category' => 'PERMISSION',
    'scope' => 'WORKER', 'date_range' => ['start_date' => '2026-10-28', 'end_date' => '2026-10-28'],
    'time_interval' => null, 'current_status' => 'AUTHORIZED', 'current_revision' => 1,
    'check_reference' => null,
    'revisions' => [[
        'revision_number' => 1, 'status' => 'AUTHORIZED', 'reason' => 'Approved reason', 'evidence_reference' => null,
        'created_by_user_id' => 10, 'approved_by_user_id' => 11, 'created_at_utc' => '2026-10-28T12:00:00Z',
    ]],
];
$detailResponse = $response($detail);
$timezoneResponse = $response([
    'workplace_id' => 1, 'local_date' => '2026-10-28', 'timezone' => 'America/Mexico_City',
    'from_utc' => '2026-10-28T06:00:00Z', 'to_utc' => '2026-10-29T06:00:00Z',
]);
$punchResponse = new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'status' => 'success', 'data' => [[
        'event_uuid' => 'event-context-1', 'id_estacion_resuelta' => 77, 'id_personal_resuelto' => 1004,
        'processing_status' => 'RECEIVED', 'integrity' => ['status' => 'VALID'],
        'ocurrido_at_utc' => '2026-10-28T14:00:00Z', 'recibido_at_utc' => '2026-10-28T14:00:01Z',
        'metodo_captura' => 'BIOMETRIC', 'id_dispositivo' => 4, 'source_timestamp_raw' => '2026-10-28 08:00:00',
    ]],
    'pagination' => ['current_page' => 1, 'per_page' => 200, 'total_records' => 1, 'total_pages' => 1],
], JSON_THROW_ON_ERROR));
$history = [];
$dailyReader = $makeReader([$response($collectionPage), $detailResponse, $timezoneResponse, $punchResponse], $history);
$incidenceReader = new ChecadorPersistedIncidenceReadService($dailyReader);
$integrated = $incidenceReader->readPersistedIncidences(1, '2026-10-28', '2026-10-28', 1004, null, null, $user);
$urls = array_map(static fn(array $transaction): string => (string)$transaction['request']->getUri(), $history);
$assert(($integrated['ok'] ?? false)
    && ($integrated['incidences'][0]['revisions'][0]['motivo'] ?? null) === 'Approved reason'
    && ($integrated['incidences'][0]['punches'][0]['event_uuid'] ?? null) === 'event-context-1'
    && ($integrated['incidences'][0]['punches'][0]['relation'] ?? null) === 'CONTEXTUAL_DATE_OVERLAP'
    && str_contains($urls[1] ?? '', '/api/v2/incidences/inc-1')
    && str_contains($urls[3] ?? '', '/api/v2/punch-events'),
    'C02 hydrates revision history from P3G detail and contextual events through M05 GET');

$history = [];
$deniedDailyReader = $makeReader([
    $response($collectionPage), $detailResponse, $timezoneResponse,
    new Response(403, ['Content-Type' => 'application/json'], json_encode(['status' => 'error', 'error' => ['code' => 'FUNCTION_PERMISSION_DENIED']], JSON_THROW_ON_ERROR)),
], $history);
$deniedResult = (new ChecadorPersistedIncidenceReadService($deniedDailyReader))
    ->readPersistedIncidences(1, '2026-10-28', '2026-10-28', 1004, null, null, $user);
$assert(($deniedResult['ok'] ?? false)
    && ($deniedResult['projection_status'] ?? '') === 'PARTIAL'
    && ($deniedResult['incidences'][0]['punch_projection_state'] ?? '') === 'UNAVAILABLE'
    && ($deniedResult['incidences'][0]['punches'] ?? []) === [],
    'C02 labels denied contextual punch reads unavailable instead of presenting them as empty evidence');

if ($previous[0] === null) unset($_ENV['APP_ENV']); else $_ENV['APP_ENV'] = $previous[0];
if ($previous[1] === false) putenv('APP_ENV'); else putenv('APP_ENV=' . $previous[1]);
echo "SUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

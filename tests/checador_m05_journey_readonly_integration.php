<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
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

$previousEnvironment = [];
foreach (['APP_ENV', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'CHECADOR_ADVANCED_DEMO_SCENARIO'] as $key) {
    $previousEnvironment[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
    unset($_ENV[$key]);
    putenv($key);
}
$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';

$makeReader = static function (array $responses, array &$history): ChecadorDailyReadService {
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));
    $base = 'https://m05-read-only.invalid/';
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static fn(Usuario $user): string => 'mock-m05-jwt',
        $base
    );
};

$timezoneResponse = static fn(): Response => new Response(200, ['Content-Type' => 'application/json'], json_encode([
    'status' => 'success',
    'data' => [
        'workplace_id' => 1,
        'local_date' => '2026-10-28',
        'timezone' => 'America/Mexico_City',
        'from_utc' => '2026-10-28T06:00:00Z',
        'to_utc' => '2026-10-29T06:00:00Z',
    ],
], JSON_THROW_ON_ERROR));
$event = static fn(string $uuid, int $worker = 1004): array => [
    'event_uuid' => $uuid,
    'id_estacion_resuelta' => 77,
    'id_personal_resuelto' => $worker,
    'processing_status' => 'RECEIVED',
    'integrity' => ['status' => 'VALID'],
    'ocurrido_at_utc' => '2026-10-28T14:00:00Z',
    'recibido_at_utc' => '2026-10-28T14:00:01Z',
    'metodo_captura' => 'BIOMETRIC',
    'id_dispositivo' => 4,
];
$page = static function (array $events, int $current, int $total, int $totalPages): Response {
    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'status' => 'success',
        'data' => $events,
        'pagination' => [
            'current_page' => $current,
            'per_page' => 200,
            'total_records' => $total,
            'total_pages' => $totalPages,
        ],
    ], JSON_THROW_ON_ERROR));
};

$user = new Usuario();
$user->id = 33;
$history = [];
$firstPageEvents = [];
for ($index = 1; $index <= 200; $index++) $firstPageEvents[] = $event('m05-page-one-' . $index);
$reader = $makeReader([
    $timezoneResponse(),
    $page($firstPageEvents, 1, 201, 2),
    $page([$event('m05-page-two')], 2, 201, 2),
], $history);
$read = $reader->readJourneyObservationSet($user, 1, 77, '2026-10-28');
$requests = array_map(static fn(array $transaction): RequestInterface => $transaction['request'], $history);
$methods = array_map(static fn(RequestInterface $request): string => $request->getMethod(), $requests);
$urls = array_map(static fn(RequestInterface $request): string => (string) $request->getUri(), $requests);
$assert(($read['ok'] ?? false) === true
    && count($read['events'] ?? []) === 201
    && ($read['pagination']['pages_read'] ?? 0) === 2
    && ($read['source_coverage'] ?? '') === 'UNKNOWN'
    && ($read['consistency'] ?? '') === 'LIVE_NON_SNAPSHOT',
    'M05 reads every API page while keeping source completeness unknown and the read non-snapshot');
$assert($methods === ['GET', 'GET', 'GET']
    && str_contains($urls[1] ?? '', 'station_id=1')
    && str_contains($urls[1] ?? '', 'from=2026-10-28T06%3A00%3A00Z')
    && ($requests[0]->getHeaderLine('Authorization') === 'Bearer mock-m05-jwt'),
    'M05 uses only authenticated API GETs with canonical locality and API-owned UTC bounds');
$assert(($read['events'][0]['id_estacion_resuelta'] ?? 0) === 77,
    'physical station identity remains distinct from canonical locality id');

$duplicateHistory = [];
$duplicateReader = $makeReader([
    $timezoneResponse(),
    $page([$event('duplicate-event'), $event('duplicate-event')], 1, 2, 1),
], $duplicateHistory);
$duplicate = $duplicateReader->readJourneyObservationSet($user, 1, 77, '2026-10-28');
$assert(($duplicate['ok'] ?? true) === false
    && ($duplicate['error']['kind'] ?? '') === 'journey_read_inconsistent',
    'duplicate event UUIDs fail the whole observation set closed');

$scopeHistory = [];
$scopeReader = $makeReader([
    $timezoneResponse(),
    $page([$event('wrong-physical-station')], 1, 1, 1),
], $scopeHistory);
$scope = $scopeReader->readJourneyObservationSet($user, 1, 78, '2026-10-28');
$assert(($scope['ok'] ?? true) === false
    && ($scope['error']['kind'] ?? '') === 'station_scope_mismatch',
    'a physical-station mismatch is rejected instead of comparing it numerically to locality');

$viewPath = __DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-journeys.php';
$render = static function (array $vars) use ($viewPath): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require $viewPath;
    return (string) ob_get_clean();
};
$html = $render([
    'selectedWorkplaceName' => 'Interlomas',
    'selectedWorkplaceId' => 1,
    'selectedDate' => '2026-10-28',
    'selectedWorkerId' => 1004,
    'state' => 'success',
    'stateMessage' => '',
    'journeys' => [],
    'journeyErrors' => [],
    'journeyEvaluationState' => 'read_unavailable',
    'journeyObservations' => [[
        'worker_id' => 1004,
        'worker_name' => 'Alicia Montalvo',
        'identity_status' => 'RESOLVED_BY_API',
        'date' => '2026-10-28',
        'timezone' => 'America/Mexico_City',
        'events' => [$event('canonical-event-uuid')],
        'schedule' => [
            'estado_jornada' => 'SCHEDULED',
            'horario_local' => [
                'timezone' => 'America/Mexico_City',
                'segmentos' => [['tipo' => 'WORK', 'hora_inicio_local' => '08:00', 'hora_fin_local' => '17:00']],
            ],
        ],
        'schedule_error' => '',
    ]],
    'journeyObservationCoverage' => [
        'api_records_read' => 1,
        'pages_read' => 1,
        'total_pages' => 1,
        'worker_resolved_events' => 1,
        'unresolved_worker_events' => 0,
    ],
    'workerSelectOptions' => [['worker_id' => 1004, 'full_name' => 'Alicia Montalvo']],
]);
$assert(str_contains($html, 'method="get"')
    && str_contains($html, 'Alicia Montalvo')
    && str_contains($html, 'Horario efectivo informado')
    && str_contains($html, '08:00–17:00')
    && str_contains($html, 'canonical-event-uuid')
    && str_contains($html, 'No consultable: API V2 no expone una lectura GET')
    && str_contains($html, 'cobertura de fuente: desconocida'),
    'M05 renders filters, raw trace, effective schedule, and an explicit unavailable persisted-evaluation state');
$assert(!str_contains($html, 'Resultado disponible')
    && !str_contains($html, 'FALTA')
    && !str_contains($html, 'RETARDO')
    && !str_contains($html, 'horas trabajadas'),
    'M05 observation rendering does not fabricate official Journey or payroll classifications');

foreach ($previousEnvironment as $key => [$wasSet, $value, $processValue]) {
    if ($wasSet) $_ENV[$key] = $value; else unset($_ENV[$key]);
    if ($processValue === false) putenv($key); else putenv($key . '=' . $processValue);
}

echo "SUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

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
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$routeSource = file_get_contents(__DIR__ . '/../routes/web.php');

$journeyPayload = static function (string $status = 'SCHEDULED', string $level2 = 'VERIFIED', bool $evaluated = true): array {
    $events = $evaluated ? [
        ['event_uuid' => 'ev-1', 'id_dispositivo' => 1, 'ocurrido_at_utc' => '2026-10-12 14:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
        ['event_uuid' => 'ev-2', 'id_dispositivo' => null, 'ocurrido_at_utc' => '2026-10-12 22:00:00', 'metodo_captura' => 'BIOMETRIC', 'pairing_role' => 'EXIT', 'segment_ordinal' => 1],
    ] : null;
    $data = [
        'evaluated' => $evaluated,
        'journey_status' => $status === 'UNSCHEDULED' ? 'UNSCHEDULED_DAY' : ($status === 'UNKNOWN' ? 'UNKNOWN_SCHEDULE' : 'EVALUATED'),
        'context' => ['id_personal' => 1, 'id_estacion' => 1, 'fecha_local' => '2026-10-12', 'timezone' => 'America/Mexico_City'],
        'schedule' => ['estado_jornada' => $status, 'scheduled_shift_id' => $status === 'SCHEDULED' ? 105 : null, 'horario_local' => $status === 'SCHEDULED' ? ['segmentos' => [
            ['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '22:00:00', 'hora_fin_local' => '06:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 1],
            ['ordinal' => 2, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '13:00:00', 'hora_fin_local' => '17:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
        ]] : null],
        'source_evidence' => ['snapshot_audit_ref' => 'audit-1', 'window_level2_status' => $level2, 'level2_evaluation_ref' => 'eval-1'],
        'pairing' => $evaluated ? ['pairing_id' => 42, 'revision_numero' => 1, 'creado_at_utc' => '2026-10-12 14:05:00', 'pairing_status' => 'EXACT_PAIR', 'algorithm_version' => 'v2.4-p1', 'segments' => [['ordinal' => 1, 'entry_event_uuid' => 'ev-1', 'exit_event_uuid' => 'ev-2']]] : null,
        'calculation' => $evaluated ? ['calculation_id' => 38, 'revision_numero' => 1, 'creado_at_utc' => '2026-10-12 14:05:02', 'version_algoritmo' => 'v2.4-p2', 'estado_calculo' => 'CALCULATED', 'segundos_trabajados_totales' => 28800, 'segmentos' => []] : null,
        'evaluated_raw_event_count' => $evaluated ? count($events) : null,
        'evaluated_raw_events' => $events,
        'provenance' => [
            'response_generated_at_utc' => '2026-10-13T17:50:00Z',
            'p1_created_at_utc' => $evaluated ? '2026-10-12 14:05:00' : null,
            'p2_created_at_utc' => $evaluated ? '2026-10-12 14:05:02' : null,
            'p1_algorithm_version' => 'v2.4-p1',
            'p2_algorithm_version' => 'v2.4-p2',
            'shift_template_version' => $evaluated ? 1 : null,
            'source_completeness_contract_version' => 'v2.4',
            'level2_policy_ref' => $evaluated ? 'POLICY_DEFAULT' : null,
            'policy_rule_version' => null,
            'evaluated_raw_event_count' => $evaluated ? count($events) : null,
            'evaluated_raw_events' => $events,
        ],
    ];
    return ['status' => 'success', 'data' => $data];
};

$make = static function (array $responses, ?array &$history = null) use ($base): ChecadorDailyReadService {
    $stack = HandlerStack::create(new MockHandler($responses));
    $history ??= [];
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static fn (Usuario $user): string => 'portal3-journey-jwt',
        $base
    );
};

$history = [];
$service = $make([new Response(200, ['Content-Type' => 'application/json'], json_encode($journeyPayload(), JSON_THROW_ON_ERROR))], $history);
$result = $service->evaluateJourney($user, 1, 1, '2026-10-12', 'audit-1');
$request = $history[0]['request'] ?? null;
$body = $request instanceof RequestInterface ? json_decode((string) $request->getBody(), true) : [];

$assert($result['ok'] === true, 'P4D01 authorized user can request Journey');
$assert(str_contains($controllerSource, 'ChecadorAccessService::authorize($requestedWorkplace)'), 'P4D02 local Checador access remains before API call');
$assert(str_contains($controllerSource, 'requestedWorkplace') && str_contains($serviceSource, 'workplaceId'), 'P4D03 workplace scope remains explicit');
$assert(($body['id_personal'] ?? 0) === 1, 'P4D04 canonical worker id is sent');
$assert(!str_contains($controllerSource, 'tb_usuarios') && !str_contains($serviceSource, 'no_colaborador'), 'P4D05 Portal user id is not a worker fallback');
$assert(!str_contains($serviceSource, 'referencia_externa_personal'), 'P4D06 external reference is not a worker fallback');
$assert($request instanceof RequestInterface && $request->getHeaderLine('Authorization') === 'Bearer portal3-journey-jwt', 'P4D07 server-side JWT is used');
$assert(!$request->hasHeader('X-Device-Id') && !$request->hasHeader('X-Credential-Key'), 'P4D08 Device Auth is never used');
$assert(!preg_match('/fetch\(|axios\.|XMLHttpRequest|api\/v2\/journeys/i', $viewSource), 'P4D09 browser direct Journey API is absent');
$assert($request instanceof RequestInterface && str_contains((string) $request->getUri(), '/api/v2/journeys/evaluate'), 'P4D10 exact contract endpoint is used');
$assert(($body['id_estacion'] ?? 0) === 1 && ($body['fecha_local'] ?? '') === '2026-10-12' && ($body['snapshot_audit_ref'] ?? '') === 'audit-1', 'P4D11 exact request payload is used');
$assert(($result['data']['calculation']['segundos_trabajados_totales'] ?? null) === 28800 && !str_contains($serviceSource, 'segundos_trabajados_totales'), 'P4D12 response is parsed without local recalculation');
$assert(str_contains($viewSource, 'UNSCHEDULED') && str_contains($viewSource, 'Sin horario efectivo asignado'), 'P4D13 UNSCHEDULED is rendered distinctly');
$assert(!str_contains($viewSource, "'UNSCHEDULED' => 'Descanso programado'"), 'P4D14 UNSCHEDULED does not become DAY_OFF');
$assert(!str_contains($viewSource, 'SIN MARCACIÓN'), 'P4D15 UNSCHEDULED does not become SIN MARCACIÓN');
$assert(str_contains($viewSource, 'Nunca se activa automáticamente'), 'P4D16 UNSCHEDULED does not become automatic FALTA');
$assert(str_contains($viewSource, 'Descanso programado'), 'P4D17 DAY_OFF has distinct wording');
$assert(str_contains($viewSource, 'events') && str_contains($viewSource, 'foreach ($events'), 'P4D18 observed events remain visible on DAY_OFF');
$unknown = $make([new Response(200, [], json_encode($journeyPayload('UNKNOWN', 'UNKNOWN', false), JSON_THROW_ON_ERROR))])->evaluateJourney($user, 1, 1, '2026-10-12');
$incomplete = $make([new Response(200, [], json_encode($journeyPayload('SCHEDULED', 'INCOMPLETE', false), JSON_THROW_ON_ERROR))])->evaluateJourney($user, 1, 1, '2026-10-12');
$assert(($unknown['data']['journey_status'] ?? '') === 'UNKNOWN_SCHEDULE', 'P4D19 UNKNOWN stays non-final');
$assert(($incomplete['data']['source_evidence']['window_level2_status'] ?? '') === 'INCOMPLETE', 'P4D20 INCOMPLETE stays non-final');
$assert(str_contains($serviceSource, 'window_level2_status'), 'P4D21 aggregate source status is preserved');
$assert(str_contains($viewSource, 'foreach ($events as $event)'), 'P4D22 all returned events are rendered');
$assert(str_contains($viewSource, 'foreach ($events as $event)') && !str_contains($viewSource, 'array_slice($events'), 'P4D23 5+ events are not truncated');
$assert(str_contains($viewSource, 'pairing') && str_contains($serviceSource, 'pairing'), 'P4D24 unmatched pairing evidence can be preserved');
$assert(str_contains($viewSource, 'journey_status') && str_contains($serviceSource, 'journey_status'), 'P4D25 ambiguous/review status is preserved');
$assert(str_contains($viewSource, 'segmentos') && str_contains($serviceSource, 'schedule'), 'P4D26 multi-segment Journey data is preserved');
$assert(str_contains($viewSource, 'dia_offset_fin'), 'P4D27 night-shift local workday context is preserved');
$assert(!preg_match('/first.*punch|last.*punch|emparej.*local/i', $serviceSource . $controllerSource), 'P4D28 Portal3 does not pair punches');
$assert(!preg_match('/worked.*minute|segundos.*trabajados.*\+|strtotime.*ocurrido/i', $serviceSource . $controllerSource), 'P4D29 Portal3 does not calculate worked time');
$assert(!preg_match('/laten|retardo/i', $serviceSource . $controllerSource . $viewSource), 'P4D30 Portal3 does not calculate lateness');
$assert(!preg_match('/early.?departure|salida anticipada/i', $serviceSource . $controllerSource . $viewSource), 'P4D31 Portal3 does not calculate early departure');
$assert(!preg_match('/overtime|tiempo extra|horas extra/i', $serviceSource . $controllerSource . $viewSource), 'P4D32 Portal3 does not calculate overtime');
$assert(str_contains($viewSource, 'Autorizar FALTA explícitamente') && str_contains($controllerSource, 'authorizeFalta'), 'P4D33 Portal3 does not infer FALTA locally');
$assert(!preg_match('/n[oó]mina|payroll|descuento/i', $viewSource), 'P4D34 Portal3 does not produce payroll conclusion');
$failed = $make([new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR))])->evaluateJourney($user, 1, 1, '2026-10-12');
$assert(($failed['error']['kind'] ?? '') === 'api_unavailable', 'P4D35 HTTP failure is distinct from Journey UNKNOWN');
$assert(str_contains($controllerSource, '$journeys = []') && str_contains($controllerSource, '$journeyErrors = []'), 'P4D36 stale Journey is cleared before refresh');
$assert(str_contains($controllerSource, 'getEffectiveSchedule') && str_contains($serviceSource, 'api/v2/workers/'), 'P4D37 P04C schedule integration is preserved');
$assert(str_contains($controllerSource, 'getLevel2WindowStatus'), 'P4D38 P04B Level 2 integration is preserved');
$assert(str_contains($controllerSource, 'createDailyPunchSnapshot') && str_contains($serviceSource, 'readDailyPunchSnapshotPage'), 'P4D39 P04A/P03 behavior is preserved');
$assert(!preg_match('/JWT|Authorization|snapshot_token|secret/i', $viewSource), 'P4D40 internal JWT/secrets are absent from rendered view');

printf("CHECADOR_P04D_JOURNEY_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

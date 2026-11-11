<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
session_start();

use App\Models\Usuario;
use App\Services\ChecadorDailyReadService;
use App\Services\ChecadorWeeklyScheduleService;
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

$weekDates = array_column(ChecadorWeeklyScheduleService::dates('2026-11-02'), 'date');
$midweekStart = '2026-11-12';
$midweekTargets = [
    'LUNES' => '2026-11-16', 'MARTES' => '2026-11-17', 'MIERCOLES' => '2026-11-18',
    'JUEVES' => '2026-11-12', 'VIERNES' => '2026-11-13', 'SABADO' => '2026-11-14', 'DOMINGO' => '2026-11-15',
];
$resolvedMidweekTargets = [];
foreach ($midweekTargets as $weekday => $_date) {
    $resolvedMidweekTargets[$weekday] = ChecadorWeeklyScheduleService::firstWeekdayOnOrAfter($weekday, $midweekStart);
}
$assert($resolvedMidweekTargets === $midweekTargets
    && ChecadorWeeklyScheduleService::firstWeekdayOnOrAfter('jueves', $midweekStart) === $midweekStart
    && ChecadorWeeklyScheduleService::firstWeekdayOnOrAfter('FUNDAY', $midweekStart) === null
    && ChecadorWeeklyScheduleService::firstWeekdayOnOrAfter('LUNES', '2026-02-30') === null,
    'a Thursday initial start maps Thu-Sun into the selected week and Mon-Wed to their first recurrence without backdating');
$workers = [];
for ($id = 1004; $id <= 1049; $id++) {
    $workers[] = [
        'worker_id' => $id,
        'full_name' => 'Colaborador ' . $id,
        'verified_dates' => $id <= 1008 ? $weekDates : ($id === 1009 ? [$weekDates[1]] : [$weekDates[0]]),
        'versioned_verified_dates' => $id <= 1008 ? $weekDates : ($id === 1009 ? [$weekDates[1]] : [$weekDates[0]]),
    ];
}
$eligible = ChecadorWeeklyScheduleService::workersWithVerifiedMembership($workers, $weekDates);
$assert(array_column($eligible, 'worker_id') === [1004, 1005, 1006, 1007, 1008],
    'only the five workers verified for every date of the selected week are assignable');
$assert(ChecadorWeeklyScheduleService::workersWithVerifiedMembership($workers, []) === []
    && ChecadorWeeklyScheduleService::workersWithVerifiedMembership([['worker_id' => 1]], $weekDates) === [],
    'an empty requested interval or missing verified_dates fails closed');
$anyDateEligible = ChecadorWeeklyScheduleService::workersWithAnyVerifiedMembership($workers, $weekDates);
$assert(array_column($anyDateEligible, 'worker_id') === range(1004, 1049)
    && ($anyDateEligible[5]['assignable_dates'] ?? []) === [$weekDates[1]],
    'date-aware selector includes workers on verified days without claiming full-week membership');
$partialWeekWorker = ChecadorWeeklyScheduleService::workersWithAnyVerifiedMembership([[
    'worker_id' => 2,
    'versioned_verified_dates' => ['2026-11-06', '2026-11-07', '2026-11-08'],
]], $weekDates);
$assert(count($partialWeekWorker) === 1
    && ($partialWeekWorker[0]['assignable_dates'] ?? []) === ['2026-11-06', '2026-11-07', '2026-11-08'],
    'a membership starting Friday enables only Friday through Sunday in the initial week');
$workerOneHistory = [
    ['id' => 56, 'id_personal' => 1, 'id_estacion' => 1, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'LUNES', 'valido_desde' => '2026-11-09', 'valido_hasta' => null, 'es_activo' => 1],
    ['id' => 57, 'id_personal' => 1, 'id_estacion' => 1, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'MARTES', 'valido_desde' => '2026-11-10', 'valido_hasta' => null, 'es_activo' => 1],
    ['id' => 58, 'id_personal' => 1, 'id_estacion' => 1, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'MIERCOLES', 'valido_desde' => '2026-11-11', 'valido_hasta' => null, 'es_activo' => 1],
    ['id' => 59, 'id_personal' => 1, 'id_estacion' => 1, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'JUEVES', 'valido_desde' => '2026-11-12', 'valido_hasta' => null, 'es_activo' => 1],
    ['id' => 60, 'id_personal' => 1, 'id_estacion' => 1, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'VIERNES', 'valido_desde' => '2026-11-13', 'valido_hasta' => null, 'es_activo' => 1],
    ['id' => 61, 'id_personal' => 1, 'id_estacion' => 1, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'SABADO', 'valido_desde' => '2026-11-14', 'valido_hasta' => null, 'es_activo' => 1],
    ['id' => 62, 'id_personal' => 1, 'id_estacion' => 1, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'DOMINGO', 'valido_desde' => '2026-11-15', 'valido_hasta' => '2026-11-22', 'es_activo' => 0],
    ['id' => 63, 'id_personal' => 1, 'id_estacion' => 1, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'DOMINGO', 'valido_desde' => '2026-11-22', 'valido_hasta' => null, 'es_activo' => 1],
];
$fridayGap = ChecadorWeeklyScheduleService::habitualAssignmentInterval($workerOneHistory, 1, 1, 'VIERNES', '2026-11-06');
$saturdayGap = ChecadorWeeklyScheduleService::habitualAssignmentInterval($workerOneHistory, 1, 1, 'SABADO', '2026-11-07');
$sundayGap = ChecadorWeeklyScheduleService::habitualAssignmentInterval($workerOneHistory, 1, 1, 'DOMINGO', '2026-11-08');
$mondayOverlap = ChecadorWeeklyScheduleService::habitualAssignmentInterval($workerOneHistory, 1, 1, 'LUNES', '2026-11-09');
$assert(($fridayGap['ok'] ?? false) === true && ($fridayGap['valid_to'] ?? null) === '2026-11-13'
    && ($saturdayGap['ok'] ?? false) === true && ($saturdayGap['valid_to'] ?? null) === '2026-11-14'
    && ($sundayGap['ok'] ?? false) === true && ($sundayGap['valid_to'] ?? null) === '2026-11-15'
    && ($mondayOverlap['status'] ?? '') === 'conflict'
    && (int) ($mondayOverlap['conflicting_assignments'][0]['id'] ?? 0) === 56,
    'initial weekly gaps stop at future habitual versions while a date already covered by history is rejected');
$tuesdayEligible = ChecadorWeeklyScheduleService::workersWithVerifiedMembership($workers, [$weekDates[1]]);
$assert(array_column($tuesdayEligible, 'worker_id') === [1004, 1005, 1006, 1007, 1008, 1009]
    && !in_array(1010, array_column($tuesdayEligible, 'worker_id'), true),
    'one-day server eligibility accepts only workers verified on the selected date');
$assert(ChecadorWeeklyScheduleService::workersWithAnyVerifiedMembership($workers, ['2026-02-30']) === [],
    'invalid candidate dates fail closed in the date-aware selector');
$deduplicated = ChecadorWeeklyScheduleService::workersWithVerifiedMembership([$workers[0], $workers[0]], $weekDates);
$assert(count($deduplicated) === 1, 'duplicate worker identities do not create duplicate options');

$referenceOnlyWorker = [[
    'worker_id' => 1099,
    'full_name' => 'Trabajador de padrón actual',
    'verified_dates' => [],
    'versioned_verified_dates' => [],
    'roster_status' => 'current_roster',
]];
$referenceOnlyMatrix = ChecadorWeeklyScheduleService::matrix($referenceOnlyWorker, [1099 => [[
    'id' => 10001,
    'id_plantilla' => 901,
    'dia_semana' => 'LUNES',
    'valido_desde' => '2026-11-02',
    'valido_hasta' => null,
    'es_activo' => 1,
    'tipo_asignacion' => 'HABITUAL',
]]], [901 => ['nombre' => 'Turno que no debe atribuirse', 'segmentos' => []]], '2026-11-02');
$assert(count($referenceOnlyMatrix['workers'] ?? []) === 1
    && ($referenceOnlyMatrix['workers'][0]['cells']['2026-11-02']['status'] ?? '') === 'membership_unverified'
    && ($referenceOnlyMatrix['habitual_workers'][0]['habitual_coverage']['status'] ?? '') === 'unknown',
    'current-roster reference row remains visible while unverified dates mask any schedule and coverage result');
$assert(ChecadorWeeklyScheduleService::workersWithAnyVerifiedMembership($referenceOnlyWorker, $weekDates) === [],
    'reference-only roster workers remain ineligible for assignment controls without date-specific membership');
$currentRosterOnlyWorker = [[
    'worker_id' => 1100,
    'verified_dates' => [$weekDates[1]],
    'versioned_verified_dates' => [],
    'roster_status' => 'current_roster',
]];
$assert(ChecadorWeeklyScheduleService::workersWithVerifiedMembership($currentRosterOnlyWorker, [$weekDates[1]]) === []
    && ChecadorWeeklyScheduleService::workersWithAnyVerifiedMembership($currentRosterOnlyWorker, $weekDates) === [],
    'current physical roster evidence alone cannot enable habitual or temporary assignment writes');

$jsonResponse = static fn(int $status, array $body): Response => new Response(
    $status,
    ['Content-Type' => 'application/json'],
    json_encode($body, JSON_THROW_ON_ERROR)
);
$segment = [
    'ordinal' => 1,
    'tipo_segmento' => 'WORK',
    'hora_inicio_local' => '22:00:00',
    'hora_fin_local' => '06:00:00',
    'dia_offset_inicio' => 0,
    'dia_offset_fin' => 1,
];
$history = [];
$stack = HandlerStack::create(new MockHandler([
    $jsonResponse(201, ['status' => 'success', 'data' => [
        'id' => 901, 'clave' => 'QA_NOCHE_01', 'version' => 1, 'nombre' => 'Turno nocturno de prueba',
        'tipo_turno' => 'NOCTURNO', 'es_activo' => 1,
    ]]),
    $jsonResponse(200, ['status' => 'success', 'data' => [
        'id' => 901, 'clave' => 'QA_NOCHE_01', 'version' => 1, 'nombre' => 'Turno nocturno de prueba',
        'tipo_turno' => 'NOCTURNO', 'es_activo' => 1, 'segmentos' => [$segment],
    ]]),
]));
$stack->push(Middleware::history($history));
$reader = new ChecadorDailyReadService(
    new Client(['handler' => $stack, 'base_uri' => 'https://checador.mock/', 'http_errors' => false]),
    static fn(Usuario $identity): string => 'mock-token',
    'https://checador.mock/'
);
$user = new Usuario();
$user->id = 88;
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'true';
putenv('APP_ENV=dev');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=true');
$created = $reader->createShiftTemplate($user, [
    'clave' => 'QA_NOCHE_01',
    'nombre' => 'Turno nocturno de prueba',
    'tipo_turno' => 'NOCTURNO',
    'segmentos' => [$segment],
], 'm03-template-mock-001');
$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=false');
$postRequest = $history[0]['request'] ?? null;
$postBody = $postRequest instanceof RequestInterface ? json_decode((string) $postRequest->getBody(), true) : null;
$assert(($created['ok'] ?? false) === true
    && ($created['data']['segmentos'] ?? []) === [$segment]
    && $postRequest instanceof RequestInterface
    && $postRequest->getMethod() === 'POST'
    && str_contains((string) $postRequest->getUri(), '/api/v2/shift-templates')
    && ($postRequest->getHeaderLine('Idempotency-Key') ?? '') === 'm03-template-mock-001'
    && ($postBody['segmentos'][0]['dia_offset_fin'] ?? null) === 1
    && count($history) === 2
    && str_contains((string) ($history[1]['request']->getUri() ?? ''), '/versions/1'),
    'template creation uses the existing API POST and version-detail GET readback before confirming');

$assignmentHistory = [];
$assignmentStack = HandlerStack::create(new MockHandler([
    $jsonResponse(201, ['status' => 'success', 'data' => [
        'id' => 903, 'id_personal' => 1004, 'id_estacion' => 1, 'id_plantilla' => 901,
        'dia_semana' => 'MARTES', 'valido_desde' => '2026-11-03', 'valido_hasta' => '2026-11-04', 'es_activo' => 1,
        'creado_at' => '2026-11-02 18:00:00', 'registrado_at_utc' => '2026-11-02 18:00:00',
    ]]),
]));
$assignmentStack->push(Middleware::history($assignmentHistory));
$assignmentReader = new ChecadorDailyReadService(
    new Client(['handler' => $assignmentStack, 'base_uri' => 'https://checador.mock/', 'http_errors' => false]),
    static fn(Usuario $identity): string => 'mock-token',
    'https://checador.mock/'
);
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'true';
putenv('APP_ENV=dev');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=true');
$assignmentResult = $assignmentReader->createShiftAssignment($user, [
    'id_personal' => 1004, 'id_estacion' => 1, 'id_plantilla' => 901,
    'dia_semana' => 'MARTES', 'valido_desde' => '2026-11-03', 'valido_hasta' => '2026-11-04',
], 'm03-assignment-mock-001');
$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=false');
$assignmentRequest = $assignmentHistory[0]['request'] ?? null;
$assignmentBody = $assignmentRequest instanceof RequestInterface ? json_decode((string) $assignmentRequest->getBody(), true) : null;
$assert(($assignmentResult['ok'] ?? false) === true
    && ($assignmentResult['http_status'] ?? null) === 201
    && ($assignmentResult['data']['creado_at'] ?? null) === '2026-11-02 18:00:00'
    && ($assignmentResult['data']['registrado_at_utc'] ?? null) === '2026-11-02 18:00:00'
    && $assignmentRequest instanceof RequestInterface
    && $assignmentRequest->getMethod() === 'POST'
    && str_contains((string) $assignmentRequest->getUri(), '/api/v2/shift-assignments')
    && $assignmentRequest->getHeaderLine('Idempotency-Key') === 'm03-assignment-mock-001'
    && ($assignmentBody['id_personal'] ?? null) === 1004
    && ($assignmentBody['id_estacion'] ?? null) === 1
    && ($assignmentBody['valido_desde'] ?? null) === '2026-11-03'
    && ($assignmentBody['valido_hasta'] ?? null) === '2026-11-04',
    'schedule-only gate accepts the existing API route only after a contract-shaped HTTP 201 audit response');

$wrongStatusHistory = [];
$wrongStatusStack = HandlerStack::create(new MockHandler([
    $jsonResponse(200, ['status' => 'success', 'data' => [
        'id' => 904, 'id_personal' => 1004, 'id_estacion' => 1, 'id_plantilla' => 901,
        'dia_semana' => 'MARTES', 'valido_desde' => '2026-11-03', 'valido_hasta' => '2026-11-04', 'es_activo' => 1,
        'creado_at' => '2026-11-02 18:00:00', 'registrado_at_utc' => '2026-11-02 18:00:00',
    ]]),
]));
$wrongStatusStack->push(Middleware::history($wrongStatusHistory));
$wrongStatusReader = new ChecadorDailyReadService(
    new Client(['handler' => $wrongStatusStack, 'base_uri' => 'https://checador.mock/', 'http_errors' => false]),
    static fn(Usuario $identity): string => 'mock-token',
    'https://checador.mock/'
);
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'true';
putenv('APP_ENV=dev');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=true');
$wrongStatus = $wrongStatusReader->createShiftAssignment($user, [
    'id_personal' => 1004, 'id_estacion' => 1, 'id_plantilla' => 901,
    'dia_semana' => 'MARTES', 'valido_desde' => '2026-11-03', 'valido_hasta' => '2026-11-04',
], 'm03-assignment-wrong-status');
$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=false');
$assert(($wrongStatus['error']['kind'] ?? '') === 'schedule_assignment_unavailable'
    && count($wrongStatusHistory) === 1,
    'assignment API response with HTTP 200 is rejected instead of being reported as a contractual create');

$missingAuditHistory = [];
$missingAuditStack = HandlerStack::create(new MockHandler([
    $jsonResponse(201, ['status' => 'success', 'data' => [
        'id' => 905, 'id_personal' => 1004, 'id_estacion' => 1, 'id_plantilla' => 901,
        'dia_semana' => 'MARTES', 'valido_desde' => '2026-11-03', 'valido_hasta' => '2026-11-04', 'es_activo' => 1,
    ]]),
]));
$missingAuditStack->push(Middleware::history($missingAuditHistory));
$missingAuditReader = new ChecadorDailyReadService(
    new Client(['handler' => $missingAuditStack, 'base_uri' => 'https://checador.mock/', 'http_errors' => false]),
    static fn(Usuario $identity): string => 'mock-token',
    'https://checador.mock/'
);
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'true';
putenv('APP_ENV=dev');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=true');
$missingAudit = $missingAuditReader->createShiftAssignment($user, [
    'id_personal' => 1004, 'id_estacion' => 1, 'id_plantilla' => 901,
    'dia_semana' => 'MARTES', 'valido_desde' => '2026-11-03', 'valido_hasta' => '2026-11-04',
], 'm03-assignment-missing-audit');
$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=false');
$assert(($missingAudit['error']['kind'] ?? '') === 'schedule_assignment_unavailable'
    && count($missingAuditHistory) === 1,
    'HTTP 201 without persisted audit timestamps fails closed');

$nonSchedulingHistory = [];
$nonSchedulingStack = HandlerStack::create(new MockHandler([]));
$nonSchedulingStack->push(Middleware::history($nonSchedulingHistory));
$nonSchedulingReader = new ChecadorDailyReadService(
    new Client(['handler' => $nonSchedulingStack, 'base_uri' => 'https://checador.mock/', 'http_errors' => false]),
    static fn(Usuario $identity): string => 'mock-token',
    'https://checador.mock/'
);
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'true';
putenv('APP_ENV=dev');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=true');
$journeyWrite = $nonSchedulingReader->evaluateJourney($user, 1004, 1, '2026-11-03');
$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=false');
$assert(($journeyWrite['error']['kind'] ?? '') === 'api_post_disabled' && $nonSchedulingHistory === [],
    'a scheduling-only gate does not enable canonical Journey POSTs');

$invalidHistory = [];
$invalidStack = HandlerStack::create(new MockHandler([]));
$invalidStack->push(Middleware::history($invalidHistory));
$invalidReader = new ChecadorDailyReadService(
    new Client(['handler' => $invalidStack, 'base_uri' => 'https://checador.mock/', 'http_errors' => false]),
    static fn(Usuario $identity): string => 'mock-token',
    'https://checador.mock/'
);
$invalid = $invalidReader->createShiftTemplate($user, [
    'clave' => 'INVALID', 'nombre' => 'Inválido', 'tipo_turno' => 'REGULAR',
    'segmentos' => [['ordinal' => 2, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00', 'hora_fin_local' => '17:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0]],
], 'm03-template-invalid');
$assert(($invalid['error']['kind'] ?? '') === 'bad_request' && $invalidHistory === [],
    'invalid segment ordinals fail before sending an API request');

$mismatchStack = HandlerStack::create(new MockHandler([
    $jsonResponse(201, ['status' => 'success', 'data' => [
        'id' => 902, 'clave' => 'QA_NOCHE_02', 'version' => 1, 'nombre' => 'Turno nocturno distinto',
        'tipo_turno' => 'NOCTURNO', 'es_activo' => 1,
    ]]),
    $jsonResponse(200, ['status' => 'success', 'data' => [
        'id' => 902, 'clave' => 'QA_NOCHE_02', 'version' => 1, 'nombre' => 'Turno nocturno distinto',
        'tipo_turno' => 'NOCTURNO', 'es_activo' => 1,
        'segmentos' => [array_replace($segment, ['dia_offset_fin' => 0])],
    ]]),
]));
$mismatchReader = new ChecadorDailyReadService(
    new Client(['handler' => $mismatchStack, 'base_uri' => 'https://checador.mock/', 'http_errors' => false]),
    static fn(Usuario $identity): string => 'mock-token',
    'https://checador.mock/'
);
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'true';
putenv('APP_ENV=dev');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=true');
$mismatch = $mismatchReader->createShiftTemplate($user, [
    'clave' => 'QA_NOCHE_02', 'nombre' => 'Turno nocturno distinto', 'tipo_turno' => 'NOCTURNO', 'segmentos' => [$segment],
], 'm03-template-mismatch');
$_ENV['APP_ENV'] = 'testing';
$_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] = 'false';
putenv('APP_ENV=testing');
putenv('CHECADOR_API_REAL_POSTS_ENABLED=false');
putenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED=false');
$assert(($mismatch['error']['kind'] ?? '') === 'schedule_template_readback_unconfirmed',
    'a POST response is not treated as success when segment readback differs');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/app/Controllers/ChecadorController.php') ?: '';
$habitualJs = file_get_contents($root . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.habitual-schedule.init.js') ?: '';
$weeklyService = file_get_contents($root . '/app/Services/ChecadorWeeklyScheduleService.php') ?: '';
$view = file_get_contents($root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php') ?: '';
$routes = file_get_contents($root . '/routes/web.php') ?: '';
$routeAuth = file_get_contents($root . '/app/Core/Route.php') ?: '';
$assert(str_contains($routes, "Route::auth(['ChecadorController', 'createScheduleTemplate'])")
    && str_contains($routes, "'/recursos-humanos/checador/programacion-semanal/plantillas'")
    && str_contains($routeAuth, "['auth', 'csrf']"),
    'template POST uses the authenticated and CSRF-protected Portal3 route wrapper');
$assert(str_contains($controller, "authorizeScheduling(\$workplaceId, 'editar')")
    && str_contains($controller, 'scheduleApiPostOperationsEnabled()')
    && str_contains($controller, 'createShiftTemplate($user, $template')
    && str_contains($controller, "workersWithVerifiedMembership(")
    && str_contains($weeklyService, "\$worker['versioned_verified_dates']")
    && str_contains($controller, '[$targetDate]'),
    'server handlers recheck edit ACL, local API-write gate and versioned membership for the requested day');
$assignmentHandlerStart = strpos($controller, 'public function createScheduleAssignment(): void');
$assignmentHandlerEnd = strpos($controller, 'public function closeScheduleAssignment(): void', $assignmentHandlerStart === false ? 0 : $assignmentHandlerStart);
$assignmentHandler = $assignmentHandlerStart !== false && $assignmentHandlerEnd !== false
    ? substr($controller, $assignmentHandlerStart, $assignmentHandlerEnd - $assignmentHandlerStart)
    : '';
$assert(str_contains($assignmentHandler, 'getAllShiftAssignmentsSnapshot($user, $workerId, $workplaceId, null, 200)')
    && str_contains($assignmentHandler, "\$existing['data']['complete'] ?? false")
    && str_contains($assignmentHandler, 'habitualAssignmentInterval(')
    && str_contains($assignmentHandler, "\$validTo = \$interval['valid_to']")
    && str_contains($assignmentHandler, 'La API rechazó la asignación del {$targetDate} porque entra en conflicto con otra vigencia'),
    'habitual writes inspect the complete snapshot and bound a gap before a future version without overwriting it');
$assert(str_contains($assignmentHandler, "firstWeekdayOnOrAfter(")
    && str_contains($assignmentHandler, "workersForScheduleWeek(\$workplaceId, \$membershipWeekStart")
    && str_contains($assignmentHandler, "\$effectiveStartDate <= \$todayAtWorkplace")
    && str_contains($habitualJs, 'this.dateForWeekday(weekday)')
    && str_contains($habitualJs, '.sort((left, right) => left.targetDate.localeCompare(right.targetDate))'),
    'habitual writes validate a future effective start, first weekday occurrence, target-week membership, and chronological partial-failure order');
$assert(str_contains($controller, 'existing_days_by_date')
    && str_contains($controller, 'workersForScheduleWeek($workplaceId, $nextWeekStart')
    && str_contains($controller, "workersWithAnyVerifiedMembership(\n                [\$worker]")
    && str_contains($controller, 'checador.habitual-schedule.init.js?v=20261105-4'),
    'midweek readback returns assignment and membership evidence through the following week and serves the refreshed initializer');
$assignmentEffectiveFilterStart = strpos($assignmentHandler, '$effective = array_values(');
$assignmentEffectiveFilterEnd = $assignmentEffectiveFilterStart === false
    ? false
    : strpos($assignmentHandler, '));', $assignmentEffectiveFilterStart);
$assignmentEffectiveFilter = $assignmentEffectiveFilterStart !== false && $assignmentEffectiveFilterEnd !== false
    ? substr($assignmentHandler, $assignmentEffectiveFilterStart, $assignmentEffectiveFilterEnd - $assignmentEffectiveFilterStart)
    : '';
$assignmentCreateCall = strpos($assignmentHandler, '$result = $reader->createShiftAssignment(');
$assignmentCreateFailure = $assignmentCreateCall === false
    ? false
    : strpos($assignmentHandler, "if (!(\$result['ok'] ?? false))", $assignmentCreateCall);
$assignmentReadbackAfterCreate = $assignmentCreateCall === false
    ? false
    : strpos($assignmentHandler, '$reader->getShiftAssignments(', $assignmentCreateCall);
$assert($assignmentEffectiveFilter !== ''
    && !str_contains($assignmentEffectiveFilter, "\$row['es_activo']")
    && str_contains($assignmentHandler, "\$effective[0]['es_activo']")
    && str_contains($assignmentHandler, '$responseStatus = 409;'),
    'a closed assignment whose half-open interval covers the target date blocks a duplicate create as a business conflict');
$assert($assignmentCreateFailure !== false && $assignmentReadbackAfterCreate !== false
    && $assignmentCreateFailure < $assignmentReadbackAfterCreate
    && str_contains($assignmentHandler, "\$result['error']['status']")
    && str_contains($assignmentHandler, "'administrative_conflict'"),
    'an API business rejection is returned with its client status before readback instead of being mislabeled as HTTP 502');
$assert(str_contains($assignmentHandler, '$feedbackWorkplaceId = $workplaceId;')
    && str_contains($assignmentHandler, '$this->sessionWorkplaceId($feedbackScope)')
    && str_contains($assignmentHandler, "'workplace_id' => (int) (\$feedbackWorkplaceId ?? 0)"),
    'rejected out-of-scope form values retain feedback only within the validated session locality');
$assert(!str_contains($view, 'data-schedule-template-form')
    && !str_contains($view, 'segments[0][tipo_segmento]')
    && str_contains($view, 'REST')
    && str_contains($view, 'foreach ($assignableWorkers as $assignableWorker)')
    && str_contains($view, 'data-verified-dates=')
    && str_contains($view, 'data-schedule-target-date')
    && str_contains($view, 'foreach ($templates ?? [] as $template)')
    && str_contains($view, '$templateOptionLabel($template)'),
    'M03 assignment selectors keep authorized V2 templates and explicit rest without a duplicate template editor');
$assert(str_contains($view, "\$assignableWorker['versioned_verified_dates']")
    && str_contains($view, 'data-worker-label=')
    && str_contains($view, 'data-schedule-worker-status'),
    'temporary options carry the canonical versioned dates and a readable eligibility status');
$assert(str_contains($habitualJs, "dateSelect?.addEventListener('change', () => {")
    && str_contains($habitualJs, 'this.updateEligibility();')
    && str_contains($habitualJs, "verifiedDates.includes(targetDate)")
    && str_contains($habitualJs, 'Ningún trabajador tiene membresía confirmada para ${targetDate}.')
    && str_contains($habitualJs, 'option.textContent = option.dataset.workerLabel || option.textContent')
    && str_contains($habitualJs, 'option.disabled = !eligible'),
    'temporary worker eligibility is date-aware while the option label remains only the employee name');
$preflightStart = strpos($habitualJs, 'const preflight = await axios.get(readUrl');
$writeAfterPreflight = $preflightStart === false ? false : strpos($habitualJs, 'await axios.post(form.action, payload', $preflightStart);
$assert(str_contains($view, 'data-assignment-read-url="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignaciones/readback"')
    && $preflightStart !== false
    && str_contains($habitualJs, 'preflightData?.complete !== true')
    && str_contains($habitualJs, 'Object.prototype.hasOwnProperty.call(existingByDate, day.targetDate)')
    && $writeAfterPreflight !== false
    && strpos($habitualJs, 'const blockedDates = pending.filter', $preflightStart) < $writeAfterPreflight,
    'habitual multi-day save rereads complete membership and assignment state and blocks known date conflicts before the first API POST');
$assert(str_contains($view, 'Administrar turnos')
    && str_contains($view, 'Consultar Horarios y turnos')
    && str_contains($view, 'checador/horarios-turnos')
    && !str_contains($view, 'Turnos disponibles')
    && !str_contains($view, 'shift-template-catalog-heading'),
    'M03 links to the canonical M04 catalog instead of rendering a duplicate table');
$assert(str_contains($controller, "in_array(\$coverage, ['ready', 'demo_fixture'], true)") === false,
    'global partial coverage no longer suppresses an individually verified assignment cohort');
$assert(str_contains($controller, 'getShiftTemplateVersion($currentUser')
    && str_contains($controller, 'array_slice($listedTemplates, 0, 50)')
    && str_contains($controller, '$templateDetailsById[(int) $template[\'id\']] = $template'),
    'visible templates are offered only after their existing API version-detail GET confirms segments');

$weekViewPath = $root . '/app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-week.php';
$renderWeek = static function (array $data) use ($weekViewPath): string {
    ob_start();
    try {
        extract($data, EXTR_SKIP);
        include $weekViewPath;
        return (string) ob_get_clean();
    } finally {
        while (ob_get_level() > 0) ob_end_clean();
    }
};
$viewBase = [
    'weekDates' => ChecadorWeeklyScheduleService::dates('2026-11-02'),
    'scheduleRows' => [],
    'assignableWorkers' => $anyDateEligible,
    'selectedWorkplaceId' => 1,
    'selectedWorkplaceName' => 'Interlomas',
    'weekStart' => '2026-11-02',
    'effectiveDateMin' => '2026-11-06',
    'effectiveDateMax' => '2026-11-08',
    'timezone' => 'America/Mexico_City',
    'coverage' => 'partial',
    'rosterCoverage' => 'partial',
    'rosterMessage' => 'Membresías parciales para parte del padrón.',
    'matrixCoverage' => 'partial',
    'matrixMessage' => 'La consulta no informa continuación.',
    'errorMessage' => '',
    'feedback' => null,
    'templates' => [[
        'id' => 1201,
        'clave' => 'LEGACY_IL1_220000_060000',
        'version' => 1,
        'nombre' => 'Legacy Interlomas — ENCARGADO 3 — 22:00–06:00',
        'tipo_turno' => 'NOCTURNO',
        'es_activo' => 1,
        'segmentos' => [[
            'ordinal' => 1,
            'tipo_segmento' => 'WORK',
            'hora_inicio_local' => '22:00:00',
            'hora_fin_local' => '06:00:00',
            'dia_offset_inicio' => 0,
            'dia_offset_fin' => 1,
        ]],
    ]],
    'templateCatalogAvailable' => true,
    'canAssign' => true,
    'canEditSchedules' => true,
    'canManageTemplates' => false,
    'demoMode' => false,
    'legalReference' => App\Services\ChecadorLaborPolicy::referenceForDate('2026-11-02'),
    'dailyLimits' => App\Services\ChecadorLaborPolicy::generalDailyReferences(),
];
$viewBase['assignableWorkers'] = $anyDateEligible;
$viewBase['assignableWorkers'][0]['full_name'] = 'Chino Varela Urbano';
$habitualDates = $viewBase['weekDates'];
$habitualVerifiedDates = array_column($habitualDates, 'date');
$habitualCells = [];
foreach ($habitualDates as $index => $day) {
    $habitualCells[$day['date']] = $index === 0
        ? ['status' => 'assigned', 'template' => ['nombre' => 'Turno V2'], 'assignment_type' => 'HABITUAL']
        : ['status' => 'unassigned', 'label' => 'Sin asignación visible'];
}
$viewBase['habitualScheduleRows'] = [[
    'worker_id' => 1004,
    'full_name' => 'Trabajador QA de prueba',
    'verified_dates' => $habitualVerifiedDates,
    'has_versioned_membership' => true,
    'schedule_read_status' => 'available',
    'cells' => $habitualCells,
    'habitual_coverage' => ChecadorWeeklyScheduleService::habitualCoverage([
        'verified_dates' => $habitualVerifiedDates,
        'schedule_read_status' => 'available',
        'cells' => $habitualCells,
    ], $habitualDates),
]];
$authorizedHtml = $renderWeek($viewBase);
preg_match('/<section class="card mb-3" aria-labelledby="weekly-matrix-heading".*?<\/section>/s', $authorizedHtml, $weeklySectionMatch);
$temporaryTriggerCount = substr_count($weeklySectionMatch[0] ?? '', 'data-bs-target="#temporary-schedule-assignment-modal"');
preg_match('/<select class="form-select" id="temporary-assignment-worker".*?<\\/select>/s', $authorizedHtml, $workerSelectMatch);
preg_match_all('/<option value="([0-9]+)"[^>]*>/', $workerSelectMatch[0] ?? '', $workerOptionMatches);
$assert(!str_contains($authorizedHtml, 'action="/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas"')
    && str_contains($authorizedHtml, 'action="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignar"')
    && array_map('intval', $workerOptionMatches[1] ?? []) === range(1004, 1049)
    && preg_match('/<option value="1009"[^>]*data-verified-dates="2026-11-03"[^>]*disabled>/', $authorizedHtml) === 1,
    'manager view exposes the temporary-assignment modal and disables workers not verified for the initially selected date');
$assert(str_contains($authorizedHtml, 'Nuevo cambio temporal')
    && str_contains($authorizedHtml, 'temporary-schedule-assignment-modal')
    && str_contains($authorizedHtml, 'Horario habitual vigente de referencia')
    && str_contains($authorizedHtml, 'Guardar cambio')
    && $temporaryTriggerCount === 1
    && str_contains($weeklySectionMatch[0] ?? '', 'class="btn bg-white text-primary"')
    && str_contains($weeklySectionMatch[0] ?? '', 'ti ti-plus')
    && substr_count($authorizedHtml, 'id="temporary-schedule-assignment-modal"') === 1
    && !str_contains($authorizedHtml, 'Registrar plantilla local de estación')
    && !str_contains($authorizedHtml, 'LEGACY_IL1_220000_060000')
    && !str_contains($authorizedHtml, 'Legacy Interlomas')
    && str_contains($authorizedHtml, 'ENCARGADO 3')
    && str_contains($authorizedHtml, 'Consultar Horarios y turnos'),
    'M03 uses the existing assignment writer in a modal, removes local template capture, and displays clean names with an M04 link');
$turnLabelCount = preg_match_all('/<option value="1201">([^<]*)<\/option>/u', $authorizedHtml, $turnLabelMatches);
$assert(preg_match('/<option value="1004" data-worker-label="Chino Varela Urbano"[^>]*>Chino Varela Urbano<\/option>/', $workerSelectMatch[0] ?? '') === 1
    && !str_contains($workerSelectMatch[0] ?? '', 'Chino Varela Urbano · 1004')
    && preg_match('/<option value="1201">ENCARGADO 3 · 22:00–06:00 \(\+1 día\)<\/option>/', $authorizedHtml) === 1
    && $turnLabelCount === 9
    && count(array_unique($turnLabelMatches[1] ?? [])) === 1
    && !preg_match('/<option value="1201">[^<]*(?:legacy|v1|v2)[^<]*<\/option>/iu', $authorizedHtml)
    && str_contains($authorizedHtml, '<option value="REST">Descanso explícito</option>'),
    'M03 worker and turn options show clean labels while retaining worker/template/rest values');
$assert(str_contains($authorizedHtml, '<th scope="col">Cobertura habitual</th>')
    && str_contains($authorizedHtml, '<span class="visually-hidden">Acciones</span><i class="ti ti-dots-vertical fs-6" aria-hidden="true"></i>')
    && str_contains($authorizedHtml, 'data-habitual-worker-id="1004"')
    && str_contains($authorizedHtml, 'data-habitual-assignment-read-url=')
    && str_contains($authorizedHtml, 'schedule[DOMINGO]')
    && str_contains($authorizedHtml, 'name="effective_start_date"')
    && str_contains($authorizedHtml, 'min="2026-11-06"')
    && str_contains($authorizedHtml, 'max="2026-11-08"')
    && str_contains($authorizedHtml, 'primera fecha igual o posterior al inicio y se repite semanalmente')
    && str_contains($authorizedHtml, 'data-habitual-day-effective')
    && str_contains($authorizedHtml, 'ti ti-calendar-plus me-1')
    && str_contains($authorizedHtml, 'Completar horario habitual')
    && !str_contains($authorizedHtml, 'Cambiar horario habitual</button></li>')
    && str_contains($authorizedHtml, 'ti ti-history me-1')
    && str_contains($authorizedHtml, 'aria-label="Acciones de Trabajador QA de prueba"'),
    'manager row action invokes the existing modal after an authorized complete readback and preserves verified assigned days');
$rowDropdownRestored = preg_match('/<div class="dropdown dropstart">\s*<button class="btn p-0 border-0 bg-transparent text-body"[^>]*>\s*<i class="ti ti-dots-vertical fs-6" aria-hidden="true"><\/i>\s*<\/button>/s', $authorizedHtml) === 1;
$assert($rowDropdownRestored
    && str_contains($authorizedHtml, 'class="btn btn-light dropdown-toggle text-dark" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Opciones del módulo"')
    && str_contains($authorizedHtml, 'ti ti-dots-vertical fs-4')
    && str_contains($authorizedHtml, 'class="dropdown-menu dropdown-menu-end shadow-sm"')
    && str_contains($authorizedHtml, 'class="dropdown-item pointer"')
    && str_contains($authorizedHtml, '>Configuración</a>')
    && str_contains($authorizedHtml, '>Asistencia del día</a>'),
    'M03 header reuses the payroll dropdown button/menu classes while worker-row dropdowns retain the compact dots-only trigger');
preg_match('/<td>\s*<span class="badge [^"]+">Parcial<\/span>.*?<\/td>/s', $authorizedHtml, $coverageCellMatch);
$assert(isset($coverageCellMatch[0])
    && !str_contains($coverageCellMatch[0], '<button')
    && !str_contains($coverageCellMatch[0], 'Ver historial')
    && str_contains($authorizedHtml, 'href="/departamento-operativo/recursos-humanos/checador/horarios-turnos?worker_id=1004&amp;date=2026-11-02"'),
    'habitual coverage contains status and day count only; history remains in the row menu with the existing destination');

$emptyCells = [];
foreach ($habitualDates as $day) {
    $emptyCells[$day['date']] = ['status' => 'unassigned', 'label' => 'Sin asignación confirmada'];
}
$emptyCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => $habitualVerifiedDates,
    'schedule_read_status' => 'available',
    'cells' => $emptyCells,
], $habitualDates);
$emptyHtml = $renderWeek(array_replace($viewBase, ['habitualScheduleRows' => [[
    'worker_id' => 1011,
    'full_name' => 'Trabajador QA sin horario',
    'verified_dates' => $habitualVerifiedDates,
    'has_versioned_membership' => true,
    'schedule_read_status' => 'available',
    'cells' => $emptyCells,
    'habitual_coverage' => $emptyCoverage,
]]]));
$assert(($emptyCoverage['status'] ?? '') === 'unassigned'
    && str_contains($emptyHtml, 'Asignar horario habitual</button></li>')
    && !str_contains($emptyHtml, 'Completar horario habitual')
    && !str_contains($emptyHtml, 'Cambiar horario habitual</button></li>'),
    'a completely read 0/7 worker with proven membership receives only the initial assignment action');

$partialWeekVerifiedDates = ['2026-11-06', '2026-11-07', '2026-11-08'];
$partialWeekCells = [];
foreach ($habitualDates as $day) {
    $partialWeekCells[$day['date']] = in_array($day['date'], $partialWeekVerifiedDates, true)
        ? ['status' => 'unassigned', 'label' => 'Sin asignación confirmada']
        : ['status' => 'membership_unverified', 'label' => 'Pertenencia no confirmada'];
}
$partialMembershipCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => $partialWeekVerifiedDates,
    'schedule_read_status' => 'available',
    'cells' => $partialWeekCells,
], $habitualDates);
$partialMembershipHtml = $renderWeek(array_replace($viewBase, ['habitualScheduleRows' => [[
    'worker_id' => 2,
    'full_name' => 'Trabajador con membresía desde viernes',
    'verified_dates' => $partialWeekVerifiedDates,
    'has_versioned_membership' => true,
    'schedule_read_status' => 'available',
    'cells' => $partialWeekCells,
    'habitual_coverage' => $partialMembershipCoverage,
]] ]));
$assert(($partialMembershipCoverage['status'] ?? '') === 'unknown'
    && str_contains($partialMembershipHtml, 'data-habitual-worker-id="2"')
    && str_contains($partialMembershipHtml, 'data-habitual-assignment-read-url=')
    && str_contains($partialMembershipHtml, 'Asignar horario habitual</button></li>')
    && !str_contains($partialMembershipHtml, 'Completar horario habitual</button></li>'),
    'an initially unassigned worker with verified membership only Fri-Sun can open the initial flow without claiming full-week coverage');

$partialUnavailableCells = $partialWeekCells;
foreach ($partialWeekVerifiedDates as $date) {
    $partialUnavailableCells[$date] = ['status' => 'schedule_unavailable', 'label' => 'Horario no disponible'];
}
$partialUnavailableCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => $partialWeekVerifiedDates,
    'schedule_read_status' => 'unavailable',
    'cells' => $partialUnavailableCells,
], $habitualDates);
$partialUnavailableHtml = $renderWeek(array_replace($viewBase, ['habitualScheduleRows' => [[
    'worker_id' => 2,
    'full_name' => 'Trabajador con lectura incompleta',
    'verified_dates' => $partialWeekVerifiedDates,
    'has_versioned_membership' => true,
    'schedule_read_status' => 'unavailable',
    'cells' => $partialUnavailableCells,
    'habitual_coverage' => $partialUnavailableCoverage,
]] ]));
$assert(!str_contains($partialUnavailableHtml, 'data-habitual-worker-id="2"')
    && !str_contains($partialUnavailableHtml, 'Asignar horario habitual</button></li>')
    && str_contains($partialUnavailableHtml, 'worker_id=2'),
    'a failed assignment-history read remains read-only even when membership is confirmed for part of the week');

$partialAssignedCells = $partialWeekCells;
$partialAssignedCells['2026-11-06'] = [
    'status' => 'assigned',
    'assignment' => ['id' => 2802, 'id_plantilla' => 1201, 'tipo_asignacion' => 'HABITUAL', 'es_activo' => 1],
    'template' => ['nombre' => 'Turno V2'],
];
$partialAssignedCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => $partialWeekVerifiedDates,
    'schedule_read_status' => 'available',
    'cells' => $partialAssignedCells,
], $habitualDates);
$partialAssignedHtml = $renderWeek(array_replace($viewBase, ['habitualScheduleRows' => [[
    'worker_id' => 2,
    'full_name' => 'Trabajador con un día asignado',
    'verified_dates' => $partialWeekVerifiedDates,
    'has_versioned_membership' => true,
    'schedule_read_status' => 'available',
    'cells' => $partialAssignedCells,
    'habitual_coverage' => $partialAssignedCoverage,
]] ]));
$assert(!str_contains($partialAssignedHtml, 'data-habitual-worker-id="2"')
    && !str_contains($partialAssignedHtml, 'Asignar horario habitual</button></li>'),
    'a known habitual assignment within the verified portion suppresses the initial assignment action');

$completeCells = [];
foreach ($habitualDates as $index => $day) {
    $completeCells[$day['date']] = $index >= 5
        ? ['status' => 'rest', 'assignment_type' => 'HABITUAL']
        : ['status' => 'assigned', 'template' => ['nombre' => 'Turno V2'], 'assignment_type' => 'HABITUAL'];
    $completeCells[$day['date']]['assignment'] = [
        'id' => 2000 + $index,
        'tipo_asignacion' => 'HABITUAL',
        'es_activo' => 1,
        'dia_semana' => (string) $day['weekday'],
        'valido_desde' => $day['date'],
        'valido_hasta' => null,
    ];
}
$completeCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => $habitualVerifiedDates,
    'schedule_read_status' => 'available',
    'cells' => $completeCells,
], $habitualDates);
$completeHtml = $renderWeek(array_replace($viewBase, ['habitualScheduleRows' => [[
    'worker_id' => 1006,
    'full_name' => 'Trabajador QA completo',
    'verified_dates' => $habitualVerifiedDates,
    'has_versioned_membership' => true,
    'schedule_read_status' => 'available',
    'cells' => $completeCells,
    'habitual_coverage' => $completeCoverage,
]]]));
$assert(($completeCoverage['status'] ?? '') === 'complete'
    && ($completeCoverage['assignable'] ?? true) === false
    && str_contains($completeHtml, 'data-habitual-replacement-worker-id="1006"')
    && str_contains($completeHtml, 'Cambiar horario habitual</button></li>')
    && !str_contains($completeHtml, 'Asignar horario habitual</button></li>')
    && str_contains($completeHtml, 'aria-label="Acciones de Trabajador QA completo"')
    && str_contains($completeHtml, 'worker_id=1006'),
    'a fully covered worker is offered the existing permanent replacement flow and cannot receive a duplicate initial assignment');

$unknownCells = $habitualCells;
$unknownCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => [$habitualDates[0]['date']],
    'schedule_read_status' => 'available',
    'cells' => $unknownCells,
], $habitualDates);
$unknownHtml = $renderWeek(array_replace($viewBase, ['habitualScheduleRows' => [[
    'worker_id' => 1009,
    'full_name' => 'Trabajador sin pertenencia confirmada',
    'verified_dates' => [$habitualDates[0]['date']],
    'has_versioned_membership' => false,
    'schedule_read_status' => 'available',
    'cells' => $unknownCells,
    'habitual_coverage' => $unknownCoverage,
]]]));
$assert(($unknownCoverage['status'] ?? '') === 'unknown'
    && ($unknownCoverage['assignable'] ?? true) === false
    && !str_contains($unknownHtml, 'data-habitual-worker-id="1009"')
    && str_contains($unknownHtml, 'aria-label="Acciones de Trabajador sin pertenencia confirmada"')
    && str_contains($unknownHtml, 'worker_id=1009'),
    'workers without confirmed membership retain history access but receive no assignment action');
$missingMembershipMetadataHtml = $renderWeek(array_replace($viewBase, ['habitualScheduleRows' => [[
    'worker_id' => 1012,
    'full_name' => 'Trabajador sin metadato de adscripción',
    'verified_dates' => $habitualVerifiedDates,
    'schedule_read_status' => 'available',
    'cells' => $emptyCells,
    'habitual_coverage' => $emptyCoverage,
]]]));
$assert(!str_contains($missingMembershipMetadataHtml, 'data-habitual-worker-id="1012"')
    && str_contains($missingMembershipMetadataHtml, 'worker_id=1012'),
    'missing canonical membership metadata fails closed and preserves only the history action');
$partialCoverage = ChecadorWeeklyScheduleService::habitualCoverage([
    'verified_dates' => $habitualVerifiedDates,
    'schedule_read_status' => 'partial',
    'cells' => $completeCells,
], $habitualDates);
$partialReadHtml = $renderWeek(array_replace($viewBase, ['habitualScheduleRows' => [[
    'worker_id' => 1010,
    'full_name' => 'Trabajador con lectura parcial',
    'verified_dates' => $habitualVerifiedDates,
    'has_versioned_membership' => true,
    'schedule_read_status' => 'partial',
    'cells' => $completeCells,
    'habitual_coverage' => $partialCoverage,
]] ]));
$assert(($partialCoverage['status'] ?? '') === 'unknown'
    && !str_contains($partialReadHtml, 'data-habitual-assignment-read-url=')
    && !str_contains($partialReadHtml, 'data-habitual-replacement-worker-id="1010"')
    && str_contains($partialReadHtml, 'worker_id=1010'),
    'a partial assignment read never supplies cached edit or replacement controls');
$assert(substr_count($authorizedHtml, '22:00–06:00 (+1 día)') >= 2
    && str_contains($authorizedHtml, 'value="1201"'),
    'confirmed night-shift hours remain in the matrix and selectable option with the next-day offset');
$templateAdminHtml = $renderWeek(array_replace($viewBase, [
    'canAssign' => false,
    'canEditSchedules' => false,
    'canManageTemplates' => true,
    'canManageGlobalTemplates' => true,
    'canManageStationTemplates' => false,
]));
$assert(str_contains($templateAdminHtml, 'checador/horarios-turnos')
    && !str_contains($templateAdminHtml, 'action="/departamento-operativo/recursos-humanos/checador/programacion-semanal/plantillas"')
    && !str_contains($templateAdminHtml, 'action="/departamento-operativo/recursos-humanos/checador/programacion-semanal/asignar"'),
    'a template administrator reaches M04 from M03 without a duplicate creation form');
$unauthorizedHtml = $renderWeek(array_replace($viewBase, ['canAssign' => false, 'canEditSchedules' => false]));
$assert(!str_contains($unauthorizedHtml, 'programacion-semanal/plantillas')
    && !str_contains($unauthorizedHtml, 'programacion-semanal/asignar')
    && !str_contains($unauthorizedHtml, 'data-bs-target="#temporary-schedule-assignment-modal"')
    && !str_contains($unauthorizedHtml, 'id="temporary-schedule-assignment-modal"'),
    'view fixture omits both mutation forms when edit authorization is absent');
$assert(!str_contains($unauthorizedHtml, 'Asignar horario habitual')
    && !str_contains($templateAdminHtml, 'Asignar horario habitual'),
    'habitual assignment UI is omitted for read-only and template-admin-only profiles');
$readOnlyHabitualHtml = $renderWeek(array_replace($viewBase, ['canAssign' => false, 'canEditSchedules' => false]));
$assert(!str_contains($readOnlyHabitualHtml, 'data-habitual-worker-id="1004"')
    && !str_contains($readOnlyHabitualHtml, 'ti ti-calendar-plus me-1')
    && str_contains($readOnlyHabitualHtml, 'ti ti-history me-1')
    && str_contains($readOnlyHabitualHtml, 'aria-label="Acciones de Trabajador QA de prueba"'),
    'read-only profile has history only and no habitual assignment action');

printf("CHECADOR_M03_SCHEDULE_CAPTURE: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

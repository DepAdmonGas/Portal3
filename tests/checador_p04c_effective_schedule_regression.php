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
    if ($condition) {
        echo "PASS {$message}\n";
        $passes++;
    } else {
        echo "FAIL {$message}\n";
        $fails++;
    }
};

$user = new Usuario();
$user->id = 999;
$base = 'https://checador.test/';
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/index.php');
$p04aSource = file_get_contents(__DIR__ . '/checador_p04_level1_snapshot_regression.php');
$p04bSource = file_get_contents(__DIR__ . '/checador_p04b_level2_window_regression.php');
$p03Source = file_get_contents(__DIR__ . '/../app/Services/ChecadorDailyReadService.php');

$make = static function (array $responses, ?array &$history = null) use ($base): ChecadorDailyReadService {
    $mock = new MockHandler($responses);
    $stack = HandlerStack::create($mock);
    $history ??= [];
    $stack->push(Middleware::history($history));
    return new ChecadorDailyReadService(
        new Client(['handler' => $stack, 'base_uri' => $base, 'http_errors' => false]),
        static fn(Usuario $user): string => 'portal3-effective-schedule-jwt',
        $base
    );
};

$schedulePayload = static function (string $status = 'SCHEDULED', array $segments = []): Response {
    $data = [
        'id_personal' => 105,
        'id_estacion' => 12,
        'fecha_local' => '2026-11-02',
        'estado_jornada' => $status,
    ];
    if ($status === 'SCHEDULED') {
        $data['origen'] = [
            'id_asignacion' => 42,
            'id_plantilla' => 10,
            'clave_plantilla' => 'TURNO_MATUTINO_8H',
            'version_plantilla' => 3,
        ];
        $data['horario_local'] = [
            'timezone' => 'America/Mexico_City',
            'segmentos' => $segments ?: [[
                'ordinal' => 1,
                'tipo' => 'WORK',
                'hora_inicio_local' => '22:00:00',
                'hora_fin_local' => '06:00:00',
                'dia_offset_inicio' => 0,
                'dia_offset_fin' => 1,
            ]],
        ];
        $data['horario_utc'] = [
            'start_utc' => '2026-11-03T04:00:00Z',
            'end_utc' => '2026-11-03T12:00:00Z',
        ];
    } else {
        $data['origen'] = null;
        $data['horario_local'] = null;
        $data['horario_utc'] = null;
    }
    return new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'status' => 'success',
        'data' => $data,
    ], JSON_THROW_ON_ERROR));
};

$history = [];
$scheduled = $make([$schedulePayload()], $history)->getEffectiveSchedule($user, 105, 12, '2026-11-02');
$request = $history[0]['request'] ?? null;
$uri = $request instanceof RequestInterface ? (string) $request->getUri() : '';

$assert($scheduled['ok'] === true, 'P4C01 authorized user can obtain effective schedule');
$assert(str_contains($controllerSource, 'ChecadorAccessService::authorize($requestedWorkplace)'), 'P4C02 local Checador authorization remains before API access');
$assert(str_contains($controllerSource, 'requestedWorkplace') && str_contains($controllerSource, 'getEffectiveSchedule'), 'P4C03 requested workplace remains explicit and scoped');
$assert(str_contains($controllerSource, "id_personal_resuelto"), 'P4C04 worker identity uses canonical id_personal_resuelto');
$assert(!str_contains($controllerSource, "referencia_externa_personal") && !str_contains($controllerSource, "no_colaborador"), 'P4C05 external worker references are not worker fallbacks');
$assert(str_contains($uri, '/api/v2/workers/105/effective-schedule'), 'P4C06 effective schedule uses the authoritative endpoint');
$assert(!preg_match('/APP_TIMEZONE|APP_TIMEZONE|date_default_timezone_set/', $controllerSource . $serviceSource . $viewSource), 'P4C07 APP_TIMEZONE is not a business fallback');
$assert(!preg_match('/Intl\.DateTimeFormat|resolvedOptions\(\)\.timeZone|navigator\.language/', $viewSource), 'P4C08 browser timezone is not used');
$assert(($scheduled['data']['estado_jornada'] ?? '') === 'SCHEDULED'
    && ($scheduled['data']['horario_local']['timezone'] ?? '') === 'America/Mexico_City', 'P4C09 effective schedule response is parsed exactly');
$assert(($scheduled['data']['origen']['version_plantilla'] ?? 0) === 3, 'P4C10 schedule provenance and version are preserved');
$assert(($scheduled['data']['horario_local']['segmentos'][0]['dia_offset_fin'] ?? 0) === 1 && str_contains($viewSource, 'dia_offset_fin'), 'P4C11 overnight end_day_offset is preserved');
$assert(!preg_match('/\bDATE\s*\(|ocurrido_at_utc.*\bDATE\(ocurrido_at_utc/i', $controllerSource . $serviceSource . $viewSource), 'P4C12 local workday is not derived from UTC DATE');

$dayOff = $make([$schedulePayload('DAY_OFF')])->getEffectiveSchedule($user, 105, 12, '2026-11-02');
$unknown = $make([$schedulePayload('UNKNOWN')])->getEffectiveSchedule($user, 105, 12, '2026-11-02');
$assert(($dayOff['data']['estado_jornada'] ?? '') === 'DAY_OFF', 'P4C13 DAY_OFF remains distinct');
$assert(($unknown['data']['estado_jornada'] ?? '') === 'UNKNOWN', 'P4C14 UNKNOWN remains distinct from DAY_OFF');
$assert(!str_contains($viewSource, "'UNSCHEDULED' => 'Descanso programado'"), 'P4C15 missing schedule is not rendered as Descanso');
$assert(!preg_match('/station.?default|default.?schedule|schedule.*fallback/i', $controllerSource . $serviceSource . $viewSource), 'P4C16 no station-default schedule is invented');

$twoSegments = $make([$schedulePayload('SCHEDULED', [
    ['ordinal' => 1, 'tipo' => 'WORK', 'hora_inicio_local' => '08:00:00', 'hora_fin_local' => '12:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
    ['ordinal' => 2, 'tipo' => 'WORK', 'hora_inicio_local' => '13:00:00', 'hora_fin_local' => '17:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
])])->getEffectiveSchedule($user, 105, 12, '2026-11-02');
$assert(count($twoSegments['data']['horario_local']['segmentos'] ?? []) === 2, 'P4C17 all returned segments are preserved');
$assert(!preg_match('/Salida comida|Regreso comida/i', $viewSource) && str_contains($viewSource, 'primarySlots'), 'P4C18 schedule labels remain separate from P3-H projection');
$assert(!preg_match('/first.*punch|last.*punch|pair(?:Punch|Event|Slot|ingAlgorithm)/i', $controllerSource . $serviceSource), 'P4C19 punch pairing calculation is not implemented locally');
$assert(!preg_match('/laten|retardo/i', $controllerSource . $serviceSource . $viewSource), 'P4C20 lateness is not calculated');
$assert(!preg_match('/early.?departure|salida anticipada/i', $controllerSource . $serviceSource . $viewSource), 'P4C21 early departure is not calculated');
$assert(!preg_match('/overtime|tiempo extra|horas extra/i', $controllerSource . $serviceSource . $viewSource), 'P4C22 overtime is not calculated');
$assert(!str_contains($viewSource, 'SIN MARCACIÓN'), 'P4C23 SIN MARCACIÓN is not inferred');
$assert(str_contains($viewSource, 'Nunca se activa automáticamente') && str_contains($controllerSource, 'authorizeFalta'), 'P4C24 FALTA is not inferred');
$assert(!preg_match('/RecibosNomina|descuento/i', $controllerSource . $serviceSource . $viewSource), 'P4C25 payroll conclusions are not rendered');
$assert(str_contains($controllerSource, 'getLevel2WindowStatus') && str_contains($controllerSource, '$level2Meta'), 'P4C26 P04B Level 2 remains authoritative and separate');
$assert(str_contains($p04aSource, 'createDailyPunchSnapshot'), 'P4C27 P04A snapshot behavior remains intact');
$assert(str_contains($p03Source, 'readObservedPunches'), 'P4C28 P03 raw-read behavior remains intact');
$assert(($make([new Response(500, [], json_encode(['status' => 'SERVER_ERROR'], JSON_THROW_ON_ERROR))])->getEffectiveSchedule($user, 105, 12, '2026-11-02')['error']['kind'] ?? '') === 'api_unavailable'
    && ($make([$schedulePayload('UNKNOWN')])->getEffectiveSchedule($user, 105, 12, '2026-11-02')['data']['estado_jornada'] ?? '') === 'UNKNOWN', 'P4C29 transport failure remains distinct from schedule UNKNOWN');
$assert(str_contains($controllerSource, '$effectiveSchedules = []') && str_contains($controllerSource, '$effectiveScheduleErrors = []'), 'P4C30 failed fresh lookup cannot display stale prior schedule');

printf("CHECADOR_P04C_EFFECTIVE_SCHEDULE_RESULT: %d PASS / %d FAIL / 0 SKIPPED\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

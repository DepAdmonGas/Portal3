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

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$keys = ['APP_ENV', 'CHECADOR_API_REAL_POSTS_ENABLED', 'CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED', 'CHECADOR_ADVANCED_DEMO_ENABLED'];
$previous = [];
foreach ($keys as $key) $previous[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
$set = static function (string $key, ?string $value): void {
    if ($value === null) { unset($_ENV[$key]); putenv($key); return; }
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
};
try {
    $set('APP_ENV', 'dev');
    $set('CHECADOR_API_REAL_POSTS_ENABLED', null);
    $set('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED', 'true');
    $set('CHECADOR_ADVANCED_DEMO_ENABLED', 'false');

    $segments = [
        ['ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '14:00:00', 'hora_fin_local' => '18:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
        ['ordinal' => 2, 'tipo_segmento' => 'BREAK', 'hora_inicio_local' => '18:00:00', 'hora_fin_local' => '19:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
        ['ordinal' => 3, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '19:00:00', 'hora_fin_local' => '22:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
    ];
    $postSegments = [];
    foreach ($segments as $i => $segment) $postSegments[] = ['id' => 800 + $i] + $segment;
    $postResponse = ['status' => 'success', 'data' => [
        'id' => 16, 'clave' => 'LOC_1_T_4_14002200', 'version' => 2, 'nombre' => 'Turno QA versionado',
        'tipo_turno' => 'REGULAR', 'scope' => 'STATION', 'id_estacion' => 1, 'es_activo' => 1, 'segmentos' => $postSegments,
    ]];
    $detailResponse = ['status' => 'success', 'data' => $postResponse['data']];
    $catalogResponse = ['status' => 'success', 'data' => ['page' => 1, 'per_page' => 200, 'items' => [[
        'id' => 16, 'clave' => 'LOC_1_T_4_14002200', 'version' => 2, 'nombre' => 'Turno QA versionado',
        'tipo_turno' => 'REGULAR', 'scope' => 'STATION', 'id_estacion' => 1, 'es_activo' => 1,
    ]]]];
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(201, ['Content-Type' => 'application/json'], json_encode($postResponse)),
        new Response(200, ['Content-Type' => 'application/json'], json_encode($detailResponse)),
        new Response(200, ['Content-Type' => 'application/json'], json_encode($catalogResponse)),
    ]));
    $stack->push(Middleware::history($history));
    $reader = new ChecadorDailyReadService(new Client(['handler' => $stack, 'base_uri' => 'http://api.test/', 'http_errors' => false]),
        static fn(Usuario $user): string => 'mock-jwt', 'http://api.test/');
    $user = new Usuario();
    $user->id = 33;
    $result = $reader->createShiftTemplateVersion($user, 'LOC_1_T_4_14002200', 1, [
        'nombre' => 'Turno QA versionado', 'tipo_turno' => 'REGULAR', 'segmentos' => $segments,
    ], 1);
    $assert(($result['ok'] ?? false) === true && (int) ($result['data']['version'] ?? 0) === 2
        && ($result['data']['scope'] ?? '') === 'STATION' && (int) ($result['data']['id_estacion'] ?? 0) === 1,
        'version writer ignores API-generated segment IDs, validates detail, and confirms latest station-scoped catalog readback');
    $assert(count($history) === 3 && $history[0]['request']->getMethod() === 'POST'
        && $history[0]['request']->getUri()->getPath() === '/api/v2/shift-templates/LOC_1_T_4_14002200/versions'
        && $history[1]['request']->getMethod() === 'GET' && $history[2]['request']->getMethod() === 'GET',
        'versioned edit uses the canonical POST followed by immutable-version detail and scoped catalog GET readbacks');
    $posted = json_decode((string) $history[0]['request']->getBody(), true);
    $assert(($posted['scope'] ?? '') === 'STATION' && (int) ($posted['id_estacion'] ?? 0) === 1
        && !isset($posted['actor_id']) && !isset($posted['id_usuario_actor'])
        && $history[0]['request']->getHeaderLine('Idempotency-Key') === '',
        'payload retains station scope while API JWT remains actor authority and no undocumented idempotency field is added');
} finally {
    foreach ($keys as $key) {
        [$wasSet, $envValue, $processValue] = $previous[$key];
        if ($wasSet) $_ENV[$key] = $envValue; else unset($_ENV[$key]);
        if ($processValue === false) putenv($key); else putenv($key . '=' . $processValue);
    }
}

printf("CHECADOR_M04_VERSIONED_LOCAL_TEMPLATE: %d PASS / %d FAIL\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

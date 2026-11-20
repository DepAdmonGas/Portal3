<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorWeeklyScheduleSqlReadService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
Capsule::schema()->create('op_rh_v2_turnos_plantillas', static function (Blueprint $table): void {
    $table->increments('id'); $table->string('clave'); $table->unsignedInteger('version');
    $table->string('nombre'); $table->string('tipo_turno'); $table->string('scope');
    $table->integer('id_estacion')->nullable(); $table->integer('es_activo');
});
Capsule::schema()->create('op_rh_v2_turnos_segmentos', static function (Blueprint $table): void {
    $table->increments('id'); $table->unsignedInteger('id_plantilla'); $table->unsignedInteger('ordinal');
    $table->string('tipo_segmento'); $table->time('hora_inicio_local'); $table->time('hora_fin_local');
    $table->integer('dia_offset_inicio'); $table->integer('dia_offset_fin');
});
Capsule::schema()->create('op_rh_v2_turnos_asignaciones', static function (Blueprint $table): void {
    $table->increments('id'); $table->integer('id_personal'); $table->integer('id_estacion');
    $table->unsignedInteger('id_plantilla')->nullable(); $table->string('tipo_asignacion');
    $table->string('dia_semana'); $table->date('valido_desde'); $table->date('valido_hasta')->nullable();
    $table->integer('es_activo'); $table->dateTime('registrado_at_utc')->nullable(); $table->string('motivo')->nullable();
});
Capsule::schema()->create('op_rh_v2_shift_template_station_lineage', static function (Blueprint $table): void {
    $table->increments('id'); $table->integer('id_estacion');
    $table->unsignedInteger('source_global_template_id'); $table->string('source_global_clave');
    $table->unsignedInteger('source_global_version'); $table->unsignedInteger('local_template_id');
    $table->string('local_clave');
});
Capsule::schema()->create('op_rh_v2_localidades_zonas_horarias', static function (Blueprint $table): void {
    $table->increments('id'); $table->integer('id_localidad');
    $table->date('valido_desde'); $table->date('valido_hasta')->nullable(); $table->string('timezone_iana', 64);
});

Capsule::table('op_rh_v2_turnos_plantillas')->insert([
    ['id' => 1, 'clave' => 'GLOBAL', 'version' => 1, 'nombre' => 'Global', 'tipo_turno' => 'REGULAR', 'scope' => 'GLOBAL', 'id_estacion' => null, 'es_activo' => 1],
    ['id' => 2, 'clave' => 'LOCAL2', 'version' => 1, 'nombre' => 'Palo Solo', 'tipo_turno' => 'REGULAR', 'scope' => 'STATION', 'id_estacion' => 2, 'es_activo' => 1],
    ['id' => 3, 'clave' => 'DEPT9', 'version' => 1, 'nombre' => 'Autolavado', 'tipo_turno' => 'NOCTURNO', 'scope' => 'STATION', 'id_estacion' => 9, 'es_activo' => 1],
    ['id' => 4, 'clave' => 'DEPT9', 'version' => 2, 'nombre' => 'Autolavado v2', 'tipo_turno' => 'NOCTURNO', 'scope' => 'STATION', 'id_estacion' => 9, 'es_activo' => 1],
]);
Capsule::table('op_rh_v2_turnos_segmentos')->insert([
    ['id_plantilla' => 1, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00', 'hora_fin_local' => '17:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
    ['id_plantilla' => 2, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00', 'hora_fin_local' => '17:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
    ['id_plantilla' => 3, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '22:00', 'hora_fin_local' => '06:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 1],
    ['id_plantilla' => 4, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '21:00', 'hora_fin_local' => '06:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 1],
]);
Capsule::table('op_rh_v2_turnos_asignaciones')->insert([
    ['id' => 10, 'id_personal' => 50, 'id_estacion' => 9, 'id_plantilla' => 3, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'LUNES', 'valido_desde' => '2026-11-16', 'valido_hasta' => null, 'es_activo' => 1, 'motivo' => null],
    ['id' => 11, 'id_personal' => 50, 'id_estacion' => 2, 'id_plantilla' => 2, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'LUNES', 'valido_desde' => '2026-11-16', 'valido_hasta' => null, 'es_activo' => 1, 'motivo' => null],
    ['id' => 12, 'id_personal' => 50, 'id_estacion' => 9, 'id_plantilla' => null, 'tipo_asignacion' => 'HABITUAL', 'dia_semana' => 'LUNES', 'valido_desde' => '2026-10-05', 'valido_hasta' => '2026-10-12', 'es_activo' => 0, 'motivo' => 'Descanso histórico'],
]);
foreach ([1, 2, 9] as $localityId) {
    Capsule::table('op_rh_v2_localidades_zonas_horarias')->insert([
        'id_localidad' => $localityId, 'valido_desde' => '2020-01-01', 'valido_hasta' => null,
        'timezone_iana' => 'America/Mexico_City',
    ]);
}

$user = new Usuario(); $user->id = 9;
$reader = new ChecadorWeeklyScheduleSqlReadService(static fn(Usuario $actor, int $workplace): bool => $actor->id === 9 && $workplace === 9);
$data = $reader->readWeek($user, 9, '2026-11-16', [['worker_id' => 50]]);
$templates = $data['data']['templates'] ?? [];
$assignments = $data['data']['assignments_by_worker'][50] ?? [];
$assert(($data['ok'] ?? false) && count($templates) === 1 && ($templates[0]['id'] ?? 0) === 4,
    'Autolavado catalog is station-scoped and selects the latest active version, without global templates');
$assert(count($assignments) === 1 && ($assignments[0]['id_estacion'] ?? 0) === 9,
    'M03 assignment rows are filtered by the selected canonical locality; Palo Solo row is excluded');
$assert(($data['data']['templates_by_id'][3]['segmentos'][0]['dia_offset_fin'] ?? null) === 1,
    'historical referenced template keeps its persisted overnight version and segments');
$assert($reader->timezoneForDate(9, '2026-11-16') === 'America/Mexico_City',
    'M03 timezone is resolved from the existing versioned V2 locality timezone table');
$denied = (new ChecadorWeeklyScheduleSqlReadService(static fn(Usuario $actor, int $workplace): bool => false))
    ->readWeek($user, 2, '2026-11-16', [['worker_id' => 50]]);
$assert(($denied['error']['kind'] ?? '') === 'permission_denied', 'out-of-scope locality is denied before querying the schedule');
$history = $reader->readAssignmentHistory($user, 9, 50, 1, 50, '2026-11-16');
$historyItems = $history['data']['items'] ?? [];
$dateFacts = $history['data']['date_assignment_facts'] ?? [];
$assert(($history['ok'] ?? false) && count($historyItems) === 2
    && array_column($historyItems, 'id') === [10, 12]
    && count($dateFacts) === 1 && ($dateFacts[0]['id'] ?? 0) === 10
    && array_key_exists('id_plantilla', $historyItems[1]) && $historyItems[1]['id_plantilla'] === null,
    'SQL history retains closed and explicit-rest facts, while the selected-date projection excludes another locality');
$historyPage = $reader->readAssignmentHistory($user, 9, 50, 1, 1, '2026-11-16');
$historyNext = $reader->readAssignmentHistory($user, 9, 50, 2, 1, '2026-11-16');
$assert(($historyPage['data']['has_next'] ?? false) === true
    && ($historyPage['data']['items'][0]['id'] ?? 0) === 10
    && ($historyNext['data']['items'][0]['id'] ?? 0) === 12,
    'SQL assignment history is deterministically paginated with a next-page indicator');
$deniedHistory = (new ChecadorWeeklyScheduleSqlReadService(static fn(Usuario $actor, int $workplace): bool => false))
    ->readAssignmentHistory($user, 2, 50, 1, 50, '2026-11-16');
$assert(($deniedHistory['error']['kind'] ?? '') === 'permission_denied', 'SQL assignment history denies a locality outside the server authorization');
$source = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php');
$renderStart = strpos($source, 'private function renderScheduleWeek(');
$renderEnd = strpos($source, '/** Read the seven effective weekday occurrences from canonical SQL memberships.', (int) $renderStart);
$render = substr($source, (int) $renderStart, (int) $renderEnd - (int) $renderStart);
$assert(str_contains($render, 'ChecadorWeeklyScheduleSqlReadService')
    && !str_contains($render, 'getShiftAssignments(') && !str_contains($render, 'getShiftTemplates(')
    && !str_contains($render, 'resolveWorkplaceLocalDay('), 'weekly screen reads M03 facts through SQL with no API fallback');
$historyStart = strpos($source, 'private function renderScheduleHistory(');
$historyEnd = strpos($source, "    /** Read-only MVP report of raw V2 punch events. */", (int) $historyStart);
$historyController = substr($source, (int) $historyStart, (int) $historyEnd - (int) $historyStart);
$assert(str_contains($historyController, 'readAssignmentHistory(')
    && !str_contains($historyController, 'getEffectiveSchedule(')
    && !str_contains($historyController, 'getShiftAssignments('),
    'individual M03 history reads persisted SQL assignment facts without HTTP calls or Portal3 schedule resolution');
$rosterStart = strpos($source, 'private static function scheduleHistoryWorkerRoster(');
$rosterEnd = strpos($source, 'private static function', (int) $rosterStart + 1);
$historyRoster = substr($source, (int) $rosterStart, $rosterEnd === false ? null : $rosterEnd - (int) $rosterStart);
$assert(str_contains($historyRoster, 'ChecadorWeeklyScheduleSqlReadService')
    && !str_contains($historyRoster, 'resolveWorkplaceLocalDay('),
    'individual M03 worker scope and timezone confirmation use canonical SQL without the API timezone endpoint');

echo "RESULT: {$passes} PASS / {$fails} FAIL (SQLite isolated regression; not a live MariaDB verification)\n";
exit($fails === 0 ? 0 : 1);

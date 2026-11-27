<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\ChecadorAttendanceRosterService;
use App\Services\ChecadorWeeklyScheduleService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = '0';
$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();

Capsule::schema()->create('op_rh_localidades', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('localidad');
});
Capsule::schema()->create('tb_estaciones', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->integer('numlista')->nullable();
    $table->string('nombre');
    $table->integer('estatus')->default(0);
});
Capsule::schema()->create('tb_modulos_config', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('modulo_key');
    $table->string('tipo');
    $table->text('departamentos_soportados')->nullable();
    $table->string('tipo_departamento')->nullable();
    $table->boolean('allow_all')->default(false);
    $table->boolean('activo')->default(true);
});
Capsule::schema()->create('op_rh_personal', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion')->nullable();
    $table->string('nombre_completo');
    $table->integer('no_colaborador')->nullable();
    $table->integer('estado')->default(1);
});
Capsule::schema()->create('op_rh_personal_localidad_version', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('worker_id');
});

Capsule::table('op_rh_localidades')->insert([
    ['id' => 1, 'numlista' => 100, 'localidad' => 'Interlomas'],
    ['id' => 2, 'numlista' => 200, 'localidad' => 'Palo Solo'],
    ['id' => 9, 'numlista' => null, 'localidad' => 'Autolavado'],
]);
Capsule::table('tb_estaciones')->insert([
    ['id' => 1, 'numlista' => 999, 'nombre' => 'Estación de ID homónimo', 'estatus' => 0],
    ['id' => 111, 'numlista' => 100, 'nombre' => 'Interlomas física', 'estatus' => 0],
    ['id' => 2, 'numlista' => 998, 'nombre' => 'Estación de ID homónimo', 'estatus' => 0],
    ['id' => 222, 'numlista' => 200, 'nombre' => 'Palo Solo física', 'estatus' => 0],
]);
Capsule::table('tb_modulos_config')->insert([
    'modulo_key' => 'checador',
    'tipo' => 'stations_and_departments',
    'departamentos_soportados' => '[9]',
    'tipo_departamento' => 'localidades',
    'allow_all' => 0,
    'activo' => 1,
]);
Capsule::table('op_rh_personal')->insert([
    ['id' => 101, 'id_estacion' => 111, 'nombre_completo' => 'I válido', 'no_colaborador' => 101, 'estado' => 1],
    ['id' => 102, 'id_estacion' => 1, 'nombre_completo' => 'I ID coincidente ajeno', 'no_colaborador' => 102, 'estado' => 1],
    ['id' => 201, 'id_estacion' => 222, 'nombre_completo' => 'P válido', 'no_colaborador' => 201, 'estado' => 1],
    ['id' => 202, 'id_estacion' => 2, 'nombre_completo' => 'P ID coincidente ajeno', 'no_colaborador' => 202, 'estado' => 1],
    ['id' => 901, 'id_estacion' => 9, 'nombre_completo' => 'A explícito', 'no_colaborador' => 901, 'estado' => 1],
    ['id' => 902, 'id_estacion' => 222, 'nombre_completo' => 'Palo Solo no heredado', 'no_colaborador' => 902, 'estado' => 1],
]);

$timezone = 'America/Mexico_City';
$today = (new DateTimeImmutable('now', new DateTimeZone($timezone)))->format('Y-m-d');
$monday = ChecadorWeeklyScheduleService::monday($today) ?? '';
$service = new ChecadorAttendanceRosterService();
$idsFor = static function (array $result): array {
    return array_values(array_map(static fn(array $worker): int => (int) $worker['worker_id'], $result['workers'] ?? []));
};
$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$interlomas = $service->workersForScheduleWeek(1, $monday, $timezone, false);
$interlomasIds = $idsFor($interlomas);
$assert(in_array(101, $interlomasIds, true) && !in_array(102, $interlomasIds, true),
    'Interlomas locality 1 includes physical station 111 workers and excludes a coincidental id_estacion 1');

$paloSolo = $service->workersForScheduleWeek(2, $monday, $timezone, false);
$paloIds = $idsFor($paloSolo);
$assert(in_array(201, $paloIds, true) && !in_array(202, $paloIds, true),
    'Palo Solo locality 2 includes physical station 222 workers and excludes a coincidental id_estacion 2');

$autolavado = $service->workersForScheduleWeek(9, $monday, $timezone, false);
$autolavadoIds = $idsFor($autolavado);
$assert(in_array(901, $autolavadoIds, true) && !in_array(902, $autolavadoIds, true),
    'Autolavado department 9 uses its own explicit source and excludes Palo Solo workers');

$assert(($interlomas['coverage'] ?? '') === 'incomplete'
    && ($paloSolo['coverage'] ?? '') === 'incomplete'
    && ($autolavado['coverage'] ?? '') === 'incomplete',
    'unversioned current roster remains reference-only for the rest of the week');

printf("SCHEDULE_WEEK_PHYSICAL_ROSTER: %d PASS / %d FAIL (isolated SQLite fixture)\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

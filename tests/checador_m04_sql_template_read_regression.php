<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorShiftTemplateSqlReadService;
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
    $table->increments('id');
    $table->string('clave', 64);
    $table->unsignedInteger('version');
    $table->string('nombre', 128);
    $table->string('tipo_turno', 32);
    $table->string('scope', 16);
    $table->integer('id_estacion')->nullable();
    $table->integer('es_activo');
});
Capsule::schema()->create('op_rh_v2_turnos_segmentos', static function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('id_plantilla');
    $table->unsignedInteger('ordinal');
    $table->string('tipo_segmento', 32);
    $table->time('hora_inicio_local');
    $table->time('hora_fin_local');
    $table->integer('dia_offset_inicio');
    $table->integer('dia_offset_fin');
});
Capsule::schema()->create('op_rh_v2_shift_template_station_lineage', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->string('source_global_clave', 100);
    $table->unsignedInteger('source_global_version');
    $table->unsignedInteger('source_global_template_id');
    $table->string('local_clave', 100);
    $table->unsignedInteger('local_template_id');
    $table->integer('created_by_user_id');
    $table->dateTime('created_at_utc');
});

Capsule::table('op_rh_v2_turnos_plantillas')->insert([
    ['id' => 1, 'clave' => 'GLOBAL_DAY', 'version' => 1, 'nombre' => 'Global día v1', 'tipo_turno' => 'REGULAR', 'scope' => 'GLOBAL', 'id_estacion' => null, 'es_activo' => 1],
    ['id' => 2, 'clave' => 'GLOBAL_DAY', 'version' => 2, 'nombre' => 'Global día v2', 'tipo_turno' => 'REGULAR', 'scope' => 'GLOBAL', 'id_estacion' => null, 'es_activo' => 1],
    ['id' => 3, 'clave' => 'GLOBAL_OTHER', 'version' => 1, 'nombre' => 'Global otro', 'tipo_turno' => 'REGULAR', 'scope' => 'GLOBAL', 'id_estacion' => null, 'es_activo' => 1],
    ['id' => 4, 'clave' => 'LOCAL_ONE', 'version' => 1, 'nombre' => 'Local v1', 'tipo_turno' => 'NOCTURNO', 'scope' => 'STATION', 'id_estacion' => 1, 'es_activo' => 1],
    ['id' => 5, 'clave' => 'LOCAL_ONE', 'version' => 2, 'nombre' => 'Local v2', 'tipo_turno' => 'NOCTURNO', 'scope' => 'STATION', 'id_estacion' => 1, 'es_activo' => 1],
    ['id' => 6, 'clave' => 'OUTSIDE', 'version' => 1, 'nombre' => 'Otra localidad', 'tipo_turno' => 'REGULAR', 'scope' => 'STATION', 'id_estacion' => 2, 'es_activo' => 1],
    ['id' => 7, 'clave' => 'AUTOLAVADO_SHARED', 'version' => 1, 'nombre' => 'Autolavado', 'tipo_turno' => 'REGULAR', 'scope' => 'STATION', 'id_estacion' => 2, 'es_activo' => 1],
]);
Capsule::table('op_rh_v2_turnos_segmentos')->insert([
    ['id_plantilla' => 2, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '22:00:00', 'hora_fin_local' => '06:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 1],
    ['id_plantilla' => 5, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '21:00:00', 'hora_fin_local' => '06:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 1],
    ['id_plantilla' => 7, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00:00', 'hora_fin_local' => '17:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
]);
Capsule::table('op_rh_v2_shift_template_station_lineage')->insert([
    'id_estacion' => 1,
    'source_global_clave' => 'GLOBAL_DAY',
    'source_global_version' => 2,
    'source_global_template_id' => 2,
    'local_clave' => 'LOCAL_ONE',
    'local_template_id' => 5,
    'created_by_user_id' => 90,
    'created_at_utc' => '2026-11-04 12:00:00',
]);

$user = new Usuario();
$user->id = 90;
$reader = new ChecadorShiftTemplateSqlReadService(static fn(Usuario $actor, int $workplaceId): bool => $actor->id === 90 && $workplaceId === 1);
$catalog = $reader->getShiftTemplates($user, 1, 1, 200);
$items = $catalog['data']['items'] ?? [];
$byKey = [];
foreach ($items as $item) $byKey[$item['clave']] = $item;
$assert(($catalog['ok'] ?? false) && count($items) === 3 && !isset($byKey['OUTSIDE']),
    'catalog returns latest global and locality 1 templates while excluding locality 2');
$assert(($byKey['GLOBAL_DAY']['version'] ?? 0) === 2 && ($byKey['GLOBAL_DAY']['station_customization']['clave'] ?? '') === 'LOCAL_ONE',
    'global version selection and station customization lineage are preserved');
$assert(($byKey['LOCAL_ONE']['version'] ?? 0) === 2 && ($byKey['LOCAL_ONE']['derived_from_global']['version'] ?? 0) === 2,
    'station template version and source lineage are preserved');

$localDetail = $reader->getShiftTemplateVersion(
    $user, 'LOCAL_ONE', 2, 1, (int) $byKey['LOCAL_ONE']['id'], 'STATION', 1
);
$assert(($localDetail['ok'] ?? false) && ($localDetail['data']['segmentos'][0]['dia_offset_fin'] ?? null) === 1,
    'detail returns ordered segments and the next-day offset for an overnight template');
$wrongScopeDetail = $reader->getShiftTemplateVersion(
    $user, 'LOCAL_ONE', 2, 1, (int) $byKey['LOCAL_ONE']['id'], 'STATION', 2
);
$assert(($wrongScopeDetail['error']['kind'] ?? '') === 'bad_request',
    'detail refuses a template identity outside the selected locality');
$denied = $reader->getShiftTemplates($user, 2, 1, 200);
$assert(($denied['error']['kind'] ?? '') === 'permission_denied' && ($denied['error']['status'] ?? 0) === 403,
    'server-side locality authorization denial is distinct from an empty catalog');
$autolavadoReader = new ChecadorShiftTemplateSqlReadService(static fn(Usuario $actor, int $workplaceId): array =>
    $actor->id === 90 && $workplaceId === 9
        ? ['allowed' => true, 'catalog_workplace_id' => 2]
        : ['allowed' => false]);
$autolavadoCatalog = $autolavadoReader->getShiftTemplates($user, 9, 1, 200);
$autolavadoTemplate = null;
foreach ($autolavadoCatalog['data']['items'] ?? [] as $item) {
    if (($item['clave'] ?? '') === 'AUTOLAVADO_SHARED') $autolavadoTemplate = $item;
}
$autolavadoDetail = $autolavadoTemplate === null ? [] : $autolavadoReader->getShiftTemplateVersion(
    $user, 'AUTOLAVADO_SHARED', 1, 9, 7, 'STATION', 2
);
$assert(($autolavadoCatalog['ok'] ?? false) && $autolavadoTemplate !== null
    && ($autolavadoDetail['data']['segmentos'][0]['hora_inicio_local'] ?? '') === '08:00:00',
    'explicit locality 9 authorization can read its established station 2 catalog alias and persisted segments');
$crossLocalityDetail = $autolavadoReader->getShiftTemplateVersion($user, 'LOCAL_ONE', 2, 9, 5, 'STATION', 1);
$assert(($crossLocalityDetail['error']['kind'] ?? '') === 'bad_request',
    'department catalog alias cannot read a template attached to another station');

Capsule::table('op_rh_v2_shift_template_station_lineage')->delete();
Capsule::table('op_rh_v2_turnos_segmentos')->delete();
Capsule::table('op_rh_v2_turnos_plantillas')->delete();
$empty = $reader->getShiftTemplates($user, 1, 1, 200);
$assert(($empty['ok'] ?? false) && ($empty['data']['items'] ?? null) === [],
    'valid empty result remains successful and distinct from SQL failure');

Capsule::table('op_rh_v2_turnos_plantillas')->insert([
    'id' => 20, 'clave' => 'SQL_FAILURE_PROBE', 'version' => 1, 'nombre' => 'No mostrar',
    'tipo_turno' => 'REGULAR', 'scope' => 'GLOBAL', 'id_estacion' => null, 'es_activo' => 1,
]);
Capsule::schema()->drop('op_rh_v2_shift_template_station_lineage');
$sqlFailure = $reader->getShiftTemplates($user, 1, 1, 200);
$assert(($sqlFailure['error']['kind'] ?? '') === 'database_unavailable' && ($sqlFailure['error']['status'] ?? 0) === 503,
    'missing lineage table produces an explicit database error instead of empty catalog');

$serviceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorShiftTemplateSqlReadService.php') ?: '';
$assert(!str_contains($serviceSource, 'scope_id') && str_contains($serviceSource, 'authorizeShiftTemplateCatalogRead'),
    'SQL reader uses no scope_id and enforces explicit M04 locality authorization');
$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-history.php') ?: '';
$templatesJs = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js') ?: '';
$assert(str_contains($controllerSource, 'new ChecadorShiftTemplateSqlReadService()')
    && str_contains($controllerSource, 'getShiftTemplates($currentUser, $workplaceId, 1, 200)')
    && str_contains($controllerSource, "\$detailError === 'database_unavailable' ? 'database_error'")
    && str_contains($controllerSource, "\$catalog['error']['kind'] ?? '') === 'permission_denied'")
    && str_contains($viewSource, "\$catalogStatus === 'permission_denied'")
    && str_contains($viewSource, "\$catalogStatus === 'database_error'")
    && str_contains($viewSource, "\$catalogStatus === 'render_error'")
    && str_contains($viewSource, "\$catalogStatus === 'received'")
    && !str_contains($viewSource, 'No fue posible consultar los horarios. Intenta actualizar la página.'),
    'M04 catalog route uses SQL and the view separates SQL errors, authorization denial and an empty result');
$assert(str_contains($controllerSource, 'getShiftTemplateVersion(')
    && str_contains($controllerSource, "['segmentos'] ?? null) !== \$segments")
    && str_contains($templatesJs, 'this.table.row.add(node)')
    && !str_contains($templatesJs, 'this.table.row.add(row)')
    && str_contains($templatesJs, 'Array.isArray(confirmed.segmentos) ? [...confirmed.segmentos] : null'),
    'write success requires SQL segment readback and DataTables receives a DOM row without array fallback');

echo "\n{$passes} PASS, {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorAdjustmentSqlReadService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $label) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passes++ : $fails++;
};

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = Capsule::schema('default');
$schema->create('op_rh_v2_ajustes_administrativos', static function (Blueprint $table): void {
    $table->bigIncrements('id'); $table->string('uuid_ajuste'); $table->string('uuid_ajuste_logico');
    $table->unsignedInteger('revision_numero'); $table->string('estado_ajuste'); $table->unsignedBigInteger('id_ajuste_anterior')->nullable();
    $table->unsignedBigInteger('id_jornada_programada'); $table->unsignedBigInteger('id_calculo_jornada'); $table->string('tipo_ajuste');
    $table->dateTime('hora_interpretada_entrada_utc')->nullable(); $table->dateTime('hora_interpretada_salida_utc')->nullable();
    $table->text('motivo_ajuste'); $table->integer('creado_por_usuario_id'); $table->integer('aprobado_por_usuario_id'); $table->dateTime('creado_at_utc');
});
$schema->create('op_rh_v2_jornadas_programadas', static function (Blueprint $table): void {
    $table->bigIncrements('id'); $table->integer('id_personal'); $table->integer('id_estacion'); $table->date('fecha_jornada_local'); $table->string('timezone_local');
});
$schema->create('op_rh_v2_calculos_jornada', static function (Blueprint $table): void {
    $table->bigIncrements('id'); $table->unsignedBigInteger('id_jornada_programada'); $table->integer('id_personal'); $table->integer('id_estacion'); $table->date('fecha_jornada_local');
});
$schema->create('op_rh_personal', static function (Blueprint $table): void { $table->increments('id'); $table->text('nombre_completo'); $table->string('no_colaborador')->nullable(); });
$schema->create('op_rh_localidades', static function (Blueprint $table): void { $table->increments('id'); $table->text('localidad'); });
$schema->create('op_rh_v2_evaluaciones_administrativas', static function (Blueprint $table): void {
    $table->bigIncrements('id'); $table->unsignedBigInteger('id_jornada_programada'); $table->unsignedBigInteger('id_calculo_jornada');
    $table->integer('id_personal'); $table->integer('id_estacion'); $table->date('fecha_jornada_local'); $table->unsignedInteger('revision_numero');
    $table->string('estado_evaluacion'); $table->string('estado_revision_humana'); $table->dateTime('creado_at_utc');
});
$schema->create('op_rh_v2_evaluaciones_ajustes', static function (Blueprint $table): void {
    $table->unsignedBigInteger('id_evaluacion_administrativa'); $table->unsignedBigInteger('id_ajuste_administrativo'); $table->dateTime('creado_at_utc');
});
Capsule::table('op_rh_personal')->insert(['id' => 23, 'nombre_completo' => 'Trabajadora de ajuste', 'no_colaborador' => 'EMP-23']);
Capsule::table('op_rh_localidades')->insert(['id' => 1, 'localidad' => 'Interlomas']);
Capsule::table('op_rh_v2_jornadas_programadas')->insert(['id' => 41, 'id_personal' => 23, 'id_estacion' => 1, 'fecha_jornada_local' => '2026-11-19', 'timezone_local' => 'America/Mexico_City']);
Capsule::table('op_rh_v2_calculos_jornada')->insert(['id' => 51, 'id_jornada_programada' => 41, 'id_personal' => 23, 'id_estacion' => 1, 'fecha_jornada_local' => '2026-11-19']);
Capsule::table('op_rh_v2_ajustes_administrativos')->insert([
    ['id' => 61, 'uuid_ajuste' => 'adj-rev-1', 'uuid_ajuste_logico' => 'adj-logical', 'revision_numero' => 1, 'estado_ajuste' => 'SUPERSEDED', 'id_ajuste_anterior' => null, 'id_jornada_programada' => 41, 'id_calculo_jornada' => 51, 'tipo_ajuste' => 'OVERRIDE_STATUS', 'hora_interpretada_entrada_utc' => null, 'hora_interpretada_salida_utc' => null, 'motivo_ajuste' => 'Motivo inicial', 'creado_por_usuario_id' => 7, 'aprobado_por_usuario_id' => 8, 'creado_at_utc' => '2026-11-19 17:00:00'],
    ['id' => 62, 'uuid_ajuste' => 'adj-rev-2', 'uuid_ajuste_logico' => 'adj-logical', 'revision_numero' => 2, 'estado_ajuste' => 'ACTIVE', 'id_ajuste_anterior' => 61, 'id_jornada_programada' => 41, 'id_calculo_jornada' => 51, 'tipo_ajuste' => 'OVERRIDE_STATUS', 'hora_interpretada_entrada_utc' => null, 'hora_interpretada_salida_utc' => null, 'motivo_ajuste' => 'Motivo vigente', 'creado_por_usuario_id' => 7, 'aprobado_por_usuario_id' => 8, 'creado_at_utc' => '2026-11-19 18:00:00'],
]);
Capsule::table('op_rh_v2_evaluaciones_administrativas')->insert(['id' => 71, 'id_jornada_programada' => 41, 'id_calculo_jornada' => 51, 'id_personal' => 23, 'id_estacion' => 1, 'fecha_jornada_local' => '2026-11-19', 'revision_numero' => 1, 'estado_evaluacion' => 'REVIEW_REQUIRED', 'estado_revision_humana' => 'PENDING', 'creado_at_utc' => '2026-11-19 19:00:00']);
Capsule::table('op_rh_v2_evaluaciones_ajustes')->insert(['id_evaluacion_administrativa' => 71, 'id_ajuste_administrativo' => 62, 'creado_at_utc' => '2026-11-19 19:00:00']);
$actor = new Usuario(); $actor->id = 7;
$resolver = static fn(int $workerId, string $effectiveAt, string $recordedAsOf): array => [
    'status' => 'RESOLVED_SINGLE', 'assignments' => [['workplace_id' => 1]],
];
$service = new ChecadorAdjustmentSqlReadService(static fn(Usuario $user, int $workplace): bool => $user->id === 7 && $workplace === 1, $resolver);
$read = $service->listForWorkplace($actor, 1, ['start_date' => '2026-11-19', 'end_date' => '2026-11-20'], 1, 25);
$assert(($read['ok'] ?? false) === true && count($read['data']['items'] ?? []) === 1, 'authorized SQL list returns a persisted logical adjustment in the selected date window');
$group = $read['data']['items'][0] ?? [];
$assert(($group['current']['uuid'] ?? '') === 'adj-rev-2' && ($group['current']['reason'] ?? '') === 'Motivo vigente'
    && count($group['revisions'] ?? []) === 2, 'current revision and complete immutable history are projected from persisted rows');
$assert(($group['current']['worker_id'] ?? 0) === 23 && ($group['current']['workplace_id'] ?? 0) === 1
    && ($group['current']['worker_name'] ?? '') === 'Trabajadora de ajuste', 'identity and locality are derived through matching Journey and calculation joins');
$assert(count($group['current']['evaluation_links'] ?? []) === 1
    && ($group['current']['evaluation_links'][0]['status'] ?? '') === 'REVIEW_REQUIRED', 'only evaluation links with matching journey, calculation, worker, locality, and date are exposed');
$otherService = new ChecadorAdjustmentSqlReadService(static fn(Usuario $user, int $workplace): bool => false, $resolver);
$denied = $otherService->listForWorkplace($actor, 1, ['start_date' => '2026-11-19', 'end_date' => '2026-11-19']);
$assert(($denied['error'] ?? '') === 'permission_denied', 'locality authorization is enforced before querying adjustment records');
$empty = $service->listForWorkplace($actor, 1, ['start_date' => '2026-11-21', 'end_date' => '2026-11-21']);
$assert(($empty['ok'] ?? false) === true && ($empty['data']['pagination']['total_records'] ?? -1) === 0, 'valid empty query is distinct from SQL failure');
$invalid = $service->listForWorkplace($actor, 1, ['start_date' => '2026-11-01', 'end_date' => '2026-12-05']);
$assert(($invalid['error'] ?? '') === 'invalid_filter', 'date window above 31 local dates is rejected');
$broken = ChecadorAdjustmentSqlReadService::selectCurrentRevision([
    ['id' => 1, 'revision' => 1, 'status' => 'SUPERSEDED', 'previous_id' => null],
    ['id' => 3, 'revision' => 2, 'status' => 'ACTIVE', 'previous_id' => 2],
]);
$assert(($broken['status'] ?? '') === 'invalid', 'broken predecessor lineage is not silently selected as a current revision');
$cancelledPredecessor = ChecadorAdjustmentSqlReadService::selectCurrentRevision([
    ['id' => 10, 'uuid' => 'adj-cancelled-1', 'logical_uuid' => 'adj-cancelled-chain', 'revision' => 1, 'status' => 'CANCELLED', 'previous_id' => null],
    ['id' => 11, 'uuid' => 'adj-cancelled-2', 'logical_uuid' => 'adj-cancelled-chain', 'revision' => 2, 'status' => 'ACTIVE', 'previous_id' => 10],
]);
$assert(($cancelledPredecessor['status'] ?? '') === 'ready', 'a persisted cancelled predecessor may lead to a later linked adjustment revision as allowed by the DDL comment');

$source = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorAdjustmentSqlReadService.php') ?: '';
$controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '';
$view = file_get_contents(dirname(__DIR__) . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-adjustments.php') ?: '';
$js = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.adjustments.init.js') ?: '';
$assert(!preg_match('/\b(?:insert|update|delete|upsert|statement|unprepared)\s*\(/i', $source)
    && !preg_match('/Guzzle|curl_|api\/v2|Http::/i', $source), 'new adjustments service contains SELECT-only SQL and no API HTTP dependency');
$assert(str_contains($controller, 'new ChecadorAdjustmentSqlReadService()')
    && str_contains($controller, 'ChecadorAccessService::authorize($workplaceId)')
    && str_contains($controller, 'in_array($workplaceId, $authorizedIds, true)'), 'controller selects only a server-authorized canonical locality');
$assert(str_contains($view, 'No hay ajustes registrados para los filtros seleccionados.')
    && str_contains($view, 'Ver detalle') && str_contains($view, 'Ver historial')
    && !str_contains($view, 'Autorizar FALTA') && str_contains($view, 'bg-primary text-white'), 'read-only view has institutional empty state and detail/history without write actions');
$rendered = (static function (array $vars): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    include dirname(__DIR__) . '/app/Views/departamento-operativo/2-recursos-humanos/checador/workspace-adjustments.php';
    return (string) ob_get_clean();
})([
    'adjustmentState' => 'ready', 'adjustmentItems' => [$group], 'adjustmentFilters' => [],
    'adjustmentPagination' => ['page' => 1, 'total_pages' => 1],
    'adjustmentTypes' => ['OVERRIDE_STATUS' => 'Ajuste de estado'],
    'adjustmentStatuses' => ['ACTIVE' => 'Activo'], 'selectedWorkplaceId' => 1,
    'selectedWorkplaceName' => 'Interlomas',
]);
$tablePosition = strpos($rendered, '</table>');
$modalPosition = strpos($rendered, 'id="m07-adjustment-62"');
$assert($tablePosition !== false && $modalPosition !== false && $modalPosition > $tablePosition
    && str_contains($rendered, 'Motivo vigente') && str_contains($rendered, 'Historial (2)')
    && str_contains($rendered, 'adj-rev-2') && str_contains($rendered, 'Evaluaciones relacionadas'),
    'fixture rendering places complete detail/history modals outside the DataTable and shows persisted references');
$assert(str_contains($rendered, 'data-adjustment-tab="history"')
    && str_contains($rendered, 'm07-adjustment-62-history-tab')
    && str_contains($rendered, 'btn bg-danger-subtle text-danger'), 'history action opens its Bootstrap tab and modal close follows the Portal3 style');
$assert(str_contains($view, 'data-m07-worker-select') && str_contains($js, 'dropdownParent: $(modal)')
    && str_contains($js, 'ModuleStationSelector'), 'authorized worker Select2 and institutional station selector are wired to the modal and shared context');

echo "RESULT {$passes} PASS / {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorShiftTemplateSqlWriteService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'default');
$capsule->setAsGlobal();
$capsule->getDatabaseManager()->setDefaultConnection('default');
$capsule->bootEloquent();
$schema = Capsule::schema();
$schema->create('op_rh_localidades', static fn(Blueprint $table) => $table->increments('id'));
$schema->create('op_rh_v2_turnos_plantillas', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('clave', 64);
    $table->unsignedInteger('version');
    $table->string('nombre', 128);
    $table->string('tipo_turno', 32);
    $table->string('scope', 16);
    $table->integer('id_estacion')->nullable();
    $table->integer('scope_id')->nullable();
    $table->boolean('es_activo')->default(true);
    $table->integer('creado_por_usuario_id')->nullable();
    $table->dateTime('creado_at')->nullable();
    $table->dateTime('actualizado_at')->nullable();
    $table->unique(['scope_id', 'clave', 'version']);
});
$schema->create('op_rh_v2_turnos_segmentos', static function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('id_plantilla');
    $table->unsignedInteger('ordinal');
    $table->string('tipo_segmento', 32);
    $table->time('hora_inicio_local');
    $table->time('hora_fin_local');
    $table->integer('dia_offset_inicio')->default(0);
    $table->integer('dia_offset_fin')->default(0);
    $table->unique(['id_plantilla', 'ordinal']);
});
$schema->create('op_rh_v2_shift_template_station_lineage', static function (Blueprint $table): void {
    $table->increments('id');
    $table->integer('id_estacion');
    $table->string('source_global_clave', 100);
    $table->unsignedInteger('source_global_version');
    $table->unsignedInteger('source_global_template_id');
    $table->string('local_clave', 100);
    $table->unsignedInteger('local_template_id');
    $table->integer('created_by_user_id');
    $table->dateTime('created_at_utc');
    $table->unique(['id_estacion', 'source_global_clave']);
    $table->unique(['id_estacion', 'local_clave']);
    $table->unique(['local_template_id']);
});
$schema->create('op_rh_v2_turnos_audit_log', static function (Blueprint $table): void {
    $table->increments('id');
    $table->string('entity_type', 32);
    $table->unsignedInteger('entity_id');
    $table->string('operacion', 32);
    $table->integer('id_usuario_actor');
    $table->integer('id_estacion');
    $table->integer('id_personal')->nullable();
    $table->string('tipo_asignacion', 32)->nullable();
    $table->date('valido_desde')->nullable();
    $table->date('valido_hasta')->nullable();
    $table->unsignedInteger('id_plantilla')->nullable();
    $table->unsignedInteger('version_plantilla')->nullable();
    $table->text('detalle_json')->nullable();
    $table->dateTime('recorded_at_utc');
});
Capsule::table('op_rh_localidades')->insert([['id' => 1], ['id' => 2], ['id' => 6], ['id' => 9]]);
Capsule::table('op_rh_v2_turnos_plantillas')->insert([
    'id' => 900, 'clave' => 'GLOBAL_SOURCE', 'version' => 1, 'nombre' => 'Global fuente', 'tipo_turno' => 'REGULAR',
    'scope' => 'GLOBAL', 'id_estacion' => null, 'scope_id' => 0, 'es_activo' => 1,
]);
Capsule::table('op_rh_v2_turnos_segmentos')->insert([
    'id_plantilla' => 900, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00:00',
    'hora_fin_local' => '16:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0,
]);
$user = new Usuario();
$user->id = 90;
$readback = static function (Usuario $actor, string $key, int $version, int $workplaceId, int $id): array {
    $template = Capsule::table('op_rh_v2_turnos_plantillas')->where('id', $id)->first();
    if ($template === null) return ['ok' => false];
    $segments = Capsule::table('op_rh_v2_turnos_segmentos')->where('id_plantilla', $id)->orderBy('ordinal')->get()
        ->map(static fn($row): array => [
            'ordinal' => (int) $row->ordinal, 'tipo_segmento' => (string) $row->tipo_segmento,
            'hora_inicio_local' => (string) $row->hora_inicio_local, 'hora_fin_local' => (string) $row->hora_fin_local,
            'dia_offset_inicio' => (int) $row->dia_offset_inicio, 'dia_offset_fin' => (int) $row->dia_offset_fin,
        ])->all();
    return ['ok' => true, 'data' => [
        'id' => (int) $template->id, 'clave' => (string) $template->clave, 'version' => (int) $template->version,
        'nombre' => (string) $template->nombre, 'tipo_turno' => (string) $template->tipo_turno,
        'scope' => (string) $template->scope, 'id_estacion' => (int) $template->id_estacion,
        'es_activo' => (int) $template->es_activo, 'segmentos' => $segments,
    ]];
};
$writer = new ChecadorShiftTemplateSqlWriteService(
    static fn(Usuario $actor, int $workplaceId): bool => $actor->id === 90 && in_array($workplaceId, [1, 6, 9], true),
    $readback
);
$base = [
    'operation' => 'create', 'confirm_action' => '1', 'template_name' => 'Turno local mañana', 'template_type' => 'REGULAR',
    'idempotency_key' => 'm04-create-normal-0001', 'segments' => [[
        'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00', 'hora_fin_local' => '17:00',
        'dia_offset_inicio' => 0, 'dia_offset_fin' => 0,
    ]],
];
$created = $writer->write($user, 1, $base);
$assert(($created['ok'] ?? false) && count($created['template']['segmentos'] ?? []) === 1,
    'create writes a WORK segment and confirms its persisted SQL readback');
$id = (int) ($created['template']['id'] ?? 0);
$assert(Capsule::table('op_rh_v2_turnos_audit_log')->where('entity_id', $id)->where('operacion', 'CREATE')->exists(),
    'template creation includes its mandatory audit row');
$replay = $writer->write($user, 1, $base);
$assert(($replay['ok'] ?? false) && ($replay['replayed'] ?? false) && (int) $replay['template']['id'] === $id,
    'same idempotency key and payload returns the existing persisted template');
$changedRetry = $base;
$changedRetry['template_name'] = 'Nombre distinto';
$assert(($writer->write($user, 1, $changedRetry)['error'] ?? '') === 'idempotency_conflict',
    'same idempotency key with a changed payload is rejected');
$duplicate = $base;
$duplicate['idempotency_key'] = 'm04-create-duplicate-0001';
$assert(($writer->write($user, 1, $duplicate)['error'] ?? '') === 'duplicate_template_name',
    'duplicate active template names are rejected');

$versionInput = $base;
$versionInput['operation'] = 'version';
$versionInput['template_key'] = $created['template']['clave'];
$versionInput['expected_version'] = 1;
$versionInput['template_name'] = 'Turno local actualizado';
$versionInput['idempotency_key'] = 'm04-version-template-0001';
$versioned = $writer->write($user, 1, $versionInput);
$assert(($versioned['ok'] ?? false) && (int) $versioned['template']['version'] === 2
    && Capsule::table('op_rh_v2_turnos_plantillas')->where('id', $id)->where('version', 1)->value('nombre') === 'Turno local mañana',
    'edit appends an immutable version and preserves the original row');
$stale = $versionInput;
$stale['idempotency_key'] = 'm04-version-stale-00001';
$assert(($writer->write($user, 1, $stale)['error'] ?? '') === 'version_conflict',
    'stale expected versions conflict without another version');

$overnight = $base;
$overnight['template_name'] = 'Turno nocturno descanso';
$overnight['template_type'] = 'NOCTURNO';
$overnight['idempotency_key'] = 'm04-create-overnight-001';
$overnight['segments'] = [
    ['tipo_segmento' => 'WORK', 'hora_inicio_local' => '22:00', 'hora_fin_local' => '02:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 1],
    ['tipo_segmento' => 'BREAK', 'hora_inicio_local' => '02:00', 'hora_fin_local' => '02:30', 'dia_offset_inicio' => 1, 'dia_offset_fin' => 1],
    ['tipo_segmento' => 'WORK', 'hora_inicio_local' => '02:30', 'hora_fin_local' => '06:00', 'dia_offset_inicio' => 1, 'dia_offset_fin' => 1],
];
$night = $writer->write($user, 1, $overnight);
$assert(($night['ok'] ?? false) && ($night['template']['segmentos'][0]['dia_offset_fin'] ?? null) === 1
    && ($night['template']['segmentos'][1]['tipo_segmento'] ?? '') === 'BREAK',
    'night shift and explicit break offsets are persisted and read back');

$customize = [
    'operation' => 'customize', 'template_name' => 'Global local personalizado', 'template_type' => 'REGULAR',
    'source_template_key' => 'GLOBAL_SOURCE', 'source_template_version' => 1, 'idempotency_key' => 'm04-customize-source-0001',
    'segments' => $base['segments'],
];
$customized = $writer->write($user, 1, $customize);
$assert(($customized['ok'] ?? false) && Capsule::table('op_rh_v2_shift_template_station_lineage')
    ->where('id_estacion', 1)->where('source_global_clave', 'GLOBAL_SOURCE')->where('local_template_id', $customized['template']['id'] ?? 0)->exists(),
    'global customization creates a local template and immutable source lineage');
$assert(($writer->write($user, 1, $customize)['replayed'] ?? false) === true,
    'customization retry replays the same lineage-linked local template');

$autolavadoCreate = $base;
$autolavadoCreate['template_name'] = 'Autolavado turno de prueba';
$autolavadoCreate['idempotency_key'] = 'm04-autolavado-create-001';
$autolavado = $writer->write($user, 9, $autolavadoCreate);
$autolavadoId = (int) ($autolavado['template']['id'] ?? 0);
$assert(($autolavado['ok'] ?? false)
    && (int) ($autolavado['template']['id_estacion'] ?? 0) === 9
    && count($autolavado['template']['segmentos'] ?? []) === 1
    && Capsule::table('op_rh_v2_turnos_audit_log')->where('entity_id', $autolavadoId)->where('id_estacion', 9)->exists()
    && !Capsule::table('op_rh_v2_turnos_plantillas')->where('id', $autolavadoId)->where('id_estacion', 2)->exists()
    && !Capsule::table('op_rh_v2_turnos_plantillas')->where('id', $autolavadoId)->where('id_estacion', 11)->exists(),
    'Autolavado create persists template, segments, and audit only under canonical locality 9');
$autolavadoVersionInput = $autolavadoCreate;
$autolavadoVersionInput['operation'] = 'version';
$autolavadoVersionInput['template_key'] = (string) ($autolavado['template']['clave'] ?? '');
$autolavadoVersionInput['expected_version'] = 1;
$autolavadoVersionInput['template_name'] = 'Autolavado turno de prueba v2';
$autolavadoVersionInput['idempotency_key'] = 'm04-autolavado-version-001';
$autolavadoVersioned = $writer->write($user, 9, $autolavadoVersionInput);
$assert(($autolavadoVersioned['ok'] ?? false)
    && (int) ($autolavadoVersioned['template']['version'] ?? 0) === 2
    && (int) ($autolavadoVersioned['template']['id_estacion'] ?? 0) === 9
    && Capsule::table('op_rh_v2_turnos_plantillas')->where('id', $autolavadoId)->where('version', 1)->where('id_estacion', 9)->exists()
    && Capsule::table('op_rh_v2_turnos_audit_log')->where('entity_id', $autolavadoVersioned['template']['id'] ?? 0)->where('id_estacion', 9)->where('operacion', 'VERSION')->exists(),
    'Autolavado edit appends a version with persisted readback and audit at locality 9');
$autolavadoCustomize = $customize;
$autolavadoCustomize['template_name'] = 'Autolavado global personalizado';
$autolavadoCustomize['idempotency_key'] = 'm04-autolavado-customize-001';
$autolavadoCustomized = $writer->write($user, 9, $autolavadoCustomize);
$assert(($autolavadoCustomized['ok'] ?? false)
    && (int) ($autolavadoCustomized['template']['id_estacion'] ?? 0) === 9
    && Capsule::table('op_rh_v2_shift_template_station_lineage')->where('id_estacion', 9)
        ->where('local_template_id', $autolavadoCustomized['template']['id'] ?? 0)->exists()
    && Capsule::table('op_rh_v2_turnos_audit_log')->where('entity_id', $autolavadoCustomized['template']['id'] ?? 0)->where('id_estacion', 9)->where('operacion', 'CUSTOMIZE')->exists()
    && Capsule::table('op_rh_v2_turnos_plantillas')->where('id', 900)->where('scope', 'GLOBAL')->where('nombre', 'Global fuente')->exists(),
    'Autolavado customization stores local template, lineage, and audit at 9 while preserving the GLOBAL source');
$esmegasCreate = $base;
$esmegasCreate['template_name'] = 'Esmegas turno local';
$esmegasCreate['idempotency_key'] = 'm04-esmegas-create-0001';
$esmegas = $writer->write($user, 6, $esmegasCreate);
$esmegasId = (int) ($esmegas['template']['id'] ?? 0);
$assert(($esmegas['ok'] ?? false)
    && (int) ($esmegas['template']['id_estacion'] ?? 0) === 6
    && count($esmegas['template']['segmentos'] ?? []) === 1
    && Capsule::table('op_rh_v2_turnos_audit_log')->where('entity_id', $esmegasId)->where('id_estacion', 6)->exists()
    && !Capsule::table('op_rh_v2_turnos_plantillas')->where('id', $esmegasId)->whereIn('id_estacion', [2, 9])->exists(),
    'Esmegas create and persisted readback remain at canonical locality 6, separate from Palo Solo and Autolavado');
$paloWriter = new ChecadorShiftTemplateSqlWriteService(
    static fn(Usuario $actor, int $workplaceId): bool => $actor->id === 90 && $workplaceId === 2,
    $readback
);
$paloCreate = $base;
$paloCreate['template_name'] = 'Palo Solo turno local';
$paloCreate['idempotency_key'] = 'm04-palo-solo-create-0001';
$palo = $paloWriter->write($user, 2, $paloCreate);
$paloId = (int) ($palo['template']['id'] ?? 0);
$assert(($palo['ok'] ?? false)
    && (int) ($palo['template']['id_estacion'] ?? 0) === 2
    && Capsule::table('op_rh_v2_turnos_audit_log')->where('entity_id', $paloId)->where('id_estacion', 2)->exists()
    && !Capsule::table('op_rh_v2_turnos_plantillas')->where('id', $paloId)->whereIn('id_estacion', [6, 9])->exists(),
    'Palo Solo create and persisted readback remain at canonical locality 2, separate from Esmegas and Autolavado');
$assert(($writer->write($user, 2, $base)['error'] ?? '') === 'permission_denied',
    'A writer authorized for Esmegas and Autolavado cannot write Palo Solo without its own effective locality permission');
$invalid = $base;
$invalid['idempotency_key'] = 'm04-invalid-empty-segments';
$invalid['segments'] = [];
$assert(($writer->write($user, 1, $invalid)['error'] ?? '') === 'invalid_input',
    'missing WORK segments are rejected without success');

$before = Capsule::table('op_rh_v2_turnos_plantillas')->count();
Capsule::connection()->statement("CREATE TRIGGER fail_m04_audit BEFORE INSERT ON op_rh_v2_turnos_audit_log BEGIN SELECT RAISE(ABORT, 'audit denied'); END");
$auditFailure = $base;
$auditFailure['template_name'] = 'Debe revertirse';
$auditFailure['idempotency_key'] = 'm04-audit-rollback-0001';
$rollback = $writer->write($user, 1, $auditFailure);
$after = Capsule::table('op_rh_v2_turnos_plantillas')->count();
$assert(!($rollback['ok'] ?? false) && $before === $after,
    'audit insert failure rolls template and segment inserts back atomically');

$controllerSource = file_get_contents(__DIR__ . '/../app/Controllers/ChecadorController.php') ?: '';
$controllerM04 = substr($controllerSource, strpos($controllerSource, 'public function saveConfigurationScheduleTemplate'),
    strpos($controllerSource, 'private static function normalizeOriginalScheduleTemplateSegments') - strpos($controllerSource, 'public function saveConfigurationScheduleTemplate'));
$saveMethodStart = strpos($controllerSource, 'private function saveScheduleTemplateJson(');
$saveMethodEnd = strpos($controllerSource, 'private function ', ($saveMethodStart ?: 0) + 1);
$saveM04 = $saveMethodStart === false ? '' : substr($controllerSource, $saveMethodStart, $saveMethodEnd === false ? null : $saveMethodEnd - $saveMethodStart);
$readServiceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorShiftTemplateSqlReadService.php') ?: '';
$writeServiceSource = file_get_contents(__DIR__ . '/../app/Services/ChecadorShiftTemplateSqlWriteService.php') ?: '';
$jsSource = file_get_contents(__DIR__ . '/../public/assets/js/departamento-operativo/2-recursos-humanos/checador.schedule-history.templates.init.js') ?: '';
$viewSource = file_get_contents(__DIR__ . '/../app/Views/departamento-operativo/2-recursos-humanos/checador/schedule-history.php') ?: '';
$renderMethodStart = strpos($controllerSource, 'private function renderScheduleHistory(');
$renderMethodEnd = strpos($controllerSource, 'private function ', ($renderMethodStart ?: 0) + 1);
$renderM04 = $renderMethodStart === false ? '' : substr($controllerSource, $renderMethodStart, $renderMethodEnd === false ? null : $renderMethodEnd - $renderMethodStart);
$assert(str_contains($controllerM04, 'ChecadorShiftTemplateSqlWriteService')
    && str_contains($controllerM04, 'ChecadorShiftTemplateSqlReadService')
    && str_contains($controllerM04, 'CsrfToken::validate($formCsrf)')
    && str_contains($controllerM04, 'CsrfToken::validate($csrf)')
    && str_contains($controllerM04, 'authorizeStationTemplateAdministration($workplaceId)')
    && str_contains($controllerM04, 'requestM04TemplateWorkplaceId($input)')
    && str_contains($controllerM04, '$rawWorkplace !== $workplaceId')
    && str_contains($controllerM04, "\$writtenTemplate['id_estacion'] ?? 0) !== \$workplaceId")
    && str_contains($controllerSource, 'checador.schedule-history.templates.init.js?v=20261119-1')
    && !str_contains($controllerM04, 'createStationShiftTemplate(')
    && !str_contains($controllerM04, 'customizeGlobalShiftTemplate(')
    && !str_contains($controllerM04, 'createStationShiftTemplateVersion(')
    && str_contains($readServiceSource, "Capsule::connection(\$this->connectionName)")
    && str_contains($jsSource, 'idempotencyKeyFor(')
    && str_contains($viewSource, 'data-workplace-name=')
    && str_contains($jsSource, 'this.rootElement?.dataset.workplaceName')
    && str_contains($jsSource, 'window.location.reload();')
    && !str_contains($jsSource, 'confirmado en Autolavado'),
    'M04 validates the selected locality end-to-end and reloads its persisted catalog after a confirmed write');
$assert(str_contains($writeServiceSource, 'Auth::user()')
    && str_contains($writeServiceSource, 'authorizeStationTemplateAdministration($workplaceId)')
    && str_contains($writeServiceSource, 'if (!($this->authorizeWrite)($actor, $workplaceId))'),
    'M04 writer rechecks authenticated identity and effective station permission server-side');
$assert(str_contains($renderM04, '$canManageStationTemplates = $workplaceId !== null && (bool) ($templateAdminAccess[\'allowed\'] ?? false)')
    && str_contains($renderM04, '$templateWritesEnabled = $canManageStationTemplates && !$demoMode')
    && substr_count($renderM04, "'templateWritesEnabled'") === 1
    && str_contains($viewSource, 'data-edit-enabled="<?= !empty($canManageStationTemplates) ? \'1\' : \'0\' ?>"')
    && str_contains($viewSource, 'data-write-enabled="<?= !empty($templateWritesEnabled) ? \'1\' : \'0\' ?>"')
    && str_contains($viewSource, ':disabled="loading || !writeEnabled"')
    && str_contains($viewSource, ':disabled="editLoading || !writeEnabled"')
    && str_contains($controllerSource, "\$templateWriteBlockMessage = \$demoMode ? 'El modo demostración es de consulta.' : ''")
    && !str_contains($viewSource, 'las verificaciones de corte exclusivo y preflight SQL de M04')
    && str_contains($jsSource, 'this.writeBlockMessage'),
    'authorized Encargados can write without technical gates; only the existing demo view remains read-only');
$authPosition = strpos($saveM04, 'authorizeStationTemplateAdministration($workplaceId)');
$assert($authPosition !== false
    && !str_contains($saveM04, 'writeGateOpen()')
    && !str_contains($saveM04, 'isOperationallyReady()')
    && !str_contains($saveM04, 'CHECADOR_M04_')
    && !str_contains($saveM04, 'm04_cutover_not_ready')
    && !str_contains($saveM04, 'sql_preflight_not_ready'),
    'M04 POST keeps server-side locality authorization and no longer requires environment, API-cut, or SQL-attestation gates');
$assert(str_contains($writeServiceSource, 'Capsule::connection(self::CONNECTION_NAME)')
    && str_contains($writeServiceSource, '$connection->transaction(')
    && str_contains($writeServiceSource, 'findIdempotentResult(')
    && str_contains($writeServiceSource, 'readExactWithinTransaction(')
    && !str_contains($writeServiceSource, 'writeGateOpen')
    && !str_contains($writeServiceSource, 'isOperationallyReady')
    && !str_contains($writeServiceSource, 'mariadbSchemaReady')
    && !str_contains($writeServiceSource, 'hasRequiredM04Grants'),
    'M04 uses default SQL transactions, durable idempotency and readback; real SQL failures are handled without manual gates');
echo "\n{$passes} PASS, {$fails} FAIL\n";
exit($fails === 0 ? 0 : 1);

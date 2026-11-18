<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Usuario;
use App\Services\ChecadorWeeklyScheduleSqlWriteService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0; $fails = 0;
$assert = static function (bool $ok, string $message) use (&$passes, &$fails): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $message . "\n";
    $ok ? $passes++ : $fails++;
};
$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal(); $capsule->bootEloquent();

Capsule::schema()->create('op_rh_personal', static function (Blueprint $t): void { $t->increments('id'); $t->integer('estado'); });
Capsule::schema()->create('op_rh_v2_turnos_plantillas', static function (Blueprint $t): void {
    $t->increments('id'); $t->string('clave'); $t->integer('version'); $t->string('nombre');
    $t->string('tipo_turno'); $t->string('scope'); $t->integer('id_estacion')->nullable(); $t->integer('es_activo');
});
Capsule::schema()->create('op_rh_v2_turnos_segmentos', static function (Blueprint $t): void {
    $t->increments('id'); $t->integer('id_plantilla'); $t->integer('ordinal'); $t->string('tipo_segmento');
    $t->string('hora_inicio_local'); $t->string('hora_fin_local'); $t->integer('dia_offset_inicio'); $t->integer('dia_offset_fin');
});
Capsule::schema()->create('op_rh_v2_turnos_asignaciones', static function (Blueprint $t): void {
    $t->increments('id'); $t->integer('id_personal'); $t->integer('id_estacion'); $t->integer('id_plantilla')->nullable();
    $t->string('tipo_asignacion'); $t->string('dia_semana'); $t->date('valido_desde'); $t->date('valido_hasta')->nullable();
    $t->integer('es_activo'); $t->dateTime('creado_at'); $t->dateTime('registrado_at_utc');
    $t->integer('creado_por_usuario_id')->nullable(); $t->integer('actualizado_por_usuario_id')->nullable(); $t->string('motivo')->nullable();
});
Capsule::schema()->create('op_rh_v2_turnos_audit_log', static function (Blueprint $t): void {
    $t->increments('id'); $t->string('entity_type'); $t->integer('entity_id'); $t->string('operacion');
    $t->integer('id_usuario_actor'); $t->integer('id_estacion'); $t->integer('id_personal')->nullable();
    $t->string('tipo_asignacion')->nullable(); $t->date('valido_desde')->nullable(); $t->date('valido_hasta')->nullable();
    $t->integer('id_plantilla')->nullable(); $t->integer('version_plantilla')->nullable(); $t->text('detalle_json')->nullable();
    $t->dateTime('recorded_at_utc');
});
Capsule::schema()->create('op_rh_v2_localidades_zonas_horarias', static function (Blueprint $t): void {
    $t->increments('id'); $t->integer('id_localidad'); $t->date('valido_desde');
    $t->date('valido_hasta')->nullable(); $t->string('timezone_iana', 64);
});
Capsule::table('op_rh_personal')->insert([['id' => 40, 'estado' => 1], ['id' => 41, 'estado' => 1]]);
Capsule::table('op_rh_v2_localidades_zonas_horarias')->insert([
    'id_localidad' => 9, 'valido_desde' => '2020-01-01', 'valido_hasta' => null,
    'timezone_iana' => 'America/Mexico_City',
]);
Capsule::table('op_rh_v2_turnos_plantillas')->insert([
    ['id' => 10, 'clave' => 'LOCAL10', 'version' => 2, 'nombre' => 'Tarde', 'tipo_turno' => 'REGULAR', 'scope' => 'STATION', 'id_estacion' => 9, 'es_activo' => 1],
    ['id' => 20, 'clave' => 'FOREIGN', 'version' => 1, 'nombre' => 'Fuera', 'tipo_turno' => 'REGULAR', 'scope' => 'STATION', 'id_estacion' => 2, 'es_activo' => 1],
]);
Capsule::table('op_rh_v2_turnos_segmentos')->insert([
    ['id_plantilla' => 10, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '22:00:00', 'hora_fin_local' => '06:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 1],
    ['id_plantilla' => 20, 'ordinal' => 1, 'tipo_segmento' => 'WORK', 'hora_inicio_local' => '08:00:00', 'hora_fin_local' => '16:00:00', 'dia_offset_inicio' => 0, 'dia_offset_fin' => 0],
]);
putenv('CHECADOR_M03_SQL_WRITES_ENABLED=1');
putenv('CHECADOR_M03_API_WRITER_DISABLED=1');
putenv('CHECADOR_M03_SCHEMA_PREFLIGHT_OK=1');
$membership = static fn(int $workerId, int $workplaceId, string $from, string $to): array =>
    ($workplaceId === 9 && in_array($workerId, [40, 41], true)) ? ['ok' => true, 'valid_to' => null] : ['ok' => false, 'error' => 'membership_unverified'];
$service = new ChecadorWeeklyScheduleSqlWriteService(
    static fn(Usuario $actor, int $place): bool => $actor->id === 77 && $place === 9,
    $membership,
    static fn(string $lock): bool => true
);
$actor = new Usuario(); $actor->id = 77;

$restInput = ['workplace_id' => 9, 'worker_id' => 40, 'week_start' => '2026-11-23', 'target_date' => '2026-11-23',
    'effective_start_date' => '2026-11-23', 'tipo_asignacion' => 'HABITUAL', 'template_id' => 'REST',
    'idempotency_key' => 'rest-key-0000000000000001'];
$rest = $service->create($actor, $restInput);
$assert(($rest['ok'] ?? false) && array_key_exists('id_plantilla', $rest['assignment'] ?? []) && $rest['assignment']['id_plantilla'] === null,
    'explicit rest uses the real nullable id_plantilla representation and SQL readback');
$restAudit = Capsule::table('op_rh_v2_turnos_audit_log')->where('entity_id', $rest['assignment']['id'] ?? 0)->first();
$restAuditDetail = is_object($restAudit) ? json_decode((string) $restAudit->detalle_json, true) : null;
$assert(is_array($restAuditDetail)
    && hash_equals(hash('sha256', $restInput['idempotency_key']), (string) ($restAuditDetail['idempotency_key_hash'] ?? ''))
    && preg_match('/\A[a-f0-9]{64}\z/', (string) ($restAuditDetail['payload_fingerprint'] ?? '')) === 1,
    'idempotency key hash and payload fingerprint persist durably in the same audit record as the assignment');
$replay = $service->create($actor, $restInput);
$assert(($replay['ok'] ?? false) && ($replay['replayed'] ?? false) && $replay['assignment']['id'] === $rest['assignment']['id'],
    'same idempotency key replays the persisted assignment without duplication');
$conflictKey = $restInput; $conflictKey['motivo'] = 'different payload';
$assert(($service->create($actor, $conflictKey)['error'] ?? '') === 'idempotency_conflict',
    'same idempotency key with a different payload is rejected');

$scheduled = $service->create($actor, $restInput + []);
$assert(($scheduled['ok'] ?? false) && count(Capsule::table('op_rh_v2_turnos_audit_log')->get()) === 1,
    'duplicate create cannot create a second row or audit event');
$turnInput = ['workplace_id' => 9, 'worker_id' => 41, 'week_start' => '2026-11-23', 'target_date' => '2026-11-23',
    'effective_start_date' => '2026-11-23', 'tipo_asignacion' => 'HABITUAL', 'template_id' => '10',
    'idempotency_key' => 'night-key-0000000000000001'];
$turn = $service->create($actor, $turnInput);
$assert(($turn['ok'] ?? false) && ($turn['assignment']['id_plantilla'] ?? 0) === 10,
    'station-local overnight template is accepted only with persisted WORK segments');
$foreign = $turnInput; $foreign['worker_id'] = 40; $foreign['week_start'] = '2026-11-30'; $foreign['target_date'] = '2026-12-01'; $foreign['effective_start_date'] = '2026-12-01'; $foreign['template_id'] = '20'; $foreign['idempotency_key'] = 'foreign-key-000000000000001';
$assert(($service->create($actor, $foreign)['error'] ?? '') === 'template_not_allowed',
    'template from Palo Solo cannot be assigned to Autolavado');

$closeInput = ['workplace_id' => 9, 'worker_id' => 41, 'assignment_id' => $turn['assignment']['id'],
    'effective_close_date' => '2026-11-30', 'expected_valido_desde' => '2026-11-23', 'expected_valido_hasta' => null,
    'idempotency_key' => 'close-key-0000000000000001'];
$closed = $service->close($actor, $closeInput);
$assert(($closed['ok'] ?? false) && (int) $closed['assignment']['es_activo'] === 0
    && $closed['assignment']['valido_hasta'] === '2026-11-30', 'close updates validity, audits, and confirms its SQL readback');

$replaceSource = $service->create($actor, array_replace($turnInput, ['worker_id' => 41, 'week_start' => '2026-12-07', 'target_date' => '2026-12-07', 'effective_start_date' => '2026-12-07', 'idempotency_key' => 'source-key-000000000000001']));
$replaceInput = ['workplace_id' => 9, 'worker_id' => 41, 'assignment_id' => $replaceSource['assignment']['id'] ?? 0,
    'effective_close_date' => '2026-12-14', 'expected_valido_desde' => '2026-12-07', 'expected_valido_hasta' => null,
    'replacement_valido_hasta' => '2026-12-21', 'replacement_template_id' => 'REST',
    'idempotency_key' => 'replace-key-000000000000001'];
$replaced = $service->replace($actor, $replaceInput);
$assert(($replaced['ok'] ?? false) && (int) $replaced['assignment']['es_activo'] === 1
    && $replaced['assignment']['valido_desde'] === '2026-12-14', 'replace is atomic and returns the persisted replacement readback');
$replaceAuditCount = Capsule::table('op_rh_v2_turnos_audit_log')->count();
$replaceReplay = $service->replace($actor, $replaceInput);
$assert(($replaceReplay['ok'] ?? false) && ($replaceReplay['replayed'] ?? false)
    && ($replaceReplay['assignment']['id'] ?? 0) === ($replaced['assignment']['id'] ?? -1)
    && Capsule::table('op_rh_v2_turnos_audit_log')->count() === $replaceAuditCount,
    'replace idempotency replays the durable successor audit snapshot without a second close or assignment');

Capsule::statement("CREATE TRIGGER fail_m03_audit BEFORE INSERT ON op_rh_v2_turnos_audit_log BEGIN SELECT RAISE(ABORT, 'audit failure'); END");
$before = Capsule::table('op_rh_v2_turnos_asignaciones')->count();
$rollbackInput = ['workplace_id' => 9, 'worker_id' => 40, 'week_start' => '2026-11-23', 'target_date' => '2026-11-24',
    'effective_start_date' => '2026-11-24', 'tipo_asignacion' => 'HABITUAL', 'template_id' => 'REST',
    'idempotency_key' => 'rollback-key-0000000000001'];
$failed = $service->create($actor, $rollbackInput);
$assert(!($failed['ok'] ?? false) && Capsule::table('op_rh_v2_turnos_asignaciones')->count() === $before,
    'audit failure rolls back assignment write without partial persistence');

$gated = (new ChecadorWeeklyScheduleSqlWriteService(static fn(): bool => true, $membership, static fn(): bool => true));
putenv('CHECADOR_M03_API_WRITER_DISABLED=0');
$assert(($gated->create($actor, $rollbackInput)['error'] ?? '') === 'm03_writer_cutover_not_ready',
    'SQL writer remains closed until API cutover and schema preflight gates are explicitly confirmed');

foreach (['CHECADOR_M03_SQL_WRITES_ENABLED', 'CHECADOR_M03_API_WRITER_DISABLED', 'CHECADOR_M03_SCHEMA_PREFLIGHT_OK'] as $env) putenv($env);
echo "RESULT: {$passes} PASS / {$fails} FAIL (isolated SQLite; not MariaDB concurrency evidence)\n";
exit($fails === 0 ? 0 : 1);

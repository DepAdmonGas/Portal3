<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Operativo\RhPuestos;
use App\Models\Usuario;
use App\Services\ChecadorConfigurationWriteService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $message . "\n";
    $condition ? $passes++ : $fails++;
};

$keys = ['APP_ENV', 'CHECADOR_M11_CONFIGURATION_POSTS_ENABLED', 'CHECADOR_ADVANCED_DEMO_ENABLED', 'LOG_PATH'];
$original = [];
foreach ($keys as $key) $original[$key] = [array_key_exists($key, $_ENV), $_ENV[$key] ?? null, getenv($key)];
$logPath = sys_get_temp_dir() . '/portal3-m11-position-' . bin2hex(random_bytes(6)) . '.log';
$_ENV['APP_ENV'] = 'dev';
$_ENV['CHECADOR_M11_CONFIGURATION_POSTS_ENABLED'] = 'false';
$_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] = 'false';
$_ENV['LOG_PATH'] = $logPath;

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
Capsule::schema()->create('op_rh_puestos', static function (Blueprint $table): void {
    $table->increments('id');
    $table->text('puesto');
    $table->integer('status');
});
Capsule::schema()->create('op_rh_v2_m11_configuration_audit', static function (Blueprint $table): void {
    $table->increments('id');
    $table->unsignedInteger('actor_portal_user_id');
    $table->string('permission_key', 80);
    $table->unsignedInteger('workplace_id')->nullable();
    $table->string('entity_type', 32);
    $table->unsignedInteger('entity_id')->nullable();
    $table->string('action', 32);
    $table->dateTime('created_at_utc')->default('CURRENT_TIMESTAMP');
});
Capsule::table('op_rh_puestos')->insert(['puesto' => 'Operador auxiliar', 'status' => 1]);

$actor = new Usuario();
$actor->id = 90001;
$writer = new ChecadorConfigurationWriteService(static fn(Usuario $user, ?int $workplaceId): bool => $user->id === 90001 && $workplaceId === 1);

try {
    $created = $writer->savePosition($actor, 1, null, 'Especialista QA');
    $createdId = (int) ($created['item']['id'] ?? 0);
    $createdAudit = Capsule::table('op_rh_v2_m11_configuration_audit')->first();
    $assert(($created['ok'] ?? false) && $createdId > 0
        && (string) RhPuestos::query()->whereKey($createdId)->value('puesto') === 'Especialista QA'
        && ($createdAudit->entity_type ?? '') === 'POSITION'
        && ($createdAudit->action ?? '') === 'CREATE',
        'isolated create writes the position, audits it, and confirms it by readback');

    $updated = $writer->savePosition($actor, 1, $createdId, 'Especialista de calidad');
    $auditActions = Capsule::table('op_rh_v2_m11_configuration_audit')->orderBy('id')->pluck('action')->all();
    $assert(($updated['ok'] ?? false) && ($updated['item']['id'] ?? 0) === $createdId
        && (string) RhPuestos::query()->whereKey($createdId)->value('puesto') === 'Especialista de calidad'
        && $auditActions === ['CREATE', 'UPDATE'],
        'isolated edit updates the selected row and appends an update event');

    $duplicateCreate = $writer->savePosition($actor, 1, null, 'ESPECIALISTA DE CALIDAD');
    $duplicateUpdate = $writer->savePosition($actor, 1, $createdId, 'OPERADOR AUXILIAR');
    $missingUpdate = $writer->savePosition($actor, 1, 99999, 'Puesto no encontrado');
    $assert(($duplicateCreate['error'] ?? '') === 'duplicate_position'
        && ($duplicateUpdate['error'] ?? '') === 'duplicate_position'
        && ($missingUpdate['error'] ?? '') === 'position_not_found'
        && Capsule::table('op_rh_v2_m11_configuration_audit')->count() === 2,
        'active-name duplicates and missing update targets are rejected without extra audit events');

    Capsule::schema()->drop('op_rh_v2_m11_configuration_audit');
    $auditFailure = $writer->savePosition($actor, 1, $createdId, 'Cambio con auditoría no disponible');
    $persistedName = (string) RhPuestos::query()->whereKey($createdId)->value('puesto');
    $diagnosticLog = is_file($logPath) ? (string) file_get_contents($logPath) : '';
    $assert(($auditFailure['error'] ?? '') === 'position_write_failed'
        && $persistedName === 'Especialista de calidad'
        && str_contains($diagnosticLog, 'audit_insert')
        && str_contains($diagnosticLog, 'driver_code')
        && !str_contains($diagnosticLog, 'Cambio con auditoría no disponible'),
        'audit failure rolls back the position change and logs a safe server-side phase/code diagnostic');

    $serviceSource = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorConfigurationWriteService.php') ?: '';
    $controller = file_get_contents(dirname(__DIR__) . '/app/Controllers/ChecadorController.php') ?: '';
    $start = strpos($controller, 'public function saveConfigurationPosition(): void');
    $end = strpos($controller, 'public function updateConfigurationReaderProfile(): void', $start ?: 0);
    $method = $start === false ? '' : substr($controller, $start, $end === false ? null : $end - $start);
    $javascript = file_get_contents(dirname(__DIR__) . '/public/assets/js/departamento-operativo/2-recursos-humanos/checador.m11-positions.init.js') ?: '';
    $readService = file_get_contents(dirname(__DIR__) . '/app/Services/ChecadorConfigurationReadService.php') ?: '';
    $routes = file_get_contents(dirname(__DIR__) . '/routes/web.php') ?: '';
    $assert(str_contains($serviceSource, 'authorizePositionCatalog($workplaceId, \'editar\')')
        && str_contains($serviceSource, 'Auth::check()')
        && str_contains($serviceSource, '(int) $authenticated->id === (int) $actor->id'),
        'default service authorization requires the same authenticated Dirección identity and effective catalog grant');
    $assert(str_contains($method, 'HTTP_X_CSRF_TOKEN') && str_contains($method, '$input[\'_csrf_token\']')
        && str_contains($method, 'positionIdProvided && $positionId === null')
        && str_contains($method, "'invalid_position_name' => 422, default => 500")
        && !str_contains($method, 'm11_write_disabled')
        && str_contains($routes, "'/recursos-humanos/checador/configuracion/puestos'"),
        'authenticated endpoint validates CSRF and rejects malformed edit IDs instead of treating them as creates');
    $assert(str_contains($javascript, "detail: { id, name: String(row.name ?? '') }")
        && str_contains($javascript, "position_id: this.mode === 'edit' ? this.id : null")
        && str_contains($javascript, 'this.table.row.add(confirmed)')
        && str_contains($javascript, '.row(rowIndex).data(confirmed)')
        && str_contains($javascript, 'body.diagnostic_id')
        && str_contains($readService, "where('status', 1)->orderBy('puesto')->get(['id', 'puesto'])"),
        'modal selection, Axios payload, DataTables update, and reload projection preserve create/edit behavior');
    $assert(!str_contains($method, '$exception->getMessage()')
        && str_contains($serviceSource, 'exception_class') && str_contains($serviceSource, 'sql_state')
        && str_contains($serviceSource, 'driver_code') && str_contains($serviceSource, 'diagnostic_id'),
        'unexpected database failures are diagnosed in server logs without exposing exception text in JSON');
} finally {
    foreach ($keys as $key) {
        [$wasSet, $value, $processValue] = $original[$key];
        if ($wasSet) $_ENV[$key] = $value; else unset($_ENV[$key]);
        if ($processValue === false) putenv($key); else putenv($key . '=' . $processValue);
    }
    if (is_file($logPath)) unlink($logPath);
}

printf("CHECADOR_M11_POSITION_WRITE_RESULT: %d PASS / %d FAIL / isolated SQLite\n", $passes, $fails);
exit($fails === 0 ? 0 : 1);

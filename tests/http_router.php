<?php

declare(strict_types=1);

use App\Controllers\SwitchEstacionController;
use App\Controllers\DownloadController;
use App\Controllers\TokenTelegramController;
use App\Core\Auth;
use App\Core\CsrfToken;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\ControlDocumentosPersonalService;
use App\Models\Operativo\TokenTelegram;
use Illuminate\Database\Capsule\Manager as Capsule;

$database = getenv('P0_TEST_DB') ?: '';
$sessionPath = getenv('P0_TEST_SESSION_PATH') ?: '';
$downloadRoot = getenv('P0_TEST_DOWNLOAD_ROOT') ?: '';
if (!str_starts_with($database, '/tmp/portal3-p0-regression-')
    || !str_starts_with($sessionPath, '/tmp/portal3-p0-session-')
    || !str_starts_with($downloadRoot, '/tmp/portal3-p0-download-')) {
    http_response_code(500);
    exit('Unsafe P0 test configuration.');
}
ini_set('session.save_path', $sessionPath);
require dirname(__DIR__) . '/vendor/autoload.php';

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return dirname(__DIR__) . ($path === '' ? '' : DIRECTORY_SEPARATOR . $path);
    }
}

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => $database, 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$schema = $capsule->schema();
if (!$schema->hasTable('tb_usuarios')) {
    $schema->create('tb_usuarios', static function ($table): void { $table->integer('id')->primary(); $table->string('nombre'); $table->integer('id_gas'); $table->integer('id_puesto'); $table->integer('estatus')->default(0); });
    $schema->create('tb_estaciones', static function ($table): void { $table->integer('id')->primary(); $table->string('nombre'); $table->string('razonsocial')->nullable(); $table->integer('estatus')->default(0); });
    $schema->create('tb_multiestacion_usuario', static function ($table): void { $table->integer('id')->primary(); $table->integer('id_usuario'); $table->text('estaciones')->nullable(); $table->text('departamentos_puestos')->nullable(); $table->text('departamentos_localidades')->nullable(); $table->boolean('activo')->default(true); });
    $schema->create('tb_multiestacion_puesto', static function ($table): void { $table->integer('id')->primary(); $table->integer('id_puesto'); $table->text('estaciones')->nullable(); $table->text('departamentos_puestos')->nullable(); $table->text('departamentos_localidades')->nullable(); $table->boolean('activo')->default(true); });
    $schema->create('tb_modulos_config', static function ($table): void { $table->integer('id')->primary(); $table->string('modulo_key'); $table->string('tipo'); $table->text('estaciones_soportadas')->nullable(); $table->text('departamentos_soportados')->nullable(); $table->string('tipo_departamento')->nullable(); $table->boolean('allow_all')->default(false); $table->string('placeholder')->nullable(); $table->boolean('activo')->default(true); });
    $schema->create('op_rh_personal', static function ($table): void { $table->integer('id')->primary(); $table->integer('id_estacion'); $table->string('ine')->nullable(); });
    $schema->create('op_token_telegram', static function ($table): void { $table->integer('id')->primary(); $table->integer('id_usuario'); $table->string('token'); $table->integer('chat_id')->default(0); $table->string('fecha_creacion'); $table->integer('estatus')->default(0); });
    Capsule::table('tb_estaciones')->insert([['id' => 101, 'nombre' => 'Tenant A', 'razonsocial' => 'A SA', 'estatus' => 0], ['id' => 202, 'nombre' => 'Tenant B', 'razonsocial' => 'B SA', 'estatus' => 0]]);
    Capsule::table('tb_usuarios')->insert([['id' => 1, 'nombre' => 'Usuario A', 'id_gas' => 101, 'id_puesto' => 10, 'estatus' => 0], ['id' => 2, 'nombre' => 'Usuario B', 'id_gas' => 202, 'id_puesto' => 20, 'estatus' => 0]]);
    Capsule::table('tb_multiestacion_usuario')->insert(['id' => 1, 'id_usuario' => 1, 'estaciones' => '[101]', 'activo' => 1]);
    Capsule::table('tb_modulos_config')->insert(['id' => 1, 'modulo_key' => 'control-documentos-personal', 'tipo' => 'stations', 'estaciones_soportadas' => '[101,202]', 'tipo_departamento' => 'puestos', 'activo' => 1]);
    Capsule::table('op_rh_personal')->insert([['id' => 1, 'id_estacion' => 101, 'ine' => 'tenant-a-ine.pdf'], ['id' => 2, 'id_estacion' => 202, 'ine' => 'tenant-b-ine.pdf']]);
    Capsule::table('op_token_telegram')->insert(['id' => 1, 'id_usuario' => 2, 'token' => 'b-token', 'chat_id' => 222, 'fecha_creacion' => '2026-09-11 00:00:00', 'estatus' => 1]);
}

Session::init();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__test/health') exit('ok');
if ($path === '/__test/login-a') { Auth::forget(); Session::set('usuario', ['id' => 1, 'id_estacion' => 101, 'nombre_estacion' => 'Tenant A', 'razonsocial' => 'A SA', 'multiestacion' => true]); $_SESSION['permisos_do'] = ['recursos-humanos' => ['descargar' => 1]]; Session::set('csrf_handler_reached', false); exit('ok'); }
if ($path === '/__test/login-b') { Auth::forget(); Session::set('usuario', ['id' => 2, 'id_estacion' => 202, 'nombre_estacion' => 'Tenant B', 'razonsocial' => 'B SA', 'multiestacion' => true]); $_SESSION['permisos_do'] = ['recursos-humanos' => ['descargar' => 1]]; exit('ok'); }
if ($path === '/__test/logout') { Auth::forget(); Session::destroy(); exit('ok'); }
if ($path === '/__test/session') { header('Content-Type: application/json'); exit(json_encode(['id_estacion' => Session::get('usuario')['id_estacion'] ?? null, 'csrf_handler_reached' => Session::get('csrf_handler_reached', false)])); }
if ($path === '/__test/reset-csrf-marker') { Session::set('csrf_handler_reached', false); exit('ok'); }
if ($path === '/__test/csrf-token') exit(CsrfToken::token());
if ($path === '/__test/csrf') { (new CsrfMiddleware())->handle(); Session::set('csrf_handler_reached', true); exit('accepted'); }
if ($path === '/__test/sensitive-upload-path') { exit(ControlDocumentosPersonalService::getUploadDir()); }
if ($path === '/__test/authorized-personal') { $personal = ControlDocumentosPersonalService::findAuthorizedPersonal((int) ($_GET['id'] ?? 0)); header('Content-Type: application/json'); exit(json_encode(['id' => $personal?->id])); }
if ($path === '/__test/telegram-state') { header('Content-Type: application/json'); exit(json_encode(TokenTelegram::orderBy('id')->get()->map(static fn ($r) => ['id_usuario' => $r->id_usuario, 'token' => $r->token, 'chat_id' => $r->chat_id, 'estatus' => $r->estatus])->all())); }
if ($path === '/switch-estacion') { (new SwitchEstacionController())->switchSessionEstacion(); exit; }
if ($path === '/download') { (new DownloadController())->download(); exit; }
if ($path === '/token-telegram/status') { (new TokenTelegramController())->status(); exit; }
if ($path === '/token-telegram/generate') { (new TokenTelegramController())->generate(); exit; }
if ($path === '/token-telegram/revoke') { (new TokenTelegramController())->revoke(); exit; }
if (str_starts_with($path, '/uploads/')) { http_response_code(404); exit('not found'); }
http_response_code(404);
exit('not found');

<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Core\Cookie;
use App\Core\CsrfToken;
use App\Core\ErrorHandler;
use App\Core\JWTService;
use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\DptoOperativoActivoMiddleware;
use App\Repositories\UsuarioRepository;
use App\Services\SessionService;
use App\Services\TokenService;

$sessionPath = getenv('PORTAL3_LOGOUT_SESSION_TEST_PATH') ?: '';
if (!str_starts_with($sessionPath, '/tmp/portal3-logout-runtime-')) {
    http_response_code(500);
    exit('Unsafe logout test session path.');
}

ini_set('session.save_path', $sessionPath);
$_ENV['APP_ENV'] = 'dev';
$_ENV['LOG_PATH'] = getenv('PORTAL3_LOGOUT_LOG_TEST') ?: '/proc/portal3-logout-runtime/app.log';
$errorLogPath = getenv('PORTAL3_LOGOUT_ERROR_LOG_TEST') ?: '';
if ($errorLogPath !== '') {
    ini_set('error_log', $errorLogPath);
}

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$_ENV['APP_ENV'] = 'dev';
$_ENV['LOG_PATH'] = getenv('PORTAL3_LOGOUT_LOG_TEST') ?: '/proc/portal3-logout-runtime/app.log';
ErrorHandler::register();
Session::init();

$usuarioRepository = new UsuarioRepository();
$tokenService = new TokenService($usuarioRepository);
$sessionService = new SessionService();
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if ($path === '/__test/login') {
    SessionService::start(['id' => 41, 'nombre' => 'Usuario de prueba']);
    Cookie::set('token', JWTService::createAccessToken(['sub' => 41, 'nombre' => 'Usuario de prueba']));
    Cookie::set('refresh_token', 'refresh-test-token');
    header('Content-Type: application/json');
    exit(json_encode(['csrf' => CsrfToken::token(), 'authenticated' => Session::isLogged()]));
}

if ($path === '/logout') {
    (new AuthMiddleware($tokenService, $usuarioRepository))->handle();
    (new CsrfMiddleware())->handle();
    (new DptoOperativoActivoMiddleware())->handle();
    (new AuthController($tokenService, $sessionService))->logout();
}

if ($path === '/__test/protected') {
    (new AuthMiddleware($tokenService, $usuarioRepository))->handle();
    header('Content-Type: application/json');
    exit(json_encode(['protected' => true]));
}

http_response_code(404);
exit('not found');

<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\CsrfToken;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\SessionService;

$sessionPath = getenv('SESSION_SECURITY_TEST_PATH') ?: '';
if (!str_starts_with($sessionPath, '/tmp/portal3-session-security-')) {
    http_response_code(500);
    exit('Unsafe session test configuration.');
}

ini_set('session.save_path', $sessionPath);
$_ENV['APP_ENV'] = 'prod';
require dirname(__DIR__) . '/vendor/autoload.php';

Session::init();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/anonymous') {
    header('Content-Type: application/json');
    exit(json_encode(['authenticated' => Auth::check()]));
}

if ($path === '/login') {
    SessionService::start(['id' => 1, 'usuario' => 'session-test']);
    header('Content-Type: application/json');
    exit(json_encode(['authenticated' => Auth::check(), 'csrf' => CsrfToken::token()]));
}

if ($path === '/session') {
    header('Content-Type: application/json');
    exit(json_encode(['authenticated' => Auth::check()]));
}

if ($path === '/csrf') {
    (new CsrfMiddleware())->handle();
    exit('accepted');
}

if ($path === '/logout') {
    SessionService::logout();
    exit('logged-out');
}

http_response_code(404);
exit('not found');

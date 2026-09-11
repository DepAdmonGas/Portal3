<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\CsrfToken;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\SessionService;

$sessionPath = getenv('LAYOUT_CSRF_LOGOUT_SESSION_TEST_PATH') ?: '';
if (!str_starts_with($sessionPath, '/tmp/portal3-layout-csrf-logout-')) {
    http_response_code(500);
    exit('Unsafe layout CSRF logout test configuration.');
}

ini_set('session.save_path', $sessionPath);
$_ENV['APP_ENV'] = 'dev';
require dirname(__DIR__) . '/vendor/autoload.php';
Session::init();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/login') {
    SessionService::start(['id' => 1, 'usuario' => 'layout-csrf-test']);
    header('Content-Type: application/json');
    exit(json_encode(['csrf' => CsrfToken::token(), 'authenticated' => Auth::check()]));
}

if ($path === '/logout') {
    (new CsrfMiddleware())->handle();
    SessionService::logout();
    header('Content-Type: application/json');
    exit(json_encode(['success' => true]));
}

http_response_code(404);
exit('not found');

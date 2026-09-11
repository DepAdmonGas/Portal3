<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\CsrfToken;
use App\Core\Session;
use App\Middleware\CsrfMiddleware;
use App\Services\SessionService;

$sessionPath = getenv('CSRF_POST_LOGIN_SESSION_TEST_PATH') ?: '';
if (!str_starts_with($sessionPath, '/tmp/portal3-csrf-post-login-')) {
    http_response_code(500);
    exit('Unsafe CSRF post-login test configuration.');
}

ini_set('session.save_path', $sessionPath);
$_ENV['APP_ENV'] = 'dev';

require dirname(__DIR__) . '/vendor/autoload.php';
Session::init();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/anonymous-form') {
    header('Content-Type: application/json');
    exit(json_encode(['csrf_token' => CsrfToken::token()]));
}

if ($path === '/login') {
    SessionService::start(['id' => 1, 'usuario' => 'csrf-post-login-test']);
    header('Content-Type: application/json');
    exit(json_encode(['authenticated' => Auth::check()]));
}

if ($path === '/authenticated-form') {
    if (!Auth::check()) {
        http_response_code(401);
        exit;
    }

    header('Content-Type: application/json');
    exit(json_encode(['csrf_token' => CsrfToken::token(), 'authenticated' => true]));
}

if ($path === '/mutation') {
    if (!Auth::check()) {
        http_response_code(401);
        exit;
    }

    (new CsrfMiddleware())->handle();
    header('Content-Type: application/json');
    exit(json_encode(['accepted' => true, 'authenticated' => true]));
}

http_response_code(404);
exit('not found');

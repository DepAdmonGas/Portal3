<?php

declare(strict_types=1);

use App\Core\Cookie;
use App\Core\Session;
use App\Services\SessionService;

$sessionPath = getenv('POST_LOGIN_SESSION_TEST_PATH') ?: '';
$appEnv = getenv('POST_LOGIN_APP_ENV') ?: '';
if (!str_starts_with($sessionPath, '/tmp/portal3-post-login-') || !in_array($appEnv, ['dev', 'prod'], true)) {
    http_response_code(500);
    exit('Unsafe post-login test configuration.');
}

ini_set('session.save_path', $sessionPath);
if (getenv('POST_LOGIN_HTTPS') === '1') {
    $_SERVER['HTTPS'] = 'on';
}

require dirname(__DIR__) . '/vendor/autoload.php';
if (getenv('POST_LOGIN_INIT_BEFORE_ENV') === '1') {
    Session::init();
    $_ENV['APP_ENV'] = $appEnv;
} else {
    $_ENV['APP_ENV'] = $appEnv;
    Session::init();
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/login') {
    header('Content-Type: application/json');
    exit(json_encode(['success' => true]));
}

if ($path === '/login/acceso') {
    SessionService::start(['id' => 1, 'usuario' => 'post-login-test']);
    Cookie::set('token', 'test-access-token');
    header('Content-Type: application/json');
    exit(json_encode(['success' => true, 'type' => 'success']));
}

if ($path === '/home') {
    if (!Session::has('usuario') || !Cookie::has('token')) {
        header('Location: /login');
        http_response_code(302);
        exit;
    }

    header('Content-Type: application/json');
    exit(json_encode(['authenticated' => true]));
}

http_response_code(404);
exit('not found');

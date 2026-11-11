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

if ($path === '/expire-csrf-token') {
    if (!Auth::check()) {
        http_response_code(401);
        exit;
    }
    CsrfToken::token();
    Session::set('_csrf_token_time', time() - 3601);
    exit('expired');
}

if ($path === '/reset-csrf-marker') {
    Session::remove('csrf_handler_reached');
    exit('reset');
}

if ($path === '/csrf-handler-state') {
    header('Content-Type: application/json');
    exit(json_encode(['reached' => Session::get('csrf_handler_reached', false) === true]));
}

if ($path === '/incidence-create-feedback') {
    $feedback = Session::get('checador_incidence_create_feedback', []);
    header('Content-Type: application/json');
    exit(json_encode([
        'type' => is_array($feedback) ? ($feedback['type'] ?? '') : '',
        'message' => is_array($feedback) ? ($feedback['message'] ?? '') : '',
        'worker_id' => is_array($feedback) ? ($feedback['worker_id'] ?? null) : null,
        'workplace_id' => is_array($feedback) ? ($feedback['workplace_id'] ?? null) : null,
        'return_date' => is_array($feedback) ? ($feedback['return_date'] ?? '') : '',
        'reason' => is_array($feedback) ? ($feedback['form_values']['reason'] ?? '') : '',
        'confirmed' => is_array($feedback) ? (($feedback['form_values']['confirm_create'] ?? false) === true) : false,
        'idempotency_key' => is_array($feedback) ? ($feedback['retry_idempotency_key'] ?? '') : '',
    ]));
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

if ($path === '/departamento-operativo/recursos-humanos/checador/incidencias/crear') {
    if (!Auth::check()) {
        http_response_code(401);
        exit;
    }

    (new CsrfMiddleware())->handle();
    Session::set('csrf_handler_reached', true);
    exit('accepted');
}

http_response_code(404);
exit('not found');

<?php
namespace App\Middleware;

use App\Core\JWTService;
use Firebase\JWT\ExpiredException;
use App\Core\Session;

class AuthMiddleware
{
    public function handle(): void
    {
        $token = $_COOKIE['token'] ?? null;

        if (!$token) {
            $this->redirectLogin();
        }

        try {

            $payload = JWTService::validate($token);
            $GLOBALS['user'] = (array) $payload;

            if (!Session::get('usuario')) {
                $this->redirectLogin();
            }

        } catch (\Throwable $e) {
            $this->redirectLogin();
        }
    }

    private function redirectLogin(): void
    {
        Session::destroy();

        setcookie('token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        header('Location: /login');
        exit;
    }
}

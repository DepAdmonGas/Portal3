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
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        Session::destroy();

        setcookie('token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        // Para requests AJAX/API (POST, PUT, DELETE, PATCH), responder JSON en vez de HTML
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Sesión no válida. Por favor inicie sesión nuevamente.'
            ]);
            exit;
        }

        header('Location: /login');
        exit;
    }
}

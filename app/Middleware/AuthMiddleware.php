<?php
namespace App\Middleware;

use App\Core\JWTService;
use Firebase\JWT\ExpiredException;

class AuthMiddleware
{
    public function handle(): void
    {
        $token = $_COOKIE['token'] ?? null;

        if (!$token) {
            $this->unauthorized('No autenticado');
        }

        try {
            $payload = JWTService::validate($token);

            $GLOBALS['user'] = (array) $payload;

        } catch (ExpiredException $e) {
            $this->clearToken();
            $this->unauthorized('Sesión expirada');

        } catch (\Exception $e) {
            $this->clearToken();
            $this->unauthorized('Token inválido');
        }
    }

    private function clearToken(): void
    {
        setcookie('token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    private function unauthorized(string $message): void
    {
        http_response_code(401);

        if (str_starts_with($_SERVER['REQUEST_URI'], '/api')) {
            header('Content-Type: application/json');
            echo json_encode(['message' => $message]);
        } else {
            header('Location: /');
        }

        exit;
    }
}

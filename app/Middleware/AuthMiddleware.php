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

        // No hay token
        if (!$token) {
            $this->forceLogout('No autenticado');
        }

        try {
            // Validar token
            $payload = JWTService::validate($token);
            $GLOBALS['user'] = (array) $payload;

            // Validar sesión
            if (!Session::get('usuario')) {
                $this->forceLogout('Sesión expirada');
            }

        } catch (ExpiredException $e) {
            $this->forceLogout('Sesión expirada');

        } catch (\Exception $e) {
            $this->forceLogout('Token inválido');
        }
    }

    private function forceLogout(string $message): void
    {
        // Destruir sesión
        Session::destroy();

        // Eliminar cookie
        setcookie('token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => false, // ojo aquí en local
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        $this->unauthorized($message);
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

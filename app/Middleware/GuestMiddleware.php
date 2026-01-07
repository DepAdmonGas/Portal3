<?php
namespace App\Middleware;

use App\Core\JWTService;

class GuestMiddleware
{
    public static function handle(): void
    {
        $token = $_COOKIE['token'] ?? null;

        if (!$token) {
            return; // 👌 no hay sesión → mostrar login
        }

        try {
            JWTService::validate($token);

            // ✅ Token válido → redirigir
            header('Location: /home');
            exit;

        } catch (\Exception $e) {
            return;
        }
    }
}

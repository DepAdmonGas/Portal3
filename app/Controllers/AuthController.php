<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Session;

/**
 * AuthController - Controlador de autenticación
 * 
 * SECURITY: BAJO #34 - Logout efectivo con invalidación de tokens
 * 
 * @author Security Team
 */
class AuthController
{
    /**
     * Cierra la sesión del usuario actual
     * 
     * SECURITY: BAJO #34 - Implementa logout efectivo:
     * - Destruye sesión PHP
     * - Invalida access token (cookie)
     * - Invalida refresh token (cookie)
     * - Registra el logout en logs
     * 
     * @return void
     */
    public function logout()
    {
        header('Content-Type: application/json');
        
        // SECURITY: BAJO #34 - Obtener datos antes de destruir sesión
        
        // 1. Obtener ID de usuario
        $userId = Auth::id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // 2. Loggear el logout
        Logger::getLogger()->info('Logout ejecutado', [
            'user_id' => $userId,
            'ip' => $ip
        ]);
        
        // 3. Destruir sesión PHP
        Session::destroy();
        
        // SECURITY: Cookie con secure flag solo si está en HTTPS+Y producción (Vulnerabilidad #4)
        $isSecure = ($_ENV['APP_ENV'] ?? 'dev') === 'prod' && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        
        // 4. Invalidar access token cookie
        setcookie(
            'token',
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        
        // SECURITY: BAJO #34 - Invalidar también el refresh token
        setcookie(
            'refresh_token',
            '',
            [
                'expires'  => time() - 3600,
                'path'     => '/',
                'secure'   => $isSecure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
        
        // 5. Responder al cliente
        echo json_encode([
            'type' => 'success',
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}
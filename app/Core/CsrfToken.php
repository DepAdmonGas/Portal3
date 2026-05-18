<?php
namespace App\Core;

/**
 * CsrfToken - Protección contra ataques CSRF
 * 
 * Genera y valida tokens CSRF para proteger formularios
 */
class CsrfToken
{
    /**
     * Generar token CSRF si no existe
     * 
     * @return string
     */
    public static function generate(): string
    {
        if (!Session::get('_csrf_token')) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf_token', $token);
            Session::set('_csrf_token_time', time());
        }
        
        return Session::get('_csrf_token');
    }
    
    /**
     * Validar token CSRF
     * 
     * @param string $token Token a validar
     * @return bool True si el token es válido
     */
    public static function validate(string $token): bool
    {
        $sessionToken = Session::get('_csrf_token');
        $tokenTime = Session::get('_csrf_token_time', 0);
        
        // Verificar si existe el token en sesión
        if (!$sessionToken || !$token) {
            return false;
        }
        
        // Verificar tiempo (token válido por 1 hora)
        if (time() - $tokenTime > 3600) {
            // Token expirado, regenerar
            Session::remove('_csrf_token');
            Session::remove('_csrf_token_time');
            return false;
        }
        
        // Timing-safe comparison para prevenir timing attacks
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Obtener token para usar en formularios
     * 
     * @return string
     */
    public static function token(): string
    {
        return self::generate();
    }
    
    /**
     * Regenerar token CSRF
     * Útil después de validar exitosamente para mayor seguridad
     * 
     * @return string
     */
    public static function refresh(): string
    {
        Session::remove('_csrf_token');
        Session::remove('_csrf_token_time');
        return self::generate();
    }
    
    /**
     * Obtener el tiempo restante de validez del token (en segundos)
     * 
     * @return int
     */
    public static function getRemainingTime(): int
    {
        $tokenTime = Session::get('_csrf_token_time', 0);
        if (!$tokenTime) {
            return 0;
        }
        
        $remaining = 3600 - (time() - $tokenTime);
        return max(0, $remaining);
    }
}
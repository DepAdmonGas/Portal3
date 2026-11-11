<?php
namespace App\Core;

/**
 * CsrfToken - Protección contra ataques CSRF
 * 
 * Genera y valida tokens CSRF para proteger formularios
 */
class CsrfToken
{
    private const LIFETIME_SECONDS = 3600;

    /**
     * Generar token CSRF si no existe
     * 
     * @return string
     */
    public static function generate(): string
    {
        $token = Session::get('_csrf_token');
        $issuedAt = Session::get('_csrf_token_time', 0);
        $now = time();
        $age = is_numeric($issuedAt) ? $now - (int) $issuedAt : -1;

        if (!is_string($token)
            || preg_match('/\A[a-f0-9]{64}\z/i', $token) !== 1
            || $age < 0
            || $age > self::LIFETIME_SECONDS) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf_token', $token);
            Session::set('_csrf_token_time', $now);
        }
        
        return $token;
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
        if (!is_string($sessionToken) || $sessionToken === '' || $token === '') {
            return false;
        }
        
        $age = is_numeric($tokenTime) ? time() - (int) $tokenTime : -1;
        if ($age < 0 || $age > self::LIFETIME_SECONDS) {
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
        
        if (!is_numeric($tokenTime)) {
            return 0;
        }

        $age = time() - (int) $tokenTime;
        if ($age < 0) {
            return 0;
        }

        $remaining = self::LIFETIME_SECONDS - $age;
        return max(0, $remaining);
    }
}

<?php
namespace App\Middleware;

use App\Core\Session;

/**
 * RateLimitMiddleware - Protección contra ataques de fuerza bruta y DDoS
 * 
 * Implementa limitación de solicitudes por IP usando sesiones
 * 
 * Límites configurados:
 * - login: 5 intentos cada 5 minutos
 * - api: 60 solicitudes por minuto
 * - default: 100 solicitudes por minuto
 */
class RateLimitMiddleware
{
    private static $limits = [
        'login' => ['max' => 5, 'window' => 300],      // 5 intentos cada 5 min
        'api' => ['max' => 60, 'window' => 60],        // 60 req/min
        'default' => ['max' => 100, 'window' => 60],   // 100 req/min
    ];
    
    /**
     * Verifica si el request está dentro del límite permitido
     * 
     * @param string $type Tipo de ('login', 'api', 'default')
     * @return bool True si está dentro del límite, false si excedido
     */
    public static function check(string $type = 'default'): bool
    {
        $ip = self::getClientIp();
        $key = "rate_limit_{$type}_{$ip}";
        
        $limit = self::$limits[$type] ?? self::$limits['default'];
        
        // Obtener cantidad de intentos actuales
        $attempts = Session::get($key, 0);
        
        // Verificar si excede el límite
        if ($attempts >= $limit['max']) {
            self::rateLimitExceeded($type);
            return false;
        }
        
        // Incrementar contador
        Session::set($key, $attempts + 1);
        
        // Inicializar o verificar tiempo de ventana
        $startKey = "{$key}_start";
        if (!Session::get($startKey)) {
            Session::set($startKey, time());
        } else {
            $elapsed = time() - Session::get($startKey);
            // Resetear contador si pasaron más de 'window' segundos
            if ($elapsed > $limit['window']) {
                Session::remove($key);
                Session::remove($startKey);
                // Reiniciar contador para el nuevo período
                Session::set($key, 1);
                Session::set($startKey, time());
            }
        }
        
        return true;
    }
    
    /**
     * Obtiene la IP del cliente considerando proxys
     * 
     * @return string
     */
    private static function getClientIp(): string
    {
        // Verificar headers de proxy
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy estándar
            'HTTP_X_REAL_IP',            // Nginx proxy
            'REMOTE_ADDR'                // Default
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                // X-Forwarded-For puede contener múltiples IPs
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Responde cuando se excede el límite
     * 
     * @param string $type
     */
    private static function rateLimitExceeded(string $type): void
    {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . (self::$limits[$type]['window'] ?? 60));
        
        echo json_encode([
            'success' => false,
            'type' => 'rate_limit_exceeded',
            'message' => 'Demasiadas solicitudes. Intente más tarde.',
            'retry_after' => self::$limits[$type]['window'] ?? 60
        ]);
        
        exit;
    }
    
    /**
     * Resetea el límite para una IP específica (útil para testing)
     * 
     * @param string $type
     */
    public static function reset(string $type = 'default'): void
    {
        $ip = self::getClientIp();
        $key = "rate_limit_{$type}_{$ip}";
        
        Session::remove($key);
        Session::remove("{$key}_start");
    }
    
    /**
     * Obtiene información de intentos actuales (para debugging)
     * 
     * @param string $type
     * @return array
     */
    public static function getStatus(string $type = 'default'): array
    {
        $ip = self::getClientIp();
        $key = "rate_limit_{$type}_{$ip}";
        $limit = self::$limits[$type] ?? self::$limits['default'];
        
        $attempts = Session::get($key, 0);
        $startTime = Session::get("{$key}_start", 0);
        $remainingTime = 0;
        
        if ($startTime > 0) {
            $elapsed = time() - $startTime;
            $remainingTime = max(0, $limit['window'] - $elapsed);
        }
        
        return [
            'attempts' => $attempts,
            'max' => $limit['max'],
            'window' => $limit['window'],
            'remaining_time' => $remainingTime,
            'reset_at' => $startTime > 0 ? date('Y-m-d H:i:s', $startTime + $limit['window']) : null
        ];
    }
}
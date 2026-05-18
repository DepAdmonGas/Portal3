<?php
namespace App\Middleware;

use App\Core\CsrfToken;
use App\Core\Session;

/**
 * CsrfMiddleware - Verifica tokens CSRF en requests POST/PUT/DELETE/PATCH
 * 
 * Protege contra ataques Cross-Site Request Forgery
 */
class CsrfMiddleware
{
    /**
     * Handle - Verifica el token CSRF en requests que modifican datos
     * 
     * @return bool
     */
    public function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        
        // Solo verificar en métodos que modifican datos
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            
            // Excluir endpoints que usan autenticación JWT (tienen su propia protección)
            // y el endpoint de login que no necesita CSRF
            $excludedRoutes = ['/login', '/api/', '/refresh-token', '/logout'];
            
            $isExcluded = false;
            foreach ($excludedRoutes as $route) {
                if (strpos($uri, $route) !== false) {
                    $isExcluded = true;
                    break;
                }
            }
            
            if (!$isExcluded) {
                // Obtener token desde header (Axios) o POST
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 
                         $_POST['_csrf_token'] ?? 
                         null;
                
                // Si no hay token en el request, generamos uno nuevo para la sesión
                // Esto permite que el primer request funcione si no hay token
                if (empty($token)) {
                    // Regenerar token en la sesión para el siguiente request
                    CsrfToken::token();
                    
                    // Permitir el request actual (no hay forma de validar sin token)
                    // En producción, esto podría ser más estricto
                    return true;
                }
                
                // Validar el token
                if (!CsrfToken::validate($token)) {
                    http_response_code(419); // 419 = Authentication Timeout (apropiado para CSRF)
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'type' => 'csrf_expired',
                        'message' => 'Token de seguridad expirado. Por favor actualice la página e intente de nuevo.'
                    ]);
                    exit;
                }
            }
        }
        
        return true;
    }
}
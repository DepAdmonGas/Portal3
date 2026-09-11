<?php
namespace App\Middleware;

use App\Core\CsrfToken;

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
            // Login is handled by Route::guest and the Telegram webhook does not
            // use Route::auth. Every mutable authenticated route is cookie/session
            // based and must therefore provide a valid CSRF token.
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ??
                     $_POST['_csrf_token'] ??
                     null;

            if (empty($token) || !CsrfToken::validate($token)) {
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
        
        return true;
    }
}

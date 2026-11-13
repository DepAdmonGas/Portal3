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
            // A submitted form token is authoritative when present. This is a
            // deterministic source rule, so a stale global AJAX header cannot
            // override a valid form token and invalid form data cannot fall
            // back to a different valid header token.
            if (array_key_exists('_csrf_token', $_POST)) {
                $token = is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : null;
            } else {
                $token = is_string($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)
                    ? $_SERVER['HTTP_X_CSRF_TOKEN']
                    : null;
            }

            if ($token === null || $token === '' || !CsrfToken::validate($token)) {
                $newToken = CsrfToken::refresh();

                $this->redirectIncidenceCreateFormWithFeedback();

                http_response_code(419); // 419 = Authentication Timeout (apropiado para CSRF)
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'type' => 'csrf_expired',
                    'message' => 'Token de seguridad expirado. Por favor actualice la página e intente de nuevo.',
                    'new_token' => $newToken
                ]);
                exit;
            }
        }
        
        return true;
    }

    private function redirectIncidenceCreateFormWithFeedback(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isFormRequest = in_array($contentType, ['application/x-www-form-urlencoded', 'multipart/form-data'], true)
            && array_key_exists('_csrf_token', $_POST)
            && strtoupper((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'XMLHTTPREQUEST'
            && !str_contains($accept, 'application/json');

        if ($method !== 'POST'
            || $requestPath !== '/departamento-operativo/recursos-humanos/checador/incidencias/crear'
            || !$isFormRequest) {
            return;
        }

        $readString = static function (string $key, int $maxBytes): string {
            $value = $_POST[$key] ?? '';
            if (!is_string($value)) {
                return '';
            }
            $value = trim($value);
            return function_exists('mb_substr')
                ? mb_substr($value, 0, $maxBytes, 'UTF-8')
                : substr($value, 0, $maxBytes);
        };

        $date = $readString('return_date', 10);
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $dateParts) !== 1
            || !checkdate((int) $dateParts[2], (int) $dateParts[3], (int) $dateParts[1])) {
            $date = date('Y-m-d');
        }

        $workerId = filter_var($_POST['worker_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $workplaceId = filter_var($_POST['workplace_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $idempotencyKey = $readString('idempotency_key', 64);
        if (preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey) !== 1) {
            $idempotencyKey = '';
        }

        \App\Core\Session::set('checador_incidence_create_feedback', [
            'type' => 'error',
            'message' => 'El token de seguridad venció o ya no coincide. No se registró la incidencia; conservamos los datos. Revísalos y confirma nuevamente.',
            'worker_id' => $workerId === false ? null : (int) $workerId,
            'workplace_id' => $workplaceId === false ? 0 : (int) $workplaceId,
            'return_date' => $date,
            'form_values' => [
                'category' => $readString('category', 64),
                'start_date' => $readString('start_date', 10),
                'end_date' => $readString('end_date', 10),
                'reason' => $readString('reason', 2000),
                'evidence_reference' => $readString('evidence_reference', 500),
                'confirm_create' => (string) ($_POST['confirm_create'] ?? '') === '1',
            ],
            'retry_idempotency_key' => $idempotencyKey,
        ]);

        $query = ['date' => $date];
        if ($workerId !== false) {
            $query['worker_id'] = (int) $workerId;
        }
        $location = '/departamento-operativo/recursos-humanos/checador/incidencias?' . http_build_query($query);
        header('Location: ' . $location, true, 303);
        exit;
    }
}

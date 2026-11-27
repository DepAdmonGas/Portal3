<?php
namespace App\Core;

use Throwable;

class ErrorHandler
{
    private static bool $responseSent = false;

    public static function register(): void
    {
        // Development errors must use the same safe response as production.
        // Whoops' debug page includes request/session/environment details.
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('html_errors', '0');
        error_reporting(E_ALL);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    private static function isJsonRequest(): bool
    {
        // Si es POST/PUT/DELETE/PATCH, asumir API (fetch/axios)
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return true;
        }
        return (
            isset($_SERVER['HTTP_ACCEPT']) &&
            str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
        );
    }

    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        try {
            Logger::error('PHP runtime error', [
                'error_code' => $errno,
                'file' => basename((string) $errfile),
                'line' => $errline,
            ]);
        } catch (Throwable) {
            error_log('Portal3 could not write a PHP runtime diagnostic.');
        }

        // Consume the error so PHP's native handler cannot print its details.
        return true;
    }

    public static function handleException(Throwable $exception)
    {
        try {
            Logger::critical($exception);
        } catch (Throwable) {
            error_log('Portal3 could not write an unhandled exception diagnostic.');
        }
        self::sendGenericResponse();
    }

    public static function handleShutdown()
    {
        $error = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
        if (is_array($error) && in_array((int) ($error['type'] ?? 0), $fatalTypes, true)) {
            try {
                Logger::critical('Fatal runtime error', [
                    'error_type' => $error['type'],
                    'file' => basename((string) ($error['file'] ?? '')),
                    'line' => $error['line'],
                ]);
            } catch (Throwable) {
                error_log('Portal3 could not write a fatal runtime diagnostic.');
            }
            self::sendGenericResponse();
        }
    }

    private static function sendGenericResponse(): void
    {
        if (self::$responseSent) {
            return;
        }
        self::$responseSent = true;

        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }

        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        if (self::isJsonRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo '{"success":false,"message":"Ha ocurrido un error. Contacte al administrador."}';
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo '<!doctype html><html lang="es"><meta charset="utf-8"><title>Error</title><h1>Error interno del servidor</h1><p>No fue posible completar la solicitud.</p></html>';
    }
}

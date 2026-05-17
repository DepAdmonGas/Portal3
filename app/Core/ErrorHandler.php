<?php
namespace App\Core;

use Throwable;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Handler\JsonResponseHandler;

class ErrorHandler
{
    public static function register()
    {
        // SECURITY: Usar valor por defecto 'prod' si no existe APP_ENV (Vulnerabilidad #10)
        $appEnv = $_ENV['APP_ENV'] ?? 'prod';
        
        if ($appEnv === 'dev') {
            // --- Modo desarrollo: página bonita de error ---
            $whoops = new Run;

            // Handler visual (HTML)
            $pageHandler = new PrettyPageHandler();
            $pageHandler->setPageTitle("🚧 Error en " . ($_ENV['APP_NAME'] ?? 'Portal3'));

            // Si es API (acepta JSON), usa el handler JSON automáticamente
            if (self::isJsonRequest()) {
                $whoops->pushHandler(new JsonResponseHandler());
            } else {
                $whoops->pushHandler($pageHandler);
            }

            $whoops->register();
        } else {
            // --- Modo producción: solo logs, nunca exponer detalles ---
            set_error_handler([self::class, 'handleError']);
            set_exception_handler([self::class, 'handleException']);
            register_shutdown_function([self::class, 'handleShutdown']);
        }
    }

    private static function isJsonRequest(): bool
    {
        return (
            isset($_SERVER['HTTP_ACCEPT']) &&
            str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')
        );
    }

    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        $logger = Logger::getLogger();
        $logger->error("PHP Error [$errno]: $errstr en $errfile:$errline");
        return false;
    }

    public static function handleException(Throwable $exception)
    {
        $logger = Logger::getLogger();
        
        // SECURITY: Loguear detalles completos pero nunca exponer al usuario (Vulnerabilidad #10)
        $logger->critical("Excepción no controlada", [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);

        http_response_code(500);
        
        // Si es solicitud JSON, responder con formato consistente
        if (self::isJsonRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Ha ocurrido un error. Contacte al administrador.'
            ]);
        } else {
            echo "<h1>Error interno del servidor</h1>";
        }
    }

    public static function handleShutdown()
    {
        $error = error_get_last();
        if ($error !== null) {
            $logger = Logger::getLogger();
            $logger->critical("Error fatal: {$error['message']} en {$error['file']} línea {$error['line']}");
        }
    }
}

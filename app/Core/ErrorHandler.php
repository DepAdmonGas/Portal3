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
        if ($_ENV['APP_ENV'] === 'dev') {
            // --- Modo desarrollo: página bonita de error ---
            $whoops = new Run;

            // Handler visual (HTML)
            $pageHandler = new PrettyPageHandler();
            $pageHandler->setPageTitle("🚧 Error en {$_ENV['APP_NAME']}");

            // Si es API (acepta JSON), usa el handler JSON automáticamente
            if (self::isJsonRequest()) {
                $whoops->pushHandler(new JsonResponseHandler());
            } else {
                $whoops->pushHandler($pageHandler);
            }

            $whoops->register();
        } else {
            // --- Modo producción: logs + mensaje genérico ---
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
        $logger->critical(
            "Excepción: " . $exception->getMessage() .
            " en " . $exception->getFile() . ":" . $exception->getLine()
        );

        http_response_code(500);
        echo "<h1>Error interno del servidor</h1>";
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

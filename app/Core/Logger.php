<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;
use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

final class Logger
{
    private static ?MonoLogger $logger = null;

    /**
     * Obtiene la instancia de Monolog
     */
    private static function instance(): MonoLogger
    {
        if (self::$logger === null) {

            $logger = new MonoLogger(
                $_ENV['APP_NAME'] ?? 'Application'
            );

            $defaultPath = dirname(__DIR__, 2) . '/storage/logs/app.log';

            $logPath = $_ENV['LOG_PATH'] ?? $defaultPath;

            // Si la ruta es relativa la convertimos a absoluta
            if (!str_starts_with($logPath, '/')) {
                $logPath = dirname(__DIR__, 2) . '/' . ltrim($logPath, '/');
            }

            // Crear directorio si no existe
            $directory = dirname($logPath);

            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $handler = new StreamHandler(
                $logPath,
                MonoLogger::DEBUG
            );

            $handler->setFormatter(
                new LineFormatter(
                    "[%datetime%] %level_name%: %message% %context%\n",
                    "Y-m-d H:i:s",
                    true,
                    true
                )
            );

            $logger->pushHandler($handler);

            self::$logger = $logger;
        }

        return self::$logger;
    }

    public static function debug(
        string $message,
        array $context = []
    ): void {
        self::instance()->debug($message, $context);
    }

    public static function info(
        string $message,
        array $context = []
    ): void {
        self::instance()->info($message, $context);
    }

    public static function notice(
        string $message,
        array $context = []
    ): void {
        self::instance()->notice($message, $context);
    }

    public static function warning(
        string $message,
        array $context = []
    ): void {
        self::instance()->warning($message, $context);
    }

    public static function error(
        string $message,
        array $context = []
    ): void {
        self::instance()->error($message, $context);
    }

    public static function critical(
        string|Throwable $message,
        array $context = []
    ): void {

        if ($message instanceof Throwable) {

            self::instance()->critical(
                $message->getMessage(),
                array_merge(
                    [
                        'file'  => $message->getFile(),
                        'line'  => $message->getLine(),
                        'trace' => $message->getTraceAsString()
                    ],
                    $context
                )
            );

            return;
        }

        self::instance()->critical(
            $message,
            $context
        );
    }

    public static function alert(
        string $message,
        array $context = []
    ): void {
        self::instance()->alert($message, $context);
    }

    public static function emergency(
        string $message,
        array $context = []
    ): void {
        self::instance()->emergency($message, $context);
    }
}

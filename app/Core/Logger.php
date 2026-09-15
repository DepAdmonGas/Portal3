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

    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_CONTEXT_KEYS = [
        'password', 'password_confirmation', 'token', 'access_token', 'refresh_token',
        'authorization', 'cookie', 'session', 'session_id', 'csrf_token', 'secret',
        'webhook_secret', 'telegram_webhook_secret', 'api_key', 'api_token',
        'headers', 'body', 'request_body', 'response_body', 'payload', 'document_path',
        'filename', 'sql', 'query',
    ];

    private const PII_CONTEXT_KEYS = [
        'usuario', 'username', 'email', 'ip', 'remote_addr', 'phone', 'telefono',
        'address', 'direccion', 'curp', 'rfc', 'nss', 'ine',
    ];

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
                    false,
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
        self::instance()->debug(self::sanitizeMessage($message), self::sanitizeContext($context));
    }

    public static function info(
        string $message,
        array $context = []
    ): void {
        self::instance()->info(self::sanitizeMessage($message), self::sanitizeContext($context));
    }

    public static function notice(
        string $message,
        array $context = []
    ): void {
        self::instance()->notice(self::sanitizeMessage($message), self::sanitizeContext($context));
    }

    public static function warning(
        string $message,
        array $context = []
    ): void {
        self::instance()->warning(self::sanitizeMessage($message), self::sanitizeContext($context));
    }

    public static function error(
        string $message,
        array $context = []
    ): void {
        self::instance()->error(self::sanitizeMessage($message), self::sanitizeContext($context));
    }

    public static function critical(
        string|Throwable $message,
        array $context = []
    ): void {

        if ($message instanceof Throwable) {

            self::instance()->critical(
                'Unhandled exception',
                self::sanitizeContext(array_merge(
                    [
                        'exception_class' => $message::class,
                        'file' => $message->getFile(),
                        'line' => $message->getLine(),
                    ],
                    $context
                ))
            );

            return;
        }

        self::instance()->critical(
            self::sanitizeMessage($message),
            self::sanitizeContext($context)
        );
    }

    public static function alert(
        string $message,
        array $context = []
    ): void {
        self::instance()->alert(self::sanitizeMessage($message), self::sanitizeContext($context));
    }

    public static function emergency(
        string $message,
        array $context = []
    ): void {
        self::instance()->emergency(self::sanitizeMessage($message), self::sanitizeContext($context));
    }

    private static function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $normalizedKey = strtolower(str_replace('-', '_', (string) $key));
            if (in_array($normalizedKey, self::SENSITIVE_CONTEXT_KEYS, true)
                || in_array($normalizedKey, self::PII_CONTEXT_KEYS, true)) {
                $sanitized[$key] = self::REDACTED;
                continue;
            }

            $sanitized[$key] = is_array($value)
                ? self::sanitizeContext($value)
                : self::sanitizeValue($value);
        }

        return $sanitized;
    }

    private static function sanitizeMessage(string $message): string
    {
        return str_replace(["\r", "\n"], ' ', $message);
    }

    private static function sanitizeValue(mixed $value): mixed
    {
        return is_string($value)
            ? str_replace(["\r", "\n"], ' ', $value)
            : $value;
    }
}

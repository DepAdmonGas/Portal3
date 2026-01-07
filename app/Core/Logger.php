<?php
namespace App\Core;

use Monolog\Logger as MonoLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static $logger = null;

    public static function getLogger(): MonoLogger
    {
        if (self::$logger === null) {
            $log = new MonoLogger($_ENV['APP_NAME'] ?? 'app');

            $handler = new StreamHandler(__DIR__ . '/../../storage/logs/app.log', MonoLogger::DEBUG);

            $formatter = new LineFormatter("[%datetime%] %level_name%: %message%\n", "Y-m-d H:i:s");
            $handler->setFormatter($formatter);

            $log->pushHandler($handler);

            self::$logger = $log;
        }

        return self::$logger;
    }
}

<?php
namespace App\Core;

use Carbon\Carbon;

class Bootstrap
{
    public static function init(): void
    {
        // Configurar zona horaria de PHP
        date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');

        // Configurar Carbon globalmente
        Carbon::setLocale($_ENV['APP_LOCALE'] ?? 'en');
        Carbon::setToStringFormat($_ENV['APP_DATE_FORMAT'] ?? 'Y-m-d H:i:s');
    }
}

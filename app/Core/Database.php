<?php

namespace App\Core;

use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    public static function initialize(): void
    {
        $capsule = new Capsule;

        $default = [
            'driver'    => $_ENV['DB_CONNECTION'] ?? '',
            'host'      => $_ENV['DB_HOST'] ?? '',
            'port'      => $_ENV['DB_PORT'] ?? null,
            'database'  => $_ENV['DB_DATABASE'] ?? '',
            'username'  => $_ENV['DB_USERNAME'] ?? '',
            'password'  => $_ENV['DB_PASSWORD'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ];
        $capsule->addConnection($default, 'default');

        $readPrivateEnv = static function (string $key): string {
            $value = $_ENV[$key] ?? null;
            if (!is_string($value) || trim($value) === '') {
                $value = getenv($key);
            }
            return is_string($value) ? trim($value) : '';
        };
        $m04Host = $readPrivateEnv('DB_M04_HOST');
        $m04Database = $readPrivateEnv('DB_M04_DATABASE');
        $m04Username = $readPrivateEnv('DB_M04_USERNAME');
        $m04PasswordValue = $_ENV['DB_M04_PASSWORD'] ?? null;
        if (!is_string($m04PasswordValue) || $m04PasswordValue === '') {
            $m04PasswordValue = getenv('DB_M04_PASSWORD');
        }
        $m04Password = is_string($m04PasswordValue) ? $m04PasswordValue : '';
        $m04Port = $readPrivateEnv('DB_M04_PORT');
        $validPort = $m04Port === '' || (ctype_digit($m04Port) && (int) $m04Port >= 1 && (int) $m04Port <= 65535);
        if ($m04Host !== '' && $m04Database !== '' && $m04Username !== ''
            && $m04Password !== '' && $validPort) {
            $capsule->addConnection([
                'driver'    => 'mysql',
                'host'      => $m04Host,
                'port'      => $m04Port === '' ? 3306 : (int) $m04Port,
                'database'  => $m04Database,
                'username'  => $m04Username,
                'password'  => $m04Password,
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
            ], 'checador_m04');
        }

        // Hacer disponible Eloquent globalmente
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
    /**
     * Permit coverage writes only through the already-configured local Portal3
     * PDO connection when it is demonstrably attached to development bd_admongas.
     */
    public static function isLocalDevelopmentDatabaseConnection(): bool
    {
        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        if (!in_array($environment, ['dev', 'development', 'local'], true)) {
            return false;
        }

        try {
            $connection = Capsule::connection();
            $config = $connection->getConfig();
            $host = strtolower(trim((string) ($config['host'] ?? '')));
            if (($config['driver'] ?? '') !== 'mysql'
                || ($config['database'] ?? '') !== 'bd_admongas'
                || !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                return false;
            }

            $identity = $connection->selectOne(
                'SELECT DATABASE() AS database_name, CURRENT_USER() AS principal, @@hostname AS server_name'
            );

            return ($identity->database_name ?? null) === 'bd_admongas'
                && trim((string) ($identity->principal ?? '')) !== ''
                && trim((string) ($identity->server_name ?? '')) !== '';
        } catch (\Throwable) {
            return false;
        }
    }

}

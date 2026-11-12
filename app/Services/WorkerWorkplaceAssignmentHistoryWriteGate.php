<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;

/** Explicit opt-in for HR assignment history writes; unset and all other values are off. */
final class WorkerWorkplaceAssignmentHistoryWriteGate
{
    public const ENVIRONMENT_KEY = 'CHECADOR_V2_WORKPLACE_HISTORY_WRITES';
    public const BASELINE_IMPORT_KEY = 'CHECADOR_V2_PREPROD_BASELINE_IMPORT';
    public const EXPECTED_DB_HOST_KEY = 'CHECADOR_V2_PREPROD_DB_HOST_EXPECTED';
    public const EXPECTED_DB_SERVER_KEY = 'CHECADOR_V2_PREPROD_DB_SERVER_HOSTNAME_EXPECTED';
    public const EXPECTED_DB_PORT_KEY = 'CHECADOR_V2_PREPROD_DB_PORT_EXPECTED';
    public const PREPRODUCTION_DATABASE = 'bd_admongas_v2_preprod';

    public static function enabled(): bool
    {
        $value = $_ENV[self::ENVIRONMENT_KEY]
            ?? $_SERVER[self::ENVIRONMENT_KEY]
            ?? getenv(self::ENVIRONMENT_KEY);

        return is_string($value) && $value === '1';
    }

    /**
     * The initial baseline has its own preproduction-only gate. The generic
     * history gate remains unchanged for other write paths and is not enabled
     * as a side effect of this one-off import. Missing pins or DB metadata fail closed.
     */
    public static function baselineImportEnabled(): bool
    {
        $baselineFlag = self::environmentValue(self::BASELINE_IMPORT_KEY);
        $environment = strtolower(trim(self::environmentValue('APP_ENV') ?? ''));
        $expectedHost = strtolower(trim(self::environmentValue(self::EXPECTED_DB_HOST_KEY) ?? ''));
        $expectedServer = strtolower(trim(self::environmentValue(self::EXPECTED_DB_SERVER_KEY) ?? ''));
        $expectedPort = self::positivePort(self::environmentValue(self::EXPECTED_DB_PORT_KEY));

        if ($baselineFlag !== '1'
            || $environment !== 'preproduction'
            || $expectedHost === ''
            || $expectedServer === ''
            || $expectedPort === null) {
            return false;
        }

        try {
            $connection = Capsule::connection();
            $config = $connection->getConfig();
            if (($config['driver'] ?? null) !== 'mysql') {
                return false;
            }
            $configuredHost = strtolower(trim((string) ($config['host'] ?? '')));
            $configuredPort = self::positivePort($config['port'] ?? null);
            if ((string) ($config['database'] ?? '') !== self::PREPRODUCTION_DATABASE
                || $configuredHost !== $expectedHost
                || $configuredPort !== $expectedPort) {
                return false;
            }

            $identity = $connection->selectOne(
                'SELECT DATABASE() AS database_name, @@hostname AS server_hostname, @@port AS server_port'
            );

            return self::baselineImportContextAllows([
                'app_env' => $environment,
                'baseline_flag' => $baselineFlag,
                'configured_driver' => $config['driver'] ?? null,
                'expected_db_host' => $expectedHost,
                'expected_server_hostname' => $expectedServer,
                'expected_db_port' => $expectedPort,
                'configured_database' => (string) ($config['database'] ?? ''),
                'configured_db_host' => $configuredHost,
                'configured_db_port' => $configuredPort,
                'database_name' => (string) ($identity->database_name ?? ''),
                'server_hostname' => strtolower(trim((string) ($identity->server_hostname ?? ''))),
                'server_port' => self::positivePort($identity->server_port ?? null),
            ]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Pure policy seam for isolated tests; no environment or database access. */
    public static function baselineImportContextAllows(array $context): bool
    {
        $expectedHost = strtolower(trim((string) ($context['expected_db_host'] ?? '')));
        $expectedServer = strtolower(trim((string) ($context['expected_server_hostname'] ?? '')));
        $expectedPort = self::positivePort($context['expected_db_port'] ?? null);

        return strtolower(trim((string) ($context['app_env'] ?? ''))) === 'preproduction'
            && ($context['baseline_flag'] ?? null) === '1'
            && $expectedHost !== ''
            && $expectedServer !== ''
            && $expectedPort !== null
            && ($context['configured_driver'] ?? null) === 'mysql'
            && (string) ($context['configured_database'] ?? '') === self::PREPRODUCTION_DATABASE
            && (string) ($context['database_name'] ?? '') === self::PREPRODUCTION_DATABASE
            && strtolower(trim((string) ($context['configured_db_host'] ?? ''))) === $expectedHost
            && strtolower(trim((string) ($context['server_hostname'] ?? ''))) === $expectedServer
            && self::positivePort($context['configured_db_port'] ?? null) === $expectedPort
            && self::positivePort($context['server_port'] ?? null) === $expectedPort;
    }

    private static function environmentValue(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return is_string($value) ? $value : null;
    }

    private static function positivePort(mixed $value): ?int
    {
        $port = filter_var($value, FILTER_VALIDATE_INT);
        return $port !== false && $port > 0 && $port <= 65535 ? $port : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Throwable;

/** M01-only, read-only access to persisted raw events and historical time zones. */
final class ChecadorDailyAttendanceSqlReadService
{
    private const RAW_TABLE = 'op_rh_v2_marcaciones_crudas';
    private const MAX_EVENTS = 4000;
    private const READ_TABLES = [
        'op_rh_v2_marcaciones_crudas',
        'op_rh_localidades',
        'tb_estaciones',
        'op_rh_v2_localidades_zonas_horarias',
        'op_rh_personal',
        'op_rh_personal_localidad_version',
    ];

    private ?Connection $testConnection;
    /** @var callable(int):bool|null */
    private $authorizeLocality;

    /** Test seams accept only an in-memory SQLite connection while APP_ENV=test. */
    public function __construct(?Connection $testConnection = null, ?callable $authorizeLocality = null)
    {
        if ($testConnection !== null
            && (strtolower(self::environment()) !== 'test'
                || $testConnection->getDriverName() !== 'sqlite'
                || $testConnection->getConfig('database') !== ':memory:')) {
            throw new \InvalidArgumentException('M01 test connections are restricted to in-memory SQLite tests.');
        }
        if ($authorizeLocality !== null && $testConnection === null) {
            throw new \InvalidArgumentException('M01 authorization overrides are restricted to isolated tests.');
        }
        $this->testConnection = $testConnection;
        $this->authorizeLocality = $authorizeLocality;
    }

    /** @param list<int> $authorizedLocalityIds */
    public function resolveCurrentLocalDay(int $localityId, array $authorizedLocalityIds): array
    {
        if (!$this->isAuthorized($localityId, $authorizedLocalityIds)) {
            return self::failure('locality_not_authorized');
        }
        try {
            $connection = $this->readConnection();
            $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $candidateDate = $nowUtc->format('Y-m-d');
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $window = $this->resolveLocalDay($connection, $localityId, $candidateDate);
                if (!($window['ok'] ?? false)) {
                    return $window;
                }
                $resolvedDate = $nowUtc->setTimezone(new \DateTimeZone($window['data']['timezone']))->format('Y-m-d');
                if ($resolvedDate === $candidateDate) {
                    $window['data']['local_date'] = $resolvedDate;
                    return $window;
                }
                $candidateDate = $resolvedDate;
            }
            return self::failure('timezone_unresolved');
        } catch (Throwable $exception) {
            $this->logFailure('current_local_day', $localityId, null, $exception);
            return self::failure('database_unavailable');
        }
    }

    /** @param list<int> $authorizedLocalityIds */
    public function readDay(
        int $localityId,
        string $localDate,
        array $authorizedLocalityIds,
        ?int $workerId = null
    ): array {
        if (!$this->isAuthorized($localityId, $authorizedLocalityIds)) {
            return self::failure('locality_not_authorized');
        }
        if (!self::isCivilDate($localDate) || ($workerId !== null && $workerId < 1)) {
            return self::failure('bad_request');
        }

        try {
            $connection = $this->readConnection();
            $window = $this->resolveLocalDay($connection, $localityId, $localDate);
            if (!($window['ok'] ?? false)) {
                return $window;
            }
            $query = $connection->table(self::RAW_TABLE)
                ->where('id_estacion_resuelta', '=', $window['data']['physical_station_id'])
                ->whereNotNull('ocurrido_at_utc')
                ->where('ocurrido_at_utc', '>=', $window['data']['from_utc'])
                ->where('ocurrido_at_utc', '<', $window['data']['to_utc']);
            if ($workerId !== null) {
                $query->where('id_personal_resuelto', '=', $workerId);
            }
            $rows = $query->select([
                'id', 'event_uuid', 'id_dispositivo', 'id_estacion_resuelta', 'id_personal_resuelto',
                'source_timestamp_raw', 'source_timezone_reported', 'effective_timezone',
                'timezone_fallback_applied', 'ocurrido_at_utc', 'recibido_at_utc', 'metodo_captura',
                'external_event_id', 'secuencia_dispositivo', 'processing_status',
                'integrity_version', 'integrity_key_id',
            ])->selectRaw(
                "CASE WHEN integrity_seal IS NULL OR LENGTH(integrity_seal) = 0 THEN 0 ELSE 1 END AS integrity_seal_present"
            )->orderBy('ocurrido_at_utc')->orderBy('id')->limit(self::MAX_EVENTS + 1)->get();
            if ($rows->count() > self::MAX_EVENTS) {
                return self::failure('read_limit_exceeded');
            }

            $events = [];
            foreach ($rows as $row) {
                $event = (array) $row;
                if ((int) ($event['id_estacion_resuelta'] ?? 0) !== (int) $window['data']['physical_station_id']) {
                    return self::failure('locality_scope_mismatch');
                }
                $event['integrity_seal_present'] = (int) ($event['integrity_seal_present'] ?? 0) === 1;
                $event['integrity_format_status'] = 'NOT_VERIFIED_BY_PORTAL3';
                $event['integrity_verification_status'] = 'NOT_VERIFIED_BY_PORTAL3';
                $event['local_time'] = self::formatLocalTime(
                    (string) ($event['ocurrido_at_utc'] ?? ''),
                    (string) $window['data']['timezone']
                );
                $events[] = $event;
            }

            return [
                'ok' => true,
                'locality_id' => $localityId,
                'physical_station_id' => $window['data']['physical_station_id'],
                'local_date' => $localDate,
                'timezone' => $window['data']['timezone'],
                'from_utc' => $window['data']['from_utc'],
                'to_utc' => $window['data']['to_utc'],
                'events' => $events,
                'consistency' => 'LIVE_NON_SNAPSHOT',
                'source_completeness' => 'UNKNOWN',
            ];
        } catch (Throwable $exception) {
            $this->logFailure('read_day', $localityId, $localDate, $exception);
            return self::failure('database_unavailable');
        }
    }

    /** @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string}} */
    private function resolveLocalDay(Connection $connection, int $localityId, string $localDate): array
    {
        if (!$this->schemaReady($connection)) {
            return self::failure('schema_unavailable');
        }
        $mapping = $connection->select(
            'SELECT locality.id AS locality_id, locality.numlista, station.id AS physical_station_id, station.estatus '
            . 'FROM op_rh_localidades AS locality '
            . 'INNER JOIN tb_estaciones AS station ON station.numlista = locality.numlista '
            . 'WHERE locality.id = ? ORDER BY station.id LIMIT 2',
            [$localityId]
        );
        if (count($mapping) !== 1
            || (int) ($mapping[0]->locality_id ?? 0) !== $localityId
            || (int) ($mapping[0]->numlista ?? 0) < 1
            || (int) ($mapping[0]->estatus ?? -1) !== 0) {
            return self::failure('station_mapping_unavailable');
        }
        $sameNumber = $connection->select(
            'SELECT id FROM op_rh_localidades WHERE numlista = ? ORDER BY id LIMIT 2',
            [(int) $mapping[0]->numlista]
        );
        if (count($sameNumber) !== 1 || (int) ($sameNumber[0]->id ?? 0) !== $localityId) {
            return self::failure('station_mapping_unavailable');
        }

        $zones = $connection->select(
            'SELECT timezone_iana FROM op_rh_v2_localidades_zonas_horarias '
            . 'WHERE id_localidad = ? AND valido_desde <= ? '
            . 'AND (valido_hasta IS NULL OR valido_hasta > ?) ORDER BY valido_desde DESC LIMIT 2',
            [$localityId, $localDate, $localDate]
        );
        if (count($zones) !== 1) {
            return self::failure('timezone_unresolved');
        }
        $timezone = trim((string) ($zones[0]->timezone_iana ?? ''));
        if (!ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
            return self::failure('timezone_unresolved');
        }
        try {
            $zone = new \DateTimeZone($timezone);
            $fromLocal = new \DateTimeImmutable($localDate . ' 00:00:00', $zone);
            if ($fromLocal->format('Y-m-d') !== $localDate) {
                return self::failure('timezone_unresolved');
            }
            $toLocal = $fromLocal->modify('+1 day');
            return [
                'ok' => true,
                'data' => [
                    'locality_id' => $localityId,
                    'physical_station_id' => (int) $mapping[0]->physical_station_id,
                    'timezone' => $timezone,
                    'from_utc' => $fromLocal->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'to_utc' => $toLocal->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ],
            ];
        } catch (Throwable) {
            return self::failure('timezone_unresolved');
        }
    }

    private function schemaReady(Connection $connection): bool
    {
        $required = [
            'op_rh_localidades' => ['id', 'numlista'],
            'tb_estaciones' => ['id', 'numlista', 'estatus'],
            'op_rh_v2_localidades_zonas_horarias' => ['id_localidad', 'timezone_iana', 'valido_desde', 'valido_hasta'],
            'op_rh_v2_marcaciones_crudas' => [
                'id', 'event_uuid', 'id_dispositivo', 'id_estacion_resuelta', 'id_personal_resuelto',
                'source_timestamp_raw', 'source_timezone_reported', 'effective_timezone',
                'timezone_fallback_applied', 'ocurrido_at_utc', 'recibido_at_utc', 'metodo_captura',
                'external_event_id', 'secuencia_dispositivo', 'processing_status',
                'integrity_version', 'integrity_key_id', 'integrity_seal',
            ],
            'op_rh_personal' => ['id', 'id_estacion', 'nombre_completo', 'no_colaborador', 'estado'],
            'op_rh_personal_localidad_version' => [
                'id', 'worker_id', 'workplace_id', 'valid_from_utc', 'valid_to_utc', 'recorded_at_utc',
            ],
        ];
        $schema = $connection->getSchemaBuilder();
        foreach ($required as $table => $columns) {
            if (!$schema->hasTable($table) || !$schema->hasColumns($table, $columns)) {
                return false;
            }
        }
        return true;
    }

    /** @param list<int> $authorizedLocalityIds */
    private function isAuthorized(int $localityId, array $authorizedLocalityIds): bool
    {
        if ($localityId < 1 || !in_array($localityId, $authorizedLocalityIds, true)) {
            return false;
        }
        if ($this->authorizeLocality !== null) {
            return (bool) ($this->authorizeLocality)($localityId);
        }
        return Auth::user() !== null
            && (ChecadorAccessService::authorize($localityId)['allowed'] ?? false) === true;
    }

    private function readConnection(): Connection
    {
        if ($this->testConnection !== null) {
            return $this->testConnection;
        }
        $connection = Capsule::connection('default');
        $config = $connection->getConfig();
        $identity = $connection->selectOne(
            'SELECT DATABASE() AS schema_name, @@hostname AS server_hostname, @@port AS server_port, CURRENT_USER() AS principal'
        );
        if (self::approvedRuntimeProfile(self::environment(), $config, $identity) === null) {
            throw new \RuntimeException('M01 SQL connection identity is outside the approved Fedora/preproduction targets.');
        }
        self::verifySelectPermissions($connection);
        return $connection;
    }

    private static function verifySelectPermissions(Connection $connection): void
    {
        foreach (self::READ_TABLES as $table) {
            // A zero-row SELECT confirms effective table-level SELECT without reading business data.
            $connection->table($table)->selectRaw('1')->limit(0)->get();
        }
    }

    /**
     * Validate the configured and effective identity for one of the two approved destinations.
     * Preproduction is bound to its exact schema and approved runtime environment, not to a host name.
     *
     * @param array<string,mixed> $config
     */
    private static function approvedRuntimeProfile(string $environment, array $config, object $identity): ?string
    {
        $environment = strtolower(trim($environment));
        $driver = strtolower(trim((string) ($config['driver'] ?? '')));
        $configuredDatabase = trim((string) ($config['database'] ?? ''));
        $effectiveDatabase = trim((string) ($identity->schema_name ?? ''));
        $configuredPort = (int) ($config['port'] ?? 3306);
        if ($configuredPort < 1) $configuredPort = 3306;
        $effectivePort = (int) ($identity->server_port ?? 0);
        $serverHostname = strtolower(trim((string) ($identity->server_hostname ?? '')));
        $principal = trim((string) ($identity->principal ?? ''));
        if ($driver !== 'mysql'
            || $configuredDatabase === ''
            || $effectiveDatabase !== $configuredDatabase
            || $serverHostname === ''
            || $principal === ''
            || $effectivePort !== $configuredPort) {
            return null;
        }

        $configuredHost = strtolower(trim((string) ($config['host'] ?? '')));
        if (in_array($environment, ['dev', 'development', 'local'], true)
            && $configuredDatabase === 'bd_admongas'
            && in_array($configuredHost, ['localhost', '127.0.0.1', '::1'], true)
            && $serverHostname === 'fedora'
            && $configuredPort === 3306
            && $effectivePort === 3306) {
            return 'fedora';
        }

        if (in_array($environment, ['preprod', 'preproduction', 'staging', 'prod', 'production'], true)
            && $configuredDatabase === 'bd_admongas_v2_preprod'
            && $effectiveDatabase === 'bd_admongas_v2_preprod') {
            return 'preproduction';
        }

        return null;
    }

    private function logFailure(string $stage, int $localityId, ?string $date, Throwable $exception): void
    {
        try {
            $sqlState = $exception instanceof \Illuminate\Database\QueryException
                ? (string) ($exception->errorInfo[0] ?? '')
                : '';
            Logger::error('Checador M01 SQL read failed', [
                'stage' => $stage,
                'reason' => $exception instanceof \Illuminate\Database\QueryException ? 'sql_read_failed' : 'read_unavailable',
                'exception_class' => $exception::class,
                'sqlstate' => preg_match('/\A[A-Z0-9]{5}\z/', $sqlState) === 1 ? $sqlState : null,
                'locality_id' => $localityId,
                'work_date' => $date,
            ]);
        } catch (Throwable) {
            // Diagnostics must not alter the safe read result.
        }
    }

    private static function formatLocalTime(string $utcValue, string $timezone): ?array
    {
        if ($utcValue === '') {
            return null;
        }
        try {
            $local = (new \DateTimeImmutable($utcValue, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone($timezone));
            return ['date' => $local->format('Y-m-d'), 'time' => $local->format('H:i'), 'utc' => $utcValue];
        } catch (Throwable) {
            return null;
        }
    }

    private static function isCivilDate(string $date): bool
    {
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches) !== 1) {
            return false;
        }
        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private static function environment(): string
    {
        return (string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '');
    }

    /** @return array{ok:false,error:array{kind:string}} */
    private static function failure(string $kind): array
    {
        return ['ok' => false, 'error' => ['kind' => $kind]];
    }
}

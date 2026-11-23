<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Models\Usuario;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** M04 SQL-only append-only writer. Access and readiness are checked in Portal3 at request time. */
final class ChecadorShiftTemplateSqlWriteService
{
    public const CONNECTION_NAME = 'default';

    /** @var callable(Usuario,int):bool */
    private $authorizeWrite;
    /** @var callable():bool */
    private $gate;
    /** @var callable():bool */
    private $schemaPreflight;
    /** @var callable(Usuario,string,int,int,int):array|null */
    private $readback;

    public function __construct(?callable $authorizeWrite = null, ?callable $gate = null, ?callable $schemaPreflight = null, ?callable $readback = null)
    {
        $this->authorizeWrite = $authorizeWrite ?? static function (Usuario $actor, int $workplaceId): bool {
            $authenticated = Auth::user();
            return $authenticated !== null
                && (int) ($authenticated->id ?? 0) === (int) ($actor->id ?? 0)
                && (ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false);
        };
        $this->gate = $gate ?? static fn(): bool => self::writeGateOpen();
        $this->schemaPreflight = $schemaPreflight ?? fn(): bool => $this->mariadbSchemaReady();
        $this->readback = $readback ?? static fn(Usuario $actor, string $key, int $version, int $workplaceId, int $id): array =>
            (new ChecadorShiftTemplateSqlReadService(null, self::CONNECTION_NAME))
                ->getShiftTemplateVersion($actor, $key, $version, $workplaceId, $id, 'STATION', $workplaceId);
    }

    /** Fedora's owner-approved local exception is bound to the actual local bd_admongas connection. */
    public static function writeGateOpen(): bool
    {
        if (ChecadorDailyReadService::demoModeEnabled()) return false;

        try {
            $connection = Capsule::connection(self::CONNECTION_NAME);
            $identity = $connection->selectOne('SELECT DATABASE() AS database_name');
            $config = $connection->getConfig();
            if (self::isLocalDevelopmentTarget(
                (string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: ''),
                (string) ($config['driver'] ?? ''),
                (string) ($config['host'] ?? ''),
                (string) ($config['database'] ?? ''),
                (string) ($identity->database_name ?? '')
            )) {
                return true;
            }
        } catch (Throwable) {
            // If the local target cannot be positively identified, use the normal closed-by-default gate.
        }

        foreach ([
            'CHECADOR_M04_SQL_WRITER_ENABLED',
            'CHECADOR_M04_API_WRITER_CLOSED',
            'CHECADOR_M04_SQL_GRANTS_VERIFIED',
            'CHECADOR_M04_SQL_PREFLIGHT_VERIFIED',
        ] as $gate) {
            $value = strtolower(trim((string) ($_ENV[$gate] ?? getenv($gate) ?: '')));
            if (!in_array($value, ['1', 'true', 'yes', 'on'], true)) return false;
        }

        return true;
    }

    /** Local exception requires dev APP_ENV plus the exact local MySQL database identity. */
    private static function isLocalDevelopmentTarget(
        string $environment,
        string $driver,
        string $host,
        string $configuredDatabase,
        string $actualDatabase
    ): bool {
        return in_array(strtolower(trim($environment)), ['dev', 'development', 'local'], true)
            && strtolower(trim($driver)) === 'mysql'
            && in_array(strtolower(trim($host)), ['localhost', '127.0.0.1', '::1'], true)
            && $configuredDatabase === 'bd_admongas'
            && $actualDatabase === 'bd_admongas';
    }

    /** UI and handlers require a live schema and effective-grant preflight. */
    public static function isOperationallyReady(): bool
    {
        return self::writeGateOpen() && (new self())->mariadbSchemaReady();
    }

    /** @return array{ok:bool,template?:array<string,mixed>,error?:string,status?:int,replayed?:bool} */
    public function write(Usuario $actor, int $workplaceId, array $input): array
    {
        $operation = is_string($input['operation'] ?? null) ? trim($input['operation']) : '';
        $name = trim((string) ($input['template_name'] ?? ''));
        $type = strtoupper(trim((string) ($input['template_type'] ?? '')));
        $segments = $this->normalizeSegments($input['segments'] ?? null);
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        $templateKey = trim((string) ($input['template_key'] ?? ''));
        $expectedVersion = filter_var($input['expected_version'] ?? null, FILTER_VALIDATE_INT);
        $sourceKey = trim((string) ($input['source_template_key'] ?? ''));
        $sourceVersion = filter_var($input['source_template_version'] ?? null, FILTER_VALIDATE_INT);

        if ($workplaceId < 1 || (int) ($actor->id ?? 0) < 1
            || !in_array($operation, ['create', 'version', 'customize'], true)
            || $name === '' || mb_strlen($name) > 128 || preg_match('/\A[A-Z][A-Z0-9_]{0,31}\z/D', $type) !== 1
            || $segments === [] || strlen($key) < 16 || strlen($key) > 128
            || preg_match('/\A[A-Za-z0-9._:-]+\z/D', $key) !== 1
            || ($operation === 'version' && ($templateKey === '' || strlen($templateKey) > 64 || $expectedVersion === false || $expectedVersion < 1))
            || ($operation === 'customize' && ($sourceKey === '' || strlen($sourceKey) > 100 || $sourceVersion === false || $sourceVersion < 1))) {
            return self::failure('invalid_input', 422);
        }
        if ($operation === 'create' && ($input['confirm_action'] ?? '') !== '1') return self::failure('confirmation_required', 422);
        if (!($this->authorizeWrite)($actor, $workplaceId)) return self::failure('permission_denied', 403);
        if (!($this->gate)()) return self::failure('demo_mode_read_only', 403);
        if (!($this->schemaPreflight)()) return self::failure('m04_sql_schema_not_ready', 503);

        $fingerprint = hash('sha256', json_encode([
            'operation' => $operation, 'workplace_id' => $workplaceId, 'actor_id' => (int) $actor->id,
            'template_key' => $templateKey, 'expected_version' => $expectedVersion === false ? null : $expectedVersion,
            'source_template_key' => $sourceKey, 'source_template_version' => $sourceVersion === false ? null : $sourceVersion,
            'name' => $name, 'type' => $type, 'segments' => $segments,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $keyHash = hash('sha256', $key);
        $connection = Capsule::connection(self::CONNECTION_NAME);

        try {
            $result = $connection->transaction(function () use (
                $connection, $actor, $workplaceId, $operation, $name, $type, $segments, $templateKey,
                $expectedVersion, $sourceKey, $sourceVersion, $keyHash, $fingerprint
            ): array {
                if (!($this->authorizeWrite)($actor, $workplaceId) || !($this->gate)()) {
                    throw new DomainException('permission_denied');
                }
                $workplace = $connection->table('op_rh_localidades')->where('id', $workplaceId)->lockForUpdate()->first(['id']);
                if ($workplace === null) throw new DomainException('workplace_not_found');

                $replay = $this->findIdempotentResult($connection, (int) $actor->id, $workplaceId, $operation, $keyHash);
                if ($replay !== null) {
                    if (!hash_equals((string) ($replay['fingerprint'] ?? ''), $fingerprint)) throw new DomainException('idempotency_conflict');
                    return ['id' => (int) $replay['template_id'], 'key' => (string) $replay['template_key'],
                        'version' => (int) $replay['version'], 'replayed' => true];
                }

                if ($operation === 'create') {
                    $this->assertNameAvailable($connection, $workplaceId, $name);
                    $normalized = strtoupper((string) (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name));
                    $slug = trim((string) preg_replace('/[^A-Z0-9]+/', '_', $normalized), '_');
                    $newKey = substr('ST' . $workplaceId . '_' . $slug . '_' . strtoupper(bin2hex(random_bytes(4))), 0, 64);
                    $templateId = $this->insertVersion($connection, (int) $actor->id, $workplaceId, $newKey, 1, $name, $type, $segments);
                    $auditOperation = 'CREATE';
                } elseif ($operation === 'version') {
                    $current = $connection->table('op_rh_v2_turnos_plantillas')->where('scope', 'STATION')
                        ->where('id_estacion', $workplaceId)->where('clave', $templateKey)->orderByDesc('version')->lockForUpdate()->first();
                    if ($current === null || (int) $current->es_activo !== 1) throw new DomainException('template_not_found');
                    if ((int) $current->version !== (int) $expectedVersion) throw new DomainException('version_conflict');
                    $this->assertNameAvailable($connection, $workplaceId, $name, $templateKey, true);
                    $newKey = $templateKey;
                    $newVersion = (int) $current->version + 1;
                    $templateId = $this->insertVersion($connection, (int) $actor->id, $workplaceId, $newKey, $newVersion, $name, $type, $segments);
                    $auditOperation = 'VERSION';
                } else {
                    if (ChecadorAccessService::isDepartmentLocality($workplaceId)
                        && $workplaceId !== ChecadorAccessService::AUTOLAVADO_LOCALITY_ID) {
                        throw new DomainException('permission_denied');
                    }
                    $source = $connection->table('op_rh_v2_turnos_plantillas')->where('clave', $sourceKey)
                        ->where('version', $sourceVersion)->where('scope', 'GLOBAL')->whereNull('id_estacion')
                        ->where('es_activo', 1)->lockForUpdate()->first();
                    if ($source === null) throw new DomainException('source_not_found');
                    $latestSourceVersion = $connection->table('op_rh_v2_turnos_plantillas')->where('clave', $sourceKey)
                        ->where('scope', 'GLOBAL')->whereNull('id_estacion')->max('version');
                    if ((int) $latestSourceVersion !== (int) $sourceVersion) throw new DomainException('source_not_found');
                    $lineage = $connection->table('op_rh_v2_shift_template_station_lineage')->where('id_estacion', $workplaceId)
                        ->where('source_global_clave', $sourceKey)->lockForUpdate()->first();
                    if ($lineage !== null) throw new DomainException('customization_exists');
                    $this->assertNameAvailable($connection, $workplaceId, $name, null, true);
                    $localKey = substr('ST' . $workplaceId . '_C_' . strtoupper(substr(hash('sha256', $sourceKey . ':' . $sourceVersion . ':' . $keyHash), 0, 28)), 0, 64);
                    $templateId = $this->insertVersion($connection, (int) $actor->id, $workplaceId, $localKey, 1, $name, $type, $segments);
                    $connection->table('op_rh_v2_shift_template_station_lineage')->insert([
                        'id_estacion' => $workplaceId, 'source_global_clave' => $sourceKey,
                        'source_global_version' => (int) $sourceVersion, 'source_global_template_id' => (int) $source->id,
                        'local_clave' => $localKey, 'local_template_id' => $templateId,
                        'created_by_user_id' => (int) $actor->id, 'created_at_utc' => $this->utcNow($connection),
                    ]);
                    $newKey = $localKey;
                    $newVersion = 1;
                    $auditOperation = 'CUSTOMIZE';
                }

                $version = $operation === 'version' ? $newVersion : 1;
                $persisted = $this->readExactWithinTransaction($connection, $templateId, $newKey, $version, $workplaceId);
                if ($persisted === null || $persisted['segmentos'] !== $segments) throw new DomainException('readback_mismatch');
                $details = json_encode([
                    'idempotency_key_hash' => $keyHash, 'payload_fingerprint' => $fingerprint,
                    'template_id' => $templateId, 'template_key' => $newKey, 'version' => $version,
                    'scope' => 'STATION', 'segments' => $segments,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $connection->table('op_rh_v2_turnos_audit_log')->insert([
                    'entity_type' => 'TEMPLATE', 'entity_id' => $templateId, 'operacion' => $auditOperation,
                    'id_usuario_actor' => (int) $actor->id, 'id_estacion' => $workplaceId,
                    'id_personal' => null, 'tipo_asignacion' => null, 'valido_desde' => null, 'valido_hasta' => null,
                    'id_plantilla' => $templateId, 'version_plantilla' => $version, 'detalle_json' => $details,
                    'recorded_at_utc' => $this->utcNow($connection),
                ]);
                $audit = $this->findIdempotentResult($connection, (int) $actor->id, $workplaceId, $operation, $keyHash);
                if ($audit === null || !hash_equals($fingerprint, (string) ($audit['fingerprint'] ?? ''))) {
                    throw new DomainException('audit_readback_failed');
                }
                return ['id' => $templateId, 'key' => $newKey, 'version' => $version, 'replayed' => false];
            }, 1);

            $reader = new ChecadorShiftTemplateSqlReadService(null, self::CONNECTION_NAME);
            $readback = ($this->readback)($actor, $result['key'], $result['version'], $workplaceId, $result['id']);
            $template = $readback['data'] ?? null;
            if (!($readback['ok'] ?? false) || !is_array($template) || ($template['segmentos'] ?? null) !== $segments) {
                return self::failure('readback_unconfirmed', 502);
            }
            return ['ok' => true, 'template' => $template, 'replayed' => (bool) $result['replayed']];
        } catch (Throwable $exception) {
            $error = $exception instanceof DomainException ? $exception->getMessage() : 'database_write_failed';
            $known = ['permission_denied', 'workplace_not_found', 'invalid_input', 'idempotency_conflict', 'template_not_found',
                'version_conflict', 'source_not_found', 'customization_exists', 'duplicate_template_name', 'readback_mismatch', 'audit_readback_failed'];
            if (!in_array($error, $known, true)) $error = 'database_write_failed';
            try {
                Logger::warning('Checador M04 SQL template write failed', [
                    'operation' => 'm04_template_' . $operation, 'workplace_id' => $workplaceId,
                    'actor_id' => (int) $actor->id, 'error_code' => $error, 'exception_class' => $exception::class,
                ]);
            } catch (Throwable) {
                // Logging is best-effort; a failed write remains a failed response.
            }
            $status = match ($error) {
                'permission_denied' => 403,
                'invalid_input' => 422,
                'idempotency_conflict', 'version_conflict', 'customization_exists', 'duplicate_template_name' => 409,
                'template_not_found', 'source_not_found', 'workplace_not_found' => 404,
                default => 503,
            };
            return self::failure($error, $status);
        }
    }

    private function insertVersion($connection, int $actorId, int $workplaceId, string $key, int $version, string $name, string $type, array $segments): int
    {
        $id = (int) $connection->table('op_rh_v2_turnos_plantillas')->insertGetId([
            'clave' => $key, 'version' => $version, 'nombre' => $name, 'tipo_turno' => $type,
            'scope' => 'STATION', 'id_estacion' => $workplaceId, 'es_activo' => 1,
            'creado_por_usuario_id' => $actorId, 'creado_at' => $this->utcNow($connection), 'actualizado_at' => $this->utcNow($connection),
        ]);
        foreach ($segments as $segment) {
            $connection->table('op_rh_v2_turnos_segmentos')->insert(['id_plantilla' => $id] + $segment);
        }
        return $id;
    }

    private function assertNameAvailable($connection, int $workplaceId, string $name, ?string $sameKey = null, bool $stationOnly = false): void
    {
        $rows = $connection->table('op_rh_v2_turnos_plantillas')->where('nombre', $name)->where('es_activo', 1)
            ->where(static function ($query) use ($workplaceId, $stationOnly): void {
                if (!$stationOnly) $query->where('scope', 'GLOBAL')->whereNull('id_estacion')->orWhere(static fn($local) => $local->where('scope', 'STATION')->where('id_estacion', $workplaceId));
                else $query->where('scope', 'STATION')->where('id_estacion', $workplaceId);
            })->get(['clave', 'scope', 'id_estacion', 'version']);
        foreach ($rows as $row) {
            if ($sameKey !== null && (string) $row->clave === $sameKey && (string) $row->scope === 'STATION'
                && (int) $row->id_estacion === $workplaceId) continue;
            $max = $connection->table('op_rh_v2_turnos_plantillas')->where('clave', $row->clave)->where('scope', $row->scope);
            $row->scope === 'GLOBAL' ? $max->whereNull('id_estacion') : $max->where('id_estacion', $workplaceId);
            if ((int) $row->version === (int) $max->max('version')) throw new DomainException('duplicate_template_name');
        }
    }

    private function readExactWithinTransaction($connection, int $id, string $key, int $version, int $workplaceId): ?array
    {
        $row = $connection->table('op_rh_v2_turnos_plantillas')->where('id', $id)->where('clave', $key)
            ->where('version', $version)->where('scope', 'STATION')->where('id_estacion', $workplaceId)->first();
        if ($row === null) return null;
        $segments = $connection->table('op_rh_v2_turnos_segmentos')->where('id_plantilla', $id)->orderBy('ordinal')->get();
        if ($segments->isEmpty()) return null;
        $normalized = $segments->map(static fn($segment): array => [
            'ordinal' => (int) $segment->ordinal, 'tipo_segmento' => (string) $segment->tipo_segmento,
            'hora_inicio_local' => (string) $segment->hora_inicio_local, 'hora_fin_local' => (string) $segment->hora_fin_local,
            'dia_offset_inicio' => (int) $segment->dia_offset_inicio, 'dia_offset_fin' => (int) $segment->dia_offset_fin,
        ])->all();
        return ['id' => (int) $row->id, 'clave' => (string) $row->clave, 'version' => (int) $row->version,
            'nombre' => (string) $row->nombre, 'tipo_turno' => (string) $row->tipo_turno, 'scope' => 'STATION',
            'id_estacion' => (int) $row->id_estacion, 'es_activo' => (int) $row->es_activo, 'segmentos' => $normalized];
    }

    private function findIdempotentResult($connection, int $actorId, int $workplaceId, string $operation, string $keyHash): ?array
    {
        $auditOperation = ['create' => 'CREATE', 'version' => 'VERSION', 'customize' => 'CUSTOMIZE'][$operation];
        $query = $connection->table('op_rh_v2_turnos_audit_log')->where('entity_type', 'TEMPLATE')
            ->where('id_usuario_actor', $actorId)->where('id_estacion', $workplaceId)->where('operacion', $auditOperation);
        $driver = $connection->getDriverName();
        $expr = $driver === 'sqlite'
            ? "json_extract(detalle_json, '$.idempotency_key_hash')"
            : "JSON_UNQUOTE(JSON_EXTRACT(detalle_json, '$.idempotency_key_hash'))";
        $row = $query->whereRaw($expr . ' = ?', [$keyHash])->orderByDesc('id')->first(['detalle_json']);
        if ($row === null) return null;
        $detail = json_decode((string) $row->detalle_json, true);
        if (!is_array($detail)) return null;
        return ['fingerprint' => $detail['payload_fingerprint'] ?? '', 'template_id' => $detail['template_id'] ?? 0,
            'template_key' => $detail['template_key'] ?? '', 'version' => $detail['version'] ?? 0];
    }

    private function normalizeSegments(mixed $raw): array
    {
        if (!is_array($raw) || !array_is_list($raw) || !in_array(count($raw), [1, 3], true)) return [];
        $segments = [];
        $previousEnd = null;
        foreach ($raw as $index => $segment) {
            if (!is_array($segment)) return [];
            $kind = strtoupper(trim((string) ($segment['tipo_segmento'] ?? '')));
            $start = trim((string) ($segment['hora_inicio_local'] ?? ''));
            $end = trim((string) ($segment['hora_fin_local'] ?? ''));
            $startOffset = filter_var($segment['dia_offset_inicio'] ?? null, FILTER_VALIDATE_INT);
            $endOffset = filter_var($segment['dia_offset_fin'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($kind, ['WORK', 'BREAK'], true)
                || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?\z/D', $start) !== 1
                || preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?\z/D', $end) !== 1
                || !in_array($startOffset, [0, 1], true) || !in_array($endOffset, [0, 1], true)) return [];
            $start = strlen($start) === 5 ? $start . ':00' : $start;
            $end = strlen($end) === 5 ? $end . ':00' : $end;
            $startMinute = $startOffset * 1440 + (int) substr($start, 0, 2) * 60 + (int) substr($start, 3, 2);
            $endMinute = $endOffset * 1440 + (int) substr($end, 0, 2) * 60 + (int) substr($end, 3, 2);
            if ($endMinute <= $startMinute || ($previousEnd !== null && $previousEnd !== $startMinute) || ($index === 0 && $startOffset !== 0)) return [];
            $segments[] = ['ordinal' => $index + 1, 'tipo_segmento' => $kind, 'hora_inicio_local' => $start,
                'hora_fin_local' => $end, 'dia_offset_inicio' => $startOffset, 'dia_offset_fin' => $endOffset];
            $previousEnd = $endMinute;
        }
        if (array_column($segments, 'tipo_segmento') !== (count($segments) === 1 ? ['WORK'] : ['WORK', 'BREAK', 'WORK'])) return [];
        return $segments;
    }

    private function mariadbSchemaReady(): bool
    {
        try {
            $connection = Capsule::connection(self::CONNECTION_NAME);
            if ($connection->getDriverName() !== 'mysql') return false;
            $identity = $connection->selectOne(
                "SELECT DATABASE() AS database_name, CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1))) AS grantee"
            );
            $databaseName = trim((string) ($identity->database_name ?? ''));
            $grantee = trim((string) ($identity->grantee ?? ''));
            $config = $connection->getConfig();
            $localDevelopmentException = self::isLocalDevelopmentTarget(
                (string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: ''),
                (string) ($config['driver'] ?? ''),
                (string) ($config['host'] ?? ''),
                (string) ($config['database'] ?? ''),
                $databaseName
            );
            $activeRole = strtoupper(trim((string) ($connection->selectOne('SELECT CURRENT_ROLE() AS role_name')->role_name ?? 'NONE')));
            if ($databaseName === '' || $grantee === ''
                || (!$localDevelopmentException && $activeRole !== '' && $activeRole !== 'NONE')
                || (!$localDevelopmentException && !self::hasRequiredM04Grants($connection->select(
                    "SELECT 'GLOBAL' AS grant_scope, NULL AS table_schema, NULL AS table_name, PRIVILEGE_TYPE AS privilege_type FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = ?
                    UNION ALL
                    SELECT 'SCHEMA', TABLE_SCHEMA, NULL, PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE = ?
                    UNION ALL
                    SELECT 'TABLE', TABLE_SCHEMA, TABLE_NAME, PRIVILEGE_TYPE FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE = ? AND TABLE_NAME IN (?, ?, ?, ?, ?)",
                    [$grantee, $grantee, $grantee,
                        'op_rh_localidades', 'op_rh_v2_turnos_plantillas', 'op_rh_v2_turnos_segmentos',
                        'op_rh_v2_shift_template_station_lineage', 'op_rh_v2_turnos_audit_log']
                ), $databaseName, $localDevelopmentException))) return false;
            $tables = ['op_rh_localidades', 'op_rh_v2_turnos_plantillas', 'op_rh_v2_turnos_segmentos',
                'op_rh_v2_shift_template_station_lineage', 'op_rh_v2_turnos_audit_log'];
            foreach ($tables as $table) {
                $status = $connection->selectOne('SHOW TABLE STATUS LIKE ' . $connection->getPdo()->quote($table));
                if ($status === null || strtoupper((string) ($status->Engine ?? '')) !== 'INNODB') return false;
            }
            $required = [
                'op_rh_v2_turnos_plantillas' => ['id','clave','version','nombre','tipo_turno','scope','id_estacion','scope_id','es_activo','creado_por_usuario_id','creado_at','actualizado_at'],
                'op_rh_v2_turnos_segmentos' => ['id','id_plantilla','ordinal','tipo_segmento','hora_inicio_local','hora_fin_local','dia_offset_inicio','dia_offset_fin'],
                'op_rh_v2_shift_template_station_lineage' => ['id','id_estacion','source_global_clave','source_global_version','source_global_template_id','local_clave','local_template_id','created_by_user_id','created_at_utc'],
                'op_rh_v2_turnos_audit_log' => ['id','entity_type','entity_id','operacion','id_usuario_actor','id_estacion','id_plantilla','version_plantilla','detalle_json','recorded_at_utc'],
            ];
            foreach ($required as $table => $columns) {
                $columnRows = $connection->select('SHOW COLUMNS FROM `' . $table . '`');
                $byName = [];
                foreach ($columnRows as $column) $byName[(string) $column->Field] = $column;
                if (array_diff($columns, array_keys($byName)) !== []) return false;
                if ($table === 'op_rh_v2_turnos_plantillas'
                    && !str_contains(strtoupper((string) ($byName['scope_id']->Extra ?? '')), 'GENERATED')) return false;
                if ($table === 'op_rh_v2_turnos_audit_log'
                    && !str_starts_with(strtolower((string) ($byName['detalle_json']->Type ?? '')), 'longtext')) return false;
            }
            foreach ([
                'op_rh_v2_turnos_segmentos' => ['fk_v2_segmentos_plantilla'],
                'op_rh_v2_shift_template_station_lineage' => ['fk_v2_shift_lineage_local_template', 'fk_v2_shift_lineage_source_template'],
            ] as $table => $constraints) {
                $create = $connection->selectOne('SHOW CREATE TABLE `' . $table . '`');
                $definition = is_object($create) ? implode(' ', array_map('strval', array_values((array) $create))) : '';
                foreach ($constraints as $constraint) if (!str_contains($definition, $constraint)) return false;
            }
            $jsonProbe = $connection->selectOne("SELECT JSON_UNQUOTE(JSON_EXTRACT('{\"m04_probe\":\"ok\"}', '$.m04_probe')) AS probe_value");
            if (($jsonProbe->probe_value ?? null) !== 'ok') return false;
            foreach ([
                'op_rh_v2_turnos_plantillas' => ['uk_v2_turnos_plantillas_scope_clave_version' => ['scope_id','clave','version']],
                'op_rh_v2_turnos_segmentos' => ['uk_v2_segmento_plantilla_ordinal' => ['id_plantilla','ordinal']],
                'op_rh_v2_shift_template_station_lineage' => [
                    'uq_v2_shift_lineage_station_source' => ['id_estacion','source_global_clave'],
                    'uq_v2_shift_lineage_station_local' => ['id_estacion','local_clave'],
                    'uq_v2_shift_lineage_local_template' => ['local_template_id'],
                ],
            ] as $table => $indexes) {
                $rows = $connection->select('SHOW INDEX FROM `' . $table . '`');
                foreach ($indexes as $name => $columns) {
                    $found = [];
                    $unique = false;
                    foreach ($rows as $row) {
                        if ((string) $row->Key_name !== $name) continue;
                        $found[(int) $row->Seq_in_index] = (string) $row->Column_name;
                        $unique = (int) $row->Non_unique === 0;
                    }
                    ksort($found);
                    if (!$unique || array_values($found) !== $columns) return false;
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** Validate required effective operations; extra grants are reported as risk, not mistaken for missing access. */
    private static function hasRequiredM04Grants(array $grantRows, string $databaseName, bool $allowBroadPrivileges = false): bool
    {
        $schemaMatches = static function (string $candidate) use ($databaseName): bool {
            $normalized = '';
            $length = strlen($candidate);
            for ($index = 0; $index < $length; $index++) {
                $character = $candidate[$index];
                if ($character === '\\' && isset($candidate[$index + 1])
                    && in_array($candidate[$index + 1], ['\\', '_', '%'], true)) {
                    $normalized .= $candidate[++$index];
                    continue;
                }
                $normalized .= $character;
            }
            return hash_equals($databaseName, $normalized);
        };
        $appliesTo = static function (object $row, string $table) use ($schemaMatches): bool {
            $scope = strtoupper((string) ($row->grant_scope ?? ''));
            $schema = (string) ($row->table_schema ?? '');
            $tableName = (string) ($row->table_name ?? '');
            return $scope === 'GLOBAL'
                || ($scope === 'SCHEMA' && $schemaMatches($schema))
                || ($scope === 'TABLE' && $schemaMatches($schema) && $tableName === $table);
        };
        $immutableTables = [
            'op_rh_v2_turnos_plantillas',
            'op_rh_v2_turnos_segmentos',
            'op_rh_v2_shift_template_station_lineage',
            'op_rh_v2_turnos_audit_log',
        ];
        foreach ($immutableTables as $table) {
            foreach ($grantRows as $row) {
                if (!$appliesTo($row, $table)) continue;
                $privilege = strtoupper(trim((string) ($row->privilege_type ?? '')));
                if (!$allowBroadPrivileges && in_array($privilege, ['UPDATE', 'DELETE', 'ALL', 'ALL PRIVILEGES'], true)) return false;
            }
        }

        $has = static function (string $table, string $requiredPrivilege) use ($grantRows, $appliesTo): bool {
            foreach ($grantRows as $row) {
                $privilege = strtoupper(trim((string) ($row->privilege_type ?? '')));
                if ($appliesTo($row, $table) && ($privilege === $requiredPrivilege || in_array($privilege, ['ALL', 'ALL PRIVILEGES'], true))) {
                    return true;
                }
            }
            return false;
        };

        $required = [
            'op_rh_localidades' => ['SELECT'],
            'op_rh_v2_turnos_plantillas' => ['SELECT', 'INSERT'],
            'op_rh_v2_turnos_segmentos' => ['SELECT', 'INSERT'],
            'op_rh_v2_shift_template_station_lineage' => ['SELECT', 'INSERT'],
            'op_rh_v2_turnos_audit_log' => ['SELECT', 'INSERT'],
        ];
        foreach ($required as $table => $privileges) {
            foreach ($privileges as $privilege) {
                if (!$has($table, $privilege)) return false;
            }
        }
        return true;
    }

    private function utcNow($connection): mixed
    {
        return $connection->getDriverName() === 'mysql' ? Capsule::raw('UTC_TIMESTAMP()') : gmdate('Y-m-d H:i:s');
    }

    private static function failure(string $error, int $status): array
    {
        return ['ok' => false, 'error' => $error, 'status' => $status];
    }
}

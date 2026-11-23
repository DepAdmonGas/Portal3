<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Models\Usuario;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** M04 SQL-only append-only writer. Authorization is checked in Portal3 for every operation. */
final class ChecadorShiftTemplateSqlWriteService
{
    public const CONNECTION_NAME = 'default';

    /** @var callable(Usuario,int):bool */
    private $authorizeWrite;
    /** @var callable(Usuario,string,int,int,int):array|null */
    private $readback;

    public function __construct(?callable $authorizeWrite = null, ?callable $readback = null)
    {
        $this->authorizeWrite = $authorizeWrite ?? static function (Usuario $actor, int $workplaceId): bool {
            $authenticated = Auth::user();
            return $authenticated !== null
                && (int) ($authenticated->id ?? 0) === (int) ($actor->id ?? 0)
                && (ChecadorAccessService::authorizeStationTemplateAdministration($workplaceId)['allowed'] ?? false);
        };
        $this->readback = $readback ?? static fn(Usuario $actor, string $key, int $version, int $workplaceId, int $id): array =>
            (new ChecadorShiftTemplateSqlReadService(null, self::CONNECTION_NAME))
                ->getShiftTemplateVersion($actor, $key, $version, $workplaceId, $id, 'STATION', $workplaceId);
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
        if (ChecadorDailyReadService::demoModeEnabled()) return self::failure('demo_mode_read_only', 403);

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
                if (!($this->authorizeWrite)($actor, $workplaceId)) {
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


    private function utcNow($connection): mixed
    {
        return $connection->getDriverName() === 'mysql' ? Capsule::raw('UTC_TIMESTAMP()') : gmdate('Y-m-d H:i:s');
    }

    private static function failure(string $error, int $status): array
    {
        return ['ok' => false, 'error' => $error, 'status' => $status];
    }
}

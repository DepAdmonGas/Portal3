<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalLocalidadVersion;
use App\Models\Usuario;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Canonical M06 SQL reader and gated writer for administrative incidences. */
final class ChecadorAdministrativeIncidenceSqlService
{
    public const CATEGORIES = [
        'VACATION',
        'PERMISSION',
        'DISABILITY',
        'AUTHORIZED_LATENESS',
        'AUTHORIZED_EARLY_DEPARTURE',
        'OFFICIAL_MISSION',
    ];

    private const UUID_NAMESPACE = '98d64f66-bf8b-5d7b-b4d8-49d6f82a73f0';
    private const STATUS_PENDING = 'PENDING_APPROVAL';

    /** @var callable(Usuario,int):bool */
    private $authorizeRead;
    /** @var callable(Usuario,int):bool */
    private $authorizeCreate;
    /** @var callable(int,int,string,string,string):bool */
    private $workerCoversPeriod;
    /** @var callable(int,string):array */
    private $readPolicy;
    /** @var callable():bool */
    private $schemaPreflight;
    /** @var callable():DateTimeImmutable */
    private $clock;
    private bool $apiWriterCutoverVerified;
    private bool $historyProtectionVerified;

    /**
     * Operational cutover/protection default to false. The controller may pass
     * verified attestations only for the local Fedora database; isolated tests
     * can inject their own values. Remote environments remain fail-closed.
     */
    public function __construct(
        ?callable $authorizeRead = null,
        ?callable $authorizeCreate = null,
        ?callable $workerCoversPeriod = null,
        ?callable $readPolicy = null,
        ?callable $schemaPreflight = null,
        bool $apiWriterCutoverVerified = false,
        bool $historyProtectionVerified = false,
        ?callable $clock = null
    ) {
        $this->authorizeRead = $authorizeRead ?? static fn(Usuario $actor, int $workplaceId): bool =>
            (ChecadorAccessService::authorizeIncidenceRead($workplaceId)['allowed'] ?? false) === true;
        $this->authorizeCreate = $authorizeCreate ?? static fn(Usuario $actor, int $workplaceId): bool =>
            (ChecadorAccessService::authorizeIncidenceCreate($workplaceId)['allowed'] ?? false) === true;
        $this->workerCoversPeriod = $workerCoversPeriod ?? [$this, 'canonicalWorkerCoversPeriod'];
        $this->readPolicy = $readPolicy ?? static fn(int $workplaceId, string $date): array =>
            (new ChecadorAttendancePolicyStoreService())->readForEffectiveDate($workplaceId, $date);
        $this->schemaPreflight = $schemaPreflight ?? [$this, 'mariadbSchemaReady'];
        $this->apiWriterCutoverVerified = $apiWriterCutoverVerified;
        $this->historyProtectionVerified = $historyProtectionVerified;
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @return array{ok:bool,data?:array,error?:string} */
    public function listForWorkplace(Usuario $actor, int $workplaceId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if ($workplaceId < 1 || $page < 1 || $perPage < 1 || $perPage > 100) {
            return ['ok' => false, 'error' => 'invalid_request'];
        }
        if (!(($this->authorizeRead)($actor, $workplaceId))) {
            return ['ok' => false, 'error' => 'permission_denied'];
        }
        $normalized = self::normalizeFilters($filters);
        if ($normalized === null) return ['ok' => false, 'error' => 'invalid_filter'];

        try {
            $base = $this->baseListQuery($workplaceId, $normalized);
            $total = (int) (clone $base)->count();
            $rows = $base->orderByDesc('m.fecha_inicio_local')
                ->orderByDesc('m.id')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();
            $items = $rows->map(static fn(object $row): array => self::mapListRow($row))->all();
            $masterIds = array_values(array_filter(array_map(static fn(array $item): int => (int) $item['_master_id'], $items)));
            $revisionMap = [];
            if ($masterIds !== []) {
                $revisionRows = Capsule::connection('default')->table('op_rh_v2_incidencias_revisiones')
                    ->whereIn('id_incidencia_maestro', $masterIds)->orderBy('id_incidencia_maestro')->orderBy('revision_numero')
                    ->get(['id_incidencia_maestro', 'revision_numero', 'estado_revision', 'motivo', 'folio_evidencia',
                        'creado_por_usuario_id', 'aprobado_por_usuario_id', 'creado_at_utc']);
                foreach ($revisionRows as $revision) {
                    $revisionMap[(int) $revision->id_incidencia_maestro][] = [
                        'revision_number' => (int) $revision->revision_numero,
                        'status' => (string) $revision->estado_revision,
                        'reason' => $revision->motivo === null ? null : (string) $revision->motivo,
                        'evidence_reference' => $revision->folio_evidencia === null ? null : (string) $revision->folio_evidencia,
                        'created_by_user_id' => (int) $revision->creado_por_usuario_id,
                        'approved_by_user_id' => $revision->aprobado_por_usuario_id === null ? null : (int) $revision->aprobado_por_usuario_id,
                        'created_at_utc' => (string) $revision->creado_at_utc,
                    ];
                }
            }
            foreach ($items as &$item) {
                $item['revisions'] = $revisionMap[$item['_master_id']] ?? [];
                unset($item['_master_id']);
            }
            unset($item);
            return ['ok' => true, 'data' => [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_records' => $total,
                    'total_pages' => $total === 0 ? 0 : (int) ceil($total / $perPage),
                ],
            ]];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'sql_read_failed'];
        }
    }

    /** @return array{ok:bool,data?:array,error?:string} */
    public function detail(Usuario $actor, int $workplaceId, string $uuid): array
    {
        if ($workplaceId < 1 || !self::isUuid($uuid)) return ['ok' => false, 'error' => 'invalid_request'];
        if (!(($this->authorizeRead)($actor, $workplaceId))) return ['ok' => false, 'error' => 'permission_denied'];
        try {
            $master = Capsule::connection('default')->table('op_rh_v2_incidencias_maestro as m')
                ->leftJoin('op_rh_personal as p', 'p.id', '=', 'm.id_personal')
                ->where('m.uuid_incidencia', $uuid)
                ->where('m.id_localidad', $workplaceId)
                ->select([
                    'm.id', 'm.uuid_incidencia', 'm.id_personal', 'm.id_localidad', 'm.id_estacion',
                    'm.tipo_incidencia', 'm.alcance_incidencia', 'm.fecha_inicio_local', 'm.fecha_fin_local',
                    'm.estado_actual', 'm.revision_actual', 'm.creado_at_utc', 'm.actualizado_at_utc',
                    'p.nombre_completo', 'p.no_colaborador',
                ])->first();
            if ($master === null) return ['ok' => false, 'error' => 'not_found'];
            $revisions = Capsule::connection('default')->table('op_rh_v2_incidencias_revisiones')
                ->where('id_incidencia_maestro', (int) $master->id)
                ->orderBy('revision_numero')
                ->get([
                    'revision_numero', 'estado_revision', 'motivo', 'folio_evidencia',
                    'creado_por_usuario_id', 'aprobado_por_usuario_id', 'creado_at_utc',
                ])->map(static fn(object $row): array => [
                    'revision_number' => (int) $row->revision_numero,
                    'status' => (string) $row->estado_revision,
                    'reason' => $row->motivo === null ? null : (string) $row->motivo,
                    'evidence_reference' => $row->folio_evidencia === null ? null : (string) $row->folio_evidencia,
                    'created_by_user_id' => (int) $row->creado_por_usuario_id,
                    'approved_by_user_id' => $row->aprobado_por_usuario_id === null ? null : (int) $row->aprobado_por_usuario_id,
                    'created_at_utc' => (string) $row->creado_at_utc,
                ])->all();
            return ['ok' => true, 'data' => self::mapDetail($master, $revisions)];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'sql_read_failed'];
        }
    }

    /** @return array{ready:bool,reason:string} */
    public function writeReadiness(): array
    {
        if (!$this->apiWriterCutoverVerified) return ['ready' => false, 'reason' => 'api_writer_cutover_unverified'];
        if (!$this->historyProtectionVerified) return ['ready' => false, 'reason' => 'history_protection_unverified'];
        try {
            if (!(($this->schemaPreflight)())) return ['ready' => false, 'reason' => 'schema_preflight_failed'];
        } catch (Throwable) {
            return ['ready' => false, 'reason' => 'schema_preflight_failed'];
        }
        return ['ready' => true, 'reason' => 'ready'];
    }

    /** @return array{ok:bool,data?:array,error?:string} */
    public function create(
        Usuario $actor,
        int $workplaceId,
        int $workerId,
        string $category,
        string $startDate,
        string $endDate,
        string $reason,
        ?string $evidenceReference,
        string $idempotencyKey
    ): array {
        if ($workplaceId < 1 || $workerId < 1 || !in_array($category, self::CATEGORIES, true)
            || !self::isCivilDate($startDate) || !self::isCivilDate($endDate) || $endDate < $startDate
            || trim($reason) === '' || mb_strlen($reason, 'UTF-8') > 5000
            || ($evidenceReference !== null && mb_strlen($evidenceReference, 'UTF-8') > 100)
            || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/D', $idempotencyKey)) {
            return ['ok' => false, 'error' => 'invalid_request'];
        }
        if (!(($this->authorizeCreate)($actor, $workplaceId))) return ['ok' => false, 'error' => 'permission_denied'];
        $readiness = $this->writeReadiness();
        if (!$readiness['ready']) return ['ok' => false, 'error' => $readiness['reason']];

        $reason = trim(str_replace(["\r\n", "\r"], "\n", $reason));
        $evidenceReference = $evidenceReference === null || trim($evidenceReference) === '' ? null : trim($evidenceReference);
        $uuid = self::uuidV5ForIdempotencyKey($idempotencyKey);
        $policy = $this->policyForPeriod($workplaceId, $startDate, $endDate);
        if (!($policy['ok'] ?? false)) return ['ok' => false, 'error' => (string) ($policy['error'] ?? 'policy_unavailable')];
        if (!(($this->workerCoversPeriod)($workerId, $workplaceId, $startDate, $endDate, (string) $policy['timezone']))) {
            return ['ok' => false, 'error' => 'worker_scope_unverified'];
        }

        $connection = Capsule::connection('default');
        try {
            $created = $connection->transaction(function () use (
                $connection, $actor, $workplaceId, $workerId, $category, $startDate, $endDate,
                $reason, $evidenceReference, $uuid
            ): array {
                $locality = RhLocalidad::query()->whereKey($workplaceId)->lockForUpdate()->first();
                if ($locality === null) throw new DomainException('workplace_not_found');
                $worker = RhPersonal::query()->whereKey($workerId)->where('estado', 1)->lockForUpdate()->first();
                if ($worker === null) throw new DomainException('worker_not_found');

                $existing = Capsule::connection('default')->table('op_rh_v2_incidencias_maestro')
                    ->where('uuid_incidencia', $uuid)->lockForUpdate()->first();
                if ($existing !== null) {
                    $revision = Capsule::connection('default')->table('op_rh_v2_incidencias_revisiones')
                        ->where('id_incidencia_maestro', (int) $existing->id)
                        ->where('revision_numero', 1)->lockForUpdate()->first();
                    if (!self::sameCreatePayload($existing, $revision, $actor, $workplaceId, $workerId, $category,
                        $startDate, $endDate, $reason, $evidenceReference)) {
                        throw new DomainException('idempotency_conflict');
                    }
                    return ['uuid' => $uuid, 'replayed' => true];
                }

                $createdAt = ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $masterId = (int) Capsule::connection('default')->table('op_rh_v2_incidencias_maestro')->insertGetId([
                    'uuid_incidencia' => $uuid,
                    'id_personal' => $workerId,
                    'id_estacion' => null,
                    'id_localidad' => $workplaceId,
                    'tipo_incidencia' => $category,
                    'alcance_incidencia' => 'WORKER',
                    'fecha_inicio_local' => $startDate,
                    'fecha_fin_local' => $endDate,
                    'estado_actual' => self::STATUS_PENDING,
                    'revision_actual' => 1,
                    'creado_at_utc' => $createdAt,
                    'actualizado_at_utc' => $createdAt,
                ]);
                if ($masterId < 1) throw new DomainException('master_insert_failed');
                Capsule::connection('default')->table('op_rh_v2_incidencias_revisiones')->insert([
                    'id_incidencia_maestro' => $masterId,
                    'revision_numero' => 1,
                    'estado_revision' => self::STATUS_PENDING,
                    'motivo' => $reason,
                    'folio_evidencia' => $evidenceReference,
                    'creado_por_usuario_id' => (int) $actor->id,
                    'aprobado_por_usuario_id' => null,
                    'creado_at_utc' => $createdAt,
                ]);
                return ['uuid' => $uuid, 'replayed' => false];
            });
        } catch (DomainException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable) {
            // A concurrent replay can win the unique UUID index. Re-read and
            // compare the persisted original payload before calling it success.
            $created = ['uuid' => $uuid, 'replayed' => true];
        }

        $readback = $this->detail($actor, $workplaceId, (string) $created['uuid']);
        $data = $readback['data'] ?? [];
        $first = is_array($data['revisions'][0] ?? null) ? $data['revisions'][0] : [];
        if (($readback['ok'] ?? false) !== true
            || (int) ($data['worker_id'] ?? 0) !== $workerId
            || (int) ($data['workplace_id'] ?? 0) !== $workplaceId
            || (string) ($data['category'] ?? '') !== $category
            || (string) ($data['scope'] ?? '') !== 'WORKER'
            || (string) ($data['start_date'] ?? '') !== $startDate
            || (string) ($data['end_date'] ?? '') !== $endDate
            || (string) ($data['current_status'] ?? '') !== self::STATUS_PENDING
            || (int) ($data['current_revision'] ?? 0) !== 1
            || (int) ($first['revision_number'] ?? 0) !== 1
            || (string) ($first['status'] ?? '') !== self::STATUS_PENDING
            || (int) ($first['created_by_user_id'] ?? 0) !== (int) $actor->id
            || (string) ($first['reason'] ?? '') !== $reason
            || ($first['evidence_reference'] ?? null) !== $evidenceReference) {
            return ['ok' => false, 'error' => 'readback_unconfirmed'];
        }
        return ['ok' => true, 'data' => ['incidence' => $data, 'replayed' => (bool) ($created['replayed'] ?? false)]];
    }

    /** Deterministic v5 aggregate identity; the idempotency key remains client-held on retry. */
    public static function uuidV5ForIdempotencyKey(string $key): string
    {
        $namespace = hex2bin(str_replace('-', '', self::UUID_NAMESPACE));
        if ($namespace === false) throw new DomainException('invalid_uuid_namespace');
        $hash = sha1($namespace . 'PORTAL3-M06:' . $key, true);
        $hash[6] = chr((ord($hash[6]) & 0x0f) | 0x50);
        $hash[8] = chr((ord($hash[8]) & 0x3f) | 0x80);
        $hex = bin2hex(substr($hash, 0, 16));
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }

    /** Read-only preflight; history/cutover attestations are separately required. */
    public function mariadbSchemaReady(): bool
    {
        try {
            $connection = Capsule::connection('default');
            if ($connection->getDriverName() !== 'mysql') return false;
            $schema = Capsule::schema('default');
            $required = [
                'op_rh_v2_incidencias_maestro' => [
                    'id', 'uuid_incidencia', 'id_personal', 'id_estacion', 'id_localidad', 'tipo_incidencia',
                    'alcance_incidencia', 'fecha_inicio_local', 'fecha_fin_local', 'estado_actual', 'revision_actual',
                    'creado_at_utc', 'actualizado_at_utc',
                ],
                'op_rh_v2_incidencias_revisiones' => [
                    'id', 'id_incidencia_maestro', 'revision_numero', 'estado_revision', 'motivo', 'folio_evidencia',
                    'creado_por_usuario_id', 'aprobado_por_usuario_id', 'creado_at_utc',
                ],
            ];
            foreach ($required as $table => $columns) {
                foreach ($columns as $column) if (!$schema->hasColumn($table, $column)) return false;
            }
            $nullableColumns = $connection->select(
                "SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS "
                . "WHERE TABLE_SCHEMA = DATABASE() AND ((TABLE_NAME = ? AND COLUMN_NAME = ?) "
                . "OR (TABLE_NAME = ? AND COLUMN_NAME = ?))",
                ['op_rh_v2_incidencias_maestro', 'id_estacion', 'op_rh_v2_incidencias_revisiones', 'aprobado_por_usuario_id']
            );
            $nullableMap = [];
            foreach ($nullableColumns as $column) {
                $nullableMap[$column->TABLE_NAME . '.' . $column->COLUMN_NAME] = strtoupper((string) $column->IS_NULLABLE);
            }
            if (($nullableMap['op_rh_v2_incidencias_maestro.id_estacion'] ?? '') !== 'YES'
                || ($nullableMap['op_rh_v2_incidencias_revisiones.aprobado_por_usuario_id'] ?? '') !== 'YES') {
                return false;
            }
            foreach ([
                'op_rh_localidades', 'op_rh_personal', 'op_rh_personal_localidad_version',
                'op_rh_v2_localidades_zonas_horarias', 'op_rh_v2_attendance_policy_versions',
            ] as $table) {
                if (!$schema->hasTable($table)) return false;
            }
            $tables = $connection->select(
                "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?, ?)",
                array_keys($required)
            );
            if (count($tables) !== 2) return false;
            foreach ($tables as $table) if (strtoupper((string) $table->ENGINE) !== 'INNODB') return false;
            $indexes = $connection->select(
                "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND ((TABLE_NAME = ? AND INDEX_NAME = ?) OR (TABLE_NAME = ? AND INDEX_NAME = ?)) ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
                ['op_rh_v2_incidencias_maestro', 'uk_v2_incidencia_uuid', 'op_rh_v2_incidencias_revisiones', 'uk_v2_incid_rev_numero']
            );
            $indexColumns = [];
            foreach ($indexes as $index) {
                if ((int) $index->NON_UNIQUE !== 0) return false;
                $indexColumns[$index->TABLE_NAME . '.' . $index->INDEX_NAME][] = (string) $index->COLUMN_NAME;
            }
            if (($indexColumns['op_rh_v2_incidencias_maestro.uk_v2_incidencia_uuid'] ?? []) !== ['uuid_incidencia']
                || ($indexColumns['op_rh_v2_incidencias_revisiones.uk_v2_incid_rev_numero'] ?? []) !== ['id_incidencia_maestro', 'revision_numero']) return false;
            $foreignKeys = $connection->select(
                "SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND ((TABLE_NAME = ? AND CONSTRAINT_NAME = ?) OR (TABLE_NAME = ? AND CONSTRAINT_NAME = ?))",
                ['op_rh_v2_incidencias_maestro', 'fk_v2_incid_maestro_localidad', 'op_rh_v2_incidencias_revisiones', 'fk_v2_incid_rev_maestro']
            );
            $fkMap = [];
            foreach ($foreignKeys as $foreignKey) {
                $fkMap[$foreignKey->TABLE_NAME . '.' . $foreignKey->CONSTRAINT_NAME] = [
                    (string) $foreignKey->COLUMN_NAME,
                    (string) $foreignKey->REFERENCED_TABLE_NAME,
                    (string) $foreignKey->REFERENCED_COLUMN_NAME,
                ];
            }
            if (($fkMap['op_rh_v2_incidencias_maestro.fk_v2_incid_maestro_localidad'] ?? []) !== ['id_localidad', 'op_rh_localidades', 'id']
                || ($fkMap['op_rh_v2_incidencias_revisiones.fk_v2_incid_rev_maestro'] ?? []) !== ['id_incidencia_maestro', 'op_rh_v2_incidencias_maestro', 'id']) return false;

            $grantee = (string) ($connection->selectOne(
                "SELECT CONCAT(QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', 1)), '@', QUOTE(SUBSTRING_INDEX(CURRENT_USER(), '@', -1))) AS grantee"
            )->grantee ?? '');
            if ($grantee === '') return false;
            $tablesNeedingSelect = [
                'op_rh_localidades', 'op_rh_personal', 'op_rh_personal_localidad_version',
                'op_rh_v2_localidades_zonas_horarias', 'op_rh_v2_attendance_policy_versions',
                'op_rh_v2_incidencias_maestro', 'op_rh_v2_incidencias_revisiones',
            ];
            foreach ($tablesNeedingSelect as $table) {
                if (!$this->hasEffectiveTablePrivilege($connection, $grantee, $table, 'SELECT')) return false;
            }
            foreach (['op_rh_v2_incidencias_maestro', 'op_rh_v2_incidencias_revisiones'] as $table) {
                if (!$this->hasEffectiveTablePrivilege($connection, $grantee, $table, 'INSERT')) return false;
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function hasEffectiveTablePrivilege(mixed $connection, string $grantee, string $table, string $required): bool
    {
        $rows = $connection->select(
            "SELECT PRIVILEGE_TYPE FROM information_schema.USER_PRIVILEGES WHERE GRANTEE = ?\n"
            . "UNION SELECT PRIVILEGE_TYPE FROM information_schema.SCHEMA_PRIVILEGES WHERE GRANTEE = ? AND TABLE_SCHEMA = DATABASE()\n"
            . "UNION SELECT PRIVILEGE_TYPE FROM information_schema.TABLE_PRIVILEGES WHERE GRANTEE = ? AND TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$grantee, $grantee, $grantee, $table]
        );
        foreach ($rows as $row) {
            $privilege = strtoupper((string) $row->PRIVILEGE_TYPE);
            if ($privilege === 'ALL PRIVILEGES' || $privilege === $required) return true;
        }
        return false;
    }

    /** @return array{ok:bool,timezone?:string,error?:string} */
    private function policyForPeriod(int $workplaceId, string $startDate, string $endDate): array
    {
        try {
            $start = ($this->readPolicy)($workplaceId, $startDate);
            $end = $startDate === $endDate ? $start : ($this->readPolicy)($workplaceId, $endDate);
            if (!($start['ok'] ?? false) || !($end['ok'] ?? false)) return ['ok' => false, 'error' => 'policy_unavailable'];
            $startData = $start['data'] ?? [];
            $endData = $end['data'] ?? [];
            $timezone = (string) ($endData['timezone_id'] ?? '');
            if (!ChecadorOperationalPeriod::isIanaTimezone($timezone)
                || !ChecadorOperationalPeriod::isIanaTimezone((string) ($startData['timezone_id'] ?? ''))
                || (string) $startData['timezone_id'] !== $timezone) {
                return ['ok' => false, 'error' => 'workplace_timezone_unresolved'];
            }
            if (($endData['status'] ?? '') !== 'VERSIONED'
                || !is_array($endData['policy'] ?? null)
                || !is_int($endData['policy']['incidence_deadline_days'] ?? null)
                || $endData['policy']['incidence_deadline_days'] < 0
                || $endData['policy']['incidence_deadline_days'] > 30) {
                return ['ok' => false, 'error' => 'policy_unavailable'];
            }
            $deadlineDays = $endData['policy']['incidence_deadline_days'];
            $cutoffDate = (new DateTimeImmutable($endDate, new DateTimeZone($timezone)))
                ->modify('+' . $deadlineDays . ' days')->format('Y-m-d');
            $today = ($this->clock)()->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
            if ($today > $endDate && $today > $cutoffDate) return ['ok' => false, 'error' => 'registration_deadline_expired'];
            return ['ok' => true, 'timezone' => $timezone];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'policy_unavailable'];
        }
    }

    private function canonicalWorkerCoversPeriod(int $workerId, int $workplaceId, string $startDate, string $endDate, string $timezone): bool
    {
        try {
            if (!RhPersonal::query()->whereKey($workerId)->where('estado', 1)->exists()
                || !RhLocalidad::query()->whereKey($workplaceId)->exists()) return false;
            $zone = new DateTimeZone($timezone);
            $rangeStart = (new DateTimeImmutable($startDate . ' 00:00:00', $zone))
                ->setTimezone(new DateTimeZone('UTC'));
            $rangeEnd = (new DateTimeImmutable($endDate . ' 00:00:00', $zone))->modify('+1 day')
                ->setTimezone(new DateTimeZone('UTC'));
            $asOf = ($this->clock)()->setTimezone(new DateTimeZone('UTC'));
            $points = [$rangeStart->format('Y-m-d\TH:i:s.u\Z') => $rangeStart];
            $versions = RhPersonalLocalidadVersion::query()->where('worker_id', $workerId)
                ->where('recorded_at_utc', '<=', $asOf->format('Y-m-d H:i:s.u'))->orderBy('id')->get([
                    'valid_from_utc', 'valid_to_utc', 'recorded_at_utc', 'supersedes_id',
                ]);
            foreach ($versions as $version) {
                foreach ([$version->valid_from_utc, $version->valid_to_utc] as $raw) {
                    if ($raw === null) continue;
                    $point = new DateTimeImmutable((string) $raw, new DateTimeZone('UTC'));
                    if ($point > $rangeStart && $point < $rangeEnd) {
                        $points[$point->format('Y-m-d\TH:i:s.u\Z')] = $point;
                    }
                }
            }
            ksort($points, SORT_STRING);
            $resolver = new WorkerWorkplaceAssignmentResolver($this->clock);
            foreach ($points as $point) {
                if ($point >= $rangeEnd) continue;
                $resolved = $resolver->resolveAssignments(
                    $workerId,
                    $point->format('Y-m-d\TH:i:s.u\Z'),
                    $asOf->format('Y-m-d\TH:i:s.u\Z')
                );
                $assignments = $resolved['assignments'] ?? [];
                if (($resolved['status'] ?? '') !== 'RESOLVED_SINGLE' || count($assignments) !== 1
                    || (int) ($assignments[0]['workplace_id'] ?? 0) !== $workplaceId) return false;
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function baseListQuery(int $workplaceId, array $filters): mixed
    {
        $query = Capsule::connection('default')->table('op_rh_v2_incidencias_maestro as m')
            ->leftJoin('op_rh_v2_incidencias_revisiones as r', static function ($join): void {
                $join->on('r.id_incidencia_maestro', '=', 'm.id')->on('r.revision_numero', '=', 'm.revision_actual');
            })
            ->leftJoin('op_rh_personal as p', 'p.id', '=', 'm.id_personal')
            ->where('m.id_localidad', $workplaceId)
            ->select([
                'm.uuid_incidencia', 'm.id_personal', 'm.id_localidad', 'm.tipo_incidencia', 'm.alcance_incidencia',
                'm.fecha_inicio_local', 'm.fecha_fin_local', 'm.estado_actual', 'm.revision_actual',
                'm.id as _master_id', 'm.creado_at_utc', 'p.nombre_completo', 'p.no_colaborador', 'r.motivo', 'r.folio_evidencia',
                'r.creado_por_usuario_id', 'r.creado_at_utc as revision_creado_at_utc',
            ]);
        if ($filters['worker_id'] !== null) $query->where('m.id_personal', $filters['worker_id']);
        if ($filters['category'] !== null) $query->where('m.tipo_incidencia', $filters['category']);
        if ($filters['status'] !== null) $query->where('m.estado_actual', $filters['status']);
        if ($filters['start_date'] !== null) $query->where('m.fecha_fin_local', '>=', $filters['start_date']);
        if ($filters['end_date'] !== null) $query->where('m.fecha_inicio_local', '<=', $filters['end_date']);
        return $query;
    }

    private static function normalizeFilters(array $filters): ?array
    {
        $worker = $filters['worker_id'] ?? null;
        if ($worker !== null && (!is_int($worker) || $worker < 1)) return null;
        $category = $filters['category'] ?? null;
        if ($category !== null && !in_array($category, self::CATEGORIES, true)) return null;
        $status = $filters['status'] ?? null;
        if ($status !== null && !in_array($status, ['PENDING_APPROVAL', 'ACTIVE', 'CANCELLED', 'SUPERSEDED', 'REVIEW_REQUIRED', 'REJECTED', 'REVOKED'], true)) return null;
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;
        if (($start !== null && (!is_string($start) || !self::isCivilDate($start)))
            || ($end !== null && (!is_string($end) || !self::isCivilDate($end)))
            || ($start !== null && $end !== null && $end < $start)) return null;
        return ['worker_id' => $worker, 'category' => $category, 'status' => $status,
            'start_date' => $start, 'end_date' => $end];
    }

    private static function mapListRow(object $row): array
    {
        return [
            '_master_id' => (int) $row->_master_id,
            'uuid_incidencia' => (string) $row->uuid_incidencia,
            'worker_id' => (int) $row->id_personal,
            'worker_name' => trim((string) ($row->nombre_completo ?? '')) ?: 'Nombre no disponible',
            'employee_number' => $row->no_colaborador === null ? null : (string) $row->no_colaborador,
            'workplace_id' => (int) $row->id_localidad,
            'category' => (string) $row->tipo_incidencia,
            'scope' => (string) $row->alcance_incidencia,
            'start_date' => (string) $row->fecha_inicio_local,
            'end_date' => (string) $row->fecha_fin_local,
            'current_status' => (string) $row->estado_actual,
            'current_revision' => (int) $row->revision_actual,
            'reason' => $row->motivo === null ? null : (string) $row->motivo,
            'evidence_reference' => $row->folio_evidencia === null ? null : (string) $row->folio_evidencia,
            'created_by_user_id' => $row->creado_por_usuario_id === null ? null : (int) $row->creado_por_usuario_id,
            'created_at_utc' => (string) ($row->revision_creado_at_utc ?? $row->creado_at_utc),
        ];
    }

    private static function mapDetail(object $master, array $revisions): array
    {
        return [
            'id' => (int) $master->id,
            'uuid_incidencia' => (string) $master->uuid_incidencia,
            'worker_id' => (int) $master->id_personal,
            'worker_name' => trim((string) ($master->nombre_completo ?? '')) ?: 'Nombre no disponible',
            'employee_number' => $master->no_colaborador === null ? null : (string) $master->no_colaborador,
            'workplace_id' => (int) $master->id_localidad,
            'station_id' => $master->id_estacion === null ? null : (int) $master->id_estacion,
            'category' => (string) $master->tipo_incidencia,
            'scope' => (string) $master->alcance_incidencia,
            'start_date' => (string) $master->fecha_inicio_local,
            'end_date' => (string) $master->fecha_fin_local,
            'current_status' => (string) $master->estado_actual,
            'current_revision' => (int) $master->revision_actual,
            'created_at_utc' => (string) $master->creado_at_utc,
            'updated_at_utc' => (string) $master->actualizado_at_utc,
            'revisions' => $revisions,
        ];
    }

    private static function sameCreatePayload(
        object $master,
        ?object $revision,
        Usuario $actor,
        int $workplaceId,
        int $workerId,
        string $category,
        string $startDate,
        string $endDate,
        string $reason,
        ?string $evidenceReference
    ): bool {
        return $revision !== null
            && (int) $master->id_personal === $workerId
            && (int) $master->id_localidad === $workplaceId
            && $master->id_estacion === null
            && (string) $master->tipo_incidencia === $category
            && (string) $master->alcance_incidencia === 'WORKER'
            && (string) $master->fecha_inicio_local === $startDate
            && (string) $master->fecha_fin_local === $endDate
            && (string) $master->estado_actual === self::STATUS_PENDING
            && (int) $master->revision_actual === 1
            && (int) $revision->revision_numero === 1
            && (string) $revision->estado_revision === self::STATUS_PENDING
            && (string) ($revision->motivo ?? '') === $reason
            && ($revision->folio_evidencia === null ? null : (string) $revision->folio_evidencia) === $evidenceReference
            && (int) $revision->creado_por_usuario_id === (int) $actor->id
            && $revision->aprobado_por_usuario_id === null;
    }

    private static function isCivilDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private static function isUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value) === 1;
    }
}

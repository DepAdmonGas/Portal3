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
    private const STATUS_RESOLVED = 'RESOLVED_PENDING_AUTHORIZATION';
    private const STATUS_ACTIVE = 'ACTIVE';
    private const STATUS_REJECTED = 'REJECTED';
    private const STATUS_CANCELLED = 'CANCELLED';

    /** @var callable(Usuario,int):bool */
    private $authorizeRead;
    /** @var callable(Usuario,int):bool */
    private $authorizeCreate;
    /** @var callable(Usuario,int):bool */
    private $authorizeRevision;
    /** @var callable(Usuario,int,string):bool */
    private $authorizeLifecycle;
    /** @var callable(int,int,string,string,string):bool */
    private $workerCoversPeriod;
    /** @var callable(int,string):array */
    private $readPolicy;
    /** @var callable():bool */
    private $schemaPreflight;
    /** @var callable():DateTimeImmutable */
    private $clock;
    private bool $apiWriterCutoverVerified;

    /**
     * The only operational gate is the independently verified API writer cutover.
     * It defaults closed. History append-only is an application rule; the owner
     * accepted that MariaDB does not enforce it for the shared default principal.
     */
    public function __construct(
        ?callable $authorizeRead = null,
        ?callable $authorizeCreate = null,
        ?callable $workerCoversPeriod = null,
        ?callable $readPolicy = null,
        ?callable $schemaPreflight = null,
        bool $apiWriterCutoverVerified = false,
        ?callable $clock = null,
        ?callable $authorizeRevision = null,
        ?callable $authorizeLifecycle = null
    ) {
        $this->authorizeRead = $authorizeRead ?? static fn(Usuario $actor, int $workplaceId): bool =>
            (ChecadorAccessService::authorizeIncidenceRead($workplaceId)['allowed'] ?? false) === true;
        $this->authorizeCreate = $authorizeCreate ?? static fn(Usuario $actor, int $workplaceId): bool =>
            (ChecadorAccessService::authorizeIncidenceCreate($workplaceId)['allowed'] ?? false) === true;
        $this->authorizeRevision = $authorizeRevision ?? static fn(Usuario $actor, int $workplaceId): bool =>
            (ChecadorAccessService::authorizeIncidenceRevision($workplaceId)['allowed'] ?? false) === true;
        $this->authorizeLifecycle = $authorizeLifecycle ?? static fn(Usuario $actor, int $workplaceId, string $action): bool =>
            (ChecadorAccessService::authorizeLifecycle($workplaceId, $action)['allowed'] ?? false) === true;
        $this->workerCoversPeriod = $workerCoversPeriod ?? [$this, 'canonicalWorkerCoversPeriod'];
        $this->readPolicy = $readPolicy ?? static fn(int $workplaceId, string $date): array =>
            (new ChecadorAttendancePolicyStoreService())->readForEffectiveDate($workplaceId, $date);
        $this->schemaPreflight = $schemaPreflight ?? [$this, 'mariadbSchemaReady'];
        $this->apiWriterCutoverVerified = $apiWriterCutoverVerified;
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @return array{ok:bool,data?:array,error?:string} */
    public function listForWorkplace(
        Usuario $actor,
        int $workplaceId,
        array $filters = [],
        int $page = 1,
        int $perPage = 25,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): array
    {
        $sortColumns = [
            'worker_name' => 'p.nombre_completo',
            'category' => 'm.tipo_incidencia',
            'start_date' => 'm.fecha_inicio_local',
            'status' => 'm.estado_actual',
            'created_at' => 'm.creado_at_utc',
        ];
        if ($workplaceId < 1 || $page < 1 || $perPage < 1 || $perPage > 100
            || !isset($sortColumns[$sortBy]) || !in_array($sortDirection, ['asc', 'desc'], true)) {
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
            $scopeOnly = self::normalizeFilters([]);
            $recordsTotal = (int) $this->baseListQuery($workplaceId, $scopeOnly)->count();
            $rows = $base->orderBy($sortColumns[$sortBy], $sortDirection)
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
                    'records_total' => $recordsTotal,
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

    /** Append a content revision while preserving the current lifecycle state. */
    public function appendRevision(
        Usuario $actor,
        int $workplaceId,
        string $uuid,
        int $expectedRevision,
        string $reason,
        ?string $evidenceReference = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $reason = self::normalizeText($reason);
        $evidenceReference = self::normalizeOptionalText($evidenceReference);
        if ($workplaceId < 1 || !self::isUuid($uuid) || $expectedRevision < 1
            || $reason === '' || mb_strlen($reason, 'UTF-8') > 5000
            || ($evidenceReference !== null && mb_strlen($evidenceReference, 'UTF-8') > 100)
            || ($startDate !== null && !self::isCivilDate($startDate))
            || ($endDate !== null && !self::isCivilDate($endDate))) {
            return ['ok' => false, 'error' => 'invalid_request'];
        }
        if (!(($this->authorizeRevision)($actor, $workplaceId))) return ['ok' => false, 'error' => 'permission_denied'];
        $readiness = $this->writeReadiness();
        if (!$readiness['ready']) return ['ok' => false, 'error' => $readiness['reason']];

        return $this->appendPersistedRevision(
            $actor, $workplaceId, $uuid, $expectedRevision, null, $reason,
            $evidenceReference, $startDate, $endDate
        );
    }

    /** Execute one of the four contract-defined lifecycle transitions. */
    public function transition(
        Usuario $actor,
        int $workplaceId,
        string $uuid,
        string $action,
        int $expectedRevision,
        string $reason,
        ?string $evidenceReference = null
    ): array {
        $targets = [
            'resolve' => self::STATUS_RESOLVED,
            'authorize' => self::STATUS_ACTIVE,
            'reject' => self::STATUS_REJECTED,
            'revoke' => self::STATUS_CANCELLED,
        ];
        $reason = self::normalizeText($reason);
        $evidenceReference = self::normalizeOptionalText($evidenceReference);
        $requiredReason = $action !== 'authorize';
        if ($workplaceId < 1 || !self::isUuid($uuid) || $expectedRevision < 1 || !isset($targets[$action])
            || ($requiredReason && $reason === '') || mb_strlen($reason, 'UTF-8') > 5000
            || ($evidenceReference !== null && mb_strlen($evidenceReference, 'UTF-8') > 100)) {
            return ['ok' => false, 'error' => 'invalid_request'];
        }
        if (!(($this->authorizeLifecycle)($actor, $workplaceId, $action))) return ['ok' => false, 'error' => 'permission_denied'];
        $readiness = $this->writeReadiness();
        if (!$readiness['ready']) return ['ok' => false, 'error' => $readiness['reason']];

        return $this->appendPersistedRevision(
            $actor, $workplaceId, $uuid, $expectedRevision, $targets[$action],
            $reason, $evidenceReference, null, null, $action
        );
    }

    /** @return array{ok:bool,data?:array,error?:string} */
    private function appendPersistedRevision(
        Usuario $actor,
        int $workplaceId,
        string $uuid,
        int $expectedRevision,
        ?string $targetStatus,
        string $reason,
        ?string $evidenceReference,
        ?string $startDate,
        ?string $endDate,
        ?string $action = null
    ): array {
        $connection = Capsule::connection('default');
        $newRevision = $expectedRevision + 1;
        $statusForRetry = $targetStatus;
        try {
            $write = $connection->transaction(function () use (
                $connection, $actor, $workplaceId, $uuid, $expectedRevision, $newRevision,
                $targetStatus, $statusForRetry, $reason, $evidenceReference, $startDate, $endDate, $action
            ): array {
                $master = $connection->table('op_rh_v2_incidencias_maestro')
                    ->where('uuid_incidencia', $uuid)->where('id_localidad', $workplaceId)
                    ->lockForUpdate()->first();
                if ($master === null) throw new DomainException('incidence_not_found');
                $currentNumber = (int) $master->revision_actual;
                $latest = $connection->table('op_rh_v2_incidencias_revisiones')
                    ->where('id_incidencia_maestro', (int) $master->id)
                    ->orderByDesc('revision_numero')->lockForUpdate()->first();
                if ($latest === null || (int) $latest->revision_numero !== $currentNumber) {
                    throw new DomainException('persisted_data_inconsistent');
                }
                $effectiveStart = $startDate ?? (string) $master->fecha_inicio_local;
                $effectiveEnd = $endDate ?? (string) $master->fecha_fin_local;
                $nextStatus = $targetStatus ?? (string) $master->estado_actual;
                $approvedBy = in_array($action, ['authorize', 'reject', 'revoke'], true) ? (int) $actor->id : null;

                if ($currentNumber === $newRevision && $this->matchesSemanticReplay(
                    $master, $latest, $actor, $nextStatus, $reason, $evidenceReference,
                    $effectiveStart, $effectiveEnd, $approvedBy
                )) {
                    return ['revision' => $newRevision, 'status' => $nextStatus, 'replayed' => true];
                }
                if ($currentNumber !== $expectedRevision) throw new DomainException('stale_revision_conflict');
                if ($effectiveEnd < $effectiveStart) throw new DomainException('invalid_request');
                $blockedStatuses = match ($action) {
                    null => [self::STATUS_CANCELLED, 'REVOKED', self::STATUS_REJECTED],
                    'resolve', 'revoke' => [self::STATUS_CANCELLED, 'REVOKED'],
                    default => [self::STATUS_CANCELLED, 'REVOKED', self::STATUS_REJECTED],
                };
                if (in_array((string) $master->estado_actual, $blockedStatuses, true)) {
                    throw new DomainException('invalid_lifecycle_transition');
                }

                if ($effectiveStart !== (string) $master->fecha_inicio_local || $effectiveEnd !== (string) $master->fecha_fin_local) {
                    $timezone = $this->timezoneForPeriod($workplaceId, $effectiveStart, $effectiveEnd);
                    if ($timezone === null) throw new DomainException('policy_unavailable');
                    if (!(($this->workerCoversPeriod)(
                        (int) $master->id_personal, $workplaceId, $effectiveStart, $effectiveEnd, $timezone
                    ))) throw new DomainException('worker_scope_unverified');
                }

                $createdAt = ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $connection->table('op_rh_v2_incidencias_revisiones')->insert([
                    'id_incidencia_maestro' => (int) $master->id,
                    'revision_numero' => $newRevision,
                    'estado_revision' => $nextStatus,
                    'motivo' => $reason === '' ? null : $reason,
                    'folio_evidencia' => $evidenceReference,
                    'creado_por_usuario_id' => (int) $actor->id,
                    'aprobado_por_usuario_id' => $approvedBy,
                    'creado_at_utc' => $createdAt,
                ]);
                $updated = $connection->table('op_rh_v2_incidencias_maestro')
                    ->where('id', (int) $master->id)->where('id_localidad', $workplaceId)
                    ->where('revision_actual', $expectedRevision)
                    ->update([
                        'fecha_inicio_local' => $effectiveStart,
                        'fecha_fin_local' => $effectiveEnd,
                        'estado_actual' => $nextStatus,
                        'revision_actual' => $newRevision,
                        'actualizado_at_utc' => $createdAt,
                    ]);
                if ($updated !== 1) throw new DomainException('stale_revision_conflict');
                return ['revision' => $newRevision, 'status' => $nextStatus, 'replayed' => false];
            });
        } catch (DomainException $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'sql_write_failed'];
        }

        $readback = $this->detail($actor, $workplaceId, $uuid);
        $data = $readback['data'] ?? [];
        $latestRead = is_array($data['revisions'] ?? null) ? end($data['revisions']) : null;
        $expectedStart = $startDate ?? (string) ($data['start_date'] ?? '');
        $expectedEnd = $endDate ?? (string) ($data['end_date'] ?? '');
        $target = $statusForRetry ?? (string) ($data['current_status'] ?? '');
        if (!($readback['ok'] ?? false) || !is_array($latestRead)
            || (int) ($data['workplace_id'] ?? 0) !== $workplaceId
            || (int) ($data['current_revision'] ?? 0) !== (int) $write['revision']
            || (string) ($data['current_status'] ?? '') !== $target
            || (string) ($data['start_date'] ?? '') !== $expectedStart
            || (string) ($data['end_date'] ?? '') !== $expectedEnd
            || (int) ($latestRead['revision_number'] ?? 0) !== (int) $write['revision']
            || (string) ($latestRead['status'] ?? '') !== $target
            || (int) ($latestRead['created_by_user_id'] ?? 0) !== (int) $actor->id
            || (string) ($latestRead['reason'] ?? '') !== $reason
            || ($latestRead['evidence_reference'] ?? null) !== $evidenceReference
            || (in_array($action, ['authorize', 'reject', 'revoke'], true)
                && (int) ($latestRead['approved_by_user_id'] ?? 0) !== (int) $actor->id)) {
            return ['ok' => false, 'error' => 'readback_unconfirmed'];
        }
        return ['ok' => true, 'data' => ['incidence' => $data, 'replayed' => (bool) $write['replayed']]];
    }

    private function matchesSemanticReplay(
        object $master,
        object $revision,
        Usuario $actor,
        string $status,
        string $reason,
        ?string $evidenceReference,
        string $startDate,
        string $endDate,
        ?int $approvedBy
    ): bool {
        return (string) $master->estado_actual === $status
            && (string) $master->fecha_inicio_local === $startDate
            && (string) $master->fecha_fin_local === $endDate
            && (string) $revision->estado_revision === $status
            && (string) ($revision->motivo ?? '') === $reason
            && ($revision->folio_evidencia === null ? null : (string) $revision->folio_evidencia) === $evidenceReference
            && (int) $revision->creado_por_usuario_id === (int) $actor->id
            && ($revision->aprobado_por_usuario_id === null ? null : (int) $revision->aprobado_por_usuario_id) === $approvedBy;
    }

    private function timezoneForPeriod(int $workplaceId, string $startDate, string $endDate): ?string
    {
        try {
            $start = ($this->readPolicy)($workplaceId, $startDate);
            $end = $startDate === $endDate ? $start : ($this->readPolicy)($workplaceId, $endDate);
            $startZone = (string) ($start['data']['timezone_id'] ?? '');
            $endZone = (string) ($end['data']['timezone_id'] ?? '');
            return ($start['ok'] ?? false) && ($end['ok'] ?? false)
                && $startZone !== '' && $startZone === $endZone
                && ChecadorOperationalPeriod::isIanaTimezone($startZone)
                ? $startZone : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function normalizeText(string $value): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $value));
    }

    private static function normalizeOptionalText(?string $value): ?string
    {
        if ($value === null) return null;
        $value = trim($value);
        return $value === '' ? null : $value;
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

    /** Check the operational SQL requirements, independent of constraint names or local machine. */
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
                foreach ($columns as $column) {
                    if (!$schema->hasColumn($table, $column)) return false;
                }
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
            foreach ($tables as $table) {
                if (strtoupper((string) $table->ENGINE) !== 'INNODB') return false;
            }

            // Enforce semantic uniqueness, not hard-coded index names.
            $indexes = $connection->select(
                "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME "
                . "FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() "
                . "AND TABLE_NAME IN (?, ?) ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
                array_keys($required)
            );
            $unique = [];
            foreach ($indexes as $index) {
                if ((int) $index->NON_UNIQUE === 0) {
                    $unique[(string) $index->TABLE_NAME][(string) $index->INDEX_NAME][] = (string) $index->COLUMN_NAME;
                }
            }
            if (!in_array(['uuid_incidencia'], array_values($unique['op_rh_v2_incidencias_maestro'] ?? []), true)
                || !in_array(['id_incidencia_maestro', 'revision_numero'], array_values($unique['op_rh_v2_incidencias_revisiones'] ?? []), true)) {
                return false;
            }

            // No hard-coded FK names or information_schema grant rows: the application
            // checks canonical scope, and actual SQL permissions are enforced by MariaDB.
            return true;
        } catch (Throwable) {
            return false;
        }
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
        if ($filters['search'] !== null) {
            $term = addcslashes($filters['search'], "\\%_");
            $like = '%' . $term . '%';
            $categoryLabels = [
                'VACATION' => 'Vacaciones', 'PERMISSION' => 'Permiso', 'DISABILITY' => 'Incapacidad',
                'AUTHORIZED_LATENESS' => 'Retardo autorizado',
                'AUTHORIZED_EARLY_DEPARTURE' => 'Salida anticipada autorizada', 'OFFICIAL_MISSION' => 'Misión oficial',
            ];
            $statusLabels = [
                'PENDING_APPROVAL' => 'Pendiente de autorización',
                'RESOLVED_PENDING_AUTHORIZATION' => 'Resuelta pendiente de autorización', 'ACTIVE' => 'Activa',
                'CANCELLED' => 'Cancelada', 'SUPERSEDED' => 'Sustituida', 'REVIEW_REQUIRED' => 'Requiere revisión',
                'REJECTED' => 'Rechazada', 'REVOKED' => 'Revocada',
            ];
            $categoryMatches = array_keys(array_filter($categoryLabels, static fn(string $label): bool => mb_stripos($label, $filters['search'], 0, 'UTF-8') !== false));
            $statusMatches = array_keys(array_filter($statusLabels, static fn(string $label): bool => mb_stripos($label, $filters['search'], 0, 'UTF-8') !== false));
            $query->where(static function ($searchQuery) use ($like, $categoryMatches, $statusMatches): void {
                $searchQuery->where('p.nombre_completo', 'like', $like)
                    ->orWhere('p.no_colaborador', 'like', $like)
                    ->orWhere('m.id_personal', 'like', $like)
                    ->orWhere('m.tipo_incidencia', 'like', $like)
                    ->orWhere('m.estado_actual', 'like', $like)
                    ->orWhere('m.fecha_inicio_local', 'like', $like)
                    ->orWhere('m.fecha_fin_local', 'like', $like)
                    ->orWhere('m.uuid_incidencia', 'like', $like);
                if ($categoryMatches !== []) $searchQuery->orWhereIn('m.tipo_incidencia', $categoryMatches);
                if ($statusMatches !== []) $searchQuery->orWhereIn('m.estado_actual', $statusMatches);
            });
        }
        return $query;
    }

    private static function normalizeFilters(array $filters): ?array
    {
        $worker = $filters['worker_id'] ?? null;
        if (is_string($worker)) {
            $worker = trim($worker);
            if ($worker === '') $worker = null;
            else {
                $validatedWorker = filter_var($worker, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($validatedWorker === false) return null;
                $worker = $validatedWorker;
            }
        }
        if ($worker !== null && (!is_int($worker) || $worker < 1)) return null;
        $category = $filters['category'] ?? null;
        if (is_string($category)) $category = trim($category);
        if ($category === '') $category = null;
        if ($category !== null && !in_array($category, self::CATEGORIES, true)) return null;
        $status = $filters['status'] ?? null;
        if (is_string($status)) $status = trim($status);
        if ($status === '') $status = null;
        if ($status !== null && !in_array($status, [self::STATUS_PENDING, self::STATUS_RESOLVED, self::STATUS_ACTIVE,
            self::STATUS_CANCELLED, 'SUPERSEDED', 'REVIEW_REQUIRED', self::STATUS_REJECTED, 'REVOKED'], true)) return null;
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;
        if (is_string($start)) { $start = trim($start); if ($start === '') $start = null; }
        if (is_string($end)) { $end = trim($end); if ($end === '') $end = null; }
        $search = $filters['search'] ?? null;
        if (is_string($search)) { $search = trim($search); if ($search === '') $search = null; }
        if (($start !== null && (!is_string($start) || !self::isCivilDate($start)))
            || ($end !== null && (!is_string($end) || !self::isCivilDate($end)))
            || ($start !== null && $end !== null && $end < $start)
            || ($search !== null && (!is_string($search) || mb_strlen($search, 'UTF-8') > 120))) return null;
        return ['worker_id' => $worker, 'category' => $category, 'status' => $status,
            'start_date' => $start, 'end_date' => $end, 'search' => $search === null || trim($search) === '' ? null : trim($search)];
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

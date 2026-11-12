<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalLocalidadVersion;
use App\Models\Usuario;
use App\Services\ModuleStationService;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Collection;
use LogicException;

/** Read-only preparation and locked revalidation for one authorized locality baseline. */
final class WorkerWorkplaceAssignmentBaselinePreviewService
{
    public const SOURCE_MODULE_KEY = 'control-documentos-personal';
    /** This provisional exclusion applies only to Checador V2's initial history baseline. */
    public const PROVISIONALLY_EXCLUDED_WORKPLACE_IDS = [999];

    private Closure $authorizationContextResolver;
    private Closure $utcClock;

    /**
     * The optional resolvers are dependency seams for isolated tests. Production calls use
     * the authenticated Portal3 identity and the existing RH locality/edit scope.
     *
     * @param null|callable():array{actor_id:int,workplace_ids:list<int>,can_edit:bool} $authorizationContextResolver
     * @param null|callable():DateTimeImmutable $utcClock
     */
    public function __construct(?callable $authorizationContextResolver = null, ?callable $utcClock = null)
    {
        $this->authorizationContextResolver = Closure::fromCallable(
            $authorizationContextResolver ?? self::portalAuthorizationContext(...)
        );
        $this->utcClock = Closure::fromCallable(
            $utcClock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }

    /**
     * Return only the active unversioned workers for one authorized canonical locality.
     * The database snapshot is read-only and candidate IDs are returned only in this scoped result.
     *
     * @return array<string,mixed>
     */
    public function preview(int $workplaceId): array
    {
        $connection = Capsule::connection();
        $pdo = $connection->getPdo();
        if ($connection->transactionLevel() !== 0 || $pdo->inTransaction()) {
            throw new LogicException('Baseline preview must own its read-only snapshot transaction.');
        }

        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION READ ONLY');

        try {
            $snapshot = $this->readScopedSnapshot($workplaceId);
            $pdo->exec('COMMIT');

            return [
                'source_module' => self::SOURCE_MODULE_KEY,
                'source_id_space' => 'op_rh_localidades.id',
                'workplace_id' => $workplaceId,
                'locality_reference' => $snapshot['locality_reference'],
                'locality_reference_sha256' => $snapshot['locality_reference_sha256'],
                'provisional_baseline_exclusions' => self::PROVISIONALLY_EXCLUDED_WORKPLACE_IDS,
                'mode' => 'PREVIEW_ONLY',
                'live_import_enabled' => false,
                'database_writes' => false,
                'counts' => $snapshot['counts'],
                'excluded_candidates_by_reason' => $snapshot['exclusion_reasons'],
                'candidate_worker_ids' => array_map(
                    static fn(array $row): int => (int) $row['worker_id'],
                    $snapshot['candidate_rows']->all()
                ),
                'candidate_workers' => $snapshot['candidate_rows']->map(static fn(array $row): array => [
                    'worker_id' => (int) $row['worker_id'],
                    'workplace_id' => (int) $row['workplace_id'],
                    'full_name' => (string) ($row['full_name'] ?? ''),
                    'employee_number' => $row['employee_number'] ?? null,
                ])->all(),
                'active_worker_ids' => $snapshot['source_rows']
                    ->filter(static fn(object $row): bool => (int) $row->estado === 1)
                    ->map(static fn(object $row): int => (int) $row->worker_id)
                    ->values()
                    ->all(),
                'active_workers' => $snapshot['source_rows']
                    ->filter(static fn(object $row): bool => (int) $row->estado === 1)
                    ->map(static fn(object $row): array => [
                        'worker_id' => (int) $row->worker_id,
                        'full_name' => trim((string) ($row->nombre_completo ?? '')),
                        'employee_number' => $row->no_colaborador === null ? null : (string) $row->no_colaborador,
                    ])
                    ->values()
                    ->all(),
                'active_workers_with_membership_ids' => $snapshot['source_rows']
                    ->filter(static fn(object $row): bool => (int) $row->estado === 1
                        && isset($snapshot['history_worker_ids'][(int) $row->worker_id]))
                    ->map(static fn(object $row): int => (int) $row->worker_id)
                    ->values()
                    ->all(),
                'worker_memberships' => $snapshot['history_rows']->map(static fn(object $row): array => [
                    'id' => (int) $row->id,
                    'worker_id' => (int) $row->worker_id,
                    'workplace_id' => (int) $row->workplace_id,
                    'valid_from_utc' => (string) $row->valid_from_utc,
                    'valid_to_utc' => $row->valid_to_utc === null ? null : (string) $row->valid_to_utc,
                    'supersedes_id' => $row->supersedes_id === null ? null : (int) $row->supersedes_id,
                ])->values()->all(),
                'candidate_snapshot_sha256' => self::candidateFingerprint($snapshot['candidate_rows']),
                'source_snapshot_sha256' => $snapshot['source_snapshot_sha256'],
                'locality_distribution' => [[
                    'workplace_id' => $workplaceId,
                    'active_worker_count' => $snapshot['counts']['active_workers'],
                    'baseline_candidate_count' => $snapshot['counts']['baseline_candidates'],
                ]],
            ];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @return list<int> Canonical locality IDs mapped uniquely from authorized physical stations. */
    public function authorizedStationWorkplaceIds(): array
    {
        $context = ($this->authorizationContextResolver)();
        return array_values(array_filter(
            array_unique(array_map('intval', $context['workplace_ids'] ?? [])),
            static fn(int $workplaceId): bool => $workplaceId > 0
                && !in_array($workplaceId, self::PROVISIONALLY_EXCLUDED_WORKPLACE_IDS, true)
        ));
    }

    /**
     * Load the exact same locality-scoped candidate set used by preview, optionally retaining locks.
     * Caller owns a transaction when $forUpdate is true.
     *
     * @return array{locality_reference:array<string,mixed>,locality_reference_sha256:string,source_snapshot_sha256:string,source_rows:Collection,candidate_rows:Collection,history_rows:Collection,history_worker_ids:array<int,true>,counts:array<string,int>}
     */
    public function readScopedSnapshot(
        int $workplaceId,
        bool $forUpdate = false,
        ?int $actorId = null,
        bool $requireWrite = false
    ): array
    {
        $this->assertAuthorizedWorkplace($workplaceId, $actorId, $requireWrite);
        if ($forUpdate && Capsule::connection()->transactionLevel() < 1) {
            throw new LogicException('Locked scoped baseline snapshot requires an active transaction.');
        }
        if ($workplaceId <= 0 || in_array($workplaceId, self::PROVISIONALLY_EXCLUDED_WORKPLACE_IDS, true)) {
            throw new DomainException('The requested canonical locality is invalid or excluded from this baseline.');
        }

        $configQuery = Capsule::table('tb_modulos_config')->where('modulo_key', self::SOURCE_MODULE_KEY);
        if ($forUpdate) {
            $configQuery->lockForUpdate();
        }
        $config = $configQuery->first(['modulo_key', 'tipo_departamento', 'activo']);
        if ($config === null || (int) $config->activo !== 1 || (string) $config->tipo_departamento !== 'localidades') {
            throw new DomainException('The current HR source is not configured in the canonical locality ID space.');
        }

        $mapping = $this->resolvePhysicalStationMapping($workplaceId, $forUpdate);

        $sourceQuery = Capsule::table('op_rh_personal as p')
            ->where('p.id_estacion', $workplaceId)
            ->orderBy('p.id');
        if ($forUpdate) {
            $sourceQuery->lockForUpdate();
        }
        $sourceRows = $sourceQuery->get([
            'p.id as worker_id', 'p.estado', 'p.id_estacion', 'p.nombre_completo', 'p.no_colaborador',
        ])
            ->map(static function (object $row) use ($mapping, $workplaceId): object {
                $row->physical_station_id = $mapping['physical_station_id'];
                $row->physical_numlista = $mapping['numlista'];
                $row->physical_station_status = $mapping['station_status'];
                $row->canonical_workplace_id = $mapping['workplace_id'];
                $row->source_locality_id = (int) $row->id_estacion;
                $row->canonical_locality_exists = 1;
                $row->mapping_unique = $row->source_locality_id === $workplaceId ? 1 : 0;
                return $row;
            });

        $workerIds = $sourceRows->pluck('worker_id')->map(static fn($id): int => (int) $id)->all();
        $historyRows = collect();
        if ($workerIds !== []) {
            $historyQuery = Capsule::table('op_rh_personal_localidad_version')
                ->whereIn('worker_id', $workerIds)
                ->orderBy('worker_id')
                ->orderBy('id');
            if ($forUpdate) {
                $historyQuery->lockForUpdate();
            }
            $historyRows = $historyQuery->get([
                'id', 'worker_id', 'workplace_id', 'valid_from_utc', 'valid_to_utc', 'supersedes_id',
            ]);
        }

        $workersWithHistory = [];
        foreach ($historyRows as $history) {
            $workersWithHistory[(int) $history->worker_id] = true;
        }

        $candidateRows = $sourceRows
            ->filter(static fn(object $row): bool => (int) $row->estado === 1
                && (int) $row->canonical_locality_exists === 1
                && (int) $row->mapping_unique === 1
                && !isset($workersWithHistory[(int) $row->worker_id]))
            ->map(static fn(object $row): array => [
                'worker_id' => (int) $row->worker_id,
                'workplace_id' => (int) $row->canonical_workplace_id,
                'full_name' => trim((string) ($row->nombre_completo ?? '')),
                'employee_number' => $row->no_colaborador === null ? null : (string) $row->no_colaborador,
            ])->values();

        $historyWorkerIds = [];
        foreach ($historyRows as $history) {
            $historyWorkerIds[(int) $history->worker_id] = true;
        }

        $activeRows = $sourceRows->where('estado', 1);
        $conflictCount = $this->countCurrentAssignmentConflicts($activeRows, $historyRows);
        $counts = [
            'total_workers' => $sourceRows->count(),
            'active_workers' => $activeRows->count(),
            'inactive_workers' => $sourceRows->where('estado', 0)->count(),
            'other_status_workers' => $sourceRows->reject(static fn(object $row): bool => in_array((int) $row->estado, [0, 1], true))->count(),
            'current_assignments_valid' => $sourceRows->filter(static fn(object $row): bool => (int) $row->estado === 1 && (int) $row->canonical_locality_exists === 1)->count(),
            'invalid_locality_references' => $sourceRows->filter(static fn(object $row): bool => (int) $row->estado === 1 && (int) $row->canonical_locality_exists !== 1)->count(),
            'active_with_preexisting_versions' => count(array_intersect_key($workersWithHistory, array_flip($activeRows->pluck('worker_id')->map(static fn($id): int => (int) $id)->all()))),
            'conflicting_assignments' => $conflictCount,
            'baseline_candidates' => $candidateRows->count(),
            'existing_version_rows' => $historyRows->count(),
            'excluded_workers' => $sourceRows->count() - $candidateRows->count(),
        ];

        $exclusionReasons = [
            'inactive_workers' => $counts['inactive_workers'],
            'unrecognized_worker_status' => $counts['other_status_workers'],
            'active_workers_with_existing_versions' => $counts['active_with_preexisting_versions'],
            'active_workers_with_conflicting_assignments' => $counts['conflicting_assignments'],
        ];

        $localityReference = [
            'workplace_id' => $mapping['workplace_id'],
            'numlista' => $mapping['numlista'],
            'locality' => $mapping['locality'],
            'physical_station_id' => $mapping['physical_station_id'],
            'physical_station_numlista' => $mapping['numlista'],
            'physical_station_status' => $mapping['station_status'],
            'source_module' => (string) $config->modulo_key,
            'source_type' => (string) $config->tipo_departamento,
            'source_active' => (int) $config->activo,
        ];
        $localityFingerprint = hash('sha256', json_encode($localityReference, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $sourceFingerprint = hash('sha256', $localityFingerprint . "\n" . self::sourceFingerprint($sourceRows));

        return [
            'locality_reference' => $localityReference,
            'locality_reference_sha256' => $localityFingerprint,
            'source_snapshot_sha256' => $sourceFingerprint,
            'source_rows' => $sourceRows,
            'candidate_rows' => $candidateRows,
            'history_rows' => $historyRows,
            'history_worker_ids' => $historyWorkerIds,
            'counts' => $counts,
            'exclusion_reasons' => $exclusionReasons,
        ];
    }

    /**
     * Resolve the physical RH station from a canonical locality through the unique numlista bridge.
     * The station row is valid only while the institutional catalog marks it active (estatus=0).
     * When requested, all mapping rows remain locked until the caller's transaction completes.
     *
     * @return array{workplace_id:int,locality:string,numlista:int,physical_station_id:int,station_status:int}
     */
    public function resolvePhysicalStationMapping(int $workplaceId, bool $forUpdate = false): array
    {
        if ($workplaceId <= 0) {
            throw new DomainException('A positive canonical locality ID is required for station mapping.');
        }
        if ($forUpdate && Capsule::connection()->transactionLevel() < 1) {
            throw new LogicException('Locked physical-to-canonical mapping requires an active transaction.');
        }

        $localityQuery = Capsule::table('op_rh_localidades')->where('id', $workplaceId);
        if ($forUpdate) {
            $localityQuery->lockForUpdate();
        }
        $locality = $localityQuery->first(['id', 'numlista', 'localidad']);
        if ($locality === null || $locality->numlista === null || (int) $locality->numlista <= 0) {
            throw new DomainException('The canonical locality has no verifiable positive numlista mapping.');
        }
        $numlista = (int) $locality->numlista;

        $localitiesQuery = Capsule::table('op_rh_localidades')->where('numlista', $numlista);
        if ($forUpdate) {
            $localitiesQuery->lockForUpdate();
        }
        $localities = $localitiesQuery->get(['id', 'numlista']);
        if ($localities->count() !== 1 || (int) $localities->first()->id !== $workplaceId) {
            throw new DomainException('The canonical locality numlista mapping is missing or ambiguous.');
        }

        $stationsQuery = Capsule::table('tb_estaciones')->where('numlista', $numlista);
        if ($forUpdate) {
            $stationsQuery->lockForUpdate();
        }
        $stations = $stationsQuery->get(['id', 'numlista', 'estatus']);
        if ($stations->count() !== 1) {
            throw new DomainException('The physical station mapping is missing or ambiguous.');
        }
        $station = $stations->first();
        if ((int) $station->estatus !== 0) {
            throw new DomainException('The physical station is not active in the institutional station catalog.');
        }

        return [
            'workplace_id' => $workplaceId,
            'locality' => (string) ($locality->localidad ?? ''),
            'numlista' => $numlista,
            'physical_station_id' => (int) $station->id,
            'station_status' => (int) $station->estatus,
        ];
    }

    /** Require the current Portal3 RH scope; import additionally requires edit permission and actor identity. */
    public function assertAuthorizedWorkplace(int $workplaceId, ?int $actorId = null, bool $requireWrite = false): int
    {
        if ($workplaceId <= 0) {
            throw new DomainException('An explicit canonical locality ID is required; global baseline scope is disabled.');
        }

        $context = ($this->authorizationContextResolver)();
        $currentActor = (int) ($context['actor_id'] ?? 0);
        $authorizedIds = array_values(array_unique(array_map('intval', $context['workplace_ids'] ?? [])));
        if ($currentActor <= 0 || !in_array($workplaceId, $authorizedIds, true)) {
            throw new DomainException('The authenticated RH user is not authorized for the requested locality.');
        }
        if ($actorId !== null && $actorId !== $currentActor) {
            throw new DomainException('The recorded actor must match the authenticated Portal3 user.');
        }
        if ($requireWrite && (empty($context['can_edit']) || $actorId === null)) {
            throw new DomainException('Baseline import requires the authenticated RH editor as the recorded actor.');
        }

        return $currentActor;
    }

    /**
     * Lock and revalidate one previously-previewed candidate inside the batch transaction.
     * This method is read-only; it deliberately does not append a version.
     *
     * @return array{worker_id:int,workplace_id:int}
     */
    public function lockAndRevalidateCandidate(int $workerId, int $expectedWorkplaceId, ?int $actorId = null): array
    {
        $this->assertAuthorizedWorkplace($expectedWorkplaceId, $actorId, requireWrite: true);
        $connection = Capsule::connection();
        if ($connection->transactionLevel() < 1) {
            throw new LogicException('Candidate revalidation requires an outer transaction that retains the row lock.');
        }
        if ($workerId <= 0 || $expectedWorkplaceId <= 0) {
            throw new DomainException('Candidate worker and Workplace IDs must be positive.');
        }
        if (in_array($expectedWorkplaceId, self::PROVISIONALLY_EXCLUDED_WORKPLACE_IDS, true)) {
            throw new DomainException('This canonical locality is provisionally excluded from the initial Checador V2 baseline.');
        }

        self::assertLocalitySourceConfiguration(lock: true);
        $mapping = $this->resolvePhysicalStationMapping($expectedWorkplaceId, forUpdate: true);
        $worker = RhPersonal::query()->whereKey($workerId)->lockForUpdate()->first(['id', 'id_estacion', 'estado']);
        if ($worker === null) {
            throw new DomainException('Candidate worker no longer exists.');
        }
        if ((int) $worker->estado !== 1) {
            throw new DomainException('Candidate worker is no longer active.');
        }
        if ((int) $worker->id_estacion !== $expectedWorkplaceId) {
            throw new DomainException('Candidate locality changed after preview.');
        }
        if (RhPersonalLocalidadVersion::query()->where('worker_id', $workerId)->exists()) {
            throw new DomainException('Candidate already has assignment history; manual reconciliation is required.');
        }

        return ['worker_id' => $workerId, 'workplace_id' => $mapping['workplace_id']];
    }

    /** Validate explicit future UTC cutoff, actor identity, and mandatory reason without choosing defaults. */
    public function prepareVersionMetadata(string $validFromUtc, int $recordedBy, string $reason): array
    {
        $validFrom = WorkplaceTimezoneTimestamp::normalize($validFromUtc);
        $now = ($this->utcClock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        if ($validFrom <= $now) {
            throw new DomainException('The baseline cutoff must be prospective and later than the current UTC instant.');
        }
        if ($recordedBy <= 0 || !Usuario::query()->whereKey($recordedBy)->exists()) {
            throw new DomainException('recorded_by must identify an existing Portal3 user.');
        }

        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainException('An approved non-empty reason of at most 500 characters is required.');
        }

        return [
            'valid_from_utc' => $validFrom,
            'valid_to_utc' => null,
            'recorded_by' => $recordedBy,
            'reason' => $reason,
        ];
    }

    /** Lock and validate the canonical HR source configuration inside the import transaction. */
    public function assertLocalitySourceConfigurationForImport(): void
    {
        self::assertLocalitySourceConfiguration(lock: true);
    }

    /** Fingerprint exact worker/locality candidate pairs. */
    public static function candidateFingerprint(iterable $rows): string
    {
        $pairs = [];
        foreach ($rows as $row) {
            $workerId = (int) (is_array($row) ? $row['worker_id'] : $row->worker_id);
            $workplaceId = (int) (is_array($row) ? $row['workplace_id'] : $row->workplace_id);
            $pairs[] = $workerId . ':' . $workplaceId;
        }
        sort($pairs, SORT_STRING);
        return hash('sha256', implode("\n", $pairs));
    }

    /** Normalize an explicit list of worker IDs without accepting duplicates or coercive values. */
    public static function normalizeSelectedWorkerIds(array $selectedWorkerIds, bool $requireNonEmpty = true): array
    {
        if ($requireNonEmpty && $selectedWorkerIds === []) {
            throw new \InvalidArgumentException('Select at least one worker from the current preview.');
        }

        $normalized = [];
        foreach ($selectedWorkerIds as $value) {
            $workerId = filter_var($value, FILTER_VALIDATE_INT);
            if ($workerId === false || $workerId === null || $workerId <= 0 || isset($normalized[$workerId])) {
                throw new \InvalidArgumentException('Selected worker IDs must be unique positive integers.');
            }
            $normalized[$workerId] = true;
        }

        $ids = array_map('intval', array_keys($normalized));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    /** Return only explicitly selected rows from the current full preview candidate set. */
    public static function selectCandidateRows(iterable $candidateRows, array $selectedWorkerIds): array
    {
        $selectedIds = self::normalizeSelectedWorkerIds($selectedWorkerIds);
        $candidatesById = [];
        foreach ($candidateRows as $row) {
            $workerId = (int) (is_array($row) ? ($row['worker_id'] ?? 0) : ($row->worker_id ?? 0));
            $workplaceId = (int) (is_array($row) ? ($row['workplace_id'] ?? 0) : ($row->workplace_id ?? 0));
            if ($workerId <= 0 || $workplaceId <= 0 || isset($candidatesById[$workerId])) {
                throw new DomainException('The current preview candidate set is invalid or ambiguous.');
            }
            $candidate = is_array($row) ? $row : get_object_vars($row);
            $candidate['worker_id'] = $workerId;
            $candidate['workplace_id'] = $workplaceId;
            $candidatesById[$workerId] = $candidate;
        }

        $selectedRows = [];
        foreach ($selectedIds as $workerId) {
            if (!isset($candidatesById[$workerId])) {
                throw new DomainException('A selected worker is not an eligible candidate in the current preview.');
            }
            $selectedRows[] = $candidatesById[$workerId];
        }
        return $selectedRows;
    }

    /** Fingerprint all current worker/status/locality source references within one locality. */
    public static function sourceFingerprint(iterable $rows): string
    {
        $records = [];
        foreach ($rows as $row) {
            $workerId = (int) (is_array($row) ? $row['worker_id'] : $row->worker_id);
            $status = (int) (is_array($row) ? $row['estado'] : $row->estado);
            $sourceLocalityId = is_array($row)
                ? ($row['source_locality_id'] ?? $row['id_estacion'])
                : ($row->source_locality_id ?? $row->id_estacion);
            $sourceId = $sourceLocalityId === null ? 'NULL' : (string) (int) $sourceLocalityId;
            $physicalStationId = is_array($row) ? ($row['physical_station_id'] ?? null) : ($row->physical_station_id ?? null);
            $physicalId = $physicalStationId === null ? 'NULL' : (string) (int) $physicalStationId;
            $exists = (int) (is_array($row) ? $row['canonical_locality_exists'] : $row->canonical_locality_exists);
            $numlista = (int) (is_array($row) ? ($row['physical_numlista'] ?? 0) : ($row->physical_numlista ?? 0));
            $stationStatus = (int) (is_array($row) ? ($row['physical_station_status'] ?? -1) : ($row->physical_station_status ?? -1));
            $canonicalWorkplaceId = (int) (is_array($row) ? ($row['canonical_workplace_id'] ?? 0) : ($row->canonical_workplace_id ?? 0));
            $mappingUnique = (int) (is_array($row) ? ($row['mapping_unique'] ?? 0) : ($row->mapping_unique ?? 0));
            $records[] = $workerId . ':' . $status . ':' . $sourceId . ':' . $physicalId . ':' . $numlista . ':' . $stationStatus
                . ':' . $canonicalWorkplaceId . ':' . $mappingUnique . ':' . $exists;
        }
        sort($records, SORT_STRING);
        return hash('sha256', implode("\n", $records));
    }

    private static function portalAuthorizationContext(): array
    {
        if (!Auth::check()) {
            throw new DomainException('Baseline preview/import requires an authenticated Portal3 RH user.');
        }
        $permissions = ControlDocumentosPersonalService::getPermisos();
        // Baseline membership is keyed by canonical RH locality IDs. Use only
        // the module's authorized station catalog; never mix department IDs.
        $stationWorkplaces = ModuleStationService::getAvailableStations(self::SOURCE_MODULE_KEY);
        $workplaceIds = [];
        foreach ($stationWorkplaces as $station) {
            $workplaceId = (int) ($station['id'] ?? 0);
            $locality = $workplaceId > 0
                && !in_array($workplaceId, self::PROVISIONALLY_EXCLUDED_WORKPLACE_IDS, true)
                ? RhLocalidad::query()->find($workplaceId)
                : null;
            if ($locality === null || $locality->numlista === null) {
                continue;
            }
            $numlista = (int) $locality->numlista;
            if ($numlista <= 0
                || RhLocalidad::query()->where('numlista', $numlista)->count() !== 1
                || \App\Models\Estacion::query()->where('numlista', $numlista)->count() !== 1
                || (int) \App\Models\Estacion::query()->where('numlista', $numlista)->value('estatus') !== 0) {
                continue;
            }
            $workplaceIds[] = $workplaceId;
        }
        return [
            'actor_id' => (int) ($permissions['id_usuario'] ?? 0),
            'workplace_ids' => array_values(array_unique($workplaceIds)),
            'can_edit' => !empty($permissions['puedeEditar']),
        ];
    }

    /** @param iterable<object> $workers @param iterable<object> $historyRows */
    private function countCurrentAssignmentConflicts(iterable $workers, iterable $historyRows): int
    {
        $historiesByWorker = [];
        foreach ($historyRows as $row) {
            $historiesByWorker[(int) $row->worker_id][] = $row;
        }
        $conflicts = 0;
        foreach ($workers as $worker) {
            if ((int) $worker->estado !== 1) {
                continue;
            }
            $rows = $historiesByWorker[(int) $worker->worker_id] ?? [];
            $superseded = [];
            foreach ($rows as $row) {
                if ($row->supersedes_id !== null) {
                    $superseded[(int) $row->supersedes_id] = true;
                }
            }
            $openLocalities = [];
            $openCount = 0;
            $invalidInterval = false;
            $mismatch = false;
            foreach ($rows as $row) {
                $from = self::storedUtcTimestamp((string) $row->valid_from_utc);
                $to = $row->valid_to_utc === null ? null : self::storedUtcTimestamp((string) $row->valid_to_utc);
                if ($from === null || ($to !== null && $to <= $from)) {
                    $invalidInterval = true;
                }
                if (isset($superseded[(int) $row->id]) || $row->valid_to_utc !== null) {
                    continue;
                }
                $openCount++;
                $openLocalities[(int) $row->workplace_id] = true;
                if ((int) $row->workplace_id !== (int) $worker->canonical_workplace_id) {
                    $mismatch = true;
                }
            }
            if ($invalidInterval || $mismatch || count($openLocalities) > 1 || $openCount > count($openLocalities)) {
                $conflicts++;
            }
        }
        return $conflicts;
    }

    private static function storedUtcTimestamp(string $value): ?string
    {
        if (!preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/D', $value, $parts)) {
            return null;
        }
        return $parts[1] . ' ' . $parts[2] . '.' . str_pad($parts[3] ?? '', 6, '0');
    }

    private static function assertLocalitySourceConfiguration(bool $lock = false): void
    {
        $query = Capsule::table('tb_modulos_config')->where('modulo_key', self::SOURCE_MODULE_KEY);
        if ($lock) {
            $query->lockForUpdate();
        }
        $configuration = $query->first(['tipo_departamento', 'activo']);
        if ($configuration === null || (int) $configuration->activo !== 1 || (string) $configuration->tipo_departamento !== 'localidades') {
            throw new DomainException('The current HR source is not configured in the canonical locality ID space.');
        }
    }
}

<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Models\Operativo\RhRolComodines;
use App\Models\Operativo\RhComodinesDia;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use App\Models\Usuario;
use App\Models\Estacion;
use App\Services\ModuloDptoOperativoService;
use App\Services\TelegramService;
use Illuminate\Database\Capsule\Manager as Capsule;

class RolComodinesService
{
    public const MODULE_KEY = 'incidencias-nomina';

    /**
     * Business-authorized RH identities for temporary Comodines coverage.
     * A change to this roster requires explicit business authorization; station,
     * position, and workplace data must never add workers to it implicitly.
     * Keep this as the single server-side source used by search and writes.
     * @var list<int>
     */
    public const AUTHORIZED_COVERAGE_WORKER_IDS = [96, 100, 102, 310, 432, 452, 472, 491, 500, 512];

    /**
     * Owner-approved canonical destinations that operate as service stations.
     * This is the sole destination eligibility list for the selector and writer.
     * @var list<int>
     */
    public const ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS = [1, 2, 3, 4, 5, 6, 7, 14];

    /**
     * Verified crosswalk snapshot keyed by canonical locality ID. It is an
     * integrity check, not another classification list: any changed or
     * ambiguous mapping fails closed until reviewed and explicitly updated.
     * @var array<int, array{numlista:int,station_id:int}>
     */
    private const COVERAGE_DESTINATION_STATION_MAP = [
        1 => ['numlista' => 2, 'station_id' => 1],
        2 => ['numlista' => 1, 'station_id' => 2],
        3 => ['numlista' => 3, 'station_id' => 3],
        4 => ['numlista' => 4, 'station_id' => 4],
        5 => ['numlista' => 5, 'station_id' => 5],
        6 => ['numlista' => 6, 'station_id' => 6],
        7 => ['numlista' => 7, 'station_id' => 7],
        14 => ['numlista' => 8, 'station_id' => 14],
    ];

    private const COVERAGE_EMPTY_ROSTER_NOTICE = 'No hay comodines elegibles. Verifica la adscripción habitual registrada por RH, el periodo y las localidades autorizadas.';

    /**
     * API V2 has no approved Rol de Comodines writer. Keep Portal3's direct
     * database mutation path closed until that canonical API capability exists.
     */
    private const CANONICAL_API_WRITER_AVAILABLE = false;

    private const DESCANSO_VALUE = 400;

    private const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    public static function getPermisos(): array
    {
        $usuario = Auth::user();
        $sessionUsuario = Session::get('usuario');
        $idUsuario = (int)($sessionUsuario['id'] ?? 0);
        $idPuesto = (int)($usuario->id_puesto ?? 0);
        $idEstacion = (int)($sessionUsuario['id_estacion'] ?? 0);
        $multiestacion = !empty($sessionUsuario['multiestacion']);
        $nombrePuesto = $usuario->puesto->tipo_puesto ?? '';

        $permisosDb = ModuloDptoOperativoService::permisosSesion('recursos-humanos');

        return [
            'id_usuario'     => $idUsuario,
            'id_estacion'    => $idEstacion,
            'id_puesto'      => $idPuesto,
            'nombre_puesto'  => $nombrePuesto,
            'multiestacion'  => $multiestacion,
            'puedeCrear'     => !empty($permisosDb['crear']),
            'puedeEditar'    => !empty($permisosDb['editar']),
            'puedeEliminar'  => !empty($permisosDb['eliminar']),
            'puedeDescargar' => !empty($permisosDb['descargar']),
        ];
    }

    public static function getNombreDia(int $dia): string
    {
        return self::DIAS[$dia] ?? '';
    }

    public static function getDias(): array
    {
        return self::DIAS;
    }

    /** @return array{schema_ready:bool,writes_enabled:bool,reason:string} */
    public static function canonicalProgrammingReadiness(): array
    {
        try {
            $schemaReady = Capsule::schema()->hasColumn('op_rh_comodines_dia', 'id_personal')
                && Capsule::schema()->hasColumn('op_rh_comodines_dia', 'id_usuario');
        } catch (\Throwable) {
            $schemaReady = false;
        }

        $environment = strtolower(trim((string) self::environmentValue('APP_ENV')));
        $featureFlag = trim((string) self::environmentValue('CHECADOR_COMODINES_CANONICAL_WRITES'));
        $apiWriterAvailable = self::CANONICAL_API_WRITER_AVAILABLE;
        $writesEnabled = $schemaReady
            && $environment === 'dev'
            && $featureFlag === '1'
            && $apiWriterAvailable;

        return [
            'schema_ready' => $schemaReady,
            'writes_enabled' => $writesEnabled,
            'reason' => !$schemaReady
                ? 'canonical_column_missing'
                : ($environment !== 'dev'
                    ? 'development_only'
                    : ($featureFlag !== '1'
                        ? 'authorization_flag_disabled'
                        : (!$apiWriterAvailable ? 'canonical_api_writer_unavailable' : 'ready'))),
        ];
    }

    /**
     * Resolve existing Checador and RH grants for a canonical scheduling operation.
     * No puesto name or client-provided station expands the user's scope.
     *
     * @return array{allowed:bool,reason:string,station_workplace_ids:list<int>,worker_workplace_ids:list<int>}
     */
    public static function authorizeCanonicalProgramming(int $workplaceId, string $operation): array
    {
        $deny = static fn(string $reason): array => [
            'allowed' => false,
            'reason' => $reason,
            'station_workplace_ids' => [],
            'worker_workplace_ids' => [],
        ];
        if ($workplaceId < 1 || !in_array($operation, ['read', 'create'], true)) {
            return $deny('invalid_operation_or_workplace');
        }

        $baseScope = ChecadorAccessService::authorize(null);
        if (empty($baseScope['allowed'])) {
            return $deny((string) ($baseScope['reason'] ?? 'checador_access_denied'));
        }
        $stationWorkplaceIds = self::positiveIds(array_column($baseScope['station_workplaces'] ?? [], 'id'));
        if (!in_array($workplaceId, $stationWorkplaceIds, true)) {
            return $deny('requested_station_out_of_scope');
        }

        $scheduling = ChecadorAccessService::authorizeBiometricosScheduling(
            $workplaceId,
            $operation === 'create' ? 'editar' : 'leer'
        );
        if (empty($scheduling['allowed'])) {
            return $deny((string) ($scheduling['reason'] ?? 'scheduling_permission_denied'));
        }

        $user = Auth::user();
        $userId = (int) ($user->id ?? 0);
        if ($userId < 1) {
            return $deny('unauthenticated');
        }
        $permissions = ModuloDptoOperativoService::getPermiso($userId, 'recursos-humanos');
        $requiredPermission = $operation === 'create' ? 'crear' : 'leer';
        if (empty($permissions[$requiredPermission])) {
            return $deny('institutional_permission_denied');
        }

        return [
            'allowed' => true,
            'reason' => 'authorized',
            'station_workplace_ids' => $stationWorkplaceIds,
            // An Encargado can search only station rosters, never a separately
            // authorized department locality such as Autolavado.
            'worker_workplace_ids' => $stationWorkplaceIds,
        ];
    }

    /** @param list<int> $authorizedWorkerWorkplaceIds @return array{ok:bool,reason?:string,workers?:list<array<string,mixed>>,has_more?:bool} */
    public static function searchCanonicalWorkers(string $search, array $authorizedWorkerWorkplaceIds): array
    {
        if (empty(self::canonicalProgrammingReadiness()['schema_ready'])) {
            return ['ok' => false, 'reason' => 'canonical_column_missing'];
        }
        $authorizedWorkerWorkplaceIds = self::positiveIds($authorizedWorkerWorkplaceIds);
        $search = trim($search);
        if (mb_strlen($search) < 2 || $authorizedWorkerWorkplaceIds === []) {
            return ['ok' => true, 'workers' => [], 'has_more' => false];
        }

        try {
            // Worker affiliation is stored in canonical op_rh_localidades.id
            // space. numlista conversion is reserved for legacy rows that
            // actually persist tb_estaciones.id.
            $canonicalWorkplaceIds = $authorizedWorkerWorkplaceIds;

            $pattern = '%' . addcslashes($search, "\\%_") . '%';
            $people = RhPersonal::query()
                ->where('estado', 1)
                ->whereIn('id_estacion', $canonicalWorkplaceIds)
                ->where(static function ($query) use ($pattern): void {
                    $query->where('nombre_completo', 'LIKE', $pattern)
                        ->orWhereRaw('CAST(no_colaborador AS CHAR) LIKE ?', [$pattern]);
                })
                ->orderBy('nombre_completo')
                ->limit(26)
                ->get(['id', 'id_estacion', 'nombre_completo', 'no_colaborador']);

            $workers = [];
            foreach ($people->take(25) as $person) {
                $workers[] = [
                    'worker_id' => (int) $person->id,
                    'full_name' => trim((string) $person->nombre_completo) ?: 'Nombre no disponible',
                    'employee_number' => $person->no_colaborador === null ? null : (string) $person->no_colaborador,
                ];
            }
            return ['ok' => true, 'workers' => $workers, 'has_more' => $people->count() > 25];
        } catch (\Throwable) {
            return ['ok' => false, 'reason' => 'worker_search_unavailable'];
        }
    }

    /**
     * Validate the business-approved worker identity and active RH state. The
     * home Workplace is resolved separately from canonical membership, never
     * from the post, physical station, or selected destination.
     * Call with $lock=true only inside the canonical Portal3 writer transaction.
     *
     * @return array{ok:bool,eligible?:bool,reason?:string,worker_id?:int}
     */
    public static function resolveCoverageCandidateSource(int $workerId, bool $lock = false): array
    {
        if ($workerId < 1) return ['ok' => false, 'reason' => 'candidate_source_mismatch'];
        if (!in_array($workerId, self::AUTHORIZED_COVERAGE_WORKER_IDS, true)) {
            return ['ok' => false, 'reason' => 'candidate_worker_not_authorized'];
        }

        try {
            $personQuery = Capsule::table('op_rh_personal')
                ->where('id', $workerId)
                ->where('estado', 1);
            if ($lock) $personQuery->lockForUpdate();
            if ($personQuery->first(['id']) === null) {
                return ['ok' => false, 'reason' => 'candidate_worker_inactive_or_missing'];
            }
            return [
                'ok' => true,
                'eligible' => true,
                'worker_id' => $workerId,
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'reason' => 'candidate_source_unavailable'];
        }
    }

    /**
     * Search only the business-authorized RH roster, requiring active status and
     * one canonical home membership covering the requested interval in the
     * caller's authorized scope. The destination is checked by the request writer.
     * @param list<int> $authorizedWorkplaceIds canonical Workplace IDs
     * @return array{ok:bool,reason?:string,workers?:list<array<string,mixed>>,has_more?:bool,eligibility_notice?:string|null}
     */
    public static function searchCoverageWorkers(
        array $authorizedWorkplaceIds,
        string $search,
        string $fromLocal,
        string $toLocalExclusive
    ): array {
        $from = self::coverageLocalBoundaryUtc($fromLocal);
        $to = self::coverageLocalBoundaryUtc($toLocalExclusive);
        $search = trim($search);
        $authorizedWorkplaceIds = self::positiveIds($authorizedWorkplaceIds);
        if ($from === null || $to === null || $to <= $from || mb_strlen($search) > 80) {
            return ['ok' => false, 'reason' => 'invalid_worker_search'];
        }
        if ($authorizedWorkplaceIds === []) {
            return ['ok' => true, 'workers' => [], 'has_more' => false, 'eligibility_notice' => self::COVERAGE_EMPTY_ROSTER_NOTICE];
        }

        try {
            $lastInstant = (new \DateTimeImmutable($to, new \DateTimeZone('UTC')))
                ->modify('-1 microsecond')->format('Y-m-d H:i:s.u');
            $recordedAsOf = gmdate('Y-m-d H:i:s.u');
            $query = Capsule::table('op_rh_personal as p')
                ->join('op_rh_personal_localidad_version as m', 'm.worker_id', '=', 'p.id')
                ->where('p.estado', 1)
                ->whereIn('p.id', self::AUTHORIZED_COVERAGE_WORKER_IDS)
                ->whereIn('m.workplace_id', $authorizedWorkplaceIds)
                ->where('m.valid_from_utc', '<=', $from)
                ->where('m.recorded_at_utc', '<=', $recordedAsOf)
                ->where(static function ($validity) use ($lastInstant): void {
                    $validity->whereNull('m.valid_to_utc')->orWhere('m.valid_to_utc', '>=', $lastInstant);
                });
            if ($search !== '') {
                $pattern = '%' . addcslashes($search, "\\%_") . '%';
                $query->where(static function ($filter) use ($pattern): void {
                    $filter->where('p.nombre_completo', 'LIKE', $pattern)
                        ->orWhereRaw('CAST(p.no_colaborador AS CHAR) LIKE ?', [$pattern])
                        ->orWhereRaw('CAST(p.id AS CHAR) LIKE ?', [$pattern]);
                });
            }
            $rows = $query->select(['p.id as worker_id', 'p.nombre_completo', 'p.no_colaborador'])
                ->distinct()->orderBy('p.nombre_completo')->orderBy('p.id')->limit(501)->get();

            $workers = [];
            foreach ($rows as $row) {
                $workerId = (int) $row->worker_id;
                $membership = self::coverageCandidateMembership(
                    $workerId,
                    $authorizedWorkplaceIds,
                    $from,
                    $lastInstant,
                    $recordedAsOf
                );
                if (empty($membership['eligible'])) continue;
                if (count($workers) >= 25) {
                    return ['ok' => true, 'workers' => $workers, 'has_more' => true];
                }
                $workers[] = [
                    'worker_id' => $workerId,
                    'full_name' => trim((string) $row->nombre_completo) ?: 'Nombre no disponible',
                    'employee_number' => $row->no_colaborador === null ? null : (string) $row->no_colaborador,
                    'home_workplace_id' => (int) $membership['home_workplace_id'],
                    'id_home_membership_version' => (int) $membership['membership_id'],
                    'eligible' => true,
                ];
            }
            if ($rows->count() > 500) {
                return ['ok' => false, 'reason' => 'worker_search_limit'];
            }
            return [
                'ok' => true,
                'workers' => $workers,
                'has_more' => false,
                'eligibility_notice' => $workers === [] ? self::COVERAGE_EMPTY_ROSTER_NOTICE : null,
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'reason' => 'worker_search_unavailable'];
        }
    }

    /** @param list<int> $authorizedWorkplaceIds @return array{eligible:bool,reason?:string,membership_id?:int,home_workplace_id?:int} */
    private static function coverageCandidateMembership(
        int $workerId,
        array $authorizedWorkplaceIds,
        string $from,
        string $lastInstant,
        string $recordedAsOf
    ): array
    {
        try {
            $resolver = new WorkerWorkplaceAssignmentResolver();
            $fromInstant = str_replace(' ', 'T', $from) . 'Z';
            $lastBoundary = str_replace(' ', 'T', $lastInstant) . 'Z';
            $recordedAsOfInstant = str_replace(' ', 'T', $recordedAsOf) . 'Z';
            $first = $resolver->resolveAssignments($workerId, $fromInstant, $recordedAsOfInstant);
            $last = $resolver->resolveAssignments($workerId, $lastBoundary, $recordedAsOfInstant);
            if (($first['status'] ?? '') !== 'RESOLVED_SINGLE' || ($last['status'] ?? '') !== 'RESOLVED_SINGLE') {
                return ['eligible' => false, 'reason' => 'candidate_membership_not_valid_for_period'];
            }
            $firstAssignment = $first['assignments'][0] ?? [];
            $lastAssignment = $last['assignments'][0] ?? [];
            $homeWorkplaceId = (int) ($firstAssignment['workplace_id'] ?? 0);
            $membershipId = (int) ($firstAssignment['id'] ?? 0);
            if ($homeWorkplaceId < 1 || !in_array($homeWorkplaceId, $authorizedWorkplaceIds, true)
                || $homeWorkplaceId !== (int) ($lastAssignment['workplace_id'] ?? 0)
                || $membershipId < 1 || $membershipId !== (int) ($lastAssignment['id'] ?? 0)) {
                return ['eligible' => false, 'reason' => 'candidate_membership_not_valid_for_period'];
            }
            $membership = Capsule::table('op_rh_personal_localidad_version')
                ->where('id', $membershipId)
                ->where('worker_id', $workerId)
                ->where('workplace_id', $homeWorkplaceId)
                ->where('valid_from_utc', '<=', $from)
                ->where(static function ($validity) use ($lastInstant): void {
                    $validity->whereNull('valid_to_utc')->orWhere('valid_to_utc', '>=', $lastInstant);
                })
                ->where('recorded_at_utc', '<=', $recordedAsOf)
                ->first(['id']);
            if ($membership === null) return ['eligible' => false, 'reason' => 'candidate_membership_not_valid_for_period'];
            return [
                'eligible' => true,
                'membership_id' => (int) $membership->id,
                'home_workplace_id' => $homeWorkplaceId,
            ];
        } catch (\Throwable) {
            return ['eligible' => false, 'reason' => 'candidate_membership_unverified'];
        }
    }

    public static function isAllowedCoverageDestination(int $workplaceId): bool
    {
        return in_array($workplaceId, self::ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS, true);
    }

    /**
     * Resolve an owner-approved target only when its canonical-to-physical
     * crosswalk is still the reviewed, unique mapping. The explicit business
     * list governs this Comodines decision; do not apply the conflicting
     * legacy `estatus` interpretation here.
     *
     * @return array{id:int,nombre:string,numlista:int,station_id:int}|null
     */
    public static function resolveCoverageDestination(int $workplaceId, bool $forUpdate = false): ?array
    {
        if (!self::isAllowedCoverageDestination($workplaceId)) return null;
        $expected = self::COVERAGE_DESTINATION_STATION_MAP[$workplaceId] ?? null;
        if ($expected === null) return null;

        try {
            $localityQuery = Capsule::table('op_rh_localidades')->where('id', $workplaceId);
            if ($forUpdate) $localityQuery->lockForUpdate();
            $locality = $localityQuery->first(['id', 'numlista', 'localidad']);
            if ($locality === null || (int) $locality->numlista !== $expected['numlista']) return null;

            $canonicalMappingsQuery = Capsule::table('op_rh_localidades')
                ->where('numlista', $expected['numlista'])
                ->orderBy('id');
            if ($forUpdate) $canonicalMappingsQuery->lockForUpdate();
            $canonicalMappings = $canonicalMappingsQuery->get(['id']);
            if ($canonicalMappings->count() !== 1 || (int) $canonicalMappings[0]->id !== $workplaceId) return null;

            $stationMappingsQuery = Capsule::table('tb_estaciones')
                ->where('numlista', $expected['numlista'])
                ->orderBy('id');
            if ($forUpdate) $stationMappingsQuery->lockForUpdate();
            $stationMappings = $stationMappingsQuery->get(['id', 'numlista']);
            if ($stationMappings->count() !== 1
                || (int) $stationMappings[0]->id !== $expected['station_id']
                || (int) $stationMappings[0]->numlista !== $expected['numlista']) {
                return null;
            }

            return [
                'id' => $workplaceId,
                'nombre' => trim((string) $locality->localidad),
                'numlista' => $expected['numlista'],
                'station_id' => $expected['station_id'],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<array{id:int,nombre:string}> */
    public static function coverageWorkplaces(int $excludeWorkplaceId): array
    {
        try {
            $workplaces = [];
            foreach (self::ALLOWED_COVERAGE_DESTINATION_LOCALITY_IDS as $workplaceId) {
                if ($workplaceId === $excludeWorkplaceId) continue;
                $destination = self::resolveCoverageDestination($workplaceId);
                if ($destination === null || $destination['nombre'] === '') continue;
                $workplaces[] = ['id' => $destination['id'], 'nombre' => $destination['nombre']];
            }
            usort($workplaces, static fn(array $left, array $right): int => strnatcasecmp($left['nombre'], $right['nombre']));
            return $workplaces;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function coverageLocalBoundaryUtc(string $date): ?string
    {
        if (preg_match('/\\A\\d{4}-\\d{2}-\\d{2}\\z/D', $date) !== 1) return null;
        $zone = new \DateTimeZone('America/Mexico_City');
        $local = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $zone);
        if (!$local || $local->format('Y-m-d') !== $date) return null;
        return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }

    /**
     * Retired legacy writer. Workplace Coverage persistence belongs exclusively to API V2.
     * Kept as a fail-closed compatibility surface for any stale in-process caller.
     */
    public static function createCanonicalProgramming(
        array $input,
        array $authorizedStationWorkplaceIds,
        array $authorizedWorkerWorkplaceIds
    ): array {
        return [
            'ok' => false,
            'reason' => 'canonical_api_required',
            'message' => 'Las coberturas solo pueden persistirse mediante API V2.',
        ];
    }

    /**
     * Canonical program entries for one authorized workplace and date.
     * This is an administrative label only; it does not create API schedules or authorize coverage.
     *
     * @param list<int> $authorizedStationWorkplaceIds
     * @return array{status:string,workers:list<array<string,mixed>>,message:string}
     */
    public static function getCanonicalScheduledWorkersForDate(
        int $workplaceId,
        string $date,
        array $authorizedStationWorkplaceIds
    ): array {
        $empty = static fn(string $status, string $message): array => [
            'status' => $status,
            'workers' => [],
            'message' => $message,
        ];
        if (!self::canonicalProgrammingReadiness()['schema_ready']) {
            return $empty('unavailable', 'El esquema todavía no contiene la identidad canónica de las programaciones.');
        }
        $dateValue = self::parseChecadorDate($date);
        if ($dateValue === null) {
            return $empty('unavailable', 'La fecha laboral no es válida.');
        }
        $station = self::resolvePhysicalStationForWorkplace($workplaceId, $authorizedStationWorkplaceIds);
        if (empty($station['ok'])) {
            return $empty('unavailable', 'No se pudo validar la estación de cobertura.');
        }

        try {
            $day = self::DIAS[(int) $dateValue->format('N')];
            $selectedRows = RhComodinesDia::query()
                ->where('id_estacion', (int) $station['station_id'])
                ->where('dia', $day)
                ->whereNotNull('id_personal')
                ->orderBy('id_reporte')
                ->limit(1001)
                ->get(['id_reporte', 'id_personal', 'dia']);
            if ($selectedRows->count() > 1000) {
                return $empty('partial', 'La consulta excede el límite seguro; no se agregó programación al padrón.');
            }
            if ($selectedRows->isEmpty()) {
                return $empty('ready', 'No hay programación canónica registrada para esta fecha y estación.');
            }

            $headerIds = self::positiveIds($selectedRows->map(static fn($row): int => (int) $row->id_reporte)->all());
            $allRows = RhComodinesDia::query()
                ->whereIn('id_reporte', $headerIds)
                ->orderBy('id_reporte')
                ->limit(5001)
                ->get(['id_reporte', 'id_personal', 'id_estacion', 'dia']);
            if ($allRows->count() > 5000) {
                return $empty('partial', 'La programación contiene demasiados renglones para validarse de forma segura.');
            }

            $stationIds = self::positiveIds($allRows->map(static fn($row): int => (int) $row->id_estacion)->all());
            $stationMap = self::mapPhysicalStationsToWorkplaces($stationIds);
            $authorizedIds = self::positiveIds($authorizedStationWorkplaceIds);
            $unsafeHeaders = [];
            $seenAssignments = [];
            foreach ($allRows as $row) {
                $reportId = (int) $row->id_reporte;
                $rowWorkplaceId = $stationMap[(int) $row->id_estacion] ?? null;
                $workerId = (int) ($row->id_personal ?? 0);
                $rowDay = (string) $row->dia;
                $assignmentKey = $reportId . ':' . $workerId . ':' . $rowDay;
                if ($rowWorkplaceId === null || !in_array($rowWorkplaceId, $authorizedIds, true)
                    || $workerId < 1 || !in_array($rowDay, self::DIAS, true)
                    || isset($seenAssignments[$assignmentKey])) {
                    $unsafeHeaders[$reportId] = true;
                }
                $seenAssignments[$assignmentKey] = true;
            }

            $headers = RhRolComodines::query()->whereIn('id', $headerIds)
                ->get(['id', 'fecha_inicio', 'fecha_fin', 'status'])->keyBy('id');
            $workerIds = self::positiveIds($selectedRows->map(static fn($row): int => (int) $row->id_personal)->all());
            $people = $workerIds === [] ? collect() : RhPersonal::query()
                ->where('estado', 1)->whereIn('id', $workerIds)
                ->get(['id', 'nombre_completo', 'no_colaborador'])->keyBy('id');

            $workers = [];
            $partial = false;
            $validatedAssignments = 0;
            $seenRows = [];
            foreach ($selectedRows as $row) {
                $reportId = (int) $row->id_reporte;
                $workerId = (int) $row->id_personal;
                $header = $headers->get($reportId);
                $worker = $people->get($workerId);
                if (isset($unsafeHeaders[$reportId]) || !$header || !$worker) {
                    $partial = true;
                    continue;
                }
                $start = self::parseChecadorDate((string) $header->fecha_inicio);
                $end = self::parseChecadorDate((string) $header->fecha_fin);
                if ($start === null || $end === null || $start > $end
                    || !in_array((int) $header->status, [0, 1], true)) {
                    $partial = true;
                    continue;
                }
                if ($dateValue < $start || $dateValue > $end) {
                    continue;
                }
                $validatedAssignments++;
                $rowKey = $reportId . ':' . $workerId . ':' . (string) $row->dia;
                if (isset($seenRows[$rowKey])) {
                    $partial = true;
                }
                $seenRows[$rowKey] = true;
                $workers[$workerId] = [
                    'worker_id' => $workerId,
                    'full_name' => trim((string) $worker->nombre_completo) ?: 'Nombre no disponible',
                    'employee_number' => $worker->no_colaborador === null ? null : (string) $worker->no_colaborador,
                    'relationship' => 'scheduled_comodine',
                    'programming_state' => 'validated_structurally',
                    'coverage_authorized' => false,
                ];
            }

            return [
                'status' => $partial ? 'partial' : 'ready',
                'workers' => array_values($workers),
                'message' => $partial
                    ? 'Algunas programaciones no pudieron validarse; sólo se muestran las asignaciones canónicas verificables.'
                    : ($validatedAssignments === 0
                        ? 'No hay programación canónica registrada para esta fecha y estación.'
                        : 'Programación administrativa validada estructuralmente; no acredita autorización de cobertura.'),
            ];
        } catch (\Throwable) {
            return $empty('unavailable', 'No se pudo validar la programación canónica de esta estación.');
        }
    }

    public static function getList(): array
    {
        $roles = RhRolComodines::orderBy('id', 'DESC')->get();

        $rows = [];
        foreach ($roles as $rol) {
            $fechaInicio = self::formatFechaList($rol->fecha_inicio);
            $fechaFin = self::formatFechaList($rol->fecha_fin);

            $rows[] = [
                'id'            => (int)$rol->id,
                'fecha_inicio'  => $fechaInicio,
                'fecha_fin'     => $fechaFin,
                'status'        => (int)$rol->status,
                'status_label'  => $rol->status == 1 ? 'Finalizado' : 'Pendiente',
            ];
        }

        return $rows;
    }

    private static function formatFechaList($fecha): string
    {
        if ($fecha === null || $fecha === '' || $fecha === false) return 'S/I';

        $str = trim((string)$fecha);
        if ($str === '' || $str === '0000-00-00' || $str === '0000-00-00 00:00:00' || $str === 'null') return 'S/I';

        $formatted = formatearFecha($str);
        return $formatted !== '' ? $formatted : 'S/I';
    }

    public static function getDetail(int $idReporte): ?array
    {
        $rol = RhRolComodines::find($idReporte);
        if (!$rol) return null;

        $fechaInicio = self::normalizeDate($rol->fecha_inicio);
        $fechaFin = self::normalizeDate($rol->fecha_fin);

        $empleados = self::getEmpleados($idReporte);
        $estaciones = self::getEstaciones();

        $asignaciones = [];
        $diaRows = RhComodinesDia::where('id_reporte', $idReporte)->get();
        foreach ($diaRows as $row) {
            $asignaciones[$row->id_usuario][$row->dia] = (int)$row->id_estacion;
        }

        return [
            'id'                 => (int)$rol->id,
            'fecha_inicio'       => $fechaInicio,
            'fecha_fin'          => $fechaFin,
            'fecha_inicio_label' => $fechaInicio ? formatearFecha($fechaInicio) : 'S/I',
            'fecha_fin_label'    => $fechaFin ? formatearFecha($fechaFin) : 'S/I',
            'status'             => (int)$rol->status,
            'empleados'          => $empleados,
            'estaciones'         => $estaciones,
            'asignaciones'       => $asignaciones,
        ];
    }

    public static function getEmpleados(?int $idReporte = null): array
    {
        $assignedIds = [];
        if ($idReporte) {
            $assignedIds = RhComodinesDia::where('id_reporte', $idReporte)
                ->pluck('id_usuario')
                ->toArray();
        }

        $users = Usuario::where(function ($q) use ($assignedIds) {
            $q->where(function ($q2) {
                $q2->where('id_gas', 8)->where('id_puesto', 6);
            })->orWhere('id', 321);
        })
        ->where(function ($q) use ($assignedIds) {
            $q->where('estatus', 0);
            if (!empty($assignedIds)) {
                $q->orWhereIn('id', $assignedIds);
            }
        })
        ->orderBy('id', 'ASC')
        ->get();

        $empleados = [];
        foreach ($users as $u) {
            $empleados[] = [
                'id'     => (int)$u->id,
                'nombre' => $u->nombre,
            ];
        }

        return $empleados;
    }

    public static function getEstaciones(): array
    {
        $estaciones = Estacion::where('numlista', '<=', 8)
            ->orderBy('numlista', 'ASC')
            ->get(['id', 'nombre']);

        $result = [];
        foreach ($estaciones as $e) {
            $result[] = [
                'id'     => (int)$e->id,
                'nombre' => $e->nombre,
            ];
        }

        return $result;
    }

    /**
     * Read-only station projection. Legacy id_usuario is never selected or treated as a worker ID.
     *
     * @param list<int> $authorizedWorkplaceIds canonical op_rh_localidades IDs
     * @return array{ok:bool,error?:string,station_name?:string,roles?:list<array<string,mixed>>}
     */
    public static function getChecadorReadModel(int $workplaceId, array $authorizedWorkplaceIds): array
    {
        $authorizedWorkplaceIds = array_values(array_unique(array_filter(
            array_map('intval', $authorizedWorkplaceIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($workplaceId < 1 || !in_array($workplaceId, $authorizedWorkplaceIds, true)) {
            return ['ok' => false, 'error' => 'requested_station_out_of_scope'];
        }

        try {
            $locality = RhLocalidad::query()->where('id', $workplaceId)->first(['id', 'numlista']);
            $numlista = (int) ($locality->numlista ?? 0);
            if (!$locality || $numlista < 1
                || RhLocalidad::query()->where('numlista', $numlista)->count() !== 1
                || Estacion::query()->where('numlista', $numlista)->count() !== 1) {
                return ['ok' => false, 'error' => 'station_mapping_unavailable'];
            }

            $station = Estacion::query()->where('numlista', $numlista)->first(['id', 'numlista', 'nombre', 'estatus']);
            if (!$station || (int) $station->estatus !== 1) {
                return ['ok' => false, 'error' => 'station_mapping_unavailable'];
            }

            // Read only report/day fields for the authorized physical station. Never fetch tb_usuarios IDs.
            $stationAssignments = RhComodinesDia::query()
                ->where('id_estacion', (int) $station->id)
                ->orderBy('id_reporte')
                ->limit(1001)
                ->get(['id_reporte', 'dia']);
            if ($stationAssignments->count() > 1000) {
                return ['ok' => false, 'error' => 'read_limit_exceeded'];
            }

            $headerIds = array_values(array_unique(array_filter(
                $stationAssignments->map(static fn($assignment): int => (int) $assignment->id_reporte)->all(),
                static fn(int $id): bool => $id > 0
            )));
            if ($headerIds === []) {
                return [
                    'ok' => true,
                    'station_name' => (string) $station->nombre,
                    'roles' => [],
                ];
            }

            // A header may contain rows at several stations. Validate every
            // child row before returning even the selected station's portion;
            // one unmappable or out-of-scope row makes the entire header unsafe.
            $assignmentColumns = ['id_reporte', 'id_estacion', 'dia'];
            $hasCanonicalWorkerColumn = Capsule::schema()->hasColumn('op_rh_comodines_dia', 'id_personal');
            if ($hasCanonicalWorkerColumn) {
                $assignmentColumns[] = 'id_personal';
            }
            $allAssignments = RhComodinesDia::query()
                ->whereIn('id_reporte', $headerIds)
                ->orderBy('id_reporte')
                ->limit(5001)
                ->get($assignmentColumns);
            if ($allAssignments->count() > 5000) {
                return ['ok' => false, 'error' => 'read_limit_exceeded'];
            }

            $allStationIds = array_values(array_unique(array_filter(
                $allAssignments->map(static fn($assignment): int => (int) $assignment->id_estacion)->all(),
                static fn(int $id): bool => $id > 0
            )));
            $allStations = $allStationIds === []
                ? []
                : Estacion::query()->whereIn('id', $allStationIds)->get(['id', 'numlista', 'estatus']);
            $stationById = [];
            foreach ($allStations as $candidateStation) {
                $stationById[(int) $candidateStation->id] = $candidateStation;
            }

            $localityByStationId = [];
            foreach ($stationById as $stationId => $candidateStation) {
                $candidateNumlista = (int) $candidateStation->numlista;
                if ($candidateNumlista < 1 || (int) $candidateStation->estatus !== 1
                    || Estacion::query()->where('numlista', $candidateNumlista)->count() !== 1) {
                    continue;
                }
                $matchingLocalities = RhLocalidad::query()
                    ->where('numlista', $candidateNumlista)
                    ->get(['id']);
                if ($matchingLocalities->count() === 1) {
                    $localityByStationId[$stationId] = (int) $matchingLocalities[0]->id;
                }
            }

            $unsafeHeaderIds = [];
            $canonicalIdsByReport = [];
            $unresolvedRowsByReport = [];
            $canonicalWorkerIds = [];
            foreach ($allAssignments as $assignment) {
                $reportId = (int) $assignment->id_reporte;
                $assignmentStationId = (int) $assignment->id_estacion;
                $assignmentLocalityId = $localityByStationId[$assignmentStationId] ?? null;
                if ($assignmentLocalityId === null
                    || !in_array($assignmentLocalityId, $authorizedWorkplaceIds, true)) {
                    $unsafeHeaderIds[$reportId] = true;
                }
                if ($assignmentStationId === (int) $station->id) {
                    $workerId = $hasCanonicalWorkerColumn ? (int) ($assignment->id_personal ?? 0) : 0;
                    if ($workerId > 0) {
                        $canonicalIdsByReport[$reportId][$workerId] = true;
                        $canonicalWorkerIds[$workerId] = true;
                    } else {
                        $unresolvedRowsByReport[$reportId] = ($unresolvedRowsByReport[$reportId] ?? 0) + 1;
                    }
                }
            }

            $activePeople = $canonicalWorkerIds === []
                ? collect()
                : RhPersonal::query()->where('estado', 1)->whereIn('id', array_keys($canonicalWorkerIds))
                    ->get(['id', 'nombre_completo'])->keyBy('id');

            $headers = RhRolComodines::query()
                ->whereIn('id', $headerIds)
                ->orderByDesc('id')
                ->limit(101)
                ->get(['id', 'fecha_inicio', 'fecha_fin', 'status']);
            if ($headers->count() > 100) {
                return ['ok' => false, 'error' => 'read_limit_exceeded'];
            }

            $daysByReport = [];
            $stationRowsByReport = [];
            $validDays = array_flip(array_values(self::DIAS));
            foreach ($stationAssignments as $assignment) {
                $reportId = (int) $assignment->id_reporte;
                $stationRowsByReport[$reportId] = true;
                $day = trim((string) $assignment->dia);
                if (isset($validDays[$day])) {
                    $daysByReport[$reportId][$day] = true;
                }
            }

            $dayOrder = array_flip(array_values(self::DIAS));
            $roles = [];
            foreach ($headers as $header) {
                $reportId = (int) $header->id;
                $status = (int) $header->status;
                $start = self::parseChecadorDate($header->fecha_inicio === null ? null : (string) $header->fecha_inicio);
                $end = self::parseChecadorDate($header->fecha_fin === null ? null : (string) $header->fecha_fin);
                $validPeriod = $start !== null && $end !== null && $start <= $end;
                $hasStationRows = isset($stationRowsByReport[$reportId]);

                // Never list global/unassigned headers inside a station-scoped view.
                // An incomplete header without a station assignment cannot be attributed safely.
                if (!$hasStationRows || isset($unsafeHeaderIds[$reportId])) {
                    continue;
                }

                $days = array_keys($daysByReport[$reportId] ?? []);
                usort($days, static fn(string $left, string $right): int => ($dayOrder[$left] ?? 99) <=> ($dayOrder[$right] ?? 99));
                $periodLabel = $validPeriod
                    ? $start->format('d/m/Y') . ' al ' . $end->format('d/m/Y')
                    : 'Sin periodo válido';
                $workerNames = [];
                $unresolvedCount = (int) ($unresolvedRowsByReport[$reportId] ?? 0);
                foreach (array_keys($canonicalIdsByReport[$reportId] ?? []) as $workerId) {
                    $person = $activePeople->get((int) $workerId);
                    if (!$person) {
                        $unresolvedCount++;
                        continue;
                    }
                    $workerNames[] = trim((string) $person->nombre_completo) ?: 'Nombre no disponible';
                }
                $workerNames = array_values(array_unique($workerNames));
                $identityLabel = $workerNames === []
                    ? 'Registro legacy sin vínculo canónico verificable'
                    : implode(', ', $workerNames);
                if ($unresolvedCount > 0) {
                    $identityLabel .= ' · ' . $unresolvedCount . ' renglón(es) sin identidad canónica activa';
                }
                $structurallyValid = $validPeriod && in_array($status, [0, 1], true)
                    && $days !== [] && $workerNames !== [] && $unresolvedCount === 0;

                $roles[] = [
                    'period_label' => $periodLabel,
                    'status_label' => match ($status) {
                        0 => 'Pendiente',
                        1 => 'Finalizado',
                        default => 'Estado no documentado',
                    },
                    'station_label' => (string) $station->nombre,
                    'days_label' => $days === [] ? 'Días no reconocidos' : implode(', ', $days),
                    'identity_label' => $identityLabel,
                    'programming_label' => $structurallyValid ? 'Programación validada estructuralmente' : 'Programación incompleta o legacy',
                    'incomplete' => !$structurallyValid,
                    // “Finalizado” is the legacy workflow state; it is not evidence of coverage approval.
                    'coverage_authorized' => false,
                ];
            }

            return [
                'ok' => true,
                'station_name' => (string) $station->nombre,
                'roles' => $roles,
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'read_unavailable'];
        }
    }

    /** Resolve display labels only for rows already returned within API V2 actor scope. */
    public static function coveragePresentationRows(array $items): array
    {
        $workerIds = self::positiveIds(array_map(static fn(array $row): int => (int) ($row['id_personal'] ?? 0), $items));
        $workplaceIds = self::positiveIds(array_map(
            static fn(array $row): int => (int) ($row['id_home_workplace'] ?? 0),
            $items
        ));
        $workplaceIds = self::positiveIds([...$workplaceIds, ...array_map(
            static fn(array $row): int => (int) ($row['id_coverage_workplace'] ?? 0),
            $items
        )]);

        $people = $workerIds === [] ? collect() : RhPersonal::query()
            ->whereIn('id', $workerIds)->get(['id', 'nombre_completo'])->keyBy('id');
        $localities = $workplaceIds === [] ? collect() : RhLocalidad::query()
            ->whereIn('id', $workplaceIds)->get(['id', 'localidad'])->keyBy('id');
        $statusLabels = [
            'REQUESTED' => 'Pendiente',
            'AUTHORIZED' => 'Autorizada',
            'REJECTED' => 'Rechazada',
            'CANCELLED' => 'Cancelada',
        ];
        $dayLabels = [
            'LUNES' => 'Lunes', 'MARTES' => 'Martes', 'MIERCOLES' => 'Miércoles',
            'JUEVES' => 'Jueves', 'VIERNES' => 'Viernes', 'SABADO' => 'Sábado', 'DOMINGO' => 'Domingo',
        ];

        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $worker = $people->get((int) ($item['id_personal'] ?? 0));
            $home = $localities->get((int) ($item['id_home_workplace'] ?? 0));
            $target = $localities->get((int) ($item['id_coverage_workplace'] ?? 0));
            $schedule = [];
            foreach (is_array($item['proposed_weekly_schedule'] ?? null) ? $item['proposed_weekly_schedule'] : [] as $day) {
                if (!is_array($day)) continue;
                $weekday = $dayLabels[strtoupper((string) ($day['dia_semana'] ?? ''))] ?? null;
                if ($weekday === null) continue;
                $schedule[] = $weekday . ': ' . (strtoupper((string) ($day['tipo'] ?? '')) === 'DAY_OFF' ? 'Descanso' : 'Horario asignado');
            }
            $from = (string) ($item['fecha_desde_local'] ?? '');
            $to = (string) ($item['fecha_hasta_local_exclusiva'] ?? '');
            $requestedAt = (string) ($item['requested_at_utc'] ?? '');
            $requestedAtLabel = 'Fecha no disponible';
            if ($requestedAt !== '') {
                try {
                    $requestedAtLabel = (new \DateTimeImmutable($requestedAt, new \DateTimeZone('UTC')))
                        ->setTimezone(new \DateTimeZone('America/Mexico_City'))
                        ->format('d/m/Y H:i');
                } catch (\Throwable) {
                    $requestedAtLabel = 'Fecha no disponible';
                }
            }
            $rows[] = [
                'coverage_uuid' => (string) ($item['coverage_uuid'] ?? ''),
                'coverage_id' => (int) ($item['id'] ?? 0),
                'worker_id' => (int) ($item['id_personal'] ?? 0),
                'home_workplace_id' => (int) ($item['id_home_workplace'] ?? 0),
                'coverage_workplace_id' => (int) ($item['id_coverage_workplace'] ?? 0),
                'period_label' => $from !== '' && $to !== '' ? $from . ' a ' . $to . ' (fin no incluido)' : 'Periodo no disponible',
                'from_local' => $from,
                'to_local_exclusive' => $to,
                'requested_at_utc' => $requestedAt,
                'requested_at_label' => $requestedAtLabel,
                'status_label' => $statusLabels[strtoupper((string) ($item['status'] ?? ''))] ?? 'Estado no disponible',
                'status_code' => strtoupper((string) ($item['status'] ?? '')),
                'home_label' => trim((string) ($home->localidad ?? '')) ?: 'Localidad de origen no disponible',
                'coverage_label' => trim((string) ($target->localidad ?? '')) ?: 'Localidad de cobertura no disponible',
                'worker_label' => trim((string) ($worker->nombre_completo ?? '')) ?: 'Trabajador no disponible',
                'schedule_label' => $schedule === [] ? 'Patrón no disponible' : implode(' · ', $schedule),
            ];
        }
        return $rows;
    }

    private static function parseChecadorDate(?string $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        return $date instanceof \DateTimeImmutable
            && $date->format('Y-m-d') === $value
            && !array_filter(\DateTimeImmutable::getLastErrors() ?: [])
                ? $date
                : null;
    }

    /** @return array{ok:bool,reason?:string,workplace_id?:int,station_id?:int,station_name?:string,numlista?:int} */
    private static function resolvePhysicalStationForWorkplace(
        int $workplaceId,
        array $authorizedStationWorkplaceIds,
        bool $lock = false
    ): array {
        $authorizedStationWorkplaceIds = self::positiveIds($authorizedStationWorkplaceIds);
        if ($workplaceId < 1 || !in_array($workplaceId, $authorizedStationWorkplaceIds, true)) {
            return ['ok' => false, 'reason' => 'requested_station_out_of_scope'];
        }
        $localityQuery = RhLocalidad::query()->whereKey($workplaceId);
        if ($lock) $localityQuery->lockForUpdate();
        $locality = $localityQuery->first(['id', 'numlista']);
        $numlista = (int) ($locality->numlista ?? 0);
        if (!$locality || $numlista < 1
            || RhLocalidad::query()->where('numlista', $numlista)->count() !== 1
            || Estacion::query()->where('numlista', $numlista)->count() !== 1) {
            return ['ok' => false, 'reason' => 'station_mapping_unavailable'];
        }
        $stationQuery = Estacion::query()->where('numlista', $numlista);
        if ($lock) $stationQuery->lockForUpdate();
        $station = $stationQuery->first(['id', 'numlista', 'nombre', 'estatus']);
        if (!$station || (int) $station->estatus !== 0) {
            return ['ok' => false, 'reason' => 'station_mapping_unavailable'];
        }
        return [
            'ok' => true,
            'workplace_id' => $workplaceId,
            'station_id' => (int) $station->id,
            'station_name' => (string) $station->nombre,
            'numlista' => $numlista,
        ];
    }

    /** @param list<int> $stationIds @return array<int,int> physical station ID => canonical locality ID */
    private static function mapPhysicalStationsToWorkplaces(array $stationIds): array
    {
        $stationIds = self::positiveIds($stationIds);
        if ($stationIds === []) return [];
        $stations = Estacion::query()->whereIn('id', $stationIds)->get(['id', 'numlista', 'estatus']);
        $mapped = [];
        foreach ($stations as $station) {
            $stationId = (int) $station->id;
            $numlista = (int) $station->numlista;
            if ((int) $station->estatus !== 0 || $numlista < 1
                || Estacion::query()->where('numlista', $numlista)->count() !== 1
                || RhLocalidad::query()->where('numlista', $numlista)->count() !== 1) {
                continue;
            }
            $locality = RhLocalidad::query()->where('numlista', $numlista)->first(['id']);
            if ($locality) $mapped[$stationId] = (int) $locality->id;
        }
        return $mapped;
    }

    /**
     * Validate the worker's canonical locality against the authorized
     * worker-home allowlist. Coverage locality authorization is checked
     * separately and remains independent from this base-workplace check.
     *
     * @param list<int> $authorizedWorkerWorkplaceIds canonical locality IDs
     */
    private static function workerBaseWorkplaceIsAuthorized(
        int $workerLocalityId,
        array $authorizedWorkerWorkplaceIds
    ): bool {
        if ($workerLocalityId < 1) {
            return false;
        }
        $authorizedWorkerWorkplaceIds = self::positiveIds($authorizedWorkerWorkplaceIds);
        if ($authorizedWorkerWorkplaceIds === []) {
            return false;
        }

        return in_array($workerLocalityId, $authorizedWorkerWorkplaceIds, true);
    }

    /** @param array<int|string,mixed> $values @return list<int> */
    private static function positiveIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $validated = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validated !== false) $ids[(int) $validated] = true;
        }
        return array_map('intval', array_keys($ids));
    }

    private static function environmentValue(string $key): mixed
    {
        if (array_key_exists($key, $_ENV)) return $_ENV[$key];
        if (array_key_exists($key, $_SERVER)) return $_SERVER[$key];
        $value = getenv($key);
        return $value === false ? null : $value;
    }

    public static function getPdfStyles(): string
    {
        return '
            body {
                font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
                font-size: 10px;
                color: #333;
                margin: 0;
                padding: 0;
            }
            h2 {
                font-size: 16px;
                font-weight: bold;
                margin: 10px 0 5px 0;
            }
            .dates-row {
                width: 100%;
                margin-bottom: 10px;
                border-collapse: collapse;
            }
            .dates-row td {
                vertical-align: top;
                padding: 2px 8px;
            }
            .dates-row small {
                color: #666;
                font-weight: bold;
                display: block;
            }
            .custom-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 9px;
            }
            .custom-table thead tr th {
                border: none;
                text-transform: uppercase;
                color: #fff;
                padding: 8px 4px;
                font-size: 8.5px;
                font-weight: bold;
            }
            .custom-table thead tr th:first-child {
                border-top-left-radius: 7px;
            }
            .custom-table thead tr th:last-child {
                border-top-right-radius: 7px;
            }
            .custom-table tbody tr td {
                padding: 8px 4px;
                font-size: 9px;
                border: none;
            }
            .custom-table tbody tr:nth-child(even) td {
                background-color: #f8f9fa;
            }
            .tables-bg {
                background: #215D98;
                color: white;
            }
            .text-start-custom {
                text-align: left;
            }
        ';
    }

    public static function buildRolComodinesPdfHtml(int $idReporte): string
    {
        $detail = self::getDetail($idReporte);
        if (!$detail) return '<p>Registro no encontrado.</p>';

        $fechaInicio = $detail['fecha_inicio'] ? formatearFecha($detail['fecha_inicio']) : 'Sin información';
        $fechaFin = $detail['fecha_fin'] ? formatearFecha($detail['fecha_fin']) : 'Sin información';

        $html = '<h2>Rol de comodines</h2>';

        $html .= '<table class="dates-row">';
        $html .= '<tr>';
        $html .= '<td width="50%"><small>FECHA DE INICIO:</small><div>' . $fechaInicio . '</div></td>';
        $html .= '<td width="50%"><small>FECHA DE TERMINO:</small><div>' . $fechaFin . '</div></td>';
        $html .= '</tr></table>';

        $html .= '<table class="custom-table">';
        $html .= '<thead><tr class="tables-bg">';
        $html .= '<th class="text-center" width="30">#</th>';
        $html .= '<th class="text-start-custom">Nombre completo</th>';
        $html .= '<th class="text-center">Lunes</th>';
        $html .= '<th class="text-center">Martes</th>';
        $html .= '<th class="text-center">Miércoles</th>';
        $html .= '<th class="text-center">Jueves</th>';
        $html .= '<th class="text-center">Viernes</th>';
        $html .= '<th class="text-center">Sábado</th>';
        $html .= '<th class="text-center">Domingo</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        $diasNombres = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        $rowNum = 1;

        foreach ($detail['empleados'] as $emp) {
            $html .= '<tr>';
            $html .= '<td class="text-center fw-bold">' . $rowNum . '</td>';
            $html .= '<td class="text-start-custom">' . htmlspecialchars($emp['nombre']) . '</td>';

            foreach ($diasNombres as $dia) {
                $val = $detail['asignaciones'][$emp['id']][$dia] ?? 0;
                $estacion = self::resolverEstacion($val, $detail['estaciones']);
                $html .= '<td class="text-center">' . htmlspecialchars($estacion) . '</td>';
            }

            $html .= '</tr>';
            $rowNum++;
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private static function resolverEstacion(int $val, array $estaciones): string
    {
        if ($val == 400) return 'Descanso';
        if ($val <= 0) return 'S/I';

        foreach ($estaciones as $est) {
            if ((int)$est['id'] === $val) return $est['nombre'];
        }

        return 'S/I';
    }

    private static function normalizeDate($fecha): string
    {
        if ($fecha === null || $fecha === '' || $fecha === false) return '';

        $str = trim((string)$fecha);
        if ($str === '' || $str === '0000-00-00' || $str === '0000-00-00 00:00:00') return '';

        try {
            $date = \Carbon\Carbon::parse($str);
            return $date->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function getAsignacion(int $idReporte, int $idUsuario, int $dia): int
    {
        $nombreDia = self::getNombreDia($dia);
        $asignacion = RhComodinesDia::where('id_reporte', $idReporte)
            ->where('id_usuario', $idUsuario)
            ->where('dia', $nombreDia)
            ->first();

        return $asignacion ? (int)$asignacion->id_estacion : 0;
    }

    /** Legacy mutation methods remain as closed compatibility stubs; no Portal3 SQL writes. */
    public static function agregar(): int
    {
        return 0;
    }

    public static function editarAsignacion(int $idReporte, int $idUsuario, int $idEstacion, int $dia): bool
    {
        return false;
    }

    public static function guardarFechas(int $idReporte, string $fechaInicio, string $fechaFin): bool
    {
        return false;
    }

    public static function finalizar(int $idReporte, string $fechaInicio, string $fechaFin): bool
    {
        return false;
    }

    public static function eliminar(int $idReporte): bool
    {
        return false;
    }

    public static function notificarCreacion(int $idReporte, int $idUsuario): void
    {
        try {
            $usuario = Auth::user();
            $nombreUsuario = $usuario->nombre ?? 'Desconocido';

            $mensaje = '➕ Se ha creado un nuevo <b>Rol de Comodines</b>:'
                . PHP_EOL . 'ID: #' . $idReporte
                . PHP_EOL . 'Creado por: ' . $nombreUsuario
                . PHP_EOL . 'Estado: Borrador';

            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
            $telegram->sendMessageToMultiple($userIds, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en notificarCreacion RolComodines: ' . $e->getMessage());
        }
    }

    public static function notificarEdicion(int $idReporte, int $idUsuario): void
    {
        try {
            $usuario = Auth::user();
            $nombreUsuario = $usuario->nombre ?? 'Desconocido';

            $mensaje = '✏️ Se ha editado un <b>Rol de Comodines</b>:'
                . PHP_EOL . 'ID: #' . $idReporte
                . PHP_EOL . 'Editado por: ' . $nombreUsuario;

            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
            $telegram->sendMessageToMultiple($userIds, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en notificarEdicion RolComodines: ' . $e->getMessage());
        }
    }

    public static function notificarFinalizacion(int $idReporte, int $idUsuario, string $fechaInicio, string $fechaFin): void
    {
        try {
            $usuario = Auth::user();
            $nombreUsuario = $usuario->nombre ?? 'Desconocido';

            $mensaje = '✅ Se ha finalizado un <b>Rol de Comodines</b>:'
                . PHP_EOL . 'ID: #' . $idReporte
                . PHP_EOL . 'Período: ' . formatearFecha($fechaInicio) . ' al ' . formatearFecha($fechaFin)
                . PHP_EOL . 'Finalizado por: ' . $nombreUsuario;

            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
            $telegram->sendMessageToMultiple($userIds, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en notificarFinalizacion RolComodines: ' . $e->getMessage());
        }
    }

    public static function notificarEliminacion(int $idReporte, int $idUsuario): void
    {
        try {
            $usuario = Auth::user();
            $nombreUsuario = $usuario->nombre ?? 'Desconocido';

            $mensaje = '🗑️ Se ha eliminado un <b>Rol de Comodines</b>:'
                . PHP_EOL . 'ID: #' . $idReporte
                . PHP_EOL . 'Eliminado por: ' . $nombreUsuario;

            $telegram = new TelegramService();
            $userIds = $telegram->getUserIdsDeptoOperativo($idUsuario);
            $telegram->sendMessageToMultiple($userIds, $mensaje);
        } catch (\Throwable $e) {
            error_log('Error en notificarEliminacion RolComodines: ' . $e->getMessage());
        }
    }
}

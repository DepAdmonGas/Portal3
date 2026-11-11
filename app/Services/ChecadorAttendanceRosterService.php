<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalLocalidadVersion;
use App\Models\Operativo\RhLocalidad;
use Throwable;

/** Read-only attendance roster constrained by canonical bitemporal workplace membership. */
final class ChecadorAttendanceRosterService
{
    public const MAX_ROSTER_ROWS = 250;

    /**
     * Active worker options for the authorized attendance filter. This path is
     * independent of PunchEvent reads. The legacy HR source stores
     * tb_estaciones.id in op_rh_personal.id_estacion. Resolve that physical
     * station through the unique active numlista bridge; versioned assignments
     * take precedence and must resolve to exactly one requested Workplace.
     *
     * @return array{status:string,coverage:string,workers:list<array{worker_id:int,full_name:string,employee_number:?string}>,message:string}
     */
    public function workersForAttendanceFilter(
        int $workplaceId,
        string $date,
        ?string $timezone = null,
        bool $demoMode = false
    ): array {
        if ($workplaceId < 1 || !self::isCivilDate($date)) {
            return self::result('unavailable', 'unknown', [], 'No se pudo validar la fecha o localidad del filtro.');
        }
        if ($demoMode && ChecadorDailyReadService::demoModeEnabled()) {
            return self::result('demo_unavailable', 'synthetic_only', [], 'El filtro local no usa personal operativo en modo demostración.');
        }

        try {
            $locality = RhLocalidad::query()->whereKey($workplaceId)->first(['id', 'localidad', 'numlista']);
            if ($locality === null) {
                return self::result('unavailable', 'unknown', [], 'La localidad canónica no existe.');
            }
            $legacyStationId = ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId);
            $isDepartment = ChecadorAccessService::isDepartmentLocality($workplaceId);
            $directLocalityId = $legacyStationId ?? ($isDepartment ? $workplaceId : null);
            $legacyMappingUnavailable = (int) ($locality->numlista ?? 0) > 0 && $legacyStationId === null && !$isDepartment;

            $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
            $localDevelopment = in_array($environment, ['dev', 'development', 'local'], true);
            $timezone = is_string($timezone) && ChecadorOperationalPeriod::isIanaTimezone($timezone)
                ? $timezone
                : null;
            if ($timezone === null
                && $localDevelopment
                && $workplaceId === 1
                && mb_strtolower(trim((string) $locality->localidad)) === 'interlomas') {
                $configuredTimezone = trim((string) ($_ENV['APP_TIMEZONE'] ?? getenv('APP_TIMEZONE') ?: ''));
                if (ChecadorOperationalPeriod::isIanaTimezone($configuredTimezone)) {
                    $timezone = $configuredTimezone;
                }
            }
            $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $currentOnlyFallback = $timezone === null;
            $effectiveAt = $currentOnlyFallback
                ? $nowUtc->format('Y-m-d\TH:i:s.u\Z')
                : (new \DateTimeImmutable($date . ' 12:00:00', new \DateTimeZone($timezone)))
                    ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
            $todayAtWorkplace = $currentOnlyFallback
                ? null
                : ChecadorOperationalPeriod::currentLocalDate($timezone, $nowUtc);
            $allowUnversionedCurrentSource = $currentOnlyFallback
                || ($todayAtWorkplace !== null && $todayAtWorkplace === $date);

            // Include active current assignments only through the physical
            // station resolved above, plus workers with canonical history for
            // this locality. QA inactive accounts never enter the roster.
            $peopleQuery = RhPersonal::query()->where('estado', 1);
            $peopleQuery->where(static function ($query) use ($workplaceId, $directLocalityId): void {
                if ($directLocalityId !== null) {
                    $query->where('id_estacion', $directLocalityId)
                        ->orWhereIn('id', RhPersonalLocalidadVersion::query()
                            ->where('workplace_id', $workplaceId)
                            ->select('worker_id')
                            ->distinct());
                } else {
                    $query->whereIn('id', RhPersonalLocalidadVersion::query()
                        ->where('workplace_id', $workplaceId)
                        ->select('worker_id')
                        ->distinct());
                }
            });
            $people = $peopleQuery
                ->orderBy('id')
                ->get(['id', 'id_estacion', 'nombre_completo', 'no_colaborador', 'estado']);
            $workerIds = $people->map(static fn($person): int => (int) $person->id)->all();
            $versionedWorkerIds = $workerIds === []
                ? []
                : array_fill_keys(array_map('intval', RhPersonalLocalidadVersion::query()
                    ->whereIn('worker_id', $workerIds)
                    ->distinct()
                    ->pluck('worker_id')
                    ->all()), true);

            $resolver = new WorkerWorkplaceAssignmentResolver();
            $workers = [];
            $currentWorkers = [];
            $invalidMembership = false;
            $invalidCurrentMembership = false;
            $unversionedHistoricalMembership = false;
            foreach ($people as $person) {
                $workerId = (int) $person->id;
                $currentBelongs = false;
                if (isset($versionedWorkerIds[$workerId])) {
                    $currentMembership = $resolver->resolveAssignments(
                        $workerId,
                        $nowUtc->format('Y-m-d\TH:i:s.u\Z')
                    );
                    if (($currentMembership['status'] ?? '') === 'DATA_INVALID') {
                        $invalidCurrentMembership = true;
                    } else {
                        $currentAssignments = $currentMembership['assignments'] ?? [];
                        $currentBelongs = ($currentMembership['status'] ?? '') === 'RESOLVED_SINGLE'
                            && count($currentAssignments) === 1
                            && (int) ($currentAssignments[0]['workplace_id'] ?? 0) === $workplaceId;
                    }
                } else {
                    $currentBelongs = $directLocalityId !== null
                        && (int) ($person->id_estacion ?? 0) === $directLocalityId;
                }

                $name = trim((string) $person->nombre_completo);
                if ($workerId > 0 && $name !== '' && $name !== 'Nombre no disponible' && $currentBelongs) {
                    $employeeNumber = $person->no_colaborador === null
                        ? ''
                        : trim((string) $person->no_colaborador);
                    $currentWorkers[] = [
                        'worker_id' => $workerId,
                        'full_name' => $name,
                        'employee_number' => $employeeNumber !== '' && $employeeNumber !== '0' ? $employeeNumber : null,
                    ];
                }

                if (isset($versionedWorkerIds[$workerId])) {
                    $membership = $resolver->resolveAssignments($workerId, $effectiveAt);
                    $assignments = $membership['assignments'] ?? [];
                    if (($membership['status'] ?? '') === 'DATA_INVALID') {
                        $invalidMembership = true;
                        continue;
                    }
                    if (($membership['status'] ?? '') !== 'RESOLVED_SINGLE'
                        || count($assignments) !== 1
                        || (int) ($assignments[0]['workplace_id'] ?? 0) !== $workplaceId) {
                        continue;
                    }
                } elseif (!$allowUnversionedCurrentSource) {
                    if ($directLocalityId !== null
                        && (int) ($person->id_estacion ?? 0) === $directLocalityId) {
                        $unversionedHistoricalMembership = true;
                    }
                    // The current HR locality cannot establish historical
                    // membership, so it is deliberately not rendered as such.
                    continue;
                } elseif ($directLocalityId === null
                    || (int) ($person->id_estacion ?? 0) !== $directLocalityId) {
                    // Without a versioned history, the legacy HR source is only
                    // current evidence and is never used to reconstruct a past roster.
                    continue;
                }

                $name = trim((string) $person->nombre_completo);
                if ($workerId < 1 || $name === '' || $name === 'Nombre no disponible') {
                    continue;
                }
                $employeeNumber = $person->no_colaborador === null
                    ? ''
                    : trim((string) $person->no_colaborador);
                $workers[] = [
                    'worker_id' => $workerId,
                    'full_name' => $name,
                    'employee_number' => $employeeNumber !== '' && $employeeNumber !== '0' ? $employeeNumber : null,
                ];
            }

            if ($currentOnlyFallback) {
                if ($legacyMappingUnavailable) {
                    return self::result(
                        'partial',
                        'incomplete',
                        $currentWorkers,
                        'No se pudo resolver de forma única el vínculo activo entre la estación y la localidad; el padrón actual puede estar incompleto.'
                    );
                }
                return self::result(
                    ($invalidMembership || $invalidCurrentMembership) ? 'partial' : 'current_roster_only',
                    'current_active_roster',
                    $currentWorkers,
                    'La zona horaria de la localidad no está disponible. Esta lista refleja sólo las adscripciones activas actuales y no acredita pertenencia para la fecha seleccionada.'
                );
            }
            if ($legacyMappingUnavailable) {
                if (!$allowUnversionedCurrentSource) {
                    return self::result(
                        'partial',
                        'current_roster_historical_unverified',
                        $currentWorkers,
                        'No se pudo resolver el vínculo actual entre estación y localidad. El padrón histórico no se completa usando la estación vigente.'
                    );
                }
                return self::result(
                    'partial',
                    'incomplete',
                    $workers,
                    'No se pudo resolver de forma única el vínculo activo entre la estación y la localidad; el padrón puede estar incompleto.'
                );
            }
            if ($invalidMembership || $unversionedHistoricalMembership) {
                if (!$allowUnversionedCurrentSource) {
                    return self::result(
                        'partial',
                        'current_roster_historical_unverified',
                        $invalidCurrentMembership ? [] : $currentWorkers,
                        'La fecha es histórica y el padrón histórico canónico no está completo. Se muestra el padrón activo actual como referencia, sin atribuirlo a la fecha consultada; las marcaciones verificadas de esa fecha se presentan por separado.'
                    );
                }
                return self::result('partial', 'incomplete', $workers, 'El catálogo autorizado es parcial porque algunas membresías no pudieron validarse.');
            }
            if (!$allowUnversionedCurrentSource) {
                return self::result('ready', 'historical_versioned_membership', $workers, 'Pertenencia histórica validada mediante asignaciones canónicas vigentes para la fecha.');
            }
            return self::result('ready', 'current_active_roster', $workers, 'Personal activo atribuido mediante localidad canónica vigente.');
        } catch (Throwable) {
            return self::result('unavailable', 'unknown', [], 'No se pudo consultar el catálogo local autorizado.');
        }
    }

    /** Classify only the identity evidence available to the current attendance query. */
    public static function observedWorkerRelationship(int $workerId, array $attendanceRoster, array $scheduledWorkers = []): string
    {
        if ($workerId < 1) {
            return 'unresolved';
        }
        $habitual = false;
        foreach ($attendanceRoster['workers'] ?? [] as $worker) {
            if ((int) ($worker['worker_id'] ?? 0) === $workerId) {
                $habitual = true;
                break;
            }
        }
        $scheduledIds = array_map(
            static fn(array $worker): int => (int) ($worker['worker_id'] ?? 0),
            array_filter($scheduledWorkers, 'is_array')
        );
        $scheduled = in_array($workerId, $scheduledIds, true);
        $historicalMembership = in_array(
            $attendanceRoster['coverage'] ?? null,
            ['historical_membership_partial', 'historical_versioned_membership'],
            true
        );
        $currentOnlyForHistorical = ($attendanceRoster['coverage'] ?? null) === 'current_roster_historical_unverified';
        if ($habitual && $scheduled) {
            return $currentOnlyForHistorical
                ? 'current_roster_historical_unverified'
                : ($historicalMembership ? 'historical_and_scheduled' : 'habitual_and_scheduled');
        }
        if ($habitual) {
            return $currentOnlyForHistorical
                ? 'current_roster_historical_unverified'
                : ($historicalMembership ? 'historical_membership' : 'habitual');
        }
        if ($scheduled) return 'scheduled_comodine';
        return ($attendanceRoster['status'] ?? null) === 'ready'
            && ($attendanceRoster['coverage'] ?? null) === 'current_active_roster'
                ? 'observed_outside_roster'
                : 'observed';
    }

    /** @param list<int> $workerIds @return array<int,array{worker_id:int,full_name:string,employee_number:?string}> */
    public function observedProfiles(array $workerIds, bool $demoMode): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $workerIds), static fn(int $id): bool => $id > 0)));
        if (($demoMode && ChecadorDailyReadService::demoModeEnabled()) || $ids === []) return [];
        try {
            $people = RhPersonal::query()->whereIn('id', $ids)->get(['id', 'nombre_completo', 'no_colaborador']);
            $profiles = [];
            foreach ($people as $person) {
                $profiles[(int) $person->id] = [
                    'worker_id' => (int) $person->id,
                    'full_name' => trim((string) $person->nombre_completo) ?: 'Nombre no disponible',
                    'employee_number' => $person->no_colaborador === null ? null : (string) $person->no_colaborador,
                ];
            }
            return $profiles;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{status:string,coverage:string,workers:list<array{worker_id:int,full_name:string,employee_number:?string}>,message:string}
     */
    public function currentRoster(int $workplaceId, string $date, string $timezone, bool $demoMode): array
    {
        if ($workplaceId < 1 || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
            return self::result('unavailable', 'unknown', [], 'No se pudo validar el centro o su zona horaria laboral.');
        }

        if ($demoMode && ChecadorDailyReadService::demoModeEnabled()) {
            if ($workplaceId !== 14 || $date !== ChecadorDailyReadService::demoReferenceDate()) {
                return self::result('demo_unavailable', 'synthetic_only', [], 'El escenario de demostración sólo incluye el centro y fecha de referencia configurados.');
            }
            return self::result('demo_fixture', 'synthetic_only', [[
                'worker_id' => 900001,
                'full_name' => 'Colaborador de demostración',
                'employee_number' => null,
            ]], 'Padrón sintético aislado; no se consultó el padrón operativo.');
        }

        $today = ChecadorOperationalPeriod::currentLocalDate(
            $timezone,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
        );
        if ($today === null || $today !== $date) {
            return self::result('historical_roster_unavailable', 'observed_workers_only', [], 'No existe un padrón histórico autoritativo para esta fecha; se mostrarán sólo identidades de marcaciones observadas.');
        }

        try {
            $query = RhPersonal::query()->where('estado', 1);
            $people = $query->orderBy('id')->limit(self::MAX_ROSTER_ROWS + 1)
                ->get(['id', 'id_estacion', 'nombre_completo', 'no_colaborador']);
            $truncated = $people->count() > self::MAX_ROSTER_ROWS;
            if ($truncated) {
                $people = $people->take(self::MAX_ROSTER_ROWS);
            }

            $resolver = new WorkerWorkplaceAssignmentResolver();
            $nowUtc = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
            $legacyStationId = ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId);
            $isDepartment = ChecadorAccessService::isDepartmentLocality($workplaceId);
            $directLocalityId = $legacyStationId ?? ($isDepartment ? $workplaceId : null);
            $workers = [];
            $invalidMembership = false;
            foreach ($people as $person) {
                $workerId = (int) $person->id;
                $membership = $resolver->resolveAssignments($workerId, $nowUtc);
                if (($membership['status'] ?? '') === 'DATA_INVALID') {
                    $invalidMembership = true;
                    continue;
                }
                $belongs = false;
                foreach ($membership['assignments'] ?? [] as $assignment) {
                    if ((int) ($assignment['workplace_id'] ?? 0) === $workplaceId) {
                        $belongs = true;
                        break;
                    }
                }
                if (!$belongs && ($membership['status'] ?? '') === 'RESOLVED_NONE') {
                    if ($directLocalityId !== null && (int) ($person->id_estacion ?? 0) === $directLocalityId) {
                        $belongs = true;
                    }
                }
                if (!$belongs) {
                    continue;
                }
                $workers[] = [
                    'worker_id' => $workerId,
                    'full_name' => trim((string) $person->nombre_completo) ?: 'Nombre no disponible',
                    'employee_number' => $person->no_colaborador === null ? null : (string) $person->no_colaborador,
                ];
            }

            if ($truncated || $invalidMembership) {
                return self::result('partial', 'incomplete', $workers, $truncated
                    ? 'El padrón activo excede el máximo de lectura; la cobertura es parcial.'
                    : 'Algunas membresías canónicas no pudieron validarse; la cobertura es parcial.');
            }
            return self::result('ready', 'current_active_roster', $workers, 'Personal activo atribuido mediante membresía canónica vigente.');
        } catch (Throwable) {
            return self::result('unavailable', 'unknown', [], 'No se pudo consultar de forma segura el padrón local y sus membresías canónicas.');
        }
    }

    /** Active Portal3 workers whose canonical assignment covers local noon on the requested schedule date. */
    public function workersForScheduleDate(int $workplaceId, string $date, string $timezone, bool $demoMode): array
    {
        if ($workplaceId < 1 || !ChecadorOperationalPeriod::isIanaTimezone($timezone)
            || !self::isCivilDate($date)) {
            return self::result('unavailable', 'unknown', [], 'No se pudo validar la fecha o el centro autorizado.');
        }
        if ($demoMode && ChecadorDailyReadService::demoModeEnabled()) {
            if ($workplaceId !== 14) return self::result('demo_unavailable', 'synthetic_only', [], 'El escenario sintético no contiene personal para este centro.');
            return self::result('demo_fixture', 'synthetic_only', [[
                'worker_id' => 900001,
                'full_name' => 'Colaborador de demostración',
                'employee_number' => null,
            ]], 'Padrón sintético aislado; no se consultó el padrón operativo.');
        }
        try {
            $effectiveAt = (new \DateTimeImmutable($date . ' 12:00:00', new \DateTimeZone($timezone)))
                ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
            $people = RhPersonal::query()->where('estado', 1)->orderBy('id')
                ->limit(self::MAX_ROSTER_ROWS + 1)->get(['id', 'nombre_completo', 'no_colaborador']);
            $truncated = $people->count() > self::MAX_ROSTER_ROWS;
            if ($truncated) $people = $people->take(self::MAX_ROSTER_ROWS);
            $resolver = new WorkerWorkplaceAssignmentResolver();
            $workers = [];
            $invalid = false;
            foreach ($people as $person) {
                $membership = $resolver->resolveAssignments((int) $person->id, $effectiveAt);
                if (($membership['status'] ?? '') === 'DATA_INVALID') {
                    $invalid = true;
                    continue;
                }
                foreach ($membership['assignments'] ?? [] as $assignment) {
                    if ((int) ($assignment['workplace_id'] ?? 0) === $workplaceId) {
                        $workers[] = [
                            'worker_id' => (int) $person->id,
                            'full_name' => trim((string) $person->nombre_completo) ?: 'Nombre no disponible',
                            'employee_number' => $person->no_colaborador === null ? null : (string) $person->no_colaborador,
                        ];
                        break;
                    }
                }
            }
            if ($truncated || $invalid) return self::result('partial', 'incomplete', $workers, 'La cobertura del padrón o de las membresías canónicas es parcial.');
            return self::result('ready', 'effective_date_membership', $workers, 'Personal activo con pertenencia canónica validada para la fecha seleccionada.');
        } catch (Throwable) {
            return self::result('unavailable', 'unknown', [], 'No se pudo consultar el padrón y su pertenencia canónica.');
        }
    }

    /**
     * Return the current active station roster as reference candidates, plus
     * active workers with a verified membership in the requested week. The
     * selected-week membership is still resolved separately for every local
     * calendar day and is the only evidence used to display schedules or
     * authorize a write. The SQL candidate set is locality scoped before the
     * safe row limit.
     *
     * @return array{status:string,coverage:string,workers:list<array{worker_id:int,full_name:string,employee_number:?string,verified_dates:list<string>,versioned_verified_dates:list<string>,has_versioned_membership:bool,roster_status:string}>,message:string,reference_roster_count:int,week_membership_only_count:int,workers_with_versioned_membership:int,unversioned_membership_candidate_count:?int}
     */
    public function workersForScheduleWeek(
        int $workplaceId,
        string $weekStart,
        string $timezone,
        bool $demoMode
    ): array {
        $weekDates = ChecadorWeeklyScheduleService::dates($weekStart);
        if ($workplaceId < 1 || $weekDates === [] || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
            return self::result('unavailable', 'unknown', [], 'No se pudo validar la localidad, semana o zona horaria laboral.');
        }

        if ($demoMode && ChecadorDailyReadService::demoModeEnabled()) {
            if ($workplaceId !== 14) {
                return self::result('demo_unavailable', 'synthetic_only', [], 'El escenario de demostración no contiene personal para este centro.');
            }
            return self::result('demo_fixture', 'synthetic_only', [[
                'worker_id' => 900001,
                'full_name' => 'Colaborador de demostración',
                'employee_number' => null,
                'verified_dates' => array_column($weekDates, 'date'),
                'versioned_verified_dates' => array_column($weekDates, 'date'),
                'roster_status' => 'week_membership',
            ]], 'Padrón sintético aislado; no se consultó el padrón operativo.');
        }

        try {
            $locality = RhLocalidad::query()->whereKey($workplaceId)->first(['id', 'numlista']);
            if ($locality === null) {
                return self::result('unavailable', 'unknown', [], 'La localidad canónica no existe.');
            }

            $isAutolavado = $workplaceId === ChecadorAccessService::AUTOLAVADO_LOCALITY_ID;
            $physicalStationId = ChecadorAccessService::resolveActiveStationIdForLocality($workplaceId);
            $directLocalityId = $physicalStationId ?? ($isAutolavado ? $workplaceId : null);
            $physicalMappingUnavailable = $physicalStationId === null && !$isAutolavado;

            // Select the locality's active physical roster and its versioned
            // members before enforcing the safety ceiling. This prevents an
            // unrelated global roster from consuming the first 250 rows.
            $candidates = RhPersonal::query()
                ->where('estado', 1)
                ->where(static function ($query) use ($directLocalityId, $workplaceId): void {
                    if ($directLocalityId !== null) {
                        $query->where('id_estacion', $directLocalityId)
                            ->orWhereIn('id', RhPersonalLocalidadVersion::query()
                                ->where('workplace_id', $workplaceId)
                                ->select('worker_id')
                                ->distinct());
                    } else {
                        $query->whereIn('id', RhPersonalLocalidadVersion::query()
                            ->where('workplace_id', $workplaceId)
                            ->select('worker_id')
                            ->distinct());
                    }
                })
                ->orderBy('id')
                ->limit(self::MAX_ROSTER_ROWS + 1)
                ->get(['id', 'id_estacion', 'nombre_completo', 'no_colaborador']);

            $truncated = $candidates->count() > self::MAX_ROSTER_ROWS;
            if ($truncated) {
                $candidates = $candidates->take(self::MAX_ROSTER_ROWS);
            }

            $workerIds = $candidates->map(static fn($person): int => (int) $person->id)->all();
            $versionedWorkerIds = $workerIds === []
                ? []
                : array_fill_keys(array_map('intval', RhPersonalLocalidadVersion::query()
                    ->whereIn('worker_id', $workerIds)
                    ->distinct()
                    ->pluck('worker_id')
                    ->all()), true);

            $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $today = ChecadorOperationalPeriod::currentLocalDate($timezone, $nowUtc);
            $resolver = new WorkerWorkplaceAssignmentResolver();
            $workers = [];
            $unversionedCandidates = 0;
            $membershipGaps = false;
            $invalidMembership = false;
            $invalidCurrentMembership = false;

            foreach ($candidates as $person) {
                $workerId = (int) $person->id;
                $verifiedDates = [];
                $versionedVerifiedDates = [];
                $currentBelongs = false;

                if (!isset($versionedWorkerIds[$workerId])) {
                    $unversionedCandidates++;
                    $currentBelongs = $directLocalityId !== null
                        && (int) ($person->id_estacion ?? 0) === $directLocalityId;
                    // The current HR station can support only today's active
                    // roster. It is not a historical or future effective interval.
                    foreach ($weekDates as $day) {
                        if ($today !== null
                            && $day['date'] === $today
                            && $directLocalityId !== null
                            && (int) ($person->id_estacion ?? 0) === $directLocalityId) {
                            $verifiedDates[] = $day['date'];
                        } else {
                            $membershipGaps = true;
                        }
                    }
                } else {
                    $currentMembership = $resolver->resolveAssignments(
                        $workerId,
                        $nowUtc->format('Y-m-d\TH:i:s.u\Z')
                    );
                    if (($currentMembership['status'] ?? '') === 'DATA_INVALID') {
                        $invalidCurrentMembership = true;
                    } else {
                        $currentAssignments = $currentMembership['assignments'] ?? [];
                        $currentBelongs = ($currentMembership['status'] ?? '') === 'RESOLVED_SINGLE'
                            && count($currentAssignments) === 1
                            && (int) ($currentAssignments[0]['workplace_id'] ?? 0) === $workplaceId;
                    }

                    foreach ($weekDates as $day) {
                        $effectiveAt = (new \DateTimeImmutable($day['date'] . ' 12:00:00', new \DateTimeZone($timezone)))
                            ->setTimezone(new \DateTimeZone('UTC'))
                            ->format('Y-m-d\TH:i:s.u\Z');
                        $membership = $resolver->resolveAssignments($workerId, $effectiveAt);
                        if (($membership['status'] ?? '') === 'DATA_INVALID') {
                            $invalidMembership = true;
                            $membershipGaps = true;
                            continue;
                        }

                        $belongs = false;
                        foreach ($membership['assignments'] ?? [] as $assignment) {
                            if ((int) ($assignment['workplace_id'] ?? 0) === $workplaceId) {
                                $belongs = true;
                                break;
                            }
                        }
                        if ($belongs) {
                            $verifiedDates[] = $day['date'];
                            $versionedVerifiedDates[] = $day['date'];
                        } elseif (($membership['status'] ?? '') === 'RESOLVED_NONE') {
                            // An empty effective result does not establish
                            // the missing historical interval or future roster.
                            $membershipGaps = true;
                        }
                    }
                }

                // Keep the independently sourced current station roster visible
                // when the selected week has no verified membership at all.
                // Such a row is reference-only: matrixProjection masks all its
                // dates as membership_unverified and the date-aware selector
                // excludes it from assignment controls.
                if (!$currentBelongs && $verifiedDates === []) {
                    continue;
                }

                $name = trim((string) $person->nombre_completo);
                if ($workerId < 1 || $name === '' || $name === 'Nombre no disponible') {
                    $invalidMembership = true;
                    continue;
                }
                $employeeNumber = $person->no_colaborador === null ? '' : trim((string) $person->no_colaborador);
                $workers[] = [
                    'worker_id' => $workerId,
                    'full_name' => $name,
                    'employee_number' => $employeeNumber !== '' && $employeeNumber !== '0' ? $employeeNumber : null,
                    'verified_dates' => $verifiedDates,
                    'versioned_verified_dates' => $versionedVerifiedDates,
                    'has_versioned_membership' => isset($versionedWorkerIds[$workerId]),
                    'roster_status' => $currentBelongs ? 'current_roster' : 'week_membership',
                ];
            }

            $referenceRosterCount = count(array_filter(
                $workers,
                static fn(array $worker): bool => ($worker['roster_status'] ?? '') === 'current_roster'
            ));
            $weekOnlyCount = count($workers) - $referenceRosterCount;
            $workersWithVersionedMembership = count(array_filter(
                $workers,
                static fn(array $worker): bool => ($worker['versioned_verified_dates'] ?? []) !== []
            ));

            $partial = $truncated || $physicalMappingUnavailable || $unversionedCandidates > 0 || $membershipGaps || $invalidMembership || $invalidCurrentMembership;
            if (!$partial) {
                return self::scheduleWeekResult('ready', 'versioned_weekly_membership', $workers, 'La pertenencia de los candidatos se validó para cada día de la semana.', $referenceRosterCount, $weekOnlyCount, $workersWithVersionedMembership, 0);
            }

            $reasons = [];
            if ($truncated) $reasons[] = 'La lista local de candidatos excede el límite seguro; se omiten filas no verificadas.';
            if ($physicalMappingUnavailable) $reasons[] = 'No se resolvió de forma única la relación entre estación física y localidad; el padrón legacy no se atribuye.';
            if ($unversionedCandidates > 0 || $membershipGaps) $reasons[] = 'El padrón activo de la estación se conserva como referencia; la pertenencia de la semana sólo se confirma en las fechas con membresía verificable.';
            if ($invalidMembership || $invalidCurrentMembership) $reasons[] = 'Algunas membresías no pudieron validarse; se excluyeron de la atribución de localidad y de las fechas afectadas.';

            $unversionedCandidateCount = !$truncated && !$physicalMappingUnavailable ? $unversionedCandidates : null;
            return self::scheduleWeekResult('partial', 'incomplete', $workers, implode(' ', array_unique($reasons)), $referenceRosterCount, $weekOnlyCount, $workersWithVersionedMembership, $unversionedCandidateCount);
        } catch (Throwable) {
            return self::result('unavailable', 'unknown', [], 'No se pudo consultar de forma segura el padrón local y sus membresías canónicas.');
        }
    }

    private static function isCivilDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        return $parsed !== false && $parsed->format('Y-m-d') === $date
            && !array_filter(\DateTimeImmutable::getLastErrors() ?: []);
    }

    /** @return array{status:string,coverage:string,workers:list<array<string,mixed>>,message:string} */
    private static function result(string $status, string $coverage, array $workers, string $message): array
    {
        return ['status' => $status, 'coverage' => $coverage, 'workers' => $workers, 'message' => $message];
    }

    /** @return array<string,mixed> */
    private static function scheduleWeekResult(
        string $status,
        string $coverage,
        array $workers,
        string $message,
        int $referenceRosterCount,
        int $weekOnlyCount,
        int $workersWithVersionedMembership,
        ?int $unversionedMembershipCandidateCount
    ): array {
        return self::result($status, $coverage, $workers, $message) + [
            'reference_roster_count' => $referenceRosterCount,
            'week_membership_only_count' => $weekOnlyCount,
            'workers_with_versioned_membership' => $workersWithVersionedMembership,
            'unversioned_membership_candidate_count' => $unversionedMembershipCandidateCount,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalLocalidadVersion;
use App\Models\Operativo\RhLocalidad;
use Throwable;

/** Read-only current-day roster constrained by canonical bitemporal workplace membership. */
final class ChecadorAttendanceRosterService
{
    public const MAX_ROSTER_ROWS = 250;

    /** @param list<int> $workerIds @return array<int,array{worker_id:int,full_name:string,employee_number:?string}> */
    public function observedProfiles(array $workerIds, bool $demoMode): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $workerIds), static fn(int $id): bool => $id > 0)));
        if ($demoMode || $ids === []) return [];
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

    public function currentRoster(int $workplaceId, string $date, string $timezone, bool $demoMode): array
    {
        if ($workplaceId < 1 || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
            return self::result('unavailable', 'unknown', [], 'No se pudo validar el centro o su zona horaria laboral.');
        }

        if ($demoMode) {
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
                ->get(['id', 'nombre_completo', 'no_colaborador']);
            $truncated = $people->count() > self::MAX_ROSTER_ROWS;
            if ($truncated) {
                $people = $people->take(self::MAX_ROSTER_ROWS);
            }

            $resolver = new WorkerWorkplaceAssignmentResolver();
            $nowUtc = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
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
        if ($demoMode) {
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
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhPersonal;
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

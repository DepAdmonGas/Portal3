<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalLocalidadVersion;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use LogicException;

/** Append-only write boundary for bitemporal worker-Workplace memberships. */
final class WorkerWorkplaceAssignmentVersionService
{
    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** Return the current operation instant in strict UTC RFC3339 form. */
    public function nowUtc(): string
    {
        return ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Append a single-locality transition while the caller retains the worker
     * row lock. The old fact is superseded by a corrected closed interval and
     * the new locality starts at the same half-open boundary.
     *
     * @return array{closed_version_id:int,new_version_id:int}
     */
    public function appendCurrentLocalityTransition(
        int $workerId,
        int $expectedCurrentWorkplaceId,
        int $newWorkplaceId,
        string $validFromUtc,
        int $actorId,
        string $reason
    ): array {
        $connection = Capsule::connection();
        if ($connection->transactionLevel() < 1) {
            throw new LogicException('A locality transition requires a caller-owned transaction.');
        }
        if ($workerId <= 0 || $expectedCurrentWorkplaceId <= 0 || $newWorkplaceId <= 0
            || $actorId <= 0 || trim($reason) === '') {
            throw new InvalidArgumentException('A transition requires canonical IDs, an actor, and a reason.');
        }
        if ($expectedCurrentWorkplaceId === $newWorkplaceId) {
            throw new InvalidArgumentException('A locality transition must change the canonical Workplace.');
        }

        $effectiveAt = WorkplaceTimezoneTimestamp::normalize($validFromUtc);
        $effectiveInstant = (new DateTimeImmutable($effectiveAt, new DateTimeZone('UTC')))
            ->format('Y-m-d\TH:i:s.u\Z');
        $worker = RhPersonal::query()->whereKey($workerId)->lockForUpdate()->first(['id', 'id_estacion']);
        if ($worker === null) {
            throw new DomainException('Canonical worker op_rh_personal.id does not exist.');
        }
        if ((int) $worker->id_estacion !== $expectedCurrentWorkplaceId) {
            throw new DomainException('Worker current locality changed before the versioned transition.');
        }
        $localityIdsToLock = [$expectedCurrentWorkplaceId, $newWorkplaceId];
        sort($localityIdsToLock, SORT_NUMERIC);
        foreach ($localityIdsToLock as $localityId) {
            if (RhLocalidad::query()->whereKey($localityId)->lockForUpdate()->first(['id']) === null) {
                throw new DomainException('Both transition Workplaces must exist in op_rh_localidades.id.');
            }
        }

        $beforeEffective = (new DateTimeImmutable($effectiveAt, new DateTimeZone('UTC')))
            ->modify('-1 microsecond')
            ->format('Y-m-d\TH:i:s.u\Z');
        $resolved = (new WorkerWorkplaceAssignmentResolver($this->clock))
            ->resolveAssignments($workerId, $beforeEffective);
        if ($resolved['status'] !== 'RESOLVED_SINGLE'
            || (int) $resolved['assignments'][0]['workplace_id'] !== $expectedCurrentWorkplaceId) {
            throw new DomainException('Current assignment history is missing, ambiguous, or does not match the HR source.');
        }

        $currentVersion = RhPersonalLocalidadVersion::query()
            ->whereKey((int) $resolved['assignments'][0]['id'])
            ->where('worker_id', $workerId)
            ->lockForUpdate()
            ->first();
        if ($currentVersion === null) {
            throw new DomainException('Current assignment version disappeared during transition.');
        }
        $currentValidFrom = self::databaseTimestamp((string) $currentVersion->valid_from_utc);
        if (WorkplaceTimezoneTimestamp::normalize($currentValidFrom) >= $effectiveAt) {
            throw new DomainException('A transition cannot precede or equal its current assignment start.');
        }

        $closedVersionId = $this->appendVersion(
            $workerId,
            $expectedCurrentWorkplaceId,
            $currentValidFrom,
            $effectiveInstant,
            $actorId,
            $reason,
            (int) $currentVersion->id
        );
        $newVersionId = $this->appendVersion(
            $workerId,
            $newWorkplaceId,
            $effectiveInstant,
            null,
            $actorId,
            $reason
        );

        return ['closed_version_id' => $closedVersionId, 'new_version_id' => $newVersionId];
    }

    public function appendVersion(
        int $workerId,
        int $workplaceId,
        string $validFromUtc,
        ?string $validToUtc = null,
        ?int $actorId = null,
        ?string $reason = null,
        ?int $supersedesId = null
    ): int {
        if ($workerId <= 0 || $workplaceId <= 0) {
            throw new InvalidArgumentException('Canonical worker and Workplace IDs must be positive.');
        }
        if ($actorId !== null && $actorId <= 0) {
            throw new InvalidArgumentException('Actor ID must be positive or null.');
        }
        if ($reason !== null && mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Reason must not exceed 500 characters.');
        }

        $from = WorkplaceTimezoneTimestamp::normalize($validFromUtc);
        $to = $validToUtc === null ? null : WorkplaceTimezoneTimestamp::normalize($validToUtc);
        if ($to !== null && $to <= $from) {
            throw new InvalidArgumentException('Assignment interval must have valid_to_utc later than valid_from_utc.');
        }
        if ($supersedesId !== null && $supersedesId <= 0) {
            throw new InvalidArgumentException('supersedes_id must be positive or null.');
        }

        $recordedAt = ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

        return Capsule::connection()->transaction(function () use (
            $workerId,
            $workplaceId,
            $from,
            $to,
            $actorId,
            $reason,
            $supersedesId,
            $recordedAt
        ): int {
            // Serialize writes for one worker; different workers never share this lock.
            $worker = RhPersonal::query()->whereKey($workerId)->lockForUpdate()->first();
            if ($worker === null) {
                throw new DomainException('Canonical worker op_rh_personal.id does not exist.');
            }
            if (RhLocalidad::query()->whereKey($workplaceId)->lockForUpdate()->first(['id']) === null) {
                throw new DomainException('Canonical Workplace op_rh_localidades.id does not exist.');
            }

            $rows = RhPersonalLocalidadVersion::query()
                ->where('worker_id', $workerId)
                ->orderBy('id')
                ->get();

            $supersededIds = [];
            foreach ($rows as $row) {
                if ($row->supersedes_id !== null) {
                    $targetId = (int)$row->supersedes_id;
                    if (isset($supersededIds[$targetId])) {
                        throw new DomainException('Existing assignment correction lineage branches from one version.');
                    }
                    $supersededIds[$targetId] = true;
                }
            }

            if ($supersedesId !== null) {
                $target = $rows->first(static fn($row): bool => (int)$row->id === $supersedesId);
                if ($target === null || isset($supersededIds[$supersedesId])) {
                    throw new DomainException('Correction target must be an unsuperseded version of this worker.');
                }
            }

            $active = [];
            foreach ($rows as $row) {
                $id = (int)$row->id;
                if (isset($supersededIds[$id]) || $id === $supersedesId) {
                    continue;
                }
                $active[] = self::intervalFromRow($row);
            }

            for ($i = 0, $count = count($active); $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($active[$i]['workplace_id'] === $active[$j]['workplace_id']
                        && self::overlaps($active[$i]['from'], $active[$i]['to'], $active[$j]['from'], $active[$j]['to'])) {
                        throw new DomainException('Existing active assignment history overlaps for one worker/Workplace membership.');
                    }
                }
            }

            foreach ($active as $known) {
                if ($known['workplace_id'] === $workplaceId
                    && self::overlaps($from, $to, $known['from'], $known['to'])) {
                    throw new DomainException('Effective assignments may not overlap for one worker/Workplace membership.');
                }
            }

            $row = RhPersonalLocalidadVersion::query()->create([
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'valid_from_utc' => $from,
                'valid_to_utc' => $to,
                'recorded_at_utc' => $recordedAt,
                'recorded_by' => $actorId,
                'reason' => $reason,
                'supersedes_id' => $supersedesId,
            ]);

            return (int)$row->id;
        });
    }

    /** @return array{workplace_id:int,from:string,to:?string} */
    private static function intervalFromRow(RhPersonalLocalidadVersion $row): array
    {
        $from = WorkplaceTimezoneTimestamp::normalize(self::databaseTimestamp((string)$row->valid_from_utc));
        $to = $row->valid_to_utc === null
            ? null
            : WorkplaceTimezoneTimestamp::normalize(self::databaseTimestamp((string)$row->valid_to_utc));
        if ($to !== null && $to <= $from) {
            throw new DomainException('Existing worker-Workplace assignment contains an invalid interval.');
        }

        return ['workplace_id' => (int)$row->workplace_id, 'from' => $from, 'to' => $to];
    }

    private static function databaseTimestamp(string $value): string
    {
        if (!preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/D', $value, $parts)) {
            throw new DomainException('Stored assignment DATETIME has an invalid representation.');
        }

        return $parts[1] . 'T' . $parts[2] . '.' . str_pad($parts[3] ?? '', 6, '0') . 'Z';
    }

    private static function overlaps(string $aFrom, ?string $aTo, string $bFrom, ?string $bTo): bool
    {
        return ($aTo === null || $bFrom < $aTo) && ($bTo === null || $aFrom < $bTo);
    }
}

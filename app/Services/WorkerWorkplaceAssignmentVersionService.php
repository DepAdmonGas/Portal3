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

/** Append-only write boundary for bitemporal worker-Workplace memberships. */
final class WorkerWorkplaceAssignmentVersionService
{
    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
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
            if (!RhLocalidad::query()->whereKey($workplaceId)->exists()) {
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

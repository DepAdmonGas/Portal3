<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhPersonal;
use App\Models\Operativo\RhPersonalLocalidadVersion;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Resolves the active set of canonical Workplaces without a legacy fallback. */
final class WorkerWorkplaceAssignmentResolver
{
    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @return array{status:string,worker_id:int,effective_at_utc:string,recorded_as_of_utc:string,assignments:list<array<string,mixed>>} */
    public function resolveAssignments(int $workerId, string $effectiveInstant, ?string $recordedAt = null): array
    {
        try {
            $effectiveAtUtc = WorkplaceTimezoneTimestamp::normalize($effectiveInstant);
            $recordedAsOfUtc = $recordedAt === null
                ? ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')
                : WorkplaceTimezoneTimestamp::normalize($recordedAt);
        } catch (Throwable) {
            return $this->result('DATA_INVALID', $workerId, '', '', []);
        }

        if ($workerId <= 0 || !RhPersonal::query()->whereKey($workerId)->exists()) {
            return $this->result('DATA_INVALID', $workerId, $effectiveAtUtc, $recordedAsOfUtc, []);
        }

        $rows = RhPersonalLocalidadVersion::query()
            ->where('worker_id', $workerId)
            ->where('recorded_at_utc', '<=', $recordedAsOfUtc)
            ->orderBy('id')
            ->get();

        $byId = [];
        $supersededIds = [];
        foreach ($rows as $row) {
            $id = (int)$row->id;
            $byId[$id] = $row;
            if ($row->supersedes_id !== null) {
                $targetId = (int)$row->supersedes_id;
                if (!isset($supersededIds[$targetId])) {
                    $supersededIds[$targetId] = $id;
                } else {
                    return $this->result('DATA_INVALID', $workerId, $effectiveAtUtc, $recordedAsOfUtc, []);
                }
            }
        }

        foreach ($supersededIds as $targetId => $_successorId) {
            if (!isset($byId[$targetId])) {
                return $this->result('DATA_INVALID', $workerId, $effectiveAtUtc, $recordedAsOfUtc, []);
            }
        }

        $active = [];
        foreach ($rows as $row) {
            if (isset($supersededIds[(int)$row->id])) {
                continue;
            }
            $workplaceId = (int)$row->workplace_id;
            if ($workplaceId <= 0) {
                return $this->result('DATA_INVALID', $workerId, $effectiveAtUtc, $recordedAsOfUtc, []);
            }

            try {
                $from = WorkplaceTimezoneTimestamp::normalize(self::databaseTimestamp((string)$row->valid_from_utc));
                $to = $row->valid_to_utc === null
                    ? null
                    : WorkplaceTimezoneTimestamp::normalize(self::databaseTimestamp((string)$row->valid_to_utc));
            } catch (Throwable) {
                return $this->result('DATA_INVALID', $workerId, $effectiveAtUtc, $recordedAsOfUtc, []);
            }
            if ($to !== null && $to <= $from) {
                return $this->result('DATA_INVALID', $workerId, $effectiveAtUtc, $recordedAsOfUtc, []);
            }

            try {
                $recordedAt = self::databaseTimestamp((string)$row->recorded_at_utc);
            } catch (Throwable) {
                return $this->result('DATA_INVALID', $workerId, $effectiveAtUtc, $recordedAsOfUtc, []);
            }

            $active[] = [
                'id' => (int)$row->id,
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'valid_from_utc' => $from,
                'valid_to_utc' => $to,
                'recorded_at_utc' => $recordedAt,
                'recorded_by' => $row->recorded_by === null ? null : (int)$row->recorded_by,
                'reason' => $row->reason,
                'supersedes_id' => $row->supersedes_id === null ? null : (int)$row->supersedes_id,
            ];
        }

        for ($i = 0, $count = count($active); $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($active[$i]['workplace_id'] === $active[$j]['workplace_id']
                    && self::overlaps(
                        $active[$i]['valid_from_utc'],
                        $active[$i]['valid_to_utc'],
                        $active[$j]['valid_from_utc'],
                        $active[$j]['valid_to_utc']
                    )) {
                    return $this->result('DATA_INVALID', $workerId, $effectiveAtUtc, $recordedAsOfUtc, []);
                }
            }
        }

        $assignments = array_values(array_filter(
            $active,
            static fn(array $row): bool => $row['valid_from_utc'] <= $effectiveAtUtc
                && ($row['valid_to_utc'] === null || $effectiveAtUtc < $row['valid_to_utc'])
        ));
        usort($assignments, static fn(array $a, array $b): int =>
            [$a['workplace_id'], $a['valid_from_utc'], $a['id']] <=> [$b['workplace_id'], $b['valid_from_utc'], $b['id']]
        );

        $status = match (count($assignments)) {
            0 => 'RESOLVED_NONE',
            1 => 'RESOLVED_SINGLE',
            default => 'RESOLVED_MULTIPLE',
        };

        return $this->result($status, $workerId, $effectiveAtUtc, $recordedAsOfUtc, $assignments);
    }

    /** @return array{status:string,worker_id:int,effective_at_utc:string,recorded_as_of_utc:string,assignments:list<array<string,mixed>>} */
    public function resolveAssignmentsAsRecordedAt(int $workerId, string $effectiveInstant, string $recordedAt): array
    {
        return $this->resolveAssignments($workerId, $effectiveInstant, $recordedAt);
    }

    private function result(string $status, int $workerId, string $effectiveAt, string $recordedAt, array $assignments): array
    {
        return [
            'status' => $status,
            'worker_id' => $workerId,
            'effective_at_utc' => $effectiveAt,
            'recorded_as_of_utc' => $recordedAt,
            'assignments' => $assignments,
        ];
    }

    private static function databaseTimestamp(string $value): string
    {
        if (!preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/D', $value, $parts)) {
            throw new \InvalidArgumentException('Stored assignment DATETIME has an invalid representation.');
        }

        return $parts[1] . 'T' . $parts[2] . '.' . str_pad($parts[3] ?? '', 6, '0') . 'Z';
    }

    private static function overlaps(string $aFrom, ?string $aTo, string $bFrom, ?string $bTo): bool
    {
        return ($aTo === null || $bFrom < $aTo) && ($bTo === null || $aFrom < $bTo);
    }
}

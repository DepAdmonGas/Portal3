<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\PersonalScheduleVersion;
use App\Models\Operativo\PersonalScheduleDayDefinition;
use App\Models\Operativo\PersonalScheduleSegment;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;

/** Atomic, append-only write boundary for weekly bitemporal schedule versions. */
final class PersonalScheduleVersionService
{
    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @param list<array{weekday_iso:int,day_state:string,segments:list<array<string,mixed>>}> $week
     */
    public function appendVersion(
        int $workerId,
        int $workplaceId,
        string $validFromLocalDate,
        ?string $validToLocalDate,
        array $week,
        ?int $actorId = null,
        ?string $reason = null,
        ?int $supersedesId = null,
        string $recordSource = 'PORTAL3',
        int $definitionVersion = 1
    ): int {
        if ($workerId <= 0 || $workplaceId <= 0) {
            throw new InvalidArgumentException('Canonical worker and Workplace IDs must be positive.');
        }
        if ($actorId !== null && $actorId <= 0) {
            throw new InvalidArgumentException('recorded_by must be a positive actor ID or null.');
        }
        if ($reason !== null && mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Schedule change reason must not exceed 500 characters.');
        }
        if (trim($recordSource) === '' || mb_strlen($recordSource) > 64) {
            throw new InvalidArgumentException('record_source must contain 1 to 64 characters.');
        }
        if ($definitionVersion < 1 || $definitionVersion > 65_535) {
            throw new InvalidArgumentException('definition_version must be an unsigned small integer greater than zero.');
        }
        if ($supersedesId !== null && $supersedesId <= 0) {
            throw new InvalidArgumentException('supersedes_id must be positive or null.');
        }

        $validFrom = PersonalScheduleDefinitionValidator::normalizeLocalDate($validFromLocalDate);
        $validTo = $validToLocalDate === null
            ? null
            : PersonalScheduleDefinitionValidator::normalizeLocalDate($validToLocalDate);
        if ($validTo !== null && $validTo <= $validFrom) {
            throw new InvalidArgumentException('Schedule effective date range must be non-empty and half-open.');
        }
        $normalizedWeek = PersonalScheduleDefinitionValidator::normalizeWeek($week);

        return Capsule::connection()->transaction(function () use (
            $workerId,
            $workplaceId,
            $validFrom,
            $validTo,
            $normalizedWeek,
            $actorId,
            $reason,
            $supersedesId,
            $recordSource,
            $definitionVersion
        ): int {
            // Stable row lock serializes this worker's writes; overlap validation remains Workplace-scoped.
            if (RhPersonal::query()->whereKey($workerId)->lockForUpdate()->first() === null) {
                throw new DomainException('Canonical worker op_rh_personal.id does not exist.');
            }
            if (!RhLocalidad::query()->whereKey($workplaceId)->exists()) {
                throw new DomainException('Canonical Workplace op_rh_localidades.id does not exist.');
            }
            // Take record time only after acquiring the worker lock so a waiting correction cannot predate its predecessor.
            $recordedAt = ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

            $rows = PersonalScheduleVersion::query()->where('worker_id', $workerId)->orderBy('id')->get();
            $supersededIds = [];
            $byId = [];
            foreach ($rows as $row) {
                $id = (int)$row->id;
                $byId[$id] = $row;
                if ($row->supersedes_id !== null) {
                    $targetId = (int)$row->supersedes_id;
                    if (isset($supersededIds[$targetId])) {
                        throw new DomainException('Existing schedule correction lineage branches from one version.');
                    }
                    $supersededIds[$targetId] = true;
                }
            }
            if ($supersedesId !== null) {
                $target = $byId[$supersedesId] ?? null;
                if ($target === null || isset($supersededIds[$supersedesId])) {
                    throw new DomainException('Correction target must be an unsuperseded version of this worker.');
                }
                if (self::storedTimestamp((string)$target->recorded_at_utc) >= $recordedAt) {
                    throw new DomainException('Correction record time must be later than its predecessor.');
                }
            }

            $active = [];
            foreach ($rows as $row) {
                $id = (int)$row->id;
                if (isset($supersededIds[$id]) || $id === $supersedesId) {
                    continue;
                }
                $from = self::storedDate((string)$row->valid_from_local_date);
                $to = $row->valid_to_local_date === null ? null : self::storedDate((string)$row->valid_to_local_date);
                if ($to !== null && $to <= $from) {
                    throw new DomainException('Existing schedule history contains an invalid effective date range.');
                }
                $active[] = ['workplace_id' => (int)$row->workplace_id, 'from' => $from, 'to' => $to];
            }

            for ($i = 0, $count = count($active); $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($active[$i]['workplace_id'] === $active[$j]['workplace_id']
                        && self::overlaps($active[$i]['from'], $active[$i]['to'], $active[$j]['from'], $active[$j]['to'])) {
                        throw new DomainException('Existing schedule history overlaps for one worker/Workplace pair.');
                    }
                }
            }
            foreach ($active as $known) {
                if ($known['workplace_id'] === $workplaceId
                    && self::overlaps($validFrom, $validTo, $known['from'], $known['to'])) {
                    throw new DomainException('Effective schedule versions may not overlap for one worker/Workplace pair.');
                }
            }

            // Membership history is intentionally not a write-time prerequisite; independent domains may be corrected/backfilled separately.
            $version = PersonalScheduleVersion::query()->create([
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'valid_from_local_date' => $validFrom,
                'valid_to_local_date' => $validTo,
                'recorded_at_utc' => $recordedAt,
                'recorded_by' => $actorId,
                'record_source' => $recordSource,
                'definition_version' => $definitionVersion,
                'reason' => $reason,
                'supersedes_id' => $supersedesId,
            ]);

            foreach ($normalizedWeek as $dayData) {
                $day = PersonalScheduleDayDefinition::query()->create([
                    'schedule_version_id' => (int)$version->id,
                    'weekday_iso' => $dayData['weekday_iso'],
                    'day_state' => $dayData['day_state'],
                ]);
                foreach ($dayData['segments'] as $segmentData) {
                    PersonalScheduleSegment::query()->create([
                        'day_definition_id' => (int)$day->id,
                        'sequence_number' => $segmentData['sequence_number'],
                        'start_local_time' => $segmentData['start_local_time'],
                        'end_local_time' => $segmentData['end_local_time'],
                        'end_day_offset' => $segmentData['end_day_offset'],
                    ]);
                }
            }

            return (int)$version->id;
        });
    }

    private static function storedDate(string $value): string
    {
        try {
            return PersonalScheduleDefinitionValidator::normalizeLocalDate(substr($value, 0, 10));
        } catch (InvalidArgumentException $exception) {
            throw new DomainException('Stored schedule date has an invalid representation.', 0, $exception);
        }
    }

    private static function storedTimestamp(string $value): string
    {
        if (!preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/D', $value, $parts)) {
            throw new DomainException('Stored schedule record timestamp has an invalid representation.');
        }
        return $parts[1] . ' ' . $parts[2] . '.' . str_pad($parts[3] ?? '', 6, '0');
    }

    private static function overlaps(string $aFrom, ?string $aTo, string $bFrom, ?string $bTo): bool
    {
        return ($aTo === null || $bFrom < $aTo) && ($bTo === null || $aFrom < $bTo);
    }
}

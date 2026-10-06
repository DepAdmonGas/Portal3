<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\PersonalScheduleVersion;
use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhPersonal;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Minimal bitemporal version selector; it does not resolve Journey outcomes. */
final class PersonalScheduleHistorySelector
{
    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @return array{status:string,worker_id:int,workplace_id:int,local_workday_date:string,recorded_as_of_utc:string,version:?array,selected_weekday_definition:?array}
     */
    public function selectVersion(int $workerId, int $workplaceId, string $localWorkdayDate, ?string $recordedAt = null): array
    {
        try {
            $localDate = PersonalScheduleDefinitionValidator::normalizeLocalDate($localWorkdayDate);
            $recordedAsOf = $recordedAt === null
                ? ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')
                : WorkplaceTimezoneTimestamp::normalize($recordedAt);
        } catch (Throwable) {
            return $this->result('DATA_INVALID', $workerId, $workplaceId, '', '', null, null);
        }
        if ($workerId <= 0 || $workplaceId <= 0
            || !RhPersonal::query()->whereKey($workerId)->exists()
            || !RhLocalidad::query()->whereKey($workplaceId)->exists()) {
            return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
        }

        try {
            $rows = PersonalScheduleVersion::query()
                ->where('worker_id', $workerId)
                ->where('recorded_at_utc', '<=', $recordedAsOf)
                ->orderBy('id')
                ->get();
            $byId = [];
            $supersededIds = [];
            foreach ($rows as $row) {
                $id = (int)$row->id;
                $byId[$id] = $row;
                if ($row->supersedes_id !== null) {
                    $targetId = (int)$row->supersedes_id;
                    if (isset($supersededIds[$targetId])) {
                        return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
                    }
                    $supersededIds[$targetId] = $id;
                }
            }
            foreach ($supersededIds as $targetId => $_successorId) {
                if (!isset($byId[$targetId])) {
                    return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
                }
                $successor = $byId[$_successorId];
                if (self::databaseTimestamp((string)$successor->recorded_at_utc)
                    <= self::databaseTimestamp((string)$byId[$targetId]->recorded_at_utc)) {
                    return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
                }
            }

            $active = [];
            foreach ($rows as $row) {
                if (isset($supersededIds[(int)$row->id])) {
                    continue;
                }
                $from = self::storedDate((string)$row->valid_from_local_date);
                $to = $row->valid_to_local_date === null ? null : self::storedDate((string)$row->valid_to_local_date);
                if ($to !== null && $to <= $from) {
                    return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
                }
                $active[] = [
                    'row' => $row,
                    'workplace_id' => (int)$row->workplace_id,
                    'from' => $from,
                    'to' => $to,
                ];
            }
            for ($i = 0, $count = count($active); $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($active[$i]['workplace_id'] === $active[$j]['workplace_id']
                        && self::overlaps($active[$i]['from'], $active[$i]['to'], $active[$j]['from'], $active[$j]['to'])) {
                        return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
                    }
                }
            }

            $matches = array_values(array_filter(
                $active,
                static fn(array $candidate): bool => $candidate['workplace_id'] === $workplaceId
                    && $candidate['from'] <= $localDate
                    && ($candidate['to'] === null || $localDate < $candidate['to'])
            ));
            if ($matches === []) {
                return $this->result('NO_VERSION', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
            }
            if (count($matches) !== 1) {
                return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
            }

            $row = $matches[0]['row'];
            $dayRows = $row->days()->with('segments')->get();
            $inputWeek = [];
            foreach ($dayRows as $day) {
                $segments = [];
                foreach ($day->segments as $segment) {
                    $segments[] = [
                        'sequence_number' => (int)$segment->sequence_number,
                        'start_local_time' => self::databaseTime((string)$segment->start_local_time),
                        'end_local_time' => self::databaseTime((string)$segment->end_local_time),
                        'end_day_offset' => (int)$segment->end_day_offset,
                    ];
                }
                $inputWeek[] = [
                    'weekday_iso' => (int)$day->weekday_iso,
                    'day_state' => (string)$day->day_state,
                    'segments' => $segments,
                ];
            }
            $normalizedWeek = PersonalScheduleDefinitionValidator::normalizeWeek($inputWeek);
            $persistedDayIds = [];
            foreach ($dayRows as $day) {
                $persistedDayIds[(int)$day->weekday_iso] = [
                    'id' => (int)$day->id,
                    'segments' => $day->segments->keyBy('sequence_number'),
                ];
            }
            $resultWeek = [];
            foreach ($normalizedWeek as $day) {
                $persisted = $persistedDayIds[$day['weekday_iso']] ?? null;
                if ($persisted === null) {
                    return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
                }
                foreach ($day['segments'] as &$segmentData) {
                    $persistedSegment = $persisted['segments']->get($segmentData['sequence_number']);
                    if ($persistedSegment === null) {
                        return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
                    }
                    $segmentData['id'] = (int)$persistedSegment->id;
                }
                unset($segmentData);
                $resultWeek[] = ['id' => $persisted['id']] + $day;
            }

            $weekdayIso = (int)(new DateTimeImmutable($localDate, new DateTimeZone('UTC')))->format('N');
            $selectedDay = null;
            foreach ($resultWeek as $day) {
                if ($day['weekday_iso'] === $weekdayIso) {
                    $selectedDay = $day;
                    break;
                }
            }
            if ($selectedDay === null) {
                return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
            }

            $version = [
                'id' => (int)$row->id,
                'worker_id' => (int)$row->worker_id,
                'workplace_id' => (int)$row->workplace_id,
                'valid_from_local_date' => $matches[0]['from'],
                'valid_to_local_date' => $matches[0]['to'],
                'recorded_at_utc' => self::databaseTimestamp((string)$row->recorded_at_utc),
                'recorded_by' => $row->recorded_by === null ? null : (int)$row->recorded_by,
                'record_source' => (string)$row->record_source,
                'definition_version' => (int)$row->definition_version,
                'reason' => $row->reason,
                'supersedes_id' => $row->supersedes_id === null ? null : (int)$row->supersedes_id,
                'week' => $resultWeek,
            ];

            return $this->result('VERSION_FOUND', $workerId, $workplaceId, $localDate, $recordedAsOf, $version, $selectedDay);
        } catch (Throwable) {
            return $this->result('DATA_INVALID', $workerId, $workplaceId, $localDate, $recordedAsOf, null, null);
        }
    }

    public function selectVersionAsRecordedAt(int $workerId, int $workplaceId, string $localWorkdayDate, string $recordedAt): array
    {
        return $this->selectVersion($workerId, $workplaceId, $localWorkdayDate, $recordedAt);
    }

    private function result(
        string $status,
        int $workerId,
        int $workplaceId,
        string $localDate,
        string $recordedAt,
        ?array $version,
        ?array $selectedDay
    ): array {
        return [
            'status' => $status,
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'local_workday_date' => $localDate,
            'recorded_as_of_utc' => $recordedAt,
            'version' => $version,
            'selected_weekday_definition' => $selectedDay,
        ];
    }

    private static function storedDate(string $value): string
    {
        return PersonalScheduleDefinitionValidator::normalizeLocalDate(substr($value, 0, 10));
    }

    private static function databaseTime(string $value): string
    {
        if (!preg_match('/\A([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d{1,6})?\z/D', $value, $parts)) {
            throw new \InvalidArgumentException('Stored local TIME has an invalid representation.');
        }
        return sprintf('%02d:%02d:%02d', (int)$parts[1], (int)$parts[2], (int)$parts[3]);
    }

    private static function databaseTimestamp(string $value): string
    {
        if (!preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/D', $value, $parts)) {
            throw new \InvalidArgumentException('Stored schedule record timestamp has an invalid representation.');
        }
        return $parts[1] . 'T' . $parts[2] . '.' . str_pad($parts[3] ?? '', 6, '0') . 'Z';
    }

    private static function overlaps(string $aFrom, ?string $aTo, string $bFrom, ?string $bTo): bool
    {
        return ($aTo === null || $bFrom < $aTo) && ($bTo === null || $aFrom < $bTo);
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhLocalidadTimezoneVersion;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Read-only composition of versioned schedule, Workplace timezone and membership history. */
final class EffectiveScheduleResolver
{
    private WorkplaceTimezoneResolver $timezoneResolver;
    private WorkerWorkplaceAssignmentResolver $assignmentResolver;
    private PersonalScheduleHistorySelector $scheduleSelector;

    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(
        ?WorkplaceTimezoneResolver $timezoneResolver = null,
        ?WorkerWorkplaceAssignmentResolver $assignmentResolver = null,
        ?PersonalScheduleHistorySelector $scheduleSelector = null,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->timezoneResolver = $timezoneResolver ?? new WorkplaceTimezoneResolver($this->clock);
        $this->assignmentResolver = $assignmentResolver ?? new WorkerWorkplaceAssignmentResolver($this->clock);
        $this->scheduleSelector = $scheduleSelector ?? new PersonalScheduleHistorySelector($this->clock);
    }

    public function resolve(
        int $workerId,
        int $workplaceId,
        string $localWorkdayDate,
        ?string $recordedAsOfUtc = null,
    ): EffectiveScheduleResult {
        try {
            $localDate = PersonalScheduleDefinitionValidator::normalizeLocalDate($localWorkdayDate);
            $recordedAsOf = $recordedAsOfUtc === null
                ? ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')
                : WorkplaceTimezoneTimestamp::normalize($recordedAsOfUtc);
            $recordedAsOfArgument = self::asRfc3339($recordedAsOf);
        } catch (Throwable) {
            return $this->result(EffectiveScheduleResult::DATA_INVALID, 'INVALID_INPUT', $workerId, $workplaceId, '', '');
        }

        if ($workerId <= 0 || $workplaceId <= 0) {
            return $this->result(EffectiveScheduleResult::DATA_INVALID, 'INVALID_INPUT', $workerId, $workplaceId, $localDate, $recordedAsOf);
        }

        $schedule = $this->scheduleSelector->selectVersion($workerId, $workplaceId, $localDate, $recordedAsOfArgument);
        if ($schedule['status'] === 'NO_VERSION') {
            return $this->result(EffectiveScheduleResult::UNKNOWN, 'SCHEDULE_UNRESOLVED', $workerId, $workplaceId, $localDate, $recordedAsOf);
        }
        if ($schedule['status'] !== 'VERSION_FOUND'
            || !is_array($schedule['version'])
            || !is_array($schedule['selected_weekday_definition'])) {
            return $this->result(EffectiveScheduleResult::DATA_INVALID, 'INVALID_PERSISTED_STATE', $workerId, $workplaceId, $localDate, $recordedAsOf);
        }

        $version = $schedule['version'];
        $day = $schedule['selected_weekday_definition'];
        $scheduleVersionId = (int)($version['id'] ?? 0);
        $scheduleDayId = (int)($day['id'] ?? 0);
        $dayState = $day['day_state'] ?? null;
        if ($scheduleVersionId <= 0 || $scheduleDayId <= 0
            || !in_array($dayState, ['SCHEDULED', 'DAY_OFF', 'UNSCHEDULED'], true)
            || !is_array($day['segments'] ?? null)) {
            return $this->result(EffectiveScheduleResult::DATA_INVALID, 'INVALID_PERSISTED_STATE', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId ?: null, $scheduleDayId ?: null);
        }

        $timezone = $this->resolveSelfConsistentTimezone($workplaceId, $localDate, $recordedAsOfArgument);
        if ($timezone['status'] !== 'RESOLVED') {
            $status = $timezone['status'] === 'DATA_INVALID' ? EffectiveScheduleResult::DATA_INVALID : EffectiveScheduleResult::UNKNOWN;
            return $this->result(
                $status,
                $timezone['reason'],
                $workerId,
                $workplaceId,
                $localDate,
                $recordedAsOf,
                $scheduleVersionId,
                $scheduleDayId,
                $timezone['timezone'] ?? null,
                $timezone['local_day_start_utc'] ?? null,
            );
        }

        $timezoneData = $timezone['timezone'];
        $dayStartUtc = $timezone['local_day_start_utc'];

        if ($dayState !== 'SCHEDULED') {
            $membership = $this->assignmentsAt($workerId, $workplaceId, $dayStartUtc, $recordedAsOfArgument);
            if ($membership['status'] === 'DATA_INVALID') {
                return $this->result(EffectiveScheduleResult::DATA_INVALID, 'INVALID_PERSISTED_STATE', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc);
            }
            if ($membership['status'] !== 'RESOLVED') {
                return $this->result(EffectiveScheduleResult::UNKNOWN, 'ASSIGNMENT_UNRESOLVED', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc);
            }
            if ($membership['ids'] === []) {
                return $this->result(EffectiveScheduleResult::UNKNOWN, 'NOT_ASSIGNED', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc);
            }
            $status = $dayState === 'DAY_OFF' ? EffectiveScheduleResult::DAY_OFF : EffectiveScheduleResult::UNSCHEDULED;
            return $this->result($status, null, $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc, $membership['ids']);
        }

        $rawSegments = $day['segments'];
        if ($rawSegments === []) {
            return $this->result(EffectiveScheduleResult::DATA_INVALID, 'INVALID_PERSISTED_STATE', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc);
        }

        $segments = [];
        $allAssignmentIds = [];
        $primaryAnchorUtc = null;
        foreach ($rawSegments as $rawSegment) {
            if (!is_array($rawSegment)) {
                return $this->result(EffectiveScheduleResult::DATA_INVALID, 'INVALID_PERSISTED_STATE', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc);
            }
            try {
                $segment = self::normalizeSegment($rawSegment);
                $startLocal = $localDate . ' ' . $segment['start_local_time'];
                $endDate = self::addCalendarDays($localDate, $segment['end_day_offset']);
                $endLocal = $endDate . ' ' . $segment['end_local_time'];
                $start = self::localDateTimeCandidates($startLocal, new DateTimeZone($timezoneData['timezone_iana']));
                $end = self::localDateTimeCandidates($endLocal, new DateTimeZone($timezoneData['timezone_iana']));
            } catch (Throwable) {
                return $this->result(EffectiveScheduleResult::DATA_INVALID, 'TIME_MATERIALIZATION_FAILED', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc, [], null, $segments);
            }

            if ($start['status'] !== 'RESOLVED' || $end['status'] !== 'RESOLVED') {
                $reason = $start['status'] === 'AMBIGUOUS' || $end['status'] === 'AMBIGUOUS'
                    ? 'TIME_AMBIGUOUS'
                    : ($start['status'] === 'NONEXISTENT' || $end['status'] === 'NONEXISTENT' ? 'TIME_NONEXISTENT' : 'TIME_MATERIALIZATION_FAILED');
                $status = $reason === 'TIME_MATERIALIZATION_FAILED' ? EffectiveScheduleResult::DATA_INVALID : EffectiveScheduleResult::UNKNOWN;
                return $this->result($status, $reason, $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc, [], null, $segments);
            }

            $startUtc = $start['instants'][0];
            $endUtc = $end['instants'][0];
            if ($endUtc <= $startUtc) {
                return $this->result(EffectiveScheduleResult::DATA_INVALID, 'INVALID_PERSISTED_STATE', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc, [], null, $segments);
            }
            if ($startUtc < $timezoneData['valid_from_utc']
                || ($timezoneData['valid_to_utc'] !== null && $endUtc > $timezoneData['valid_to_utc'])) {
                return $this->result(EffectiveScheduleResult::UNKNOWN, 'TIMEZONE_TRANSITION_WITHIN_WORKDAY', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc, [], null, $segments);
            }

            $covered = $this->coverSegment($workerId, $workplaceId, $startUtc, $endUtc, $recordedAsOfArgument);
            if ($covered['status'] === 'DATA_INVALID') {
                return $this->result(EffectiveScheduleResult::DATA_INVALID, 'INVALID_PERSISTED_STATE', $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc, $allAssignmentIds, $primaryAnchorUtc, $segments);
            }
            if ($covered['status'] !== 'COVERED') {
                $reason = $covered['status'] === 'UNRESOLVED' ? 'ASSIGNMENT_UNRESOLVED' : 'ASSIGNMENT_NOT_COVERING_SCHEDULE';
                return $this->result(EffectiveScheduleResult::UNKNOWN, $reason, $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc, $allAssignmentIds, $primaryAnchorUtc, $segments);
            }

            $segmentAssignmentIds = $covered['ids'];
            foreach ($segmentAssignmentIds as $id) {
                $allAssignmentIds[] = $id;
            }
            $primaryAnchorUtc = $primaryAnchorUtc === null || $startUtc < $primaryAnchorUtc ? $startUtc : $primaryAnchorUtc;
            $segments[] = [
                'id' => $segment['id'],
                'ordinal' => $segment['ordinal'],
                'start_local_time' => $segment['start_local_time'],
                'end_local_time' => $segment['end_local_time'],
                'end_day_offset' => $segment['end_day_offset'],
                'start_local_datetime' => $startLocal,
                'end_local_datetime' => $endLocal,
                'start_utc' => $startUtc,
                'end_utc' => $endUtc,
                'timezone_version_id' => $timezoneData['version_id'],
                'assignment_version_ids' => $segmentAssignmentIds,
            ];
        }

        $allAssignmentIds = array_values(array_unique($allAssignmentIds));
        return $this->result(EffectiveScheduleResult::SCHEDULED, null, $workerId, $workplaceId, $localDate, $recordedAsOf, $scheduleVersionId, $scheduleDayId, $timezoneData, $dayStartUtc, $allAssignmentIds, $primaryAnchorUtc, $segments);
    }

    /** @return array<string,mixed> */
    private function resolveSelfConsistentTimezone(int $workplaceId, string $localDate, string $recordedAsOfArgument): array
    {
        try {
            $rows = RhLocalidadTimezoneVersion::query()
                ->where('workplace_id', $workplaceId)
                ->where('recorded_at_utc', '<=', str_replace(['T', 'Z'], [' ', ''], $recordedAsOfArgument))
                ->orderBy('id')
                ->get();
        } catch (Throwable) {
            return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
        }
        if ($rows->isEmpty()) {
            return ['status' => 'UNKNOWN', 'reason' => 'TIMEZONE_UNRESOLVED'];
        }

        $byId = [];
        $supersededBy = [];
        foreach ($rows as $row) {
            $id = (int)$row->id;
            if ($id <= 0 || isset($byId[$id])) {
                return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
            }
            $byId[$id] = $row;
            if ($row->supersedes_id !== null) {
                $targetId = (int)$row->supersedes_id;
                if ($targetId <= 0 || isset($supersededBy[$targetId])) {
                    return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
                }
                $supersededBy[$targetId] = $id;
            }
        }
        foreach ($supersededBy as $targetId => $successorId) {
            if (!isset($byId[$targetId])) {
                return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
            }
            try {
                if (self::storedUtc((string)$byId[$successorId]->recorded_at_utc)
                    <= self::storedUtc((string)$byId[$targetId]->recorded_at_utc)) {
                    return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
                }
            } catch (Throwable) {
                return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
            }
        }

        $active = [];
        foreach ($rows as $row) {
            if (isset($supersededBy[(int)$row->id])) {
                continue;
            }
            try {
                $zone = (string)$row->timezone_iana;
                if (!WorkplaceTimezoneVersionService::isValidIanaTimezone($zone)) {
                    return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
                }
                $from = self::storedUtc((string)$row->valid_from_utc);
                $to = $row->valid_to_utc === null ? null : self::storedUtc((string)$row->valid_to_utc);
                if ($to !== null && $to <= $from) {
                    return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
                }
                $active[] = [
                    'version_id' => (int)$row->id,
                    'timezone_iana' => $zone,
                    'valid_from_utc' => $from,
                    'valid_to_utc' => $to,
                    'recorded_at_utc' => self::storedUtc((string)$row->recorded_at_utc),
                ];
            } catch (Throwable) {
                return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
            }
        }

        $hasOverlap = false;
        for ($i = 0, $count = count($active); $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (($active[$i]['valid_to_utc'] === null || $active[$j]['valid_from_utc'] < $active[$i]['valid_to_utc'])
                    && ($active[$j]['valid_to_utc'] === null || $active[$i]['valid_from_utc'] < $active[$j]['valid_to_utc'])) {
                    $hasOverlap = true;
                }
            }
        }

        $matches = [];
        $relevantGap = false;
        $relevantAmbiguity = false;
        $dayStart = $localDate . ' 00:00:00';
        foreach ($active as $candidate) {
            try {
                $wall = self::localDateTimeCandidates($dayStart, new DateTimeZone($candidate['timezone_iana']));
            } catch (Throwable) {
                return ['status' => 'DATA_INVALID', 'reason' => 'TIME_MATERIALIZATION_FAILED'];
            }
            if ($wall['status'] === 'RESOLVED') {
                $instant = $wall['instants'][0];
                if ($candidate['valid_from_utc'] <= $instant
                    && ($candidate['valid_to_utc'] === null || $instant < $candidate['valid_to_utc'])) {
                    $matches[] = $candidate + ['local_day_start_utc' => $instant];
                }
            } elseif ($wall['status'] === 'AMBIGUOUS') {
                foreach ($wall['instants'] as $instant) {
                    if ($candidate['valid_from_utc'] <= $instant
                        && ($candidate['valid_to_utc'] === null || $instant < $candidate['valid_to_utc'])) {
                        $relevantAmbiguity = true;
                        break;
                    }
                }
            } elseif ($wall['status'] === 'NONEXISTENT' && $wall['transition_utc'] !== null) {
                $transition = $wall['transition_utc'];
                if ($candidate['valid_from_utc'] <= $transition
                    && ($candidate['valid_to_utc'] === null || $transition < $candidate['valid_to_utc'])) {
                    $relevantGap = true;
                }
            }
        }

        if ($relevantAmbiguity) {
            return ['status' => 'DATA_INVALID', 'reason' => 'TIME_AMBIGUOUS'];
        }
        if (count($matches) > 1) {
            return ['status' => 'DATA_INVALID', 'reason' => 'TIMEZONE_AMBIGUOUS'];
        }
        if ($hasOverlap) {
            return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
        }
        if ($matches === []) {
            return ['status' => 'UNKNOWN', 'reason' => $relevantGap ? 'TIME_NONEXISTENT' : 'TIMEZONE_UNRESOLVED'];
        }

        $selected = $matches[0];
        $resolved = $this->timezoneResolver->resolve($workplaceId, self::asRfc3339($selected['local_day_start_utc']), $recordedAsOfArgument);
        if (($resolved['status'] ?? null) !== 'RESOLVED'
            || (int)($resolved['version_id'] ?? 0) !== $selected['version_id']) {
            return ['status' => 'DATA_INVALID', 'reason' => 'INVALID_PERSISTED_STATE'];
        }

        return [
            'status' => 'RESOLVED',
            'reason' => null,
            'local_day_start_utc' => $selected['local_day_start_utc'],
            'timezone' => $selected,
        ];
    }

    /** @return array{status:string,ids:list<int>} */
    private function assignmentsAt(int $workerId, int $workplaceId, string $instantUtc, string $recordedAsOfArgument): array
    {
        $resolved = $this->assignmentResolver->resolveAssignments($workerId, self::asRfc3339($instantUtc), $recordedAsOfArgument);
        if (($resolved['status'] ?? null) === 'DATA_INVALID') {
            return ['status' => 'DATA_INVALID', 'ids' => []];
        }
        if (!in_array($resolved['status'] ?? null, ['RESOLVED_NONE', 'RESOLVED_SINGLE', 'RESOLVED_MULTIPLE'], true)) {
            return ['status' => 'UNRESOLVED', 'ids' => []];
        }
        $ids = [];
        foreach ($resolved['assignments'] ?? [] as $assignment) {
            if ((int)($assignment['workplace_id'] ?? 0) === $workplaceId) {
                $id = (int)($assignment['id'] ?? 0);
                if ($id <= 0) {
                    return ['status' => 'DATA_INVALID', 'ids' => []];
                }
                $ids[] = $id;
            }
        }
        if (count($ids) > 1) {
            return ['status' => 'DATA_INVALID', 'ids' => []];
        }
        return ['status' => 'RESOLVED', 'ids' => $ids];
    }

    /** @return array{status:string,ids:list<int>} */
    private function coverSegment(int $workerId, int $workplaceId, string $startUtc, string $endUtc, string $recordedAsOfArgument): array
    {
        $cursor = $startUtc;
        $ids = [];
        while ($cursor < $endUtc) {
            $resolved = $this->assignmentResolver->resolveAssignments($workerId, self::asRfc3339($cursor), $recordedAsOfArgument);
            if (($resolved['status'] ?? null) === 'DATA_INVALID') {
                return ['status' => 'DATA_INVALID', 'ids' => []];
            }
            if (!in_array($resolved['status'] ?? null, ['RESOLVED_NONE', 'RESOLVED_SINGLE', 'RESOLVED_MULTIPLE'], true)) {
                return ['status' => 'UNRESOLVED', 'ids' => []];
            }
            $matches = [];
            foreach ($resolved['assignments'] ?? [] as $assignment) {
                if ((int)($assignment['workplace_id'] ?? 0) === $workplaceId) {
                    $matches[] = $assignment;
                }
            }
            if (count($matches) > 1) {
                return ['status' => 'DATA_INVALID', 'ids' => []];
            }
            if ($matches === []) {
                return ['status' => 'GAP', 'ids' => []];
            }
            $assignment = $matches[0];
            $from = $assignment['valid_from_utc'] ?? null;
            $to = $assignment['valid_to_utc'] ?? null;
            $id = (int)($assignment['id'] ?? 0);
            if (!is_string($from) || $from > $cursor || $id <= 0
                || ($to !== null && (!is_string($to) || $to <= $cursor))) {
                return ['status' => 'DATA_INVALID', 'ids' => []];
            }
            $ids[] = $id;
            if ($to === null || $to >= $endUtc) {
                return ['status' => 'COVERED', 'ids' => array_values(array_unique($ids))];
            }
            $cursor = $to;
        }
        return ['status' => 'COVERED', 'ids' => array_values(array_unique($ids))];
    }

    /** @return array{id:int,ordinal:int,start_local_time:string,end_local_time:string,end_day_offset:int} */
    private static function normalizeSegment(array $segment): array
    {
        $id = (int)($segment['id'] ?? 0);
        $ordinal = (int)($segment['sequence_number'] ?? 0);
        $offset = $segment['end_day_offset'] ?? null;
        $start = $segment['start_local_time'] ?? null;
        $end = $segment['end_local_time'] ?? null;
        if ($id <= 0 || $ordinal <= 0 || !is_int($offset) || $offset < 0 || $offset > 4_294_967_295
            || !is_string($start) || !preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d\z/D', $start)
            || !is_string($end) || !preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d\z/D', $end)) {
            throw new \InvalidArgumentException('Persisted schedule segment is invalid.');
        }
        return ['id' => $id, 'ordinal' => $ordinal, 'start_local_time' => $start, 'end_local_time' => $end, 'end_day_offset' => $offset];
    }

    private static function addCalendarDays(string $date, int $days): string
    {
        $base = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        if ($base === false) {
            throw new \InvalidArgumentException('Local workday date cannot be materialized.');
        }
        $end = $base->modify('+' . $days . ' days');
        if ($end === false || ($days > 0 && (int)$end->format('U') <= (int)$base->format('U'))) {
            throw new \OutOfRangeException('Schedule day offset cannot be materialized safely.');
        }
        return $end->format('Y-m-d');
    }

    /** @return array{status:string,instants:list<string>,transition_utc:?string} */
    private static function localDateTimeCandidates(string $localDateTime, DateTimeZone $timezone): array
    {
        $wall = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $localDateTime, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($wall === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $wall->format('Y-m-d H:i:s') !== $localDateTime) {
            return ['status' => 'INVALID', 'instants' => [], 'transition_utc' => null];
        }
        $wallTimestamp = $wall->getTimestamp();
        $window = 3 * 86_400;
        if ($wallTimestamp < PHP_INT_MIN + $window || $wallTimestamp > PHP_INT_MAX - $window) {
            return ['status' => 'INVALID', 'instants' => [], 'transition_utc' => null];
        }
        $transitions = $timezone->getTransitions($wallTimestamp - $window, $wallTimestamp + $window);
        if (!is_array($transitions) || $transitions === []) {
            return ['status' => 'INVALID', 'instants' => [], 'transition_utc' => null];
        }
        $offsets = [];
        foreach ($transitions as $transition) {
            if (isset($transition['offset']) && is_int($transition['offset'])) {
                $offsets[$transition['offset']] = true;
            }
        }
        $instants = [];
        foreach (array_keys($offsets) as $offset) {
            $candidateTimestamp = $wallTimestamp - (int)$offset;
            $candidate = (new DateTimeImmutable('@' . $candidateTimestamp))->setTimezone($timezone);
            if ($candidate->format('Y-m-d H:i:s') === $localDateTime) {
                $instant = $candidate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
                $instants[$instant] = true;
            }
        }
        $instants = array_keys($instants);
        sort($instants, SORT_STRING);
        if (count($instants) === 1) {
            return ['status' => 'RESOLVED', 'instants' => $instants, 'transition_utc' => null];
        }
        if (count($instants) > 1) {
            return ['status' => 'AMBIGUOUS', 'instants' => $instants, 'transition_utc' => null];
        }

        $previousOffset = null;
        foreach ($transitions as $transition) {
            if (!isset($transition['ts'], $transition['offset']) || !is_int($transition['ts']) || !is_int($transition['offset'])) {
                continue;
            }
            $newOffset = (int)$transition['offset'];
            if ($previousOffset !== null && $newOffset > $previousOffset) {
                $gapStart = (int)$transition['ts'] + $previousOffset;
                $gapEnd = (int)$transition['ts'] + $newOffset;
                if ($wallTimestamp >= $gapStart && $wallTimestamp < $gapEnd) {
                    return [
                        'status' => 'NONEXISTENT',
                        'instants' => [],
                        'transition_utc' => (new DateTimeImmutable('@' . (int)$transition['ts']))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
                    ];
                }
            }
            $previousOffset = $newOffset;
        }
        return ['status' => 'INVALID', 'instants' => [], 'transition_utc' => null];
    }

    private static function storedUtc(string $value): string
    {
        if (!preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/D', $value, $parts)) {
            throw new \InvalidArgumentException('Stored UTC DATETIME has an invalid representation.');
        }
        return WorkplaceTimezoneTimestamp::normalize($parts[1] . 'T' . $parts[2] . '.' . str_pad($parts[3] ?? '', 6, '0') . 'Z');
    }

    private static function asRfc3339(string $utc): string
    {
        return str_replace(' ', 'T', $utc) . 'Z';
    }

    private function result(
        string $status,
        ?string $reason,
        int $workerId,
        int $workplaceId,
        string $localDate,
        string $recordedAsOf,
        ?int $scheduleVersionId = null,
        ?int $scheduleDayId = null,
        ?array $timezone = null,
        ?string $dayStartUtc = null,
        array $assignmentIds = [],
        ?string $primaryAnchorUtc = null,
        array $segments = [],
    ): EffectiveScheduleResult {
        return new EffectiveScheduleResult(
            $status,
            $reason,
            $workerId,
            $workplaceId,
            $localDate,
            $recordedAsOf,
            $dayStartUtc,
            $timezone,
            $scheduleVersionId,
            $scheduleDayId,
            array_values(array_unique($assignmentIds)),
            $primaryAnchorUtc,
            $segments,
        );
    }
}

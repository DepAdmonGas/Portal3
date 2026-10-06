<?php

declare(strict_types=1);

namespace App\Services;

/** Explicit outcome and provenance for one worker/Workplace/local-day query. */
final class EffectiveScheduleResult
{
    public const SCHEDULED = 'SCHEDULED';
    public const DAY_OFF = 'DAY_OFF';
    public const UNSCHEDULED = 'UNSCHEDULED';
    public const UNKNOWN = 'UNKNOWN';
    public const DATA_INVALID = 'DATA_INVALID';

    /** @param list<int> $assignmentVersionIds
     *  @param list<array<string,mixed>> $segments
     *  @param array<string,mixed>|null $timezone
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $reasonCode,
        public readonly int $workerId,
        public readonly int $workplaceId,
        public readonly string $localWorkdayDate,
        public readonly string $recordedAsOfUtc,
        public readonly ?string $localDayStartUtc = null,
        public readonly ?array $timezone = null,
        public readonly ?int $scheduleVersionId = null,
        public readonly ?int $scheduleDayId = null,
        public readonly array $assignmentVersionIds = [],
        public readonly ?string $assignmentPrimaryAnchorUtc = null,
        public readonly array $segments = [],
    ) {
        if (!in_array($status, [self::SCHEDULED, self::DAY_OFF, self::UNSCHEDULED, self::UNKNOWN, self::DATA_INVALID], true)) {
            throw new \InvalidArgumentException('Unsupported effective schedule result status.');
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'reason_code' => $this->reasonCode,
            'worker_id' => $this->workerId,
            'workplace_id' => $this->workplaceId,
            'local_workday_date' => $this->localWorkdayDate,
            'recorded_as_of_utc' => $this->recordedAsOfUtc,
            'local_day_start_utc' => $this->localDayStartUtc,
            'timezone' => $this->timezone,
            'schedule_version_id' => $this->scheduleVersionId,
            'schedule_day_id' => $this->scheduleDayId,
            'assignment_version_ids' => $this->assignmentVersionIds,
            'assignment_primary_anchor_utc' => $this->assignmentPrimaryAnchorUtc,
            'segments' => $this->segments,
        ];
    }
}

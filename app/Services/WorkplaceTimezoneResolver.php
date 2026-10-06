<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhLocalidadTimezoneVersion;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Resolves versioned IANA identity without consulting any ambient timezone. */
final class WorkplaceTimezoneResolver
{
    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @return array{status:string, workplace_id:int, effective_at_utc:string, recorded_as_of_utc:string, version_id?:int, timezone_iana?:string, valid_from_utc?:string, valid_to_utc:?string, recorded_at_utc?:string}
     */
    public function resolve(int $workplaceId, string $effectiveAt, ?string $recordedAsOf = null): array
    {
        try {
            $effectiveAtUtc = WorkplaceTimezoneTimestamp::normalize($effectiveAt);
            $recordedAtUtc = $recordedAsOf === null
                ? ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u')
                : WorkplaceTimezoneTimestamp::normalize($recordedAsOf);
        } catch (Throwable) {
            return $this->result('DATA_INVALID', $workplaceId, '', '');
        }

        if ($workplaceId <= 0 || !RhLocalidad::query()->whereKey($workplaceId)->exists()) {
            return $this->result('DATA_INVALID', $workplaceId, $effectiveAtUtc, $recordedAtUtc);
        }

        $rows = RhLocalidadTimezoneVersion::query()
            ->where('workplace_id', $workplaceId)
            ->where('recorded_at_utc', '<=', $recordedAtUtc)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return $this->result('TIMEZONE_UNRESOLVED', $workplaceId, $effectiveAtUtc, $recordedAtUtc);
        }

        $supersededIds = [];
        foreach ($rows as $row) {
            if ($row->supersedes_id !== null) {
                $supersededIds[(int)$row->supersedes_id] = true;
            }
        }

        $active = [];
        foreach ($rows as $row) {
            if (isset($supersededIds[(int)$row->id])) {
                continue;
            }

            try {
                $timezone = (string)$row->timezone_iana;
                if (!WorkplaceTimezoneVersionService::isValidIanaTimezone($timezone)) {
                    return $this->result('DATA_INVALID', $workplaceId, $effectiveAtUtc, $recordedAtUtc);
                }
                $from = WorkplaceTimezoneTimestamp::normalize(self::databaseTimestamp((string)$row->valid_from_utc));
                $to = $row->valid_to_utc === null
                    ? null
                    : WorkplaceTimezoneTimestamp::normalize(self::databaseTimestamp((string)$row->valid_to_utc));
                if ($to !== null && $to <= $from) {
                    return $this->result('DATA_INVALID', $workplaceId, $effectiveAtUtc, $recordedAtUtc);
                }
            } catch (Throwable) {
                return $this->result('DATA_INVALID', $workplaceId, $effectiveAtUtc, $recordedAtUtc);
            }

            $active[] = [
                'row' => $row,
                'from' => $from,
                'to' => $to,
            ];
        }

        $matches = array_values(array_filter(
            $active,
            static fn(array $candidate): bool => $candidate['from'] <= $effectiveAtUtc
                && ($candidate['to'] === null || $effectiveAtUtc < $candidate['to'])
        ));

        if ($matches === []) {
            return $this->result('TIMEZONE_UNRESOLVED', $workplaceId, $effectiveAtUtc, $recordedAtUtc);
        }
        if (count($matches) !== 1) {
            return $this->result('AMBIGUOUS', $workplaceId, $effectiveAtUtc, $recordedAtUtc);
        }

        $match = $matches[0];
        /** @var RhLocalidadTimezoneVersion $row */
        $row = $match['row'];

        return [
            'status' => 'RESOLVED',
            'workplace_id' => $workplaceId,
            'effective_at_utc' => $effectiveAtUtc,
            'recorded_as_of_utc' => $recordedAtUtc,
            'version_id' => (int)$row->id,
            'timezone_iana' => (string)$row->timezone_iana,
            'valid_from_utc' => $match['from'],
            'valid_to_utc' => $match['to'],
            'recorded_at_utc' => self::databaseTimestamp((string)$row->recorded_at_utc),
        ];
    }

    private function result(string $status, int $workplaceId, string $effectiveAtUtc, string $recordedAtUtc): array
    {
        return [
            'status' => $status,
            'workplace_id' => $workplaceId,
            'effective_at_utc' => $effectiveAtUtc,
            'recorded_as_of_utc' => $recordedAtUtc,
        ];
    }

    private static function databaseTimestamp(string $value): string
    {
        // DATETIME values have no timezone by definition; schema contract says UTC.
        if (preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/D', $value, $parts)) {
            return $parts[1] . 'T' . $parts[2] . '.' . str_pad($parts[3] ?? '', 6, '0') . 'Z';
        }
        throw new \InvalidArgumentException('Stored UTC DATETIME has an invalid representation.');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\RhLocalidadTimezoneVersion;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use Throwable;

/** Validated, append-only write boundary for Workplace timezone history. */
final class WorkplaceTimezoneVersionService
{
    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(?callable $clock = null)
    {
        $this->clock = $clock ?? static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * Append one or more intervals atomically. Each item has timezone_iana,
     * valid_from_utc, optional valid_to_utc, and optional supersedes_id.
     *
     * @param list<array{timezone_iana:string,valid_from_utc:string,valid_to_utc?:?string,supersedes_id?:?int}> $versions
     * @return list<int> inserted IDs
     */
    public function appendVersions(
        int $workplaceId,
        array $versions,
        ?int $actorId = null,
        ?string $reason = null
    ): array {
        if ($workplaceId <= 0 || $versions === []) {
            throw new InvalidArgumentException('A canonical Workplace and at least one version are required.');
        }
        if ($reason !== null && mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('Reason must not exceed 500 characters.');
        }
        if ($actorId !== null && $actorId <= 0) {
            throw new InvalidArgumentException('Actor ID must be a positive existing application identity or null.');
        }

        $normalized = [];
        $supersedes = [];
        foreach ($versions as $version) {
            $timezone = $version['timezone_iana'] ?? '';
            if (!is_string($timezone) || !self::isValidIanaTimezone($timezone)) {
                throw new InvalidArgumentException('Timezone must be an exact runtime-supported IANA identifier.');
            }

            $from = WorkplaceTimezoneTimestamp::normalize((string)($version['valid_from_utc'] ?? ''));
            $to = array_key_exists('valid_to_utc', $version) && $version['valid_to_utc'] !== null
                ? WorkplaceTimezoneTimestamp::normalize((string)$version['valid_to_utc'])
                : null;
            if ($to !== null && $to <= $from) {
                throw new InvalidArgumentException('Timezone interval must have valid_to_utc later than valid_from_utc.');
            }

            $supersedesId = isset($version['supersedes_id']) ? (int)$version['supersedes_id'] : null;
            if ($supersedesId !== null && ($supersedesId <= 0 || isset($supersedes[$supersedesId]))) {
                throw new InvalidArgumentException('Each active history row may be superseded only once per append.');
            }
            if ($supersedesId !== null) {
                $supersedes[$supersedesId] = true;
            }

            $normalized[] = [
                'timezone_iana' => $timezone,
                'valid_from_utc' => $from,
                'valid_to_utc' => $to,
                'supersedes_id' => $supersedesId,
            ];
        }

        $recordedAt = ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');

        return Capsule::connection()->transaction(function () use (
            $workplaceId,
            $normalized,
            $supersedes,
            $actorId,
            $reason,
            $recordedAt
        ): array {
            // Serializes writers for a Workplace on InnoDB (the verified local engine).
            $workplace = RhLocalidad::query()->whereKey($workplaceId)->lockForUpdate()->first();
            if ($workplace === null) {
                throw new DomainException('Canonical Workplace op_rh_localidades.id does not exist.');
            }

            $existingRows = RhLocalidadTimezoneVersion::query()
                ->where('workplace_id', $workplaceId)
                ->orderBy('id')
                ->get();
            $supersededIds = [];
            foreach ($existingRows as $row) {
                if ($row->supersedes_id !== null) {
                    $supersededIds[(int)$row->supersedes_id] = true;
                }
            }

            foreach (array_keys($supersedes) as $targetId) {
                $target = $existingRows->first(static fn($row): bool => (int)$row->id === $targetId);
                if ($target === null || isset($supersededIds[$targetId])) {
                    throw new DomainException('Correction target must be an unsuperseded version of this Workplace.');
                }
            }

            $active = [];
            foreach ($existingRows as $row) {
                if (isset($supersededIds[(int)$row->id]) || isset($supersedes[(int)$row->id])) {
                    continue;
                }
                $active[] = self::intervalFromRow($row);
            }

            for ($i = 0, $count = count($active); $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if (self::overlaps($active[$i]['from'], $active[$i]['to'], $active[$j]['from'], $active[$j]['to'])) {
                        throw new DomainException('Existing active timezone history already contains an overlap.');
                    }
                }
            }

            foreach ($normalized as $candidate) {
                foreach ($active as $known) {
                    if (self::overlaps($candidate['valid_from_utc'], $candidate['valid_to_utc'], $known['from'], $known['to'])) {
                        throw new DomainException('Effective timezone intervals may not overlap for one Workplace.');
                    }
                }
                $active[] = [
                    'from' => $candidate['valid_from_utc'],
                    'to' => $candidate['valid_to_utc'],
                ];
            }

            $ids = [];
            foreach ($normalized as $candidate) {
                $row = RhLocalidadTimezoneVersion::query()->create([
                    'workplace_id' => $workplaceId,
                    'timezone_iana' => $candidate['timezone_iana'],
                    'valid_from_utc' => $candidate['valid_from_utc'],
                    'valid_to_utc' => $candidate['valid_to_utc'],
                    'recorded_at_utc' => $recordedAt,
                    'recorded_by' => $actorId,
                    'reason' => $reason,
                    'supersedes_id' => $candidate['supersedes_id'],
                ]);
                $ids[] = (int)$row->id;
            }

            return $ids;
        });
    }

    public static function isValidIanaTimezone(string $timezone): bool
    {
        static $valid = null;
        if ($valid === null) {
            $valid = array_fill_keys(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
            $valid['UTC'] = true;
        }
        return isset($valid[$timezone]);
    }

    /** @return array{from:string,to:?string} */
    private static function intervalFromRow(RhLocalidadTimezoneVersion $row): array
    {
        $from = WorkplaceTimezoneTimestamp::normalize(self::storedTimestamp((string)$row->valid_from_utc));
        $to = $row->valid_to_utc === null
            ? null
            : WorkplaceTimezoneTimestamp::normalize(self::storedTimestamp((string)$row->valid_to_utc));
        if ($to !== null && $to <= $from) {
            throw new DomainException('Existing timezone history contains an invalid interval.');
        }
        if (!self::isValidIanaTimezone((string)$row->timezone_iana)) {
            throw new DomainException('Existing timezone history contains an invalid IANA identifier.');
        }
        return ['from' => $from, 'to' => $to];
    }

    private static function storedTimestamp(string $value): string
    {
        if (!preg_match('/\A(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/D', $value, $parts)) {
            throw new DomainException('Stored UTC DATETIME has an invalid representation.');
        }
        return $parts[1] . 'T' . $parts[2] . '.' . str_pad($parts[3] ?? '', 6, '0') . 'Z';
    }

    private static function overlaps(string $aFrom, ?string $aTo, string $bFrom, ?string $bTo): bool
    {
        return ($aTo === null || $bFrom < $aTo) && ($bTo === null || $aFrom < $bTo);
    }
}

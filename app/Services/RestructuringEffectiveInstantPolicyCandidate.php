<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Synthetic-testable proposal for Alternative B: explicit local date/time resolved against a versioned Workplace IANA zone.
 * It is deliberately not called by FormatosService while the HR policy remains unapproved.
 */
final class RestructuringEffectiveInstantPolicyCandidate
{
    /**
     * @param list<array{id:int,timezone_iana:string,valid_from_utc:string,valid_to_utc:?string}> $timezoneVersions
     * @return array{status:string,effective_at_utc?:string,timezone_iana?:string,timezone_version_id?:int,candidates?:list<array{effective_at_utc:string,timezone_iana:string,timezone_version_id:int}>}
     */
    public static function resolve(string $localDate, string $localTime, array $timezoneVersions): array
    {
        $wallText = self::validatedWallClock($localDate, $localTime);
        if ($wallText === null) {
            return ['status' => 'DATA_INVALID'];
        }
        if ($timezoneVersions === []) {
            return ['status' => 'TIMEZONE_UNRESOLVED'];
        }

        $wallEpoch = (new DateTimeImmutable($wallText, new DateTimeZone('UTC')))->getTimestamp();
        $localCandidatesExist = false;
        $qualified = [];

        foreach ($timezoneVersions as $version) {
            $versionId = (int) ($version['id'] ?? 0);
            $timezoneName = (string) ($version['timezone_iana'] ?? '');
            if ($versionId <= 0 || !WorkplaceTimezoneVersionService::isValidIanaTimezone($timezoneName)) {
                return ['status' => 'DATA_INVALID'];
            }

            try {
                $validFrom = WorkplaceTimezoneTimestamp::normalize((string) ($version['valid_from_utc'] ?? ''));
                $validTo = ($version['valid_to_utc'] ?? null) === null
                    ? null
                    : WorkplaceTimezoneTimestamp::normalize((string) $version['valid_to_utc']);
            } catch (\Throwable) {
                return ['status' => 'DATA_INVALID'];
            }
            if ($validTo !== null && $validTo <= $validFrom) {
                return ['status' => 'DATA_INVALID'];
            }

            $zone = new DateTimeZone($timezoneName);
            $transitions = $zone->getTransitions($wallEpoch - 172800, $wallEpoch + 172800);
            if ($transitions === false) {
                return ['status' => 'DATA_INVALID'];
            }

            $offsets = [];
            foreach ($transitions as $transition) {
                $offsets[(int) $transition['offset']] = true;
            }

            foreach (array_keys($offsets) as $offset) {
                $candidateEpoch = $wallEpoch - (int) $offset;
                $candidateWall = (new DateTimeImmutable('@' . $candidateEpoch))
                    ->setTimezone($zone)
                    ->format('Y-m-d H:i:s');
                if ($candidateWall !== $wallText) {
                    continue;
                }

                $localCandidatesExist = true;
                $effectiveAtUtc = gmdate('Y-m-d H:i:s', $candidateEpoch) . '.000000';
                if ($effectiveAtUtc < $validFrom || ($validTo !== null && $effectiveAtUtc >= $validTo)) {
                    continue;
                }

                $key = $effectiveAtUtc . '|' . $versionId;
                $qualified[$key] = [
                    'effective_at_utc' => str_replace(' ', 'T', $effectiveAtUtc) . 'Z',
                    'timezone_iana' => $timezoneName,
                    'timezone_version_id' => $versionId,
                ];
            }
        }

        if ($qualified === []) {
            return ['status' => $localCandidatesExist ? 'TIMEZONE_UNRESOLVED' : 'TIME_NONEXISTENT'];
        }
        if (count($qualified) !== 1) {
            return ['status' => 'TIME_AMBIGUOUS', 'candidates' => array_values($qualified)];
        }

        return ['status' => 'RESOLVED', ...array_values($qualified)[0]];
    }

    private static function validatedWallClock(string $localDate, string $localTime): ?string
    {
        if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D', $localDate, $date)
            || !preg_match('/\A(\d{2}):(\d{2})(?::(\d{2}))?\z/D', $localTime, $time)
            || !checkdate((int) $date[2], (int) $date[3], (int) $date[1])
            || (int) $time[1] > 23 || (int) $time[2] > 59 || (int) ($time[3] ?? 0) > 59) {
            return null;
        }

        return $localDate . ' ' . $time[1] . ':' . $time[2] . ':' . ($time[3] ?? '00');
    }
}

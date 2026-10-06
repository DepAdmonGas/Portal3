<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Strict parsing helpers for timestamps whose source must identify an instant. */
final class WorkplaceTimezoneTimestamp
{
    public static function normalize(string $timestamp): string
    {
        if (!preg_match(
            '/\A(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,6}))?(Z|[+-]\d{2}:\d{2})\z/D',
            $timestamp,
            $parts
        )) {
            throw new InvalidArgumentException('Timestamp must be RFC3339 with an explicit UTC offset.');
        }

        if (!checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])
            || (int)$parts[4] > 23
            || (int)$parts[5] > 59
            || (int)$parts[6] > 59) {
            throw new InvalidArgumentException('Timestamp contains an invalid calendar or clock value.');
        }

        if (isset($parts[8]) && $parts[8] !== 'Z') {
            $offset = substr($parts[8], 1);
            if ((int)substr($offset, 0, 2) > 23 || (int)substr($offset, 3, 2) > 59) {
                throw new InvalidArgumentException('Timestamp contains an invalid UTC offset.');
            }
            if ($parts[8] === '-00:00') {
                throw new InvalidArgumentException('The RFC3339 unknown-local-offset marker is not an absolute offset.');
            }
        }

        try {
            $instant = new DateTimeImmutable($timestamp);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('Timestamp cannot be parsed as an instant.', 0, $exception);
        }

        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}

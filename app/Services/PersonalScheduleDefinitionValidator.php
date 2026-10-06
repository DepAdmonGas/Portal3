<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Canonical ISO-week and local-civil-time invariants shared by writes and reads. */
final class PersonalScheduleDefinitionValidator
{
    public const DAY_STATES = ['SCHEDULED', 'DAY_OFF', 'UNSCHEDULED'];
    private const UNSIGNED_INT_MAX = 4_294_967_295;

    public static function normalizeLocalDate(string $date): string
    {
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $date)) {
            throw new InvalidArgumentException('Local workday dates must use exact YYYY-MM-DD format.');
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($parsed === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Local workday date is not a real calendar date.');
        }

        return $date;
    }

    /**
     * Input shape: list of seven rows {weekday_iso, day_state, segments}.
     * Segment ordinal and end_day_offset use unsigned 32-bit storage bounds;
     * no smaller business maximum is applied.
     *
     * @return list<array{weekday_iso:int,day_state:string,segments:list<array{sequence_number:int,start_local_time:string,end_local_time:string,end_day_offset:int}>}>
     */
    public static function normalizeWeek(array $days): array
    {
        if (!array_is_list($days) || count($days) !== 7) {
            throw new InvalidArgumentException('A complete schedule version must define exactly seven ISO weekdays.');
        }

        $normalized = [];
        $seenDays = [];
        foreach ($days as $day) {
            if (!is_array($day)) {
                throw new InvalidArgumentException('Each weekday definition must be an array.');
            }
            $weekday = $day['weekday_iso'] ?? null;
            if (!is_int($weekday) || $weekday < 1 || $weekday > 7 || isset($seenDays[$weekday])) {
                throw new InvalidArgumentException('Weekdays must be unique ISO integers from 1 (Monday) through 7 (Sunday).');
            }
            $seenDays[$weekday] = true;
            $state = $day['day_state'] ?? null;
            if (!is_string($state) || !in_array($state, self::DAY_STATES, true)) {
                throw new InvalidArgumentException('Schedule day state must be SCHEDULED, DAY_OFF, or UNSCHEDULED.');
            }
            $segments = $day['segments'] ?? null;
            if (!is_array($segments) || !array_is_list($segments)) {
                throw new InvalidArgumentException('Each weekday must contain an explicit segments list.');
            }
            if ($state === 'SCHEDULED' && count($segments) < 1) {
                throw new InvalidArgumentException('SCHEDULED requires at least one local-time segment.');
            }
            if ($state !== 'SCHEDULED' && $segments !== []) {
                throw new InvalidArgumentException($state . ' must not contain work segments.');
            }

            $normalizedSegments = [];
            $seenSequences = [];
            foreach ($segments as $segment) {
                if (!is_array($segment)) {
                    throw new InvalidArgumentException('Each schedule segment must be an array.');
                }
                $sequence = $segment['sequence_number'] ?? $segment['sequence'] ?? null;
                $offset = $segment['end_day_offset'] ?? null;
                if (!is_int($sequence) || $sequence < 1 || $sequence > self::UNSIGNED_INT_MAX || isset($seenSequences[$sequence])) {
                    throw new InvalidArgumentException('Segment sequence numbers must be unique positive unsigned 32-bit integers per weekday.');
                }
                if (!is_int($offset) || $offset < 0 || $offset > self::UNSIGNED_INT_MAX) {
                    throw new InvalidArgumentException('end_day_offset must be a non-negative unsigned 32-bit integer.');
                }
                $seenSequences[$sequence] = true;
                $start = self::normalizeLocalTime($segment['start_local_time'] ?? null);
                $end = self::normalizeLocalTime($segment['end_local_time'] ?? null);
                $startSeconds = self::timeSeconds($start);
                $endSeconds = ($offset * 86_400) + self::timeSeconds($end);
                if ($endSeconds <= $startSeconds) {
                    throw new InvalidArgumentException('Segment end must occur after its start using the explicit end_day_offset.');
                }
                $normalizedSegments[] = [
                    'sequence_number' => $sequence,
                    'start_local_time' => $start,
                    'end_local_time' => $end,
                    'end_day_offset' => $offset,
                    '_start_seconds' => $startSeconds,
                    '_end_seconds' => $endSeconds,
                ];
            }

            for ($i = 0, $count = count($normalizedSegments); $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($normalizedSegments[$i]['_start_seconds'] < $normalizedSegments[$j]['_end_seconds']
                        && $normalizedSegments[$j]['_start_seconds'] < $normalizedSegments[$i]['_end_seconds']) {
                        throw new InvalidArgumentException('Segments within one scheduled weekday may not overlap.');
                    }
                }
            }

            usort($normalizedSegments, static fn(array $a, array $b): int => $a['sequence_number'] <=> $b['sequence_number']);
            $normalized[$weekday] = [
                'weekday_iso' => $weekday,
                'day_state' => $state,
                'segments' => array_map(static function (array $segment): array {
                    unset($segment['_start_seconds'], $segment['_end_seconds']);
                    return $segment;
                }, $normalizedSegments),
            ];
        }

        if (count($seenDays) !== 7) {
            throw new InvalidArgumentException('A complete schedule version must contain ISO weekdays 1 through 7 exactly once.');
        }
        ksort($normalized);
        return array_values($normalized);
    }

    private static function normalizeLocalTime(mixed $value): string
    {
        if (!is_string($value)
            || !preg_match('/\A([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?\z/D', $value, $parts)) {
            throw new InvalidArgumentException('Local times must be exact 24-hour HH:MM or HH:MM:SS values.');
        }

        return sprintf('%02d:%02d:%02d', (int)$parts[1], (int)$parts[2], (int)($parts[3] ?? 0));
    }

    private static function timeSeconds(string $time): int
    {
        [$hours, $minutes, $seconds] = array_map('intval', explode(':', $time));
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}

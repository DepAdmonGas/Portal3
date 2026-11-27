<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Conservative presentation of a raw instant and its locality timezone versions. */
final class ChecadorPunchReportProjection
{
    /** @param list<array{timezone_iana:string,valido_desde:string,valido_hasta:?string}> $versions */
    public static function localCapture(string $utc, array $versions): array
    {
        try {
            if (!preg_match('/\A(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?\z/D', $utc, $parts)) return ['status'=>'invalid'];
            $instant = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $parts[1] . '.' . str_pad($parts[2] ?? '', 6, '0'), new DateTimeZone('UTC'));
            if (!$instant || $instant->format('Y-m-d H:i:s') !== $parts[1]) return ['status'=>'invalid'];
            $matches = [];
            foreach ($versions as $version) {
                $zone = trim((string)($version['timezone_iana'] ?? ''));
                if (!ChecadorOperationalPeriod::isIanaTimezone($zone)) return ['status'=>'invalid'];
                $local = $instant->setTimezone(new DateTimeZone($zone));
                $date = $local->format('Y-m-d');
                $from = (string)($version['valido_desde'] ?? '');
                $to = $version['valido_hasta'] ?? null;
                $validDate = static function (string $value): bool {
                    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
                    return $parsed !== false && $parsed->format('Y-m-d') === $value;
                };
                if (!$validDate($from) || ($to !== null && (!$validDate((string)$to) || (string)$to <= $from))) return ['status'=>'invalid'];
                if ($from <= $date && ($to === null || $date < (string)$to)) {
                    $matches[] = ['local_datetime'=>$local->format('Y-m-d H:i:s'), 'timezone'=>$zone, 'offset'=>$local->format('P')];
                }
            }
            if (count($matches) !== 1) return ['status'=>count($matches) === 0 ? 'unavailable' : 'ambiguous'];
            return ['status'=>'resolved', ...$matches[0]];
        } catch (Throwable) {
            return ['status'=>'invalid'];
        }
    }
    /** Build one UTC interval per local civil day; gaps or overlaps fail closed. */
    public static function localIntervals(string $from, string $to, array $versions): array
    {
        $utc = new DateTimeZone('UTC');
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $utc);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $utc);
        if (!$start || !$end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to || $start > $end || $start->diff($end)->days > 30) return ['status'=>'invalid'];
        $intervals = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $applicable = [];
            foreach ($versions as $version) {
                $a = (string)($version['valido_desde'] ?? ''); $b = $version['valido_hasta'] ?? null;
                $aDate = DateTimeImmutable::createFromFormat('!Y-m-d', $a, $utc);
                $bDate = $b === null ? null : DateTimeImmutable::createFromFormat('!Y-m-d', (string)$b, $utc);
                if (!$aDate || $aDate->format('Y-m-d') !== $a || ($b !== null && (!$bDate || $bDate->format('Y-m-d') !== $b || $b <= $a)) || !ChecadorOperationalPeriod::isIanaTimezone((string)($version['timezone_iana'] ?? ''))) return ['status'=>'invalid'];
                if ($a <= $date && ($b === null || $date < $b)) $applicable[] = $version['timezone_iana'];
            }
            if (count($applicable) !== 1) return ['status'=>count($applicable) ? 'ambiguous' : 'unavailable'];
            $zone = new DateTimeZone($applicable[0]);
            $boundaries = [];
            foreach ([$date, $day->modify('+1 day')->format('Y-m-d')] as $boundary) {
                $wall = (new DateTimeImmutable($boundary.' 00:00:00', $utc))->getTimestamp();
                $transitions = $zone->getTransitions($wall - 172800, $wall + 172800);
                $candidates = [];
                foreach ($transitions ?: [] as $transition) {
                    $stamp = $wall - (int)$transition['offset'];
                    if ((new DateTimeImmutable('@'.$stamp))->setTimezone($zone)->format('Y-m-d H:i:s') === $boundary.' 00:00:00') $candidates[$stamp] = $stamp;
                }
                if (count($candidates) !== 1) return ['status'=>'ambiguous'];
                $boundaries[] = gmdate('Y-m-d H:i:s', array_values($candidates)[0]);
            }
            if ($boundaries[0] >= $boundaries[1]) return ['status'=>'invalid'];
            if ($intervals !== [] && $boundaries[0] < $intervals[count($intervals)-1][1]) return ['status'=>'ambiguous'];
            $intervals[] = $boundaries;
        }
        return ['status'=>'resolved','intervals'=>$intervals];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

/** Bounded period helpers for the Checador UI; not an attendance calculator. */
final class ChecadorOperationalPeriod
{
    /** @return array{from_date:string,to_date:string,preset:string,year:int,month:int,label:string}|null */
    public static function reportRange(
        string $preset,
        \DateTimeImmutable $nowUtc,
        ?int $year = null,
        ?int $month = null
    ): ?array {
        $utc = new \DateTimeZone('UTC');
        $today = $nowUtc->setTimezone($utc)->setTime(0, 0);
        $currentYear = (int) $today->format('Y');
        $currentMonth = (int) $today->format('n');
        $selectedYear = $year ?? $currentYear;
        $selectedMonth = $month ?? $currentMonth;

        if ($preset === 'today') {
            $from = $today;
            $to = $today;
            $selectedYear = $currentYear;
            $selectedMonth = $currentMonth;
            $label = 'Hoy (UTC)';
        } elseif ($preset === 'last7') {
            $from = $today->modify('-6 days');
            $to = $today;
            $selectedYear = $currentYear;
            $selectedMonth = $currentMonth;
            $label = 'Últimos 7 días (UTC)';
        } elseif ($preset === 'month') {
            $from = $today->modify('first day of this month');
            $to = $today;
            $selectedYear = $currentYear;
            $selectedMonth = $currentMonth;
            $label = 'Mes actual a la fecha (UTC)';
        } elseif ($preset === 'year') {
            if ($selectedYear !== $currentYear || $selectedMonth < 1 || $selectedMonth > $currentMonth) {
                return null;
            }
            $from = $today->setDate($selectedYear, $selectedMonth, 1);
            $monthEnd = $from->modify('last day of this month');
            $to = $monthEnd > $today ? $today : $monthEnd;
            $label = 'Año en curso · ' . self::monthLabel($selectedMonth) . ' (segmento UTC)';
        } else {
            return null;
        }

        $rangeDays = (int) $from->diff($to)->format('%a') + 1;
        if ($from > $to || $rangeDays > 31) {
            return null;
        }

        return [
            'from_date' => $from->format('Y-m-d'),
            'to_date' => $to->format('Y-m-d'),
            'preset' => $preset,
            'year' => $selectedYear,
            'month' => $selectedMonth,
            'label' => $label,
        ];
    }

    public static function currentLocalDate(string $ianaTimezone, \DateTimeImmutable $nowUtc): ?string
    {
        if (!self::isIanaTimezone($ianaTimezone)) {
            return null;
        }

        try {
            return $nowUtc->setTimezone(new \DateTimeZone($ianaTimezone))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isIanaTimezone(string $timezone): bool
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            return false;
        }

        return in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true);
    }

    private static function monthLabel(int $month): string
    {
        return [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ][$month] ?? 'mes no disponible';
    }
}

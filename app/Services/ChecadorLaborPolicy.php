<?php

declare(strict_types=1);

namespace App\Services;

/** Versioned reference values only; this class does not evaluate hours or legal compliance. */
final class ChecadorLaborPolicy
{
    /** @return array{year:int,ordinary_weekly_max_hours:int,extraordinary_weekly_reference_hours:int,extraordinary_daily_max_hours:int,extraordinary_max_days_per_week:int,additional_extraordinary_weekly_cap_hours:int,ordinary_plus_extraordinary_daily_cap_hours:int,source_urls:list<string>,classification_required:bool} */
    public static function referenceForDate(string $date): ?array
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date || array_filter(\DateTimeImmutable::getLastErrors() ?: [])) {
            return null;
        }
        $year = (int) substr($date, 0, 4);
        $ordinary = [2026 => 48, 2027 => 46, 2028 => 44, 2029 => 42, 2030 => 40];
        $extraordinary = [2026 => 9, 2027 => 9, 2028 => 10, 2029 => 11, 2030 => 12];
        if (!isset($ordinary[$year])) {
            return null;
        }
        return [
            'year' => $year,
            'ordinary_weekly_max_hours' => $ordinary[$year],
            'extraordinary_weekly_reference_hours' => $extraordinary[$year],
            'extraordinary_daily_max_hours' => 4,
            'extraordinary_max_days_per_week' => 4,
            'additional_extraordinary_weekly_cap_hours' => 4,
            'ordinary_plus_extraordinary_daily_cap_hours' => 12,
            'source_urls' => [
                'https://sidof.segob.gob.mx/notas/docFuente/5781417',
                'https://dof.gob.mx/nota_detalle_popup.php?codigo=5786537',
                'https://www.diputados.gob.mx/LeyesBiblio/pdf/LFT.pdf',
            ],
            'classification_required' => true,
        ];
    }

    /** @return array{diurna:float,nocturna:float,mixta:float,diurna_period:string,nocturna_period:string,mixed_nocturnal_max_minutes_exclusive:int,applies_automatically:false} */
    public static function generalDailyReferences(): array
    {
        return [
            'diurna' => 8.0,
            'nocturna' => 7.0,
            'mixta' => 7.5,
            'diurna_period' => '06:00–20:00',
            'nocturna_period' => '20:00–06:00',
            'mixed_nocturnal_max_minutes_exclusive' => 210,
            'applies_automatically' => false,
        ];
    }
}

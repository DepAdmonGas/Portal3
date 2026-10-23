<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/** Read-only projection for the frozen V2 weekday/validity assignment contract. */
final class ChecadorWeeklyScheduleService
{
    private const DAYS = [
        1 => ['LUNES', 'Lun'], 2 => ['MARTES', 'Mar'], 3 => ['MIERCOLES', 'Mié'],
        4 => ['JUEVES', 'Jue'], 5 => ['VIERNES', 'Vie'], 6 => ['SABADO', 'Sáb'], 7 => ['DOMINGO', 'Dom'],
    ];

    public static function monday(string $date): ?string
    {
        $parsed = self::parseDate($date);
        return $parsed?->modify('monday this week')->format('Y-m-d');
    }

    public static function isCurrentOrFutureWeek(string $monday, string $timezone, ?DateTimeImmutable $nowUtc = null): bool
    {
        if (!ChecadorOperationalPeriod::isIanaTimezone($timezone) || self::monday($monday) !== $monday) return false;
        $nowUtc ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $today = ChecadorOperationalPeriod::currentLocalDate($timezone, $nowUtc);
        return $today !== null && $monday >= (self::monday($today) ?? $today);
    }

    /** @return list<array{date:string,weekday:string,label:string}> */
    public static function dates(string $monday): array
    {
        $start = self::parseDate($monday);
        if ($start === null || self::monday($monday) !== $monday) return [];
        $days = [];
        foreach (self::DAYS as $offset => [$weekday, $label]) {
            $days[] = ['date' => $start->modify('+' . ($offset - 1) . ' days')->format('Y-m-d'), 'weekday' => $weekday, 'label' => $label];
        }
        return $days;
    }

    /**
     * @param list<array<string,mixed>> $workers
     * @param array<int,list<array<string,mixed>>> $assignmentsByWorker
     * @param array<int,array<string,mixed>> $templatesById
     * @return array{dates:list<array<string,mixed>>,workers:list<array<string,mixed>>}
     */
    public static function matrix(array $workers, array $assignmentsByWorker, array $templatesById, string $monday): array
    {
        $dates = self::dates($monday);
        $rows = [];
        foreach ($workers as $worker) {
            $workerId = (int) ($worker['worker_id'] ?? 0);
            $records = $assignmentsByWorker[$workerId] ?? [];
            $cells = [];
            foreach ($dates as $day) {
                $matches = array_values(array_filter($records, static function (array $assignment) use ($day): bool {
                    return (int) ($assignment['es_activo'] ?? 0) === 1
                        && (string) ($assignment['dia_semana'] ?? '') === $day['weekday']
                        && (string) ($assignment['valido_desde'] ?? '') <= $day['date']
                        && (($assignment['valido_hasta'] ?? null) === null || $day['date'] < (string) $assignment['valido_hasta']);
                }));
                if (count($matches) > 1) {
                    $cells[$day['date']] = ['status' => 'ambiguous', 'label' => 'Conflicto: varias asignaciones', 'assignment' => null, 'template' => null];
                } elseif ($matches === []) {
                    $cells[$day['date']] = ['status' => 'unassigned', 'label' => 'Sin asignación visible', 'assignment' => null, 'template' => null];
                } else {
                    $assignment = $matches[0];
                    $templateId = $assignment['id_plantilla'] ?? null;
                    $template = $templateId === null ? null : ($templatesById[(int) $templateId] ?? null);
                    $cells[$day['date']] = $templateId === null
                        ? ['status' => 'rest', 'label' => 'Descanso explícito', 'assignment' => $assignment, 'template' => null]
                        : ['status' => $template === null ? 'template_unavailable' : 'assigned',
                            'label' => $template === null ? 'Plantilla no disponible' : (string) ($template['nombre'] ?? 'Turno'),
                            'assignment' => $assignment, 'template' => $template];
                }
            }
            $rows[] = $worker + ['cells' => $cells];
        }
        return ['dates' => $dates, 'workers' => $rows];
    }

    public static function weekdayFor(string $date): ?string
    {
        $parsed = self::parseDate($date);
        if ($parsed === null) return null;
        return self::DAYS[(int) $parsed->format('N')][0] ?? null;
    }

    private static function parseDate(string $date): ?DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date
            && !array_filter(DateTimeImmutable::getLastErrors() ?: []) ? $parsed : null;
    }
}

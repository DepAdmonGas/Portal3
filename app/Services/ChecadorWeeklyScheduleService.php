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
     * Keep only unique workers with a positive identity and versioned canonical
     * membership for every required local date. Current-roster-only dates are
     * not sufficient for an assignment write. Missing data fails closed.
     *
     * @param list<array<string,mixed>> $workers
     * @param list<string> $requiredDates
     * @return list<array<string,mixed>>
     */
    public static function workersWithVerifiedMembership(array $workers, array $requiredDates): array
    {
        if ($requiredDates === []) return [];
        foreach ($requiredDates as $date) {
            if (!is_string($date) || self::parseDate($date) === null) return [];
        }

        $eligible = [];
        $seen = [];
        foreach ($workers as $worker) {
            if (!is_array($worker)) continue;
            $workerId = (int) ($worker['worker_id'] ?? 0);
            $verifiedDates = $worker['versioned_verified_dates'] ?? null;
            if ($workerId < 1 || isset($seen[$workerId]) || !is_array($verifiedDates)) continue;
            $verified = array_fill_keys(array_filter($verifiedDates, 'is_string'), true);
            foreach ($requiredDates as $date) {
                if (!isset($verified[$date])) continue 2;
            }
            $seen[$workerId] = true;
            $eligible[] = $worker;
        }

        return $eligible;
    }

    /**
     * Keep unique workers with versioned canonical membership on at least one
     * candidate date and expose only those dates for assignment controls.
     * The write handler must still revalidate the selected date server-side.
     *
     * @param list<array<string,mixed>> $workers
     * @param list<string> $candidateDates
     * @return list<array<string,mixed>>
     */
    public static function workersWithAnyVerifiedMembership(array $workers, array $candidateDates): array
    {
        if ($candidateDates === []) return [];
        foreach ($candidateDates as $date) {
            if (!is_string($date) || self::parseDate($date) === null) return [];
        }

        $eligible = [];
        $seen = [];
        foreach ($workers as $worker) {
            if (!is_array($worker)) continue;
            $workerId = (int) ($worker['worker_id'] ?? 0);
            $verifiedDates = $worker['versioned_verified_dates'] ?? null;
            if ($workerId < 1 || isset($seen[$workerId]) || !is_array($verifiedDates)) continue;
            $verified = array_fill_keys(array_filter($verifiedDates, 'is_string'), true);
            $assignableDates = array_values(array_filter(
                $candidateDates,
                static fn(string $date): bool => isset($verified[$date])
            ));
            if ($assignableDates === []) continue;
            $seen[$workerId] = true;
            $eligible[] = $worker + ['assignable_dates' => $assignableDates];
        }

        return $eligible;
    }

    /**
     * Resolve a safe HABITUAL interval for a new recurring weekday assignment.
     * A later persisted version bounds this interval instead of causing the
     * API to reject an otherwise valid initial assignment for the gap before it.
     * Existing rows are canonical, normalized assignment history.
     *
     * @param list<array<string,mixed>> $assignments
     * @return array{ok:bool,status:string,valid_to:?string,conflicting_assignments:list<array<string,mixed>>}
     */
    public static function habitualAssignmentInterval(
        array $assignments,
        int $workerId,
        int $workplaceId,
        string $weekday,
        string $targetDate
    ): array {
        $weekday = strtoupper(trim($weekday));
        $weekdays = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'];
        if ($workerId < 1 || $workplaceId < 1 || !in_array($weekday, $weekdays, true)
            || self::parseDate($targetDate) === null) {
            return ['ok' => false, 'status' => 'invalid_request', 'valid_to' => null, 'conflicting_assignments' => []];
        }

        $matching = [];
        foreach ($assignments as $assignment) {
            if (!is_array($assignment)
                || (int) ($assignment['id_personal'] ?? 0) !== $workerId
                || (int) ($assignment['id_estacion'] ?? 0) !== $workplaceId
                || strtoupper((string) ($assignment['tipo_asignacion'] ?? 'HABITUAL')) !== 'HABITUAL'
                || strtoupper((string) ($assignment['dia_semana'] ?? '')) !== $weekday) {
                continue;
            }
            $validFrom = (string) ($assignment['valido_desde'] ?? '');
            $validToValue = $assignment['valido_hasta'] ?? null;
            $validTo = $validToValue === null ? null : (string) $validToValue;
            if (self::parseDate($validFrom) === null
                || ($validTo !== null && (self::parseDate($validTo) === null || $validTo <= $validFrom))) {
                return ['ok' => false, 'status' => 'invalid_history', 'valid_to' => null, 'conflicting_assignments' => []];
            }
            $matching[] = ['assignment' => $assignment, 'valid_from' => $validFrom, 'valid_to' => $validTo];
        }

        $covering = array_values(array_filter($matching, static fn(array $item): bool =>
            $item['valid_from'] <= $targetDate
            && ($item['valid_to'] === null || $targetDate < $item['valid_to'])
        ));
        if ($covering !== []) {
            return [
                'ok' => false,
                'status' => 'conflict',
                'valid_to' => null,
                'conflicting_assignments' => array_column($covering, 'assignment'),
            ];
        }

        $futureStarts = array_values(array_filter($matching, static fn(array $item): bool =>
            $item['valid_from'] > $targetDate
            && ($item['valid_to'] === null || $item['valid_to'] > $targetDate)
        ));
        usort($futureStarts, static fn(array $left, array $right): int =>
            $left['valid_from'] <=> $right['valid_from']
        );
        return [
            'ok' => true,
            'status' => 'ready',
            'valid_to' => $futureStarts[0]['valid_from'] ?? null,
            'conflicting_assignments' => [],
        ];
    }

    /**
     * @param list<array<string,mixed>> $workers
     * @param array<int,list<array<string,mixed>>> $assignmentsByWorker
     * @param array<int,array<string,mixed>> $templatesById
     * @return array{dates:list<array<string,mixed>>,workers:list<array<string,mixed>>}
     */
    public static function matrix(array $workers, array $assignmentsByWorker, array $templatesById, string $monday): array
    {
        $effective = self::matrixProjection($workers, $assignmentsByWorker, $templatesById, $monday, false);
        $habitual = self::matrixProjection($workers, $assignmentsByWorker, $templatesById, $monday, true);
        foreach ($habitual['workers'] as &$worker) {
            $worker['habitual_coverage'] = self::habitualCoverage($worker, $habitual['dates']);
        }
        unset($worker);
        $effective['habitual_workers'] = $habitual['workers'];
        return $effective;
    }

    /**
     * Classify seven-day habitual coverage without treating missing assignments
     * as rest or incomplete source reads as an empty schedule.
     *
     * @param array<string,mixed> $worker
     * @param list<array{date:string,weekday:string,label:string}> $dates
     * @return array{status:string,label:string,covered_days:int,total_days:int,missing_days:list<string>,assignable:bool}
     */
    public static function habitualCoverage(array $worker, array $dates): array
    {
        $total = count($dates);
        $covered = 0;
        $missing = [];
        $verifiedDates = is_array($worker['verified_dates'] ?? null)
            ? array_fill_keys(array_filter($worker['verified_dates'], 'is_string'), true)
            : null;
        $unknown = $total !== 7
            || $verifiedDates === null
            || ($worker['schedule_read_status'] ?? 'unknown') !== 'available';

        foreach ($dates as $day) {
            $date = (string) ($day['date'] ?? '');
            $cell = $worker['cells'][$date] ?? null;
            if ($verifiedDates === null || !isset($verifiedDates[$date])) {
                $unknown = true;
                continue;
            }
            if (!is_array($cell) || in_array((string) ($cell['status'] ?? ''), [
                'membership_unverified', 'schedule_unavailable', 'schedule_not_requested', 'ambiguous', 'template_unavailable',
            ], true)) {
                $unknown = true;
                continue;
            }
            if (in_array((string) ($cell['status'] ?? ''), ['assigned', 'rest'], true)) {
                $covered++;
                continue;
            }
            if (($cell['status'] ?? '') === 'unassigned') {
                $missing[] = (string) ($day['weekday'] ?? '');
                continue;
            }
            $unknown = true;
        }

        if ($unknown) {
            $status = 'unknown';
            $label = 'No confirmada';
        } elseif ($covered === 0) {
            $status = 'unassigned';
            $label = 'Sin asignación';
        } elseif ($covered < $total) {
            $status = 'partial';
            $label = 'Parcial';
        } else {
            $status = 'complete';
            $label = 'Completa';
        }

        return [
            'status' => $status,
            'label' => $label,
            'covered_days' => $covered,
            'total_days' => $total,
            'missing_days' => $missing,
            'assignable' => !$unknown && $covered < $total,
        ];
    }

    /**
     * Project a week either as the effective schedule (temporary overrides
     * take precedence) or as its recurring habitual baseline.
     *
     * @param list<array<string,mixed>> $workers
     * @param array<int,list<array<string,mixed>>> $assignmentsByWorker
     * @param array<int,array<string,mixed>> $templatesById
     * @return array{dates:list<array<string,mixed>>,workers:list<array<string,mixed>>}
     */
    private static function matrixProjection(
        array $workers,
        array $assignmentsByWorker,
        array $templatesById,
        string $monday,
        bool $habitualOnly
    ): array {
        $dates = self::dates($monday);
        $rows = [];
        foreach ($workers as $worker) {
            $workerId = (int) ($worker['worker_id'] ?? 0);
            $records = $assignmentsByWorker[$workerId] ?? [];
            $cells = [];
            foreach ($dates as $day) {
                if (array_key_exists('verified_dates', $worker)
                    && (!is_array($worker['verified_dates']) || !in_array($day['date'], $worker['verified_dates'], true))) {
                    $cells[$day['date']] = [
                        'status' => 'membership_unverified',
                        'label' => 'Pertenencia no confirmada',
                        'assignment' => null,
                        'template' => null,
                    ];
                    continue;
                }

                if (($worker['schedule_read_status'] ?? null) === 'unavailable') {
                    $cells[$day['date']] = [
                        'status' => 'schedule_unavailable',
                        'label' => 'Horario no disponible',
                        'assignment' => null,
                        'template' => null,
                    ];
                    continue;
                }

                if (($worker['schedule_read_status'] ?? null) === 'not_requested') {
                    $cells[$day['date']] = [
                        'status' => 'schedule_not_requested',
                        'label' => 'Selecciona para consultar',
                        'assignment' => null,
                        'template' => null,
                    ];
                    continue;
                }

                $matches = array_values(array_filter($records, static function (array $assignment) use ($day): bool {
                    $validFrom = (string) ($assignment['valido_desde'] ?? '');
                    $validTo = $assignment['valido_hasta'] ?? null;
                    $active = (int) ($assignment['es_activo'] ?? 0) === 1;
                    $closedHistoricalInterval = !$active
                        && is_string($validTo)
                        && $validTo > $validFrom;

                    // The validity interval is authoritative for historical days.
                    // A replacement closes the prior row (es_activo=0), but that
                    // row still governs dates before its exclusive end.
                    return ($active || $closedHistoricalInterval)
                        && (string) ($assignment['dia_semana'] ?? '') === $day['weekday']
                        && $validFrom <= $day['date']
                        && ($validTo === null || $day['date'] < (string) $validTo);
                }));

                if ($habitualOnly) {
                    $matches = array_values(array_filter(
                        $matches,
                        static fn(array $assignment): bool => strtoupper((string) ($assignment['tipo_asignacion'] ?? 'HABITUAL')) !== 'TEMPORAL'
                    ));
                }

                if ($matches === [] && ($worker['schedule_read_status'] ?? '') === 'partial') {
                    $cells[$day['date']] = [
                        'status' => 'schedule_unavailable',
                        'label' => 'Lectura parcial; no se confirma el día',
                        'assignment' => null,
                        'template' => null,
                    ];
                    continue;
                }

                $temporalMatches = array_values(array_filter($matches, static fn(array $a): bool => strtoupper((string) ($a['tipo_asignacion'] ?? 'HABITUAL')) === 'TEMPORAL'));
                $habitualMatches = array_values(array_filter($matches, static fn(array $a): bool => strtoupper((string) ($a['tipo_asignacion'] ?? 'HABITUAL')) !== 'TEMPORAL'));

                if (count($temporalMatches) > 1 || (count($temporalMatches) === 0 && count($habitualMatches) > 1)) {
                    $cells[$day['date']] = ['status' => 'ambiguous', 'label' => 'Conflicto: varias asignaciones', 'assignment' => null, 'template' => null];
                } elseif (count($temporalMatches) === 0 && count($habitualMatches) === 0) {
                    $cells[$day['date']] = ['status' => 'unassigned', 'label' => 'Sin asignación visible', 'assignment' => null, 'template' => null];
                } else {
                    $assignment = count($temporalMatches) === 1 ? $temporalMatches[0] : $habitualMatches[0];
                    $isTemporal = count($temporalMatches) === 1;
                    $tipoAsig = $isTemporal ? 'TEMPORAL' : 'HABITUAL';
                    $templateId = $assignment['id_plantilla'] ?? null;
                    $template = $templateId === null ? null : ($templatesById[(int) $templateId] ?? null);
                    $cells[$day['date']] = $templateId === null
                        ? [
                            'status' => 'rest',
                            'label' => $isTemporal ? 'Descanso temporal' : 'Descanso explícito',
                            'assignment' => $assignment,
                            'assignment_type' => $tipoAsig,
                            'template' => null,
                        ]
                        : [
                            'status' => $template === null ? 'template_unavailable' : 'assigned',
                            'label' => $template === null ? 'Plantilla no disponible' : (string) ($template['nombre'] ?? 'Turno'),
                            'assignment' => $assignment,
                            'assignment_type' => $tipoAsig,
                            'template' => $template,
                        ];
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

    /** Return the first civil date on or after the start date for a recurring weekday. */
    public static function firstWeekdayOnOrAfter(string $weekday, string $startDate): ?string
    {
        $weekday = strtoupper(trim($weekday));
        $start = self::parseDate($startDate);
        if ($start === null || !in_array($weekday, array_column(self::DAYS, 0), true)) return null;

        for ($offset = 0; $offset <= 6; $offset++) {
            $candidate = $start->modify('+' . $offset . ' days');
            if (self::weekdayFor($candidate->format('Y-m-d')) === $weekday) {
                return $candidate->format('Y-m-d');
            }
        }

        return null;
    }

    private static function parseDate(string $date): ?DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date
            && !array_filter(DateTimeImmutable::getLastErrors() ?: []) ? $parsed : null;
    }
}

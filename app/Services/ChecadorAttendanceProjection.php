<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Projects only API P1 classifications whose source and segment evidence is usable. */
final class ChecadorAttendanceProjection
{
    /** @return array<string,mixed> */
    public static function workerRow(array $profile, array $events, ?array $journey, ?string $timezone): array
    {
        $slots = [
            'ENTRY:1' => null,
            'EXIT:1' => null,
            'ENTRY:2' => null,
            'EXIT:2' => null,
        ];
        $duplicates = [];
        $classifiedIds = [];
        $sourceEvents = [];
        foreach ($events as $index => $event) {
            if (!is_array($event)) {
                continue;
            }
            $sourceEvents[self::eventKey($event, 'raw-' . $index)] = $event;
        }

        $evaluatedEvents = is_array($journey['evaluated_raw_events'] ?? null)
            ? $journey['evaluated_raw_events']
            : [];
        $inconsistentCandidateIds = [];
        $pairing = is_array($journey['pairing'] ?? null) ? $journey['pairing'] : [];
        $segments = is_array($pairing['segments'] ?? null) ? $pairing['segments'] : [];
        $segmentStatuses = [];
        $extraSegmentOrdinals = [];
        $segmentsValid = true;
        foreach ($segments as $segment) {
            if (!is_array($segment)
                || !is_int($segment['ordinal'] ?? null)
                || $segment['ordinal'] < 1
                || !is_string($segment['status'] ?? null)
                || isset($segmentStatuses[$segment['ordinal']])) {
                $segmentsValid = false;
                break;
            }
            $ordinal = $segment['ordinal'];
            $segmentStatuses[$ordinal] = $segment['status'];
            if ($ordinal >= 3) {
                $extraSegmentOrdinals[$ordinal] = true;
            }
        }

        $pairingStatus = (string) ($pairing['pairing_status'] ?? 'UNKNOWN');
        $sourceLevel2Status = (string) ($journey['source_evidence']['window_level2_status'] ?? 'UNKNOWN');
        $completenessStatus = (string) ($pairing['completeness_l2'] ?? 'UNKNOWN');
        $journeyEvaluated = ($journey['evaluated'] ?? false) === true;
        $scheduleStatus = (string) ($journey['schedule']['estado_jornada'] ?? 'UNKNOWN');
        $globalStatusTrusted = $journeyEvaluated
            && $scheduleStatus === 'SCHEDULED'
            && $sourceLevel2Status === 'VERIFIED'
            && $completenessStatus === 'VERIFIED'
            && $segmentsValid
            && in_array($pairingStatus, [
                'EXACT_PAIR',
                'PARTIAL_MISSING_IN',
                'PARTIAL_MISSING_OUT',
                'UNMATCHED_NO_PUNCHES',
                'AMBIGUOUS_PAIRING',
            ], true);

        // Merge API classifications into the verified raw event where available.
        // The API candidate remains the displayed source for events outside the local-day GET window.
        if ($journeyEvaluated) {
            foreach ($evaluatedEvents as $index => $event) {
                if (!is_array($event)) {
                    continue;
                }
                $eventKey = self::eventKey($event, 'journey-' . $index);
                $role = $event['pairing_role'] ?? null;
                $ordinal = $event['segment_ordinal'] ?? null;
                $segmentStatus = is_int($ordinal) ? ($segmentStatuses[$ordinal] ?? null) : null;
                $trustedSegment = $globalStatusTrusted
                    && is_string($segmentStatus)
                    && in_array($segmentStatus, ['EXACT_PAIR', 'PARTIAL_MISSING_IN', 'PARTIAL_MISSING_OUT'], true);

                if (isset($sourceEvents[$eventKey])) {
                    $rawTimestamp = $sourceEvents[$eventKey]['ocurrido_at_utc'] ?? null;
                    if (is_string($rawTimestamp)
                        && is_string($event['ocurrido_at_utc'] ?? null)
                        && trim($rawTimestamp) !== ''
                        && trim($event['ocurrido_at_utc']) !== ''
                        && $rawTimestamp !== $event['ocurrido_at_utc']) {
                        $trustedSegment = false;
                        $inconsistentCandidateIds[$eventKey] = true;
                    }
                    if ($trustedSegment) {
                        $sourceEvents[$eventKey]['pairing_role'] = $role;
                        $sourceEvents[$eventKey]['segment_ordinal'] = $ordinal;
                        $sourceEvents[$eventKey]['pairing_segment_status'] = $segmentStatus;
                    }
                } elseif ($globalStatusTrusted) {
                    $sourceEvents[$eventKey] = $event;
                    if ($trustedSegment) {
                        $sourceEvents[$eventKey]['pairing_segment_status'] = $segmentStatus;
                    } else {
                        unset($sourceEvents[$eventKey]['pairing_role'], $sourceEvents[$eventKey]['segment_ordinal']);
                    }
                } else {
                    // Keep a source observation, but hide classifications when completeness or schedule
                    // evidence is not sufficient to use them administratively.
                    $sourceEvents[$eventKey] = $event;
                    unset($sourceEvents[$eventKey]['pairing_role'], $sourceEvents[$eventKey]['segment_ordinal']);
                }
            }
        }

        $candidateSlots = [];
        if ($globalStatusTrusted) {
            foreach ($evaluatedEvents as $index => $event) {
                if (!is_array($event)) {
                    continue;
                }
                $role = $event['pairing_role'] ?? null;
                $ordinal = $event['segment_ordinal'] ?? null;
                if (!in_array($role, ['ENTRY', 'EXIT'], true)
                    || !is_int($ordinal)
                    || $ordinal < 1
                    || $ordinal > 2
                    || !in_array($segmentStatuses[$ordinal] ?? null, ['EXACT_PAIR', 'PARTIAL_MISSING_IN', 'PARTIAL_MISSING_OUT'], true)) {
                    continue;
                }
                $key = $role . ':' . $ordinal;
                $eventKey = self::eventKey($event, 'journey-' . $index);
                if (isset($inconsistentCandidateIds[$eventKey])) {
                    continue;
                }
                $candidateSlots[$key][] = [
                    'key' => $eventKey,
                    'event' => $event,
                ];
            }
        }

        foreach ($candidateSlots as $key => $candidates) {
            if (count($candidates) !== 1) {
                $duplicates[$key] = true;
                continue;
            }
            $candidate = $candidates[0];
            $event = $sourceEvents[$candidate['key']] ?? $candidate['event'];
            $slots[$key] = $event;
            $classifiedIds[$candidate['key']] = true;
        }

        $extra = [];
        foreach ($sourceEvents as $eventKey => $event) {
            if (!isset($classifiedIds[$eventKey])) {
                $extra[] = $event;
            }
        }

        $observedCount = count($sourceEvents);
        if ($journey === null) {
            $status = $observedCount === 0 ? 'no_observed_event' : 'classification_pending';
        } elseif (count($duplicates) > 0 || $pairingStatus === 'AMBIGUOUS_PAIRING') {
            $status = 'ambiguous';
        } elseif (!$globalStatusTrusted && $journeyEvaluated) {
            $status = 'incomplete';
        } elseif (!$journeyEvaluated && $scheduleStatus === 'UNSCHEDULED') {
            $status = 'incomplete';
        } elseif ($journeyEvaluated && $globalStatusTrusted && $observedCount > 0
            && in_array($pairingStatus, ['PARTIAL_MISSING_IN', 'PARTIAL_MISSING_OUT'], true)) {
            $status = 'incomplete';
        } elseif ($journeyEvaluated && $globalStatusTrusted && $observedCount > 0) {
            $status = 'evaluated';
        } else {
            $status = $observedCount === 0 ? 'no_observed_event' : 'classification_pending';
        }

        $renderedSlots = [];
        foreach ($slots as $key => $event) {
            $renderedSlots[$key] = [
                'event' => $event,
                'ambiguous' => isset($duplicates[$key]),
                'time_local' => $event === null ? null : self::localEventTime($event, $timezone),
                'event_reference' => $event === null ? null : (string) ($event['id_evento'] ?? $event['event_uuid'] ?? $event['id'] ?? ''),
            ];
        }

        return [
            'worker_id' => (int) ($profile['worker_id'] ?? 0),
            'full_name' => (string) ($profile['full_name'] ?? 'Nombre no disponible'),
            'employee_number' => $profile['employee_number'] ?? null,
            'observed_count' => $observedCount,
            'evaluated' => is_array($journey) && is_bool($journey['evaluated'] ?? null) ? $journey['evaluated'] : null,
            'evaluated_raw_event_count' => is_array($journey) && is_int($journey['evaluated_raw_event_count'] ?? null)
                ? $journey['evaluated_raw_event_count'] : null,
            'evaluated_raw_events' => is_array($journey) && is_array($journey['evaluated_raw_events'] ?? null)
                ? $journey['evaluated_raw_events'] : null,
            'additional_segment_count' => $globalStatusTrusted ? count($extraSegmentOrdinals) : 0,
            'slots' => $renderedSlots,
            'additional_events' => $extra,
            'status' => $status,
            'journey_status' => (string) ($journey['journey_status'] ?? 'UNKNOWN'),
            'pairing_status' => $pairingStatus,
            'source_level2_status' => $sourceLevel2Status,
            'classification_available' => $globalStatusTrusted,
            'no_absence_inference' => true,
        ];
    }

    private static function eventKey(array $event, string $fallback): string
    {
        foreach (['event_uuid', 'id_evento', 'id'] as $field) {
            if (is_string($event[$field] ?? null) && trim($event[$field]) !== '') {
                return 'event:' . trim($event[$field]);
            }
        }
        $encoded = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? 'fallback:' . $fallback : 'payload:' . hash('sha256', $encoded);
    }

    /** Convert one source event into an already-resolved workplace timezone for display only. */
    public static function localEventTime(array $event, ?string $timezone): ?array
    {
        $utcValue = $event['ocurrido_at_utc'] ?? $event['captured_at_utc'] ?? null;
        if (!is_string($utcValue)
            || !is_string($timezone)
            || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
            return null;
        }
        try {
            $local = (new DateTimeImmutable($utcValue, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($timezone));
            return ['time' => $local->format('H:i'), 'date' => $local->format('Y-m-d'), 'utc' => $utcValue];
        } catch (Throwable) {
            return null;
        }
    }
}

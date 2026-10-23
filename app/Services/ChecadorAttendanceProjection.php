<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Projects only the API's explicit P3-H role and segment metadata. */
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
        $extra = [];
        $classifiedIds = [];
        $evaluatedEvents = is_array($journey['evaluated_raw_events'] ?? null)
            ? $journey['evaluated_raw_events']
            : [];

        // Preserve the complete stable snapshot even when Journey has not
        // been evaluated for this worker/page. P3-H role metadata is the only
        // source for slots; raw events without it remain visible as pending.
        $sourceEvents = [];
        foreach ($events as $index => $event) {
            if (!is_array($event)) continue;
            $sourceEvents[self::eventKey($event, 'raw-' . $index)] = $event;
        }
        foreach ($evaluatedEvents as $index => $event) {
            if (!is_array($event)) continue;
            $sourceEvents[self::eventKey($event, 'journey-' . $index)] ??= $event;
        }

        foreach ($evaluatedEvents as $event) {
            if (!is_array($event)) {
                continue;
            }
            $role = $event['pairing_role'] ?? null;
            $ordinal = $event['segment_ordinal'] ?? null;
            $key = is_string($role) && is_int($ordinal) && $ordinal > 0 ? $role . ':' . $ordinal : null;
            $eventKey = self::eventKey($event, 'journey-' . count($classifiedIds));
            if ($key !== null && array_key_exists($key, $slots)) {
                if ($slots[$key] === null) {
                    $slots[$key] = $event;
                    $classifiedIds[$eventKey] = true;
                } else {
                    $duplicates[$key] = true;
                }
                continue;
            }
        }

        foreach ($sourceEvents as $eventKey => $event) {
            if (!isset($classifiedIds[$eventKey])) $extra[] = $event;
        }

        $observedCount = count($sourceEvents);
        $journeyStatus = (string) ($journey['journey_status'] ?? 'UNKNOWN');
        if ($journey === null) {
            $status = $observedCount === 0 ? 'no_observed_event' : 'classification_pending';
        } elseif (count($duplicates) > 0 || $journeyStatus === 'AMBIGUOUS_PAIRING') {
            $status = 'ambiguous';
        } elseif ($journeyStatus === 'REVIEW_REQUIRED') {
            $status = 'review';
        } elseif ($journeyStatus === 'EVALUATED' && $observedCount > 0) {
            $status = 'evaluated';
        } elseif ($journeyStatus === 'DATA_INCOMPLETE') {
            $status = 'incomplete';
        } else {
            $status = 'unknown';
        }

        $renderedSlots = [];
        foreach ($slots as $key => $event) {
            $renderedSlots[$key] = [
                'event' => $event,
                'ambiguous' => isset($duplicates[$key]),
                'time_local' => $event === null ? null : self::localTime($event, $timezone),
                'event_reference' => $event === null ? null : (string) ($event['id_evento'] ?? $event['event_uuid'] ?? $event['id'] ?? ''),
            ];
        }

        return [
            'worker_id' => (int) ($profile['worker_id'] ?? 0),
            'full_name' => (string) ($profile['full_name'] ?? 'Nombre no disponible'),
            'employee_number' => $profile['employee_number'] ?? null,
            'observed_count' => $observedCount,
            'slots' => $renderedSlots,
            'additional_events' => $extra,
            'status' => $status,
            'journey_status' => $journeyStatus,
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

    private static function localTime(array $event, ?string $timezone): ?array
    {
        $utcValue = $event['ocurrido_at_utc'] ?? $event['captured_at_utc'] ?? null;
        if (!is_string($utcValue) || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
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

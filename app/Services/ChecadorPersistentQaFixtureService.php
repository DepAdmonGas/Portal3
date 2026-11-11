<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Throwable;

/** Local, read-only presentation fixtures; never emits canonical P1/P2/P3 results. */
final class ChecadorPersistentQaFixtureService
{
    public const WORKPLACE_ID = 1;
    public const WORK_DATE = '2026-10-28';
    public const ADDITIONAL_DAILY_DATE = '2026-10-29';
    public const WORKER_IDS = [1004, 1005, 1006, 1007, 1008];
    public const LOCAL_INCIDENCE_WRITE_FIXTURE_VERSION = 'CHECADOR_M02_QA_20261028_V1';

    public static function enabled(?array $environment = null, ?string $requestHost = null): bool
    {
        $environment ??= self::runtimeEnvironment();
        $requestHost ??= (string) ($_SERVER['HTTP_HOST'] ?? '');
        $host = self::normalizedRequestHost($requestHost);

        return in_array(strtolower((string) ($environment['APP_ENV'] ?? '')), ['dev', 'development', 'local'], true)
            && in_array(strtolower((string) ($environment['DB_CONNECTION'] ?? '')), ['mysql', 'mariadb'], true)
            && in_array(strtolower((string) ($environment['DB_HOST'] ?? '')), ['localhost', '127.0.0.1', '::1'], true)
            && (string) ($environment['DB_DATABASE'] ?? '') === 'bd_admongas'
            && in_array(strtolower((string) ($environment['CHECADOR_M02_PERSISTENT_QA_FIXTURES_ENABLED'] ?? '')), ['1', 'true', 'yes', 'on'], true)
            && in_array($host, ['portal3.test', 'localhost', '127.0.0.1', '::1'], true);
    }

    /** Local QA incidence writes are narrower than the read-only fixture host allowlist. */
    public function localIncidenceWriteEnvironmentEnabled(): bool
    {
        if (!self::enabled() || self::exactRequestHost() !== 'portal3.test') {
            return false;
        }

        $fixture = self::fixtureDefinition();
        return self::isValidFixture($fixture)
            && ($fixture['fixture_version'] ?? null) === self::LOCAL_INCIDENCE_WRITE_FIXTURE_VERSION;
    }

    /** Confirm the fixed worker/date/locality tuple against the versioned QA scenario. */
    public function isEligibleLocalIncidenceWorker(int $workplaceId, string $date, ?int $workerId): bool
    {
        if (!$this->localIncidenceWriteEnvironmentEnabled()
            || !self::isSupportedScope($workplaceId, $date)
            || $workerId === null
            || !in_array($workerId, self::WORKER_IDS, true)
            || !$this->shouldServe('incidences', $workplaceId, $date, [])) {
            return false;
        }

        $fixture = self::fixtureDefinition();
        $worker = $fixture['workers'][$workerId] ?? null;
        return is_array($worker)
            && is_string($worker['display_alias'] ?? null)
            && trim($worker['display_alias']) !== ''
            && is_array($worker['events'] ?? null)
            && is_array($worker['incidents'] ?? null);
    }

    /**
     * Permit only a QA fixture worker already present in the current authorized roster
     * when the roster explicitly reports historical membership as unverified.
     * This does not change or upgrade the roster's coverage status.
     *
     * @param array<string,mixed> $roster
     */
    public function allowsHistoricalQaIncidenceRoster(
        array $roster,
        int $workplaceId,
        string $date,
        ?int $workerId
    ): bool {
        if (($roster['status'] ?? null) !== 'partial'
            || ($roster['coverage'] ?? null) !== 'current_roster_historical_unverified'
            || !$this->isEligibleLocalIncidenceWorker($workplaceId, $date, $workerId)
            || !is_array($roster['workers'] ?? null)) {
            return false;
        }

        foreach ($roster['workers'] as $profile) {
            if (is_array($profile)
                && (int) ($profile['worker_id'] ?? 0) === $workerId
                && trim((string) ($profile['full_name'] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** Serve only the configured local attendance dates; incidence/journey fixtures remain fixed to 28 October. */
    public function shouldServe(?string $workspace, ?int $workplaceId, string $date, array $query): bool
    {
        if (!self::enabled() || $workplaceId !== self::WORKPLACE_ID) {
            return false;
        }

        if ($workspace === 'daily-attendance') {
            return in_array($date, [self::WORK_DATE, self::ADDITIONAL_DAILY_DATE], true);
        }

        if ($workspace === 'incidences') {
            return $date === self::WORK_DATE;
        }

        if ($workspace !== 'journeys' || $date !== self::WORK_DATE) {
            return false;
        }

        $workerId = filter_var($query['worker_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $workerId !== false && in_array($workerId, self::WORKER_IDS, true);
    }

    public static function isSupportedScope(?int $workplaceId, string $date): bool
    {
        return $workplaceId === self::WORKPLACE_ID && $date === self::WORK_DATE;
    }

    /** @return array<string,mixed> */
    public static function fixtureDefinition(): array
    {
        $path = dirname(__DIR__, 2) . '/config/checador_m02_persistent_qa_fixtures.php';
        $fixture = is_file($path) ? require $path : null;
        return is_array($fixture) ? $fixture : [];
    }

    /**
     * Read incidence fixtures and development-only persisted QA records without
     * consulting raw punch events or creating a punch snapshot.
     *
     * @return array<string,mixed>
     */
    public function readIncidences(int $workplaceId, string $date, ?int $selectedWorkerId = null): array
    {
        if (!self::isSupportedScope($workplaceId, $date)) {
            return self::failure('No hay información para la localidad o fecha seleccionada.');
        }
        try {
            $fixture = self::fixtureDefinition();
            if (!self::isValidFixture($fixture)) {
                return self::failure('No fue posible preparar la consulta seleccionada.');
            }
            $timezoneRows = Capsule::table('op_rh_v2_localidades_zonas_horarias')
                ->where('id_localidad', $workplaceId)
                ->where('valido_desde', '<=', $date)
                ->where(static function ($query) use ($date): void {
                    $query->whereNull('valido_hasta')->orWhere('valido_hasta', '>', $date);
                })
                ->get(['timezone_iana']);
            $timezone = count($timezoneRows) === 1 ? (string) $timezoneRows[0]->timezone_iana : '';
            if ($timezone !== (string) ($fixture['timezone'] ?? '')
                || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                return self::failure('No se pudo confirmar la zona horaria para la fecha seleccionada.');
            }

            $roster = (new ChecadorAttendanceRosterService())->workersForAttendanceFilter(
                $workplaceId,
                $date,
                $timezone,
                false
            );
            if (!self::usableQaRoster($roster)) {
                return self::failure('No se pudo validar el padrón de la localidad seleccionada.');
            }
            $profilesById = [];
            foreach ($roster['workers'] ?? [] as $profile) {
                if (!is_array($profile)) continue;
                $workerId = (int) ($profile['worker_id'] ?? 0);
                if ($workerId > 0) $profilesById[$workerId] = $profile;
            }
            if ($selectedWorkerId !== null && !isset($profilesById[$selectedWorkerId])) {
                return self::failure('El colaborador seleccionado no pertenece a la localidad autorizada.');
            }

            $workerOptions = [];
            $workerDisplayNames = [];
            foreach ($profilesById as $workerId => $profile) {
                $name = (string) ($profile['full_name'] ?? '');
                if (in_array((int) $workerId, self::WORKER_IDS, true)) {
                    $name = trim((string) ($fixture['workers'][$workerId]['display_alias'] ?? $name));
                }
                $workerDisplayNames[(int) $workerId] = $name;
                $workerOptions[] = [
                    'worker_id' => (int) $workerId,
                    'full_name' => $name,
                    'employee_number' => $profile['employee_number'] ?? null,
                ];
            }
            $collator = class_exists(\Collator::class) ? new \Collator('es_MX') : null;
            usort($workerOptions, static function (array $left, array $right) use ($collator): int {
                $leftName = (string) ($left['full_name'] ?? '');
                $rightName = (string) ($right['full_name'] ?? '');
                $order = $collator instanceof \Collator
                    ? $collator->compare($leftName, $rightName)
                    : strcmp(mb_strtolower($leftName), mb_strtolower($rightName));
                return !is_int($order) || $order === 0
                    ? (int) ($left['worker_id'] ?? 0) <=> (int) ($right['worker_id'] ?? 0)
                    : $order;
            });

            $incidences = [];
            foreach (self::WORKER_IDS as $workerId) {
                if (!isset($profilesById[$workerId]) || ($selectedWorkerId !== null && $selectedWorkerId !== $workerId)) {
                    continue;
                }
                foreach ($fixture['workers'][$workerId]['incidents'] as $incident) {
                    $incidences[$workerId][] = [
                        'source_classification' => 'SYNTHETIC_QA_FIXTURE',
                        'qa_title' => (string) ($incident['qa_title'] ?? 'Incidencia'),
                        'qa_detail' => (string) ($incident['qa_detail'] ?? ''),
                        'qa_status_label' => (string) ($incident['qa_status_label'] ?? 'Requiere revisión'),
                        'date_range' => ['start_date' => $date, 'end_date' => $date],
                    ];
                }
            }
            $authorizedWorkerIds = array_map('intval', array_keys($profilesById));
            $store = new ChecadorPersistentQaIncidenceStore();
            $storedRecords = [];
            if ($store->enabled()) {
                $storedRead = $store->readForScope(
                    $workplaceId,
                    $date,
                    $authorizedWorkerIds,
                    $selectedWorkerId
                );
                if (($storedRead['ok'] ?? false) !== true) {
                    return self::failure('No se pudo leer el registro de incidencias.');
                }
                $storedRecords = $storedRead['records'] ?? [];
            }
            foreach ($storedRecords as $record) {
                $workerId = (int) ($record['worker_id'] ?? 0);
                if ($workerId > 0) $incidences[$workerId][] = $record;
            }
            if ($selectedWorkerId !== null && !array_key_exists($selectedWorkerId, $incidences)) {
                $incidences[$selectedWorkerId] = [];
            }

            return [
                'ok' => true,
                'state' => 'success',
                'state_message' => '',
                'timezone' => [
                    'workplace_id' => $workplaceId,
                    'local_date' => $date,
                    'timezone' => $timezone,
                    'source_classification' => 'SYNTHETIC_QA_FIXTURE',
                ],
                'worker_options' => $workerOptions,
                'worker_display_names' => $workerDisplayNames,
                'worker_options_status' => (string) ($roster['status'] ?? 'unavailable'),
                'worker_options_message' => '',
                'attendance_roster' => $roster,
                'administrative_incidences' => $incidences,
                'administrative_errors' => [],
                'selected_worker_id' => $selectedWorkerId,
                'persistent_qa_fixture_mode' => true,
            ];
        } catch (Throwable) {
            return self::failure('No se pudo preparar la consulta seleccionada.');
        }
    }

    /**
     * Read-only model for the existing attendance, Journey, and Incidence views.
     * Worker names and active membership come from the authorized local roster;
     * scenario schedules, role mappings, states, and incidence descriptions are fixtures.
     *
     * @return array<string,mixed>
     */
    public function read(int $workplaceId, string $date, ?int $selectedWorkerId = null): array
    {
        if ($date === self::ADDITIONAL_DAILY_DATE) {
            return $this->readAdditionalDailyDate($workplaceId, $date, $selectedWorkerId);
        }
        if (!self::isSupportedScope($workplaceId, $date)) {
            return self::failure('No hay información para la localidad o fecha seleccionada.');
        }
        try {
            $fixture = self::fixtureDefinition();
            if (!self::isValidFixture($fixture)) {
                return self::failure('No fue posible preparar la consulta seleccionada.');
            }

            $expectedEvents = [];
            foreach (self::WORKER_IDS as $workerId) {
                foreach ($fixture['workers'][$workerId]['events'] as $eventDefinition) {
                    $expectedEvents[(string) $eventDefinition['event_uuid']] = [
                        'worker_id' => $workerId,
                        'occurred_at_utc' => (string) $eventDefinition['occurred_at_utc'],
                    ];
                }
            }
            $eventUuids = array_keys($expectedEvents);
            $sourceRows = Capsule::table('op_rh_v2_marcaciones_crudas')
                ->whereIn('event_uuid', $eventUuids)
                ->get([
                    'event_uuid',
                    'id_dispositivo',
                    'id_personal_resuelto',
                    'id_estacion_resuelta',
                    'ocurrido_at_utc',
                    'metodo_captura',
                    'processing_status',
                ]);
            if (count($sourceRows) !== count($eventUuids)) {
                return self::failure('No fue posible consultar las marcaciones.');
            }
            $sourceEventsByUuid = [];
            foreach ($sourceRows as $sourceRow) {
                $eventUuid = (string) $sourceRow->event_uuid;
                $expected = $expectedEvents[$eventUuid] ?? null;
                if ($expected === null
                    || isset($sourceEventsByUuid[$eventUuid])
                    || (int) $sourceRow->id_personal_resuelto !== $expected['worker_id']
                    || (int) $sourceRow->id_estacion_resuelta !== self::WORKPLACE_ID
                    || (string) $sourceRow->ocurrido_at_utc !== $expected['occurred_at_utc']) {
                    return self::failure('No fue posible validar las marcaciones de la consulta.');
                }
                $sourceEventsByUuid[$eventUuid] = (array) $sourceRow;
            }

            $timezoneRows = Capsule::table('op_rh_v2_localidades_zonas_horarias')
                ->where('id_localidad', $workplaceId)
                ->where('valido_desde', '<=', $date)
                ->where(static function ($query) use ($date): void {
                    $query->whereNull('valido_hasta')->orWhere('valido_hasta', '>', $date);
                })
                ->get(['timezone_iana']);
            $timezone = count($timezoneRows) === 1 ? (string) $timezoneRows[0]->timezone_iana : '';
            if ($timezone !== (string) $fixture['timezone']
                || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                return self::failure('No se pudo confirmar la zona horaria para la fecha seleccionada.');
            }

            $roster = (new ChecadorAttendanceRosterService())->workersForAttendanceFilter(
                $workplaceId,
                $date,
                $timezone,
                false
            );
            if (!self::usableQaRoster($roster)) {
                return self::failure('No se pudo validar el padrón de la localidad seleccionada.');
            }
            $profilesById = [];
            foreach ($roster['workers'] ?? [] as $profile) {
                if (is_array($profile)) {
                    $workerId = (int) ($profile['worker_id'] ?? 0);
                    if ($workerId > 0) {
                        $profilesById[$workerId] = $profile;
                    }
                }
            }
            foreach (self::WORKER_IDS as $workerId) {
                if (!isset($profilesById[$workerId])) {
                    return self::failure('No se encontraron todos los colaboradores de la consulta.');
                }
            }
            $canonicalProfilesById = $profilesById;
            $displayProfilesById = $profilesById;
            foreach (self::WORKER_IDS as $workerId) {
                $alias = trim((string) ($fixture['workers'][$workerId]['display_alias'] ?? ''));
                if ($alias === '') {
                    return self::failure('No fue posible preparar los nombres de la consulta.');
                }
                $displayProfilesById[$workerId]['full_name'] = $alias;
            }
            if ($selectedWorkerId !== null && !isset($displayProfilesById[$selectedWorkerId])) {
                return self::failure('El colaborador seleccionado no pertenece a la localidad autorizada.');
            }

            $from = (new DateTimeImmutable($date . ' 00:00:00', new DateTimeZone($timezone)))
                ->setTimezone(new DateTimeZone('UTC'));
            $to = $from->modify('+1 day');
            $timezoneContext = [
                'workplace_id' => $workplaceId,
                'local_date' => $date,
                'timezone' => $timezone,
                'from_utc' => $from->format('Y-m-d H:i:s'),
                'to_utc' => $to->format('Y-m-d H:i:s'),
                'source_classification' => 'SYNTHETIC_QA_FIXTURE',
            ];

            $workerOptions = [];
            $workerDisplayNames = [];
            $events = [];
            $attendanceRows = [];
            $effectiveSchedules = [];
            $journeys = [];
            $incidences = [];
            foreach ($displayProfilesById as $workerId => $profile) {
                $workerId = (int) $workerId;
                $workerDisplayNames[$workerId] = (string) $profile['full_name'];
                $workerOptions[] = [
                    'worker_id' => $workerId,
                    'full_name' => (string) $profile['full_name'],
                    'employee_number' => $profile['employee_number'] ?? null,
                ];
                $row = ChecadorAttendanceProjection::workerRow($profile, [], null, $timezone);
                $row['schedule'] = null;
                $row['schedule_error'] = 'schedule_not_loaded';
                $row['attendance_relationship'] = 'habitual';
                $row['incidence_count'] = null;
                $attendanceRows[$workerId] = $row;
            }
            $collator = class_exists(\Collator::class) ? new \Collator('es_MX') : null;
            usort($workerOptions, static function (array $left, array $right) use ($collator): int {
                $leftName = (string) ($left['full_name'] ?? '');
                $rightName = (string) ($right['full_name'] ?? '');
                $order = $collator instanceof \Collator
                    ? $collator->compare($leftName, $rightName)
                    : strcmp(mb_strtolower($leftName), mb_strtolower($rightName));
                return !is_int($order) || $order === 0
                    ? (int) ($left['worker_id'] ?? 0) <=> (int) ($right['worker_id'] ?? 0)
                    : $order;
            });
            $allProfiles = array_values($canonicalProfilesById);
            foreach (self::WORKER_IDS as $workerId) {
                $definition = $fixture['workers'][$workerId];
                $profile = $displayProfilesById[$workerId];
                if ($selectedWorkerId !== null && $selectedWorkerId !== $workerId) {
                    continue;
                }

                $slots = [
                    'ENTRY:1' => self::emptySlot(),
                    'EXIT:1' => self::emptySlot(),
                    'ENTRY:2' => self::emptySlot(),
                    'EXIT:2' => self::emptySlot(),
                ];
                $workerEvents = [];
                foreach ($definition['events'] as $eventDefinition) {
                    $role = (string) $eventDefinition['pairing_role'];
                    $ordinal = (int) $eventDefinition['segment_ordinal'];
                    $slotKey = $role . ':' . $ordinal;
                    if (!array_key_exists($slotKey, $slots) || $slots[$slotKey]['event'] !== null) {
                        return self::failure('No fue posible validar la posición de las marcaciones.');
                    }
                    $eventUuid = (string) $eventDefinition['event_uuid'];
                    $sourceEvent = $sourceEventsByUuid[$eventUuid] ?? null;
                    if (!is_array($sourceEvent)) {
                        return self::failure('No se encontró una marcación relacionada con la consulta.');
                    }
                    $event = [
                        'event_uuid' => (string) $sourceEvent['event_uuid'],
                        'id_dispositivo' => (int) $sourceEvent['id_dispositivo'],
                        'id_estacion_resuelta' => (int) $sourceEvent['id_estacion_resuelta'],
                        'id_personal_resuelto' => (int) $sourceEvent['id_personal_resuelto'],
                        'ocurrido_at_utc' => (string) $sourceEvent['ocurrido_at_utc'],
                        'metodo_captura' => (string) $sourceEvent['metodo_captura'],
                        'processing_status' => (string) $sourceEvent['processing_status'],
                        'pairing_role' => $role,
                        'segment_ordinal' => $ordinal,
                        'source_classification' => 'SYNTHETIC_QA_FIXTURE',
                    ];
                    $localTime = ChecadorAttendanceProjection::localEventTime($event, $timezone);
                    if ($localTime === null || ($localTime['date'] ?? '') !== $date) {
                        return self::failure('Una marcación no coincide con la fecha seleccionada.');
                    }
                    $slots[$slotKey] = [
                        'event' => $event,
                        'ambiguous' => false,
                        'time_local' => $localTime,
                        'event_reference' => $event['event_uuid'],
                    ];
                    $workerEvents[] = $event;
                    $events[] = $event;
                }

                $schedule = self::schedule($date, $timezone, $definition['schedule_segments']);
                $incidenceItems = array_map(static fn(array $item): array => [
                    'source_classification' => 'SYNTHETIC_QA_FIXTURE',
                    'qa_title' => (string) $item['qa_title'],
                    'qa_detail' => (string) $item['qa_detail'],
                    'qa_status_label' => (string) ($item['qa_status_label'] ?? 'Requiere revisión'),
                    'date_range' => ['start_date' => $date, 'end_date' => $date],
                ], $definition['incidents']);
                $row = [
                    'worker_id' => $workerId,
                    'full_name' => (string) $profile['full_name'],
                    'employee_number' => $profile['employee_number'] ?? null,
                    'observed_count' => count($workerEvents),
                    'evaluated_raw_event_count' => null,
                    'additional_segment_count' => 0,
                    'slots' => $slots,
                    'additional_events' => [],
                    'status' => 'qa_fixture',
                    'journey_status' => 'QA_SIMULATED',
                    'pairing_status' => 'QA_SIMULATED',
                    'source_level2_status' => 'NOT_EVALUATED_IN_QA_FIXTURE',
                    'classification_available' => false,
                    'no_absence_inference' => true,
                    'schedule' => $schedule,
                    'schedule_error' => null,
                    'attendance_relationship' => 'habitual',
                    'worked_seconds' => null,
                    'worked_time_source' => null,
                    'incidence_count' => count($incidenceItems),
                    'qa_fixture' => true,
                    'qa_fixture_version' => (string) $fixture['fixture_version'],
                    'qa_scenario_label' => (string) $definition['scenario_label'],
                    'qa_status_label' => (string) $definition['journey_status_label'],
                    'qa_presentation_status' => (string) $definition['presentation_status'],
                    'qa_incidents' => $incidenceItems,
                ];
                $attendanceRows[$workerId] = $row;
                $effectiveSchedules[$workerId] = $schedule;
                $journeys[$workerId] = [
                    'qa_fixture' => true,
                    'qa_fixture_version' => (string) $fixture['fixture_version'],
                    'qa_status_label' => (string) $definition['journey_status_label'],
                    'worker_name' => (string) $profile['full_name'],
                    'evaluated' => false,
                    'journey_status' => 'QA_SIMULATED',
                    'context' => [
                        'id_personal' => $workerId,
                        'id_estacion' => self::WORKPLACE_ID,
                        'fecha_local' => $date,
                        'timezone' => $timezone,
                    ],
                    'schedule' => $schedule,
                    'calculation' => ['qa_state' => 'NOT_CALCULATED'],
                    'source_evidence' => ['source_classification' => 'SYNTHETIC_QA_FIXTURE'],
                    'qa_events' => $workerEvents,
                ];
                $incidences[$workerId] = $incidenceItems;
            }

            if ($selectedWorkerId !== null) {
                $attendanceRows = array_intersect_key($attendanceRows, [$selectedWorkerId => true]);
            }

            return [
                'ok' => true,
                'state' => 'success',
                'state_message' => '',
                'timezone' => $timezoneContext,
                'events' => $events,
                'attendance_events' => $events,
                'attendance_coverage' => [
                    'status' => 'synthetic_qa_fixture',
                    'complete' => false,
                    'message' => 'No se confirmó la cobertura completa de marcaciones.',
                ],
                'attendance_roster' => array_replace($roster, ['workers' => $allProfiles]),
                'worker_options' => $workerOptions,
                'worker_display_names' => $workerDisplayNames,
                'worker_options_status' => (string) ($roster['status'] ?? 'unavailable'),
                'worker_options_message' => '',
                'selected_worker_id' => $selectedWorkerId,
                'scheduled_comodines' => ['status' => 'not_queried', 'workers' => [], 'message' => ''],
                'attendance_rows' => $attendanceRows,
                'effective_schedules' => $effectiveSchedules,
                'effective_schedule_errors' => [],
                'journey_evaluation_state' => 'fixture_only',
                'journeys' => $journeys,
                'journey_errors' => [],
                'administrative_incidences' => $incidences,
                'administrative_errors' => [],
                'persistent_qa_fixture_mode' => true,
            ];
        } catch (Throwable) {
            return self::failure('No se pudo preparar la consulta seleccionada.');
        }
    }

    /**
     * Read the second, event-only QA date from the existing local fixture config.
     * Canonical timezone and the full authorized roster still come from read-only
     * MariaDB reads; synthetic events never query or write the raw event table.
     *
     * @return array<string,mixed>
     */
    private function readAdditionalDailyDate(int $workplaceId, string $date, ?int $selectedWorkerId): array
    {
        if ($workplaceId !== self::WORKPLACE_ID || $date !== self::ADDITIONAL_DAILY_DATE) {
            return self::failure('No hay información para la localidad o fecha seleccionada.');
        }
        try {
            $baseFixture = self::fixtureDefinition();
            $fixture = $baseFixture['additional_daily_dates'][$date] ?? null;
            if (!self::isValidFixture($baseFixture)
                || !is_array($fixture)
                || !self::isValidAdditionalDateFixture($fixture, $baseFixture)) {
                return self::failure('No fue posible preparar la consulta seleccionada.');
            }

            $timezoneRows = Capsule::table('op_rh_v2_localidades_zonas_horarias')
                ->where('id_localidad', $workplaceId)
                ->where('valido_desde', '<=', $date)
                ->where(static function ($query) use ($date): void {
                    $query->whereNull('valido_hasta')->orWhere('valido_hasta', '>', $date);
                })
                ->get(['timezone_iana']);
            $timezone = count($timezoneRows) === 1 ? (string) $timezoneRows[0]->timezone_iana : '';
            if ($timezone !== (string) ($fixture['timezone'] ?? '')
                || !ChecadorOperationalPeriod::isIanaTimezone($timezone)) {
                return self::failure('No se pudo confirmar la zona horaria para la fecha seleccionada.');
            }

            $roster = (new ChecadorAttendanceRosterService())->workersForAttendanceFilter(
                $workplaceId,
                $date,
                $timezone,
                false
            );
            if (!self::usableQaRoster($roster)
                || !is_array($roster['workers'] ?? null)
                || count($roster['workers']) !== 46) {
                return self::failure('No se pudo validar el padrón de la localidad seleccionada.');
            }
            $profilesById = [];
            foreach ($roster['workers'] ?? [] as $profile) {
                if (is_array($profile)) {
                    $workerId = (int) ($profile['worker_id'] ?? 0);
                    if ($workerId > 0) $profilesById[$workerId] = $profile;
                }
            }
            if ($selectedWorkerId !== null && !isset($profilesById[$selectedWorkerId])) {
                return self::failure('El colaborador seleccionado no pertenece a la localidad autorizada.');
            }
            foreach (self::WORKER_IDS as $workerId) {
                if (!isset($profilesById[$workerId])) {
                    return self::failure('No se encontraron todos los colaboradores de la consulta.');
                }
            }

            $workerOptions = [];
            $workerDisplayNames = [];
            $attendanceRows = [];
            $effectiveSchedules = [];
            $events = [];
            foreach ($profilesById as $workerId => $profile) {
                $displayName = (string) ($baseFixture['workers'][$workerId]['display_alias'] ?? $profile['full_name'] ?? '');
                if ($displayName === '') {
                    return self::failure('No fue posible preparar los nombres de la consulta.');
                }
                $displayProfile = $profile;
                if (in_array((int) $workerId, self::WORKER_IDS, true)) {
                    $displayProfile['full_name'] = $displayName;
                }
                $workerDisplayNames[(int) $workerId] = $displayName;
                $workerOptions[] = [
                    'worker_id' => (int) $workerId,
                    'full_name' => $displayName,
                    'employee_number' => $profile['employee_number'] ?? null,
                ];

                // With no authoritative result for the other roster members,
                // keep their state pending instead of implying a confirmed empty day.
                $row = ChecadorAttendanceProjection::workerRow($displayProfile, [], null, $timezone);
                $row['schedule'] = null;
                $row['schedule_error'] = 'schedule_not_confirmed';
                $row['attendance_relationship'] = 'habitual';
                $row['status'] = 'classification_pending';
                $row['no_absence_inference'] = true;
                $row['qa_scope_unconfirmed'] = true;
                $attendanceRows[(int) $workerId] = $row;
            }

            $collator = class_exists(\Collator::class) ? new \Collator('es_MX') : null;
            usort($workerOptions, static function (array $left, array $right) use ($collator): int {
                $leftName = (string) ($left['full_name'] ?? '');
                $rightName = (string) ($right['full_name'] ?? '');
                $order = $collator instanceof \Collator
                    ? $collator->compare($leftName, $rightName)
                    : strcmp(mb_strtolower($leftName), mb_strtolower($rightName));
                return !is_int($order) || $order === 0
                    ? (int) ($left['worker_id'] ?? 0) <=> (int) ($right['worker_id'] ?? 0)
                    : $order;
            });

            foreach (self::WORKER_IDS as $workerId) {
                if ($selectedWorkerId !== null && $selectedWorkerId !== $workerId) continue;
                $profile = $profilesById[$workerId];
                $profile['full_name'] = $workerDisplayNames[$workerId];
                $workerEvents = [];
                $scheduleSegments = $fixture['workers'][$workerId]['schedule_segments'];
                $schedule = self::schedule($date, $timezone, $scheduleSegments);
                $slots = [
                    'ENTRY:1' => self::emptySlot(),
                    'EXIT:1' => self::emptySlot(),
                    'ENTRY:2' => self::emptySlot(),
                    'EXIT:2' => self::emptySlot(),
                ];
                foreach ($fixture['workers'][$workerId]['events'] as $eventDefinition) {
                    $role = (string) $eventDefinition['pairing_role'];
                    $ordinal = (int) $eventDefinition['segment_ordinal'];
                    $slotKey = $role . ':' . $ordinal;
                    if (!array_key_exists($slotKey, $slots) || $slots[$slotKey]['event'] !== null) {
                        return self::failure('No fue posible validar la posición de las marcaciones.');
                    }
                    $occurredAtUtc = (new DateTimeImmutable(
                        $date . ' ' . $eventDefinition['local_time'] . ':00',
                        new DateTimeZone($timezone)
                    ))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                    $event = [
                        'event_uuid' => (string) $eventDefinition['event_uuid'],
                        'id_dispositivo' => null,
                        'id_estacion_resuelta' => self::WORKPLACE_ID,
                        'id_personal_resuelto' => $workerId,
                        'ocurrido_at_utc' => $occurredAtUtc,
                        'metodo_captura' => null,
                        'processing_status' => null,
                        'source_classification' => 'SYNTHETIC_QA_FIXTURE',
                        'qa_fixture_version' => (string) $fixture['fixture_version'],
                    ];
                    $local = ChecadorAttendanceProjection::localEventTime($event, $timezone);
                    if ($local === null || ($local['date'] ?? '') !== $date) {
                        return self::failure('Una marcación no coincide con la fecha seleccionada.');
                    }
                    // The slot is explicit scenario presentation data. Keep
                    // it out of canonical pairing fields and journey evaluation.
                    $slots[$slotKey] = [
                        'event' => $event,
                        'ambiguous' => false,
                        'time_local' => $local,
                        'event_reference' => $event['event_uuid'],
                    ];
                    $workerEvents[] = $event;
                    $events[] = $event;
                }
                $qaInformationalState = (string) ($fixture['workers'][$workerId]['qa_informational_state'] ?? '');
                $qaScenarioEventUuids = array_values(array_map(
                    static fn(array $event): string => (string) ($event['event_uuid'] ?? ''),
                    $workerEvents
                ));
                $qaScenarioMetadata = [
                    'qa_workplace_id' => self::WORKPLACE_ID,
                    'qa_local_date' => $date,
                    'qa_worker_id' => (int) $workerId,
                    'qa_fixture_version' => (string) $fixture['fixture_version'],
                    'qa_event_uuids' => $qaScenarioEventUuids,
                    'qa_schedule_source_classification' => 'SYNTHETIC_QA_FIXTURE',
                    'qa_schedule_source_date' => $date,
                    'qa_schedule_segments' => $scheduleSegments,
                    'qa_event_set_closed' => ($fixture['workers'][$workerId]['qa_scenario_event_set_closed'] ?? false) === true,
                    'qa_informational_state' => $qaInformationalState,
                ];
                $row = ChecadorAttendanceProjection::workerRow($profile, [], null, $timezone);
                $row['observed_count'] = count($workerEvents);
                $row['slots'] = $slots;
                $row['additional_events'] = [];
                $row['schedule'] = $schedule;
                $row['schedule_error'] = null;
                $row['schedule_source_classification'] = 'SYNTHETIC_QA_FIXTURE';
                $row['schedule_source_date'] = $date;
                $row['attendance_relationship'] = 'habitual';
                $row['status'] = 'classification_pending';
                $row['journey_status'] = 'NOT_EVALUATED_IN_QA_FIXTURE';
                $row['pairing_status'] = 'NOT_EVALUATED_IN_QA_FIXTURE';
                $row['source_level2_status'] = 'NOT_EVALUATED_IN_QA_FIXTURE';
                $row['classification_available'] = false;
                $row['qa_fixture_version'] = (string) $fixture['fixture_version'];
                $row['qa_source_classification'] = 'SYNTHETIC_QA_FIXTURE';
                $row['qa_informational_state'] = $qaInformationalState;
                $row['qa_scenario_event_set_closed'] = $qaScenarioMetadata['qa_event_set_closed'];
                $row['qa_scenario_metadata'] = $qaScenarioMetadata;
                $row['no_absence_inference'] = true;
                $attendanceRows[$workerId] = $row;
                $effectiveSchedules[$workerId] = $schedule;
            }

            if ($selectedWorkerId !== null) {
                $attendanceRows = array_intersect_key($attendanceRows, [$selectedWorkerId => true]);
            }
            return [
                'ok' => true,
                'state' => 'success',
                'state_message' => '',
                'timezone' => [
                    'workplace_id' => $workplaceId,
                    'local_date' => $date,
                    'timezone' => $timezone,
                    'source_classification' => 'SYNTHETIC_QA_FIXTURE',
                ],
                'events' => $events,
                'attendance_events' => $events,
                'attendance_coverage' => [
                    'status' => 'synthetic_qa_fixture_partial',
                    'complete' => false,
                    'message' => 'El fixture local sólo confirma los eventos incluidos para cinco colaboradores.',
                ],
                'attendance_roster' => $roster,
                'worker_options' => $workerOptions,
                'worker_display_names' => $workerDisplayNames,
                'worker_options_status' => (string) ($roster['status'] ?? 'unavailable'),
                'worker_options_message' => '',
                'selected_worker_id' => $selectedWorkerId,
                'scheduled_comodines' => ['status' => 'not_queried', 'workers' => [], 'message' => ''],
                'attendance_rows' => $attendanceRows,
                'effective_schedules' => $effectiveSchedules,
                'effective_schedule_errors' => [],
                'journey_evaluation_state' => 'fixture_only',
                'journeys' => [],
                'journey_errors' => [],
                'administrative_incidences' => [],
                'administrative_errors' => [],
                'persistent_qa_fixture_mode' => true,
            ];
        } catch (Throwable) {
            return self::failure('No se pudo preparar la consulta seleccionada.');
        }
    }

    /** @return array{event:null,ambiguous:bool,time_local:null,event_reference:null} */
    private static function emptySlot(): array
    {
        return ['event' => null, 'ambiguous' => false, 'time_local' => null, 'event_reference' => null];
    }

    /** @param list<array<string,mixed>> $segments @return array<string,mixed> */
    private static function schedule(string $date, string $timezone, array $segments): array
    {
        return [
            'estado_jornada' => 'SCHEDULED',
            'horario_local' => [
                'fecha_local' => $date,
                'timezone' => $timezone,
                'segmentos' => array_values(array_map(static fn(array $segment): array => [
                    'ordinal' => (int) $segment['ordinal'],
                    'tipo_segmento' => (string) $segment['tipo_segmento'],
                    'hora_inicio_local' => (string) $segment['hora_inicio_local'],
                    'hora_fin_local' => (string) $segment['hora_fin_local'],
                    'dia_offset_inicio' => 0,
                    'dia_offset_fin' => (int) $segment['dia_offset_fin'],
                ], $segments)),
            ],
            'source_classification' => 'SYNTHETIC_QA_FIXTURE',
        ];
    }

    /** @param array<string,mixed> $fixture */
    private static function isValidFixture(array $fixture): bool
    {
        if (($fixture['source_classification'] ?? null) !== 'SYNTHETIC_QA_FIXTURE'
            || (int) ($fixture['workplace_id'] ?? 0) !== self::WORKPLACE_ID
            || ($fixture['local_date'] ?? null) !== self::WORK_DATE
            || !is_string($fixture['fixture_version'] ?? null)
            || !is_array($fixture['workers'] ?? null)
            || array_map('intval', array_keys($fixture['workers'])) !== self::WORKER_IDS) {
            return false;
        }

        $uuids = [];
        $expectedCounts = [1004 => 2, 1005 => 4, 1006 => 1, 1007 => 2, 1008 => 3];
        foreach (self::WORKER_IDS as $workerId) {
            $definition = $fixture['workers'][$workerId] ?? null;
            if (!is_array($definition)
                || !is_string($definition['display_alias'] ?? null)
                || trim($definition['display_alias']) === ''
                || !in_array($definition['presentation_status'] ?? null, ['complete', 'incomplete', 'review'], true)
                || !is_array($definition['schedule_segments'] ?? null)
                || !is_array($definition['events'] ?? null)
                || !is_array($definition['incidents'] ?? null)
                || count($definition['events']) !== $expectedCounts[$workerId]) {
                return false;
            }
            foreach ($definition['events'] as $event) {
                if (!is_array($event)
                    || !is_string($event['event_uuid'] ?? null)
                    || preg_match('/\\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\\z/i', $event['event_uuid']) !== 1
                    || isset($uuids[$event['event_uuid']])
                    || !in_array($event['pairing_role'] ?? null, ['ENTRY', 'EXIT'], true)
                    || !in_array($event['segment_ordinal'] ?? null, [1, 2], true)
                    || !is_string($event['occurred_at_utc'] ?? null)) {
                    return false;
                }
                $uuids[$event['event_uuid']] = true;
            }
        }

        return count($uuids) === 12;
    }

    /** @param array<string,mixed> $fixture @param array<string,mixed> $baseFixture */
    private static function isValidAdditionalDateFixture(array $fixture, array $baseFixture): bool
    {
        if (($fixture['source_classification'] ?? null) !== 'SYNTHETIC_QA_FIXTURE'
            || (int) ($fixture['workplace_id'] ?? 0) !== self::WORKPLACE_ID
            || ($fixture['local_date'] ?? null) !== self::ADDITIONAL_DAILY_DATE
            || ($fixture['timezone'] ?? null) !== 'America/Mexico_City'
            || !is_string($fixture['fixture_version'] ?? null)
            || !is_array($fixture['workers'] ?? null)
            || array_map('intval', array_keys($fixture['workers'])) !== self::WORKER_IDS) {
            return false;
        }

        $expectedCounts = [1004 => 2, 1005 => 4, 1006 => 1, 1007 => 2, 1008 => 3];
        $expectedScheduleCounts = [1004 => 1, 1005 => 2, 1006 => 1, 1007 => 1, 1008 => 2];
        $expectedInformationalStates = [
            1004 => 'positions_complete',
            1005 => 'positions_complete',
            1006 => 'partial_record',
            1007 => 'schedule_review',
            1008 => 'partial_record',
        ];
        $knownUuids = [];
        foreach ($baseFixture['workers'] as $definition) {
            foreach ($definition['events'] as $event) {
                $knownUuids[strtolower((string) $event['event_uuid'])] = true;
            }
        }
        $newUuids = [];
        foreach (self::WORKER_IDS as $workerId) {
            $definition = $fixture['workers'][$workerId] ?? null;
            if (!is_array($definition)
                || !is_array($definition['events'] ?? null)
                || count($definition['events']) !== $expectedCounts[$workerId]
                || !is_array($definition['schedule_segments'] ?? null)
                || count($definition['schedule_segments']) !== $expectedScheduleCounts[$workerId]
                || ($definition['qa_scenario_event_set_closed'] ?? null) !== true
                || ($definition['qa_informational_state'] ?? null) !== $expectedInformationalStates[$workerId]) {
                return false;
            }
            foreach ($definition['schedule_segments'] as $index => $segment) {
                if (!is_array($segment)
                    || (int) ($segment['ordinal'] ?? 0) !== $index + 1
                    || ($segment['tipo_segmento'] ?? null) !== 'WORK'
                    || !is_string($segment['hora_inicio_local'] ?? null)
                    || preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]\\z/', $segment['hora_inicio_local']) !== 1
                    || !is_string($segment['hora_fin_local'] ?? null)
                    || preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]\\z/', $segment['hora_fin_local']) !== 1
                    || (int) ($segment['dia_offset_fin'] ?? -1) !== 0) {
                    return false;
                }
            }
            foreach ($definition['events'] as $event) {
                if (!is_array($event)
                    || !is_string($event['event_uuid'] ?? null)
                    || preg_match('/\\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\\z/i', $event['event_uuid']) !== 1
                    || !is_string($event['local_time'] ?? null)
                    || preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\\z/', $event['local_time']) !== 1
                    || !in_array($event['pairing_role'] ?? null, ['ENTRY', 'EXIT'], true)
                    || !in_array($event['segment_ordinal'] ?? null, [1, 2], true)) {
                    return false;
                }
                $uuid = strtolower($event['event_uuid']);
                if (isset($knownUuids[$uuid]) || isset($newUuids[$uuid])) {
                    return false;
                }
                $newUuids[$uuid] = true;
            }
        }
        return count($newUuids) === 12;
    }

    /** The fixed historical QA view may reference today's active roster, but must retain its unverified status. */
    private static function usableQaRoster(array $roster): bool
    {
        return ($roster['status'] ?? '') === 'ready'
            || (($roster['status'] ?? '') === 'partial'
                && ($roster['coverage'] ?? '') === 'current_roster_historical_unverified');
    }

    /** @param array<string,mixed>|null $environment @return array<string,string> */
    private static function runtimeEnvironment(?array $environment = null): array
    {
        if ($environment !== null) {
            return array_map(static fn(mixed $value): string => trim((string) $value), $environment);
        }

        $keys = [
            'APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE',
            'CHECADOR_M02_PERSISTENT_QA_FIXTURES_ENABLED',
        ];
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = trim((string) ($_ENV[$key] ?? getenv($key) ?: ''));
        }
        return $values;
    }

    private static function normalizedRequestHost(?string $requestHost = null): string
    {
        $requestHost ??= (string) ($_SERVER['HTTP_HOST'] ?? '');
        return strtolower(trim(preg_replace('/:\\d+$/', '', $requestHost) ?? ''));
    }

    private static function exactRequestHost(?string $requestHost = null): string
    {
        $requestHost ??= (string) ($_SERVER['HTTP_HOST'] ?? '');
        return strtolower(trim($requestHost));
    }

    /** @return array<string,mixed> */
    private static function failure(string $message): array
    {
        return [
            'ok' => false,
            'state' => 'error',
            'state_message' => $message,
            'events' => [],
            'attendance_events' => [],
            'attendance_rows' => [],
            'worker_options' => [],
            'worker_display_names' => [],
            'worker_options_status' => 'unavailable',
            'worker_options_message' => $message,
            'persistent_qa_fixture_mode' => true,
        ];
    }
}

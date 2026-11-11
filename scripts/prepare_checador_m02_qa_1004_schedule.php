<?php

declare(strict_types=1);

use App\Core\Database;
use Illuminate\Database\Capsule\Manager as Capsule;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Read-only preparation for the single approved QA scenario. This class only
 * builds checks and future-operation descriptions; it has no HTTP client or
 * database write path.
 */
final class ChecadorM02Qa1004Preparation
{
    public const WORKER_ID = 1004;
    public const WORKPLACE_ID = 1;
    public const WORK_DATE = '2026-10-28';
    public const TEMPLATE_KEY = 'QA1004_20261028_0800_1700';
    public const API_BASE_URL = 'http://127.0.0.1:8081/';
    public const API_REPOSITORY = '/home/silvinol/Proyectos/portal-api-v.1.0.1';

    public static function acceptsScope(int $workerId, int $workplaceId, string $date): bool
    {
        return $workerId === self::WORKER_ID
            && $workplaceId === self::WORKPLACE_ID
            && $date === self::WORK_DATE;
    }

    /** @return array<string,mixed> */
    public static function templatePayload(): array
    {
        return [
            'clave' => self::TEMPLATE_KEY,
            'nombre' => 'QA 1004 2026-10-28 08:00-17:00',
            'tipo_turno' => 'REGULAR',
            'segmentos' => [[
                'ordinal' => 1,
                'tipo_segmento' => 'WORK',
                'hora_inicio_local' => '08:00:00',
                'hora_fin_local' => '17:00:00',
                'dia_offset_inicio' => 0,
                'dia_offset_fin' => 0,
            ]],
        ];
    }

    /**
     * @return array<string,mixed>
     * The positive template id must come from an authenticated canonical
     * response. The manifest never substitutes null or a guessed id.
     */
    public static function assignmentPayload(int $templateId): array
    {
        if ($templateId < 1) {
            throw new InvalidArgumentException('A canonical positive template id is required.');
        }

        return [
            'id_personal' => self::WORKER_ID,
            'id_estacion' => self::WORKPLACE_ID,
            'id_plantilla' => $templateId,
            'dia_semana' => 'MIERCOLES',
            'valido_desde' => self::WORK_DATE,
            'valido_hasta' => '2026-10-29',
            'es_activo' => 1,
        ];
    }

    /** @return array<string,mixed> */
    public static function run(): array
    {
        $environment = self::environmentValues();
        $environmentChecks = self::checkEnvironment($environment);
        $apiProcess = self::inspectApiProcess();
        $checks = array_merge($environmentChecks, [
            'api_loopback_listener' => ($apiProcess['listener_verified'] ?? false) === true,
            'api_process_identity' => ($apiProcess['process_identity_verified'] ?? false) === true,
            'global_post_gate_remains_disabled' => ($environmentChecks['global_post_gate_disabled'] ?? false) === true,
        ]);

        if (in_array(false, $checks, true)) {
            return self::blockedManifest($checks, $apiProcess, 'LOCAL_TARGET_CHECK_FAILED');
        }

        try {
            Database::initialize();
            $db = Capsule::connection();
            $identityRow = $db->selectOne('SELECT DATABASE() AS database_name, VERSION() AS server_version, @@port AS server_port');
            $identity = [
                'database' => (string) ($identityRow->database_name ?? ''),
                'server_version' => (string) ($identityRow->server_version ?? ''),
                'server_port' => (int) ($identityRow->server_port ?? 0),
                'host_is_loopback_by_configuration' => true,
            ];
            $identityVerified = $identity['database'] === 'bd_admongas'
                && $identity['server_port'] === 3306
                && str_contains(strtolower($identity['server_version']), 'mariadb');
            $checks['live_database_identity'] = $identityVerified;
            if (!$identityVerified) {
                return self::blockedManifest($checks, $apiProcess, 'LIVE_DATABASE_IDENTITY_MISMATCH', $identity);
            }

            $evidence = self::collectReadOnlyEvidence($db);
            return self::buildManifest($checks, $apiProcess, $identity, $evidence);
        } catch (Throwable $exception) {
            return self::blockedManifest(
                $checks,
                $apiProcess,
                'READ_ONLY_PREFLIGHT_FAILED:' . get_class($exception)
            );
        }
    }

    /** @return array<string,mixed> */
    private static function environmentValues(): array
    {
        Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
        $value = static fn(string $key): string => trim((string) ($_ENV[$key] ?? getenv($key) ?: ''));
        return [
            'app_env' => strtolower($value('APP_ENV')),
            'db_connection' => strtolower($value('DB_CONNECTION')),
            'db_host' => strtolower($value('DB_HOST')),
            'db_name' => $value('DB_DATABASE'),
            'db_port' => $value('DB_PORT') !== '' ? (int) $value('DB_PORT') : 3306,
            'api_base_url' => $value('CHECADOR_API_BASE_URL'),
            'real_posts_flag' => strtolower($value('CHECADOR_API_REAL_POSTS_ENABLED')),
            'demo_flag' => strtolower($value('CHECADOR_ADVANCED_DEMO_ENABLED')),
        ];
    }

    /** @param array<string,mixed> $env
     *  @return array<string,bool>
     */
    private static function checkEnvironment(array $env): array
    {
        $apiUrl = rtrim((string) ($env['api_base_url'] ?? ''), '/') . '/';
        return [
            'development_environment' => in_array($env['app_env'] ?? '', ['dev', 'development', 'local'], true),
            'portal_database_is_local_mysql' => ($env['db_connection'] ?? '') === 'mysql'
                && in_array($env['db_host'] ?? '', ['localhost', '127.0.0.1', '::1'], true)
                && ($env['db_name'] ?? '') === 'bd_admongas'
                && (int) ($env['db_port'] ?? 0) === 3306,
            'api_target_is_exact_loopback' => $apiUrl === self::API_BASE_URL,
            'global_post_gate_disabled' => !in_array($env['real_posts_flag'] ?? '', ['1', 'true', 'yes', 'on'], true),
            'demo_transport_disabled' => !in_array($env['demo_flag'] ?? '', ['1', 'true', 'yes', 'on'], true),
        ];
    }

    /** @return array<string,mixed> */
    private static function inspectApiProcess(): array
    {
        if (!function_exists('proc_open') || !is_dir('/proc')) {
            return ['listener_verified' => false, 'process_identity_verified' => false, 'reason' => 'LOCAL_PROCESS_INSPECTION_UNAVAILABLE'];
        }

        $pipes = [];
        $process = @proc_open(['ss', '-ltnp', 'sport = :8081'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return ['listener_verified' => false, 'process_identity_verified' => false, 'reason' => 'LOCAL_LISTENER_INSPECTION_FAILED'];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $pids = [];
        foreach (preg_split('/\R/', $stdout) ?: [] as $line) {
            if (!str_contains($line, '127.0.0.1:8081')) {
                continue;
            }
            if (preg_match('/pid=(\d+)/', $line, $matches) === 1) {
                $pids[] = (int) $matches[1];
            }
        }
        $pids = array_values(array_unique($pids));
        if ($exitCode !== 0 || count($pids) !== 1) {
            return ['listener_verified' => false, 'process_identity_verified' => false, 'reason' => 'EXPECTED_LOOPBACK_API_PROCESS_NOT_UNIQUE'];
        }

        $pid = $pids[0];
        $cwd = @readlink('/proc/' . $pid . '/cwd') ?: '';
        $rawEnvironment = @file_get_contents('/proc/' . $pid . '/environ');
        if (!is_string($rawEnvironment)) {
            return ['listener_verified' => true, 'process_identity_verified' => false, 'reason' => 'API_PROCESS_ENVIRONMENT_UNREADABLE'];
        }
        $processEnvironment = [];
        foreach (explode("\0", $rawEnvironment) as $entry) {
            $separator = strpos($entry, '=');
            if ($separator === false) {
                continue;
            }
            $key = substr($entry, 0, $separator);
            if (in_array($key, ['APP_ENV', 'DB_HOST', 'DB_NAME'], true)) {
                $processEnvironment[$key] = substr($entry, $separator + 1);
            }
        }
        $apiAppEnv = strtolower(trim((string) ($processEnvironment['APP_ENV'] ?? '')));
        $apiDbHost = strtolower(trim((string) ($processEnvironment['DB_HOST'] ?? '')));
        $apiDbName = trim((string) ($processEnvironment['DB_NAME'] ?? ''));
        $expectedCwd = realpath(self::API_REPOSITORY) ?: self::API_REPOSITORY;
        $actualCwd = realpath($cwd) ?: $cwd;
        $processVerified = $actualCwd === $expectedCwd
            && in_array($apiAppEnv, ['dev', 'development', 'local'], true)
            && in_array($apiDbHost, ['localhost', '127.0.0.1', '::1'], true)
            && $apiDbName === 'bd_admongas';

        return [
            'listener_verified' => true,
            'process_identity_verified' => $processVerified,
            'api_app_env' => $apiAppEnv,
            'api_db_name' => $apiDbName,
            'api_db_host_is_loopback' => in_array($apiDbHost, ['localhost', '127.0.0.1', '::1'], true),
            'api_repository_matches' => $actualCwd === $expectedCwd,
            'reason' => $processVerified ? null : 'API_PROCESS_ENVIRONMENT_OR_REPOSITORY_MISMATCH',
        ];
    }

    /** @return array<string,mixed> */
    private static function collectReadOnlyEvidence($db): array
    {
        $worker = $db->selectOne('SELECT id, no_colaborador, estado, id_estacion FROM op_rh_personal WHERE id = ?', [self::WORKER_ID]);
        $mapping = $db->selectOne(
            'SELECT l.id AS locality_id, l.numlista,
                    (SELECT COUNT(*) FROM op_rh_localidades l2 WHERE l2.numlista = l.numlista) AS locality_count,
                    (SELECT COUNT(*) FROM tb_estaciones e WHERE e.numlista = l.numlista) AS physical_station_count,
                    (SELECT MIN(e.id) FROM tb_estaciones e WHERE e.numlista = l.numlista AND e.estatus = 0) AS active_physical_station_id
             FROM op_rh_localidades l WHERE l.id = ? LIMIT 1',
            [self::WORKPLACE_ID]
        );
        $timezoneRows = $db->select(
            'SELECT timezone_iana, valido_desde, valido_hasta
             FROM op_rh_v2_localidades_zonas_horarias
             WHERE id_localidad = ? AND valido_desde <= ? AND (valido_hasta IS NULL OR ? < valido_hasta)',
            [self::WORKPLACE_ID, self::WORK_DATE, self::WORK_DATE]
        );
        if (count($timezoneRows) !== 1) {
            throw new RuntimeException('CANONICAL_TIMEZONE_NOT_UNIQUE');
        }
        $timezoneName = (string) $timezoneRows[0]->timezone_iana;
        $timezone = new DateTimeZone($timezoneName);
        $utcTimezone = new DateTimeZone('UTC');
        $localWindowStart = new DateTimeImmutable(self::WORK_DATE . ' 00:00:00', $timezone);
        $localWindowEnd = $localWindowStart->modify('+1 day');
        $utcFrom = $localWindowStart->setTimezone($utcTimezone)->format('Y-m-d H:i:s');
        $utcTo = $localWindowEnd->setTimezone($utcTimezone)->format('Y-m-d H:i:s');
        $historyRows = $db->select(
            'SELECT worker_id, workplace_id, valid_from_utc, valid_to_utc, recorded_at_utc, supersedes_id
             FROM op_rh_personal_localidad_version
             WHERE worker_id = ? AND workplace_id = ? AND valid_from_utc < ? AND (valid_to_utc IS NULL OR valid_to_utc > ?)
             ORDER BY recorded_at_utc DESC',
            [self::WORKER_ID, self::WORKPLACE_ID, $utcTo, $utcFrom]
        );
        $events = $db->select(
            'SELECT id, event_uuid, id_dispositivo, id_estacion_resuelta, id_personal_resuelto,
                    ocurrido_at_utc, recibido_at_utc, processing_status, validation_flags
             FROM op_rh_v2_marcaciones_crudas
             WHERE id_personal_resuelto = ? AND id_estacion_resuelta = ? AND ocurrido_at_utc >= ? AND ocurrido_at_utc < ?
             ORDER BY ocurrido_at_utc ASC, event_uuid ASC',
            [self::WORKER_ID, self::WORKPLACE_ID, $utcFrom, $utcTo]
        );
        $templateRows = $db->select(
            'SELECT t.id, t.clave, t.version, t.nombre, t.tipo_turno, t.es_activo,
                    s.ordinal, s.tipo_segmento, s.hora_inicio_local, s.hora_fin_local,
                    s.dia_offset_inicio, s.dia_offset_fin
             FROM op_rh_v2_turnos_plantillas t
             LEFT JOIN op_rh_v2_turnos_segmentos s ON s.id_plantilla = t.id
             ORDER BY t.clave, t.version, s.ordinal'
        );
        $assignmentConflicts = $db->select(
            'SELECT id, id_personal, id_estacion, id_plantilla, dia_semana, valido_desde, valido_hasta, es_activo
             FROM op_rh_v2_turnos_asignaciones
             WHERE id_personal = ? AND id_estacion = ? AND dia_semana = \'MIERCOLES\'
               AND valido_desde < ? AND (valido_hasta IS NULL OR valido_hasta > ?)
             ORDER BY id',
            [self::WORKER_ID, self::WORKPLACE_ID, '2026-10-29', self::WORK_DATE]
        );
        $materialized = $db->selectOne(
            'SELECT COUNT(*) AS row_count FROM op_rh_v2_jornadas_programadas WHERE id_personal = ? AND id_estacion = ? AND fecha_jornada_local = ?',
            [self::WORKER_ID, self::WORKPLACE_ID, self::WORK_DATE]
        );
        $p1 = $db->selectOne(
            'SELECT COUNT(*) AS row_count FROM op_rh_v2_pareos_jornada WHERE id_personal = ? AND id_estacion = ? AND fecha_jornada_local = ?',
            [self::WORKER_ID, self::WORKPLACE_ID, self::WORK_DATE]
        );
        $p2 = $db->selectOne(
            'SELECT COUNT(*) AS row_count FROM op_rh_v2_calculos_jornada WHERE id_personal = ? AND id_estacion = ? AND fecha_jornada_local = ?',
            [self::WORKER_ID, self::WORKPLACE_ID, self::WORK_DATE]
        );
        $templateKeyCount = $db->selectOne(
            'SELECT COUNT(*) AS row_count FROM op_rh_v2_turnos_plantillas WHERE clave = ?',
            [self::TEMPLATE_KEY]
        );
        $level2Window = $db->selectOne(
            'SELECT COUNT(*) AS row_count, MAX(window_level2_status) AS persisted_status, MAX(evaluated_at_utc) AS evaluated_at_utc
             FROM op_rh_v2_level2_window_evaluations WHERE id_estacion = ? AND window_from_utc = ? AND window_to_utc = ?',
            [1, $utcFrom, $utcTo]
        );
        $snapshot = $db->selectOne(
            'SELECT COUNT(*) AS row_count FROM op_rh_v2_read_snapshots
             WHERE JSON_EXTRACT(filtros_json, \'$.station_id\') = ?
               AND JSON_EXTRACT(filtros_json, \'$.from_utc\') = ?
               AND JSON_EXTRACT(filtros_json, \'$.to_utc\') = ?',
            [self::WORKPLACE_ID, $utcFrom, $utcTo]
        );
        $rawMetadata = $db->selectOne(
            'SELECT COUNT(m.id) AS metadata_count,
                    SUM(CASE WHEN m.es_governado_nivel2 = 1 THEN 1 ELSE 0 END) AS governed_count
             FROM op_rh_v2_marcaciones_crudas r
             LEFT JOIN op_rh_v2_marcaciones_nivel2_metadata m ON m.id_raw_punch = r.id
             WHERE r.id_personal_resuelto = ? AND r.id_estacion_resuelta = ? AND r.ocurrido_at_utc >= ? AND r.ocurrido_at_utc < ?',
            [self::WORKER_ID, self::WORKPLACE_ID, $utcFrom, $utcTo]
        );
        $deviceRows = $db->select(
            'SELECT DISTINCT h.id_dispositivo, h.id_estacion, g.device_generation, g.estado AS generation_state
             FROM op_rh_v2_dispositivo_estacion_historial h
             INNER JOIN op_rh_v2_dispositivo_generaciones g ON g.id_dispositivo = h.id_dispositivo
             WHERE h.id_estacion = ? AND h.valido_desde_utc < ? AND (h.valido_hasta_utc IS NULL OR h.valido_hasta_utc > ?)
             ORDER BY h.id_dispositivo, g.device_generation',
            [1, $utcTo, $utcFrom]
        );
        $policyRows = $db->select(
            'SELECT coverage_policy_ref, max_heartbeat_gap_seconds, max_clock_drift_seconds, require_telemetry_continuity
             FROM op_rh_v2_coverage_policies WHERE coverage_policy_ref = ? AND es_activo = 1',
            ['POLICY_V1.0_DEFAULT']
        );
        $telemetry = [];
        $sequenceEvidence = [];
        foreach ($deviceRows as $device) {
            $deviceId = (int) $device->id_dispositivo;
            $generation = (string) $device->device_generation;
            $telemetry[] = [
                'device_id' => $deviceId,
                'generation' => $generation,
                'rows' => $db->selectOne(
                    'SELECT COUNT(*) AS row_count, MIN(observado_at_utc) AS first_observed_utc, MAX(observado_at_utc) AS last_observed_utc
                     FROM op_rh_v2_dispositivo_telemetria
                     WHERE id_dispositivo = ? AND device_generation = ? AND observado_at_utc >= ? AND observado_at_utc <= ?',
                    [$deviceId, $generation, $utcFrom, $utcTo]
                ),
            ];
            $sequenceEvidence[] = [
                'device_id' => $deviceId,
                'generation' => $generation,
                'governed_punch_sequences' => $db->selectOne(
                    'SELECT COUNT(*) AS row_count, MAX(device_sequence) AS max_sequence
                     FROM op_rh_v2_marcaciones_nivel2_metadata
                     WHERE id_dispositivo = ? AND device_generation = ? AND es_governado_nivel2 = 1',
                    [$deviceId, $generation]
                ),
            ];
        }

        $templateGroups = [];
        foreach ($templateRows as $row) {
            $id = (int) $row->id;
            $templateGroups[$id] ??= [
                'id' => $id,
                'clave' => (string) $row->clave,
                'version' => (int) $row->version,
                'nombre' => (string) $row->nombre,
                'tipo_turno' => (string) $row->tipo_turno,
                'es_activo' => (int) $row->es_activo,
                'segmentos' => [],
            ];
            if ($row->ordinal !== null) {
                $templateGroups[$id]['segmentos'][] = [
                    'ordinal' => (int) $row->ordinal,
                    'tipo_segmento' => strtoupper((string) $row->tipo_segmento),
                    'hora_inicio_local' => (string) $row->hora_inicio_local,
                    'hora_fin_local' => (string) $row->hora_fin_local,
                    'dia_offset_inicio' => (int) $row->dia_offset_inicio,
                    'dia_offset_fin' => (int) $row->dia_offset_fin,
                ];
            }
        }
        $expectedSegments = self::templatePayload()['segmentos'];
        $equivalentActiveTemplateIds = [];
        foreach ($templateGroups as $template) {
            if ($template['es_activo'] === 1 && $template['segmentos'] === $expectedSegments) {
                $equivalentActiveTemplateIds[] = $template['id'];
            }
        }

        $eventsOut = [];
        foreach ($events as $event) {
            $localTime = (new DateTimeImmutable((string) $event->ocurrido_at_utc, new DateTimeZone('UTC')))->setTimezone($timezone);
            $eventsOut[] = [
                'event_uuid' => (string) $event->event_uuid,
                'occurred_at_utc' => (string) $event->ocurrido_at_utc,
                'occurred_at_local' => $localTime->format('Y-m-d H:i:s'),
                'received_at_utc' => (string) $event->recibido_at_utc,
                'processing_status' => (string) $event->processing_status,
                'validation_flags' => json_decode((string) $event->validation_flags, true) ?: [],
            ];
        }

        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $windowEnd = new DateTimeImmutable($utcTo, $utcTimezone);
        $level2Count = (int) ($level2Window->row_count ?? 0);
        $telemetryCounts = array_map(static fn(array $item): int => (int) ($item['rows']->row_count ?? 0), $telemetry);

        return [
            'utc_window' => ['from' => $utcFrom, 'to_exclusive' => $utcTo],
            'timezone' => $timezoneName,
            'worker' => $worker ? [
                'id' => (int) $worker->id,
                'employee_number' => (int) $worker->no_colaborador,
                'active' => (int) $worker->estado === 1,
                'home_locality_id' => (int) $worker->id_estacion,
            ] : null,
            'workplace_mapping' => $mapping ? [
                'locality_id' => (int) $mapping->locality_id,
                'unique_locality_numlista' => (int) $mapping->locality_count === 1,
                'unique_physical_station_mapping' => (int) $mapping->physical_station_count === 1,
                'physical_station_id' => $mapping->active_physical_station_id === null ? null : (int) $mapping->active_physical_station_id,
            ] : null,
            'timezone_rows' => array_map(static fn($row): array => [
                'timezone' => (string) $row->timezone_iana,
                'valid_from' => (string) $row->valido_desde,
                'valid_to' => $row->valido_hasta,
            ], $timezoneRows),
            'canonical_locality_history' => array_map(static fn($row): array => [
                'workplace_id' => (int) $row->workplace_id,
                'valid_from_utc' => (string) $row->valid_from_utc,
                'valid_to_utc' => $row->valid_to_utc,
                'recorded_at_utc' => (string) $row->recorded_at_utc,
                'supersedes_id' => $row->supersedes_id === null ? null : (int) $row->supersedes_id,
            ], $historyRows),
            'raw_events' => $eventsOut,
            'template_duplicate_check' => [
                'key' => self::TEMPLATE_KEY,
                'same_key_rows' => (int) ($templateKeyCount->row_count ?? 0),
                'equivalent_active_template_ids' => $equivalentActiveTemplateIds,
                'template_count' => count($templateGroups),
                'create_needed' => (int) ($templateKeyCount->row_count ?? 0) === 0 && $equivalentActiveTemplateIds === [],
                'all_rows_checked_for_equivalent_shape' => true,
            ],
            'assignment_overlap_rows' => array_map(static fn($row): array => [
                'id' => (int) $row->id,
                'template_id' => $row->id_plantilla === null ? null : (int) $row->id_plantilla,
                'effective_from' => (string) $row->valido_desde,
                'effective_to' => $row->valido_hasta,
                'active' => (int) $row->es_activo === 1,
            ], $assignmentConflicts),
            'existing_shift_or_evaluation_rows' => [
                'materialized_shifts' => (int) ($materialized->row_count ?? 0),
                'p1_pairings' => (int) ($p1->row_count ?? 0),
                'p2_calculations' => (int) ($p2->row_count ?? 0),
            ],
            'target_level1_snapshots' => (int) ($snapshot->row_count ?? 0),
            'raw_event_level2_metadata' => [
                'rows' => (int) ($rawMetadata->metadata_count ?? 0),
                'governed_rows' => (int) ($rawMetadata->governed_count ?? 0),
            ],
            'level2' => [
                'persisted_window_evaluations' => $level2Count,
                'persisted_status' => $level2Window->persisted_status,
                'window_is_still_open' => $nowUtc < $windowEnd,
                'checked_at_utc' => $nowUtc->format('Y-m-d H:i:s'),
                'relevant_device_generations' => array_map(static fn($row): array => [
                    'device_id' => (int) $row->id_dispositivo,
                    'generation' => (string) $row->device_generation,
                    'state' => (string) $row->generation_state,
                ], $deviceRows),
                'active_coverage_policies' => array_map(static fn($row): array => [
                    'policy' => (string) $row->coverage_policy_ref,
                    'max_heartbeat_gap_seconds' => (int) $row->max_heartbeat_gap_seconds,
                    'max_clock_drift_seconds' => (int) $row->max_clock_drift_seconds,
                    'require_telemetry_continuity' => (int) $row->require_telemetry_continuity === 1,
                ], $policyRows),
                'telemetry' => array_map(static fn(array $item): array => [
                    'device_id' => $item['device_id'],
                    'generation' => $item['generation'],
                    'rows' => (int) ($item['rows']->row_count ?? 0),
                    'first_observed_utc' => $item['rows']->first_observed_utc,
                    'last_observed_utc' => $item['rows']->last_observed_utc,
                ], $telemetry),
                'governed_sequence_evidence' => array_map(static fn(array $item): array => [
                    'device_id' => $item['device_id'],
                    'generation' => $item['generation'],
                    'rows' => (int) ($item['governed_punch_sequences']->row_count ?? 0),
                    'max_sequence' => $item['governed_punch_sequences']->max_sequence === null ? null : (int) $item['governed_punch_sequences']->max_sequence,
                ], $sequenceEvidence),
                'read_api' => [
                    'route' => 'GET /api/v2/level2/window-status',
                    'read_only' => false,
                    'reason' => 'The handler calls evaluarVentanaNivel2(), which inserts op_rh_v2_level2_window_evaluations.',
                    'invoked' => false,
                ],
                'direct_select_status' => $level2Count > 0 ? (string) $level2Window->persisted_status : 'NO_PERSISTED_EVALUATION',
            ],
        ];
    }

    /** @param array<string,bool> $checks
     *  @param array<string,mixed> $apiProcess
     *  @param array<string,mixed> $identity
     *  @param array<string,mixed> $evidence
     *  @return array<string,mixed>
     */
    private static function buildManifest(array $checks, array $apiProcess, array $identity, array $evidence): array
    {
        $candidateTemplateIds = $evidence['template_duplicate_check']['equivalent_active_template_ids'] ?? [];
        $templateId = count($candidateTemplateIds) === 1 ? (int) $candidateTemplateIds[0] : null;
        $assignment = $templateId === null ? null : self::assignmentPayload($templateId);
        $history = $evidence['canonical_locality_history'] ?? [];
        $membershipLate = false;
        foreach ($history as $entry) {
            foreach ($evidence['raw_events'] ?? [] as $event) {
                if ((string) $entry['recorded_at_utc'] > (string) $event['received_at_utc']) {
                    $membershipLate = true;
                    break 2;
                }
            }
        }
        $allTelemetryPresent = !empty($evidence['level2']['telemetry'])
            && count(array_filter($evidence['level2']['telemetry'], static fn(array $item): bool => $item['rows'] > 0)) === count($evidence['level2']['telemetry']);
        $level2Potential = !$evidence['level2']['window_is_still_open']
            && $allTelemetryPresent
            && !empty($evidence['level2']['active_coverage_policies'])
            && $evidence['level2']['persisted_window_evaluations'] > 0
            && $evidence['level2']['persisted_status'] === 'VERIFIED';

        return [
            'task' => 'CHECADOR_M02_SCOPED_QA_EXECUTION_PREPARATION',
            'status' => 'PREPARED_ONLY',
            'execution_control' => [
                'mode' => 'PREPARE_ONLY',
                'mutations_enabled' => false,
                'http_requests_sent' => 0,
                'write_operations_executed' => 0,
                'no_jwt_created_or_used' => true,
                'no_global_post_gate_change' => true,
            ],
            'scope' => [
                'worker_id' => self::WORKER_ID,
                'workplace_id' => self::WORKPLACE_ID,
                'local_date' => self::WORK_DATE,
                'utc_window' => $evidence['utc_window'],
                'reject_other_worker_workplace_or_date' => true,
            ],
            'target_checks' => $checks,
            'api_process' => $apiProcess,
            'database_identity' => $identity,
            'scenario_evidence' => [
                'utc_window' => $evidence['utc_window'],
                'timezone' => $evidence['timezone'],
                'worker' => $evidence['worker'],
                'workplace_mapping' => $evidence['workplace_mapping'],
                'timezone_rows' => $evidence['timezone_rows'],
                'canonical_locality_history' => $evidence['canonical_locality_history'],
                'raw_events' => $evidence['raw_events'],
                'assignment_overlap_rows' => $evidence['assignment_overlap_rows'],
                'existing_shift_or_evaluation_rows' => $evidence['existing_shift_or_evaluation_rows'],
                'target_level1_snapshots' => $evidence['target_level1_snapshots'],
                'raw_event_level2_metadata' => $evidence['raw_event_level2_metadata'],
            ],
            'authentication' => [
                'verified_principal' => false,
                'required_for_template_and_assignment' => 'Legitimate Portal3 JWT; active principal; biometricos.editar=1; server-authorized locality 1.',
                'jwt_fabrication' => 'PROHIBITED',
                'permission_or_identity_change' => 'NOT_REQUESTED_OR_PERFORMED',
            ],
            'proposed_template' => [
                'scope' => 'GLOBAL_CATALOG',
                'payload' => self::templatePayload(),
                'timezone' => 'Not a template field; resolved through the canonical locality-1 timezone mapping for the assignment date.',
                'duplicate_check' => $evidence['template_duplicate_check'],
                'create_allowed_in_this_script' => false,
                'permanent_effect' => 'Creates a persistent global template version; no delete operation is exposed. Correction is a new immutable version.',
                'contract_idempotency' => 'The contract mentions optional X-Idempotency-Key, but the inspected handler/service do not consume it; the unique clave/version conflict returns 409, not replayed success.',
            ],
            'proposed_assignment' => [
                'payload_ready' => $assignment !== null,
                'payload' => $assignment,
                'payload_template' => [
                    'id_personal' => self::WORKER_ID,
                    'id_estacion' => self::WORKPLACE_ID,
                    'id_plantilla' => 'Required positive id returned by canonical template GET/POST; not guessed.',
                    'dia_semana' => 'MIERCOLES',
                    'valido_desde' => self::WORK_DATE,
                    'valido_hasta' => '2026-10-29',
                    'es_activo' => 1,
                ],
                'interval_semantics' => '[valido_desde, valido_hasta); affects 2026-10-28 only.',
                'timezone' => $evidence['timezone'],
                'duplicate_and_overlap_checks' => [
                    'overlapping_rows' => count($evidence['assignment_overlap_rows'] ?? []),
                    'materialized_shift_rows' => $evidence['existing_shift_or_evaluation_rows']['materialized_shifts'] ?? null,
                    'canonical_membership_recorded_after_event_receipt' => $membershipLate,
                ],
                'create_allowed_in_this_script' => false,
                'api_client_idempotency' => 'Portal3 sends Idempotency-Key but API handler does not enforce it; resolve ambiguous responses by authenticated GET before any retry.',
            ],
            'readback_plan' => [
                'template' => 'GET /api/v2/shift-templates/QA1004_20261028_0800_1700/versions/1',
                'assignment' => 'GET /api/v2/shift-assignments?id_personal=1004&id_estacion=1&fecha_referencia=2026-10-28&page=1&per_page=50',
                'effective_schedule' => 'GET /api/v2/workers/1004/effective-schedule?id_estacion=1&fecha_local=2026-10-28',
                'all_require_legitimate_bearer_and_station_scope' => true,
            ],
            'level2' => $evidence['level2'] + [
                'can_be_verified_from_current_evidence' => $level2Potential,
                'raw_clock_drift_alert_blocks_level2_directly' => false,
                'qa_events_counted_as_governed_by_level2' => $evidence['raw_event_level2_metadata']['governed_rows'] ?? 0,
                'required_evidence' => [
                    'closed_UTC_window_and_resolved_active_device_generations',
                    'accepted_through_and_contiguous_governed_device_sequences',
                    'telemetry_sequence_continuity_if_required_by_policy',
                    'telemetry coverage across the entire window; no heartbeat gap above 900 seconds',
                    'complete true health booleans: application_running, sdk_initialized, sensor_connected, capture_thread_alive, queue_writable',
                    'telemetry clock_status=SYNCED and server-received/observed drift within 300 seconds',
                ],
                'clock_drift_detail' => 'CLOCK_DRIFT_ALERT compares raw punch occurrence with receipt; Level 2 code evaluates telemetry clock_status and telemetry receipt/observation drift instead. The two QA punch rows have Level 2 metadata with es_governado_nivel2=0, so a Level 2 statement about governed events would not itself classify these punches.',
            ],
            'journey_evaluation' => [
                'feasible_now' => false,
                'reason' => 'No schedule or Level 1 snapshot exists; target window is open and Level 2 evidence is absent. A valid evaluation cannot be claimed.',
                'not_invoked' => true,
                'event_labels' => 'The 08:00 and 17:00 events remain unclassified until an authorized, contract-valid evaluation returns.',
            ],
            'excluded_operations' => [
                'POST /api/v2/punch-events/read-snapshots' => 'Not invoked; it persists snapshot records.',
                'GET /api/v2/level2/window-status' => 'Not invoked; despite GET, its handler calls evaluarVentanaNivel2(), which inserts a Level 2 evaluation row.',
                'POST /api/v2/journeys/evaluate' => 'Not invoked; it evaluates Level 2 and materializes/persists shift, P1 and P2 records.',
            ],
            'safety_risks' => [
                'global_template_effect' => 'Template catalog is not station- or worker-scoped.',
                'global_post_gate' => 'Must remain disabled; enabling it exposes other Portal3 real POST workflows.',
                'qa_allowlist' => 'The API enforces station scope but has no worker-1004 allowlist; the local preparation script itself is hard-scoped.',
                'rollback' => 'Template correction is versioned, not deletion. Assignment close mutates the row; no routed cancellation endpoint was found.',
                'bitemporal' => 'The canonical workplace row was recorded after the raw events were received; effective-time membership is not proof it was known at event time.',
            ],
        ];
    }

    /** @param array<string,bool> $checks
     *  @param array<string,mixed> $apiProcess
     *  @param array<string,mixed>|null $identity
     *  @return array<string,mixed>
     */
    private static function blockedManifest(array $checks, array $apiProcess, string $reason, ?array $identity = null): array
    {
        return [
            'task' => 'CHECADOR_M02_SCOPED_QA_EXECUTION_PREPARATION',
            'status' => 'BLOCKED_BEFORE_DATA_READ',
            'reason' => $reason,
            'scope' => ['worker_id' => self::WORKER_ID, 'workplace_id' => self::WORKPLACE_ID, 'local_date' => self::WORK_DATE],
            'target_checks' => $checks,
            'api_process' => $apiProcess,
            'database_identity' => $identity,
            'execution_control' => ['mode' => 'PREPARE_ONLY', 'mutations_enabled' => false, 'http_requests_sent' => 0],
        ];
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (PHP_SAPI !== 'cli' || $argc !== 1) {
        fwrite(STDERR, "Read-only preparation command; no arguments are accepted.\n");
        exit(2);
    }

    $manifest = ChecadorM02Qa1004Preparation::run();
    echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(($manifest['status'] ?? '') === 'PREPARED_ONLY' ? 0 : 2);
}

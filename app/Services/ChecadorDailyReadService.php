<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Models\Usuario;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Narrow server-to-server read boundary for the Checador daily observed
 * punches screen. This is deliberately not a generic API client.
 */
final class ChecadorDailyReadService
{
    /** Deterministic fallback for the existing V3 demo fixture set. */
    public const DEMO_REFERENCE_DATE = '2026-10-19';

    private const DEFAULT_PAGE_SIZE = 50;
    private const MAX_PAGE_SIZE = 200;
    public const INCIDENCE_CATEGORIES = [
        'VACATION',
        'PERMISSION',
        'DISABILITY',
        'AUTHORIZED_LATENESS',
        'AUTHORIZED_EARLY_DEPARTURE',
        'OFFICIAL_MISSION',
        'PUNCH_ATTENDANCE',
    ];

    /** @var callable(Usuario): string */
    private $tokenFactory;

    private ClientInterface $http;
    private string $baseUrl;
    private ?string $demoScenario = null;
    /** @var array<string,array{workplace_id:int,from_utc:string,snapshot_audit_ref:string}> */
    private array $demoSnapshots = [];

    public function __construct(
        ?ClientInterface $http = null,
        ?callable $tokenFactory = null,
        ?string $baseUrl = null
    ) {
        $this->demoScenario = self::configuredDemoScenario();
        try {
            // The reserved .invalid host is only diagnostic metadata. Every
            // request is intercepted locally while demo mode is active.
            $this->baseUrl = $this->demoScenario !== null
                ? 'https://checador-demo.invalid/'
                : self::resolveBaseUrl($baseUrl);
        } catch (\Throwable $exception) {
            self::logMvpApiInitializationFailure($baseUrl, $exception);
            throw $exception;
        }
        $this->http = $http ?? new Client([
            'base_uri' => $this->baseUrl,
            'http_errors' => false,
            'connect_timeout' => 5.0,
            'timeout' => 15.0,
        ]);
        $this->tokenFactory = $tokenFactory ?? static function (Usuario $user): string {
            return ChecadorApiJwtService::createAccessToken($user);
        };
    }

    /** Synthetic responses are test-only: HTTP application requests always use the configured API. */
    public static function demoModeEnabled(): bool
    {
        if (PHP_SAPI !== 'cli') {
            return false;
        }

        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        $flag = strtolower(trim((string) ($_ENV['CHECADOR_ADVANCED_DEMO_ENABLED'] ?? getenv('CHECADOR_ADVANCED_DEMO_ENABLED') ?: '')));

        return in_array($environment, ['dev', 'development', 'local'], true)
            && in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Real API POST operations remain disabled until a local development
     * environment has been explicitly cleared for an authenticated UAT.
     * Demo requests are handled by the local fixture adapter before this gate.
     */
    public static function realApiPostOperationsEnabled(): bool
    {
        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        $flag = strtolower(trim((string) ($_ENV['CHECADOR_API_REAL_POSTS_ENABLED'] ?? getenv('CHECADOR_API_REAL_POSTS_ENABLED') ?: '')));

        return in_array($environment, ['dev', 'development', 'local'], true)
            && in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Local Shift Scheduling writes may be enabled without enabling POSTs for
     * Journey, incidences, temporary access, snapshots, or other modules.
     * The general opt-in remains backward compatible when it is explicitly on.
     */
    public static function scheduleApiPostOperationsEnabled(): bool
    {
        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        $flag = strtolower(trim((string) ($_ENV['CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED'] ?? getenv('CHECADOR_SHIFT_SCHEDULING_POSTS_ENABLED') ?: '')));

        return in_array($environment, ['dev', 'development', 'local'], true)
            && (self::realApiPostOperationsEnabled() || in_array($flag, ['1', 'true', 'yes', 'on'], true));
    }

    public static function attendancePolicyPostOperationsEnabled(): bool
    {
        return self::localDevelopmentFlagEnabled('CHECADOR_ATTENDANCE_POLICY_POSTS_ENABLED');
    }

    /** M11 Portal3-local catalog/profile writes are separately gated in development. */
    public static function m11ConfigurationPostOperationsEnabled(): bool
    {
        return self::localDevelopmentFlagEnabled('CHECADOR_M11_CONFIGURATION_POSTS_ENABLED');
    }

    /** PIN temporary transitions have an isolated development gate for M07. */
    public static function temporaryAccessPostOperationsEnabled(): bool
    {
        return self::realApiPostOperationsEnabled()
            || self::localDevelopmentFlagEnabled('CHECADOR_TEMPORARY_ACCESS_POSTS_ENABLED');
    }

    public static function p3gBPostOperationsEnabled(): bool
    {
        return self::localDevelopmentFlagEnabled('CHECADOR_P3G_B_POSTS_ENABLED');
    }

    private static function localDevelopmentFlagEnabled(string $name): bool
    {
        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        $flag = strtolower(trim((string) ($_ENV[$name] ?? getenv($name) ?: '')));
        return in_array($environment, ['dev', 'development', 'local'], true)
            && in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    private static function isApprovedShiftSchedulingWritePath(string $path): bool
    {
        $requestPath = parse_url($path, PHP_URL_PATH);
        if (!is_string($requestPath)) {
            return false;
        }
        $requestPath = '/' . ltrim($requestPath, '/');

        return $requestPath === '/api/v2/shift-templates'
            || preg_match('#\A/api/v2/shift-templates/[A-Za-z0-9_-]{1,64}/versions\z#D', $requestPath) === 1
            || preg_match('#\\A/api/v2/shift-assignments(?:/[1-9][0-9]*/(?:close|replace))?\\z#D', $requestPath) === 1;
    }

    private static function realApiPostAllowed(string $method, string $path): bool
    {
        if (strtoupper($method) !== 'POST') {
            return true;
        }

        $requestPath = parse_url($path, PHP_URL_PATH);
        $requestPath = is_string($requestPath) ? '/' . ltrim($requestPath, '/') : '';
        if ($requestPath === '/api/v2/attendance-policies') {
            return self::attendancePolicyPostOperationsEnabled();
        }
        if ($requestPath === '/api/v2/incidences') {
            return self::p3gBPostOperationsEnabled();
        }
        if (preg_match('#\\A/api/v2/temporary-access/requests/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/(?:authorize|reject|cancel)\\z#iD', $requestPath) === 1) {
            return self::temporaryAccessPostOperationsEnabled();
        }
        if (self::realApiPostOperationsEnabled()) return true;

        return self::scheduleApiPostOperationsEnabled()
            && self::isApprovedShiftSchedulingWritePath($path);
    }

    /** @return string|null A safe scenario name, or null when demo mode is disabled. */
    public static function configuredDemoScenario(): ?string
    {
        if (!self::demoModeEnabled()) {
            return null;
        }

        $scenario = strtolower(trim((string) ($_ENV['CHECADOR_ADVANCED_DEMO_SCENARIO'] ?? getenv('CHECADOR_ADVANCED_DEMO_SCENARIO') ?: 'ready')));
        $allowed = [
            'ready',
            'timezone_unavailable',
            'snapshot_expired',
            'level2_verified',
            'level2_incomplete',
            'level2_unknown',
            'empty',
            'service_unavailable',
            'day_schedule',
            'night_schedule',
            'day_off',
            'unscheduled',
            'journey_with_punches',
            'journey_insufficient',
            'journey_review',
            'two_punches',
            'four_punches',
            'many_punches',
            'missing_entry',
            'missing_exit',
            'ambiguous_pairing',
            'overnight_journey',
            'three_segments',
            'journey_not_evaluated',
            'incidence_none',
            'incidence_active',
            'incidence_pending_review',
            'incidence_rejected',
            'incidence_revoked',
            'incidence_history',
            'incidence_conflict',
            'incidence_evaluation_unavailable',
            'incidence_service_unavailable',
            'evaluation_pending_review',
            'evaluation_data_incomplete',
            'evaluation_incidence_conflict',
            'evaluation_available',
            'evaluation_stale_calculation',
            'evaluation_unauthorized',
            'evaluation_duplicate',
            'evaluation_service_unavailable',
            'evaluation_falta_authorized',
            'worker_filter_dataset',
        ];

        // Invalid config remains in the offline demo boundary and fails closed;
        // it must never fall through to the configured remote API.
        return in_array($scenario, $allowed, true) ? $scenario : 'invalid';
    }

    /**
     * Return the demo-only calendar reference. Local development may override
     * it to exercise the default "today" query without changing any fixture
     * contracts or persisting synthetic events in the SQL report source.
     */
    public static function demoReferenceDate(?\DateTimeImmutable $now = null): string
    {
        if (!self::demoModeEnabled()) {
            return self::DEMO_REFERENCE_DATE;
        }

        $configured = trim((string) ($_ENV['CHECADOR_ADVANCED_DEMO_REFERENCE_DATE']
            ?? getenv('CHECADOR_ADVANCED_DEMO_REFERENCE_DATE') ?: ''));
        if (self::isCivilDate($configured)) {
            return $configured;
        }

        $zone = new \DateTimeZone('America/Mexico_City');
        return ($now ?? new \DateTimeImmutable('now', $zone))->setTimezone($zone)->format('Y-m-d');
    }

    public function isDemoMode(): bool
    {
        return $this->demoScenario !== null;
    }

    public function demoScenario(): ?string
    {
        return $this->demoScenario;
    }

    /** True only when this client is configured to reach a loopback API host. */
    public function isLocalTarget(): bool
    {
        $host = strtolower((string) (parse_url($this->baseUrl, PHP_URL_HOST) ?? ''));
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Resolve the API-owned timezone and UTC window for one local calendar day.
     *
     * @return array{ok:bool, data?:array<string,mixed>, error?:array{kind:string,status:int}}
     */
    public function resolveWorkplaceLocalDay(Usuario $user, int $workplaceId, string $date): array
    {
        if ($workplaceId < 1 || !self::isCivilDate($date)) {
            return self::failure('bad_request', 400);
        }

        $response = $this->request($user, 'GET', 'api/v2/workplaces/' . $workplaceId . '/timezone', [
            'date' => $date,
        ], 'workplace_timezone');
        if (!$response['ok']) {
            return $response;
        }

        $payload = $response['payload'];
        $data = $payload['data'] ?? null;
        if (!is_array($data)
            || (int) ($data['workplace_id'] ?? 0) !== $workplaceId
            || (string) ($data['local_date'] ?? '') !== $date
            || trim((string) ($data['timezone'] ?? '')) === ''
            || trim((string) ($data['from_utc'] ?? '')) === ''
            || trim((string) ($data['to_utc'] ?? '')) === '') {
            $this->logMvpApiDiagnostic('workplace_timezone', array_merge(
                $response['diagnostic'] ?? [],
                [
                    'local_error_kind' => 'api_unavailable',
                    'response_contract_valid' => false,
                    'contract_failure' => 'timezone_payload_invalid',
                ]
            ));
            return self::failure('api_unavailable', 502);
        }

        if (!ChecadorOperationalPeriod::isIanaTimezone((string) $data['timezone'])) {
            $this->logMvpApiDiagnostic('workplace_timezone', array_merge(
                $response['diagnostic'] ?? [],
                [
                    'local_error_kind' => 'timezone_unresolved',
                    'response_contract_valid' => false,
                    'contract_failure' => 'timezone_not_iana',
                ]
            ));
            return self::failure('timezone_unresolved', 502);
        }

        $this->logMvpApiDiagnostic('workplace_timezone', array_merge(
            $response['diagnostic'] ?? [],
            ['response_contract_valid' => true]
        ), true);

        return ['ok' => true, 'data' => [
            'workplace_id' => $workplaceId,
            'local_date' => $date,
            'timezone' => (string) $data['timezone'],
            // These boundaries are opaque API authority; do not recalculate.
            'from_utc' => (string) $data['from_utc'],
            'to_utc' => (string) $data['to_utc'],
        ]];
    }

    /** Read the API-owned M11 device inventory using the delegated human JWT. */
    public function readAdministrativeDevices(Usuario $user): array
    {
        if ($this->demoScenario !== null) return self::failure('demo_operation_blocked', 403);

        $response = $this->request($user, 'GET', 'api/v2/administrative-devices');
        if (!($response['ok'] ?? false)) return $response;
        $payload = $response['payload'];
        $items = $payload['items'] ?? null;
        $completeness = $payload['completeness'] ?? null;
        $readAt = $payload['read_at_utc'] ?? null;
        if (($payload['status'] ?? null) !== 'OK'
            || !in_array($completeness, ['COMPLETE', 'PARTIAL', 'UNKNOWN'], true)
            || !is_string($readAt) || trim($readAt) === ''
            || !is_array($items) || !array_is_list($items)
            || count($items) > 500) {
            return self::failure('administrative_unavailable', 502);
        }

        $safeItems = [];
        foreach ($items as $item) {
            if (!is_array($item)
                || !is_array($item['device'] ?? null)
                || !is_array($item['physical_station'] ?? null)
                || !is_array($item['workplace'] ?? null)
                || !is_array($item['assignment'] ?? null)
                || !is_array($item['last_communication'] ?? null)) {
                return self::failure('administrative_unavailable', 502);
            }
            $device = $item['device'];
            $station = $item['physical_station'];
            $workplace = $item['workplace'];
            $assignment = $item['assignment'];
            $communication = $item['last_communication'];
            if (!is_int($device['id'] ?? null) || $device['id'] < 1
                || !is_string($device['uuid'] ?? null) || !is_string($device['name'] ?? null)
                || !in_array($device['persisted_status'] ?? null, ['ACTIVE', 'INACTIVE', 'UNKNOWN'], true)
                || !in_array($assignment['status'] ?? null, ['ASSIGNED', 'UNASSIGNED', 'UNKNOWN'], true)
                || !in_array($workplace['mapping_status'] ?? null, ['VERIFIED', 'AMBIGUOUS'], true)
                || !(($communication['last_server_received_at_utc'] ?? null) === null
                    || is_string($communication['last_server_received_at_utc']))) {
                return self::failure('administrative_unavailable', 502);
            }
            // Whitelist the approved response fields; secrets and telemetry payloads
            // cannot cross this boundary even if the upstream response changes.
            $safeItems[] = [
                'device' => [
                    'id' => $device['id'], 'uuid' => $device['uuid'], 'name' => $device['name'],
                    'persisted_status' => $device['persisted_status'],
                ],
                'physical_station' => [
                    'id' => $station['id'] ?? null, 'name' => $station['name'] ?? null,
                    'numlista' => $station['numlista'] ?? null,
                ],
                'workplace' => [
                    'id' => $workplace['id'] ?? null, 'name' => $workplace['name'] ?? null,
                    'mapping_status' => $workplace['mapping_status'],
                ],
                'assignment' => [
                    'status' => $assignment['status'], 'valid_from_utc' => $assignment['valid_from_utc'] ?? null,
                    'valid_to_utc' => $assignment['valid_to_utc'] ?? null, 'source' => $assignment['source'] ?? null,
                ],
                'group' => [
                    'id' => null, 'name' => null, 'mapping_status' => 'UNAVAILABLE',
                ],
                'last_communication' => [
                    'last_server_received_at_utc' => $communication['last_server_received_at_utc'],
                    'source' => $communication['source'] ?? 'ACCEPTED_DEVICE_TELEMETRY',
                ],
            ];
        }

        $partialReasons = $payload['partial_reasons'] ?? [];
        if (!is_array($partialReasons) || !array_is_list($partialReasons)) {
            return self::failure('administrative_unavailable', 502);
        }
        foreach ($partialReasons as $reason) {
            if (!is_string($reason) || preg_match('/\\A[A-Z][A-Z0-9_]{0,63}\\z/D', $reason) !== 1) {
                return self::failure('administrative_unavailable', 502);
            }
        }

        return ['ok' => true, 'data' => [
            'status' => 'OK', 'completeness' => $completeness, 'read_at_utc' => $readAt,
            'items' => $safeItems,
            'partial_reasons' => array_values($partialReasons),
        ]];
    }

    /** Read the API-owned effective M11 policy; no legacy value is promoted into a V2 version. */
    public function readAttendancePolicy(Usuario $user, int $workplaceId, string $effectiveDate): array
    {
        if ($this->demoScenario !== null) return self::failure('demo_operation_blocked', 403);
        if ($workplaceId < 1 || !self::isCivilDate($effectiveDate)) return self::failure('bad_request', 400);

        $response = $this->request($user, 'GET', 'api/v2/attendance-policies', [
            'workplace_id' => $workplaceId,
            'effective_date' => $effectiveDate,
        ]);
        if (!($response['ok'] ?? false)) return $response;
        $data = $response['payload']['data'] ?? null;
        if (!self::validAttendancePolicyEnvelope($data, $workplaceId, $effectiveDate)) {
            return self::failure('administrative_unavailable', 502);
        }
        return ['ok' => true, 'data' => $data];
    }

    /** Discover the station's current local date using API timezone authority, then read exactly that date. */
    public function readCurrentAttendancePolicy(Usuario $user, int $workplaceId): array
    {
        $seedDate = gmdate('Y-m-d');
        $seed = $this->readAttendancePolicy($user, $workplaceId, $seedDate);
        if (!($seed['ok'] ?? false)) return $seed;
        $localDate = (string) ($seed['data']['current_local_date'] ?? '');
        if (!self::isCivilDate($localDate)) return self::failure('administrative_unavailable', 502);
        if ($localDate === $seedDate) return $seed;
        $local = $this->readAttendancePolicy($user, $workplaceId, $localDate);
        if (!($local['ok'] ?? false)
            || (string) ($local['data']['current_local_date'] ?? '') !== $localDate) {
            return self::failure('administrative_unavailable', 502);
        }
        return $local;
    }

    /** Create a future M11 version through its dedicated development gate and API writer. */
    public function createAttendancePolicy(Usuario $user, array $input, string $idempotencyKey): array
    {
        if ($this->demoScenario !== null) return self::failure('demo_write_blocked', 403);
        $workplaceId = self::positiveInt($input['workplace_id'] ?? null);
        $effectiveDate = is_string($input['effective_from_local_date'] ?? null)
            ? trim($input['effective_from_local_date']) : '';
        $tolerance = self::strictInteger($input['retardo_tolerance_minutes'] ?? null);
        $deadlineDays = self::strictInteger($input['incidence_deadline_days'] ?? null);
        if ($workplaceId === null || !self::isCivilDate($effectiveDate)
            || $tolerance === null || $tolerance < 0 || $tolerance > 120
            || $deadlineDays === null || $deadlineDays < 0 || $deadlineDays > 30
            || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', trim($idempotencyKey))) {
            return self::failure('bad_request', 400);
        }
        if (!self::attendancePolicyPostOperationsEnabled()) return self::failure('api_post_disabled', 403);

        $response = $this->requestJson($user, 'POST', 'api/v2/attendance-policies', [
            'workplace_id' => $workplaceId,
            'effective_from_local_date' => $effectiveDate,
            'retardo_tolerance_minutes' => $tolerance,
            'incidence_deadline_days' => $deadlineDays,
        ], trim($idempotencyKey));
        if (!($response['ok'] ?? false)) return $response;
        $data = $response['payload']['data'] ?? null;
        if (!is_array($data)
            || self::positiveInt($data['id'] ?? null) === null
            || (int) ($data['workplace_id'] ?? 0) !== $workplaceId
            || !is_int($data['policy_version'] ?? null) || $data['policy_version'] < 1
            || (string) ($data['effective_from_local_date'] ?? '') !== $effectiveDate
            || (int) ($data['retardo_tolerance_minutes'] ?? -1) !== $tolerance
            || (int) ($data['incidence_deadline_days'] ?? -1) !== $deadlineDays
            || !is_string($data['timezone_id'] ?? null)
            || !ChecadorOperationalPeriod::isIanaTimezone($data['timezone_id'])
            || !is_int($data['actor_portal_user_id'] ?? null)
            || (string) ($data['source'] ?? '') !== 'PORTAL3_ADMIN') {
            return self::failure('administrative_unavailable', 502);
        }
        return ['ok' => true, 'http_status' => (int) ($response['http_status'] ?? 200), 'data' => $data];
    }

    private static function validAttendancePolicyEnvelope(mixed $data, int $workplaceId, string $date): bool
    {
        if (!is_array($data) || (int) ($data['workplace_id'] ?? 0) !== $workplaceId
            || (string) ($data['effective_date'] ?? '') !== $date
            || !self::isCivilDate((string) ($data['current_local_date'] ?? ''))
            || !is_string($data['timezone_id'] ?? null)
            || !ChecadorOperationalPeriod::isIanaTimezone($data['timezone_id'])
            || !in_array(($data['status'] ?? null), ['VERSIONED', 'LEGACY_UNVERSIONED'], true)
            || !array_key_exists('policy', $data)) return false;
        if ($data['status'] === 'LEGACY_UNVERSIONED') return $data['policy'] === null;
        $policy = $data['policy'];
        return is_array($policy)
            && (int) ($policy['workplace_id'] ?? 0) === $workplaceId
            && self::isCivilDate((string) ($policy['effective_from_local_date'] ?? ''))
            && (string) $policy['effective_from_local_date'] <= $date
            && is_int($policy['retardo_tolerance_minutes'] ?? null)
            && $policy['retardo_tolerance_minutes'] >= 0 && $policy['retardo_tolerance_minutes'] <= 120
            && is_int($policy['incidence_deadline_days'] ?? null)
            && $policy['incidence_deadline_days'] >= 0 && $policy['incidence_deadline_days'] <= 30;
    }

    /**
     * Read observed raw punches for the exact API-provided UTC window.
     *
     * @param array{from_utc:string,to_utc:string} $timezoneContext
     * @return array{ok:bool, events?:array<int,array<string,mixed>>, pagination?:array<string,int>, error?:array{kind:string,status:int}}
     */
    public function readObservedPunches(
        Usuario $user,
        int $workplaceId,
        array $timezoneContext,
        int $page = 1,
        int $perPage = self::DEFAULT_PAGE_SIZE
    ): array {
        if ($workplaceId < 1
            || $page < 1
            || $perPage < 1
            || $perPage > self::MAX_PAGE_SIZE
            || trim((string) ($timezoneContext['from_utc'] ?? '')) === ''
            || trim((string) ($timezoneContext['to_utc'] ?? '')) === '') {
            return self::failure('bad_request', 400);
        }

        $response = $this->request($user, 'GET', 'api/v2/punch-events', [
            'from' => (string) $timezoneContext['from_utc'],
            'to' => (string) $timezoneContext['to_utc'],
            'station_id' => $workplaceId,
            'page' => $page,
            'per_page' => $perPage,
        ]);
        if (!$response['ok']) {
            return $response;
        }

        $payload = $response['payload'];
        $events = $payload['data'] ?? null;
        $pagination = $payload['pagination'] ?? null;
        if (!is_array($events) || !is_array($pagination)) {
            return self::failure('api_unavailable', 502);
        }

        foreach (['current_page', 'per_page', 'total_records', 'total_pages'] as $field) {
            $value = $pagination[$field] ?? null;
            $validInteger = is_int($value) || (is_string($value) && ctype_digit($value));
            if (!array_key_exists($field, $pagination) || !$validInteger) {
                return self::failure('api_unavailable', 502);
            }
        }

        $rawPagination = array_map(static fn(mixed $value): int => (int) $value, $pagination);
        if ($rawPagination['current_page'] < 1
            || $rawPagination['current_page'] !== $page
            || $rawPagination['per_page'] < 1
            || $rawPagination['per_page'] > self::MAX_PAGE_SIZE
            || $rawPagination['per_page'] !== $perPage
            || $rawPagination['total_records'] < 0
            || $rawPagination['total_pages'] < 0
            || $rawPagination['total_pages'] !== ($rawPagination['total_records'] > 0
                ? (int) ceil($rawPagination['total_records'] / $perPage)
                : 0)
            || count($events) > $perPage
            || count(array_filter($events, 'is_array')) !== count($events)) {
            return self::failure('api_unavailable', 502);
        }

        $integrityStatuses = ['VALID', 'INVALID', 'UNVERIFIABLE', 'LEGACY_UNSEALED'];
        foreach ($events as $event) {
            $eventUuid = $event['event_uuid'] ?? null;
            $physicalStationId = $event['id_estacion_resuelta'] ?? null;
            $workerId = $event['id_personal_resuelto'] ?? null;
            $processingStatus = $event['processing_status'] ?? null;
            $integrityStatus = $event['integrity']['status'] ?? null;
            $validPhysicalId = is_int($physicalStationId)
                || (is_string($physicalStationId) && ctype_digit($physicalStationId));
            $validWorkerId = $workerId === null
                || is_int($workerId)
                || (is_string($workerId) && ctype_digit($workerId));
            if (!is_string($eventUuid) || trim($eventUuid) === ''
                || !$validPhysicalId || (int) $physicalStationId < 1
                || !$validWorkerId || ($workerId !== null && (int) $workerId < 1)
                || !is_string($processingStatus) || trim($processingStatus) === ''
                || !is_string($integrityStatus) || !in_array($integrityStatus, $integrityStatuses, true)) {
                return self::failure('api_unavailable', 502);
            }
        }

        $normalizedPagination = [
            'current_page' => $rawPagination['current_page'],
            'per_page' => $rawPagination['per_page'],
            'total_records' => $rawPagination['total_records'],
            'total_pages' => $rawPagination['total_pages'],
        ];

        return [
            'ok' => true,
            'events' => array_values(array_filter($events, 'is_array')),
            'pagination' => $normalizedPagination,
        ];
    }

    /**
     * Read the live, authorized raw-event result for one local workday without
     * creating a snapshot. `station_id` is a canonical locality; each returned
     * `id_estacion_resuelta` is a physical station and must be checked against
     * the already-resolved physical ID supplied by the caller.
     *
     * This validates API pagination, but does not claim source completeness or
     * snapshot consistency. The raw events are never classified here.
     *
     * @return array{ok:bool,timezone?:array<string,string>,events?:list<array<string,mixed>>,pagination?:array<string,int>,consistency?:string,source_coverage?:string,error?:array{kind:string,status:int}}
     */
    public function readJourneyObservationSet(
        Usuario $user,
        int $workplaceId,
        int $physicalStationId,
        string $date,
        int $maxPages = 20
    ): array {
        if ($workplaceId < 1 || $physicalStationId < 1 || !self::isCivilDate($date)
            || $maxPages < 1 || $maxPages > 20) {
            return self::failure('bad_request', 400);
        }

        $timezoneResult = $this->resolveWorkplaceLocalDay($user, $workplaceId, $date);
        if (!($timezoneResult['ok'] ?? false)) {
            return $timezoneResult;
        }
        $timezone = $timezoneResult['data'];
        $events = [];
        $seenUuids = [];
        $firstPagination = null;
        $readPages = 0;

        for ($page = 1; $page <= $maxPages; $page++) {
            $result = $this->readObservedPunches($user, $workplaceId, $timezone, $page, self::MAX_PAGE_SIZE);
            if (!($result['ok'] ?? false)) {
                return $result;
            }

            $pagination = $result['pagination'];
            if ($firstPagination === null) {
                $firstPagination = $pagination;
            } elseif ($pagination['total_records'] !== $firstPagination['total_records']
                || $pagination['total_pages'] !== $firstPagination['total_pages']) {
                return self::failure('journey_read_inconsistent', 502);
            }

            foreach ($result['events'] as $event) {
                if ((int) ($event['id_estacion_resuelta'] ?? 0) !== $physicalStationId) {
                    return self::failure('station_scope_mismatch', 502);
                }
                $uuid = (string) ($event['event_uuid'] ?? '');
                if ($uuid === '' || isset($seenUuids[$uuid])) {
                    return self::failure('journey_read_inconsistent', 502);
                }
                $seenUuids[$uuid] = true;
                $events[] = $event;
            }

            $readPages = $page;
            $requiredPages = max(1, (int) ($firstPagination['total_pages'] ?? 0));
            if ($page >= $requiredPages) {
                if (count($events) !== (int) ($firstPagination['total_records'] ?? -1)) {
                    return self::failure('journey_read_inconsistent', 502);
                }

                return [
                    'ok' => true,
                    'timezone' => $timezone,
                    'events' => $events,
                    'pagination' => [
                        'total_records' => (int) $firstPagination['total_records'],
                        'total_pages' => (int) $firstPagination['total_pages'],
                        'pages_read' => $readPages,
                        'per_page' => self::MAX_PAGE_SIZE,
                    ],
                    'consistency' => 'LIVE_NON_SNAPSHOT',
                    'source_coverage' => 'UNKNOWN',
                ];
            }
        }

        return self::failure('journey_read_limit', 502);
    }

    /**
     * Read one page of raw punch events using the v2 read contract.
     * The API remains the authority for station scope and event filtering.
     *
     * @return array{ok:bool,events?:array<int,array<string,mixed>>,pagination?:array{current_page:int,per_page:int,total_records:int,total_pages:int},error?:array{kind:string,status:int}}
     */
    public function readPunchReportPage(
        Usuario $user,
        int $workplaceId,
        string $fromUtc,
        string $toUtc,
        string $externalWorkerReference = '',
        int $page = 1,
        int $perPage = self::DEFAULT_PAGE_SIZE
    ): array {
        $externalWorkerReference = trim($externalWorkerReference);
        if ($workplaceId < 1
            || trim($fromUtc) === ''
            || trim($toUtc) === ''
            || $page < 1
            || $perPage < 1
            || $perPage > self::MAX_PAGE_SIZE
            || preg_match('//u', $externalWorkerReference) !== 1
            || self::unicodeLength($externalWorkerReference) > 64) {
            return self::failure('bad_request', 400);
        }

        $query = [
            'from' => $fromUtc,
            'to' => $toUtc,
            'station_id' => $workplaceId,
            'page' => $page,
            'per_page' => $perPage,
        ];
        if ($externalWorkerReference !== '') {
            $query['external_worker_reference'] = $externalWorkerReference;
        }

        $response = $this->request($user, 'GET', 'api/v2/punch-events', $query, 'raw_punch_events');
        if (!$response['ok']) {
            return $response;
        }

        $events = $response['payload']['data'] ?? null;
        $pagination = $response['payload']['pagination'] ?? null;
        if (!is_array($events) || !is_array($pagination)) {
            $this->logMvpApiDiagnostic('raw_punch_events', array_merge(
                $response['diagnostic'] ?? [],
                [
                    'local_error_kind' => 'api_unavailable',
                    'response_contract_valid' => false,
                    'contract_failure' => 'events_or_pagination_missing',
                ]
            ));
            return self::failure('api_unavailable', 502);
        }
        foreach (['current_page', 'per_page', 'total_records', 'total_pages'] as $field) {
            $value = $pagination[$field] ?? null;
            if (!(is_int($value) || (is_string($value) && ctype_digit($value)))) {
                $this->logMvpApiDiagnostic('raw_punch_events', array_merge(
                    $response['diagnostic'] ?? [],
                    [
                        'local_error_kind' => 'api_unavailable',
                        'response_contract_valid' => false,
                        'contract_failure' => 'pagination_field_invalid',
                    ]
                ));
                return self::failure('api_unavailable', 502);
            }
        }

        $normalized = array_map(static fn(mixed $value): int => (int) $value, $pagination);
        if ($normalized['current_page'] < 1
            || $normalized['current_page'] !== $page
            || $normalized['per_page'] < 1
            || $normalized['per_page'] > self::MAX_PAGE_SIZE
            || $normalized['per_page'] !== $perPage
            || $normalized['total_records'] < 0
            || $normalized['total_pages'] < 0
            || $normalized['total_pages'] !== ($normalized['total_records'] > 0 ? (int) ceil($normalized['total_records'] / $perPage) : 0)
            || count($events) > $perPage
            || count(array_filter($events, 'is_array')) !== count($events)) {
            $this->logMvpApiDiagnostic('raw_punch_events', array_merge(
                $response['diagnostic'] ?? [],
                [
                    'local_error_kind' => 'api_unavailable',
                    'response_contract_valid' => false,
                    'contract_failure' => 'pagination_or_event_shape_invalid',
                ]
            ));
            return self::failure('api_unavailable', 502);
        }

        foreach ($events as $event) {
            $resolvedStationId = $event['id_estacion_resuelta'] ?? null;
            $validStationId = is_int($resolvedStationId)
                || (is_string($resolvedStationId) && ctype_digit($resolvedStationId));
            if (!$validStationId || (int) $resolvedStationId !== $workplaceId) {
                $this->logMvpApiDiagnostic('raw_punch_events', array_merge(
                    $response['diagnostic'] ?? [],
                    [
                        'local_error_kind' => 'api_authorization',
                        'response_contract_valid' => false,
                        'contract_failure' => 'event_station_mismatch',
                    ]
                ));
                return self::failure('api_authorization', 403);
            }
        }

        $this->logMvpApiDiagnostic('raw_punch_events', array_merge(
            $response['diagnostic'] ?? [],
            ['response_contract_valid' => true]
        ), true);

        return [
            'ok' => true,
            'events' => array_values($events),
            'pagination' => [
                'current_page' => $normalized['current_page'],
                'per_page' => $normalized['per_page'],
                'total_records' => $normalized['total_records'],
                'total_pages' => $normalized['total_pages'],
            ],
        ];
    }

    /**
     * Create one Level 1 read snapshot for the API-owned UTC window.
     * The returned token is server-side state and must not reach the browser.
     *
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function createDailyPunchSnapshot(
        Usuario $user,
        int $workplaceId,
        array $timezoneContext,
        string $creationRequestUuid
    ): array {
        $fromUtc = trim((string) ($timezoneContext['from_utc'] ?? ''));
        $toUtc = trim((string) ($timezoneContext['to_utc'] ?? ''));
        if ($workplaceId < 1 || $fromUtc === '' || $toUtc === '' || trim($creationRequestUuid) === '') {
            return self::failure('bad_request', 400);
        }

        $response = $this->requestJson(
            $user,
            'POST',
            'api/v2/punch-events/read-snapshots',
            [
                'from' => $fromUtc,
                'to' => $toUtc,
                'station_id' => $workplaceId,
            ],
            $creationRequestUuid
        );
        if (!$response['ok']) {
            return $response;
        }

        $snapshot = $response['payload']['data']['snapshot'] ?? null;
        $completeness = $response['payload']['data']['completeness'] ?? null;
        if (!is_array($snapshot)
            || !is_array($completeness)
            || trim((string) ($snapshot['snapshot_token'] ?? '')) === ''
            || (string) ($snapshot['status'] ?? '') !== 'READY'
            || !isset($snapshot['expires_at'])
            || ($completeness['level_1_api_snapshot'] ?? false) !== true) {
            return self::failure('snapshot_unavailable', 502);
        }

        return [
            'ok' => true,
            'data' => [
                'snapshot_token' => (string) $snapshot['snapshot_token'],
                'snapshot_audit_ref' => (string) ($snapshot['snapshot_audit_ref'] ?? ''),
                'status' => (string) $snapshot['status'],
                'created_at' => (string) ($snapshot['created_at'] ?? ''),
                'expires_at' => (string) $snapshot['expires_at'],
                'total_records' => (int) ($snapshot['total_records'] ?? 0),
                'completeness_status' => (string) ($completeness['status'] ?? 'LEVEL_1_COMPLETE_ONLY'),
            ],
        ];
    }

    /**
     * Read one page from an existing Level 1 snapshot. The API revalidates
     * creator identity and current station scope on every request.
     *
     * @return array{ok:bool,events?:array<int,array<string,mixed>>,pagination?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function readDailyPunchSnapshotPage(
        Usuario $user,
        string $snapshotToken,
        ?string $cursor = null,
        int $limit = self::DEFAULT_PAGE_SIZE,
        ?int $resolvedWorkerId = null
    ): array {
        if (trim($snapshotToken) === '' || $limit < 1 || $limit > self::MAX_PAGE_SIZE
            || ($resolvedWorkerId !== null && $resolvedWorkerId < 1)) {
            return self::failure('bad_request', 400);
        }

        $query = ['limit' => $limit];
        if ($cursor !== null && trim($cursor) !== '') {
            $query['cursor'] = $cursor;
        }
        $response = $this->request(
            $user,
            'GET',
            'api/v2/punch-events/read-snapshots/' . rawurlencode($snapshotToken) . '/events',
            $query,
            null,
            $resolvedWorkerId
        );
        if (!$response['ok']) {
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        $items = is_array($data) ? ($data['items'] ?? null) : null;
        $pagination = is_array($data) ? ($data['pagination'] ?? null) : null;
        if (!is_array($items) || !is_array($pagination)
            || !array_key_exists('next_cursor', $pagination)
            || !array_key_exists('has_more', $pagination)
            || !array_key_exists('page_size', $pagination)) {
            return self::failure('snapshot_unavailable', 502);
        }

        return [
            'ok' => true,
            'events' => array_values(array_filter($items, 'is_array')),
            'pagination' => [
                'next_cursor' => $pagination['next_cursor'] !== null ? (string) $pagination['next_cursor'] : null,
                'has_more' => (bool) $pagination['has_more'],
                'page_size' => (int) $pagination['page_size'],
                ...(array_key_exists('matched_records', $pagination)
                    ? ['matched_records' => (int) $pagination['matched_records']]
                    : []),
            ],
        ];
    }

    /**
     * Read the API-owned Level 2 source/window completeness result for the
     * exact UTC window returned by the workplace timezone endpoint.
     *
     * @param array{from_utc:string,to_utc:string} $timezoneContext
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function getLevel2WindowStatus(
        Usuario $user,
        int $workplaceId,
        array $timezoneContext
    ): array {
        $fromUtc = trim((string) ($timezoneContext['from_utc'] ?? ''));
        $toUtc = trim((string) ($timezoneContext['to_utc'] ?? ''));
        if ($workplaceId < 1 || $fromUtc === '' || $toUtc === '') {
            return self::failure('bad_request', 400);
        }

        $response = $this->request($user, 'GET', 'api/v2/level2/window-status', [
            'station_id' => $workplaceId,
            'from' => $fromUtc,
            'to' => $toUtc,
        ]);
        if (!$response['ok']) {
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        $aggregate = is_array($data) ? (string) ($data['window_level2_status'] ?? '') : '';
        if (!is_array($data) || !in_array($aggregate, ['VERIFIED', 'INCOMPLETE', 'UNKNOWN'], true)) {
            return self::failure('level2_unavailable', 502);
        }

        foreach (['delivery_completeness', 'operational_coverage', 'time_window_attribution'] as $field) {
            if (!array_key_exists($field, $data) || !is_string($data[$field])) {
                return self::failure('level2_unavailable', 502);
            }
        }

        return [
            'ok' => true,
            'data' => [
                // The aggregate is authoritative; dimensions are contextual.
                'window_level2_status' => $aggregate,
                'delivery_completeness' => (string) $data['delivery_completeness'],
                'operational_coverage' => (string) $data['operational_coverage'],
                'time_window_attribution' => (string) $data['time_window_attribution'],
                'has_zero_governed_events' => (bool) ($data['has_zero_governed_events'] ?? false),
                'governed_event_count' => (int) ($data['governed_event_count'] ?? 0),
            ],
        ];
    }

    /**
     * Read the API-owned effective schedule for one canonical worker already
     * present in the stable PunchEvent result. The worker id is never taken
     * from the Portal user or from an external worker reference.
     *
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function getEffectiveSchedule(
        Usuario $user,
        int $workerId,
        int $workplaceId,
        string $date
    ): array {
        if ($workerId < 1 || $workplaceId < 1 || !self::isCivilDate($date)) {
            return self::failure('bad_request', 400);
        }

        $response = $this->request($user, 'GET', 'api/v2/workers/' . $workerId . '/effective-schedule', [
            'id_estacion' => $workplaceId,
            'fecha_local' => $date,
        ]);
        if (!$response['ok']) {
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        $status = is_array($data) ? (string) ($data['estado_jornada'] ?? '') : '';
        if (!is_array($data)
            || (int) ($data['id_personal'] ?? 0) !== $workerId
            || (int) ($data['id_estacion'] ?? 0) !== $workplaceId
            || (string) ($data['fecha_local'] ?? '') !== $date
            || !in_array($status, ['SCHEDULED', 'DAY_OFF', 'UNSCHEDULED', 'UNKNOWN'], true)) {
            return self::failure('schedule_unavailable', 502);
        }

        if ($status === 'SCHEDULED') {
            $local = $data['horario_local'] ?? null;
            $segments = is_array($local) ? ($local['segmentos'] ?? null) : null;
            if (!is_array($local)
                || !self::isIanaTimezone($local['timezone'] ?? null)
                || !is_array($segments)
                || $segments === []) {
                return self::failure('schedule_unavailable', 502);
            }
            foreach ($segments as $segment) {
                if (!is_array($segment)
                    || !isset($segment['ordinal'], $segment['tipo'], $segment['hora_inicio_local'], $segment['hora_fin_local'])) {
                    return self::failure('schedule_unavailable', 502);
                }
            }
        }

        return ['ok' => true, 'data' => $data];
    }

    /**
     * Read the opt-in API V2 WORKER_EFFECTIVE resolver. Unlike the legacy
     * station query, the API derives the home locality and authorized coverage
     * from canonical membership and coverage history; Portal3 never chooses it.
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function getWorkerEffectiveSchedule(
        Usuario $user,
        int $workerId,
        string $date,
        ?int $expectedEffectiveWorkplaceId = null
    ): array {
        if ($workerId < 1 || !self::isCivilDate($date)
            || ($expectedEffectiveWorkplaceId !== null && $expectedEffectiveWorkplaceId < 1)) {
            return self::failure('bad_request', 400);
        }
        $response = $this->request($user, 'GET', 'api/v2/workers/' . $workerId . '/effective-schedule', [
            'fecha_local' => $date,
            'resolution_context' => 'WORKER_EFFECTIVE',
        ]);
        if (!$response['ok']) return $response;

        $data = $response['payload']['data'] ?? null;
        $status = is_array($data) ? (string) ($data['estado_jornada'] ?? '') : '';
        $effectiveWorkplaceId = is_array($data)
            ? self::positiveInt($data['effective_workplace_id'] ?? $data['id_estacion'] ?? null)
            : null;
        if (!is_array($data)
            || (int) ($data['id_personal'] ?? 0) !== $workerId
            || (string) ($data['fecha_local'] ?? '') !== $date
            || ($data['resolution_context'] ?? '') !== 'WORKER_EFFECTIVE'
            || !in_array($status, ['SCHEDULED', 'DAY_OFF', 'UNSCHEDULED', 'UNKNOWN'], true)
            || ($status !== 'UNKNOWN' && $effectiveWorkplaceId === null)) {
            return self::failure('schedule_unavailable', 502);
        }
        if ($expectedEffectiveWorkplaceId !== null && $effectiveWorkplaceId !== null
            && $effectiveWorkplaceId !== $expectedEffectiveWorkplaceId) {
            return self::failure('effective_workplace_mismatch', 409);
        }
        if ($status === 'SCHEDULED') {
            $local = $data['horario_local'] ?? null;
            $segments = is_array($local) ? ($local['segmentos'] ?? null) : null;
            if (!is_array($local) || !self::isIanaTimezone($local['timezone'] ?? null)
                || !is_array($segments) || $segments === []) {
                return self::failure('schedule_unavailable', 502);
            }
            foreach ($segments as $segment) {
                if (!is_array($segment) || !isset($segment['ordinal'], $segment['tipo'], $segment['hora_inicio_local'], $segment['hora_fin_local'])) {
                    return self::failure('schedule_unavailable', 502);
                }
            }
        }
        return ['ok' => true, 'data' => $data];
    }

    /**
     * Read the API-owned effective-dated assignment history for one canonical
     * worker and authorized Workplace. A reference date is optional and, when
     * supplied, follows the API's half-open validity interval contract.
     *
     * @return array{ok:bool,data?:array{page:int,per_page:int,items:list<array<string,mixed>>},error?:array{kind:string,status:int}}
     */
    public function getShiftAssignments(
        Usuario $user,
        int $workerId,
        int $workplaceId,
        int $page = 1,
        int $perPage = 50,
        ?string $referenceDate = null,
        bool $snapshotMode = false,
        ?string $snapshotToken = null
    ): array {
        if ($workerId < 1 || $workplaceId < 1 || $page < 1 || $page > 10000
            || $perPage < 1 || $perPage > 200
            || ($referenceDate !== null && !self::isCivilDate($referenceDate))) {
            return self::failure('bad_request', 400);
        }

        $query = [
            'id_personal' => $workerId,
            'id_estacion' => $workplaceId,
            'page' => $page,
            'per_page' => $perPage,
        ];
        if ($referenceDate !== null) {
            $query['fecha_referencia'] = $referenceDate;
        }
        $headers = [];
        if ($snapshotMode) {
            $query['pagination_mode'] = 'snapshot';
            if ($snapshotToken !== null && $snapshotToken !== '') {
                $headers['X-Read-Snapshot'] = $snapshotToken;
            }
        } elseif ($snapshotToken !== null) {
            return self::failure('bad_request', 400);
        }

        $response = $this->request($user, 'GET', 'api/v2/shift-assignments', $query, null, null, $headers);
        if (!$response['ok']) {
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        $items = is_array($data) ? ($data['items'] ?? null) : null;
        if (!is_array($data)
            || (int) ($data['page'] ?? 0) !== $page
            || (int) ($data['per_page'] ?? 0) !== $perPage
            || !is_array($items)
            || count($items) > $perPage) {
            return self::failure('schedule_history_unavailable', 502);
        }

        $validWeekdays = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'];
        $normalizedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                return self::failure('schedule_history_unavailable', 502);
            }

            $assignmentId = self::positiveInt($item['id'] ?? null);
            $itemWorkerId = self::positiveInt($item['id_personal'] ?? null);
            $itemWorkplaceId = self::positiveInt($item['id_estacion'] ?? null);
            $templateId = ($item['id_plantilla'] ?? null) === null
                ? null
                : self::positiveInt($item['id_plantilla']);
            $weekday = strtoupper(trim((string) ($item['dia_semana'] ?? '')));
            $validFrom = (string) ($item['valido_desde'] ?? '');
            $validToValue = $item['valido_hasta'] ?? null;
            $validTo = $validToValue === null ? null : (string) $validToValue;
            $active = filter_var($item['es_activo'] ?? null, FILTER_VALIDATE_INT);

            if ($assignmentId === null
                || $itemWorkerId !== $workerId
                || $itemWorkplaceId !== $workplaceId
                || (($item['id_plantilla'] ?? null) !== null && $templateId === null)
                || !in_array($weekday, $validWeekdays, true)
                || !self::isCivilDate($validFrom)
                || ($validTo !== null && (!self::isCivilDate($validTo) || $validTo <= $validFrom))
                || !in_array($active, [0, 1], true)) {
                return self::failure('schedule_history_unavailable', 502);
            }

            $registeredAt = $item['registrado_at_utc'] ?? null;
            if ($registeredAt !== null && !is_string($registeredAt)) {
                return self::failure('schedule_history_unavailable', 502);
            }

            $normalizedItems[] = [
                'id' => $assignmentId,
                'id_personal' => $itemWorkerId,
                'id_estacion' => $itemWorkplaceId,
                'id_plantilla' => $templateId,
                'tipo_asignacion' => strtoupper(trim((string) ($item['tipo_asignacion'] ?? 'HABITUAL'))),
                'dia_semana' => $weekday,
                'valido_desde' => $validFrom,
                'valido_hasta' => $validTo,
                'es_activo' => $active,
                'registrado_at_utc' => $registeredAt,
            ];
        }

        $normalized = [
            'ok' => true,
            'data' => [
                'page' => $page,
                'per_page' => $perPage,
                'items' => $normalizedItems,
            ],
        ];
        if ($snapshotMode) {
            $pagination = $data['pagination'] ?? null;
            $totalRecords = is_array($pagination) ? filter_var($pagination['total_records'] ?? null, FILTER_VALIDATE_INT) : false;
            $totalPages = is_array($pagination) ? filter_var($pagination['total_pages'] ?? null, FILTER_VALIDATE_INT) : false;
            $hasMore = is_array($pagination) ? filter_var($pagination['has_more'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
            $token = is_array($pagination) ? (string) ($pagination['read_snapshot'] ?? '') : '';
            $expiresAt = is_array($pagination) ? (string) ($pagination['expires_at_utc'] ?? '') : '';
            if ($totalRecords === false || $totalRecords < 0 || $totalPages === false || $totalPages < 0
                || $totalPages > 50 || $hasMore === null
                || preg_match('/\A[A-Za-z0-9_-]{43}\z/D', $token) !== 1
                || !self::isUtcTimestamp($expiresAt)) {
                return self::failure('schedule_history_unavailable', 502);
            }
            $expectedPages = $totalRecords === 0 ? 0 : (int) ceil($totalRecords / $perPage);
            $expectedCount = $totalRecords === 0 ? 0 : min($perPage, max(0, $totalRecords - (($page - 1) * $perPage)));
            if ($totalPages !== $expectedPages || count($normalizedItems) !== $expectedCount
                || $hasMore !== ($page < $totalPages) || ($totalPages === 0 && $page !== 1)
                || ($totalPages > 0 && $page > $totalPages)) {
                return self::failure('schedule_history_unavailable', 502);
            }
            $normalized['data']['pagination'] = [
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'has_more' => $hasMore,
                'read_snapshot' => $token,
                'expires_at_utc' => $expiresAt,
            ];
        }
        return $normalized;
    }

    /**
     * Read one worker's complete assignment set from one API-owned immutable snapshot.
     * Snapshot tokens stay inside this server-side service and are never returned to a view.
     *
     * @return array{ok:bool,data?:array{items:list<array<string,mixed>>,complete:bool},error?:array{kind:string,status:int}}
     */
    public function getAllShiftAssignmentsSnapshot(Usuario $user, int $workerId, int $workplaceId, ?string $referenceDate = null, int $perPage = 200): array
    {
        if ($workerId < 1 || $workplaceId < 1 || $perPage < 1 || $perPage > 200
            || ($referenceDate !== null && !self::isCivilDate($referenceDate))) {
            return self::failure('bad_request', 400);
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $items = [];
            $seenIds = [];
            $snapshotToken = null;
            $expectedPages = null;
            $expectedTotal = null;
            $expectedExpiry = null;
            $failed = null;
            for ($page = 1; $page <= 50; $page++) {
                $response = $this->getShiftAssignments($user, $workerId, $workplaceId, $page, $perPage, $referenceDate, true, $snapshotToken);
                if (($response['ok'] ?? false) !== true) {
                    $failed = $response;
                    break;
                }
                $data = $response['data'];
                $pagination = $data['pagination'] ?? null;
                if (!is_array($pagination)) {
                    $failed = self::failure('schedule_history_unavailable', 502);
                    break;
                }
                if ($snapshotToken === null) {
                    $snapshotToken = (string) $pagination['read_snapshot'];
                    $expectedPages = (int) $pagination['total_pages'];
                    $expectedTotal = (int) $pagination['total_records'];
                    $expectedExpiry = (string) $pagination['expires_at_utc'];
                } elseif (!hash_equals($snapshotToken, (string) $pagination['read_snapshot'])
                    || $expectedPages !== (int) $pagination['total_pages']
                    || $expectedTotal !== (int) $pagination['total_records']
                    || $expectedExpiry !== (string) $pagination['expires_at_utc']) {
                    $failed = self::failure('schedule_history_unavailable', 502);
                    break;
                }
                foreach ($data['items'] as $item) {
                    $id = (int) $item['id'];
                    if (isset($seenIds[$id])) {
                        $failed = self::failure('schedule_history_unavailable', 502);
                        break 2;
                    }
                    $seenIds[$id] = true;
                    $items[] = $item;
                }
                if ($expectedTotal === 0) {
                    if ($page !== 1 || $data['items'] !== [] || $pagination['has_more'] !== false) {
                        $failed = self::failure('schedule_history_unavailable', 502);
                    }
                    break;
                }
                if ($page > $expectedPages || (bool) $pagination['has_more'] !== ($page < $expectedPages)) {
                    $failed = self::failure('schedule_history_unavailable', 502);
                    break;
                }
                if ($page === $expectedPages) {
                    break;
                }
            }
            if ($failed !== null) {
                // An expired/invalidated snapshot may be restarted once; no results from it survive.
                if ($attempt === 0 && (int) ($failed['error']['status'] ?? 0) === 409) {
                    continue;
                }
                return $failed;
            }
            if ($expectedPages === null || $expectedTotal === null || count($items) !== $expectedTotal
                || count($seenIds) !== $expectedTotal || ($expectedPages === 0 && $items !== [])) {
                return self::failure('schedule_history_unavailable', 502);
            }
            return ['ok' => true, 'data' => ['items' => $items, 'complete' => true]];
        }
        return self::failure('schedule_history_unavailable', 502);
    }

    /** @return array{ok:bool,data?:array{page:int,per_page:int,items:list<array<string,mixed>>},error?:array{kind:string,status:int}} */
    public function getShiftTemplates(Usuario $user, int $page = 1, int $perPage = 100, ?int $workplaceId = null): array
    {
        if ($page < 1 || $page > 10000 || $perPage < 1 || $perPage > 200) return self::failure('bad_request', 400);
        $queryParams = ['page' => $page, 'per_page' => $perPage];
        if ($workplaceId !== null && $workplaceId > 0) {
            $queryParams['station_id'] = $workplaceId;
        }
        $response = $this->request($user, 'GET', 'api/v2/shift-templates', $queryParams);
        if (!$response['ok']) return $response;
        $data = $response['payload']['data'] ?? null;
        $items = is_array($data) ? ($data['items'] ?? null) : null;
        if (!is_array($data) || (int) ($data['page'] ?? 0) !== $page || (int) ($data['per_page'] ?? 0) !== $perPage
            || !is_array($items) || count($items) > $perPage) return self::failure('schedule_templates_unavailable', 502);
        $normalized = [];
        foreach ($items as $item) {
            $id = is_array($item) ? self::positiveInt($item['id'] ?? null) : null;
            $version = is_array($item) ? self::positiveInt($item['version'] ?? null) : null;
            $key = is_array($item) ? trim((string) ($item['clave'] ?? '')) : '';
            $name = is_array($item) ? trim((string) ($item['nombre'] ?? '')) : '';
            $active = is_array($item) ? filter_var($item['es_activo'] ?? null, FILTER_VALIDATE_INT) : false;
            $scope = is_array($item) ? strtoupper(trim((string) ($item['scope'] ?? 'GLOBAL'))) : 'GLOBAL';
            $stationId = is_array($item) && isset($item['id_estacion']) && $item['id_estacion'] !== null ? (int) $item['id_estacion'] : null;
            $lineage = is_array($item['derived_from_global'] ?? null) ? $item['derived_from_global'] : null;
            $customization = is_array($item['station_customization'] ?? null) ? $item['station_customization'] : null;
            if ($id === null || $version === null || $key === '' || strlen($key) > 100 || $name === '' || !in_array($active, [0, 1], true)) {
                return self::failure('schedule_templates_unavailable', 502);
            }
            $normalized[] = ['id' => $id, 'clave' => $key, 'version' => $version, 'nombre' => $name,
                'tipo_turno' => (string) ($item['tipo_turno'] ?? ''), 'scope' => $scope, 'id_estacion' => $stationId, 'es_activo' => (int) $active,
                'derived_from_global' => $lineage === null ? null : ['clave' => trim((string) ($lineage['clave'] ?? '')), 'version' => self::positiveInt($lineage['version'] ?? null)],
                'station_customization' => $customization === null ? null : ['clave' => trim((string) ($customization['clave'] ?? '')), 'source_version' => self::positiveInt($customization['source_version'] ?? null)]];
        }
        return ['ok' => true, 'data' => ['page' => $page, 'per_page' => $perPage, 'items' => $normalized]];
    }

    /** Create or safely recover the existing station copy of a visible global template. */
    public function customizeGlobalShiftTemplate(Usuario $user, array $source, array $template, string $idempotencyKey, int $workplaceId): array
    {
        $sourceKey = trim((string) ($source['clave'] ?? ''));
        $sourceVersion = self::positiveInt($source['version'] ?? null);
        $name = trim((string) ($template['nombre'] ?? ''));
        $type = strtoupper(trim((string) ($template['tipo_turno'] ?? '')));
        $rawSegments = $template['segmentos'] ?? null;
        if (preg_match('/\\A[A-Za-z0-9_-]{1,100}\\z/', $sourceKey) !== 1 || $sourceVersion === null || $workplaceId < 1
            || $name === '' || self::unicodeLength($name) > 128 || !in_array($type, ['REGULAR', 'NOCTURNO', 'MIXTO', 'ROTATIVO'], true)
            || !is_array($rawSegments) || !array_is_list($rawSegments) || $rawSegments === [] || count($rawSegments) > 255
            || trim($idempotencyKey) === '') return self::failure('bad_request', 400);
        $segments = [];
        foreach ($rawSegments as $index => $segment) {
            if (!is_array($segment) || (int) ($segment['ordinal'] ?? 0) !== $index + 1) return self::failure('bad_request', 400);
            $segmentType = strtoupper(trim((string) ($segment['tipo_segmento'] ?? '')));
            $start = (string) ($segment['hora_inicio_local'] ?? ''); $end = (string) ($segment['hora_fin_local'] ?? '');
            $startOffset = filter_var($segment['dia_offset_inicio'] ?? null, FILTER_VALIDATE_INT); $endOffset = filter_var($segment['dia_offset_fin'] ?? null, FILTER_VALIDATE_INT);
            $normalize = static function (string $time): ?string {
                if (preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\\z/', $time) !== 1) return null;
                return strlen($time) === 5 ? $time . ':00' : $time;
            };
            $start = $normalize($start); $end = $normalize($end);
            if (!in_array($segmentType, ['WORK', 'BREAK'], true) || $start === null || $end === null
                || !in_array($startOffset, [0, 1], true) || !in_array($endOffset, [0, 1], true)) return self::failure('bad_request', 400);
            $segments[] = ['ordinal' => $index + 1, 'tipo_segmento' => $segmentType, 'hora_inicio_local' => $start,
                'hora_fin_local' => $end, 'dia_offset_inicio' => $startOffset, 'dia_offset_fin' => $endOffset];
        }
        $response = $this->requestJson($user, 'POST', 'api/v2/shift-templates', [
            'scope' => 'STATION', 'derived_from_global' => ['clave' => $sourceKey, 'version' => $sourceVersion],
            'nombre' => $name, 'tipo_turno' => $type, 'segmentos' => $segments,
        ], $idempotencyKey, $workplaceId);
        if (!($response['ok'] ?? false)) return $response;
        $data = $response['payload']['data'] ?? null;
        if (!is_array($data) || ($response['payload']['status'] ?? null) !== 'success'
            || (string) ($data['scope'] ?? '') !== 'STATION' || (int) ($data['id_estacion'] ?? 0) !== $workplaceId
            || (int) ($data['version'] ?? 0) < 1 || (string) ($data['derived_from_global']['clave'] ?? '') !== $sourceKey
            || (int) ($data['derived_from_global']['version'] ?? 0) !== $sourceVersion) return self::failure('schedule_template_readback_unconfirmed', 502);
        $localKey = (string) ($data['clave'] ?? ''); $localVersion = (int) $data['version'];
        $detail = $this->getShiftTemplateVersion($user, $localKey, $localVersion, $workplaceId);
        if (!($detail['ok'] ?? false) || (int) ($detail['data']['id'] ?? 0) !== (int) ($data['id'] ?? 0)
            || (string) ($detail['data']['derived_from_global']['clave'] ?? '') !== $sourceKey
            || (int) ($detail['data']['derived_from_global']['version'] ?? 0) !== $sourceVersion
            || (string) ($detail['data']['nombre'] ?? '') !== $name || ($detail['data']['segmentos'] ?? null) !== $segments) {
            return self::failure('schedule_template_readback_unconfirmed', 502);
        }
        return ['ok' => true, 'created' => (int) ($response['http_status'] ?? 0) === 201, 'data' => $detail['data']];
    }

    /** Create the next immutable version of an existing station template and confirm it by readback. */
    public function createShiftTemplateVersion(Usuario $user, string $templateKey, int $expectedVersion, array $template, int $workplaceId): array
    {
        $templateKey = trim($templateKey);
        $name = trim((string) ($template['nombre'] ?? ''));
        $type = strtoupper(trim((string) ($template['tipo_turno'] ?? '')));
        $rawSegments = $template['segmentos'] ?? null;
        if (preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $templateKey) !== 1 || $expectedVersion < 1 || $workplaceId < 1
            || $name === '' || self::unicodeLength($name) > 128
            || !in_array($type, ['REGULAR', 'NOCTURNO', 'MIXTO', 'ROTATIVO'], true)
            || !is_array($rawSegments) || !array_is_list($rawSegments) || $rawSegments === [] || count($rawSegments) > 255) {
            return self::failure('bad_request', 400);
        }

        $segments = [];
        foreach ($rawSegments as $index => $segment) {
            if (!is_array($segment) || (int) ($segment['ordinal'] ?? 0) !== $index + 1) return self::failure('bad_request', 400);
            $segmentType = strtoupper(trim((string) ($segment['tipo_segmento'] ?? '')));
            $start = (string) ($segment['hora_inicio_local'] ?? '');
            $end = (string) ($segment['hora_fin_local'] ?? '');
            $startOffset = filter_var($segment['dia_offset_inicio'] ?? null, FILTER_VALIDATE_INT);
            $endOffset = filter_var($segment['dia_offset_fin'] ?? null, FILTER_VALIDATE_INT);
            $normalizeTime = static function (string $value): ?string {
                if (preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/', $value) !== 1) return null;
                return strlen($value) === 5 ? $value . ':00' : $value;
            };
            $startNormalized = $normalizeTime($start);
            $endNormalized = $normalizeTime($end);
            if (!in_array($segmentType, ['WORK', 'BREAK'], true) || $startNormalized === null || $endNormalized === null
                || !in_array($startOffset, [0, 1], true) || !in_array($endOffset, [0, 1], true)) return self::failure('bad_request', 400);
            $startSecond = $startOffset * 86400 + (int) substr($startNormalized, 0, 2) * 3600 + (int) substr($startNormalized, 3, 2) * 60 + (int) substr($startNormalized, 6, 2);
            $endSecond = $endOffset * 86400 + (int) substr($endNormalized, 0, 2) * 3600 + (int) substr($endNormalized, 3, 2) * 60 + (int) substr($endNormalized, 6, 2);
            if ($endSecond <= $startSecond) return self::failure('bad_request', 400);
            $segments[] = ['ordinal' => $index + 1, 'tipo_segmento' => $segmentType,
                'hora_inicio_local' => $startNormalized, 'hora_fin_local' => $endNormalized,
                'dia_offset_inicio' => $startOffset, 'dia_offset_fin' => $endOffset];
        }

        $path = 'api/v2/shift-templates/' . rawurlencode($templateKey) . '/versions';
        $response = $this->requestJson($user, 'POST', $path, [
            'nombre' => $name, 'tipo_turno' => $type, 'segmentos' => $segments,
            'scope' => 'STATION', 'id_estacion' => $workplaceId,
        ], null, $workplaceId);
        if (!($response['ok'] ?? false)) return $response;
        $data = $response['payload']['data'] ?? null;
        $newVersion = $expectedVersion + 1;
        if ((int) ($response['http_status'] ?? 0) !== 201 || ($response['payload']['status'] ?? null) !== 'success'
            || !is_array($data) || self::positiveInt($data['id'] ?? null) === null
            || (string) ($data['clave'] ?? '') !== $templateKey || (int) ($data['version'] ?? 0) !== $newVersion
            || (string) ($data['nombre'] ?? '') !== $name || (string) ($data['tipo_turno'] ?? '') !== $type
            || strtoupper((string) ($data['scope'] ?? '')) !== 'STATION' || (int) ($data['id_estacion'] ?? 0) !== $workplaceId
            || (int) ($data['es_activo'] ?? 0) !== 1 || !self::segmentsMatchExpected($data['segmentos'] ?? null, $segments)) {
            return self::failure('schedule_template_readback_unconfirmed', 502);
        }

        $detail = $this->getShiftTemplateVersion($user, $templateKey, $newVersion, $workplaceId);
        if (!($detail['ok'] ?? false) || (int) ($detail['data']['id'] ?? 0) !== (int) $data['id']
            || (string) ($detail['data']['nombre'] ?? '') !== $name || (string) ($detail['data']['tipo_turno'] ?? '') !== $type
            || (int) ($detail['data']['es_activo'] ?? 0) !== 1 || ($detail['data']['segmentos'] ?? null) !== $segments) {
            return self::failure('schedule_template_readback_unconfirmed', 502);
        }
        $catalog = $this->getShiftTemplates($user, 1, 200, $workplaceId);
        if (!($catalog['ok'] ?? false)) return self::failure('schedule_template_readback_unconfirmed', 502);
        $latest = null;
        foreach ($catalog['data']['items'] as $item) if ((string) $item['clave'] === $templateKey) { $latest = $item; break; }
        if ($latest === null || (int) $latest['version'] !== $newVersion || $latest['scope'] !== 'STATION'
            || (int) ($latest['id_estacion'] ?? 0) !== $workplaceId || (int) $latest['es_activo'] !== 1
            || (int) $latest['id'] !== (int) $data['id'] || $latest['nombre'] !== $name) {
            return self::failure('schedule_template_readback_unconfirmed', 502);
        }
        return ['ok' => true, 'data' => array_merge($detail['data'], ['scope' => 'STATION', 'id_estacion' => $workplaceId])];
    }

    /** Create a template through the existing API contract and require detail readback. */
    public function createShiftTemplate(Usuario $user, array $template, string $idempotencyKey, ?int $workplaceId = null): array
    {
        $key = trim((string) ($template['clave'] ?? ''));
        $name = trim((string) ($template['nombre'] ?? ''));
        $type = strtoupper(trim((string) ($template['tipo_turno'] ?? '')));
        $rawSegments = $template['segmentos'] ?? null;
        if ($key === '' || strlen($key) > 64 || str_contains($key, '/')
            || preg_match('/[\\x00-\\x1F\\x7F]/', $key) === 1
            || $name === '' || self::unicodeLength($name) > 128
            || !in_array($type, ['REGULAR', 'NOCTURNO', 'MIXTO', 'ROTATIVO'], true)
            || !is_array($rawSegments) || !array_is_list($rawSegments) || $rawSegments === [] || count($rawSegments) > 255
            || trim($idempotencyKey) === '') {
            return self::failure('bad_request', 400);
        }

        $segments = [];
        foreach ($rawSegments as $index => $segment) {
            if (!is_array($segment) || (int) ($segment['ordinal'] ?? 0) !== $index + 1) {
                return self::failure('bad_request', 400);
            }
            $segmentType = strtoupper(trim((string) ($segment['tipo_segmento'] ?? '')));
            $start = (string) ($segment['hora_inicio_local'] ?? '');
            $end = (string) ($segment['hora_fin_local'] ?? '');
            $startOffset = filter_var($segment['dia_offset_inicio'] ?? null, FILTER_VALIDATE_INT);
            $endOffset = filter_var($segment['dia_offset_fin'] ?? null, FILTER_VALIDATE_INT);
            $normalizeTime = static function (string $value): ?string {
                if (preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\\z/', $value) !== 1) return null;
                return strlen($value) === 5 ? $value . ':00' : $value;
            };
            $startNormalized = $normalizeTime($start);
            $endNormalized = $normalizeTime($end);
            if (!in_array($segmentType, ['WORK', 'BREAK'], true)
                || $startNormalized === null || $endNormalized === null
                || !in_array($startOffset, [0, 1], true) || !in_array($endOffset, [0, 1], true)) {
                return self::failure('bad_request', 400);
            }
            $startSecond = $startOffset * 86400 + ((int) substr($startNormalized, 0, 2) * 3600)
                + ((int) substr($startNormalized, 3, 2) * 60) + (strlen($startNormalized) > 5 ? (int) substr($startNormalized, 6, 2) : 0);
            $endSecond = $endOffset * 86400 + ((int) substr($endNormalized, 0, 2) * 3600)
                + ((int) substr($endNormalized, 3, 2) * 60) + (strlen($endNormalized) > 5 ? (int) substr($endNormalized, 6, 2) : 0);
            if ($endSecond <= $startSecond) return self::failure('bad_request', 400);
            $segments[] = [
                'ordinal' => $index + 1,
                'tipo_segmento' => $segmentType,
                'hora_inicio_local' => $startNormalized,
                'hora_fin_local' => $endNormalized,
                'dia_offset_inicio' => $startOffset,
                'dia_offset_fin' => $endOffset,
            ];
        }

        $payload = ['clave' => $key, 'nombre' => $name, 'tipo_turno' => $type, 'segmentos' => $segments];
        if ($workplaceId !== null && $workplaceId > 0) {
            $payload['scope'] = 'STATION';
            $payload['id_estacion'] = $workplaceId;
        }
        $response = $this->requestJson($user, 'POST', 'api/v2/shift-templates', $payload, $idempotencyKey, $workplaceId);
        if (!$response['ok']) return $response;
        $data = $response['payload']['data'] ?? null;
        $templateId = is_array($data) ? self::positiveInt($data['id'] ?? null) : null;
        if (($response['payload']['status'] ?? null) !== 'success'
            || !is_array($data) || $templateId === null
            || (string) ($data['clave'] ?? '') !== $key || (int) ($data['version'] ?? 0) !== 1
            || (string) ($data['nombre'] ?? '') !== $name || (string) ($data['tipo_turno'] ?? '') !== $type
            || (int) ($data['es_activo'] ?? 0) !== 1
            || ($workplaceId !== null && ((string) ($data['scope'] ?? '') !== 'STATION'
                || (int) ($data['id_estacion'] ?? 0) !== $workplaceId))) {
            return self::failure('schedule_templates_unavailable', 502);
        }

        $readback = $this->getShiftTemplateVersion($user, $key, 1, $workplaceId);
        if (!($readback['ok'] ?? false)
            || (int) ($readback['data']['id'] ?? 0) !== $templateId
            || (string) ($readback['data']['nombre'] ?? '') !== $name
            || (string) ($readback['data']['tipo_turno'] ?? '') !== $type
            || (int) ($readback['data']['es_activo'] ?? 0) !== 1
            || ($workplaceId !== null && ((string) ($readback['data']['scope'] ?? '') !== 'STATION'
                || (int) ($readback['data']['id_estacion'] ?? 0) !== $workplaceId))
            || ($readback['data']['segmentos'] ?? null) !== $segments) {
            return self::failure('schedule_template_readback_unconfirmed', 502);
        }

        return ['ok' => true, 'data' => $readback['data']];
    }

    /** @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}} */
    public function getShiftTemplateVersion(Usuario $user, string $templateKey, int $version, ?int $workplaceId = null): array
    {
        if ($templateKey === '' || strlen($templateKey) > 100 || str_contains($templateKey, '/') || $version < 1) return self::failure('bad_request', 400);
        $query = $workplaceId !== null && $workplaceId > 0 ? ['station_id' => $workplaceId] : [];
        $response = $this->request($user, 'GET', 'api/v2/shift-templates/' . rawurlencode($templateKey) . '/versions/' . $version, $query);
        if (!$response['ok']) return $response;
        $data = $response['payload']['data'] ?? null;
        $segments = is_array($data) ? ($data['segmentos'] ?? null) : null;
        if (!is_array($data) || (int) ($data['version'] ?? 0) !== $version || (string) ($data['clave'] ?? '') !== $templateKey || !is_array($segments)) {
            return self::failure('schedule_templates_unavailable', 502);
        }
        $normalizedSegments = [];
        foreach ($segments as $segment) {
            if (!is_array($segment) || self::positiveInt($segment['ordinal'] ?? null) === null
                || !is_string($segment['tipo_segmento'] ?? null)
                || preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\\z/', (string) ($segment['hora_inicio_local'] ?? '')) !== 1
                || preg_match('/\\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\\z/', (string) ($segment['hora_fin_local'] ?? '')) !== 1
                || !in_array((int) ($segment['dia_offset_inicio'] ?? -1), [0, 1], true)
                || !in_array((int) ($segment['dia_offset_fin'] ?? -1), [0, 1], true)) return self::failure('schedule_templates_unavailable', 502);
            $normalizedSegments[] = ['ordinal' => (int) $segment['ordinal'], 'tipo_segmento' => (string) $segment['tipo_segmento'],
                'hora_inicio_local' => (string) $segment['hora_inicio_local'], 'hora_fin_local' => (string) $segment['hora_fin_local'],
                'dia_offset_inicio' => (int) $segment['dia_offset_inicio'], 'dia_offset_fin' => (int) $segment['dia_offset_fin']];
        }
        return ['ok' => true, 'data' => ['id' => self::positiveInt($data['id'] ?? null), 'clave' => $templateKey,
            'version' => $version, 'nombre' => (string) ($data['nombre'] ?? ''), 'tipo_turno' => (string) ($data['tipo_turno'] ?? ''),
            'es_activo' => (int) ($data['es_activo'] ?? 0), 'scope' => strtoupper((string) ($data['scope'] ?? '')),
            'id_estacion' => isset($data['id_estacion']) ? (int) $data['id_estacion'] : null,
            'derived_from_global' => is_array($data['derived_from_global'] ?? null) ? ['clave' => trim((string) $data['derived_from_global']['clave']),
                'version' => self::positiveInt($data['derived_from_global']['version'] ?? null)] : null,
            'segmentos' => $normalizedSegments]];
    }

    /** Caller must verify the write by canonical GET readback before presenting success. */
    public function createShiftAssignment(Usuario $user, array $assignment, string $idempotencyKey): array
    {
        $workerId = self::positiveInt($assignment['id_personal'] ?? null);
        $workplaceId = self::positiveInt($assignment['id_estacion'] ?? null);
        $templateId = ($assignment['id_plantilla'] ?? null) === null ? null : self::positiveInt($assignment['id_plantilla']);
        $weekday = strtoupper(trim((string) ($assignment['dia_semana'] ?? '')));
        $validFrom = (string) ($assignment['valido_desde'] ?? '');
        $validTo = $assignment['valido_hasta'] ?? null;
        $tipoAsignacion = strtoupper(trim((string) ($assignment['tipo_asignacion'] ?? 'HABITUAL')));
        if (!in_array($tipoAsignacion, ['HABITUAL', 'TEMPORAL'], true)) $tipoAsignacion = 'HABITUAL';
        if ($workerId === null || $workplaceId === null || (($assignment['id_plantilla'] ?? null) !== null && $templateId === null)
            || !in_array($weekday, ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'DOMINGO'], true)
            || !self::isCivilDate($validFrom) || ($validTo !== null && (!is_string($validTo) || !self::isCivilDate($validTo) || $validTo <= $validFrom))
            || ($tipoAsignacion === 'TEMPORAL' && $validTo === null)
            || trim($idempotencyKey) === '') return self::failure('bad_request', 400);
        $body = ['id_personal' => $workerId, 'id_estacion' => $workplaceId, 'id_plantilla' => $templateId,
            'tipo_asignacion' => $tipoAsignacion,
            'dia_semana' => $weekday, 'valido_desde' => $validFrom, 'valido_hasta' => $validTo, 'es_activo' => 1];
        $response = $this->requestJson($user, 'POST', 'api/v2/shift-assignments', $body, $idempotencyKey, $workplaceId);
        if (!$response['ok']) return $response;
        $data = $response['payload']['data'] ?? null;
        $httpStatus = (int) ($response['http_status'] ?? 0);
        if (!is_array($data) || self::positiveInt($data['id'] ?? null) === null
            || $httpStatus !== 201
            || (int) ($data['id_personal'] ?? 0) !== $workerId || (int) ($data['id_estacion'] ?? 0) !== $workplaceId
            || (($data['id_plantilla'] ?? null) !== null && (int) $data['id_plantilla'] !== $templateId)
            || (string) ($data['dia_semana'] ?? '') !== $weekday || (string) ($data['valido_desde'] ?? '') !== $validFrom
            || (($data['valido_hasta'] ?? null) !== $validTo)
            || (string) ($data['tipo_asignacion'] ?? 'HABITUAL') !== $tipoAsignacion
            || !is_string($data['creado_at'] ?? null)
            || preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $data['creado_at']) !== 1
            || !is_string($data['registrado_at_utc'] ?? null)
            || preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}$/', $data['registrado_at_utc']) !== 1) {
            return self::failure('schedule_assignment_unavailable', 502);
        }
        return ['ok' => true, 'http_status' => $httpStatus, 'data' => $data];
    }

    /** Close one assignment with an expected version and validate the API response. */
    public function closeShiftAssignment(
        Usuario $user,
        int $assignmentId,
        int $workplaceId,
        string $effectiveCloseDate,
        string $expectedValidFrom,
        ?string $expectedValidTo,
        string $idempotencyKey
    ): array {
        if ($assignmentId < 1 || $workplaceId < 1 || !self::isCivilDate($effectiveCloseDate)
            || !self::isCivilDate($expectedValidFrom)
            || ($expectedValidTo !== null && !self::isCivilDate($expectedValidTo))
            || trim($idempotencyKey) === '') {
            return self::failure('bad_request', 400);
        }
        $response = $this->requestJson(
            $user,
            'POST',
            'api/v2/shift-assignments/' . $assignmentId . '/close',
            [
                'effective_close_date' => $effectiveCloseDate,
                'expected_valido_desde' => $expectedValidFrom,
                'expected_valido_hasta' => $expectedValidTo,
            ],
            $idempotencyKey,
            $workplaceId
        );
        if (!$response['ok']) return $response;
        $data = $response['payload']['data'] ?? null;
        if (!is_array($data)
            || self::positiveInt($data['id'] ?? null) !== $assignmentId
            || (int) ($data['id_estacion'] ?? 0) !== $workplaceId
            || (string) ($data['valido_desde'] ?? '') !== $expectedValidFrom
            || (string) ($data['valido_hasta'] ?? '') !== $effectiveCloseDate
            || (int) ($data['es_activo'] ?? -1) !== 0) {
            return self::failure('schedule_assignment_readback_unconfirmed', 502);
        }
        return ['ok' => true, 'data' => $data];
    }

    /** Close and replace one assignment atomically, then validate both returned versions. */
    public function replaceShiftAssignment(
        Usuario $user,
        int $assignmentId,
        int $workplaceId,
        string $effectiveCloseDate,
        string $expectedValidFrom,
        ?string $expectedValidTo,
        ?int $replacementTemplateId,
        ?string $replacementValidTo,
        string $idempotencyKey
    ): array {
        if ($assignmentId < 1 || $workplaceId < 1 || !self::isCivilDate($effectiveCloseDate)
            || !self::isCivilDate($expectedValidFrom)
            || ($expectedValidTo !== null && !self::isCivilDate($expectedValidTo))
            || ($replacementValidTo !== null && !self::isCivilDate($replacementValidTo))
            || ($replacementValidTo !== null && $replacementValidTo <= $effectiveCloseDate)
            || ($replacementTemplateId !== null && $replacementTemplateId < 1)
            || trim($idempotencyKey) === '') {
            return self::failure('bad_request', 400);
        }
        $response = $this->requestJson(
            $user,
            'POST',
            'api/v2/shift-assignments/' . $assignmentId . '/replace',
            [
                'effective_close_date' => $effectiveCloseDate,
                'expected_valido_desde' => $expectedValidFrom,
                'expected_valido_hasta' => $expectedValidTo,
                'id_plantilla' => $replacementTemplateId,
                'valido_hasta' => $replacementValidTo,
            ],
            $idempotencyKey,
            $workplaceId
        );
        if (!$response['ok']) return $response;
        $data = $response['payload']['data'] ?? null;
        $closed = is_array($data) ? ($data['closed'] ?? null) : null;
        $replacement = is_array($data) ? ($data['replacement'] ?? null) : null;
        $idempotent = (bool) ($data['idempotent'] ?? false);
        $audit = is_array($data) ? ($data['audit'] ?? null) : null;
        $actorId = (int) ($user->id ?? 0);
        $replacementAudit = is_array($audit) ? ($audit['replace'] ?? null) : null;
        $auditConfirmed = $idempotent || (is_array($replacementAudit)
            && self::positiveInt($replacementAudit['id'] ?? null) !== null
            && (string) ($replacementAudit['operation'] ?? '') === 'REPLACE'
            && (int) ($replacementAudit['actor_user_id'] ?? 0) === $actorId
            && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($replacementAudit['recorded_at_utc'] ?? '')) === 1);
        if (!is_array($closed) || !is_array($replacement)
            || self::positiveInt($closed['id'] ?? null) !== $assignmentId
            || (int) ($closed['id_estacion'] ?? 0) !== $workplaceId
            || (string) ($closed['valido_desde'] ?? '') !== $expectedValidFrom
            || (string) ($closed['valido_hasta'] ?? '') !== $effectiveCloseDate
            || (int) ($closed['es_activo'] ?? -1) !== 0
            || (int) ($replacement['id_personal'] ?? 0) < 1
            || (int) ($replacement['id_estacion'] ?? 0) !== $workplaceId
            || (($replacement['id_plantilla'] ?? null) !== $replacementTemplateId)
            || (string) ($replacement['valido_desde'] ?? '') !== $effectiveCloseDate
            || (($replacement['valido_hasta'] ?? null) !== $replacementValidTo)
            || (int) ($replacement['es_activo'] ?? -1) !== 1
            || (!$idempotent && (int) ($replacement['creado_por_usuario_id'] ?? 0) !== $actorId)
            || !$auditConfirmed) {
            return self::failure('schedule_assignment_readback_unconfirmed', 502);
        }
        return ['ok' => true, 'data' => ['closed' => $closed, 'replacement' => $replacement, 'audit' => $audit, 'idempotent' => $idempotent]];
    }

    /**
     * Cross-check API resolution provenance against the API's assignment
     * validity records. This does not resolve a schedule or choose a winner.
     *
     * @param list<array<string,mixed>> $assignmentsAtDate
     * @return array{status:string,weekday:string,assignment:?array<string,mixed>}
     */
    public static function reconcileEffectiveScheduleValidity(
        array $effectiveSchedule,
        array $assignmentsAtDate,
        int $workerId,
        int $workplaceId,
        string $date,
        bool $assignmentListTruncated = false
    ): array {
        $unknown = ['status' => 'unknown', 'weekday' => '', 'assignment' => null];
        if ($workerId < 1 || $workplaceId < 1 || !self::isCivilDate($date)
            || (int) ($effectiveSchedule['id_personal'] ?? 0) !== $workerId
            || (int) ($effectiveSchedule['id_estacion'] ?? 0) !== $workplaceId
            || (string) ($effectiveSchedule['fecha_local'] ?? '') !== $date) {
            return ['status' => 'inconsistent', 'weekday' => '', 'assignment' => null];
        }

        $weekdayNames = [1 => 'LUNES', 2 => 'MARTES', 3 => 'MIERCOLES', 4 => 'JUEVES', 5 => 'VIERNES', 6 => 'SABADO', 7 => 'DOMINGO'];
        try {
            $weekdayNumber = (int) (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format('N');
        } catch (\Throwable) {
            return $unknown;
        }
        $weekday = $weekdayNames[$weekdayNumber] ?? '';
        if ($weekday === '') {
            return $unknown;
        }

        $covering = [];
        foreach ($assignmentsAtDate as $assignment) {
            if (!is_array($assignment)
                || (string) ($assignment['dia_semana'] ?? '') !== $weekday
                || !self::isCivilDate((string) ($assignment['valido_desde'] ?? ''))
                || (string) $assignment['valido_desde'] > $date
                || (($assignment['valido_hasta'] ?? null) !== null
                    && ((string) $assignment['valido_hasta'] <= $date
                        || !self::isCivilDate((string) $assignment['valido_hasta'])))) {
                continue;
            }
            $covering[] = $assignment;
        }

        if (count($covering) > 1) {
            return ['status' => 'ambiguous', 'weekday' => $weekday, 'assignment' => null];
        }
        if ($assignmentListTruncated) {
            return ['status' => 'incomplete', 'weekday' => $weekday, 'assignment' => null];
        }

        $scheduleStatus = (string) ($effectiveSchedule['estado_jornada'] ?? '');
        if ($scheduleStatus === 'UNKNOWN') {
            return [
                'status' => $covering === [] ? 'unknown' : 'resolver_unknown_with_assignment',
                'weekday' => $weekday,
                'assignment' => $covering[0] ?? null,
            ];
        }

        if ($scheduleStatus === 'UNSCHEDULED') {
            return [
                'status' => $covering === [] ? 'no_assignment' : 'inconsistent',
                'weekday' => $weekday,
                'assignment' => $covering[0] ?? null,
            ];
        }

        if (!in_array($scheduleStatus, ['SCHEDULED', 'DAY_OFF'], true) || count($covering) !== 1) {
            return ['status' => 'inconsistent', 'weekday' => $weekday, 'assignment' => null];
        }

        $assignment = $covering[0];
        $origin = is_array($effectiveSchedule['origen'] ?? null) ? $effectiveSchedule['origen'] : [];
        $originAssignmentId = self::positiveInt($origin['id_asignacion'] ?? null);
        $templateMatches = $scheduleStatus === 'DAY_OFF'
            ? ($assignment['id_plantilla'] ?? null) === null
            : self::positiveInt($origin['id_plantilla'] ?? null) === self::positiveInt($assignment['id_plantilla'] ?? null);

        // es_activo describes current record status; a closed/inactive row may
        // still have been effective for a historical date inside its interval.
        if ($originAssignmentId !== self::positiveInt($assignment['id'] ?? null)
            || !$templateMatches) {
            return ['status' => 'inconsistent', 'weekday' => $weekday, 'assignment' => $assignment];
        }

        return ['status' => 'matched', 'weekday' => $weekday, 'assignment' => $assignment];
    }

    /**
     * Evaluate one canonical worker/day through the API-owned P1/P2 boundary.
     * Portal3 only validates the envelope and renders the authoritative result.
     *
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function evaluateJourney(
        Usuario $user,
        int $workerId,
        int $workplaceId,
        string $date,
        ?string $snapshotAuditRef = null
    ): array {
        if ($workerId < 1 || $workplaceId < 1 || !self::isCivilDate($date)) {
            return self::failure('bad_request', 400);
        }

        $body = [
            'id_personal' => $workerId,
            'id_estacion' => $workplaceId,
            'fecha_local' => $date,
        ];
        if ($snapshotAuditRef !== null && trim($snapshotAuditRef) !== '') {
            $body['snapshot_audit_ref'] = trim($snapshotAuditRef);
        }

        $response = $this->requestJson($user, 'POST', 'api/v2/journeys/evaluate', $body, null);
        if (!$response['ok']) {
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        if (!is_array($data)
            || !is_bool($data['evaluated'] ?? null)
            || !is_string($data['journey_status'] ?? null)
            || !is_array($data['context'] ?? null)
            || (int) ($data['context']['id_personal'] ?? 0) !== $workerId
            || (int) ($data['context']['id_estacion'] ?? 0) !== $workplaceId
            || (string) ($data['context']['fecha_local'] ?? '') !== $date
            || !self::isIanaTimezone($data['context']['timezone'] ?? null)
            || !is_array($data['schedule'] ?? null)) {
            return self::failure('journey_unavailable', 502);
        }

        $provenance = $data['provenance'] ?? null;
        $sourceEvidence = $data['source_evidence'] ?? null;
        if (!is_array($provenance) || !is_array($sourceEvidence)
            || !array_key_exists('response_generated_at_utc', $provenance)
            || !is_string($provenance['response_generated_at_utc'])
            || trim($provenance['response_generated_at_utc']) === ''
            || !array_key_exists('policy_rule_version', $provenance)
            || $provenance['policy_rule_version'] !== null
            || !is_string($provenance['source_completeness_contract_version'] ?? null)
            || !array_key_exists('level2_policy_ref', $provenance)) {
            return self::failure('journey_unavailable', 502);
        }

        $scheduleStatus = (string) ($data['schedule']['estado_jornada'] ?? '');
        if (!in_array($scheduleStatus, ['SCHEDULED', 'DAY_OFF', 'UNSCHEDULED', 'UNKNOWN'], true)) {
            return self::failure('journey_unavailable', 502);
        }

        if ($data['evaluated'] === false) {
            if (($data['pairing'] ?? null) !== null
                || ($data['calculation'] ?? null) !== null
                || !array_key_exists('evaluated_raw_event_count', $data) || $data['evaluated_raw_event_count'] !== null
                || !array_key_exists('evaluated_raw_events', $data) || $data['evaluated_raw_events'] !== null
                || !array_key_exists('p1_created_at_utc', $provenance) || $provenance['p1_created_at_utc'] !== null
                || !array_key_exists('p2_created_at_utc', $provenance) || $provenance['p2_created_at_utc'] !== null
                || !array_key_exists('shift_template_version', $provenance) || $provenance['shift_template_version'] !== null
                || !array_key_exists('level2_policy_ref', $provenance) || $provenance['level2_policy_ref'] !== null
                || !array_key_exists('evaluated_raw_event_count', $provenance) || $provenance['evaluated_raw_event_count'] !== null
                || !array_key_exists('evaluated_raw_events', $provenance) || $provenance['evaluated_raw_events'] !== null) {
                return self::failure('journey_unavailable', 502);
            }
        } else {
            $rawEvents = $data['evaluated_raw_events'] ?? null;
            $rawCount = $data['evaluated_raw_event_count'] ?? null;
            $provenanceEvents = $provenance['evaluated_raw_events'] ?? null;
            if (!is_int($rawCount) || $rawCount < 0 || !is_array($rawEvents)
                || $rawCount !== count($rawEvents)
                || ($provenance['evaluated_raw_event_count'] ?? null) !== $rawCount
                || !is_array($provenanceEvents)
                || $provenanceEvents !== $rawEvents) {
                return self::failure('journey_unavailable', 502);
            }

            $eventUuids = [];
            foreach ($rawEvents as $event) {
                $pairingRole = is_array($event) ? ($event['pairing_role'] ?? null) : null;
                $segmentOrdinal = is_array($event) ? ($event['segment_ordinal'] ?? null) : null;
                if (!is_array($event)
                    || !is_string($event['event_uuid'] ?? null)
                    || trim($event['event_uuid']) === ''
                    || !(is_int($event['id_dispositivo'] ?? null) || $event['id_dispositivo'] === null)
                    || !is_string($event['ocurrido_at_utc'] ?? null)
                    || trim($event['ocurrido_at_utc']) === ''
                    || !is_string($event['metodo_captura'] ?? null)
                    || trim($event['metodo_captura']) === ''
                    || !array_key_exists('pairing_role', $event)
                    || !is_string($pairingRole)
                    || !in_array($pairingRole, ['ENTRY', 'EXIT', 'SURPLUS', 'UNMATCHED'], true)
                    || !array_key_exists('segment_ordinal', $event)
                    || ($segmentOrdinal !== null && (!is_int($segmentOrdinal) || $segmentOrdinal < 1))
                    || ($pairingRole === 'UNMATCHED' && $segmentOrdinal !== null)
                    || ($pairingRole !== 'UNMATCHED' && $segmentOrdinal === null)) {
                    return self::failure('journey_unavailable', 502);
                }
                $eventUuid = trim((string) $event['event_uuid']);
                if (isset($eventUuids[$eventUuid])) {
                    return self::failure('journey_unavailable', 502);
                }
                $eventUuids[$eventUuid] = true;
            }

            $pairing = $data['pairing'] ?? null;
            $calculation = $data['calculation'] ?? null;
            if (!is_array($pairing) || !is_array($calculation)
                || !is_int($pairing['revision_numero'] ?? null)
                || !is_int($calculation['revision_numero'] ?? null)
                || !is_string($provenance['p1_algorithm_version'] ?? null)
                || !is_string($provenance['p2_algorithm_version'] ?? null)
                || !is_string($pairing['creado_at_utc'] ?? null)
                || !is_string($calculation['creado_at_utc'] ?? null)
                || ($provenance['p1_created_at_utc'] ?? null) !== $pairing['creado_at_utc']
                || ($provenance['p2_created_at_utc'] ?? null) !== $calculation['creado_at_utc']) {
                return self::failure('journey_unavailable', 502);
            }

            foreach (($pairing['segments'] ?? []) as $segment) {
                if (!is_array($segment)) return self::failure('journey_unavailable', 502);
                foreach (['entry_event_uuid', 'exit_event_uuid'] as $link) {
                    if (($segment[$link] ?? null) !== null
                        && (!is_string($segment[$link]) || !isset($eventUuids[$segment[$link]]))) {
                        return self::failure('journey_unavailable', 502);
                    }
                }
            }
        }

        return ['ok' => true, 'data' => $data];
    }

    /**
     * Read the API-owned administrative incidences for one worker/date range.
     * This method deliberately exposes no write or local applicability logic.
     *
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function readApplicableIncidences(
        Usuario $user,
        int $workerId,
        int $workplaceId,
        string $from,
        string $to
    ): array {
        if ($workerId < 1 || $workplaceId < 1 || !self::isCivilDate($from) || !self::isCivilDate($to) || $to < $from) {
            return self::failure('bad_request', 400);
        }

        $response = $this->request($user, 'GET', 'api/v2/incidences', [
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'from' => $from,
            'to' => $to,
        ]);
        if (!$response['ok']) {
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        $incidences = is_array($data) ? ($data['incidences'] ?? null) : null;
        $queryRange = is_array($data) ? ($data['query_range'] ?? null) : null;
        if (!is_array($data)
            || (int) ($data['worker_id'] ?? 0) !== $workerId
            || (int) ($data['workplace_id'] ?? 0) !== $workplaceId
            || !is_array($queryRange)
            || (string) ($queryRange['from'] ?? '') !== $from
            || (string) ($queryRange['to'] ?? '') !== $to
            || !is_int($data['total_records'] ?? null)
            || !is_array($incidences)
            || $data['total_records'] !== count($incidences)) {
            return self::failure('administrative_unavailable', 502);
        }

        $normalized = [];
        foreach ($incidences as $incidence) {
            $normalizedIncidence = self::normalizeIncidenceSummary($incidence, $workerId, $workplaceId);
            if ($normalizedIncidence === null) {
                return self::failure('administrative_unavailable', 502);
            }
            $normalized[] = $normalizedIncidence;
        }

        return ['ok' => true, 'data' => [
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'query_range' => ['from' => $from, 'to' => $to],
            'total_records' => count($normalized),
            'incidences' => $normalized,
        ]];
    }

    /** Read one paginated page of existing Journey calculations; this method never evaluates. */
    public function readPersistedJourneyPage(Usuario $user, array $query): array
    {
        $workplaceId = self::positiveInt($query['workplace_id'] ?? null);
        $from = is_string($query['from'] ?? null) ? trim($query['from']) : '';
        $to = is_string($query['to'] ?? null) ? trim($query['to']) : '';
        $page = self::positiveInt($query['page'] ?? null);
        $perPage = self::positiveInt($query['per_page'] ?? null);
        $workerRaw = $query['worker_id'] ?? null;
        $workerProvided = $workerRaw !== null && !(is_string($workerRaw) && trim($workerRaw) === '');
        $workerId = $workerProvided ? self::positiveInt($workerRaw) : null;
        if ($workplaceId === null || !self::isCivilDate($from) || !self::isCivilDate($to) || $to < $from
            || ($workerProvided && $workerId === null) || $page === null || $perPage === null || $perPage > 200) {
            return self::failure('bad_request', 400);
        }
        $normalized = [
            'workplace_id' => $workplaceId, 'from' => $from, 'to' => $to,
            'worker_id' => $workerId,
            'status' => is_string($query['status'] ?? null) && trim($query['status']) !== '' ? strtoupper(trim($query['status'])) : null,
            'page' => $page, 'per_page' => $perPage,
        ];
        $response = $this->request($user, 'GET', 'api/v2/journeys/persisted', $normalized);
        if (!($response['ok'] ?? false)) return $response;
        $data = $response['payload']['data'] ?? null;
        $echo = is_array($data) ? ($data['query'] ?? null) : null;
        $pagination = is_array($data) ? ($data['pagination'] ?? null) : null;
        $items = is_array($data) ? ($data['journeys'] ?? null) : null;
        $projectionStatus = is_array($data) ? ($data['projection_status'] ?? null) : null;
        $coverage = is_array($data) ? ($data['evaluation_coverage'] ?? null) : null;
        $isLastPage = is_array($pagination) && $page >= (int)($pagination['total_pages'] ?? -1);
        $expectedComplete = is_array($pagination)
            && ($pagination['total_records'] ?? null) === 0
                ? $projectionStatus === 'EMPTY'
                : ($isLastPage && $projectionStatus === 'COMPLETE');
        if (!is_array($data) || !is_array($echo) || !is_array($pagination) || !is_array($items)
            || (int)($echo['workplace_id'] ?? 0) !== $workplaceId
            || (string)($echo['from'] ?? '') !== $from || (string)($echo['to'] ?? '') !== $to
            || (($echo['worker_id'] ?? null) !== $normalized['worker_id'])
            || (($echo['status'] ?? null) !== $normalized['status'])
            || (int)($echo['page'] ?? 0) !== $page || (int)($echo['per_page'] ?? 0) !== $perPage
            || !is_int($pagination['total_records'] ?? null) || $pagination['total_records'] < 0
            || !is_int($pagination['total_pages'] ?? null) || !is_bool($pagination['has_more'] ?? null)
            || !is_bool($pagination['complete'] ?? null)
            || !in_array($projectionStatus, ['EMPTY', 'PARTIAL', 'COMPLETE', 'NOT_EVALUATED'], true)
            || !is_array($coverage)
            || !is_int($coverage['scheduled_journeys'] ?? null) || $coverage['scheduled_journeys'] < 0
            || !is_int($coverage['evaluated_journeys'] ?? null) || $coverage['evaluated_journeys'] < 0
            || !in_array($coverage['status'] ?? null, ['NOT_SCHEDULED', 'NOT_EVALUATED', 'PARTIALLY_EVALUATED', 'EVALUATED', 'UNDETERMINED'], true)
            || !is_string($coverage['evidence'] ?? null) || $coverage['evidence'] !== 'CANONICAL_SCHEDULE_AND_LATEST_PERSISTED_CALCULATION_COUNTS'
            || (($coverage['status'] ?? null) === 'UNDETERMINED'
                ? (($coverage['not_evaluated_journeys'] ?? 'not-null') !== null
                    || $coverage['evaluated_journeys'] <= $coverage['scheduled_journeys'])
                : !is_int($coverage['not_evaluated_journeys'] ?? null)
                    || $coverage['not_evaluated_journeys'] !== $coverage['scheduled_journeys'] - $coverage['evaluated_journeys'])
            || (($coverage['status'] ?? null) === 'NOT_SCHEDULED' && $coverage['scheduled_journeys'] !== 0)
            || (($coverage['status'] ?? null) === 'NOT_EVALUATED' && ($coverage['scheduled_journeys'] < 1 || $coverage['evaluated_journeys'] !== 0))
            || (($coverage['status'] ?? null) === 'PARTIALLY_EVALUATED' && ($coverage['evaluated_journeys'] < 1 || $coverage['evaluated_journeys'] >= $coverage['scheduled_journeys']))
            || (($coverage['status'] ?? null) === 'EVALUATED' && ($coverage['scheduled_journeys'] < 1 || $coverage['evaluated_journeys'] !== $coverage['scheduled_journeys']))
            || count($items) > $perPage
            || $pagination['total_pages'] !== ($pagination['total_records'] === 0 ? 0 : (int)ceil($pagination['total_records'] / $perPage))
            || $pagination['has_more'] !== ($page < $pagination['total_pages'])
            || $pagination['complete'] !== $expectedComplete) {
            return self::failure('api_unavailable', 502);
        }
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['evaluation_ref'] ?? null) || trim($item['evaluation_ref']) === ''
                || (int)($item['workplace_id'] ?? 0) !== $workplaceId
                || !is_int($item['worker_id'] ?? null) || !self::isCivilDate((string)($item['work_date'] ?? ''))
                || !is_string($item['status'] ?? null) || !is_array($item['segments'] ?? null)
                || (string)$item['work_date'] < $from || (string)$item['work_date'] > $to
                || !is_array($item['raw_punches'] ?? null) || !is_array($item['incidences'] ?? null)) {
                return self::failure('api_unavailable', 502);
            }
            if ($normalized['worker_id'] !== null && $item['worker_id'] !== $normalized['worker_id']) {
                return self::failure('api_unavailable', 502);
            }
            if ($normalized['status'] !== null && $item['status'] !== $normalized['status']) {
                return self::failure('api_unavailable', 502);
            }
        }
        return ['ok' => true, 'data' => $data];
    }

    /** Read one paginated C02 collection page using the API-owned canonical incidence source. */
    public function readIncidenceCollectionPage(Usuario $user, array $query): array
    {
        $workplaceId = self::positiveInt($query['workplace_id'] ?? null);
        $from = is_string($query['from'] ?? null) ? trim($query['from']) : '';
        $to = is_string($query['to'] ?? null) ? trim($query['to']) : '';
        $page = self::positiveInt($query['page'] ?? null);
        $perPage = self::positiveInt($query['per_page'] ?? null);
        $workerRaw = $query['worker_id'] ?? null;
        $workerProvided = $workerRaw !== null && !(is_string($workerRaw) && trim($workerRaw) === '');
        $workerId = $workerProvided ? self::positiveInt($workerRaw) : null;
        if ($workplaceId === null || !self::isCivilDate($from) || !self::isCivilDate($to) || $to < $from
            || ($workerProvided && $workerId === null) || $page === null || $perPage === null || $perPage > 200) return self::failure('bad_request', 400);
        $path = 'api/v2/workplaces/' . $workplaceId . '/incidences';
        $normalized = [
            'from' => $from, 'to' => $to,
            'worker_id' => $workerId,
            'category' => is_string($query['category'] ?? null) && trim($query['category']) !== '' ? strtoupper(trim($query['category'])) : null,
            'status' => is_string($query['status'] ?? null) && trim($query['status']) !== '' ? strtoupper(trim($query['status'])) : null,
            'page' => $page, 'per_page' => $perPage,
        ];
        $response = $this->request($user, 'GET', $path, $normalized);
        if (!($response['ok'] ?? false)) return $response;
        $data = $response['payload']['data'] ?? null;
        $echo = is_array($data) ? ($data['query'] ?? null) : null;
        $pagination = is_array($data) ? ($data['pagination'] ?? null) : null;
        $items = is_array($data) ? ($data['incidences'] ?? null) : null;
        $projectionStatus = is_array($data) ? ($data['projection_status'] ?? null) : null;
        $isLastPage = is_array($pagination) && $page >= (int)($pagination['total_pages'] ?? -1);
        $expectedComplete = is_array($pagination)
            && ($pagination['total_records'] ?? null) === 0
                ? $projectionStatus === 'EMPTY'
                : ($isLastPage && $projectionStatus === 'COMPLETE');
        if (!is_array($data) || !is_array($echo) || !is_array($pagination) || !is_array($items)
            || (int)($echo['workplace_id'] ?? 0) !== $workplaceId
            || (string)($echo['from'] ?? '') !== $from || (string)($echo['to'] ?? '') !== $to
            || (($echo['worker_id'] ?? null) !== $normalized['worker_id'])
            || (($echo['category'] ?? null) !== $normalized['category'])
            || (($echo['status'] ?? null) !== $normalized['status'])
            || (int)($echo['page'] ?? 0) !== $page || (int)($echo['per_page'] ?? 0) !== $perPage
            || !is_int($pagination['total_records'] ?? null) || $pagination['total_records'] < 0
            || !is_int($pagination['total_pages'] ?? null) || !is_bool($pagination['has_more'] ?? null)
            || !is_bool($pagination['complete'] ?? null) || !in_array($projectionStatus, ['EMPTY','PARTIAL','COMPLETE'], true)
            || count($items) > $perPage || $pagination['has_more'] !== ($page < $pagination['total_pages'])
            || $pagination['total_pages'] !== ($pagination['total_records'] === 0 ? 0 : (int)ceil($pagination['total_records'] / $perPage))
            || $pagination['complete'] !== $expectedComplete) {
            return self::failure('api_unavailable', 502);
        }
        foreach ($items as $item) {
            if (!is_array($item) || !is_string($item['uuid'] ?? null) || trim($item['uuid']) === ''
                || (int)($item['workplace_id'] ?? 0) !== $workplaceId || !is_int($item['worker_id'] ?? null)
                || !is_array($item['date_range'] ?? null) || !self::isCivilDate((string)($item['date_range']['start_date'] ?? ''))
                || !self::isCivilDate((string)($item['date_range']['end_date'] ?? ''))
                || ($item['detail_source'] ?? null) !== 'P3G_INCIDENCE_DETAIL_ENDPOINT'
                || ($item['punch_source'] ?? null) !== 'M05_PUNCH_EVENTS_ENDPOINT'
                || !is_array($item['applications'] ?? null)
                || !is_array($item['evaluations'] ?? null)) {
                return self::failure('api_unavailable', 502);
            }
            if ($normalized['worker_id'] !== null && $item['worker_id'] !== $normalized['worker_id']) return self::failure('api_unavailable', 502);
            if ((string)$item['date_range']['start_date'] > $to || (string)$item['date_range']['end_date'] < $from
                || ($normalized['category'] !== null && ($item['category'] ?? null) !== $normalized['category'])
                || ($normalized['status'] !== null && ($item['current_status'] ?? null) !== $normalized['status'])) {
                return self::failure('api_unavailable', 502);
            }
        }
        return ['ok' => true, 'data' => $data];
    }

    /**
     * Create one API-owned incidence master. Demo mode deliberately blocks
     * this write; only a real, authenticated server-to-server request may
     * reach the API. The API remains the canonical writer and actor authority.
     *
     * @param array<string,mixed> $input
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function createIncidence(Usuario $user, array $input, string $idempotencyKey): array
    {
        if ($this->demoScenario !== null) {
            return self::failure('demo_write_blocked', 403);
        }

        $workerId = self::positiveInt($input['worker_id'] ?? null);
        $workplaceId = self::positiveInt($input['workplace_id'] ?? null);
        $category = is_string($input['category'] ?? null) ? trim($input['category']) : '';
        $startDate = is_string($input['start_date'] ?? null) ? trim($input['start_date']) : '';
        $endDate = is_string($input['end_date'] ?? null) ? trim($input['end_date']) : '';
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        $evidenceReference = $input['evidence_reference'] ?? null;
        $checkDate = is_string($input['check_date'] ?? null) ? trim($input['check_date']) : '';
        $checkKind = is_string($input['check_kind'] ?? null) ? trim($input['check_kind']) : '';
        $nature = is_string($input['nature'] ?? null) ? trim($input['nature']) : '';
        $sourceEventDeviceId = self::positiveInt($input['source_event_device_id'] ?? null);
        $sourceEventUuid = is_string($input['source_event_uuid'] ?? null) ? trim($input['source_event_uuid']) : '';

        if ($workerId === null || $workplaceId === null
            || !in_array($category, self::INCIDENCE_CATEGORIES, true)
            || !self::isCivilDate($startDate) || !self::isCivilDate($endDate) || $endDate < $startDate
            || $reason === ''
            || ($evidenceReference !== null && !is_string($evidenceReference))
            || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', trim($idempotencyKey))) {
            return self::failure('bad_request', 400);
        }

        $checkPayload = null;
        if ($category === 'PUNCH_ATTENDANCE') {
            if (!self::p3gBPostOperationsEnabled()
                || !preg_match('/\A\[QA DEV\]/', $reason)
                || !is_string($evidenceReference)
                || !str_starts_with(trim($evidenceReference), 'QA-M06-')
                || !self::isCivilDate($checkDate) || $checkDate !== $startDate || $checkDate !== $endDate
                || !in_array($checkKind, ['ENTRY', 'LUNCH_EXIT', 'LUNCH_RETURN', 'FINAL_EXIT'], true)
                || !in_array($nature, ['LATENESS', 'OMISSION'], true)
                || ($nature === 'LATENESS' && ($sourceEventDeviceId === null || $sourceEventUuid === '' || strlen($sourceEventUuid) > 64))
                || ($nature === 'OMISSION' && (($input['source_event_device_id'] ?? '') !== '' || $sourceEventUuid !== ''))) {
                return self::failure('bad_request', 400);
            }
            $checkPayload = [
                'check_date' => $checkDate,
                'check_kind' => $checkKind,
                'nature' => $nature,
            ];
            if ($nature === 'LATENESS') {
                $checkPayload['source_event_device_id'] = $sourceEventDeviceId;
                $checkPayload['source_event_uuid'] = $sourceEventUuid;
            }
        } elseif ($checkDate !== '' || $checkKind !== '' || $nature !== ''
            || $sourceEventDeviceId !== null || $sourceEventUuid !== '') {
            return self::failure('bad_request', 400);
        }

        $evidenceReference = is_string($evidenceReference) ? trim($evidenceReference) : null;
        if ($evidenceReference === '') {
            $evidenceReference = null;
        }
        $body = [
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'category' => $category,
            'scope' => 'WORKER',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'time_interval' => null,
            'reason' => $reason,
            'evidence_reference' => $evidenceReference,
        ];
        if ($checkPayload !== null) {
            $body = array_merge($body, $checkPayload);
        }

        $response = $this->requestJson($user, 'POST', 'api/v2/incidences', $body, trim($idempotencyKey));
        if (!($response['ok'] ?? false)) {
            if (($response['error']['kind'] ?? '') === 'level2_authorization'
                && (int) ($response['error']['status'] ?? 0) === 403) {
                return self::failure('api_authorization', 403);
            }
            if ((int) ($response['error']['status'] ?? 0) === 422) {
                $kind = (string) ($response['error']['kind'] ?? 'bad_request');
                return in_array($kind, [
                    'incidence_deadline_expired', 'attendance_policy_not_versioned',
                    'attendance_policy_invalid', 'attendance_policy_unavailable',
                    'check_date_future', 'worker_membership_unverified', 'source_event_not_matched',
                ], true) ? $response : self::failure('bad_request', 422);
            }
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        $uuid = is_array($data) && is_string($data['uuid_incidencia'] ?? null)
            ? trim($data['uuid_incidencia'])
            : '';
        if (!is_array($data)
            || $uuid === '' || strlen($uuid) > 64 || str_contains($uuid, '/')
            || self::positiveInt($data['worker_id'] ?? null) !== $workerId
            || self::positiveInt($data['workplace_id'] ?? null) !== $workplaceId
            || ($data['category'] ?? null) !== $category
            || !is_int($data['current_revision'] ?? null) || $data['current_revision'] < 1
            || !is_string($data['status'] ?? null) || trim($data['status']) === ''
            || !is_string($data['created_at_utc'] ?? null) || trim($data['created_at_utc']) === '') {
            return self::failure('administrative_unavailable', 502);
        }
        if ($category === 'PUNCH_ATTENDANCE') {
            $checkReference = $data['check_reference'] ?? null;
            if (!self::matchesPunchCheckReference($checkReference, $checkPayload ?? [])) {
                return self::failure('administrative_unavailable', 502);
            }
        }

        return ['ok' => true, 'data' => [
            'uuid_incidencia' => $uuid,
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'category' => $category,
            'scope' => 'WORKER',
            'date_range' => ['start_date' => $startDate, 'end_date' => $endDate],
            'current_revision' => $data['current_revision'],
            'status' => $data['status'],
            'created_at_utc' => $data['created_at_utc'],
            'check_reference' => $data['check_reference'] ?? null,
        ]];
    }

    /**
     * Read one API-owned incidence and its complete append-only history.
     *
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function readIncidence(Usuario $user, string $uuidIncidencia): array
    {
        $uuidIncidencia = trim($uuidIncidencia);
        if ($uuidIncidencia === '' || strlen($uuidIncidencia) > 64 || str_contains($uuidIncidencia, '/')) {
            return self::failure('bad_request', 400);
        }

        $response = $this->request($user, 'GET', 'api/v2/incidences/' . rawurlencode($uuidIncidencia));
        if (!$response['ok']) {
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        if (!is_array($data)) {
            return self::failure('administrative_unavailable', 502);
        }
        $workerId = self::positiveInt($data['worker_id'] ?? null);
        $workplaceId = self::positiveInt($data['workplace_id'] ?? null);
        $dateRange = $data['date_range'] ?? null;
        $revisions = $data['revisions'] ?? null;
        if ($workerId === null || $workplaceId === null
            || (string) ($data['uuid_incidencia'] ?? '') !== $uuidIncidencia
            || !is_string($data['category'] ?? null)
            || !is_string($data['scope'] ?? null)
            || !is_array($dateRange)
            || !self::isCivilDate((string) ($dateRange['start_date'] ?? ''))
            || !self::isCivilDate((string) ($dateRange['end_date'] ?? ''))
            || !is_string($data['current_status'] ?? null)
            || !is_int($data['current_revision'] ?? null)
            || $data['current_revision'] < 1
            || !is_array($revisions)) {
            return self::failure('administrative_unavailable', 502);
        }
        $normalizedCheckReference = self::normalizePunchCheckReference($data['check_reference'] ?? null);
        if (($data['category'] === 'PUNCH_ATTENDANCE' && $normalizedCheckReference === null)
            || ($data['category'] !== 'PUNCH_ATTENDANCE' && $normalizedCheckReference !== null)) {
            return self::failure('administrative_unavailable', 502);
        }

        $normalizedRevisions = [];
        foreach ($revisions as $revision) {
            if (!is_array($revision)
                || !is_int($revision['revision_number'] ?? null)
                || $revision['revision_number'] < 1
                || !is_string($revision['status'] ?? null)
                || !array_key_exists('reason', $revision)
                || !array_key_exists('evidence_reference', $revision)
                || !is_int($revision['created_by_user_id'] ?? null)
                || !array_key_exists('approved_by_user_id', $revision)
                || !is_string($revision['created_at_utc'] ?? null)) {
                return self::failure('administrative_unavailable', 502);
            }
            $normalizedRevisions[] = [
                'revision_number' => $revision['revision_number'],
                'status' => $revision['status'],
                'reason' => $revision['reason'],
                'evidence_reference' => $revision['evidence_reference'],
                'created_by_user_id' => $revision['created_by_user_id'],
                'approved_by_user_id' => $revision['approved_by_user_id'],
                'created_at_utc' => $revision['created_at_utc'],
            ];
        }

        return ['ok' => true, 'data' => [
            'uuid_incidencia' => $uuidIncidencia,
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'category' => $data['category'],
            'scope' => $data['scope'],
            'date_range' => [
                'start_date' => $dateRange['start_date'],
                'end_date' => $dateRange['end_date'],
            ],
            'time_interval' => $data['time_interval'] ?? null,
            'current_status' => $data['current_status'],
            'current_revision' => $data['current_revision'],
            'check_reference' => $normalizedCheckReference,
            'revisions' => $normalizedRevisions,
        ]];
    }

    /**
     * Read an existing administrative evaluation. A missing evaluation is a
     * valid neutral result and never triggers evaluation or Journey work.
     *
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function readAdministrativeEvaluation(
        Usuario $user,
        int $workerId,
        int $workplaceId,
        string $workDate
    ): array {
        if ($workerId < 1 || $workplaceId < 1 || !self::isCivilDate($workDate)) {
            return self::failure('bad_request', 400);
        }

        $response = $this->request($user, 'GET', 'api/v2/administrative-evaluations', [
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
        ]);
        if (!$response['ok']) {
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        if (!is_array($data)
            || (int) ($data['worker_id'] ?? 0) !== $workerId
            || (int) ($data['workplace_id'] ?? 0) !== $workplaceId
            || (string) ($data['work_date'] ?? '') !== $workDate
            || !is_bool($data['evaluation_exists'] ?? null)) {
            return self::failure('administrative_unavailable', 502);
        }

        if ($data['evaluation_exists'] === false) {
            if (array_key_exists('evaluation', $data) && $data['evaluation'] !== null) {
                return self::failure('administrative_unavailable', 502);
            }
            return ['ok' => true, 'data' => [
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'work_date' => $workDate,
                'evaluation_exists' => false,
                'evaluation' => null,
            ]];
        }

        $evaluation = $data['evaluation'] ?? null;
        if (!is_array($evaluation)
            || !is_int($evaluation['revision_number'] ?? null)
            || !is_string($evaluation['administrative_status'] ?? null)
            || !is_string($evaluation['human_review_status'] ?? null)
            || !is_string($evaluation['fingerprint'] ?? null)
            || !is_string($evaluation['algorithm_version'] ?? null)
            || !(is_string($evaluation['policy_version'] ?? null) || $evaluation['policy_version'] === null)
            || (array_key_exists('policy_rule_version', $evaluation)
                && $evaluation['policy_rule_version'] !== null
                && !is_string($evaluation['policy_rule_version']))
            || !is_string($evaluation['created_at_utc'] ?? null)
            || !is_int($evaluation['associated_calculation_revision_id'] ?? null)
            || $evaluation['associated_calculation_revision_id'] < 1
            || !is_array($evaluation['linked_incidences'] ?? null)
            || !is_array($evaluation['linked_adjustments'] ?? null)) {
            return self::failure('administrative_unavailable', 502);
        }

        $linkedIncidences = [];
        foreach ($evaluation['linked_incidences'] as $linked) {
            if (!is_array($linked)
                || !is_string($linked['uuid_incidencia'] ?? null)
                || str_contains($linked['uuid_incidencia'], '/')
                || !is_string($linked['category'] ?? null)
                || !is_int($linked['revision_number'] ?? null)
                || !is_string($linked['application_status'] ?? null)) {
                return self::failure('administrative_unavailable', 502);
            }
            $linkedIncidences[] = [
                'uuid_incidencia' => $linked['uuid_incidencia'],
                'category' => $linked['category'],
                'revision_number' => $linked['revision_number'],
                'application_status' => $linked['application_status'],
            ];
        }

        $linkedAdjustments = [];
        foreach ($evaluation['linked_adjustments'] as $linked) {
            if (!is_array($linked)
                || !is_string($linked['uuid_ajuste'] ?? null)
                || !is_string($linked['uuid_ajuste_logico'] ?? null)
                || !is_string($linked['adjustment_type'] ?? null)
                || !is_int($linked['revision_number'] ?? null)
                || !is_string($linked['adjustment_status'] ?? null)) {
                return self::failure('administrative_unavailable', 502);
            }
            $linkedAdjustments[] = [
                'uuid_ajuste' => $linked['uuid_ajuste'],
                'uuid_ajuste_logico' => $linked['uuid_ajuste_logico'],
                'adjustment_type' => $linked['adjustment_type'],
                'revision_number' => $linked['revision_number'],
                'adjustment_status' => $linked['adjustment_status'],
            ];
        }

        return ['ok' => true, 'data' => [
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
            'evaluation_exists' => true,
            'evaluation' => [
                'revision_number' => $evaluation['revision_number'],
                'administrative_status' => $evaluation['administrative_status'],
                'human_review_status' => $evaluation['human_review_status'],
                'fingerprint' => $evaluation['fingerprint'],
                'algorithm_version' => $evaluation['algorithm_version'],
                'policy_version' => $evaluation['policy_version'],
                'policy_rule_version' => array_key_exists('policy_rule_version', $evaluation)
                    ? $evaluation['policy_rule_version']
                    : null,
                'associated_calculation_revision_id' => $evaluation['associated_calculation_revision_id'],
                'created_at_utc' => $evaluation['created_at_utc'],
                'linked_incidences' => $linkedIncidences,
                'linked_adjustments' => $linkedAdjustments,
            ],
        ]];
    }

    /** Read one bounded page of persisted administrative adjustments from API V2. */
    public function readAdministrativeAdjustments(Usuario $user, array $filters): array
    {
        $workplaceId = filter_var($filters['workplace_id'] ?? null, FILTER_VALIDATE_INT);
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $page = filter_var($filters['page'] ?? 1, FILTER_VALIDATE_INT);
        $perPage = filter_var($filters['per_page'] ?? 25, FILTER_VALIDATE_INT);
        $workerId = ($filters['worker_id'] ?? null) === null || ($filters['worker_id'] ?? '') === ''
            ? null : filter_var($filters['worker_id'], FILTER_VALIDATE_INT);
        $type = trim((string) ($filters['adjustment_type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $fromDate = self::isCivilDate($dateFrom) ? new \DateTimeImmutable($dateFrom) : null;
        $toDate = self::isCivilDate($dateTo) ? new \DateTimeImmutable($dateTo) : null;

        if ($workplaceId === false || $workplaceId < 1 || $fromDate === null || $toDate === null
            || $dateTo < $dateFrom || $toDate->diff($fromDate)->days > 30
            || $page === false || $page < 1 || $perPage === false || $perPage < 1 || $perPage > 100
            || ($workerId !== null && ($workerId === false || $workerId < 1))) {
            return self::failure('bad_request', 400);
        }
        if (($type !== '' && !in_array($type, ['OVERRIDE_STATUS', 'INTERPRETED_ENTRY_TIME', 'INTERPRETED_EXIT_TIME', 'JUSTIFY_LATENESS'], true))
            || ($status !== '' && !in_array($status, ['ACTIVE', 'SUPERSEDED', 'CANCELLED'], true))) {
            return self::failure('bad_request', 422);
        }

        $query = [
            'workplace_id' => $workplaceId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'page' => $page,
            'per_page' => $perPage,
        ];
        if ($workerId !== null) $query['worker_id'] = $workerId;
        if ($type !== '') $query['adjustment_type'] = $type;
        if ($status !== '') $query['status'] = $status;

        $response = $this->request($user, 'GET', 'api/v2/administrative-adjustments', $query);
        if (!($response['ok'] ?? false)) return $response;
        $data = $response['payload']['data'] ?? null;
        if (!is_array($data) || (int) ($data['workplace_id'] ?? 0) !== $workplaceId
            || !is_array($data['query'] ?? null)
            || (string) ($data['query']['date_from'] ?? '') !== $dateFrom
            || (string) ($data['query']['date_to'] ?? '') !== $dateTo
            || (($data['query']['worker_id'] ?? null) !== $workerId)
            || (($data['query']['adjustment_type'] ?? null) !== ($type === '' ? null : $type))
            || (($data['query']['status'] ?? null) !== ($status === '' ? null : $status))
            || !self::isUtcTimestamp((string) ($data['as_of_utc'] ?? ''))
            || !is_int($data['total_records'] ?? null) || $data['total_records'] < 0
            || !is_int($data['page'] ?? null) || $data['page'] !== $page
            || !is_int($data['per_page'] ?? null) || $data['per_page'] !== $perPage
            || !is_int($data['total_pages'] ?? null)
            || !is_array($data['adjustments'] ?? null) || !array_is_list($data['adjustments'])) {
            return self::failure('administrative_unavailable', 502);
        }

        $expectedPages = $data['total_records'] === 0 ? 0 : (int) ceil($data['total_records'] / $perPage);
        if ($data['total_pages'] !== $expectedPages || count($data['adjustments']) > $perPage) {
            return self::failure('administrative_unavailable', 502);
        }
        $items = [];
        $seen = [];
        foreach ($data['adjustments'] as $item) {
            if (!is_array($item)) return self::failure('administrative_unavailable', 502);
            $uuid = $item['uuid_ajuste'] ?? null;
            $logicalUuid = $item['uuid_ajuste_logico'] ?? null;
            $rowWorker = filter_var($item['worker_id'] ?? null, FILTER_VALIDATE_INT);
            $rowWorkplace = filter_var($item['workplace_id'] ?? null, FILTER_VALIDATE_INT);
            $revision = filter_var($item['revision_number'] ?? null, FILTER_VALIDATE_INT);
            $linkedCount = filter_var($item['linked_evaluation_count'] ?? null, FILTER_VALIDATE_INT);
            if (!is_string($uuid) || $uuid === '' || strlen($uuid) > 64 || str_contains($uuid, '/')
                || !is_string($logicalUuid) || $logicalUuid === '' || strlen($logicalUuid) > 64
                || isset($seen[$uuid]) || $rowWorker === false || $rowWorker < 1
                || $rowWorkplace !== $workplaceId || $revision === false || $revision < 1
                || !is_string($item['status'] ?? null) || !in_array($item['status'], ['ACTIVE', 'SUPERSEDED', 'CANCELLED'], true)
                || !is_string($item['adjustment_type'] ?? null)
                || !self::isCivilDate((string) ($item['work_date_local'] ?? ''))
                || $item['work_date_local'] < $dateFrom || $item['work_date_local'] > $dateTo
                || !is_string($item['workplace_timezone'] ?? null) || trim($item['workplace_timezone']) === ''
                || !is_string($item['motive'] ?? null)
                || !self::isUtcTimestamp((string) ($item['created_at_utc'] ?? ''))
                || !is_int($item['created_by_user_id'] ?? null) || $item['created_by_user_id'] < 1
                || !is_int($item['approved_by_user_id'] ?? null) || $item['approved_by_user_id'] < 1
                || $linkedCount === false || $linkedCount < 0
                || !self::nullableUtcTimestamp($item['interpreted_entry_at_utc'] ?? null)
                || !self::nullableUtcTimestamp($item['interpreted_exit_at_utc'] ?? null)) {
                return self::failure('administrative_unavailable', 502);
            }
            if ($workerId !== null && $rowWorker !== $workerId) return self::failure('administrative_unavailable', 502);
            if ($type !== '' && $item['adjustment_type'] !== $type) return self::failure('administrative_unavailable', 502);
            if ($status !== '' && $item['status'] !== $status) return self::failure('administrative_unavailable', 502);
            $seen[$uuid] = true;
            $items[] = [
                'uuid_ajuste' => $uuid,
                'uuid_ajuste_logico' => $logicalUuid,
                'revision_number' => $revision,
                'status' => $item['status'],
                'adjustment_type' => $item['adjustment_type'],
                'worker_id' => $rowWorker,
                'workplace_id' => $rowWorkplace,
                'work_date_local' => $item['work_date_local'],
                'workplace_timezone' => $item['workplace_timezone'],
                'motive' => $item['motive'],
                'interpreted_entry_at_utc' => $item['interpreted_entry_at_utc'],
                'interpreted_exit_at_utc' => $item['interpreted_exit_at_utc'],
                'created_at_utc' => $item['created_at_utc'],
                'created_by_user_id' => $item['created_by_user_id'],
                'approved_by_user_id' => $item['approved_by_user_id'],
                'linked_evaluation_count' => $linkedCount,
            ];
        }

        return ['ok' => true, 'data' => [
            'workplace_id' => $workplaceId,
            'query' => $query,
            'as_of_utc' => $data['as_of_utc'],
            'total_records' => $data['total_records'],
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $data['total_pages'],
            'adjustments' => $items,
        ]];
    }

    /** Read one authorized adjustment revision and its persisted lineage from API V2. */
    public function readAdministrativeAdjustmentDetail(Usuario $user, string $uuid): array
    {
        if ($uuid === '' || strlen($uuid) > 64 || str_contains($uuid, '/') || preg_match('/\A[A-Za-z0-9._:-]+\z/D', $uuid) !== 1) {
            return self::failure('bad_request', 400);
        }
        $response = $this->request($user, 'GET', 'api/v2/administrative-adjustments/' . rawurlencode($uuid));
        if (!($response['ok'] ?? false)) return $response;
        $data = $response['payload']['data'] ?? null;
        if (!is_array($data) || !is_array($data['adjustment'] ?? null)
            || !is_array($data['revision_history'] ?? null) || !array_is_list($data['revision_history'])
            || !is_array($data['linked_evaluations'] ?? null) || !array_is_list($data['linked_evaluations'])) {
            return self::failure('administrative_unavailable', 502);
        }

        $normalizeRevision = static function (mixed $item) use ($uuid): ?array {
            if (!is_array($item)) return null;
            $rowUuid = $item['uuid_ajuste'] ?? null;
            $workerId = filter_var($item['worker_id'] ?? null, FILTER_VALIDATE_INT);
            $workplaceId = filter_var($item['workplace_id'] ?? null, FILTER_VALIDATE_INT);
            $revision = filter_var($item['revision_number'] ?? null, FILTER_VALIDATE_INT);
            if (!is_string($rowUuid) || $rowUuid === '' || strlen($rowUuid) > 64 || str_contains($rowUuid, '/')
                || !is_string($item['uuid_ajuste_logico'] ?? null) || $item['uuid_ajuste_logico'] === ''
                || $workerId === false || $workerId < 1 || $workplaceId === false || $workplaceId < 1
                || $revision === false || $revision < 1 || !is_string($item['status'] ?? null)
                || !is_string($item['adjustment_type'] ?? null) || !self::isCivilDate((string) ($item['work_date_local'] ?? ''))
                || !is_string($item['workplace_timezone'] ?? null) || trim($item['workplace_timezone']) === ''
                || !is_string($item['motive'] ?? null) || !self::isUtcTimestamp((string) ($item['created_at_utc'] ?? ''))
                || !is_int($item['created_by_user_id'] ?? null) || $item['created_by_user_id'] < 1
                || !is_int($item['approved_by_user_id'] ?? null) || $item['approved_by_user_id'] < 1
                || !self::nullableUtcTimestamp($item['interpreted_entry_at_utc'] ?? null)
                || !self::nullableUtcTimestamp($item['interpreted_exit_at_utc'] ?? null)) return null;
            $previousUuid = $item['previous_revision_uuid'] ?? null;
            if ($previousUuid !== null && (!is_string($previousUuid) || $previousUuid === '' || strlen($previousUuid) > 64 || str_contains($previousUuid, '/'))) return null;
            return [
                'uuid_ajuste' => $rowUuid,
                'uuid_ajuste_logico' => (string) $item['uuid_ajuste_logico'],
                'revision_number' => $revision,
                'status' => (string) $item['status'],
                'adjustment_type' => (string) $item['adjustment_type'],
                'worker_id' => $workerId,
                'workplace_id' => $workplaceId,
                'work_date_local' => (string) $item['work_date_local'],
                'workplace_timezone' => (string) $item['workplace_timezone'],
                'motive' => (string) $item['motive'],
                'interpreted_entry_at_utc' => $item['interpreted_entry_at_utc'],
                'interpreted_exit_at_utc' => $item['interpreted_exit_at_utc'],
                'created_at_utc' => (string) $item['created_at_utc'],
                'created_by_user_id' => $item['created_by_user_id'],
                'approved_by_user_id' => $item['approved_by_user_id'],
                'previous_revision_uuid' => $previousUuid,
            ];
        };

        $selected = $normalizeRevision($data['adjustment']);
        if ($selected === null || $selected['uuid_ajuste'] !== $uuid) return self::failure('administrative_unavailable', 502);
        $history = [];
        $historyUuids = [];
        $lastRevision = 0;
        foreach ($data['revision_history'] as $item) {
            $normalized = $normalizeRevision($item);
            if ($normalized === null || $normalized['uuid_ajuste_logico'] !== $selected['uuid_ajuste_logico']
                || $normalized['worker_id'] !== $selected['worker_id'] || $normalized['workplace_id'] !== $selected['workplace_id']
                || isset($historyUuids[$normalized['uuid_ajuste']]) || $normalized['revision_number'] <= $lastRevision) {
                return self::failure('administrative_unavailable', 502);
            }
            $historyUuids[$normalized['uuid_ajuste']] = true;
            $lastRevision = $normalized['revision_number'];
            $history[] = $normalized;
        }
        if (!isset($historyUuids[$uuid])) return self::failure('administrative_unavailable', 502);

        $linkedEvaluations = [];
        foreach ($data['linked_evaluations'] as $evaluation) {
            if (!is_array($evaluation)
                || filter_var($evaluation['worker_id'] ?? null, FILTER_VALIDATE_INT) !== $selected['worker_id']
                || filter_var($evaluation['workplace_id'] ?? null, FILTER_VALIDATE_INT) !== $selected['workplace_id']
                || !self::isCivilDate((string) ($evaluation['work_date_local'] ?? ''))
                || !is_int($evaluation['revision_number'] ?? null) || $evaluation['revision_number'] < 1
                || !is_string($evaluation['status'] ?? null) || !self::isUtcTimestamp((string) ($evaluation['created_at_utc'] ?? ''))) {
                return self::failure('administrative_unavailable', 502);
            }
            $linkedEvaluations[] = [
                'worker_id' => $selected['worker_id'],
                'workplace_id' => $selected['workplace_id'],
                'work_date_local' => (string) $evaluation['work_date_local'],
                'revision_number' => $evaluation['revision_number'],
                'status' => $evaluation['status'],
                'created_at_utc' => $evaluation['created_at_utc'],
            ];
        }

        return ['ok' => true, 'data' => [
            'adjustment' => $selected,
            'revision_history' => $history,
            'linked_evaluations' => $linkedEvaluations,
        ]];
    }

    private static function nullableUtcTimestamp(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) !== '' && self::isUtcTimestamp($value));
    }

    /**
     * Execute the API-owned administrative evaluation command. Portal3 sends
     * only canonical references and renders the API result; it never computes
     * Journey, incidences, FALTA, or payroll outcomes locally.
     *
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function executeAdministrativeEvaluation(
        Usuario $user,
        int $workerId,
        int $workplaceId,
        string $workDate,
        string $idempotencyKey,
        ?int $expectedCalculationRevisionId = null,
        bool $authorizeFalta = false
    ): array {
        $idempotencyKey = trim($idempotencyKey);
        if ($workerId < 1 || $workplaceId < 1 || !self::isCivilDate($workDate)
            || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey)
            || ($expectedCalculationRevisionId !== null && $expectedCalculationRevisionId < 1)) {
            return self::failure('bad_request', 400);
        }

        $body = [
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
        ];
        if ($expectedCalculationRevisionId !== null) {
            $body['expected_calculation_revision_id'] = $expectedCalculationRevisionId;
        }
        if ($authorizeFalta) {
            $body['authorize_falta'] = true;
        }

        $response = $this->requestJson(
            $user,
            'POST',
            'api/v2/administrative-evaluations/evaluate',
            $body,
            $idempotencyKey
        );
        if (!$response['ok']) {
            if (($response['error']['kind'] ?? '') === 'level2_authorization') {
                return self::failure('api_authorization', (int) ($response['error']['status'] ?? 403));
            }
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        if (!is_array($data)
            || (int) ($data['worker_id'] ?? 0) !== $workerId
            || (int) ($data['workplace_id'] ?? 0) !== $workplaceId
            || (string) ($data['work_date'] ?? '') !== $workDate
            || !is_int($data['revision_number'] ?? null)
            || !is_string($data['administrative_status'] ?? null)
            || !is_string($data['human_review_status'] ?? null)
            || !is_string($data['fingerprint'] ?? null)
            || !is_string($data['created_at_utc'] ?? null)
            || (!array_key_exists('policy_rule_version', $data) && !array_key_exists('policy_version', $data))) {
            return self::failure('administrative_unavailable', 502);
        }

        return ['ok' => true, 'data' => $data];
    }

    /**
     * Execute one API-owned administrative lifecycle transition. Portal3
     * validates the input envelope and renders only the authoritative result;
     * the API remains the sole writer and state-transition authority.
     *
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function lifecycleAction(
        Usuario $user,
        string $uuidIncidencia,
        string $action,
        int $expectedCurrentRevision,
        string $idempotencyKey,
        array $fields = [],
        ?int $demoLocalityId = null
    ): array {
        $uuidIncidencia = trim($uuidIncidencia);
        $action = trim($action);
        $idempotencyKey = trim($idempotencyKey);
        if ($uuidIncidencia === '' || strlen($uuidIncidencia) > 64 || str_contains($uuidIncidencia, '/')
            || !in_array($action, ['resolve', 'authorize', 'reject', 'revoke'], true)
            || $expectedCurrentRevision < 1
            || $idempotencyKey === '' || strlen($idempotencyKey) > 64) {
            return self::failure('bad_request', 400);
        }

        $body = ['expected_current_revision' => $expectedCurrentRevision];
        if ($action === 'resolve') {
            $body['resolution_action'] = trim((string) ($fields['resolution_action'] ?? 'CONFIRM_INCIDENCE_PREVALENCE'));
            $body['resolution_notes'] = trim((string) ($fields['resolution_notes'] ?? ''));
            if ($body['resolution_notes'] === '') {
                return self::failure('bad_request', 400);
            }
            if (array_key_exists('evidence_reference', $fields) && trim((string) $fields['evidence_reference']) !== '') {
                $body['evidence_reference'] = trim((string) $fields['evidence_reference']);
            }
        } elseif ($action === 'authorize') {
            $notes = trim((string) ($fields['authorization_notes'] ?? ''));
            if ($notes !== '') {
                $body['authorization_notes'] = $notes;
            }
        } elseif ($action === 'reject') {
            $body['rejection_reason'] = trim((string) ($fields['rejection_reason'] ?? ''));
            if ($body['rejection_reason'] === '') {
                return self::failure('bad_request', 400);
            }
        } else {
            $body['revocation_reason'] = trim((string) ($fields['revocation_reason'] ?? ''));
            if ($body['revocation_reason'] === '') {
                return self::failure('bad_request', 400);
            }
            if (array_key_exists('evidence_reference', $fields) && trim((string) $fields['evidence_reference']) !== '') {
                $body['evidence_reference'] = trim((string) $fields['evidence_reference']);
            }
        }

        $response = $this->requestJson(
            $user,
            'POST',
            'api/v2/incidences/' . rawurlencode($uuidIncidencia) . '/' . $action,
            $body,
            $idempotencyKey,
            $demoLocalityId
        );
        if (!$response['ok']) {
            return $response;
        }

        $data = $response['payload']['data'] ?? null;
        if (!is_array($data) || (string) ($data['uuid_incidencia'] ?? '') !== $uuidIncidencia) {
            return self::failure('administrative_unavailable', 502);
        }
        return ['ok' => true, 'data' => $data];
    }

    /**
     * Read the API-owned temporary-PIN supervisor queue. The API applies its
     * own user ACL and station scope; Portal3 filters the returned authorized
     * records to the currently selected canonical workplace before rendering.
     *
     * @return array{ok:bool,requests?:array<int,array<string,mixed>>,error?:array{kind:string,status:int}}
     */
    public function listTemporaryAccessRequests(Usuario $user): array
    {
        if ($this->demoScenario !== null) {
            return self::failure('demo_operation_blocked', 403);
        }

        $response = $this->request($user, 'GET', 'api/v2/temporary-access/requests');
        if (!$response['ok']) {
            return $response;
        }

        $payload = $response['payload'];
        $rows = $payload['data'] ?? null;
        if (($payload['status'] ?? null) !== 'OK' || !is_array($rows) || !array_is_list($rows)) {
            return self::failure('api_unavailable', 502);
        }

        $requests = [];
        foreach ($rows as $row) {
            if (!is_array($row)
                || !is_string($row['request_uuid'] ?? null)
                || !preg_match('/\\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\z/i', $row['request_uuid'])
                || self::positiveInt($row['id_personal_resuelto'] ?? null) === null
                || self::positiveInt($row['id_estacion'] ?? null) === null
                || !is_string($row['nombre_trabajador'] ?? null)
                || trim($row['nombre_trabajador']) === ''
                || !is_string($row['dispositivo_nombre'] ?? null)
                || trim($row['dispositivo_nombre']) === ''
                || !is_string($row['reason'] ?? null)
                || trim($row['reason']) === ''
                || !self::isUtcDateTime((string) ($row['requested_at_utc'] ?? ''))
                || !self::isUtcDateTime((string) ($row['request_expires_at_utc'] ?? ''))) {
                return self::failure('api_unavailable', 502);
            }

            $requests[] = [
                'request_uuid' => strtolower($row['request_uuid']),
                'id_personal_resuelto' => self::positiveInt($row['id_personal_resuelto']),
                'nombre_trabajador' => trim($row['nombre_trabajador']),
                'id_estacion' => self::positiveInt($row['id_estacion']),
                'dispositivo_nombre' => trim($row['dispositivo_nombre']),
                'reason' => trim($row['reason']),
                'requested_at_utc' => $row['requested_at_utc'],
                'request_expires_at_utc' => $row['request_expires_at_utc'],
            ];
        }

        return ['ok' => true, 'requests' => $requests];
    }

    /**
     * Execute only frozen Portal3 supervisor transitions. PIN plaintext is
     * returned to the controller for one-time session flash; it is never logged.
     *
     * @return array{ok:bool,data?:array<string,mixed>,error?:array{kind:string,status:int}}
     */
    public function temporaryAccessAction(Usuario $user, string $requestUuid, string $action): array
    {
        $requestUuid = strtolower(trim($requestUuid));
        if (!preg_match('/\\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\z/i', $requestUuid)
            || !in_array($action, ['authorize', 'reject', 'cancel'], true)) {
            return self::failure('bad_request', 400);
        }
        if ($this->demoScenario !== null) {
            return self::failure('demo_operation_blocked', 403);
        }

        $response = $this->requestJson(
            $user,
            'POST',
            'api/v2/temporary-access/requests/' . rawurlencode($requestUuid) . '/' . $action,
            []
        );
        if (!$response['ok']) {
            return $response;
        }

        $payload = $response['payload'];
        $expectedStatus = match ($action) {
            'authorize' => 'AUTHORIZED',
            'reject' => 'REJECTED',
            'cancel' => 'CANCELLED',
        };
        if (($payload['status'] ?? null) !== $expectedStatus
            || strtolower((string) ($payload['request_uuid'] ?? '')) !== $requestUuid) {
            return self::failure('api_unavailable', 502);
        }

        $data = [
            'request_uuid' => $requestUuid,
            'status' => $expectedStatus,
            'mensaje' => is_string($payload['mensaje'] ?? null) ? $payload['mensaje'] : '',
        ];
        if ($action === 'authorize') {
            $pin = $payload['temporary_pin'] ?? null;
            $expiresAt = $payload['pin_expires_at_utc'] ?? null;
            if (!is_string($pin) || !preg_match('/\\A[0-9]{6}\\z/', $pin)
                || !is_string($expiresAt) || !self::isUtcDateTime($expiresAt)) {
                return self::failure('api_unavailable', 502);
            }
            $data['temporary_pin'] = $pin;
            $data['pin_expires_at_utc'] = $expiresAt;
        }

        return ['ok' => true, 'data' => $data];
    }

    private static function isUtcDateTime(string $value): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $parsed instanceof \DateTimeImmutable
            && $parsed->format('Y-m-d H:i:s') === $value
            && !array_filter(\DateTimeImmutable::getLastErrors() ?: []);
    }

    private static function isIanaTimezone(mixed $value): bool
    {
        return is_string($value)
            && trim($value) === $value
            && in_array($value, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true);
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) return $value;
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) return (int) $value;
        return null;
    }

    /** API create responses include segment database IDs; compare the contract fields only. */
    private static function segmentsMatchExpected(mixed $actual, array $expected): bool
    {
        if (!is_array($actual) || !array_is_list($actual) || count($actual) !== count($expected)) return false;
        $normalized = [];
        foreach ($actual as $index => $segment) {
            if (!is_array($segment)) return false;
            $normalized[] = [
                'ordinal' => filter_var($segment['ordinal'] ?? null, FILTER_VALIDATE_INT),
                'tipo_segmento' => strtoupper(trim((string) ($segment['tipo_segmento'] ?? ''))),
                'hora_inicio_local' => (string) ($segment['hora_inicio_local'] ?? ''),
                'hora_fin_local' => (string) ($segment['hora_fin_local'] ?? ''),
                'dia_offset_inicio' => filter_var($segment['dia_offset_inicio'] ?? null, FILTER_VALIDATE_INT),
                'dia_offset_fin' => filter_var($segment['dia_offset_fin'] ?? null, FILTER_VALIDATE_INT),
            ];
        }
        foreach ($expected as $index => $segment) {
            if (($normalized[$index] ?? null) !== $segment) return false;
        }
        return true;
    }

    private static function strictInteger(mixed $value): ?int
    {
        if (is_int($value)) return $value;
        if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $value)) return null;
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        return $parsed === false ? null : $parsed;
    }

    private static function normalizePunchCheckReference(mixed $reference): ?array
    {
        if ($reference === null) return null;
        if (!is_array($reference)
            || !self::isCivilDate((string) ($reference['check_date'] ?? ''))
            || !in_array(($reference['check_kind'] ?? null), ['ENTRY', 'LUNCH_EXIT', 'LUNCH_RETURN', 'FINAL_EXIT'], true)
            || !in_array(($reference['nature'] ?? null), ['LATENESS', 'OMISSION'], true)
            || self::positiveInt($reference['policy_version_id'] ?? null) === null
            || !is_int($reference['policy_version'] ?? null) || $reference['policy_version'] < 1
            || !self::isIanaTimezone($reference['timezone_id'] ?? null)
            || !self::isCivilDate(substr((string) ($reference['cutoff_local'] ?? ''), 0, 10))
            || !self::isLocalDateTime((string) ($reference['cutoff_local'] ?? ''))) {
            return null;
        }
        $deviceId = $reference['source_event_device_id'] ?? null;
        $eventUuid = $reference['source_event_uuid'] ?? null;
        if (($reference['nature'] === 'LATENESS'
                && (self::positiveInt($deviceId) === null || !is_string($eventUuid) || trim($eventUuid) === '' || strlen($eventUuid) > 64))
            || ($reference['nature'] === 'OMISSION' && ($deviceId !== null || $eventUuid !== null))) {
            return null;
        }
        return [
            'check_date' => (string) $reference['check_date'],
            'check_kind' => (string) $reference['check_kind'],
            'nature' => (string) $reference['nature'],
            'source_event_device_id' => $deviceId === null ? null : (int) $deviceId,
            'source_event_uuid' => $eventUuid === null ? null : (string) $eventUuid,
            'policy_version_id' => (int) $reference['policy_version_id'],
            'policy_version' => (int) $reference['policy_version'],
            'timezone_id' => (string) $reference['timezone_id'],
            'cutoff_local' => (string) $reference['cutoff_local'],
        ];
    }

    private static function matchesPunchCheckReference(mixed $reference, array $expected): bool
    {
        $normalized = self::normalizePunchCheckReference($reference);
        if ($normalized === null) return false;
        foreach (['check_date', 'check_kind', 'nature', 'source_event_device_id', 'source_event_uuid'] as $key) {
            if (($normalized[$key] ?? null) !== ($expected[$key] ?? null)) return false;
        }
        return true;
    }

    private static function isLocalDateTime(string $value): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        return $parsed instanceof \DateTimeImmutable
            && $parsed->format('Y-m-d H:i:s') === $value
            && !array_filter(\DateTimeImmutable::getLastErrors() ?: []);
    }

    private static function normalizeIncidenceSummary(mixed $incidence, int $workerId, int $workplaceId): ?array
    {
        if (!is_array($incidence)
            || !is_string($incidence['uuid_incidencia'] ?? null)
            || trim($incidence['uuid_incidencia']) === ''
            || str_contains($incidence['uuid_incidencia'], '/')
            || (int) ($incidence['worker_id'] ?? 0) !== $workerId
            || (($incidence['workplace_id'] ?? null) !== null && (int) $incidence['workplace_id'] !== $workplaceId)
            || !is_string($incidence['category'] ?? null)
            || !is_string($incidence['scope'] ?? null)
            || !is_array($incidence['date_range'] ?? null)
            || !self::isCivilDate((string) ($incidence['date_range']['start_date'] ?? ''))
            || !self::isCivilDate((string) ($incidence['date_range']['end_date'] ?? ''))
            || !is_string($incidence['current_status'] ?? null)
            || !is_int($incidence['current_revision'] ?? null)
            || $incidence['current_revision'] < 1
            || !array_key_exists('reason', $incidence)
            || !array_key_exists('source_classification', $incidence)) {
            return null;
        }

        $checkReference = self::normalizePunchCheckReference($incidence['check_reference'] ?? null);
        if (($incidence['category'] === 'PUNCH_ATTENDANCE' && $checkReference === null)
            || ($incidence['category'] !== 'PUNCH_ATTENDANCE' && $checkReference !== null)) {
            return null;
        }

        return [
            'uuid_incidencia' => $incidence['uuid_incidencia'],
            'worker_id' => $workerId,
            'workplace_id' => $incidence['workplace_id'] ?? null,
            'category' => $incidence['category'],
            'scope' => $incidence['scope'],
            'date_range' => [
                'start_date' => $incidence['date_range']['start_date'],
                'end_date' => $incidence['date_range']['end_date'],
            ],
            'time_interval' => $incidence['time_interval'] ?? null,
            'current_status' => $incidence['current_status'],
            'current_revision' => $incidence['current_revision'],
            'reason' => $incidence['reason'],
            'source_classification' => $incidence['source_classification'],
            'evidence_reference' => $incidence['evidence_reference'] ?? null,
            'created_at_utc' => $incidence['created_at_utc'] ?? null,
            'created_by_user_id' => $incidence['created_by_user_id'] ?? null,
            'approved_by_user_id' => $incidence['approved_by_user_id'] ?? null,
            'check_reference' => $checkReference,
        ];
    }

    private function request(
        Usuario $user,
        string $method,
        string $path,
        array $query = [],
        ?string $diagnosticOperation = null,
        ?int $demoResolvedWorkerId = null,
        array $additionalHeaders = []
    ): array
    {
        try {
            if ($this->demoScenario !== null) {
                if ($demoResolvedWorkerId !== null
                    && preg_match('~\Aapi/v2/punch-events/read-snapshots/([A-Za-z0-9._:-]+)/events\z~', ltrim($path, '/'), $matches) === 1) {
                    $response = $this->demoSnapshotPageResponse(
                        rawurldecode($matches[1]),
                        $query,
                        $this->demoScenario,
                        $demoResolvedWorkerId
                    );
                } else {
                    $response = $this->demoResponse($method, $path, $query, [], $user);
                }
            } else {
                $token = ($this->tokenFactory)($user);
                if (!is_string($token) || trim($token) === '') {
                    return self::failure('api_authentication', 401);
                }

                $response = $this->http->request($method, $path, [
                    'query' => $query,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Bearer ' . $token,
                    ] + $additionalHeaders,
                    'http_errors' => false,
                ]);
            }
            $status = $response->getStatusCode();
            $raw = (string) $response->getBody();
            $payload = json_decode($raw, true);
            $jsonValid = json_last_error() === JSON_ERROR_NONE && is_array($payload);
            $payload = is_array($payload) ? $payload : [];
            $diagnostic = $diagnosticOperation === null ? [] : [
                'operation' => $diagnosticOperation,
                'api_base_url' => self::safeBaseUrl($this->baseUrl),
                'http_status' => $status,
                'api_error_code' => null,
                'local_error_kind' => null,
                'transport_error_class' => null,
                'transport_error_kind' => null,
                'json_valid' => $jsonValid,
                'response_contract_valid' => null,
                'correlation_id' => self::safeCorrelationId(
                    $response->getHeaderLine('X-Correlation-ID')
                        ?: $response->getHeaderLine('X-Request-ID')
                ),
            ];

            if ($status < 200 || $status >= 300) {
                $errorCode = is_array($payload['error'] ?? null) ? ($payload['error']['code'] ?? '') : '';
                $statusCode = $errorCode !== '' ? $errorCode : ($payload['status'] ?? '');
                $normalizedPath = ltrim($path, '/');
                $kind = str_starts_with($normalizedPath, 'api/v2/temporary-access/')
                    ? self::mapTemporaryAccessApiError((string) $statusCode, $status)
                    : (str_starts_with($normalizedPath, 'api/v2/administrative-adjustments')
                        ? self::mapAdministrativeAdjustmentApiError((string) $statusCode, $status)
                        : self::mapApiError((string) $statusCode, $status));
                if ($diagnosticOperation !== null) {
                    $diagnostic['api_error_code'] = self::safeApiErrorCode((string) $statusCode);
                    $diagnostic['local_error_kind'] = $kind;
                    $diagnostic['response_contract_valid'] = false;
                    $diagnostic['api_error_envelope_valid'] = $jsonValid && self::isErrorEnvelope($payload);
                    $this->logMvpApiDiagnostic($diagnosticOperation, $diagnostic);
                    return array_merge(self::failure($kind, $status), ['diagnostic' => $diagnostic]);
                }
                return self::failure($kind, $status);
            }

            return $diagnosticOperation === null
                ? ['ok' => true, 'payload' => $payload]
                : ['ok' => true, 'payload' => $payload, 'diagnostic' => $diagnostic];
        } catch (ChecadorApiJwtConfigurationException $exception) {
            if ($diagnosticOperation !== null) {
                $this->logMvpApiDiagnostic($diagnosticOperation, [
                    'operation' => $diagnosticOperation,
                    'api_base_url' => self::safeBaseUrl($this->baseUrl),
                    'local_error_kind' => 'api_jwt_configuration',
                    'transport_error_class' => $exception::class,
                    'transport_error_kind' => 'configuration',
                    'json_valid' => null,
                    'response_contract_valid' => null,
                    'correlation_id' => self::incomingCorrelationId(),
                ]);
            }
            return self::failure('api_unavailable', 503);
        } catch (\Throwable $exception) {
            $failure = self::failure('api_unavailable', 503);
            if ($diagnosticOperation !== null) {
                $diagnostic = [
                    'operation' => $diagnosticOperation,
                    'api_base_url' => self::safeBaseUrl($this->baseUrl),
                    'http_status' => null,
                    'api_error_code' => null,
                    'local_error_kind' => 'api_unavailable',
                    'transport_error_class' => $exception::class,
                    'transport_error_kind' => self::transportErrorKind($exception->getMessage()),
                    'json_valid' => null,
                    'response_contract_valid' => null,
                    'correlation_id' => self::incomingCorrelationId(),
                ];
                $this->logMvpApiDiagnostic($diagnosticOperation, $diagnostic);
                $failure['diagnostic'] = $diagnostic;
            }
            return $failure;
        }
    }

    /** Log temporary, non-sensitive diagnostics for the raw MVP read only. */
    private function logMvpApiDiagnostic(string $operation, array $details, bool $success = false): void
    {
        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        if (PHP_SAPI === 'cli' || !in_array($environment, ['dev', 'development', 'testing'], true)) {
            return;
        }

        $context = [
            'operation' => $operation,
            'runtime_sapi' => PHP_SAPI,
            'app_env' => $environment,
            'api_base_url' => self::safeBaseUrl($this->baseUrl),
            'http_status' => $details['http_status'] ?? null,
            'api_error_code' => self::safeApiErrorCode((string) ($details['api_error_code'] ?? '')),
            'local_error_kind' => self::safeDiagnosticCode((string) ($details['local_error_kind'] ?? '')),
            'transport_error_class' => self::safeDiagnosticClass((string) ($details['transport_error_class'] ?? '')),
            'transport_error_kind' => self::safeDiagnosticCode((string) ($details['transport_error_kind'] ?? '')),
            'json_valid' => $details['json_valid'] ?? null,
            'response_contract_valid' => $details['response_contract_valid'] ?? null,
            'api_error_envelope_valid' => $details['api_error_envelope_valid'] ?? null,
            'contract_failure' => self::safeDiagnosticCode((string) ($details['contract_failure'] ?? '')),
            'correlation_id' => self::safeCorrelationId((string) ($details['correlation_id'] ?? self::incomingCorrelationId() ?? '')),
        ];

        try {
            if ($success) {
                Logger::info('Checador MVP API diagnostic', $context);
            } else {
                Logger::warning('Checador MVP API diagnostic', $context);
            }
        } catch (\Throwable) {
            // Diagnostics must never affect the read or alter authorization.
        }
    }

    private static function logMvpApiInitializationFailure(?string $providedBaseUrl, \Throwable $exception): void
    {
        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        if (PHP_SAPI === 'cli' || !in_array($environment, ['dev', 'development', 'testing'], true)) {
            return;
        }

        $configuredBaseUrl = $providedBaseUrl
            ?? ($_ENV['CHECADOR_API_BASE_URL'] ?? getenv('CHECADOR_API_BASE_URL') ?: '');
        $context = [
            'operation' => 'api_client_configuration',
            'runtime_sapi' => PHP_SAPI,
            'app_env' => $environment,
            'api_base_url' => self::safeBaseUrl(rtrim(trim((string) $configuredBaseUrl), '/') . '/'),
            'http_status' => null,
            'api_error_code' => null,
            'local_error_kind' => 'configuration_error',
            'transport_error_class' => self::safeDiagnosticClass($exception::class),
            'transport_error_kind' => 'configuration',
            'json_valid' => null,
            'response_contract_valid' => null,
            'api_error_envelope_valid' => null,
            'contract_failure' => null,
            'correlation_id' => self::incomingCorrelationId(),
        ];

        try {
            Logger::warning('Checador MVP API diagnostic', $context);
        } catch (\Throwable) {
            // Diagnostics must never affect availability or authorization.
        }
    }

    private static function safeBaseUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '[invalid-base-url]';
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        return strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']) . $port . $path;
    }

    private static function safeApiErrorCode(string $code): ?string
    {
        $code = trim($code);
        return $code !== '' && preg_match('/\A[A-Za-z0-9_.-]{1,80}\z/', $code) === 1 ? $code : null;
    }

    private static function safeDiagnosticCode(string $code): ?string
    {
        return self::safeApiErrorCode($code);
    }

    private static function safeDiagnosticClass(string $class): ?string
    {
        return $class !== '' && preg_match('/\A[A-Za-z0-9_\\\\]{1,160}\z/', $class) === 1 ? $class : null;
    }

    private static function safeCorrelationId(string $id): ?string
    {
        $id = trim($id);
        return $id !== '' && preg_match('/\A[A-Za-z0-9._:-]{1,100}\z/', $id) === 1 ? $id : null;
    }

    private static function incomingCorrelationId(): ?string
    {
        foreach (['HTTP_X_REQUEST_ID', 'HTTP_X_CORRELATION_ID'] as $key) {
            $candidate = $_SERVER[$key] ?? '';
            if (is_string($candidate) && ($safe = self::safeCorrelationId($candidate)) !== null) {
                return $safe;
            }
        }
        return null;
    }

    private static function isErrorEnvelope(array $payload): bool
    {
        return (isset($payload['status']) && is_string($payload['status']))
            || (isset($payload['error']) && is_array($payload['error']));
    }

    private static function transportErrorKind(string $message): string
    {
        $message = strtolower($message);
        return match (true) {
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => 'timeout',
            str_contains($message, 'ssl'), str_contains($message, 'tls'), str_contains($message, 'certificate') => 'tls',
            str_contains($message, 'resolve'), str_contains($message, 'name or service not known'), str_contains($message, 'could not resolve') => 'dns',
            str_contains($message, 'connection refused') => 'connection_refused',
            str_contains($message, 'failed to connect'), str_contains($message, 'connection reset'), str_contains($message, 'network is unreachable') => 'connectivity',
            default => 'transport_failure',
        };
    }

    private function requestJson(
        Usuario $user,
        string $method,
        string $path,
        array $body,
        ?string $idempotencyKey = null,
        ?int $demoLocalityId = null
    ): array {
        try {
            if ($this->demoScenario !== null) {
                $response = $this->demoResponse($method, $path, [], $body, $user, $idempotencyKey, $demoLocalityId);
            } else {
                if (strtoupper($method) === 'POST' && !self::realApiPostAllowed($method, $path)) {
                    return self::failure('api_post_disabled', 403);
                }

                $token = ($this->tokenFactory)($user);
                if (!is_string($token) || trim($token) === '') {
                    return self::failure('api_authentication', 401);
                }

                $headers = [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ];
                if ($idempotencyKey !== null && trim($idempotencyKey) !== '') {
                    $headers['Idempotency-Key'] = $idempotencyKey;
                }

                $response = $this->http->request($method, $path, [
                    'headers' => $headers,
                    'json' => $body,
                    'http_errors' => false,
                ]);
            }
            $status = $response->getStatusCode();
            $raw = (string) $response->getBody();
            $payload = json_decode($raw, true);
            $payload = is_array($payload) ? $payload : [];

            if ($status < 200 || $status >= 300) {
                $errorCode = is_array($payload['error'] ?? null) ? ($payload['error']['code'] ?? '') : '';
                $statusCode = $errorCode !== '' ? $errorCode : ($payload['status'] ?? '');
                $kind = str_starts_with(ltrim($path, '/'), 'api/v2/temporary-access/')
                    ? self::mapTemporaryAccessApiError((string) $statusCode, $status)
                    : self::mapApiError((string) $statusCode, $status);
                return self::failure($kind, $status);
            }

            return ['ok' => true, 'http_status' => $status, 'payload' => $payload];
        } catch (ChecadorApiJwtConfigurationException) {
            return self::failure('api_unavailable', 503);
        } catch (\Throwable) {
            return self::failure('api_unavailable', 503);
        }
    }

    /**
     * Return contract-shaped local responses for the advanced-screen demo.
     * Allowlisted synthetic reads, the P3-G-C lifecycle actions, and the
     * P3-G-D administrative-evaluation command are handled locally in
     * development; every other operation stays blocked.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed> $body
     */
    private function demoResponse(
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        ?Usuario $user = null,
        ?string $idempotencyKey = null,
        ?int $demoLocalityId = null
    ): Response
    {
        $method = strtoupper($method);
        $path = ltrim($path, '/');
        $scenario = $this->demoScenario ?? 'invalid';

        if ($scenario === 'invalid') {
            return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
        }
        if ($scenario === 'service_unavailable') {
            return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
        }

        $incidenceOperation = $path === 'api/v2/incidences'
            || preg_match('~\Aapi/v2/incidences/[^/]+\z~', $path) === 1
            || preg_match('~\Aapi/v2/incidences/[^/]+/(?:resolve|authorize|reject|revoke)\z~', $path) === 1
            || $path === 'api/v2/administrative-evaluations'
            || $path === 'api/v2/administrative-evaluations/evaluate';
        if ($incidenceOperation && $scenario === 'incidence_service_unavailable') {
            return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
        }
        if ($incidenceOperation && $scenario === 'evaluation_service_unavailable') {
            return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
        }

        if ($method === 'POST'
            && preg_match('~\Aapi/v2/incidences/([^/]+)/(resolve|authorize|reject|revoke)\z~', $path, $matches) === 1) {
            return $this->demoLifecycleActionResponse(
                rawurldecode($matches[1]),
                $matches[2],
                $body,
                $user,
                $idempotencyKey,
                $demoLocalityId,
                $scenario
            );
        }

        if ($method === 'POST' && $path === 'api/v2/administrative-evaluations/evaluate') {
            return $this->demoAdministrativeEvaluationResponse($body, $user, $idempotencyKey, $scenario);
        }

        if ($method === 'GET' && $path === 'api/v2/incidences') {
            $workerId = (int) ($query['worker_id'] ?? 0);
            $workplaceId = (int) ($query['workplace_id'] ?? 0);
            $from = (string) ($query['from'] ?? '');
            $to = (string) ($query['to'] ?? '');
            if ($workerId < 1 || $workplaceId < 1 || !self::isCivilDate($from) || !self::isCivilDate($to) || $to < $from) {
                return self::demoJsonResponse(422, ['status' => 'BAD_REQUEST']);
            }

            $rows = $this->demoIncidenceRowsWithState(self::demoEvaluationIncidenceScenario($scenario), $workerId, $workplaceId, $from, $to, $user);
            if ($rows === null) {
                return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
            }
            $summaries = array_map(static function (array $row): array {
                $summary = $row;
                unset($summary['revisions']);
                return $summary;
            }, $rows);

            return self::demoJsonResponse(200, [
                'status' => 'success',
                'data' => [
                    'worker_id' => $workerId,
                    'workplace_id' => $workplaceId,
                    'query_range' => ['from' => $from, 'to' => $to],
                    'total_records' => count($summaries),
                    'incidences' => $summaries,
                ],
            ]);
        }

        if ($method === 'GET' && preg_match('~\Aapi/v2/incidences/([^/]+)\z~', $path, $matches) === 1) {
            $uuid = rawurldecode($matches[1]);
            $referenceDate = self::demoReferenceDate();
            $rows = $this->demoIncidenceRowsWithState(self::demoEvaluationIncidenceScenario($scenario), 900001, 14, $referenceDate, $referenceDate, $user);
            if ($rows === null) {
                return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
            }
            foreach ($rows as $row) {
                if ($row['uuid_incidencia'] === $uuid) {
                    return self::demoJsonResponse(200, ['status' => 'success', 'data' => $row]);
                }
            }
            return self::demoJsonResponse(404, ['status' => 'INCIDENCE_NOT_FOUND']);
        }

        if ($method === 'GET' && $path === 'api/v2/administrative-evaluations') {
            $workerId = (int) ($query['worker_id'] ?? 0);
            $workplaceId = (int) ($query['workplace_id'] ?? 0);
            $workDate = (string) ($query['work_date'] ?? '');
            if ($workerId < 1 || $workplaceId < 1 || !self::isCivilDate($workDate)) {
                return self::demoJsonResponse(422, ['status' => 'BAD_REQUEST']);
            }
            if ($scenario === 'incidence_evaluation_unavailable') {
                return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
            }

            $storedEvaluation = null;
            if ($user !== null) {
                $evaluationIdentity = self::demoEvaluationIdentity($workerId, $workplaceId, $workDate);
                $storedEvaluation = $this->demoStateTransaction($user, static function (array $state) use ($evaluationIdentity): array {
                    return ['value' => $state['evaluations'][$evaluationIdentity] ?? null];
                });
            }
            if (is_array($storedEvaluation)
                && (int) ($storedEvaluation['worker_id'] ?? 0) === $workerId
                && (int) ($storedEvaluation['workplace_id'] ?? 0) === $workplaceId
                && (string) ($storedEvaluation['work_date'] ?? '') === $workDate
                && is_array($storedEvaluation['evaluation'] ?? null)) {
                return self::demoJsonResponse(200, [
                    'status' => 'success',
                    'data' => [
                        'worker_id' => $workerId,
                        'workplace_id' => $workplaceId,
                        'work_date' => $workDate,
                        'evaluation_exists' => true,
                        'evaluation' => $storedEvaluation['evaluation'],
                    ],
                ]);
            }

            $rows = $this->demoIncidenceRowsWithState(self::demoEvaluationIncidenceScenario($scenario), $workerId, $workplaceId, $workDate, $workDate, $user);
            if ($rows === null) {
                return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
            }
            $evaluationExists = in_array($scenario, ['incidence_conflict', 'evaluation_incidence_conflict'], true)
                && $workerId === 900001
                && $workplaceId === 14
                && $workDate === self::demoReferenceDate();
            $evaluation = null;
            if ($evaluationExists) {
                $evaluation = [
                    'revision_number' => 1,
                    'administrative_status' => 'REVIEW_REQUIRED',
                    'human_review_status' => 'PENDING',
                    'fingerprint' => 'DEMO-ONLY-FINGERPRINT-CONFLICT-' . str_replace('-', '', self::demoReferenceDate()),
                    'algorithm_version' => 'demo-fixture-v4a',
                    'policy_version' => 'demo-only-conflict-policy',
                    'policy_rule_version' => null,
                    'associated_calculation_revision_id' => 900001,
                    'created_at_utc' => self::demoReferenceDate() . 'T20:00:00Z',
                    'linked_incidences' => array_map(static fn(array $row): array => [
                        'uuid_incidencia' => $row['uuid_incidencia'],
                        'category' => $row['category'],
                        'revision_number' => $row['current_revision'],
                        'application_status' => 'REVIEW_REQUIRED',
                    ], $rows),
                    'linked_adjustments' => [],
                ];
            }

            return self::demoJsonResponse(200, [
                'status' => 'success',
                'data' => [
                    'worker_id' => $workerId,
                    'workplace_id' => $workplaceId,
                    'work_date' => $workDate,
                    'evaluation_exists' => $evaluationExists,
                    'evaluation' => $evaluation,
                ],
            ]);
        }

        if ($method === 'GET' && $path === 'api/v2/shift-templates') {
            $page = (int) ($query['page'] ?? 1);
            $perPage = (int) ($query['per_page'] ?? 100);
            if ($page !== 1 || $perPage < 1 || $perPage > 200) return self::demoJsonResponse(422, ['status' => 'BAD_REQUEST']);
            $items = [
                ['id' => 900001, 'clave' => 'DEMO-DIURNO', 'version' => 1, 'nombre' => 'Turno diurno · demo', 'tipo_turno' => 'REGULAR', 'es_activo' => 1],
                ['id' => 900002, 'clave' => 'DEMO-NOCTURNO', 'version' => 1, 'nombre' => 'Turno nocturno · demo', 'tipo_turno' => 'REGULAR', 'es_activo' => 1],
            ];
            return self::demoJsonResponse(200, ['status' => 'success', 'data' => ['page' => 1, 'per_page' => $perPage, 'items' => $items]]);
        }

        if ($method === 'GET' && preg_match('~\Aapi/v2/shift-templates/([^/]+)/versions/([1-9][0-9]*)\z~', $path, $matches) === 1) {
            $key = rawurldecode($matches[1]);
            $version = (int) $matches[2];
            if ($version !== 1 || !in_array($key, ['DEMO-DIURNO', 'DEMO-NOCTURNO'], true)) return self::demoJsonResponse(404, ['status' => 'NOT_FOUND']);
            $night = $key === 'DEMO-NOCTURNO';
            return self::demoJsonResponse(200, ['status' => 'success', 'data' => [
                'id' => $night ? 900002 : 900001,
                'clave' => $key,
                'version' => 1,
                'nombre' => $night ? 'Turno nocturno · demo' : 'Turno diurno · demo',
                'tipo_turno' => 'REGULAR',
                'es_activo' => 1,
                'segmentos' => [[
                    'id' => $night ? 900002 : 900001,
                    'ordinal' => 1,
                    'tipo_segmento' => 'WORK',
                    'hora_inicio_local' => $night ? '22:00:00' : '08:00:00',
                    'hora_fin_local' => $night ? '06:00:00' : '16:00:00',
                    'dia_offset_inicio' => 0,
                    'dia_offset_fin' => $night ? 1 : 0,
                ]],
            ]]);
        }

        if ($method === 'GET' && $path === 'api/v2/shift-assignments') {
            $workerId = (int) ($query['id_personal'] ?? 0);
            $workplaceId = (int) ($query['id_estacion'] ?? 0);
            $page = (int) ($query['page'] ?? 1);
            $perPage = (int) ($query['per_page'] ?? 50);
            if ($workerId !== 900001 || $workplaceId !== 14 || $page !== 1 || $perPage < 1 || $perPage > 200) {
                return self::demoJsonResponse(200, ['status' => 'success', 'data' => ['page' => $page, 'per_page' => $perPage, 'items' => []]]);
            }
            if ($scenario === 'empty' || $scenario === 'unscheduled') {
                $items = [];
            } else {
                $items = [];
                foreach ([
                    'LUNES' => 1, 'MARTES' => 1, 'MIERCOLES' => 1,
                    'JUEVES' => 1, 'VIERNES' => 1, 'SABADO' => 0, 'DOMINGO' => 0,
                ] as $weekday => $templateId) {
                    if ($scenario === 'day_off') $templateId = 0;
                    if ($scenario === 'night_schedule') $templateId = 2;
                    $items[] = [
                        'id' => 900000 + count($items) + 1,
                        'id_personal' => 900001,
                        'id_estacion' => 14,
                        'id_plantilla' => $templateId === 0 ? null : ($templateId === 2 ? 900002 : 900001),
                        'dia_semana' => $weekday,
                        'valido_desde' => self::demoReferenceDate(),
                        'valido_hasta' => null,
                        'es_activo' => 1,
                        'registrado_at_utc' => '2026-10-19T12:00:00Z',
                    ];
                }
            }
            return self::demoJsonResponse(200, ['status' => 'success', 'data' => ['page' => 1, 'per_page' => $perPage, 'items' => $items]]);
        }

        if ($method === 'GET' && preg_match('~\Aapi/v2/workplaces/([1-9][0-9]*)/timezone\z~', $path, $matches) === 1) {
            if ($scenario === 'timezone_unavailable') {
                return self::demoJsonResponse(404, ['status' => 'WORKPLACE_TIMEZONE_UNRESOLVED']);
            }

            $workplaceId = (int) $matches[1];
            $date = (string) ($query['date'] ?? '');
            if (!self::isCivilDate($date)) {
                return self::demoJsonResponse(422, ['status' => 'BAD_DATE']);
            }

            $zone = new \DateTimeZone('America/Mexico_City');
            $utc = new \DateTimeZone('UTC');
            $localStart = new \DateTimeImmutable($date . ' 00:00:00', $zone);
            $localEnd = $localStart->modify('+1 day');

            return self::demoJsonResponse(200, [
                'status' => 'success',
                'data' => [
                    'workplace_id' => $workplaceId,
                    'local_date' => $date,
                    'timezone' => 'America/Mexico_City',
                    'from_utc' => $localStart->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                    'to_utc' => $localEnd->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                ],
            ]);
        }

        if ($method === 'POST' && $path === 'api/v2/punch-events/read-snapshots') {
            $fromUtc = (string) ($body['from'] ?? '');
            $toUtc = (string) ($body['to'] ?? '');
            if ($fromUtc === '' || $toUtc === '' || (int) ($body['station_id'] ?? 0) < 1) {
                return self::demoJsonResponse(422, ['status' => 'BAD_REQUEST']);
            }

            $workplaceId = (int) ($body['station_id'] ?? 0);
            $snapshotToken = 'demo-server-only-' . $workplaceId . '-' . rtrim(strtr(base64_encode($fromUtc), '+/', '-_'), '=');
            $snapshotAuditRef = self::demoSnapshotAuditReference($workplaceId, $fromUtc);
            $recordCount = count(self::demoEvents($workplaceId, $fromUtc, $scenario));
            $this->demoSnapshots[$snapshotToken] = [
                'workplace_id' => $workplaceId,
                'from_utc' => $fromUtc,
                'snapshot_audit_ref' => $snapshotAuditRef,
            ];
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            return self::demoJsonResponse(201, [
                'status' => 'success',
                'data' => [
                    'snapshot' => [
                        'snapshot_token' => $snapshotToken,
                        'snapshot_audit_ref' => $snapshotAuditRef,
                        'status' => 'READY',
                        'created_at' => $now->format('Y-m-d\TH:i:s\Z'),
                        'expires_at' => $now->modify('+1 day')->format('Y-m-d\TH:i:s\Z'),
                        'total_records' => $recordCount,
                    ],
                    'completeness' => [
                        'level_1_api_snapshot' => true,
                        'level_2_ingestion_completeness' => false,
                        'status' => 'LEVEL_1_COMPLETE_ONLY',
                    ],
                ],
            ]);
        }

        if ($method === 'GET' && $path === 'api/v2/level2/window-status') {
            $aggregate = self::demoLevel2Status($scenario);
            $referenceStartUtc = (new \DateTimeImmutable(self::demoReferenceDate() . ' 00:00:00', new \DateTimeZone('America/Mexico_City')))
                ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
            $governedEventCount = count(self::demoEvents($query['station_id'] ?? 1, $query['from'] ?? $referenceStartUtc, $scenario));
            $dimension = match ($aggregate) {
                'VERIFIED' => 'VERIFIED',
                'INCOMPLETE' => 'INCOMPLETE',
                default => 'UNKNOWN',
            };

            return self::demoJsonResponse(200, [
                'status' => 'success',
                'data' => [
                    'window_level2_status' => $aggregate,
                    'delivery_completeness' => $dimension,
                    'operational_coverage' => $dimension,
                    'time_window_attribution' => $dimension,
                    'has_zero_governed_events' => $governedEventCount === 0,
                    'governed_event_count' => $governedEventCount,
                ],
            ]);
        }

        if ($method === 'GET' && preg_match('~\Aapi/v2/workers/([1-9][0-9]*)/effective-schedule\z~', $path, $matches) === 1) {
            $workerId = (int) $matches[1];
            $workplaceId = (int) ($query['id_estacion'] ?? 0);
            $date = (string) ($query['fecha_local'] ?? '');
            if ($workplaceId < 1 || !self::isCivilDate($date)) {
                return self::demoJsonResponse(422, ['status' => 'BAD_REQUEST']);
            }

            return self::demoJsonResponse(200, [
                'status' => 'success',
                'data' => self::demoScheduleData($workerId, $workplaceId, $date, $scenario),
            ]);
        }

        if ($method === 'POST' && $path === 'api/v2/journeys/evaluate') {
            $workerId = (int) ($body['id_personal'] ?? 0);
            $workplaceId = (int) ($body['id_estacion'] ?? 0);
            $date = (string) ($body['fecha_local'] ?? '');
            if ($workerId < 1 || $workplaceId < 1 || !self::isCivilDate($date)) {
                return self::demoJsonResponse(422, ['status' => 'BAD_REQUEST']);
            }

            $snapshotAuditRef = trim((string) ($body['snapshot_audit_ref'] ?? ''));
            $snapshotContext = null;
            foreach ($this->demoSnapshots as $snapshot) {
                if (($snapshot['snapshot_audit_ref'] ?? null) === $snapshotAuditRef
                    && $snapshot['workplace_id'] === $workplaceId) {
                    $snapshotContext = $snapshot;
                    break;
                }
            }
            if (!is_array($snapshotContext)) {
                return self::demoJsonResponse(409, ['status' => 'SNAPSHOT_EXPIRED']);
            }
            $fromUtc = $snapshotContext['from_utc'];
            try {
                $snapshotLocalDate = (new \DateTimeImmutable($fromUtc, new \DateTimeZone('UTC')))
                    ->setTimezone(new \DateTimeZone('America/Mexico_City'))
                    ->format('Y-m-d');
            } catch (\Throwable) {
                return self::demoJsonResponse(422, ['status' => 'BAD_REQUEST']);
            }
            if ($snapshotLocalDate !== $date
                || !in_array($workerId, array_map(
                    static fn(array $event): int => (int) ($event['id_personal_resuelto'] ?? 0),
                    self::demoEvents($workplaceId, $fromUtc, $scenario)
                ), true)) {
                return self::demoJsonResponse(409, ['status' => 'SNAPSHOT_CONTEXT_MISMATCH']);
            }

            return self::demoJsonResponse(200, [
                'status' => 'success',
                'data' => self::demoJourneyData(
                    $workerId,
                    $workplaceId,
                    $date,
                    $snapshotAuditRef,
                    $scenario,
                    self::demoEvents($workplaceId, $fromUtc, $scenario)
                ),
            ]);
        }

        if ($method === 'GET' && preg_match('~\Aapi/v2/punch-events/read-snapshots/([A-Za-z0-9._:-]+)/events\z~', $path, $matches) === 1) {
            return $this->demoSnapshotPageResponse($matches[1], $query, $scenario);
        }

        // In demo mode every non-whitelisted operation is blocked locally.
        return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
    }

    /**
     * Keep development fixture paging contract-shaped. A canonical worker
     * filter is applied to the complete synthetic snapshot before slicing;
     * unresolved identities and other workers cannot become matches.
     *
     * @param array<string,mixed> $query
     */
    private function demoSnapshotPageResponse(
        string $snapshotToken,
        array $query,
        string $scenario,
        ?int $resolvedWorkerId = null
    ): Response {
        if (in_array($scenario, ['invalid', 'service_unavailable'], true)) {
            return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
        }
        if ($scenario === 'snapshot_expired') {
            return self::demoJsonResponse(404, ['status' => 'SNAPSHOT_EXPIRED']);
        }

        $snapshot = $this->demoSnapshots[$snapshotToken] ?? self::parseDemoSnapshotToken($snapshotToken);
        if ($snapshot === null) {
            return self::demoJsonResponse(404, ['status' => 'SNAPSHOT_EXPIRED']);
        }
        $limit = filter_var($query['limit'] ?? self::DEFAULT_PAGE_SIZE, FILTER_VALIDATE_INT);
        if (!is_int($limit) || $limit < 1 || $limit > self::MAX_PAGE_SIZE) {
            return self::demoJsonResponse(422, ['status' => 'BAD_REQUEST']);
        }

        $items = self::demoEvents($snapshot['workplace_id'], $snapshot['from_utc'], $scenario);
        if ($resolvedWorkerId !== null) {
            $items = array_values(array_filter(
                $items,
                static fn(array $event): bool => self::positiveInt($event['id_personal_resuelto'] ?? null) === $resolvedWorkerId
            ));
        }
        $matchedRecords = count($items);
        $cursor = trim((string) ($query['cursor'] ?? ''));
        $offset = 0;
        if ($cursor !== '') {
            $pattern = $resolvedWorkerId === null
                ? '/\Ademo-offset:(0|[1-9][0-9]*)\z/'
                : '/\Ademo-filtered:' . $resolvedWorkerId . ':(0|[1-9][0-9]*)\z/';
            if (preg_match($pattern, $cursor, $cursorMatches) !== 1) {
                return self::demoJsonResponse(422, ['status' => 'INVALID_CURSOR']);
            }
            $offset = (int) $cursorMatches[1];
        }
        if ($offset > $matchedRecords) {
            return self::demoJsonResponse(422, ['status' => 'INVALID_CURSOR']);
        }

        $page = array_slice($items, $offset, $limit);
        $nextOffset = $offset + count($page);
        $hasMore = $nextOffset < $matchedRecords;
        $nextCursor = $hasMore
            ? ($resolvedWorkerId === null
                ? 'demo-offset:' . $nextOffset
                : 'demo-filtered:' . $resolvedWorkerId . ':' . $nextOffset)
            : null;

        return self::demoJsonResponse(200, [
            'status' => 'success',
            'data' => [
                'items' => $page,
                'pagination' => [
                    'next_cursor' => $nextCursor,
                    'has_more' => $hasMore,
                    'page_size' => count($page),
                    'matched_records' => $matchedRecords,
                ],
            ],
        ]);
    }

    /** Map evaluation fixtures to the already-established P3-G incidence rows. */
    private static function demoEvaluationIncidenceScenario(string $scenario): string
    {
        return match ($scenario) {
            'evaluation_pending_review' => 'incidence_pending_review',
            'evaluation_incidence_conflict' => 'incidence_conflict',
            'evaluation_available' => 'incidence_active',
            default => $scenario,
        };
    }

    private static function demoEvaluationIdentity(int $workerId, int $workplaceId, string $workDate): string
    {
        return hash('sha256', $workerId . "\0" . $workplaceId . "\0" . $workDate);
    }

    /**
     * Contract-shaped P3-G-D synthetic command. It uses only fixed outcomes,
     * retains the canonical demo tuple, and never computes a classification.
     */
    private function demoAdministrativeEvaluationResponse(
        array $body,
        ?Usuario $user,
        ?string $idempotencyKey,
        string $scenario
    ): Response {
        if ($user === null || (int) ($user->id ?? 0) < 1) {
            return self::demoJsonResponse(401, ['error' => ['code' => 'UNAUTHORIZED']]);
        }
        if ($scenario === 'evaluation_unauthorized') {
            return self::demoJsonResponse(403, ['error' => ['code' => 'FUNCTION_PERMISSION_DENIED']]);
        }
        if ($idempotencyKey === null || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey)) {
            return self::demoJsonResponse(400, ['error' => ['code' => 'INVALID_PAYLOAD']]);
        }

        $workerId = (int) ($body['worker_id'] ?? 0);
        $workplaceId = (int) ($body['workplace_id'] ?? 0);
        $workDate = (string) ($body['work_date'] ?? '');
        $authorizeFalta = ($body['authorize_falta'] ?? false) === true;
        $expectedCalculationId = $body['expected_calculation_revision_id'] ?? null;
        $referenceDate = self::demoReferenceDate();
        if ($workerId !== 900001 || $workplaceId !== 14 || $workDate !== $referenceDate
            || ($expectedCalculationId !== null && (!is_int($expectedCalculationId) || $expectedCalculationId < 1))) {
            return self::demoJsonResponse(403, ['error' => ['code' => 'STATION_SCOPE_DENIED']]);
        }

        if ($scenario === 'evaluation_stale_calculation') {
            return self::demoJsonResponse(409, ['error' => ['code' => 'STALE_REVISION_CONFLICT']]);
        }
        if ($scenario === 'evaluation_falta_authorized' && !$authorizeFalta) {
            return self::demoJsonResponse(403, ['error' => ['code' => 'FUNCTION_PERMISSION_DENIED']]);
        }
        if ($authorizeFalta && $scenario !== 'evaluation_falta_authorized') {
            return self::demoJsonResponse(403, ['error' => ['code' => 'FUNCTION_PERMISSION_DENIED']]);
        }

        $preset = match ($scenario) {
            'evaluation_pending_review', 'evaluation_duplicate' => ['REVIEW_REQUIRED', 'PENDING_REVIEW'],
            'evaluation_data_incomplete' => ['DATA_INCOMPLETE', 'PENDING_REVIEW'],
            'evaluation_incidence_conflict' => ['OVERLAP_CONFLICT', 'PENDING_REVIEW'],
            'evaluation_available' => ['VACATION_APPLIED', 'NOT_REQUIRED'],
            'evaluation_falta_authorized' => ['FALTA', 'NOT_REQUIRED'],
            default => null,
        };
        if (!is_array($preset)) {
            return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
        }

        $incidenceScenario = self::demoEvaluationIncidenceScenario($scenario);
        $fixtureRows = self::demoIncidenceFixtureRows($incidenceScenario, $workerId, $workplaceId, $workDate, $workDate);
        $linkedIncidences = [];
        foreach ($fixtureRows as $row) {
            $applicationStatus = match ($scenario) {
                'evaluation_available' => 'VACATION_APPLIED',
                'evaluation_incidence_conflict' => 'OVERLAP_CONFLICT',
                default => (string) ($row['current_status'] ?? 'REVIEW_REQUIRED'),
            };
            $linkedIncidences[] = [
                'uuid_incidencia' => (string) $row['uuid_incidencia'],
                'category' => (string) $row['category'],
                'revision_number' => (int) $row['current_revision'],
                'application_status' => $applicationStatus,
            ];
        }

        $evaluation = [
            'revision_number' => 1,
            'administrative_status' => $preset[0],
            'human_review_status' => $preset[1],
            'fingerprint' => 'DEMO-ONLY-NOT-A-CRYPTOGRAPHIC-FINGERPRINT',
            'algorithm_version' => 'demo-fixture-v4c',
            'policy_version' => 'DEMO-ONLY-NOT-A-POLICY',
            'policy_rule_version' => null,
            'associated_calculation_revision_id' => 900001,
            'created_at_utc' => $referenceDate . 'T20:00:00Z',
            'linked_incidences' => $linkedIncidences,
            'linked_adjustments' => [],
        ];
        $resultData = [
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
            'revision_number' => $evaluation['revision_number'],
            'administrative_status' => $evaluation['administrative_status'],
            'human_review_status' => $evaluation['human_review_status'],
            'fingerprint' => $evaluation['fingerprint'],
            'policy_version' => $evaluation['policy_version'],
            'policy_rule_version' => $evaluation['policy_rule_version'],
            'created_at_utc' => $evaluation['created_at_utc'],
        ];
        $identity = self::demoEvaluationIdentity($workerId, $workplaceId, $workDate);
        $idempotencyIdentity = hash('sha256', 'administrative-evaluation' . "\0" . $idempotencyKey);
        $semanticPayload = json_encode([
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'work_date' => $workDate,
            'expected_calculation_revision_id' => $expectedCalculationId,
            'authorize_falta' => $authorizeFalta,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($semanticPayload)) {
            return self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
        }
        $payloadFingerprint = hash('sha256', $semanticPayload);

        $storedResponse = $this->demoStateTransaction($user, static function (array $state) use (
            $identity,
            $idempotencyIdentity,
            $payloadFingerprint,
            $workerId,
            $workplaceId,
            $workDate,
            $evaluation,
            $resultData
        ): array {
            $prior = $state['evaluation_idempotency'][$idempotencyIdentity] ?? null;
            if (is_array($prior)) {
                if (($prior['fingerprint'] ?? null) !== $payloadFingerprint || !is_array($prior['response'] ?? null)) {
                    return ['value' => self::demoJsonResponse(409, ['error' => ['code' => 'IDEMPOTENCY_CONFLICT']])];
                }
                return ['value' => self::demoJsonResponse(200, ['status' => 'success', 'data' => $prior['response']])];
            }

            $existing = $state['evaluations'][$identity] ?? null;
            if (is_array($existing) && is_array($existing['evaluation'] ?? null) && is_array($existing['response'] ?? null)) {
                $responseData = $existing['response'];
            } else {
                $responseData = $resultData;
                $state['evaluations'][$identity] = [
                    'worker_id' => $workerId,
                    'workplace_id' => $workplaceId,
                    'work_date' => $workDate,
                    'evaluation' => $evaluation,
                    'response' => $responseData,
                    'history' => [$evaluation],
                ];
            }
            $state['evaluation_idempotency'][$idempotencyIdentity] = [
                'fingerprint' => $payloadFingerprint,
                'response' => $responseData,
            ];
            return [
                'state' => $state,
                'persist' => true,
                'value' => self::demoJsonResponse(200, ['status' => 'success', 'data' => $responseData]),
            ];
        });

        return $storedResponse instanceof Response
            ? $storedResponse
            : self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
    }

    private function demoIncidenceRowsWithState(
        string $scenario,
        int $workerId,
        int $localityId,
        string $from,
        string $to,
        ?Usuario $user
    ): ?array {
        $rows = self::demoIncidenceFixtureRows($scenario, $workerId, $localityId, $from, $to);
        if ($user === null || $rows === []) {
            return $rows;
        }

        $result = $this->demoStateTransaction($user, static function (array $state) use ($rows): array {
            foreach ($rows as &$row) {
                $saved = $state['incidences'][$row['uuid_incidencia']] ?? null;
                if (is_array($saved)
                    && (int) ($saved['worker_id'] ?? 0) === (int) $row['worker_id']
                    && (int) ($saved['workplace_id'] ?? 0) === (int) $row['workplace_id']
                    && (string) ($saved['source_classification'] ?? '') === 'SYNTHETIC_DEMO_FIXTURE') {
                    $row = $saved;
                }
            }
            unset($row);
            return ['value' => $rows];
        });

        return is_array($result) ? $result : null;
    }

    private function demoLifecycleActionResponse(
        string $uuid,
        string $action,
        array $body,
        ?Usuario $user,
        ?string $idempotencyKey,
        ?int $demoLocalityId,
        string $scenario
    ): Response {
        if ($user === null || (int) ($user->id ?? 0) < 1) {
            return self::demoJsonResponse(401, ['error' => ['code' => 'UNAUTHORIZED']]);
        }
        if ($demoLocalityId === null || $demoLocalityId < 1) {
            return self::demoJsonResponse(403, ['error' => ['code' => 'STATION_SCOPE_DENIED']]);
        }
        if ($idempotencyKey === null || !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey)) {
            return self::demoJsonResponse(400, ['error' => ['code' => 'INVALID_PAYLOAD']]);
        }

        $referenceDate = self::demoReferenceDate();
        $fixtureRows = self::demoIncidenceFixtureRows($scenario, 900001, 14, $referenceDate, $referenceDate);
        $fixture = null;
        foreach ($fixtureRows as $candidate) {
            if (($candidate['uuid_incidencia'] ?? null) === $uuid) {
                $fixture = $candidate;
                break;
            }
        }
        if ($fixture === null) {
            return self::demoJsonResponse(404, ['error' => ['code' => 'INCIDENCE_NOT_FOUND']]);
        }
        if ((int) ($fixture['workplace_id'] ?? 0) !== $demoLocalityId) {
            return self::demoJsonResponse(403, ['error' => ['code' => 'STATION_SCOPE_DENIED']]);
        }

        $fingerprintPayload = json_encode([
            'uuid_incidencia' => $uuid,
            'action' => $action,
            'body' => $body,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($fingerprintPayload)) {
            return self::demoJsonResponse(400, ['error' => ['code' => 'INVALID_PAYLOAD']]);
        }
        $fingerprint = hash('sha256', $fingerprintPayload);
        $idempotencyIdentity = hash('sha256', $idempotencyKey);

        $response = $this->demoStateTransaction($user, static function (array $state) use (
            $uuid,
            $action,
            $body,
            $fixture,
            $demoLocalityId,
            $fingerprint,
            $idempotencyIdentity
        ): array {
            $prior = $state['idempotency'][$idempotencyIdentity] ?? null;
            if (is_array($prior)) {
                if (($prior['fingerprint'] ?? '') !== $fingerprint || !is_array($prior['response'] ?? null)) {
                    return ['value' => self::demoJsonResponse(409, ['error' => ['code' => 'IDEMPOTENCY_CONFLICT']])];
                }
                return ['value' => self::demoJsonResponse(200, ['status' => 'success', 'data' => $prior['response']])];
            }

            $row = $state['incidences'][$uuid] ?? $fixture;
            if ((int) ($row['workplace_id'] ?? 0) !== $demoLocalityId) {
                return ['value' => self::demoJsonResponse(403, ['error' => ['code' => 'STATION_SCOPE_DENIED']])];
            }
            $expectedRevision = $body['expected_current_revision'] ?? null;
            if (!is_int($expectedRevision) || $expectedRevision < 1) {
                return ['value' => self::demoJsonResponse(400, ['error' => ['code' => 'INVALID_PAYLOAD']])];
            }
            if ($expectedRevision !== (int) ($row['current_revision'] ?? 0)) {
                return ['value' => self::demoJsonResponse(409, ['error' => ['code' => 'STALE_REVISION_CONFLICT']])];
            }

            $currentStatus = (string) ($row['current_status'] ?? '');
            $terminalForResolve = in_array($currentStatus, ['CANCELLED', 'REVOKED'], true);
            $terminalForAuthorization = in_array($currentStatus, ['CANCELLED', 'REVOKED', 'REJECTED'], true);
            $terminalForRevoke = in_array($currentStatus, ['CANCELLED', 'REVOKED'], true);
            $invalidTransition = match ($action) {
                'resolve' => $terminalForResolve,
                'authorize', 'reject' => $terminalForAuthorization,
                'revoke' => $terminalForRevoke,
                default => true,
            };
            if ($invalidTransition) {
                return ['value' => self::demoJsonResponse(409, ['error' => ['code' => 'INVALID_LIFECYCLE_TRANSITION']])];
            }

            $newStatus = match ($action) {
                'resolve' => 'RESOLVED_PENDING_AUTHORIZATION',
                'authorize' => 'ACTIVE',
                'reject' => 'REJECTED',
                'revoke' => 'CANCELLED',
            };
            $reasonField = match ($action) {
                'resolve' => 'resolution_notes',
                'authorize' => 'authorization_notes',
                'reject' => 'rejection_reason',
                'revoke' => 'revocation_reason',
            };
            $reason = trim((string) ($body[$reasonField] ?? ''));
            if (in_array($action, ['resolve', 'reject', 'revoke'], true) && $reason === '') {
                return ['value' => self::demoJsonResponse(400, ['error' => ['code' => 'INVALID_PAYLOAD']])];
            }
            $evidence = trim((string) ($body['evidence_reference'] ?? ''));
            $revisionNumber = (int) $row['current_revision'] + 1;
            $createdAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
            $revision = [
                'revision_number' => $revisionNumber,
                'status' => $newStatus,
                'reason' => $reason !== '' ? 'DEMO SINTÉTICO — ' . $reason : 'DEMO SINTÉTICO — Acción sin nota adicional.',
                'evidence_reference' => $evidence !== '' ? $evidence : null,
                'created_by_user_id' => 900099,
                'approved_by_user_id' => $action === 'resolve' ? null : 900099,
                'created_at_utc' => $createdAt,
            ];
            $row['current_status'] = $newStatus;
            $row['current_revision'] = $revisionNumber;
            $row['reason'] = $revision['reason'];
            $row['revisions'][] = $revision;
            if ($action === 'authorize' || $action === 'reject') {
                $row['approved_by_user_id'] = 900099;
            }

            $resultData = [
                'uuid_incidencia' => $uuid,
                'status' => $newStatus,
                'current_revision' => $revisionNumber,
            ];
            $resultField = match ($action) {
                'resolve' => 'resolution_revision_number',
                'authorize' => 'authorized_revision_number',
                'reject' => 'rejected_revision_number',
                'revoke' => 'revocation_revision_number',
            };
            $resultData[$resultField] = $revisionNumber;
            if ($action === 'resolve') {
                $resultData['resolution_status'] = $newStatus;
            }
            if (in_array($action, ['authorize', 'reject', 'revoke'], true)) {
                $resultData[match ($action) {
                    'authorize' => 'authorized_by_user_id',
                    'reject' => 'rejected_by_user_id',
                    'revoke' => 'revoked_by_user_id',
                }] = 900099;
                $resultData[match ($action) {
                    'authorize' => 'authorized_at_utc',
                    'reject' => 'rejected_at_utc',
                    'revoke' => 'revoked_at_utc',
                }] = $createdAt;
            }

            $state['incidences'][$uuid] = $row;
            $state['idempotency'][$idempotencyIdentity] = [
                'fingerprint' => $fingerprint,
                'response' => $resultData,
            ];
            return [
                'state' => $state,
                'persist' => true,
                'value' => self::demoJsonResponse(200, ['status' => 'success', 'data' => $resultData]),
            ];
        });

        return $response instanceof Response
            ? $response
            : self::demoJsonResponse(503, ['status' => 'SERVER_ERROR']);
    }

    /**
     * Per-developer, per-scenario file state for PRG demo actions. It is
     * deliberately separate from the authentication session and application DB.
     */
    private function demoStateTransaction(Usuario $user, callable $operation): mixed
    {
        if ($this->demoScenario === null || (int) ($user->id ?? 0) < 1) {
            return null;
        }

        $runtimeUid = function_exists('posix_geteuid') ? (string) posix_geteuid() : 'current-runtime';
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'portal3-checador-demo-v4b-' . $runtimeUid;
        if (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory)) {
            return null;
        }
        if (is_link($directory) || !is_dir($directory)) {
            return null;
        }
        @chmod($directory, 0700);
        $directoryMode = @fileperms($directory);
        if (!is_int($directoryMode) || ($directoryMode & 0077) !== 0) {
            return null;
        }
        if (function_exists('posix_geteuid') && @fileowner($directory) !== posix_geteuid()) {
            return null;
        }

        $applicationRoot = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
        $stateKey = hash('sha256', $applicationRoot . "\0" . (string) ((int) $user->id) . "\0" . $this->demoScenario);
        $statePath = $directory . DIRECTORY_SEPARATOR . $stateKey . '.json';
        $lockPath = $directory . DIRECTORY_SEPARATOR . $stateKey . '.lock';
        if (is_link($statePath) || is_link($lockPath)) {
            return null;
        }
        $lock = @fopen($lockPath, 'c+b');
        if (!is_resource($lock)) {
            return null;
        }
        @chmod($lockPath, 0600);
        try {
            if (!flock($lock, LOCK_EX)) {
                return null;
            }
            $state = [
                'version' => 1,
                'incidences' => [],
                'idempotency' => [],
                'evaluations' => [],
                'evaluation_idempotency' => [],
            ];
            if (is_file($statePath)) {
                $raw = @file_get_contents($statePath);
                if (!is_string($raw) || strlen($raw) > 1048576) {
                    return null;
                }
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (!is_array($decoded)
                        || ($decoded['version'] ?? null) !== 1
                        || !is_array($decoded['incidences'] ?? null)
                        || !is_array($decoded['idempotency'] ?? null)
                        || (array_key_exists('evaluations', $decoded) && !is_array($decoded['evaluations']))
                        || (array_key_exists('evaluation_idempotency', $decoded) && !is_array($decoded['evaluation_idempotency']))) {
                        return null;
                    }
                    $state = array_replace($state, $decoded);
                }
            }

            $outcome = $operation($state);
            if (!is_array($outcome) || !array_key_exists('value', $outcome)) {
                return null;
            }
            if (($outcome['persist'] ?? false) === true) {
                $newState = $outcome['state'] ?? null;
                if (!is_array($newState)) {
                    return null;
                }
                $json = json_encode($newState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $temporaryPath = @tempnam($directory, '.state-');
                if (!is_string($temporaryPath)) {
                    return null;
                }
                @chmod($temporaryPath, 0600);
                $written = @file_put_contents($temporaryPath, $json, LOCK_EX);
                if (!is_int($written) || $written !== strlen($json) || !@rename($temporaryPath, $statePath)) {
                    @unlink($temporaryPath);
                    return null;
                }
                @chmod($statePath, 0600);
            }
            return $outcome['value'];
        } catch (\Throwable) {
            return null;
        } finally {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    /** @return array<int,array<string,mixed>> */
    private static function demoEvents(mixed $workplaceId, mixed $fromUtc, string $scenario): array
    {
        $stationId = is_numeric($workplaceId) && (int) $workplaceId > 0 ? (int) $workplaceId : 1;
        try {
            $base = new \DateTimeImmutable((string) $fromUtc, new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            $base = (new \DateTimeImmutable(self::demoReferenceDate() . ' 00:00:00', new \DateTimeZone('America/Mexico_City')))
                ->setTimezone(new \DateTimeZone('UTC'));
        }

        $event = static function (
            string $suffix,
            ?int $workerId,
            ?string $offset,
            string $method = 'BIOMETRIC',
            string $fixtureVersion = 'V3A'
        ) use ($stationId, $base): array {
            return [
                'event_uuid' => 'DEMO-CHECADOR-' . $fixtureVersion . '-' . $suffix,
                'id_estacion_resuelta' => $stationId,
                'id_personal_resuelto' => $workerId,
                'ocurrido_at_utc' => $offset === null ? null : $base->modify($offset)->format('Y-m-d H:i:s'),
                'metodo_captura' => $method,
                'processing_status' => 'DEMO_ONLY',
                'integrity' => ['status' => 'UNVERIFIABLE'],
            ];
        };

        return match ($scenario) {
            'empty' => [],
            'worker_filter_dataset' => [
                $event('FILTER-OTHER-FIRST', 900002, '+8 hours'),
                $event('FILTER-UNRESOLVED', null, null, 'CARD'),
                $event('FILTER-TARGET-THIRD', 900001, '+10 hours'),
                $event('FILTER-OTHER-FOURTH', 900003, '+11 hours'),
                $event('FILTER-TARGET-FIFTH', 900001, '+12 hours'),
            ],
            'night_schedule' => [$event('NIGHT-ENTRY', 900001, '+22 hours')],
            'day_off', 'unscheduled', 'journey_insufficient' => [$event('SINGLE-OBSERVATION', 900001, '+8 hours')],
            'journey_review' => [
                $event('REVIEW-ENTRY', 900001, '+8 hours'),
                $event('REVIEW-ADDITIONAL', 900001, '+12 hours'),
                $event('REVIEW-EXIT', 900001, '+17 hours'),
            ],
            'two_punches' => [
                $event('TWO-ENTRY', 900001, '+8 hours', 'BIOMETRIC', 'V3B'),
                $event('TWO-EXIT', 900001, '+17 hours', 'BIOMETRIC', 'V3B'),
            ],
            // Deliberately not in slot/chronological order: the presentation
            // must select by the frozen role + segment contract.
            'four_punches' => [
                $event('FOUR-EXIT-2', 900001, '+14 hours', 'BIOMETRIC', 'V3B'),
                $event('FOUR-ENTRY-1', 900001, '+8 hours', 'BIOMETRIC', 'V3B'),
                $event('FOUR-EXIT-1', 900001, '+12 hours', 'BIOMETRIC', 'V3B'),
                $event('FOUR-ENTRY-2', 900001, '+13 hours', 'BIOMETRIC', 'V3B'),
            ],
            'many_punches' => [
                $event('MANY-ENTRY-1', 900001, '+8 hours', 'BIOMETRIC', 'V3B'),
                $event('MANY-SURPLUS', 900001, '+9 hours', 'BIOMETRIC', 'V3B'),
                $event('MANY-UNMATCHED', 900001, '+10 hours', 'BIOMETRIC', 'V3B'),
                $event('MANY-EXIT-1', 900001, '+12 hours', 'BIOMETRIC', 'V3B'),
                $event('MANY-ENTRY-2', 900001, '+13 hours', 'BIOMETRIC', 'V3B'),
                $event('MANY-EXIT-2', 900001, '+17 hours', 'BIOMETRIC', 'V3B'),
            ],
            'missing_entry' => [$event('MISSING-ENTRY-EXIT', 900001, '+17 hours', 'BIOMETRIC', 'V3B')],
            'missing_exit' => [$event('MISSING-EXIT-ENTRY', 900001, '+8 hours', 'BIOMETRIC', 'V3B')],
            'ambiguous_pairing' => [
                $event('AMBIGUOUS-ENTRY', 900001, '+8 hours', 'BIOMETRIC', 'V3B'),
                $event('AMBIGUOUS-EXIT', 900001, '+17 hours', 'BIOMETRIC', 'V3B'),
            ],
            'overnight_journey' => [
                $event('OVERNIGHT-ENTRY', 900001, '+22 hours', 'BIOMETRIC', 'V3B'),
                $event('OVERNIGHT-EXIT', 900001, '+30 hours', 'BIOMETRIC', 'V3B'),
            ],
            'three_segments' => [
                $event('SEGMENT-1-ENTRY', 900001, '+8 hours', 'BIOMETRIC', 'V3B'),
                $event('SEGMENT-1-EXIT', 900001, '+10 hours', 'BIOMETRIC', 'V3B'),
                $event('SEGMENT-2-ENTRY', 900001, '+13 hours', 'BIOMETRIC', 'V3B'),
                $event('SEGMENT-2-EXIT', 900001, '+15 hours', 'BIOMETRIC', 'V3B'),
                $event('SEGMENT-3-ENTRY', 900001, '+18 hours', 'BIOMETRIC', 'V3B'),
                $event('SEGMENT-3-EXIT', 900001, '+20 hours', 'BIOMETRIC', 'V3B'),
            ],
            'journey_not_evaluated' => [
                $event('NOT-EVALUATED-ENTRY', 900001, '+8 hours', 'BIOMETRIC', 'V3B'),
                $event('NOT-EVALUATED-EXIT', 900001, '+17 hours', 'BIOMETRIC', 'V3B'),
            ],
            'ready' => [
                $event('DAY-ENTRY', 900001, '+8 hours'),
                $event('DAY-EXIT', 900001, '+17 hours'),
                $event('UNRESOLVED', null, null, 'CARD'),
            ],
            default => [
                $event('DAY-ENTRY', 900001, '+8 hours'),
                $event('DAY-EXIT', 900001, '+17 hours'),
            ],
        };
    }

    /** @return array<int,array<string,mixed>> Contract-shaped fixtures tied to the existing V3 demo worker, workplace, and date. */
    private static function demoIncidenceFixtureRows(
        string $scenario,
        int $workerId,
        int $workplaceId,
        string $from,
        string $to
    ): array {
        $referenceDate = self::demoReferenceDate();
        if ($workerId !== 900001 || $workplaceId !== 14 || $from > $referenceDate || $to < $referenceDate) {
            return [];
        }

        $definitions = match ($scenario) {
            'incidence_active', 'incidence_evaluation_unavailable' => [
                ['ACTIVE', 'VACATION', 1, ['ACTIVE']],
            ],
            'incidence_pending_review' => [
                ['PENDING', 'AUTHORIZED_LATENESS', 1, ['PENDING_REVIEW']],
            ],
            'incidence_rejected' => [
                ['REJECTED', 'PERMISSION', 1, ['REJECTED']],
            ],
            'incidence_revoked' => [
                ['REVOKED', 'VACATION', 2, ['ACTIVE', 'REVOKED']],
            ],
            'incidence_history' => [
                ['HISTORY', 'PERMISSION', 3, ['SUPERSEDED', 'SUPERSEDED', 'ACTIVE']],
            ],
            'incidence_conflict' => [
                ['CONFLICT-VACATION', 'VACATION', 1, ['ACTIVE']],
                ['CONFLICT-PERMISSION', 'PERMISSION', 1, ['ACTIVE']],
            ],
            default => [],
        };

        $rows = [];
        foreach ($definitions as [$key, $category, $currentRevision, $revisionStatuses]) {
            $uuid = 'DEMO-INCIDENCE-' . $key . '-' . str_replace('-', '', $referenceDate);
            $reason = 'Escenario sintético; no acredita autorización ni efecto operativo.';
            $evidence = 'DEMO_ONLY:INCIDENCE:' . $key;
            $revisions = [];
            foreach ($revisionStatuses as $index => $status) {
                $revisionNumber = $index + 1;
                $revisions[] = [
                    'revision_number' => $revisionNumber,
                    'status' => $status,
                    'reason' => $reason,
                    'evidence_reference' => $evidence . ':REVISION:' . $revisionNumber,
                    'created_by_user_id' => 900099,
                    'approved_by_user_id' => null,
                    'created_at_utc' => $referenceDate . 'T' . sprintf('%02d', 16 + $index) . ':00:00Z',
                ];
            }
            $rows[] = [
                'uuid_incidencia' => $uuid,
                'worker_id' => 900001,
                'workplace_id' => 14,
                'category' => $category,
                'scope' => 'WORKER_DATE',
                'date_range' => ['start_date' => $referenceDate, 'end_date' => $referenceDate],
                'time_interval' => null,
                'current_status' => $revisionStatuses[count($revisionStatuses) - 1],
                'current_revision' => $currentRevision,
                'reason' => $reason,
                'source_classification' => 'SYNTHETIC_DEMO_FIXTURE',
                'evidence_reference' => $evidence,
                'created_at_utc' => $referenceDate . 'T16:00:00Z',
                'created_by_user_id' => 900099,
                'approved_by_user_id' => null,
                'revisions' => $revisions,
            ];
        }

        return $rows;
    }

    /** @return array<string,mixed> */
    private static function demoScheduleData(int $workerId, int $workplaceId, string $date, string $scenario): array
    {
        $status = match ($scenario) {
            'day_off' => 'DAY_OFF',
            'unscheduled' => 'UNSCHEDULED',
            default => 'SCHEDULED',
        };
        $base = [
            'id_personal' => $workerId,
            'id_estacion' => $workplaceId,
            'fecha_local' => $date,
            'estado_jornada' => $status,
        ];
        if ($status !== 'SCHEDULED') {
            return $base + ['origen' => null, 'horario_local' => null, 'horario_utc' => null];
        }

        $night = in_array($scenario, ['night_schedule', 'overnight_journey'], true);
        $start = $night ? '22:00:00' : '08:00:00';
        $end = $night ? '06:00:00' : '17:00:00';
        $endOffset = $night ? 1 : 0;
        $timezone = 'America/Mexico_City';
        $zone = new \DateTimeZone($timezone);
        $utc = new \DateTimeZone('UTC');
        $localStart = new \DateTimeImmutable($date . ' ' . $start, $zone);
        $localEnd = (new \DateTimeImmutable($date . ' ' . $end, $zone))->modify('+' . $endOffset . ' day');

        return $base + [
            'origen' => [
                'id_asignacion' => 900001,
                'id_plantilla' => 900001,
                'clave_plantilla' => $night ? 'DEMO-NOCTURNO' : 'DEMO-DIURNO',
                'version_plantilla' => 1,
            ],
            'horario_local' => [
                'timezone' => $timezone,
                'segmentos' => [[
                    'ordinal' => 1,
                    'tipo' => 'WORK',
                    'hora_inicio_local' => $start,
                    'hora_fin_local' => $end,
                    'dia_offset_inicio' => 0,
                    'dia_offset_fin' => $endOffset,
                ]],
            ],
            'horario_utc' => [
                'start_utc' => $localStart->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
                'end_utc' => $localEnd->setTimezone($utc)->format('Y-m-d\TH:i:s\Z'),
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function demoJourneyData(
        int $workerId,
        int $workplaceId,
        string $date,
        string $snapshotAuditRef,
        string $scenario,
        array $observedEvents
    ): array {
        $schedule = self::demoScheduleData($workerId, $workplaceId, $date, $scenario);
        $scheduleStatus = (string) $schedule['estado_jornada'];
        $level2Status = self::demoLevel2Status($scenario);
        $v3a = static fn(string $suffix): string => 'DEMO-CHECADOR-V3A-' . $suffix;
        $v3b = static fn(string $suffix): string => 'DEMO-CHECADOR-V3B-' . $suffix;
        $journeyFixture = match ($scenario) {
            'day_off' => ['evaluated' => false, 'status' => 'DAY_OFF', 'classifications' => [], 'segments' => [], 'pairing' => null, 'calculation' => null],
            'unscheduled' => ['evaluated' => false, 'status' => 'UNSCHEDULED_DAY', 'classifications' => [], 'segments' => [], 'pairing' => null, 'calculation' => null],
            'night_schedule', 'journey_insufficient', 'level2_incomplete', 'level2_unknown', 'journey_not_evaluated' => ['evaluated' => false, 'status' => 'DATA_INCOMPLETE', 'classifications' => [], 'segments' => [], 'pairing' => null, 'calculation' => null],
            'journey_review' => [
                'evaluated' => true,
                'status' => 'REVIEW_REQUIRED',
                'classifications' => [
                    $v3a('REVIEW-ENTRY') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
                    $v3a('REVIEW-ADDITIONAL') => ['pairing_role' => 'SURPLUS', 'segment_ordinal' => 1],
                    $v3a('REVIEW-EXIT') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 1],
                ],
                'segments' => [['ordinal' => 1, 'entry_event_uuid' => $v3a('REVIEW-ENTRY'), 'exit_event_uuid' => $v3a('REVIEW-EXIT')]],
                'pairing' => 'AMBIGUOUS_PAIRING',
                'calculation' => 'REVIEW_REQUIRED',
            ],
            'two_punches', 'ready', 'day_schedule', 'journey_with_punches', 'incidence_none', 'incidence_active',
            'incidence_pending_review', 'incidence_rejected', 'incidence_revoked', 'incidence_history',
            'incidence_conflict', 'incidence_evaluation_unavailable', 'incidence_service_unavailable',
            'evaluation_pending_review', 'evaluation_data_incomplete', 'evaluation_incidence_conflict',
            'evaluation_available', 'evaluation_stale_calculation', 'evaluation_unauthorized',
            'evaluation_duplicate', 'evaluation_service_unavailable', 'evaluation_falta_authorized' => [
                'evaluated' => true,
                'status' => 'EVALUATED',
                'classifications' => [
                    $v3a('DAY-ENTRY') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
                    $v3a('DAY-EXIT') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 1],
                    $v3b('TWO-ENTRY') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
                    $v3b('TWO-EXIT') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 1],
                ],
                'segments' => [[
                    'ordinal' => 1,
                    'entry_event_uuid' => $scenario === 'two_punches' ? $v3b('TWO-ENTRY') : $v3a('DAY-ENTRY'),
                    'exit_event_uuid' => $scenario === 'two_punches' ? $v3b('TWO-EXIT') : $v3a('DAY-EXIT'),
                ]],
                'pairing' => 'EXACT_PAIR',
                'calculation' => 'CALCULATED',
            ],
            'four_punches' => [
                'evaluated' => true,
                'status' => 'EVALUATED',
                'classifications' => [
                    $v3b('FOUR-EXIT-2') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 2],
                    $v3b('FOUR-ENTRY-1') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
                    $v3b('FOUR-EXIT-1') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 1],
                    $v3b('FOUR-ENTRY-2') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 2],
                ],
                'segments' => [
                    ['ordinal' => 1, 'entry_event_uuid' => $v3b('FOUR-ENTRY-1'), 'exit_event_uuid' => $v3b('FOUR-EXIT-1')],
                    ['ordinal' => 2, 'entry_event_uuid' => $v3b('FOUR-ENTRY-2'), 'exit_event_uuid' => $v3b('FOUR-EXIT-2')],
                ],
                'pairing' => 'EXACT_PAIR',
                'calculation' => 'CALCULATED',
            ],
            'many_punches' => [
                'evaluated' => true,
                'status' => 'EVALUATED',
                'classifications' => [
                    $v3b('MANY-ENTRY-1') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
                    $v3b('MANY-SURPLUS') => ['pairing_role' => 'SURPLUS', 'segment_ordinal' => 1],
                    $v3b('MANY-UNMATCHED') => ['pairing_role' => 'UNMATCHED', 'segment_ordinal' => null],
                    $v3b('MANY-EXIT-1') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 1],
                    $v3b('MANY-ENTRY-2') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 2],
                    $v3b('MANY-EXIT-2') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 2],
                ],
                'segments' => [
                    ['ordinal' => 1, 'entry_event_uuid' => $v3b('MANY-ENTRY-1'), 'exit_event_uuid' => $v3b('MANY-EXIT-1')],
                    ['ordinal' => 2, 'entry_event_uuid' => $v3b('MANY-ENTRY-2'), 'exit_event_uuid' => $v3b('MANY-EXIT-2')],
                ],
                'pairing' => 'EXACT_PAIR',
                'calculation' => 'CALCULATED',
            ],
            'missing_entry' => [
                'evaluated' => true,
                'status' => 'REVIEW_REQUIRED',
                'classifications' => [$v3b('MISSING-ENTRY-EXIT') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 1]],
                'segments' => [['ordinal' => 1, 'entry_event_uuid' => null, 'exit_event_uuid' => $v3b('MISSING-ENTRY-EXIT')]],
                'pairing' => 'PARTIAL_MISSING_IN',
                'calculation' => 'REVIEW_REQUIRED',
            ],
            'missing_exit' => [
                'evaluated' => true,
                'status' => 'REVIEW_REQUIRED',
                'classifications' => [$v3b('MISSING-EXIT-ENTRY') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 1]],
                'segments' => [['ordinal' => 1, 'entry_event_uuid' => $v3b('MISSING-EXIT-ENTRY'), 'exit_event_uuid' => null]],
                'pairing' => 'PARTIAL_MISSING_OUT',
                'calculation' => 'REVIEW_REQUIRED',
            ],
            'ambiguous_pairing' => [
                'evaluated' => true,
                'status' => 'REVIEW_REQUIRED',
                'classifications' => [
                    $v3b('AMBIGUOUS-ENTRY') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
                    $v3b('AMBIGUOUS-EXIT') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 1],
                ],
                'segments' => [['ordinal' => 1, 'entry_event_uuid' => $v3b('AMBIGUOUS-ENTRY'), 'exit_event_uuid' => $v3b('AMBIGUOUS-EXIT')]],
                'pairing' => 'AMBIGUOUS_PAIRING',
                'calculation' => 'REVIEW_REQUIRED',
            ],
            'overnight_journey' => [
                'evaluated' => true,
                'status' => 'EVALUATED',
                'classifications' => [
                    $v3b('OVERNIGHT-ENTRY') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
                    $v3b('OVERNIGHT-EXIT') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 1],
                ],
                'segments' => [['ordinal' => 1, 'entry_event_uuid' => $v3b('OVERNIGHT-ENTRY'), 'exit_event_uuid' => $v3b('OVERNIGHT-EXIT')]],
                'pairing' => 'EXACT_PAIR',
                'calculation' => 'CALCULATED',
            ],
            'three_segments' => [
                'evaluated' => true,
                'status' => 'EVALUATED',
                'classifications' => [
                    $v3b('SEGMENT-1-ENTRY') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 1],
                    $v3b('SEGMENT-1-EXIT') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 1],
                    $v3b('SEGMENT-2-ENTRY') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 2],
                    $v3b('SEGMENT-2-EXIT') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 2],
                    $v3b('SEGMENT-3-ENTRY') => ['pairing_role' => 'ENTRY', 'segment_ordinal' => 3],
                    $v3b('SEGMENT-3-EXIT') => ['pairing_role' => 'EXIT', 'segment_ordinal' => 3],
                ],
                'segments' => [
                    ['ordinal' => 1, 'entry_event_uuid' => $v3b('SEGMENT-1-ENTRY'), 'exit_event_uuid' => $v3b('SEGMENT-1-EXIT')],
                    ['ordinal' => 2, 'entry_event_uuid' => $v3b('SEGMENT-2-ENTRY'), 'exit_event_uuid' => $v3b('SEGMENT-2-EXIT')],
                    ['ordinal' => 3, 'entry_event_uuid' => $v3b('SEGMENT-3-ENTRY'), 'exit_event_uuid' => $v3b('SEGMENT-3-EXIT')],
                ],
                'pairing' => 'EXACT_PAIR',
                'calculation' => 'CALCULATED',
            ],
            default => ['evaluated' => false, 'status' => 'DATA_INCOMPLETE', 'classifications' => [], 'segments' => [], 'pairing' => null, 'calculation' => null],
        };
        $evaluated = $journeyFixture['evaluated'];

        $workerEvents = array_values(array_filter(
            $observedEvents,
            static fn(array $event): bool => (int) ($event['id_personal_resuelto'] ?? 0) === $workerId
        ));
        $evaluatedEvents = null;
        if ($evaluated) {
            $evaluatedEvents = [];
            foreach ($workerEvents as $event) {
                $classification = $journeyFixture['classifications'][(string) ($event['event_uuid'] ?? '')] ?? null;
                if (!is_array($classification)) {
                    throw new \LogicException('Demo Journey fixture is missing a P3-H event classification.');
                }
                $evaluatedEvents[] = [
                    'event_uuid' => (string) $event['event_uuid'],
                    'id_dispositivo' => null,
                    'ocurrido_at_utc' => (string) $event['ocurrido_at_utc'],
                    'metodo_captura' => (string) $event['metodo_captura'],
                    'pairing_role' => $classification['pairing_role'],
                    'segment_ordinal' => $classification['segment_ordinal'],
                ];
            }
        }

        $generatedAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        $pairingCreatedAt = substr($generatedAt, 0, 19);
        $calculationCreatedAt = (new \DateTimeImmutable($pairingCreatedAt, new \DateTimeZone('UTC')))
            ->modify('+1 second')->format('Y-m-d H:i:s');
        $journeySchedule = [
            'estado_jornada' => $scheduleStatus,
            'scheduled_shift_id' => $scheduleStatus === 'SCHEDULED' ? 900001 : null,
            'origen' => $schedule['origen'] ?? null,
            'horario_local' => isset($schedule['horario_local']) ? [
                'segmentos' => array_map(static fn(array $segment): array => [
                    'ordinal' => $segment['ordinal'],
                    'tipo_segmento' => $segment['tipo'],
                    'hora_inicio_local' => $segment['hora_inicio_local'],
                    'hora_fin_local' => $segment['hora_fin_local'],
                    'dia_offset_inicio' => $segment['dia_offset_inicio'],
                    'dia_offset_fin' => $segment['dia_offset_fin'],
                ], $schedule['horario_local']['segmentos']),
            ] : null,
        ];
        $pairing = null;
        $calculation = null;
        $provenance = [
            'response_generated_at_utc' => $generatedAt,
            'p1_created_at_utc' => null,
            'p2_created_at_utc' => null,
            'p1_algorithm_version' => null,
            'p2_algorithm_version' => null,
            'shift_template_version' => null,
            'source_completeness_contract_version' => 'v2.4',
            'level2_policy_ref' => null,
            'policy_rule_version' => null,
            'evaluated_raw_event_count' => null,
            'evaluated_raw_events' => null,
        ];

        if ($evaluated && is_array($evaluatedEvents)) {
            $pairing = [
                'pairing_id' => 900001,
                'revision_numero' => 1,
                'creado_at_utc' => $pairingCreatedAt,
                'pairing_status' => $journeyFixture['pairing'],
                'algorithm_version' => 'demo-fixture-p1',
                'segments' => $journeyFixture['segments'],
            ];
            $calculation = [
                'calculation_id' => 900001,
                'revision_numero' => 1,
                'creado_at_utc' => $calculationCreatedAt,
                'version_algoritmo' => 'demo-fixture-p2',
                'estado_calculo' => $journeyFixture['calculation'],
                'segmentos' => [],
            ];
            $provenance = array_merge($provenance, [
                'p1_created_at_utc' => $pairingCreatedAt,
                'p2_created_at_utc' => $calculationCreatedAt,
                'p1_algorithm_version' => 'demo-fixture-p1',
                'p2_algorithm_version' => 'demo-fixture-p2',
                'shift_template_version' => 1,
                'level2_policy_ref' => null,
                'evaluated_raw_event_count' => count($evaluatedEvents),
                'evaluated_raw_events' => $evaluatedEvents,
            ]);
        }

        $sourceRef = $snapshotAuditRef !== '' ? $snapshotAuditRef : 'DEMO-SNAPSHOT-REFERENCE';
        return [
            'evaluated' => $evaluated,
            'journey_status' => $journeyFixture['status'],
            'context' => [
                'id_personal' => $workerId,
                'id_estacion' => $workplaceId,
                'fecha_local' => $date,
                'timezone' => 'America/Mexico_City',
            ],
            'schedule' => $journeySchedule,
            'source_evidence' => [
                'snapshot_audit_ref' => $sourceRef,
                'window_level2_status' => $level2Status,
                'level2_evaluation_ref' => null,
            ],
            'pairing' => $pairing,
            'calculation' => $calculation,
            'evaluated_raw_event_count' => $evaluated ? count($evaluatedEvents ?? []) : null,
            'evaluated_raw_events' => $evaluated ? $evaluatedEvents : null,
            'provenance' => $provenance,
        ];
    }

    private static function demoLevel2Status(string $scenario): string
    {
        return match ($scenario) {
            'level2_verified', 'ready', 'incidence_none', 'incidence_active', 'incidence_pending_review',
            'incidence_rejected', 'incidence_revoked', 'incidence_history', 'incidence_conflict',
            'incidence_evaluation_unavailable', 'incidence_service_unavailable' => 'VERIFIED',
            'level2_incomplete', 'journey_insufficient' => 'INCOMPLETE',
            'level2_unknown', 'day_schedule', 'day_off', 'unscheduled', 'night_schedule', 'journey_with_punches', 'journey_review' => 'UNKNOWN',
            default => 'UNKNOWN',
        };
    }

    private static function demoSnapshotAuditReference(int $workplaceId, string $fromUtc): string
    {
        try {
            $localDate = (new \DateTimeImmutable($fromUtc, new \DateTimeZone('UTC')))
                ->setTimezone(new \DateTimeZone('America/Mexico_City'))
                ->format('Ymd');
        } catch (\Throwable) {
            $localDate = 'date-unavailable';
        }

        return 'DEMO-SNAPSHOT-' . $workplaceId . '-' . $localDate;
    }

    /** @return array{workplace_id:int,from_utc:string}|null */
    private static function parseDemoSnapshotToken(string $token): ?array
    {
        if (preg_match('/\Ademo-server-only-([1-9][0-9]*)-([A-Za-z0-9_-]+)\z/', $token, $matches) !== 1) {
            return null;
        }
        $encoded = strtr($matches[2], '-_', '+/');
        $padding = (4 - (strlen($encoded) % 4)) % 4;
        $fromUtc = base64_decode($encoded . str_repeat('=', $padding), true);
        if (!is_string($fromUtc) || trim($fromUtc) === '') {
            return null;
        }

        return ['workplace_id' => (int) $matches[1], 'from_utc' => $fromUtc];
    }

    /** @param array<string,mixed> $payload */
    private static function demoJsonResponse(int $status, array $payload): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private static function resolveBaseUrl(?string $baseUrl): string
    {
        if ($baseUrl === null) {
            $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
            if (!in_array($environment, ['dev', 'development', 'testing'], true)) {
                throw new \RuntimeException('Checador P03 is enabled only in development.');
            }
        }
        $configured = $baseUrl ?? ($_ENV['CHECADOR_API_BASE_URL'] ?? getenv('CHECADOR_API_BASE_URL') ?: '');
        $configured = rtrim(trim((string) $configured), '/') . '/';
        if (!preg_match('~^https?://[^\s/]+(?:/[^\s]*)?/$~i', $configured)) {
            throw new \InvalidArgumentException('Checador API base URL is not configured.');
        }
        return $configured;
    }

    private static function isCivilDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false
            && $parsed->format('Y-m-d') === $date
            && !array_filter(\DateTimeImmutable::getLastErrors() ?: []);
    }

    private static function isUtcTimestamp(string $value): bool
    {
        if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z\z/D', $value) !== 1) {
            return false;
        }
        try {
            $date = new \DateTimeImmutable($value);
            return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s') === substr($value, 0, 19);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function unicodeLength(string $value): int
    {
        $length = preg_match_all('/./us', $value, $matches);
        return is_int($length) ? $length : strlen($value);
    }

    private static function mapApiError(string $status, int $httpStatus): string
    {
        return match ($status) {
            'WORKPLACE_TIMEZONE_UNRESOLVED' => 'timezone_unresolved',
            'WORKPLACE_TIMEZONE_AMBIGUOUS' => 'timezone_ambiguous',
            'WORKPLACE_TIMEZONE_INVALID' => 'timezone_invalid',
            'WORKPLACE_SCOPE_DENIED', 'STATION_SCOPE_DENIED' => 'api_authorization',
            'UNAUTHORIZED' => 'api_authentication',
            'SNAPSHOT_EXPIRED' => 'snapshot_expired',
            'SNAPSHOT_SCOPE_CHANGED', 'SNAPSHOT_ACCESS_DENIED' => 'snapshot_authorization',
            'SNAPSHOT_NOT_READY' => 'snapshot_not_ready',
            'INVALID_CURSOR' => 'snapshot_invalid',
            'FUNCTION_PERMISSION_DENIED' => 'level2_authorization',
            'WORKER_NOT_FOUND' => 'worker_not_found',
            'INCIDENCE_NOT_FOUND' => 'incidence_not_found',
            'STALE_REVISION_CONFLICT' => 'stale_revision_conflict',
            'IDEMPOTENCY_CONFLICT' => 'idempotency_conflict',
            'INVALID_LIFECYCLE_TRANSITION' => 'invalid_lifecycle_transition',
            'CALCULATION_NOT_FOUND' => 'calculation_not_found',
            'INVALID_PAYLOAD', 'UNRESOLVED_IDENTITY' => 'bad_request',
            'ATTENDANCE_POLICY_NOT_VERSIONED' => 'attendance_policy_not_versioned',
            'ATTENDANCE_POLICY_INVALID' => 'attendance_policy_invalid',
            'ATTENDANCE_POLICY_UNAVAILABLE' => 'attendance_policy_unavailable',
            'INCIDENCE_DEADLINE_EXPIRED' => 'incidence_deadline_expired',
            'CHECK_DATE_IN_FUTURE' => 'check_date_future',
            'WORKER_MEMBERSHIP_UNVERIFIED' => 'worker_membership_unverified',
            'SOURCE_EVENT_NOT_MATCHED' => 'source_event_not_matched',
            'QA_DEVELOPMENT_SCOPE_REQUIRED' => 'qa_development_scope_required',
            'DEVELOPMENT_ONLY' => 'development_only',
            'POLICY_MUST_BE_PROSPECTIVE' => 'policy_not_prospective',
            'POLICY_VERSION_DATE_CONFLICT' => 'policy_version_date_conflict',
            'UNAUTHORIZED_STATION_ACCESS' => 'api_authorization',
            'INVALID_QUERY' => 'bad_request',
            'SOURCE_UNAVAILABLE', 'INTERNAL_ERROR', 'AUTH_DEPENDENCY_UNAVAILABLE' => 'api_unavailable',
            'BAD_DATE', 'BAD_REQUEST', 'DATE_RANGE_TOO_LARGE' => 'bad_request',
            'SERVER_ERROR' => 'api_unavailable',
            default => $httpStatus === 401 ? 'api_authentication'
                : ($httpStatus === 403 ? 'api_authorization'
                    : ($httpStatus === 409 ? 'administrative_conflict' : 'api_unavailable')),
        };
    }

    private static function mapTemporaryAccessApiError(string $status, int $httpStatus): string
    {
        return match ($status) {
            'UNAUTHORIZED' => 'api_authentication',
            'FUNCTION_PERMISSION_DENIED', 'STATION_SCOPE_DENIED', 'STATION_FORBIDDEN',
            'WORKPLACE_SCOPE_DENIED', 'FORBIDDEN' => 'api_authorization',
            'REQUEST_NOT_FOUND', 'TEMPORARY_ACCESS_NOT_FOUND' => 'temporary_access_not_found',
            'INVALID_REQUEST_STATE', 'REQUEST_EXPIRED', 'PIN_EXPIRED' => 'administrative_conflict',
            'INVALID_PARAMETER', 'BAD_REQUEST' => 'bad_request',
            default => $httpStatus === 401 ? 'api_authentication'
                : ($httpStatus === 403 ? 'api_authorization'
                    : ($httpStatus === 409 ? 'administrative_conflict' : 'api_unavailable')),
        };
    }

    /** Preserve adjustment-specific HTTP semantics without changing other workspace mappings. */
    private static function mapAdministrativeAdjustmentApiError(string $status, int $httpStatus): string
    {
        return match ($status) {
            'UNAUTHORIZED' => 'api_authentication',
            'FUNCTION_PERMISSION_DENIED', 'STATION_SCOPE_DENIED' => 'api_authorization',
            'ADJUSTMENT_NOT_FOUND' => 'adjustment_not_found',
            'WORKER_NOT_FOUND_OR_OUT_OF_SCOPE' => 'worker_not_found_or_out_of_scope',
            'INVALID_PAYLOAD', 'INVALID_DATE_RANGE', 'DATE_RANGE_TOO_LARGE', 'INVALID_FILTER' => 'bad_request',
            'DATA_INTEGRITY_CONFLICT' => 'administrative_integrity_conflict',
            'SOURCE_UNAVAILABLE' => 'administrative_unavailable',
            default => $httpStatus === 401 ? 'api_authentication'
                : ($httpStatus === 403 ? 'api_authorization'
                    : ($httpStatus >= 500 ? 'administrative_unavailable' : 'bad_request')),
        };
    }

    private static function failure(string $kind, int $status): array
    {
        return ['ok' => false, 'error' => ['kind' => $kind, 'status' => $status]];
    }
}

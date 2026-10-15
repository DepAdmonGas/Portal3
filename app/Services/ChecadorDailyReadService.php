<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\JWTService;
use App\Models\Usuario;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

/**
 * Narrow server-to-server read boundary for the Checador daily observed
 * punches screen. This is deliberately not a generic API client.
 */
final class ChecadorDailyReadService
{
    private const DEFAULT_PAGE_SIZE = 50;
    private const MAX_PAGE_SIZE = 200;

    /** @var callable(Usuario): string */
    private $tokenFactory;

    private ClientInterface $http;
    private string $baseUrl;

    public function __construct(
        ?ClientInterface $http = null,
        ?callable $tokenFactory = null,
        ?string $baseUrl = null
    ) {
        $this->baseUrl = self::resolveBaseUrl($baseUrl);
        $this->http = $http ?? new Client([
            'base_uri' => $this->baseUrl,
            'http_errors' => false,
            'connect_timeout' => 5.0,
            'timeout' => 15.0,
        ]);
        $this->tokenFactory = $tokenFactory ?? static function (Usuario $user): string {
            return JWTService::createAccessToken(['sub' => (int) $user->id]);
        };
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
        ]);
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
            return self::failure('api_unavailable', 502);
        }

        return ['ok' => true, 'data' => [
            'workplace_id' => $workplaceId,
            'local_date' => $date,
            'timezone' => (string) $data['timezone'],
            // These boundaries are opaque API authority; do not recalculate.
            'from_utc' => (string) $data['from_utc'],
            'to_utc' => (string) $data['to_utc'],
        ]];
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
            || $rawPagination['per_page'] < 1
            || $rawPagination['per_page'] > self::MAX_PAGE_SIZE
            || $rawPagination['total_records'] < 0
            || $rawPagination['total_pages'] < 0) {
            return self::failure('api_unavailable', 502);
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
        int $limit = self::DEFAULT_PAGE_SIZE
    ): array {
        if (trim($snapshotToken) === '' || $limit < 1 || $limit > self::MAX_PAGE_SIZE) {
            return self::failure('bad_request', 400);
        }

        $query = ['limit' => $limit];
        if ($cursor !== null && trim($cursor) !== '') {
            $query['cursor'] = $cursor;
        }
        $response = $this->request($user, 'GET', 'api/v2/punch-events/read-snapshots/' . rawurlencode($snapshotToken) . '/events', $query);
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
                || trim((string) ($local['timezone'] ?? '')) === ''
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
            || trim((string) ($data['context']['timezone'] ?? '')) === ''
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
                $eventUuids[(string) $event['event_uuid']] = true;
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
        array $fields = []
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
            $idempotencyKey
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

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) return $value;
        if (is_string($value) && ctype_digit($value) && (int) $value > 0) return (int) $value;
        return null;
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
        ];
    }

    private function request(Usuario $user, string $method, string $path, array $query = []): array
    {
        try {
            $token = ($this->tokenFactory)($user);
            if (!is_string($token) || trim($token) === '') {
                return self::failure('api_authentication', 401);
            }

            $response = $this->http->request($method, $path, [
                'query' => $query,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'http_errors' => false,
            ]);
            $status = $response->getStatusCode();
            $raw = (string) $response->getBody();
            $payload = json_decode($raw, true);
            $payload = is_array($payload) ? $payload : [];

            if ($status < 200 || $status >= 300) {
                $errorCode = is_array($payload['error'] ?? null) ? ($payload['error']['code'] ?? '') : '';
                $statusCode = $errorCode !== '' ? $errorCode : ($payload['status'] ?? '');
                return self::failure(self::mapApiError((string) $statusCode, $status), $status);
            }

            return ['ok' => true, 'payload' => $payload];
        } catch (\Throwable) {
            return self::failure('api_unavailable', 503);
        }
    }

    private function requestJson(
        Usuario $user,
        string $method,
        string $path,
        array $body,
        ?string $idempotencyKey = null
    ): array {
        try {
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
            $status = $response->getStatusCode();
            $raw = (string) $response->getBody();
            $payload = json_decode($raw, true);
            $payload = is_array($payload) ? $payload : [];

            if ($status < 200 || $status >= 300) {
                $errorCode = is_array($payload['error'] ?? null) ? ($payload['error']['code'] ?? '') : '';
                $statusCode = $errorCode !== '' ? $errorCode : ($payload['status'] ?? '');
                return self::failure(self::mapApiError((string) $statusCode, $status), $status);
            }

            return ['ok' => true, 'payload' => $payload];
        } catch (\Throwable) {
            return self::failure('api_unavailable', 503);
        }
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
            'UNAUTHORIZED_STATION_ACCESS' => 'api_authorization',
            'BAD_DATE', 'BAD_REQUEST', 'DATE_RANGE_TOO_LARGE' => 'bad_request',
            'SERVER_ERROR' => 'api_unavailable',
            default => $httpStatus === 401 ? 'api_authentication'
                : ($httpStatus === 403 ? 'api_authorization'
                    : ($httpStatus === 409 ? 'administrative_conflict' : 'api_unavailable')),
        };
    }

    private static function failure(string $kind, int $status): array
    {
        return ['ok' => false, 'error' => ['kind' => $kind, 'status' => $status]];
    }
}

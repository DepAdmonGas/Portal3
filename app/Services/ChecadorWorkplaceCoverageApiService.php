<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

/** API V2 client for approved coverage reads; administrative writes are retired in Portal3. */
final class ChecadorWorkplaceCoverageApiService
{
    public const FEATURE_FLAG = 'CHECADOR_V2_WORKPLACE_COVERAGE_ENABLED';
    private const PAGE_SIZE = 50;
    private const MAX_PAGES = 100;

    private ClientInterface $http;
    private string $baseUrl;
    /** @var callable(Usuario):string */
    private $tokenFactory;

    public function __construct(
        ?ClientInterface $http = null,
        ?callable $tokenFactory = null,
        ?string $baseUrl = null
    ) {
        $environment = strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        if ($baseUrl === null && !in_array($environment, ['dev', 'development', 'local', 'testing'], true)) {
            throw new \RuntimeException('coverage_api_development_only');
        }

        $configured = $baseUrl ?? ($_ENV['CHECADOR_API_BASE_URL'] ?? getenv('CHECADOR_API_BASE_URL') ?: '');
        $this->baseUrl = rtrim(trim((string) $configured), '/') . '/';
        if (!preg_match('~^https?://[^\s/]+(?:/[^\s]*)?/$~i', $this->baseUrl)) {
            throw new \RuntimeException('coverage_api_configuration_unavailable');
        }

        $this->http = $http ?? new Client([
            'base_uri' => $this->baseUrl,
            'http_errors' => false,
            'connect_timeout' => 5.0,
            'timeout' => 15.0,
        ]);
        $this->tokenFactory = $tokenFactory ?? static fn(Usuario $user): string =>
            ChecadorApiJwtService::createAccessToken($user);
    }

    /** Exact opt-in. The ordinary Portal3 configuration remains disabled by default. */
    public static function isEnabled(?string $environment = null, ?string $flag = null): bool
    {
        $environment ??= strtolower(trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '')));
        $flag ??= (string) ($_ENV[self::FEATURE_FLAG] ?? getenv(self::FEATURE_FLAG) ?: '');
        return in_array(strtolower(trim($environment)), ['dev', 'development', 'local'], true)
            && trim($flag) === '1';
    }

    /** @return array{ok:bool,http_status:int,code?:string,data?:array<string,mixed>} */
    public function listAll(Usuario $user): array
    {
        if (!self::isEnabled()) return self::failure('feature_disabled', 503);
        $token = $this->token($user);
        if ($token === null) return self::failure('api_authentication', 401);

        $items = [];
        $seenIds = [];
        $snapshotId = null;
        $beforeId = null;
        $seenCursors = [];
        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['limit' => self::PAGE_SIZE];
            if ($snapshotId !== null) $query['snapshot_id'] = $snapshotId;
            if ($beforeId !== null) $query['before_id'] = $beforeId;
            $result = $this->request($user, $token, 'GET', 'api/v2/workplace-coverages', [], null, $query);
            if (empty($result['ok'])) return $result;

            $data = $result['data'] ?? [];
            $pageSnapshot = filter_var($data['snapshot_id'] ?? null, FILTER_VALIDATE_INT);
            $pageItems = $data['items'] ?? null;
            if ($pageSnapshot === false || $pageSnapshot < 0 || !is_array($pageItems) || !array_is_list($pageItems)
                || !is_bool($data['has_more'] ?? null)) {
                return self::failure('api_contract_invalid', 502);
            }
            if ($snapshotId === null) $snapshotId = $pageSnapshot;
            elseif ($pageSnapshot !== $snapshotId) return self::failure('api_snapshot_inconsistent', 502);

            $previousItemId = null;
            foreach ($pageItems as $item) {
                if (!is_array($item) || !isset($item['id']) || !is_numeric($item['id'])) {
                    return self::failure('api_contract_invalid', 502);
                }
                $id = (int) $item['id'];
                if ($id < 1 || isset($seenIds[$id])
                    || ($previousItemId !== null && $id >= $previousItemId)
                    || ($beforeId !== null && $id >= $beforeId)) {
                    return self::failure('api_page_duplicate_or_invalid', 502);
                }
                $seenIds[$id] = true;
                $items[] = $item;
                $previousItemId = $id;
            }

            if ($data['has_more'] === false) {
                return ['ok' => true, 'http_status' => (int) $result['http_status'], 'data' => [
                    'items' => $items,
                    'snapshot_id' => $snapshotId,
                    'complete' => true,
                ]];
            }

            $next = filter_var($data['next_before_id'] ?? null, FILTER_VALIDATE_INT);
            if ($next === false || $next < 1 || $pageItems === []
                || $next !== (int) ($pageItems[count($pageItems) - 1]['id'] ?? 0)
                || ($beforeId !== null && $next >= $beforeId) || isset($seenCursors[$next])) {
                return self::failure('api_pagination_inconsistent', 502);
            }
            $seenCursors[$next] = true;
            $beforeId = $next;
        }

        return self::failure('api_pagination_limit', 502);
    }

    /** @return array{ok:bool,http_status:int,code?:string,data?:array<string,mixed>} */
    public function detail(Usuario $user, string $coverageUuid): array
    {
        if (!self::isEnabled()) return self::failure('feature_disabled', 503);
        if (!self::isUuid($coverageUuid)) return self::failure('invalid_coverage_uuid', 400);
        return $this->request($user, null, 'GET', 'api/v2/workplace-coverages/' . $coverageUuid);
    }

    /** Administrative mutations belong to the Portal3 database writer in V1.2. */
    public function requestCoverage(Usuario $user, array $payload, string $idempotencyKey): array
    {
        return self::failure('coverage_writer_portal3_only', 410);
    }

    /** API V2 does not accept administrative coverage transitions from Portal3. */
    public function transition(Usuario $user, string $coverageUuid, string $action, string $reason, string $idempotencyKey): array
    {
        return self::failure('coverage_writer_portal3_only', 410);
    }

    /** @return array{ok:bool,http_status:int,code?:string,data?:array<string,mixed>} */
    private function request(
        Usuario $user,
        ?string $knownToken,
        string $method,
        string $path,
        array $body = [],
        ?string $idempotencyKey = null,
        array $query = []
    ): array {
        $token = $knownToken ?? $this->token($user);
        if ($token === null) return self::failure('api_authentication', 401);
        try {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ];
            if ($idempotencyKey !== null) $headers['Idempotency-Key'] = $idempotencyKey;
            $response = $this->http->request($method, $path, [
                'headers' => $headers,
                'query' => $query,
                'json' => $body,
                'http_errors' => false,
            ]);
            $status = $response->getStatusCode();
            $payload = json_decode((string) $response->getBody(), true);
            if (!is_array($payload)) return self::failure('api_response_invalid', 502);
            if ($status < 200 || $status >= 300) {
                return self::failure(self::safeCode((string) ($payload['code'] ?? $payload['status'] ?? 'api_error')), $status);
            }
            if (($payload['status'] ?? '') !== 'success' || !is_array($payload['data'] ?? null)) {
                return self::failure('api_contract_invalid', 502);
            }
            return ['ok' => true, 'http_status' => $status, 'data' => $payload['data']];
        } catch (\Throwable) {
            return self::failure('api_unavailable', 503);
        }
    }

    private function token(Usuario $user): ?string
    {
        try {
            $token = ($this->tokenFactory)($user);
            return is_string($token) && trim($token) !== '' ? $token : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function validIdempotencyKey(string $key): bool
    {
        return strlen($key) >= 16 && strlen($key) <= 200
            && preg_match('/\A[A-Za-z0-9._:-]+\z/D', $key) === 1;
    }

    private static function isUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $value) === 1;
    }

    private static function safeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        return preg_match('/\A[A-Z0-9_]{1,80}\z/D', $code) === 1 ? strtolower($code) : 'api_error';
    }

    /** @return array{ok:false,http_status:int,code:string} */
    private static function failure(string $code, int $status): array
    {
        return ['ok' => false, 'http_status' => $status, 'code' => $code];
    }
}

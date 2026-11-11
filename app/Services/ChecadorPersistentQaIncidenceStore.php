<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;
use App\Core\Logger;

/**
 * Development-only persistent QA incidence records. This file store is not a
 * replacement for API V2's canonical P3-G writer and is never used when the
 * real API POST gate is enabled.
 */
final class ChecadorPersistentQaIncidenceStore
{
    public const WORKPLACE_ID = ChecadorPersistentQaFixtureService::WORKPLACE_ID;
    public const WORK_DATE = ChecadorPersistentQaFixtureService::WORK_DATE;
    private const STORAGE_VERSION = 1;

    private string $directory;
    private string $operationStage = 'idle';

    public function __construct(?string $directory = null)
    {
        // Keep the store outside the public webroot, in a Portal3 storage
        // parent writable by the local PHP-FPM group. The previous
        // storage/private parent is owned by the developer with mode 0750,
        // which prevents the apache PHP-FPM worker from creating the QA store.
        $this->directory = $directory ?? dirname(__DIR__, 2) . '/storage/checador-qa-incidences';
    }

    public function enabled(): bool
    {
        return ChecadorPersistentQaFixtureService::enabled()
            && !ChecadorDailyReadService::realApiPostOperationsEnabled();
    }

    /** QA persistence requires a distinct, explicit server-side write switch. */
    public function localQaWritesExplicitlyEnabled(): bool
    {
        $flag = strtolower(trim((string) ($_ENV['CHECADOR_M02_QA_INCIDENCE_LOCAL_WRITES_ENABLED']
            ?? getenv('CHECADOR_M02_QA_INCIDENCE_LOCAL_WRITES_ENABLED') ?: '')));

        return $this->enabled()
            && (new ChecadorPersistentQaFixtureService())->localIncidenceWriteEnvironmentEnabled()
            && in_array($flag, ['1', 'true', 'yes', 'on'], true);
    }

    /** Production requests may persist only to the fixed private project storage directory. */
    public function usesDefaultIsolatedDestination(): bool
    {
        $expected = dirname(__DIR__, 2) . '/storage/checador-qa-incidences';
        if ($this->directory !== $expected || str_contains($this->directory, '/public/')) {
            return false;
        }

        $components = preg_split('~[\\\\/]+~', $expected, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $current = str_starts_with($expected, DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '';
        foreach ($components as $component) {
            $current = rtrim($current, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $component;
            if (is_link($current)) {
                return false;
            }
        }

        if (file_exists($expected)) {
            return !is_link($expected) && is_dir($expected);
        }

        return true;
    }

    /** Confirm the fixed private destination is writable by the current server process. */
    public function canWriteDefaultIsolatedDestination(): bool
    {
        if (!$this->usesDefaultIsolatedDestination()) {
            return false;
        }
        $expected = dirname(__DIR__, 2) . '/storage/checador-qa-incidences';
        if (file_exists($expected)) {
            return is_writable($expected);
        }
        return is_dir(dirname($expected)) && is_writable(dirname($expected));
    }

    /**
     * @param array<string,mixed> $input
     * @param list<int> $authorizedWorkerIds resolved from the authenticated roster
     * @return array{ok:bool,created?:bool,data?:array<string,mixed>,error?:string}
     */
    public function create(array $input, int $actorUserId, array $authorizedWorkerIds): array
    {
        $workerId = filter_var($input['worker_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $workplaceId = filter_var($input['workplace_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $returnDate = is_string($input['return_date'] ?? null) ? trim($input['return_date']) : '';
        $category = is_string($input['category'] ?? null) ? trim($input['category']) : '';
        $startDate = is_string($input['start_date'] ?? null) ? trim($input['start_date']) : '';
        $endDate = is_string($input['end_date'] ?? null) ? trim($input['end_date']) : '';
        $reason = is_string($input['reason'] ?? null) ? trim($input['reason']) : '';
        $evidenceReference = is_string($input['evidence_reference'] ?? null)
            ? trim($input['evidence_reference'])
            : '';
        $idempotencyKey = is_string($input['idempotency_key'] ?? null) ? trim($input['idempotency_key']) : '';
        $authorizedWorkerIds = array_values(array_unique(array_map('intval', $authorizedWorkerIds)));
        $this->operationStage = 'input_validation';

        if (!$this->localQaWritesExplicitlyEnabled()
            || (PHP_SAPI !== 'cli'
                && (!$this->usesDefaultIsolatedDestination() || !$this->canWriteDefaultIsolatedDestination()))) {
            return ['ok' => false, 'error' => 'disabled'];
        }
        $validationError = match (true) {
            $actorUserId < 1 => 'invalid_actor',
            $workplaceId !== self::WORKPLACE_ID || $returnDate !== self::WORK_DATE => 'unsupported_scope',
            $workerId === false || !in_array((int) $workerId, $authorizedWorkerIds, true) => 'worker_out_of_scope',
            !$this->isEligibleFixtureWorker($workplaceId, $returnDate, $workerId === false ? null : (int) $workerId) => 'worker_not_in_fixture',
            !in_array($category, ['VACATION', 'PERMISSION', 'INCAPACITY', 'JUSTIFICATION'], true) => 'category_invalid',
            !$this->isCivilDate($returnDate) || !$this->isCivilDate($startDate) || !$this->isCivilDate($endDate) || $endDate < $startDate => 'dates_invalid',
            $startDate > $returnDate || $endDate < $returnDate => 'period_must_include_work_date',
            $reason === '' => 'reason_required',
            mb_strlen($reason) > 2000 => 'reason_too_long',
            mb_strlen($evidenceReference) > 500 => 'evidence_too_long',
            !preg_match('/\A[A-Za-z0-9._:-]{1,64}\z/', $idempotencyKey) => 'idempotency_key_invalid',
            default => null,
        };
        if ($validationError !== null) {
            return ['ok' => false, 'error' => $validationError];
        }

        $workerId = (int) $workerId;
        $workplaceId = (int) $workplaceId;
        $this->operationStage = 'persistence';
        $fingerprint = hash('sha256', json_encode([
            'worker_id' => $workerId,
            'workplace_id' => $workplaceId,
            'category' => $category,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $reason,
            'evidence_reference' => $evidenceReference === '' ? null : $evidenceReference,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $idempotencyHash = hash('sha256', $actorUserId . ':' . $workplaceId . ':' . $idempotencyKey);

        try {
            return $this->withLockedRecords(true, function (array &$records) use (
                $workerId,
                $workplaceId,
                $category,
                $startDate,
                $endDate,
                $reason,
                $evidenceReference,
                $actorUserId,
                $fingerprint,
                $idempotencyHash
            ): array {
                foreach ($records as $existing) {
                    if (!is_array($existing)) {
                        throw new RuntimeException('invalid_store');
                    }
                    if (hash_equals((string) ($existing['_idempotency_hash'] ?? ''), $idempotencyHash)) {
                        if (!hash_equals((string) ($existing['_request_fingerprint'] ?? ''), $fingerprint)) {
                            return ['ok' => false, 'error' => 'idempotency_conflict'];
                        }
                        return ['ok' => true, 'created' => false, 'data' => $this->publicRecord($existing)];
                    }
                    if (hash_equals((string) ($existing['_request_fingerprint'] ?? ''), $fingerprint)) {
                        // Exact duplicate submissions with a new key remain idempotent.
                        return ['ok' => true, 'created' => false, 'data' => $this->publicRecord($existing)];
                    }
                }

                $now = gmdate('Y-m-d\TH:i:s\Z');
                $uuid = $this->uuidV4();
                $revision = [
                    'revision_number' => 1,
                    'status' => 'PENDING_REVIEW',
                    'reason' => $reason,
                    'evidence_reference' => $evidenceReference === '' ? null : $evidenceReference,
                    'created_by_user_id' => $actorUserId,
                    'approved_by_user_id' => null,
                    'created_at_utc' => $now,
                ];
                $record = [
                    'uuid_incidencia' => $uuid,
                    'worker_id' => $workerId,
                    'workplace_id' => $workplaceId,
                    'category' => $category,
                    'scope' => 'WORKER',
                    'date_range' => ['start_date' => $startDate, 'end_date' => $endDate],
                    'time_interval' => null,
                    'current_status' => 'PENDING_REVIEW',
                    'current_revision' => 1,
                    'summary_reason' => $reason,
                    'summary_evidence_reference' => $evidenceReference === '' ? null : $evidenceReference,
                    'created_by_user_id' => $actorUserId,
                    'created_at_utc' => $now,
                    'revisions' => [$revision],
                    'source_classification' => 'LOCAL_QA_INCIDENCE',
                    'source_fixture_version' => ChecadorPersistentQaFixtureService::LOCAL_INCIDENCE_WRITE_FIXTURE_VERSION,
                    '_idempotency_hash' => $idempotencyHash,
                    '_request_fingerprint' => $fingerprint,
                ];
                $records[] = $record;
                return ['ok' => true, 'created' => true, 'data' => $this->publicRecord($record)];
            });
        } catch (Throwable $exception) {
            $errorCode = $exception instanceof RuntimeException
                ? match ($exception->getMessage()) {
                    'invalid_store_path' => 'invalid_store_path',
                    'invalid_store' => 'invalid_store',
                    'lock_unavailable' => 'lock_unavailable',
                    'storage_unavailable' => 'storage_unavailable',
                    default => 'storage_operation_failed',
                }
                : 'storage_operation_failed';
            try {
                Logger::error('Checador QA incidence persistence failed', [
                    'stage' => $this->operationStage,
                    'error_code' => $errorCode,
                    'storage_mode' => 'local_qa_file',
                    'validation_result' => 'passed',
                    'authorization_result' => 'passed',
                    'persistence_result' => 'failed',
                    'readback_result' => 'not_attempted',
                ]);
            } catch (Throwable) {
                // Logging failure must not replace the safe storage result.
            }
            return ['ok' => false, 'error' => 'storage_unavailable'];
        }
    }

    /** @param list<int> $authorizedWorkerIds @return list<array<string,mixed>> */
    public function listForScope(int $workplaceId, string $date, array $authorizedWorkerIds, ?int $selectedWorkerId = null): array
    {
        $result = $this->readForScope($workplaceId, $date, $authorizedWorkerIds, $selectedWorkerId);
        return ($result['ok'] ?? false) ? ($result['records'] ?? []) : [];
    }

    /** @param list<int> $authorizedWorkerIds @return array{ok:bool,records?:list<array<string,mixed>>,error?:string} */
    public function readForScope(int $workplaceId, string $date, array $authorizedWorkerIds, ?int $selectedWorkerId = null): array
    {
        if (!$this->enabled() || $workplaceId !== self::WORKPLACE_ID || !$this->isCivilDate($date)) {
            return ['ok' => false, 'error' => 'unavailable'];
        }
        $authorizedWorkerIds = array_values(array_unique(array_map('intval', $authorizedWorkerIds)));
        if ($selectedWorkerId !== null && !in_array($selectedWorkerId, $authorizedWorkerIds, true)) {
            return ['ok' => false, 'error' => 'worker_out_of_scope'];
        }

        try {
            $records = $this->withLockedRecords(false, function (array &$records) use (
                $workplaceId,
                $date,
                $authorizedWorkerIds,
                $selectedWorkerId
            ): array {
                $result = [];
                foreach ($records as $record) {
                    if (!is_array($record)) {
                        throw new RuntimeException('invalid_store');
                    }
                    $workerId = (int) ($record['worker_id'] ?? 0);
                    $range = is_array($record['date_range'] ?? null) ? $record['date_range'] : [];
                    if ((int) ($record['workplace_id'] ?? 0) !== $workplaceId
                        || !in_array($workerId, $authorizedWorkerIds, true)
                        || ($selectedWorkerId !== null && $workerId !== $selectedWorkerId)
                        || (string) ($range['start_date'] ?? '') > $date
                        || (string) ($range['end_date'] ?? '') < $date) {
                        continue;
                    }
                    $result[] = $this->publicRecord($record);
                }
                usort($result, static fn(array $left, array $right): int => strcmp(
                    (string) ($right['created_at_utc'] ?? ''),
                    (string) ($left['created_at_utc'] ?? '')
                ));
                return $result;
            });
            return ['ok' => true, 'records' => $records];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'storage_unavailable'];
        }
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    public function readBack(string $uuid, int $workplaceId, int $workerId): ?array
    {
        if (!$this->enabled() || $workplaceId !== self::WORKPLACE_ID) {
            return null;
        }
        try {
            return $this->withLockedRecords(false, function (array &$records) use ($uuid, $workplaceId, $workerId): ?array {
                foreach ($records as $record) {
                    if (is_array($record)
                        && hash_equals((string) ($record['uuid_incidencia'] ?? ''), $uuid)
                        && (int) ($record['workplace_id'] ?? 0) === $workplaceId
                        && (int) ($record['worker_id'] ?? 0) === $workerId) {
                        return $this->publicRecord($record);
                    }
                }
                return null;
            });
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Serialize read/modify/write and persist atomically outside the public webroot.
     * @param callable(array<string,mixed>&):mixed $callback
     */
    private function withLockedRecords(bool $write, callable $callback): mixed
    {
        $this->operationStage = 'directory_prepare';
        if (!$this->ensureDirectory($write)) {
            $empty = [];
            return $callback($empty);
        }
        $dataPath = $this->directory . '/records.json';
        $lockPath = $this->directory . '/.records.lock';
        if (is_link($lockPath)) {
            throw new RuntimeException('invalid_store_path');
        }
        $this->operationStage = 'lock_open';
        $lock = fopen($lockPath, 'c+b');
        if ($lock === false) {
            throw new RuntimeException('lock_unavailable');
        }
        @chmod($lockPath, 0600);
        try {
            $this->operationStage = 'lock_acquire';
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('lock_unavailable');
            }
            $this->operationStage = 'store_read';
            $records = $this->readRecords($dataPath);
            $this->operationStage = 'record_mutation';
            $result = $callback($records);
            if ($write && ($result['created'] ?? false) === true) {
                $this->operationStage = 'store_write';
                $this->writeRecords($dataPath, $records);
            }
            $this->operationStage = 'complete';
            flock($lock, LOCK_UN);
            return $result;
        } finally {
            fclose($lock);
        }
    }

    private function ensureDirectory(bool $create): bool
    {
        $components = preg_split('~[\\\\/]+~', $this->directory, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $current = str_starts_with($this->directory, DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '';
        foreach ($components as $component) {
            $current = rtrim($current, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $component;
            if (is_link($current)) {
                throw new RuntimeException('invalid_store_path');
            }
        }
        if (!file_exists($this->directory)) {
            if (!$create) {
                return false;
            }
            if (!@mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
                throw new RuntimeException('storage_unavailable');
            }
        }
        if (is_link($this->directory) || !is_dir($this->directory)) {
            throw new RuntimeException('invalid_store_path');
        }
        @chmod($this->directory, 0700);
        return true;
    }

    /** @return list<array<string,mixed>> */
    private function readRecords(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        $stat = @lstat($path);
        if ($stat === false || is_link($path) || !is_file($path) || (int) ($stat['size'] ?? 0) > 5_000_000) {
            throw new RuntimeException('invalid_store');
        }
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('invalid_store');
        }
        try {
            $opened = fstat($handle);
            $current = @lstat($path);
            if (!is_array($opened) || !is_array($current)
                || is_link($path)
                || (int) ($opened['dev'] ?? -1) !== (int) ($current['dev'] ?? -2)
                || (int) ($opened['ino'] ?? -1) !== (int) ($current['ino'] ?? -2)
                || (((int) ($opened['mode'] ?? 0)) & 0170000) !== 0100000) {
                throw new RuntimeException('invalid_store');
            }
            $json = stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
        if (!is_string($json)) throw new RuntimeException('invalid_store');
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || ($decoded['version'] ?? null) !== self::STORAGE_VERSION || !is_array($decoded['records'] ?? null)) {
            throw new RuntimeException('invalid_store');
        }
        return array_values($decoded['records']);
    }

    /** @param list<array<string,mixed>> $records */
    private function writeRecords(string $path, array $records): void
    {
        $payload = json_encode(['version' => self::STORAGE_VERSION, 'records' => $records], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporaryPath = $this->directory . '/.records.' . bin2hex(random_bytes(12)) . '.tmp';
        $handle = fopen($temporaryPath, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('storage_unavailable');
        }
        try {
            @chmod($temporaryPath, 0600);
            $written = fwrite($handle, $payload);
            if ($written !== strlen($payload) || !fflush($handle)) {
                throw new RuntimeException('storage_unavailable');
            }
            if (function_exists('fsync')) {
                @fsync($handle);
            }
        } finally {
            fclose($handle);
        }
        if (!@rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('storage_unavailable');
        }
        @chmod($path, 0600);
    }

    /** @param array<string,mixed> $record @return array<string,mixed> */
    private function publicRecord(array $record): array
    {
        unset($record['_idempotency_hash'], $record['_request_fingerprint']);
        return $record;
    }

    private function isCivilDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function isEligibleFixtureWorker(int $workplaceId, string $date, ?int $workerId): bool
    {
        return (new ChecadorPersistentQaFixtureService())
            ->isEligibleLocalIncidenceWorker($workplaceId, $date, $workerId);
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}

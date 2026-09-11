<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fixed-window limiter backed by private files and exclusive locks.
 *
 * It is independent of PHP sessions: starting a new session does not reset an
 * anonymous login limit. The private directory must be shared by all
 * application instances in a multi-node deployment.
 */
final class RateLimiter
{
    private const LIMITS = [
        'login' => ['max' => 10, 'window' => 300],
    ];

    private static ?string $testStorageDirectory = null;

    /** @return array{allowed: bool, retry_after: int, storage_failed: bool} */
    public static function consume(string $type, string $subject, ?string $ip = null, ?int $now = null): array
    {
        $limit = self::LIMITS[$type] ?? null;
        if ($limit === null) {
            throw new \InvalidArgumentException('Unsupported rate-limit type.');
        }

        $now ??= time();
        $key = hash('sha256', $type . '|' . self::normalizeSubject($subject) . '|' . ($ip ?? Request::ip()));

        try {
            return self::consumeAtomically($type, $key, $limit['max'], $limit['window'], $now);
        } catch (\Throwable $exception) {
            // Avoid turning a storage outage into a global login outage.
            error_log('Rate limiter storage unavailable: ' . $exception->getMessage());
            return ['allowed' => true, 'retry_after' => 0, 'storage_failed' => true];
        }
    }

    public static function clear(string $type, string $subject, ?string $ip = null): void
    {
        $key = hash('sha256', $type . '|' . self::normalizeSubject($subject) . '|' . ($ip ?? Request::ip()));
        $path = self::counterPath($type, $key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** @internal Test seam; never configured from an HTTP request. */
    public static function useStorageDirectoryForTesting(?string $directory): void
    {
        self::$testStorageDirectory = $directory;
    }

    private static function consumeAtomically(string $type, string $key, int $max, int $window, int $now): array
    {
        $directory = self::storageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to initialize rate-limit storage.');
        }

        $handle = fopen(self::counterPath($type, $key), 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Unable to lock rate-limit counter.');
        }

        try {
            $raw = stream_get_contents($handle);
            $record = is_string($raw) ? json_decode($raw, true) : null;
            $startedAt = is_array($record) && isset($record['started_at']) ? (int) $record['started_at'] : $now;
            $attempts = is_array($record) && isset($record['attempts']) ? (int) $record['attempts'] : 0;

            if ($now >= $startedAt + $window) {
                $startedAt = $now;
                $attempts = 0;
            }

            if ($attempts >= $max) {
                return ['allowed' => false, 'retry_after' => max(1, ($startedAt + $window) - $now), 'storage_failed' => false];
            }

            $attempts++;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode(['started_at' => $startedAt, 'attempts' => $attempts], JSON_THROW_ON_ERROR));
            fflush($handle);

            return ['allowed' => true, 'retry_after' => 0, 'storage_failed' => false];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function normalizeSubject(string $subject): string
    {
        return mb_strtolower(trim($subject), 'UTF-8');
    }

    private static function storageDirectory(): string
    {
        return self::$testStorageDirectory
            ?? dirname(__DIR__, 2) . '/storage/private/rate-limits';
    }

    private static function counterPath(string $type, string $key): string
    {
        return rtrim(self::storageDirectory(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $type . '-' . $key . '.json';
    }
}

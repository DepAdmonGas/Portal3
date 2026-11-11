<?php

declare(strict_types=1);

namespace App\Services;

use DomainException;
use Illuminate\Database\Capsule\Manager as Capsule;

/** Cross-process MariaDB advisory lock shared by baseline cutover and supported RH assignment writes. */
final class WorkerWorkplaceAssignmentWriteCoordinator
{
    private const LOCK_TIMEOUT_SECONDS = 30;

    /**
     * Run while holding the database-wide assignment coordination lock.
     * The callback may span the baseline commit and an operator-controlled configuration activation.
     * SQL and process configuration are not one atomic transaction; the lock only blocks cooperating Portal3 write paths.
     */
    public static function runExclusive(callable $callback, int $timeoutSeconds = self::LOCK_TIMEOUT_SECONDS): mixed
    {
        if ($timeoutSeconds < 0 || $timeoutSeconds > 60) {
            throw new DomainException('Assignment coordination lock timeout must be between 0 and 60 seconds.');
        }

        $connection = Capsule::connection();
        $identity = $connection->selectOne('SELECT DATABASE() AS database_name');
        $database = trim((string) ($identity->database_name ?? ''));
        if ($database === '') {
            throw new DomainException('Assignment coordination lock requires a selected database.');
        }
        $lockName = 'p3:cv2:assignment:' . substr(hash('sha256', $database), 0, 24);
        $lock = $connection->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, $timeoutSeconds]);
        if ((int) ($lock->acquired ?? 0) !== 1) {
            throw new DomainException('Could not acquire the Checador V2 assignment coordination lock.');
        }

        try {
            return $callback();
        } finally {
            $released = $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            if ((int) ($released->released ?? 0) !== 1) {
                throw new DomainException('The Checador V2 assignment coordination lock was lost before release.');
            }
        }
    }
}

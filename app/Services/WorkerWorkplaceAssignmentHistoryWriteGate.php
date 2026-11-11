<?php

declare(strict_types=1);

namespace App\Services;

/** Explicit opt-in for HR assignment history writes; unset and all other values are off. */
final class WorkerWorkplaceAssignmentHistoryWriteGate
{
    public const ENVIRONMENT_KEY = 'CHECADOR_V2_WORKPLACE_HISTORY_WRITES';

    public static function enabled(): bool
    {
        $value = $_ENV[self::ENVIRONMENT_KEY]
            ?? $_SERVER[self::ENVIRONMENT_KEY]
            ?? getenv(self::ENVIRONMENT_KEY);

        return is_string($value) && $value === '1';
    }
}

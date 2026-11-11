<?php

declare(strict_types=1);

namespace App\Services;

/** Explicit opt-in for membership writes owned by direct RH worker creation only. */
final class WorkerWorkplaceDirectCreateMembershipWriteGate
{
    public const ENVIRONMENT_KEY = 'CHECADOR_V2_RH_DIRECT_CREATE_MEMBERSHIP_WRITES';

    public static function enabled(): bool
    {
        $value = $_ENV[self::ENVIRONMENT_KEY]
            ?? $_SERVER[self::ENVIRONMENT_KEY]
            ?? getenv(self::ENVIRONMENT_KEY);

        return is_string($value) && $value === '1';
    }
}

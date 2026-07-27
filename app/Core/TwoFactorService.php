<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Usuario;

final class TwoFactorService
{
    public const SUCCESS = 'success';
    public const REQUIRED = 'required';
    public const INVALID = 'invalid';

    /**
     * Valida el segundo factor de autenticación.
     */
    public static function validate(
        Usuario $user,
        ?string $code
    ): string {

        if (!$user->hasTwoFactorEnabled()) {
            return self::SUCCESS;
        }

        if (empty($code)) {
            return self::REQUIRED;
        }

        if (!$user->verifyTwoFactorCode($code)) {
            return self::INVALID;
        }

        return self::SUCCESS;
    }
}

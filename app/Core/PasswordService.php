<?php

declare(strict_types=1);

namespace App\Core;

final class PasswordService
{
    /**
     * Verifica una contraseña.
     *
     * Compatible con:
     *  - Texto plano (legacy)
     *  - bcrypt
     *  - Argon2i
     *  - Argon2id
     */
    public static function verify(
        string $plainPassword,
        ?string $storedPassword
    ): bool {

        if (empty($storedPassword)) {
            return false;
        }

        // Hash moderno
        if (self::isHash($storedPassword)) {
            return password_verify(
                $plainPassword,
                $storedPassword
            );
        }

        // Legacy
        return hash_equals(
            $storedPassword,
            $plainPassword
        );
    }

    /**
     * Genera un hash.
     */
    public static function hash(
        string $password
    ): string {

        return password_hash(
            $password,
            PASSWORD_DEFAULT
        );
    }

    /**
     * Indica si un hash necesita actualizarse.
     */
    public static function needsRehash(
        string $hash
    ): bool {

        if (!self::isHash($hash)) {
            return true;
        }

        return password_needs_rehash(
            $hash,
            PASSWORD_DEFAULT
        );
    }

    /**
     * Detecta si el valor almacenado ya es un hash.
     */
    public static function isHash(
        string $password
    ): bool {

        return password_get_info($password)['algo'] !== null;
    }

    /**
     * Indica si la contraseña almacenada
     * sigue siendo texto plano.
     */
    public static function isLegacy(
        ?string $password
    ): bool {

        if ($password === null) {
            return false;
        }

        return !self::isHash($password);
    }
}

<?php

declare(strict_types=1);

namespace App\Core;

final class Cookie
{

    private static function defaults(): array
    {
        return [
            'expires'  => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /**
     * Crea una cookie.
     */
    public static function set(
        string $name,
        string $value,
        array $options = []
    ): void {

        $options = array_merge(
            self::defaults(),
            $options
        );

        setcookie(
            $name,
            $value,
            $options
        );

        // Mantener sincronizado $_COOKIE durante la petición actual
        $_COOKIE[$name] = $value;
    }

    /**
     * Obtiene una cookie.
     */
    public static function get(
        string $name,
        mixed $default = null
    ): mixed {

        return $_COOKIE[$name] ?? $default;
    }

    /**
     * Verifica si existe.
     */
    public static function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Elimina una cookie.
     */
    public static function forget(
        string $name
    ): void {

        self::set(
            $name,
            '',
            [
                'expires' => time() - 3600
            ]
        );

        unset($_COOKIE[$name]);
    }

    /**
     * Elimina varias cookies.
     */
    public static function destroy(array $cookies): void
    {
        foreach ($cookies as $cookie) {
            self::forget($cookie);
        }
    }

    /**
     * Determina si la cookie debe marcarse como Secure.
     */
    private static function isSecure(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'dev') === 'prod'
            && Request::isSecure();
    }

    public static function add(
        string $name,
        string $value,
        array $options = []
    ): void {

        if (!self::has($name)) {
            self::set(
                $name,
                $value,
                $options
            );
        }
    }

    public static function pull(
        string $name,
        mixed $default = null
    ): mixed {

        $value = self::get(
            $name,
            $default
        );

        self::forget($name);

        return $value;
    }

    public static function all(): array
    {
        return $_COOKIE;
    }

    public static function count(): int
    {
        return count($_COOKIE);
    }

    public static function clear(): void
    {
        foreach (array_keys($_COOKIE) as $cookie) {
            self::forget($cookie);
        }
    }
}

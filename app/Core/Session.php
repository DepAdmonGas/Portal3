<?php

declare(strict_types=1);

namespace App\Core;

class Session
{
    private static int $lifetime = 90000;

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set(
            'session.gc_maxlifetime',
            (string) self::$lifetime
        );

        session_set_cookie_params([
            'lifetime' => self::$lifetime,
            'path' => '/',
            'secure' => Request::isSecure(),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();

        self::check();
    }

    /**
     * Verifica expiración
     */
    private static function check(): void
    {
        if (!self::has('LAST_ACTIVITY')) {
            return;
        }

        if (
            time() - self::get('LAST_ACTIVITY')
            > self::$lifetime
        ) {

            self::destroy();

            header('Location: /login');
            exit;
        }

        self::set(
            'LAST_ACTIVITY',
            time()
        );
    }

    /**
     * Set
     */
    public static function set(
        string $key,
        mixed $value
    ): void {

        $session = &self::store();

        $session[$key] = $value;
    }

    /**
     * Get
     */
    public static function get(
        string $key,
        mixed $default = null
    ): mixed {

        $session = self::store();

        return $session[$key] ?? $default;
    }

    /**
     * Remove - Elimina una clave específica de la sesión
     */

    public static function remove(string $key): void
    {
        self::forget($key);
    }

    public static function forget(string $key)
    {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Destroy
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 3600,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Verificar login
     */
    public static function isLogged()
    {
        return Session::has('usuario');
    }

    private static function &store(): array
    {
        return $_SESSION;
    }

    public static function has(string $key): bool
    {
        return array_key_exists(
            $key,
            self::store()
        );
    }

    public static function all(): array
    {
        return self::store();
    }

    public static function pull(
        string $key,
        mixed $default = null
    ): mixed {

        $value = self::get(
            $key,
            $default
        );

        self::forget($key);

        return $value;
    }

    public static function regenerate(
        bool $deleteOld = true
    ): void {

        session_regenerate_id($deleteOld);
    }
}

<?php
namespace App\Core;

class Session
{
    private static $lifetime = 86000; 

    public static function init()
    {
        if (session_status() === PHP_SESSION_NONE) {

            ini_set('session.gc_maxlifetime', self::$lifetime);

            session_set_cookie_params([
                'lifetime' => self::$lifetime,
                'path' => '/',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_start();
        }

        self::check();
    }

    /**
     * Verifica expiración
     */
    private static function check()
    {
        if (isset($_SESSION['LAST_ACTIVITY'])) {

            if (time() - $_SESSION['LAST_ACTIVITY'] > self::$lifetime) {
                self::destroy();
                header('Location: /');
                exit;
            }
        }

        $_SESSION['LAST_ACTIVITY'] = time();
    }

    /**
     * Set
     */
    public static function set($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get
     */
    public static function get($key)
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Destroy
     */
    public static function destroy()
    {
        session_unset();
        session_destroy();
    }

    /**
     * Verificar login
     */
    public static function isLogged()
    {
        return isset($_SESSION['usuario']);
    }
}
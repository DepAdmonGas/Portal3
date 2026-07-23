<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?array $json = null;

    /**
     * Cache del input unificado.
     */
    private static ?array $input = null;

    private static ?array $server = null;

    private static ?string $body = null;

    /*
    |--------------------------------------------------------------------------
    | INPUT
    |--------------------------------------------------------------------------
    */

    public static function all(): array
    {
        return self::loadInput();
    }

    public static function only(array $keys): array
    {
        return array_intersect_key(
            self::loadInput(),
            array_flip($keys)
        );
    }

    public static function except(array $keys): array
    {
        return array_diff_key(
            self::loadInput(),
            array_flip($keys)
        );
    }

    public static function has(string $key): bool
    {
        return array_key_exists(
            $key,
            self::loadInput()
        );
    }

    public static function filled(string $key): bool
    {
        if (!self::has($key)) {
            return false;
        }

        return trim((string) self::input($key)) !== '';
    }

    public static function input(
        string $key,
        mixed $default = null
    ): mixed {

        return self::loadInput()[$key] ?? $default;
    }

    /*
    |--------------------------------------------------------------------------
    | GET
    |--------------------------------------------------------------------------
    */

    public static function query(): array
    {
        return $_GET;
    }

    public static function get(
        string $key,
        mixed $default = null
    ): mixed {

        return $_GET[$key] ?? $default;
    }

    /*
    |--------------------------------------------------------------------------
    | POST
    |--------------------------------------------------------------------------
    */

    public static function post(): array
    {
        return $_POST;
    }

    /*
    |--------------------------------------------------------------------------
    | JSON
    |--------------------------------------------------------------------------
    */

    public static function json(): array
    {
        if (self::$json !== null) {
            return self::$json;
        }

        $decoded = json_decode(
            self::body(),
            true
        );

        self::$json = is_array($decoded)
            ? $decoded
            : [];

        return self::$json;
    }

    public static function jsonInput(
        string $key,
        mixed $default = null
    ): mixed {

        return self::json()[$key] ?? $default;
    }

    /*
    |--------------------------------------------------------------------------
    | COOKIE
    |--------------------------------------------------------------------------
    */

    public static function cookies(): array
    {
        return $_COOKIE;
    }

    public static function cookie(
        string $key,
        mixed $default = null
    ): mixed {

        return $_COOKIE[$key] ?? $default;
    }

    /*
    |--------------------------------------------------------------------------
    | SERVER
    |--------------------------------------------------------------------------
    */

    private static function server(): array
    {
        if (self::$server !== null) {
            return self::$server;
        }

        self::$server = $_SERVER;

        return self::$server;
    }

    public static function method(): string
    {
        return strtoupper(
            self::server()['REQUEST_METHOD']
                ?? 'GET'
        );
    }

    public static function uri(): string
    {
        return strtok(
            $_SERVER['REQUEST_URI'] ?? '/',
            '?'
        );
    }

    public static function host(): string
    {
        return self::server()['HTTP_HOST']
            ?? '';
    }

    public static function ip(): string
    {
        return self::server()['REMOTE_ADDR']
            ?? 'unknown';
    }

    public static function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public static function referer(): string
    {
        return $_SERVER['HTTP_REFERER'] ?? '';
    }

    public static function protocol(): string
    {
        return $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    }

    public static function isSecure(): bool
    {
        return !empty(self::server()['HTTPS'])
            && self::server()['HTTPS'] !== 'off';
    }

    public static function isAjax(): bool
    {
        return (
            $_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''
        ) === 'XMLHttpRequest';
    }

    public static function expectsJson(): bool
    {
        return str_contains(
            $_SERVER['HTTP_ACCEPT'] ?? '',
            'application/json'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | HEADERS
    |--------------------------------------------------------------------------
    */

    public static function header(
        string $name,
        mixed $default = null
    ): mixed {

        $key = 'HTTP_' . strtoupper(
            str_replace('-', '_', $name)
        );

        return $_SERVER[$key] ?? $default;
    }

    public static function headers(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {

            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $header = ucwords(
                strtolower(
                    str_replace('_', '-', substr($key, 5))
                ),
                '-'
            );

            $headers[$header] = $value;
        }

        return $headers;
    }

    private static function loadInput(): array
    {
        if (self::$input !== null) {
            return self::$input;
        }

        self::$input = array_merge(
            $_GET,
            $_POST,
            self::json()
        );

        return self::$input;
    }

    /*
    |--------------------------------------------------------------------------
    | REQUEST TYPE
    |--------------------------------------------------------------------------
    */

    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isPut(): bool
    {
        return self::method() === 'PUT';
    }

    public static function isPatch(): bool
    {
        return self::method() === 'PATCH';
    }

    public static function isDelete(): bool
    {
        return self::method() === 'DELETE';
    }

    private static function body(): string
    {
        if (self::$body !== null) {
            return self::$body;
        }

        self::$body = file_get_contents('php://input') ?: '';

        return self::$body;
    }
}

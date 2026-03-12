<?php

if (!function_exists('base_url')) {
    function base_url(): string
    {
        return rtrim($_ENV['APP_URL'] ?? '', '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url() . '/assets/' . ltrim($path, '/');
    }
}


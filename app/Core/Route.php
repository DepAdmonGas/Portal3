<?php
namespace App\Core;

class Route
{
    public static function middleware(array $middlewares, array $handler)
    {
        return function () use ($middlewares, $handler) {
            Kernel::handle($middlewares);

            [$controller, $method] = $handler;
            $controller = "App\\Controllers\\$controller";

            (new $controller())->$method();
        };
    }

    public static function auth(array $handler)
    {
        return self::middleware(['auth'], $handler);
    }

    public static function guest(array $handler)
    {
        return self::middleware(['guest'], $handler);
    }
}

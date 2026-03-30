<?php

namespace App\Core;

class Route
{
    public static function middleware(array $middlewares, array $handler)
    {
        return function (...$params) use ($middlewares, $handler) {

            Kernel::handle($middlewares);

            [$controller, $method] = $handler;

            $controllerClass = "App\\Controllers\\{$controller}";

            if (!class_exists($controllerClass)) {
                throw new \Exception("Controlador {$controllerClass} no encontrado");
            }

            $instance = new $controllerClass;

            if (!method_exists($instance, $method)) {
                throw new \Exception("Método {$method} no existe en {$controllerClass}");
            }

            return $instance->$method(...array_values($params));
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
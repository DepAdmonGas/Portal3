<?php

namespace App\Core;

class Route
{
    public static function middleware(array $middlewares, array $handler)
    {
        return function (...$params) use ($middlewares, $handler) {

            // Ejecutar middlewares
            Kernel::handle($middlewares);

            // Obtener controlador y método
            [$controller, $method] = $handler;

            // Armar namespace completo
            $controller = "App\\Controllers\\$controller";

            // Verificar que exista la clase
            if (!class_exists($controller)) {
                throw new \Exception("Controlador {$controller} no encontrado");
            }

            $instance = new $controller;

            // Verificar que exista el método
            if (!method_exists($instance, $method)) {
                throw new \Exception("Método {$method} no existe en {$controller}");
            }

            // Ejecutar método pasando parámetros dinámicos
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
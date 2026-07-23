<?php

namespace App\Core;

class Route
{
    private static ?Container $container = null;


    /**
     * Registrar contenedor DI
     */
    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }


    /**
     * Ejecutar middleware y controlador
     */
    public static function middleware(
        array $middlewares,
        array $handler
    ): callable {

        return function (...$params) use ($middlewares, $handler) {


            Kernel::handle($middlewares);


            if (self::$container === null) {

                throw new \Exception(
                    'Container no inicializado en Route'
                );
            }


            if (count($handler) !== 2) {

                throw new \Exception(
                    'Handler inválido. Se esperaba [Controller, method]'
                );
            }


            [$controller, $method] = $handler;


            $controllerClass =
                "App\\Controllers\\{$controller}";


            if (!class_exists($controllerClass)) {

                throw new \Exception(
                    "Controlador {$controllerClass} no encontrado"
                );
            }


            /**
             * IMPORTANTE:
             * El controlador ahora se crea desde DI
             */
            $instance = self::$container->get(
                $controllerClass
            );


            if (!method_exists($instance, $method)) {

                throw new \Exception(
                    "Método {$method} no existe en {$controllerClass}"
                );
            }


            return $instance->$method(...$params);
        };
    }


    /**
     * Rutas protegidas
     */
    public static function auth(array $handler): callable
    {
        return self::middleware(
            ['auth', 'csrf'],
            $handler
        );
    }


    /**
     * Rutas públicas
     */
    public static function guest(array $handler): callable
    {
        return self::middleware(
            ['guest'],
            $handler
        );
    }
}

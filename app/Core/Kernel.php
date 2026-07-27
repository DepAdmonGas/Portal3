<?php

namespace App\Core;

class Kernel
{

    protected static array $routeMiddleware = [

        'auth' => \App\Middleware\AuthMiddleware::class,

        'guest' => \App\Middleware\GuestMiddleware::class,

        'csrf' => \App\Middleware\CsrfMiddleware::class,

    ];


    private static Container $container;



    public static function setContainer(
        Container $container
    ): void {

        self::$container = $container;
    }



    public static function handle(
        array $middlewares
    ): void {


        if (!isset(self::$container)) {

            throw new \Exception(
                'Container no inicializado en Kernel'
            );
        }



        foreach ($middlewares as $middleware) {


            if (!isset(self::$routeMiddleware[$middleware])) {

                throw new \Exception(
                    "Middleware [$middleware] no registrado"
                );
            }



            $class = self::$routeMiddleware[$middleware];


            $instance = self::$container->get(
                $class
            );



            if (!method_exists(
                $instance,
                'handle'
            )) {

                throw new \Exception(
                    "Middleware {$class} no tiene método handle"
                );
            }



            $instance->handle();
        }
    }
}

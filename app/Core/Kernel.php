<?php
namespace App\Core;

class Kernel
{

    /* 
    El Kernel es el componente encargado de administrar y ejecutar los middlewares del sistema.
    Actúa como un punto central que controla la seguridad, autenticación, autorización y otras reglas previas a la ejecución de los controladores.
    Permite desacoplar las rutas de la lógica de validación y facilita la escalabilidad del proyecto.
    */
    
    protected static array $routeMiddleware = [
        'auth'  => \App\Middleware\AuthMiddleware::class,
        'guest' => \App\Middleware\GuestMiddleware::class,
        // SECURITY: Protección CSRF
        'csrf'  => \App\Middleware\CsrfMiddleware::class,
    ];

    public static function handle(array $middlewares): void
    {
        foreach ($middlewares as $middleware) {
            if (!isset(self::$routeMiddleware[$middleware])) {
                throw new \Exception("Middleware [$middleware] no registrado");
            }

            $class = self::$routeMiddleware[$middleware];
            (new $class())->handle();
        }
    }
}

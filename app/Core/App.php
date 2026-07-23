<?php

namespace App\Core;

use App\Services\AuthenticationService;
use App\Services\TokenService;

use App\Repositories\UsuarioRepository;
use App\Repositories\EstacionRepository;

use App\Controllers\LoginController;

$container = new Container();


$container->set(
    UsuarioRepository::class,
    function () {
        return new UsuarioRepository();
    }
);


$container->set(
    EstacionRepository::class,
    function () {
        return new EstacionRepository();
    }
);


$container->set(
    TokenService::class,
    function ($container) {

        return new TokenService(
            $container->get(
                UsuarioRepository::class
            )
        );
    }
);


$container->set(
    AuthenticationService::class,
    function ($container) {

        return new AuthenticationService(
            $container->get(
                UsuarioRepository::class
            ),

            $container->get(
                EstacionRepository::class
            ),

            $container->get(
                TokenService::class
            )
        );
    }
);


$container->set(
    LoginController::class,
    function ($container) {

        return new LoginController(
            $container->get(
                AuthenticationService::class
            ),

            $container->get(
                TokenService::class
            )
        );
    }
);

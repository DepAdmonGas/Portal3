<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\JsonResponse;
use App\Core\Logger;
use App\Services\TokenService;
use App\Services\SessionService;

use Throwable;


class AuthController
{

    private TokenService $tokenService;
    private SessionService $sessionService;


    public function __construct(
        TokenService $tokenService,
        SessionService $sessionService
    ) {
        $this->tokenService = $tokenService;
        $this->sessionService = $sessionService;
    }



    /**
     * Logout del usuario actual
     */
    public function logout(): void
    {
        try {

            $userId = Auth::id();


            Logger::info(
                'Logout ejecutado',
                [
                    'user_id' => $userId
                ]
            );


            /**
             * Elimina cookies JWT
             */
            $this->tokenService
                ->forgetCookies();


            /**
             * Destruye sesión
             */
            $this->sessionService
                ->logout();



            JsonResponse::success(
                'Sesión cerrada correctamente'
            );
        } catch (Throwable $e) {


            Logger::critical($e);


            JsonResponse::serverError();
        }
    }
}

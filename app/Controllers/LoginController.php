<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Logger;
use App\Core\Request;
use App\Core\JsonResponse;
use App\Core\RateLimiter;
use App\Services\AuthenticationService;
use App\Services\TokenService;

use Throwable;

class LoginController
{
    private AuthenticationService $authenticationService;
    private TokenService $tokenService;


    public function __construct(
        AuthenticationService $authenticationService,
        TokenService $tokenService
    ) {
        $this->authenticationService = $authenticationService;
        $this->tokenService = $tokenService;
    }


    public function index()
    {
        $data = [
            'title' => 'Login Portal3',
            'scripts' => [
                '/js/login/actions-login.init.js?v=3.0.1',
            ]
        ];

        View::render('login/index', $data, 'auth');
    }


    public function login()
    {
        try {

            if (!RateLimiter::check('login')) {
                return;
            }


            $result = $this->authenticationService->login(
                Request::jsonInput('usuario'),
                Request::jsonInput('password'),
                Request::jsonInput('two_factor_code')
            );


            if ($result->type === 'success') {

                JsonResponse::success(
                    $result->message,
                    $result->data
                );

                return;
            }


            if ($result->type === 'two_factor_required') {

                JsonResponse::send([
                    'type' => $result->type,
                    'message' => $result->message,
                    ...$result->data
                ]);

                return;
            }


            JsonResponse::error(
                '¡Usuario o contraseña Incorrectos!'
            );
        } catch (Throwable $e) {

            Logger::critical($e);

            JsonResponse::error('¡Usuario o contraseña Incorrectos!');
        }
    }


    public function refreshToken()
    {
        try {

            $result = $this->tokenService->refresh();


            if ($result->type === 'success') {

                JsonResponse::success(
                    $result->message,
                    $result->data
                );

                return;
            }


            JsonResponse::error(
                $result->message,
                $result->status
            );
        } catch (Throwable $e) {

            Logger::critical($e);

            JsonResponse::serverError();
        }
    }
}

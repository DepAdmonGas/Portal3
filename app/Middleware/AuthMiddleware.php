<?php

namespace App\Middleware;


use App\Core\Session;
use App\Core\Cookie;
use App\Services\TokenService;
use App\Repositories\UsuarioRepository;

class AuthMiddleware
{
    private TokenService $tokenService;
    private UsuarioRepository $usuarioRepository;


    public function __construct(
        TokenService $tokenService,
        UsuarioRepository $usuarioRepository
    ) {

        $this->tokenService = $tokenService;
        $this->usuarioRepository = $usuarioRepository;
    }


    public function handle(): void
    {
        $payload = $this->tokenService->validateAccessToken();

        if (!$payload) {

            $user = $this->tokenService->refresh();

            if (!$user) {
                $this->redirectLogin();
            }

            $payload = $this->tokenService->validateAccessToken();

            if (!$payload) {
                $this->redirectLogin();
            }
        }

        if (!Session::has('usuario')) {
            $this->redirectLogin();
        }

        Session::set(
            'LAST_ACTIVITY',
            time()
        );

        if (($payload->exp - time()) < 600) {

            $user = $this->usuarioRepository->findById(
                (int) $payload->sub
            );

            if ($user) {
                $this->tokenService->refreshAccessToken($user);
            }
        }
    }


    private function redirectLogin(): void
    {

        $method =
            $_SERVER['REQUEST_METHOD'] ?? 'GET';



        Session::destroy();



        Cookie::forget(
            'token'
        );


        Cookie::forget(
            'refresh_token'
        );



        if (
            in_array(
                $method,
                [
                    'POST',
                    'PUT',
                    'DELETE',
                    'PATCH'
                ]
            )
        ) {

            header(
                'Content-Type: application/json'
            );


            http_response_code(401);


            echo json_encode([
                'success' => false,
                'message' =>
                'Sesión no válida. Por favor inicie sesión nuevamente.'
            ]);


            exit;
        }



        header(
            'Location: /login'
        );

        exit;
    }
}

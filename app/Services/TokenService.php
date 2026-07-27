<?php

namespace App\Services;

use App\Core\Cookie;
use App\Core\JWTService;
use App\Core\Logger;
use App\Core\Request;
use App\Models\Usuario;
use App\Repositories\UsuarioRepository;

class TokenService
{
    public function __construct(
        private UsuarioRepository $usuarioRepository
    ) {}

    /**
     * Genera access y refresh token.
     */
    public function issue(Usuario $user): string
    {
        $tokens = JWTService::createTokenPair([
            'sub'     => $user->id,
            'nombre'  => $user->nombre,
        ]);

        JWTService::setCookies(
            $tokens['access_token'],
            $tokens['refresh_token']
        );

        return $tokens['access_token'];
    }

    /**
     * Renueva únicamente el access token.
     */
    public function refreshAccessToken(Usuario $user): string
    {
        $accessToken = JWTService::createAccessToken([
            'sub'     => $user->id,
            'nombre'  => $user->nombre,
        ]);

        JWTService::setAccessCookie($accessToken);

        return $accessToken;
    }

    /**
     * Usa el refresh token para emitir un nuevo access token.
     */
    public function refresh(): ?Usuario
    {
        $refreshToken = Cookie::get('refresh_token');

        if (!$refreshToken) {

            Logger::info(
                'Refresh token inexistente',
                [
                    'ip' => Request::ip()
                ]
            );

            return null;
        }

        try {

            if (!JWTService::isRefreshToken($refreshToken)) {

                Logger::warning(
                    'Intento de refresh con token inválido',
                    [
                        'ip' => Request::ip()
                    ]
                );

                return null;
            }

            $payload = JWTService::validate($refreshToken);

            $user = $this->usuarioRepository->findById(
                (int) $payload->sub
            );

            if (!$user || $user->estatus !== 0) {

                Logger::warning(
                    'Usuario inactivo durante refresh',
                    [
                        'user_id' => $payload->sub,
                        'ip'      => Request::ip()
                    ]
                );

                return null;
            }

            $this->refreshAccessToken($user);

            Logger::info(
                'Access token renovado',
                [
                    'user_id' => $user->id,
                    'ip'      => Request::ip()
                ]
            );

            return $user;
        } catch (\Throwable $e) {

            Logger::warning(
                'Refresh token expirado',
                [
                    'ip'    => Request::ip(),
                    'error' => $e->getMessage()
                ]
            );

            return null;
        }
    }

    /**
     * Valida el access token actual.
     */
    public function validateAccessToken(): ?object
    {
        $token = Cookie::get('token');

        if (!$token) {
            return null;
        }

        try {
            return JWTService::validate($token);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Elimina las cookies de autenticación.
     */
    public function forgetCookies(): void
    {
        Cookie::forget('token');
        Cookie::forget('refresh_token');
    }
}

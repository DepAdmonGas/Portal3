<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use Firebase\JWT\JWT;

/** Signs short-lived human access tokens exclusively for the Checador API. */
final class ChecadorApiJwtService
{
    public const ACCESS_TOKEN_TTL = 300;
    private const MINIMUM_SECRET_BYTES = 32;

    public static function createAccessToken(Usuario $user): string
    {
        $secret = $_ENV['CHECADOR_API_JWT_SECRET'] ?? getenv('CHECADOR_API_JWT_SECRET');
        if (!is_string($secret)
            || trim($secret) === ''
            || $secret !== trim($secret)
            || strlen($secret) < self::MINIMUM_SECRET_BYTES) {
            throw new ChecadorApiJwtConfigurationException(
                'Checador API signing key is not configured or does not meet minimum requirements.'
            );
        }

        $userId = filter_var($user->id, FILTER_VALIDATE_INT);
        if ($userId === false || $userId < 1) {
            throw new \InvalidArgumentException('Authenticated Portal3 user identity is invalid.');
        }

        $issuedAt = time();
        return JWT::encode([
            'iss' => 'portal3_auth',
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::ACCESS_TOKEN_TTL,
            'sub' => $userId,
            'type' => 'access',
            'jti' => bin2hex(random_bytes(16)),
        ], $secret, 'HS256');
    }
}

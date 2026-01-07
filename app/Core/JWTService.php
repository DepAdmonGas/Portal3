<?php
namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    private static string $algo = 'HS256';

    private static function getKey(): string
    {
        $key = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');

        if (!$key) {
            throw new \Exception('JWT_SECRET no está configurado');
        }

        return $key;
    }

    public static function create(array $user): string
    {
        $ttl = 60 * 60 * 24;
        $payload = [
            'iss' => 'mi-app',
            'iat' => time(),
            'exp' => time() + $ttl,
            'sub' => $user['id'],
            'nombre' => $user['nombre']
        ];

        return JWT::encode(
            $payload,
            self::getKey(),
            self::$algo
        );
    }

    public static function validate(string $token): object
    {
        return JWT::decode(
            $token,
            new Key(self::getKey(), self::$algo)
        );
    }
}

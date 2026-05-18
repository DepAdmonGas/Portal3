<?php
namespace App\Core;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * JWTService - Servicio para manejo de JSON Web Tokens
 * 
 * SECURITY: Implementa TTL reducidos (BAJO #33)
 * - Access token: 1 hora (antes 24 horas)
 * - Refresh token: 7 días
 * 
 * @author Security Team
 * @version 1.1.0
 */
class JWTService
{
    private static string $algo = 'HS256';

    // ============================================================
    // SECURITY: TTLs reducidos (BAJO #33)
    // ============================================================
    
    /** TTL para access token: 1 hora (antes: 24 horas) */
    public const ACCESS_TOKEN_TTL = 3600; // 1 hora en segundos
    
    /** TTL para refresh token: 7 días */
    public const REFRESH_TOKEN_TTL = 604800; // 7 días en segundos
    
    /** Nombre del claim para tipo de token */
    private const CLAIM_TYPE = 'type';
    
    /** Nombre del claim para JWT ID (para blacklist) */
    private const CLAIM_JTI = 'jti';

    private static function getKey(): string
    {
        $key = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');

        if (!$key) {
            throw new \Exception('JWT_SECRET no está configurado');
        }

        return $key;
    }

    /**
     * Crea un access token JWT (compatibilidad hacia atrás)
     * 
     * @param array $user Datos del usuario
     * @return string Token JWT
     */
    public static function create(array $user): string
    {
        return self::createAccessToken($user);
    }

    /**
     * Crea un access token JWT (1 hora de validez)
     * 
     * SECURITY: BAJO #33 - TTL reducido de 24h a 1h
     * 
     * @param array $user Datos del usuario
     * @return string Token JWT
     */
    public static function createAccessToken(array $user): string
    {
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'portal3',
            'iat' => time(),
            'exp' => time() + self::ACCESS_TOKEN_TTL,
            'sub' => $user['id'],
            'nombre' => $user['nombre'] ?? '',
            self::CLAIM_TYPE => 'access',
            self::CLAIM_JTI => bin2hex(random_bytes(16)) // Unique ID para blacklist
        ];

        return JWT::encode(
            $payload,
            self::getKey(),
            self::$algo
        );
    }

    /**
     * Crea un refresh token JWT (7 días de validez)
     * 
     * El refresh token se usa para obtener un nuevo access token
     * sin necesidad de re-autenticarse.
     * 
     * @param array $user Datos del usuario
     * @return string Token JWT
     */
    public static function createRefreshToken(array $user): string
    {
        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'portal3',
            'iat' => time(),
            'exp' => time() + self::REFRESH_TOKEN_TTL,
            'sub' => $user['id'],
            'nombre' => $user['nombre'] ?? '',
            self::CLAIM_TYPE => 'refresh',
            self::CLAIM_JTI => bin2hex(random_bytes(16))
        ];

        return JWT::encode(
            $payload,
            self::getKey(),
            self::$algo
        );
    }

    /**
     * Valida un token JWT y retorna el payload
     * 
     * @param string $token Token JWT a validar
     * @return object Payload decodificado
     * @throws \Exception Si el token es inválido o ha expirado
     */
    public static function validate(string $token): object
    {
        return JWT::decode(
            $token,
            new Key(self::getKey(), self::$algo)
        );
    }

    /**
     * Verifica si el token es un access token válido
     * 
     * @param string $token Token a verificar
     * @return bool True si es un access token válido
     */
    public static function isAccessToken(string $token): bool
    {
        try {
            $payload = self::validate($token);
            return isset($payload->{self::CLAIM_TYPE}) && $payload->{self::CLAIM_TYPE} === 'access';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verifica si el token es un refresh token válido
     * 
     * @param string $token Token a verificar
     * @return bool True si es un refresh token válido
     */
    public static function isRefreshToken(string $token): bool
    {
        try {
            $payload = self::validate($token);
            return isset($payload->{self::CLAIM_TYPE}) && $payload->{self::CLAIM_TYPE} === 'refresh';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene los TTLs configurados (para información)
     * 
     * @return array
     */
    public static function getTokenConfigs(): array
    {
        return [
            'access_token_ttl' => self::ACCESS_TOKEN_TTL,
            'access_token_ttl_human' => self::formatTTL(self::ACCESS_TOKEN_TTL),
            'refresh_token_ttl' => self::REFRESH_TOKEN_TTL,
            'refresh_token_ttl_human' => self::formatTTL(self::REFRESH_TOKEN_TTL),
        ];
    }

    /**
     * Formatea TTL a texto legible
     * 
     * @param int $seconds
     * @return string
     */
    private static function formatTTL(int $seconds): string
    {
        if ($seconds < 3600) {
            return "{$seconds} segundos";
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . " horas";
        } else {
            return round($seconds / 86400, 1) . " días";
        }
    }
}
<?php
declare(strict_types=1);

namespace App\Core;

/**
 * TwoFactorAuth - Implementación de TOTP para autenticación de dos factores
 * 
 * Implementa RFC 6238 TOTP (Time-based One-Time Password)
 * Compatible con Google Authenticator, Authy, Microsoft Authenticator, etc.
 * 
 * @author Security Team
 * @version 1.0.0
 */
class TwoFactorAuth
{
    /** Intervalo de tiempo para cada código (30 segundos estándar) */
    private const STEP = 30;
    
    /** Longitud del código TOTP (6 dígitos estándar) */
    private const DIGITS = 6;
    
    /** Algoritmo de hashing (SHA1 estándar) */
    private const ALGORITHM = 'sha1';
    
    /** Caracteres para códigos de respaldo (sin I, O, 0, 1 para evitar confusión) */
    private const BACKUP_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    
    /** Cantidad de códigos de respaldo */
    private const BACKUP_CODES_COUNT = 10;

    /**
     * Genera un secreto aleatorio para el usuario
     * 
     * @param int $length Longitud del secreto (default: 32 bytes)
     * @return string Secreto en base32
     */
    public static function generateSecret(int $length = 32): string
    {
        return self::base32Encode(random_bytes($length));
    }

    /**
     * Genera el código TOTP actual para el secreto dado
     * 
     * @param string $secret Secreto en base32
     * @param int|null $time Timestamp alternativo (para testing)
     * @return string Código TOTP de 6 dígitos
     */
    public static function getCode(string $secret, ?int $time = null): string
    {
        $time = $time ?? time();
        
        $secret = self::base32Decode($secret);
        $time = pack('N', (int)($time / self::STEP));
        $time = str_pad($time, 8, "\0", STR_PAD_LEFT);
        
        $hash = hash_hmac(self::ALGORITHM, $time, $secret, true);
        
        $offset = ord($hash[19]) & 0x0f;
        
        $binary = (
            (ord($hash[$offset]) & 0x7f) << 24 |
            (ord($hash[$offset + 1]) & 0xff) << 16 |
            (ord($hash[$offset + 2]) & 0xff) << 8 |
            (ord($hash[$offset + 3]) & 0xff)
        );
        
        $otp = $binary % (int)pow(10, self::DIGITS);
        
        return str_pad((string)$otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica un código TOTP contra el secreto
     * 
     * Acepta el código actual y el anterior/futuro para permitir
     * desfase de tiempo del usuario (ventana de 1 paso antes y después)
     * 
     * @param string $secret Secreto en base32
     * @param string $code Código TOTP a verificar
     * @param int $window Ventana de tiempo aceptable (default: 1 = ±30s)
     * @return bool True si el código es válido
     */
    public static function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $time = time();
        
        // Verificar código actual y ventana de tiempo
        for ($i = -$window; $i <= $window; $i++) {
            $timeSlot = $time + ($i * self::STEP);
            
            if (self::getCode($secret, $timeSlot) === $code) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Genera códigos de respaldo (backup codes)
     * 
     * @return array Array de códigos de respaldo
     */
    public static function generateBackupCodes(): array
    {
        $codes = [];
        
        for ($i = 0; $i < self::BACKUP_CODES_COUNT; $i++) {
            $code = '';
            
            // Generar código de 8 caracteres en formato XXXX-XXXX
            for ($j = 0; $j < 8; $j++) {
                if ($j === 4) {
                    $code .= '-';
                }
                
                $code .= self::BACKUP_CHARS[random_int(0, strlen(self::BACKUP_CHARS) - 1)];
            }
            
            // Generar hash del código para almacenamiento seguro
            $codes[] = [
                'code' => $code,
                'hash' => password_hash($code, PASSWORD_BCRYPT),
                'used' => false
            ];
        }
        
        return $codes;
    }

    /**
     * Verifica un código de respaldo
     * 
     * @param string $code Código de respaldo ingresado
     * @param array $storedCodes Códigos almacenados (con hash)
     * @return int|null Índice del código usado, o null si inválido
     */
    public static function verifyBackupCode(string $code, array $storedCodes): ?int
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', $code));
        
        foreach ($storedCodes as $index => $stored) {
            if ($stored['used']) {
                continue;
            }
            
            if (password_verify($code, $stored['hash'])) {
                return $index;
            }
        }
        
        return null;
    }

    /**
     * Genera URL para código QR
     * 
     * @param string $secret Secreto en base32
     * @param string $issuer Nombre de la aplicación
     * @param string $account Nombre de usuario/email
     * @return string URL del código QR en formato otpauth://
     */
    public static function getQrCodeUrl(string $secret, string $issuer, string $account): string
    {
        $label = rawurlencode($account);
        $issuer = rawurlencode($issuer);
        
        return "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Genera imagen SVG del código QR (sin dependencia externa)
     * 
     * @param string $secret Secreto en base32
     * @param string $issuer Nombre de la aplicación
     * @param string $account Nombre de usuario/email
     * @return string Imagen QR en formato SVG
     */
    public static function getQrCodeImage(string $secret, string $issuer, string $account): string
    {
        $url = self::getQrCodeUrl($secret, $issuer, $account);
        
        // Generar QR code simple como SVG (implementación básica sin dependencia)
        // Para producción, usar library como bacon/bacon-qr-code
        return self::generateSimpleQrCode($url);
    }

    /**
     * Genera un QR code simple en formato texto/ASCII
     * Para uso debugging o fallback
     * 
     * @param string $data Datos a codificar
     * @return string Representación del QR
     */
    public static function generateSimpleQrCode(string $data): string
    {
        // Retornar URL para que el frontend genere el QR
        return $data;
    }

    /**
     * Codifica bytes a base32
     * 
     * @param string $data Bytes a codificar
     * @return string String en base32
     */
    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        
        $output = '';
        $buffer = 0;
        $bufferSize = 0;
        
        for ($i = 0; $i < strlen($data); $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bufferSize += 8;
            
            while ($bufferSize >= 5) {
                $bufferSize -= 5;
                $output .= $alphabet[($buffer >> $bufferSize) & 0x1F];
            }
        }
        
        if ($bufferSize > 0) {
            $buffer = $buffer << (5 - $bufferSize);
            $output .= $alphabet[$buffer & 0x1F];
        }
        
        return $output;
    }

    /**
     * Decodifica base32 a bytes
     * 
     * @param string $data String en base32
     * @return string Bytes decodificados
     */
    private static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        
        $data = strtoupper(preg_replace('/[^A-Z2-7]/', '', $data));
        
        $output = '';
        $buffer = 0;
        $bufferSize = 0;
        
        for ($i = 0; $i < strlen($data); $i++) {
            $value = strpos($alphabet, $data[$i]);
            
            if ($value === false) {
                continue;
            }
            
            $buffer = ($buffer << 5) | $value;
            $bufferSize += 5;
            
            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $output .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }
        
        return $output;
    }

    /**
     * Convierte secreto a formato legible para humanos
     * 
     * @param string $secret Secreto en base32
     * @return string Secreto formateado (ej: ABCD-EFGH-IJKL-MNOP)
     */
    public static function formatSecret(string $secret): string
    {
        return implode('-', str_split($secret, 4));
    }
}
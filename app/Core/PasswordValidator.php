<?php
declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * PasswordValidator - Validador de fortaleza de contraseñas
 * 
 * Implementa políticas de seguridad para contraseñas según OWASP
 * y mejores prácticas de la industria.
 * 
 * Reglas implementadas:
 * - Longitud mínima: 8 caracteres
 * - Longitud máxima: 128 caracteres (prevenir DoS)
 * - Al menos 1 letra mayúscula
 * - Al menos 1 letra minúscula
 * - Al menos 1 número
 * - Al menos 1 carácter especial
 * 
 * @author Security Team
 * @version 1.0.0
 */
class PasswordValidator
{
    /** Longitud mínima de contraseña */
    private const MIN_LENGTH = 8;
    
    /** Longitud máxima de contraseña */
    private const MAX_LENGTH = 128;
    
    /** Patrón para verificar mayúsculas */
    private const PATTERN_UPPERCASE = '/[A-Z]/';
    
    /** Patrón para verificar minúsculas */
    private const PATTERN_LOWERCASE = '/[a-z]/';
    
    /** Patrón para verificar números */
    private const PATTERN_NUMBER = '/[0-9]/';
    
    /** Patrón para verificar caracteres especiales */
    private const PATTERN_SPECIAL = '/[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\|`~]/';
    
    /** Mensajes de error por tipo de validación */
    private const MESSAGES = [
        'required' => 'La contraseña es obligatoria',
        'too_short' => 'La contraseña debe tener al menos :min caracteres',
        'too_long' => 'La contraseña excede el límite de :max caracteres',
        'no_uppercase' => 'La contraseña debe contener al menos una letra mayúscula',
        'no_lowercase' => 'La contraseña debe contener al menos una letra minúscula',
        'no_number' => 'La contraseña debe contener al menos un número',
        'no_special' => 'La contraseña debe contener al menos un carácter especial (!@#$%^&*)',
    ];

    /**
     * Valida una contraseña contra todas las reglas de seguridad
     * 
     * @param string $password La contraseña a validar
     * @return array Resultado de la validación con estado y errores
     */
    public static function validate(string $password): array
    {
        $errors = [];
        
        // Validación 1: Requerida
        if (empty($password)) {
            return [
                'valid' => false,
                'errors' => [self::MESSAGES['required']],
                'score' => 0
            ];
        }
        
        // Validación 2: Longitud mínima
        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = str_replace(':min', self::MIN_LENGTH, self::MESSAGES['too_short']);
        }
        
        // Validación 3: Longitud máxima (prevenir DoS)
        if (strlen($password) > self::MAX_LENGTH) {
            $errors[] = str_replace(':max', self::MAX_LENGTH, self::MESSAGES['too_long']);
        }
        
        // Validación 4: Mayúsculas
        if (!preg_match(self::PATTERN_UPPERCASE, $password)) {
            $errors[] = self::MESSAGES['no_uppercase'];
        }
        
        // Validación 5: Minúsculas
        if (!preg_match(self::PATTERN_LOWERCASE, $password)) {
            $errors[] = self::MESSAGES['no_lowercase'];
        }
        
        // Validación 6: Números
        if (!preg_match(self::PATTERN_NUMBER, $password)) {
            $errors[] = self::MESSAGES['no_number'];
        }
        
        // Validación 7: Caracteres especiales
        if (!preg_match(self::PATTERN_SPECIAL, $password)) {
            $errors[] = self::MESSAGES['no_special'];
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'score' => self::calculateScore($password)
        ];
    }

    /**
     * Valida la contraseña y lanza excepción si no es válida
     * 
     * @param string $password La contraseña a validar
     * @throws InvalidArgumentException Si la contraseña no cumple los requisitos
     */
    public static function assert(string $password): void
    {
        $result = self::validate($password);
        
        if (!$result['valid']) {
            throw new InvalidArgumentException(
                implode('. ', $result['errors'])
            );
        }
    }

    /**
     * Calcula un score de seguridad de la contraseña (0-100)
     * 
     * @param string $password La contraseña a evaluar
     * @return int Score de seguridad
     */
    private static function calculateScore(string $password): int
    {
        $score = 0;
        $length = strlen($password);
        
        // Puntuación por longitud (hasta 30 puntos)
        $score += min(30, $length * 2);
        
        // Puntuación por complejidad (hasta 70 puntos)
        if (preg_match(self::PATTERN_UPPERCASE, $password)) $score += 15;
        if (preg_match(self::PATTERN_LOWERCASE, $password)) $score += 15;
        if (preg_match(self::PATTERN_NUMBER, $password)) $score += 15;
        if (preg_match(self::PATTERN_SPECIAL, $password)) $score += 25;
        
        return min(100, $score);
    }

    /**
     * Genera una contraseña segura aleatoria
     * 
     * @param int $length Longitud de la contraseña (default: 16)
     * @return string Contraseña generada
     */
    public static function generate(int $length = 16): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=';
        
        $chars = $uppercase . $lowercase . $numbers . $special;
        
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];
        
        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return str_shuffle($password);
    }

    /**
     * Valida si una contraseña ha sido comprometida (usando Have I Been Pwned API)
     * 
     * Nota: Esta función requiere conexión a internet y es opcional
     * 
     * @param string $password La contraseña a verificar
     * @return bool True si la contraseña está comprometida
     */
    public static function isCompromised(string $password): bool
    {
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);
        
        $response = @file_get_contents(
            'https://api.pwnedpasswords.com/range/' . $prefix
        );
        
        if ($response === false) {
            // Si no se puede conectar, asumir que no está comprometida
            return false;
        }
        
        $hashes = explode("\r\n", $response);
        
        foreach ($hashes as $hashInfo) {
            list($hashSuffix, $count) = explode(':', $hashInfo);
            if ($hashSuffix === $suffix) {
                return (int)$count > 0;
            }
        }
        
        return false;
    }
}
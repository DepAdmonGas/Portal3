<?php

declare(strict_types=1);

namespace App\Core;

final class JsonResponse
{

    public const TYPE_SUCCESS = 'success';
    public const TYPE_ERROR = 'error';
    public const TYPE_VALIDATION = 'validation';
    public const TYPE_WARNING = 'warning';
    public const TYPE_INFO = 'info';

    /**
     * Envía una respuesta JSON.
     */
    public static function send(
        array $data,
        int $status = 200,
        array $headers = []
    ): never {

        if (!headers_sent()) {

            http_response_code($status);

            header('Content-Type: application/json; charset=UTF-8');

            foreach ($headers as $key => $value) {
                header("$key: $value");
            }
        }

        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_INVALID_UTF8_SUBSTITUTE
        );

        exit;
    }

    /**
     * Respuesta exitosa.
     */
    public static function success(
        string $message = 'OK',
        array $data = [],
        int $status = 200
    ): never {

        self::send(
            array_merge([
                'success' => true,
                'type' => self::TYPE_SUCCESS,
                'message' => $message
            ], $data),
            $status
        );
    }

    /**
     * Error genérico.
     */
    public static function error(
        string $message,
        int $status = 400,
        array $data = []
    ): never {

        self::send(
            array_merge([
                'success' => false,
                'type' => self::TYPE_ERROR,
                'message' => $message
            ], $data),
            $status
        );
    }

    /**
     * Error de validación.
     */
    public static function validation(
        string $message = 'Los datos enviados no son válidos.',
        array $errors = []
    ): never {

        self::send([
            'success' => false,
            'type' => self::TYPE_VALIDATION,
            'message' => $message,
            'errors' => $errors
        ], 422);
    }

    /**
     * No autenticado.
     */
    public static function unauthorized(
        string $message = 'No autorizado.'
    ): never {

        self::error($message, 401);
    }

    /**
     * Acceso prohibido.
     */
    public static function forbidden(
        string $message = 'Acceso denegado.'
    ): never {

        self::error($message, 403);
    }

    /**
     * Recurso inexistente.
     */
    public static function notFound(
        string $message = 'Recurso no encontrado.'
    ): never {

        self::error($message, 404);
    }

    /**
     * Conflicto.
     */
    public static function conflict(
        string $message = 'Conflicto.'
    ): never {

        self::error($message, 409);
    }

    /**
     * Rate limit.
     */
    public static function tooManyRequests(
        string $message = 'Demasiadas solicitudes.'
    ): never {

        self::error($message, 429);
    }

    /**
     * Error interno.
     */
    public static function serverError(
        string $message = 'Error interno del servidor.'
    ): never {

        self::error($message, 500);
    }

    /**
     * Respuesta personalizada.
     */
    public static function custom(
        array $payload,
        int $status = 200
    ): never {

        self::send($payload, $status);
    }
}

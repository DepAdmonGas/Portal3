<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/** Spanish, user-facing labels for the raw values shown by the MVP report. */
final class ChecadorPresentationFormatter
{
    /** @var array<string,true> */
    private static array $loggedUnknownValues = [];

    public static function captureMethodLabel(mixed $value): string
    {
        $code = self::code($value);
        if ($code === null) {
            return 'No disponible';
        }

        $labels = [
            'BIOMETRIC' => 'Registro biométrico',
            'PIN' => 'Captura con PIN',
            'CARD' => 'Captura con tarjeta',
            'NFC' => 'Captura mediante NFC',
            'MOBILE' => 'Captura móvil',
            'WEB_TERMINAL' => 'Terminal web',
            'TEMPORARY_CODE' => 'Código temporal',
            'WEB_CAPTURE' => 'Captura web',
            'PORTAL_TELEGRAM_VERIFIED' => 'Captura en Portal mediante Telegram',
        ];

        if (isset($labels[$code])) {
            return $labels[$code];
        }

        self::logUnknown('capture_method', $code);
        return 'Método de captura no reconocido';
    }

    public static function processingStatusLabel(mixed $value): string
    {
        return match (self::code($value)) {
            'RECEIVED' => 'Marcación recibida',
            'PENDING_MATCH' => 'Identificación pendiente',
            null => 'Estado no disponible',
            default => self::unknownLabel('processing_status', self::code($value) ?? ''),
        };
    }

    public static function processingStatusExplanation(mixed $value): string
    {
        return match (self::code($value)) {
            'RECEIVED' => 'Solo confirma que la marcación fue recibida; no confirma identidad, jornada ni integridad.',
            'PENDING_MATCH' => 'La identidad del colaborador aún no ha sido resuelta.',
            null => '',
            default => self::unknownExplanation('processing_status', self::code($value) ?? ''),
        };
    }

    public static function processingStatusBadgeClass(mixed $value): string
    {
        return match (self::code($value)) {
            'RECEIVED' => 'text-bg-info',
            'PENDING_MATCH' => 'text-bg-warning',
            default => 'text-bg-secondary',
        };
    }

    public static function incidenceStatusLabel(mixed $value): string
    {
        $code = self::code($value);
        $labels = [
            'ACTIVE' => 'Activa',
            'PENDING_APPROVAL' => 'Pendiente de autorización',
            'PENDING_REVIEW' => 'Pendiente de revisión',
            'PENDING' => 'Pendiente de revisión',
            'RESOLVED_PENDING_AUTHORIZATION' => 'Resuelta; pendiente de autorización',
            'REVIEW_REQUIRED' => 'Requiere revisión',
            'REJECTED' => 'Rechazada',
            'REVOKED' => 'Revocada',
            'CANCELLED' => 'Cancelada',
            'SUPERSEDED' => 'Sustituida',
        ];

        if ($code === null) {
            return 'Estado no disponible';
        }
        if (isset($labels[$code])) {
            return $labels[$code];
        }

        self::logUnknown('incidence_status', $code);
        return 'Estado no reconocido';
    }

    public static function administrativeAdjustmentTypeLabel(mixed $value): string
    {
        $code = self::code($value);
        if ($code === null) {
            return 'Tipo de ajuste no disponible';
        }

        $labels = [
            'OVERRIDE_STATUS' => 'Ajuste administrativo de estado',
            'INTERPRETED_ENTRY_TIME' => 'Hora de entrada administrativa interpretada',
            'INTERPRETED_EXIT_TIME' => 'Hora de salida administrativa interpretada',
            'JUSTIFY_LATENESS' => 'Justificación administrativa de retardo',
        ];

        if (isset($labels[$code])) {
            return $labels[$code];
        }

        self::logUnknown('administrative_adjustment_type', $code);
        return 'Tipo de ajuste no reconocido';
    }

    public static function administrativeAdjustmentStatusLabel(mixed $value): string
    {
        $code = self::code($value);
        if ($code === null) {
            return 'Estado del ajuste no disponible';
        }

        $labels = [
            'ACTIVE' => 'Vigente',
            'SUPERSEDED' => 'Sustituido por revisión posterior',
            'CANCELLED' => 'Cancelado',
        ];

        if (isset($labels[$code])) {
            return $labels[$code];
        }

        self::logUnknown('administrative_adjustment_status', $code);
        return 'Estado del ajuste no reconocido';
    }

    public static function collaboratorLabel(mixed $resolvedWorkerId): string
    {
        $workerId = self::code($resolvedWorkerId);
        return $workerId === null
            ? 'Colaborador no identificado'
            : 'Colaborador identificado';
    }

    public static function integrityLabel(): string
    {
        return 'Integridad no verificada por Portal3';
    }

    private static function unknownLabel(string $field, string $code): string
    {
        self::logUnknown($field, $code);
        return $field === 'processing_status'
            ? 'Estado no reconocido'
            : 'Valor no reconocido';
    }

    private static function unknownExplanation(string $field, string $code): string
    {
        self::logUnknown($field, $code);
        return 'No es posible interpretar el estado de esta marcación.';
    }

    private static function logUnknown(string $field, string $code): void
    {
        // Do not let malformed database values break report rendering, and
        // avoid repeating the same warning for every row in a large result.
        $safeCode = substr(preg_replace('/[\x00-\x1F\x7F]/', '?', $code) ?? '', 0, 64);
        $key = $field . ':' . $safeCode;
        if ($safeCode === '' || isset(self::$loggedUnknownValues[$key])) {
            return;
        }
        self::$loggedUnknownValues[$key] = true;

        try {
            Logger::warning('Unknown Checador presentation value', [
                'presentation_field' => $field,
                'status_code' => $safeCode,
            ]);
        } catch (\Throwable) {
            // Logging is best-effort; the public label remains generic.
        }
    }

    private static function code(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}

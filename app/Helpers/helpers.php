<?php

if (!function_exists('base_url')) {
    function base_url(): string
    {
        return rtrim($_ENV['APP_URL'] ?? '', '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url() . '/assets/' . ltrim($path, '/');
    }
}

function formatearFecha($fecha)
{
    if (empty($fecha)) return '';

    $date = \Carbon\Carbon::parse($fecha);

    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
        4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
        10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];

    return $date->format('d') . ' de ' . $meses[(int)$date->format('m')] . ' del ' . $date->format('Y');
}

if (!function_exists('formatearFechaCorta')) {

    function formatearFechaCorta($fecha)
    {
        if (empty($fecha)) return '';

        // Si es objeto (Carbon o DateTime)
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d-m-Y');
        }

        $fecha = (string) $fecha;

        // Fechas inválidas comunes
        if (
            $fecha === '0000-00-00' ||
            str_contains($fecha, '-0001')
        ) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($fecha)->format('d-m-Y');
        } catch (\Exception $e) {
            return '';
        }
    }
}

function formatDate($fecha)
{
    if (empty($fecha)) return '';

    return \Carbon\Carbon::parse($fecha)->format('Y-m-d');
}

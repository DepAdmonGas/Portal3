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

if (!function_exists('formatearFecha')) {

    function formatearFecha($fecha) {

        if (empty($fecha)) return '';

        // 👉 Si es objeto (Carbon de Eloquent)
        if (is_object($fecha)) {
            $fecha = $fecha->format('Y-m-d');
        }

        $timestamp = strtotime($fecha);

        if (!$timestamp) return '';

        $meses = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
            '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
            '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
            '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
        ];

        $dia = date('d', $timestamp);
        $mes = $meses[date('m', $timestamp)];
        $anio = date('Y', $timestamp);

        return "$dia de $mes del $anio";
    }
}

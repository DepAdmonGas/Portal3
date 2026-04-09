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

if (!function_exists('formatearFechaCorta')) {

    function formatearFechaCorta($fecha)
    {
        if (empty($fecha)) return '';

        if (is_object($fecha)) {
            return $fecha->format('d-m-Y');
        }

        $date = \DateTime::createFromFormat('d/m/Y H:i', $fecha);
        if ($date) {
            return $date->format('d-m-Y');
        }

        $timestamp = strtotime($fecha);
        if ($timestamp) {
            return date('d-m-Y', $timestamp);
        }

        return '';
    }
}

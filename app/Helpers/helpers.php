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

        try {

            $date = new DateTime($fecha);

            // ✔ Si existe intl → usarlo
            if (class_exists('IntlDateFormatter')) {

                $formatter = new IntlDateFormatter(
                    'es_MX',
                    IntlDateFormatter::LONG,
                    IntlDateFormatter::NONE
                );

                $formatter->setPattern("dd 'de' MMMM 'del' yyyy");

                return ucfirst($formatter->format($date));
            }

            $meses = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
                4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
                10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            ];

            $dia = $date->format('d');
            $mes = $meses[(int)$date->format('m')];
            $anio = $date->format('Y');

            return "$dia de $mes del $anio";

        } catch (Exception $e) {
            return '';
        }
    }

}
<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProgramaMantenimientoService
{
    public static function txtFecha(?string $fecha): string
    {

        if (empty($fecha) || $fecha == '0000-00-00') {
            return '';
        }

        try {

            $date = Carbon::createFromFormat('Y-m-d',trim($fecha));

            $meses = [
                1 => 'Ene',
                2 => 'Feb',
                3 => 'Mar',
                4 => 'Abr',
                5 => 'May',
                6 => 'Jun',
                7 => 'Jul',
                8 => 'Ago',
                9 => 'Sep',
                10 => 'Oct',
                11 => 'Nov',
                12 => 'Dic'
            ];

            $dia = $date->format('d');
            $mes = $meses[(int)$date->format('m')];
            $year = $date->format('y');
            return "{$dia}.{$mes}.{$year}";

        } catch (\Throwable $e) {

            return '';
        }
    }

    public static function colorTD(?string $fecha): string
    {

        if (empty($fecha) || $fecha == '0000-00-00') {
            return '#D3D3D3';
        }

        try {

            $date = Carbon::createFromFormat('Y-m-d',trim($fecha));
            $hoy = Carbon::today();
            $warning = $date->copy()->subDays(3);

            if ($hoy->equalTo($date)) {
                return '#ffb6af';
            }

            if ($hoy->greaterThan($date)) {
                return '#b0f2c2';
            }

            if ($hoy->greaterThanOrEqualTo($warning)) {
                return '#fcfcda';
            }

            return '#cfe2ff';

        } catch (\Throwable $e) {

            return '#D3D3D3';
        }
    }

    public static function txtColor(?string $fecha): string
    {

        if (empty($fecha) ||$fecha == '0000-00-00') {
            return 'text-black';
        }

        try {

            $date = Carbon::createFromFormat('Y-m-d',trim($fecha));
            $hoy = Carbon::today();
            $warning = $date->copy()->subDays(3);

            if ($hoy->equalTo($date)) {
                return 'text-danger';
            }

            if ($hoy->greaterThan($date)) {
                return 'text-black';
            }

            if ($hoy->greaterThanOrEqualTo($warning)) {
                return 'text-danger';
            }

            return 'text-black';

        } catch (\Throwable $e) {

            return 'text-black';
        }
    }

    public static function buscaFechaSemanal(
        ?int $idEstacion,
        int $idMantenimiento,
        int $year,
        int $mes
    ): string {

        if ($idEstacion === null) {
            return '0000-00-00';
        }

        $fecha = DB::table(
            'po_programa_anual_mantenimiento_calendario'
        )
        ->where('id_estacion', $idEstacion)
        ->where('id_mantenimiento', $idMantenimiento)
        ->whereYear('fecha', $year)
        ->whereMonth('fecha', $mes)
        ->orderByDesc('fecha')
        ->value('fecha');

        return $fecha ?? '0000-00-00';
    }

   public static function generarMes(
    string $periodicidad,
    string $fecha,
    int $mes
    ): string {

        $periodicidad = strtolower(trim($periodicidad));

        if ($periodicidad === 'semanal') {
            return '';
        }

        $intervalos = [
            'mensual'       => 1,
            'trimestral'    => 3,
            'cuatrimestral' => 4,
            'semestral'     => 6,
            'anual'         => 12,
            'bianual'       => 24
        ];

        $intervalo = $intervalos[$periodicidad] ?? 1;
        $fechaBase = Carbon::parse($fecha);
        $mesBase = (int)$fechaBase->format('m');

        if ($intervalo === 1) {

            $nuevaFecha = $fechaBase
                ->copy()
                ->month($mes);

            if ($fechaBase->day > $nuevaFecha->daysInMonth) {
                $nuevaFecha->day = $nuevaFecha->daysInMonth;
            }

            return $nuevaFecha->format('Y-m-d');
        }

        $diferencia = $mes - $mesBase;

        if ($diferencia < 0) {
            return '';
        }

        if (($diferencia % $intervalo) !== 0) {
            return '';
        }

        $nuevaFecha = $fechaBase
            ->copy()
            ->addMonths($diferencia);

        if ($fechaBase->day > $nuevaFecha->daysInMonth) {
            $nuevaFecha->day = $nuevaFecha->daysInMonth;
        }

        return $nuevaFecha->format('Y-m-d');
    }
}
<?php

namespace App\Services;

use App\Models\Operativo\OpReciboNominaV2Puntaje;
use App\Models\Operativo\RhLocalidad;

class KpiRecibosNominaService
{
    private const ACTIVIDAD_MEXDESA  = 'Recibos Mexdesa';
    private const ACTIVIDAD_ESTACION = 'Recibos Estacion';
    private const ACTIVIDAD_OPERATIVO = 'Recibos Operativo';

    private const PUNTAJE_POR_ACTIVIDAD = 3;

    private const ESTACIONES_SIN_MEXDESA = [6, 7];

    private const COLORES = [
        'total'    => '#0d6efd',
        'obtenido' => '#198754',
    ];

    private static string $info_evaluacion = '<p>La evaluación de los Recibos de Nómina se calcula con el puntaje otorgado al <b>finalizar cada actividad</b> de cada periodo. Cada actividad cuenta con un puntaje total de <b>3 puntos</b>, y de acuerdo a la oportunidad con la que se finalice se obtiene un puntaje de <b>3</b> (oportuno), <b>2</b>, <b>1</b> o <b>0</b> (fuera de tiempo).</p><h5 class="text-primary"><strong>Actividades evaluadas:</strong></h5><ul><li><b>Recibos Mexdesa</b> (Alejandro Guzmán): carga de los recibos de nómina de todas las estaciones.</li><li><b>Recibos Estacion</b>: captura de los recibos del personal de la estación.</li><li><b>Revisión Dirección de Operaciones</b>: validación de la información capturada por la estación.</li></ul><p>Las estaciones <b>Esmegas</b> y <b>Xochimilco</b> no generan la actividad de Recibos Mexdesa, por lo que su evaluación se calcula únicamente con las 2 actividades restantes.</p><h5 class="text-primary"><strong>Visualización:</strong></h5><p>La evaluación se muestra por periodo (<b>Semana</b> o <b>Quincena</b> según la estación), con un resumen por mes (<b>Resumen Mensual</b>) y un resumen de todo el año (<b>Anual</b>).</p>';

    public static function getData(int $idEstacion, int $idYear, int $idMes): array
    {
        $localidad    = RhLocalidad::find($idEstacion);
        $estacion     = $localidad?->localidad ?? 'Estación';
        $esSemanal    = RecibosNominaService::esSemanal($idEstacion);
        $descripcion  = $esSemanal ? 'Semana' : 'Quincena';
        $esAnual      = $idMes === 13;

        $data = [
            'estacion_id'     => $idEstacion,
            'estacion_nombre' => $estacion,
            'year'            => $idYear,
            'mes'             => $idMes,
            'descripcion'     => $descripcion,
            'es_semanal'      => $esSemanal,
            'es_anual'        => $esAnual,
            'colores'         => self::COLORES,
            'info'            => self::$info_evaluacion,
            'periodos'        => [],
            'mensual'         => null,
            'anual'           => null,
        ];

        if ($esAnual) {
            $totalYear = $esSemanal ? RecibosNominaService::getUltimaSemanaYear($idYear) : 24;

            $data['total_periodos'] = $totalYear;
            $data['anual'] = self::grafica(
                $estacion,
                $idEstacion,
                $idYear,
                null,
                $descripcion,
                $totalYear * self::PUNTAJE_POR_ACTIVIDAD
            );

            return $data;
        }

        $periodos = $esSemanal
            ? RecibosNominaService::semanasDelMes($idMes, $idYear)
            : RecibosNominaService::quincenasDelMes($idMes, $idYear);

        foreach ($periodos as $periodo) {
            $periodo = (int)$periodo;
            $rango = $esSemanal
                ? RecibosNominaService::getRangoFechasSemana($idYear, $periodo)
                : RecibosNominaService::getRangoFechasQuincena($idYear, $periodo);

            $data['periodos'][] = [
                'periodo'     => $periodo,
                'descripcion'  => $descripcion,
                'etiqueta'     => $descripcion . ' ' . $periodo,
                'rango'        => formatearFecha($rango['inicio'])
                    . ' al ' . formatearFecha($rango['fin']),
                'grafica'      => self::grafica(
                    $estacion,
                    $idEstacion,
                    $idYear,
                    (int)$rango['mes'],
                    $descripcion,
                    self::PUNTAJE_POR_ACTIVIDAD,
                    $periodo
                ),
            ];
        }

        $data['total_periodos'] = count($periodos);
        $data['mensual'] = self::grafica(
            $estacion,
            $idEstacion,
            $idYear,
            $idMes,
            $descripcion,
            count($periodos) * self::PUNTAJE_POR_ACTIVIDAD
        );

        return $data;
    }

    /**
     * Arma los datos de una gráfica: puntaje total vs. puntaje obtenido por actividad.
     */
    private static function grafica(
        string $estacion,
        int $idEstacion,
        int $idYear,
        ?int $mes,
        string $descripcion,
        int $puntajeTotal,
        ?int $periodo = null
    ): array {
        $categorias = [];
        $total      = [];
        $obtenido   = [];

        if (!in_array($idEstacion, self::ESTACIONES_SIN_MEXDESA, true)) {
            $categorias[] = 'Alejandro Guzmán';
            $total[]      = $puntajeTotal;
            $obtenido[]   = self::puntaje($idEstacion, $idYear, $mes, $descripcion, self::ACTIVIDAD_MEXDESA, $periodo);
        }

        $categorias[] = $estacion;
        $total[]      = $puntajeTotal;
        $obtenido[]   = self::puntaje($idEstacion, $idYear, $mes, $descripcion, self::ACTIVIDAD_ESTACION, $periodo);

        $categorias[] = 'Revisión Dirección de Operaciones';
        $total[]      = $puntajeTotal;
        $obtenido[]   = self::puntaje($idEstacion, $idYear, $mes, $descripcion, self::ACTIVIDAD_OPERATIVO, $periodo);

        return [
            'categorias' => $categorias,
            'total'      => $total,
            'obtenido'   => $obtenido,
        ];
    }

    /**
     * Periodo: puntaje del periodo (último registro). Mensual / anual: suma de los puntos capturados.
     */
    private static function puntaje(
        int $idEstacion,
        int $idYear,
        ?int $mes,
        string $descripcion,
        string $actividad,
        ?int $periodo = null
    ): int {
        $query = OpReciboNominaV2Puntaje::where('id_estacion', $idEstacion)
            ->where('year', $idYear)
            ->where('descripcion', $descripcion)
            ->where('actividad', $actividad);

        if ($periodo !== null) {
            return (int)$query
                ->where('no_semana_quincena', $periodo)
                ->orderBy('id', 'desc')
                ->value('puntaje');
        }

        if ($mes !== null) {
            $query->where('mes', $mes);
        }

        return (int)$query->sum('puntaje');
    }
}

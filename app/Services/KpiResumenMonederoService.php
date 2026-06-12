<?php

namespace App\Services;

use App\Models\Operativo\MonederoDocumento;

class KpiResumenMonederoService
{
    private static array $estaciones = [
        1 => 'Interlomas',
        2 => 'Palo Solo',
        3 => 'San Agustin',
        4 => 'Gasomira',
        5 => 'Valle de Guadalupe',
        6 => 'Esmegas',
        7 => 'Xochimilco',
        14 => 'Bosque Real',
    ];

    private static string $info_evaluacion = '<p>La evaluación de las Facturas de Monederos se lleva a cabo según los 5 monederos base: Edenred, Efectivale, Inburgas, Ultragas y Sodexo. Cada uno de ellos tiene un valor de 3 puntos, y la puntuación total se obtiene mediante la suma, alcanzando un máximo de <b>15 puntos</b>.<br><br>1. Se otorgarán <b>3 puntos</b> si la factura se carga antes del día 20 o en días anteriores del mes.<br>2. Se otorgarán <b>2 puntos</b> si la factura se cargue entre el día 21 y el día 25 del mes.<br>3. Se otorgará <b>1 punto</b> si la factura se carga entre el día 26 y el día 28 del mes.<br>4. No se asignarán puntos si la factura se carga a partir del día 29 del mes en adelante.</p><p>Este proceso implica otorgar una puntuación acumulativa, donde cada factura de monederos contribuye a incrementar el puntaje total. Es importante llevar un registro preciso de cada registro de factura de monederos efectuado en la estación, por lo que se debera de evitar el no subir la documentacion para tener un buen historial.</p><h6>No. de aperturas por estación:</h6><p>La evaluación de la factura de monederos se visualizara de acuerdo a los 12 meses del año (<b>Mensual</b>), y se presentará el resumen general de la evaluación obtenida durante todo el año (<b>Anual</b>).</p><h6>No. de aperturas de todas las estaciones:</h6><p>La evaluación de la factura de monederos se mostrará un resumen general de la evaluación obtenida a lo largo de todo el año (Anual). Esto permitirá destacar las estaciones que están llevando a cabo el proceso de manera efectiva.<br><br><b>Nota: Se considerará una mejor evaluación a aquellas estaciones con un mayor número de cumplimiento (15 puntos al mes y 180 puntos de manera anual).</b></p>';

    public static function getOpciones(): array
    {
        return [
            1 => ['id' => 1, 'titulo' => 'Facturas de Monederos', 'icono' => 'ti ti-wallet'],
        ];
    }

    public static function getTipoData(int $idEstacion, int $idYear): array
    {
        $mensual = self::calcularMensual($idEstacion, $idYear);
        $anual = self::calcularAnual($idYear);

        $mejorEstacion = self::calcularMejorEstacion($anual['obtenido'], 180);

        return [
            'estacion_nombre' => self::$estaciones[$idEstacion] ?? 'Estación',
            'estacion_id' => $idEstacion,
            'year' => $idYear,
            'estaciones' => self::$estaciones,
            'colores' => ['#3366cc', '#dc3912', '#ff9900', '#109618', '#990099', '#0099c6', '#dd4477', '#66aa00'],
            'info' => self::$info_evaluacion,
            'titulo' => 'Facturas de Monederos',
            'puntaje_mensual' => 15,
            'puntaje_anual' => 180,
            'mensual' => $mensual,
            'anual' => $anual,
            'mejor_estacion' => $mejorEstacion,
        ];
    }

    public static function consultaPuntajeMensual(int $idEstacion, int $idYear, int $mes): float
    {
        $rows = MonederoDocumento::join('op_corte_mes', 'op_monedero_documento.id_mes', '=', 'op_corte_mes.id')
            ->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
            ->where('op_corte_year.id_estacion', $idEstacion)
            ->where('op_corte_year.year', $idYear)
            ->where('op_corte_mes.mes', $mes)
            ->groupBy('op_monedero_documento.monedero')
            ->selectRaw('op_monedero_documento.monedero, SUM(op_monedero_documento.puntaje) as suma_puntajes, COUNT(op_monedero_documento.id) as cantidad_registros')
            ->get();

        $puntajeCalculo = 0;
        foreach ($rows as $r) {
            $puntajeCalculo += ($r->cantidad_registros > 0) ? $r->suma_puntajes / $r->cantidad_registros : 0;
        }

        return round($puntajeCalculo, 2);
    }

    public static function consultaFacturasMonederoES(int $idEstacion, int $idYear): float
    {
        $rows = MonederoDocumento::join('op_corte_mes', 'op_monedero_documento.id_mes', '=', 'op_corte_mes.id')
            ->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
            ->where('op_corte_year.id_estacion', $idEstacion)
            ->where('op_corte_year.year', $idYear)
            ->groupBy('op_corte_mes.mes', 'op_monedero_documento.monedero')
            ->selectRaw('op_monedero_documento.monedero, SUM(op_monedero_documento.puntaje) as suma_puntajes, COUNT(op_monedero_documento.id) as cantidad_registros')
            ->get();

        $puntajeCalculo = 0;
        foreach ($rows as $r) {
            $puntajeCalculo += ($r->cantidad_registros > 0) ? $r->suma_puntajes / $r->cantidad_registros : 0;
        }

        return round($puntajeCalculo, 2);
    }

    private static function calcularMensual(int $idEstacion, int $idYear): array
    {
        $categorias = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        $obtenido = array_fill(0, 12, 0);
        $maximo = array_fill(0, 12, 15);

        for ($mes = 1; $mes <= 12; $mes++) {
            $obtenido[$mes - 1] = self::consultaPuntajeMensual($idEstacion, $idYear, $mes);
        }

        $categorias[] = 'Anual';
        $totalAnual = array_sum($obtenido);
        $obtenido[] = round($totalAnual, 2);
        $maximo[] = 180;

        return ['categorias' => $categorias, 'obtenido' => $obtenido, 'maximo' => $maximo];
    }

    private static function calcularAnual(int $idYear): array
    {
        $ids = array_keys(self::$estaciones);
        $nombres = array_values(self::$estaciones);
        $obtenido = array_fill(0, count($ids), 0);
        $maximo = array_fill(0, count($ids), 180);

        foreach ($ids as $idx => $idEstacion) {
            $obtenido[$idx] = self::consultaFacturasMonederoES($idEstacion, $idYear);
        }

        return ['categorias' => $nombres, 'obtenido' => $obtenido, 'maximo' => $maximo];
    }

    private static function calcularMejorEstacion(array $obtenido, int $anualMax = 180): string
    {
        if (empty($obtenido)) return '';
        $nombres = array_values(self::$estaciones);
        $maxVal = max($obtenido);
        $mejores = [];
        foreach ($obtenido as $i => $val) {
            if ($val === $maxVal && isset($nombres[$i])) {
                $mejores[] = $nombres[$i];
            }
        }
        return implode(', ', $mejores) . ' - Puntaje: ' . $maxVal . ' de ' . $anualMax;
    }
}

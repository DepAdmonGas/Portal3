<?php

namespace App\Reports\Kpi;

use App\Models\Operativo\VentasDia;
use Illuminate\Database\Capsule\Manager as DB;
use RuntimeException;

class TesoreriaData
{
    private const ESTACIONES_GENERALES = [
        1,
        2,
        3,
        4,
        5,
        6,
        7,
        14,
    ];


    private const PRODUCTOS = [
        'G SUPER',
        'G PREMIUM',
        'G DIESEL',
        'Jarras',
    ];


    /*
    |--------------------------------------------------------------------------
    | Validar
    |--------------------------------------------------------------------------
    */

    public function validar(
        int $idEstacion,
        int $year,
        int $mes
    ): void {

        if ($idEstacion < 0) {

            throw new RuntimeException(
                'La estación seleccionada no es válida.'
            );
        }


        $yearActual =
            (int) date('Y');


        if (
            $year < 2021
            || $year > $yearActual
        ) {

            throw new RuntimeException(
                'El año seleccionado no es válido.'
            );
        }


        if (
            $mes < 0
            || $mes > 12
        ) {

            throw new RuntimeException(
                'El mes seleccionado no es válido.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Detalle mensual requiere estación
        |--------------------------------------------------------------------------
        */

        if (
            $mes !== 0
            && $idEstacion === 0
        ) {

            throw new RuntimeException(
                'Selecciona una estación para generar el reporte mensual de tesorería.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Estaciones
    |--------------------------------------------------------------------------
    */

    public function estaciones(
        int $idEstacion
    ): array {

        if ($idEstacion === 0) {

            return
                self::ESTACIONES_GENERALES;
        }


        return [
            $idEstacion
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Nombre estación
    |--------------------------------------------------------------------------
    |
    | El legacy de Tesorería utiliza tb_estaciones.razonsocial,
    | no op_rh_localidades.localidad.
    |
    |--------------------------------------------------------------------------
    */

    public function nombreEstacion(
        int $idEstacion
    ): string {

        $estacion =
            DB::table(
                'tb_estaciones'
            )
            ->select([
                'id',
                'razonsocial',
            ])
            ->where(
                'id',
                $idEstacion
            )
            ->first();


        if (!$estacion) {

            throw new RuntimeException(
                'No se encontró la estación seleccionada.'
            );
        }


        return
            (string) $estacion->razonsocial;
    }


    /*
    |--------------------------------------------------------------------------
    | Días
    |--------------------------------------------------------------------------
    */

    public function dias(
        int $idEstacion,
        int $year,
        int $mes
    ) {

        return DB::table(
            'op_corte_year as corte_year'
        )
            ->join(
                'op_corte_mes as corte_mes',
                'corte_mes.id_year',
                '=',
                'corte_year.id'
            )
            ->join(
                'op_corte_dia as corte_dia',
                'corte_dia.id_mes',
                '=',
                'corte_mes.id'
            )
            ->where(
                'corte_year.id_estacion',
                $idEstacion
            )
            ->where(
                'corte_year.year',
                $year
            )
            ->where(
                'corte_mes.mes',
                $mes
            )
            ->select([
                'corte_dia.id',
                'corte_dia.fecha',
                'corte_mes.id as id_mes',
            ])
            ->orderBy(
                'corte_dia.fecha'
            )
            ->get();
    }


    /*
    |--------------------------------------------------------------------------
    | Litros
    |--------------------------------------------------------------------------
    |
    | Todos los productos se obtienen en una consulta.
    |
    |--------------------------------------------------------------------------
    */

    public function litrosPorDia(
        array $idsDia
    ): array {

        if ($idsDia === []) {

            return [];
        }


        $resultados =
            VentasDia::query()

            ->from(
                'op_ventas_dia as venta'
            )

            ->whereIn(
                'venta.idreporte_dia',
                $idsDia
            )

            ->whereIn(
                'venta.producto',
                self::PRODUCTOS
            )

            ->selectRaw(
                '
                    venta.idreporte_dia AS id_dia,

                    venta.producto AS producto,

                    SUM(
                        COALESCE(
                            venta.litros,
                            0
                        )
                    ) AS litros
                    '
            )

            ->groupBy(
                'venta.idreporte_dia',
                'venta.producto'
            )

            ->get();


        $datos =
            [];


        foreach (
            $idsDia
            as $idDia
        ) {

            foreach (
                self::PRODUCTOS
                as $producto
            ) {

                $datos[$idDia][$producto] =
                    0.0;
            }
        }


        foreach (
            $resultados
            as $resultado
        ) {

            $datos[(int) $resultado->id_dia][(string) $resultado->producto] =
                (float) $resultado->litros;
        }


        return
            $datos;
    }


    /*
    |--------------------------------------------------------------------------
    | Crédito diario
    |--------------------------------------------------------------------------
    |
    | Conserva el comportamiento de pdf-tesoreria-estaciones.php.
    |
    |--------------------------------------------------------------------------
    */

    public function creditoPorDia(
        array $idsDia
    ): array {

        if ($idsDia === []) {

            return [];
        }


        $resultados =
            DB::table(
                'op_clientes_controlgas'
            )
            ->whereIn(
                'idreporte_dia',
                $idsDia
            )
            ->where(
                'concepto',
                'CRÉDITO (ANEXO)'
            )
            ->selectRaw(
                '
                    idreporte_dia AS id_dia,
                    SUM(
                        COALESCE(
                            consumo,
                            0
                        )
                    ) AS total
                    '
            )
            ->groupBy(
                'idreporte_dia'
            )
            ->get();


        $creditos =
            [];


        foreach (
            $idsDia
            as $idDia
        ) {

            $creditos[$idDia] =
                0.0;
        }


        foreach (
            $resultados
            as $resultado
        ) {

            $creditos[(int) $resultado->id_dia] =
                (float) $resultado->total;
        }


        return
            $creditos;
    }


    /*
    |--------------------------------------------------------------------------
    | Crédito mensual
    |--------------------------------------------------------------------------
    |
    | Conserva totalCredito() del reporte anual.
    |
    |--------------------------------------------------------------------------
    */

    public function creditoMes(
        int $idMes
    ): float {

        return (float) DB::table(
            'op_consumos_pagos_resumen as resumen'
        )
            ->join(
                'op_cliente as cliente',
                'resumen.id_cliente',
                '=',
                'cliente.id'
            )
            ->where(
                'resumen.id_mes',
                $idMes
            )
            ->where(
                'cliente.tipo',
                'Crédito'
            )
            ->where(
                'cliente.estado',
                1
            )
            ->sum(
                'resumen.consumos'
            );
    }


    public function productos(): array
    {
        return
            self::PRODUCTOS;
    }
}

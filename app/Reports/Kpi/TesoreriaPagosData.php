<?php

namespace App\Reports\Kpi;

use Illuminate\Database\Capsule\Manager as DB;

class TesoreriaPagosData
{


    public function tarjetasCB(
        int $idReporte,
        string $concepto
    ): float {

        $baucher =
            DB::table(
                'op_tarjetas_c_b'
            )
            ->where(
                'idreporte_dia',
                $idReporte
            )
            ->where(
                'concepto',
                $concepto
            )
            ->value(
                'baucher'
            );


        return
            (float) (
                $baucher
                ?? 0
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Prosegur
    |--------------------------------------------------------------------------
    |
    | Equivalente al método legacy:
    |
    | CorteDiarioGeneral::getTotalImporte()
    |
    | Tabla:
    | op_prosegur
    |
    |--------------------------------------------------------------------------
    */

    public function getTotalImporte(
        int $idReporte
    ): float {

        return
            (float) DB::table(
                'op_prosegur'
            )
                ->where(
                    'idreporte_dia',
                    $idReporte
                )
                ->sum(
                    'importe'
                );
    }


    /*
    |--------------------------------------------------------------------------
    | Tarjetas por varios días
    |--------------------------------------------------------------------------
    |
    | Esta función evita realizar una consulta por cada concepto/día.
    | Será útil para optimizar Tesorería posteriormente.
    |
    |--------------------------------------------------------------------------
    */

    public function tarjetasPorDias(
        array $idsDia
    ): array {

        if ($idsDia === []) {

            return [];
        }


        $resultados =
            DB::table(
                'op_tarjetas_c_b'
            )
            ->whereIn(
                'idreporte_dia',
                $idsDia
            )
            ->select([
                'idreporte_dia',
                'concepto',
                'baucher',
            ])
            ->get();


        $datos = [];


        foreach (
            $resultados
            as $resultado
        ) {

            $idDia =
                (int) $resultado->idreporte_dia;


            $concepto =
                (string) $resultado->concepto;


            /*
            |--------------------------------------------------------------------------
            | Preservar comportamiento del legacy
            |--------------------------------------------------------------------------
            |
            | tarjetasCB() hacía:
            |
            | SELECT baucher
            | ...
            | LIMIT 1
            |
            | Por eso conservamos únicamente el primer registro.
            |--------------------------------------------------------------------------
            */

            if (
                !isset(
                    $datos[$idDia][$concepto]
                )
            ) {

                $datos[$idDia][$concepto] =
                    (float) (
                        $resultado->baucher
                        ?? 0
                    );
            }
        }


        return
            $datos;
    }


    /*
    |--------------------------------------------------------------------------
    | Prosegur por varios días
    |--------------------------------------------------------------------------
    */

    public function prosegurPorDias(
        array $idsDia
    ): array {

        if ($idsDia === []) {

            return [];
        }


        $resultados =
            DB::table(
                'op_prosegur'
            )
            ->whereIn(
                'idreporte_dia',
                $idsDia
            )
            ->selectRaw(
                '
                    idreporte_dia,
                    SUM(
                        COALESCE(
                            importe,
                            0
                        )
                    ) AS total
                    '
            )
            ->groupBy(
                'idreporte_dia'
            )
            ->get();


        $datos =
            array_fill_keys(
                $idsDia,
                0.0
            );


        foreach (
            $resultados
            as $resultado
        ) {

            $datos[(int) $resultado->idreporte_dia] =
                (float) $resultado->total;
        }


        return
            $datos;
    }
}

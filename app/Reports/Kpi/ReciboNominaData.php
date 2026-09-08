<?php

namespace App\Reports\Kpi;

use App\Models\Operativo\ReciboNomina;
use App\Models\Operativo\RhLocalidad;
use RuntimeException;

class ReciboNominaData
{
    /*
    |--------------------------------------------------------------------------
    | Estaciones excluidas
    |--------------------------------------------------------------------------
    */

    private const ESTACIONES_EXCLUIDAS = [
        6,
        7,
    ];


    /*
    |--------------------------------------------------------------------------
    | Validar
    |--------------------------------------------------------------------------
    */

    public function validar(
        int $idEstacion,
        int $year
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
            $idEstacion !== 0
            && in_array(
                $idEstacion,
                self::ESTACIONES_EXCLUIDAS,
                true
            )
        ) {

            throw new RuntimeException(
                'La estación seleccionada no está disponible para este reporte.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Nombre reporte
    |--------------------------------------------------------------------------
    */

    public function nombreReporte(
        int $idEstacion
    ): string {

        if ($idEstacion === 0) {

            return 'General';
        }


        $estacion =
            RhLocalidad::query()
            ->select([
                'id',
                'localidad',
            ])
            ->find(
                $idEstacion
            );


        if (!$estacion) {

            throw new RuntimeException(
                'No se encontró la estación seleccionada.'
            );
        }


        return
            (string) $estacion->localidad;
    }


    /*
    |--------------------------------------------------------------------------
    | Resumen
    |--------------------------------------------------------------------------
    |
    | Una sola consulta para PDF general y Excel.
    |
    |--------------------------------------------------------------------------
    */

    public function resumen(
        int $idEstacion,
        int $year
    ) {

        $query =
            ReciboNomina::query()

            ->from(
                'op_recibo_nomina_v2 as nomina'
            )

            ->join(
                'op_rh_localidades as localidad',
                'nomina.id_estacion',
                '=',
                'localidad.id'
            )

            ->where(
                'nomina.year',
                $year
            )

            ->whereNotIn(
                'nomina.id_estacion',
                self::ESTACIONES_EXCLUIDAS
            );


        /*
        |--------------------------------------------------------------------------
        | Estación
        |--------------------------------------------------------------------------
        */

        if ($idEstacion !== 0) {

            $query->where(
                'nomina.id_estacion',
                $idEstacion
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Agregación
        |--------------------------------------------------------------------------
        */

        return $query

            ->selectRaw(
                '
                nomina.id_estacion,

                localidad.localidad AS nombre_estacion,

                localidad.numlista AS numlista,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 1
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_1,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 2
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_2,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 3
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_3,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 4
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_4,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 5
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_5,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 6
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_6,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 7
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_7,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 8
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_8,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 9
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_9,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 10
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_10,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 11
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_11,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.mes = 12
                            AND nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_12,

                COALESCE(
                    SUM(
                        CASE
                            WHEN nomina.importe_total != 1
                            THEN nomina.importe_total
                            ELSE 0
                        END
                    ),
                    0
                ) AS total
                '
            )

            ->groupBy(
                'nomina.id_estacion',
                'localidad.localidad',
                'localidad.numlista'
            )

            ->orderBy(
                'localidad.numlista'
            )

            ->get();
    }


    /*
    |--------------------------------------------------------------------------
    | Resumen mensual estación
    |--------------------------------------------------------------------------
    */

    public function mensual(
        int $idEstacion,
        int $year
    ): array {

        $resultados =
            ReciboNomina::query()

            ->from(
                'op_recibo_nomina_v2 as nomina'
            )

            ->where(
                'nomina.year',
                $year
            )

            ->where(
                'nomina.id_estacion',
                $idEstacion
            )

            ->whereNotIn(
                'nomina.id_estacion',
                self::ESTACIONES_EXCLUIDAS
            )

            /*
                |--------------------------------------------------------------------------
                | Mismo filtro legacy
                |--------------------------------------------------------------------------
                */

            ->where(
                'nomina.importe_total',
                '!=',
                1
            )

            ->selectRaw(
                '
                    nomina.mes AS mes,

                    SUM(
                        COALESCE(
                            nomina.importe_total,
                            0
                        )
                    ) AS total
                    '
            )

            ->groupBy(
                'nomina.mes'
            )

            ->get();


        $mensual =
            array_fill(
                1,
                12,
                0.0
            );


        foreach (
            $resultados
            as $resultado
        ) {

            $mes =
                (int) $resultado->mes;


            if (
                $mes < 1
                || $mes > 12
            ) {

                continue;
            }


            $mensual[$mes] =
                (float) $resultado->total;
        }


        return $mensual;
    }
}

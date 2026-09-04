<?php

namespace App\Reports\Kpi;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\SolicitudVale;
use RuntimeException;

class SolicitudValeData
{
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
    | Resumen general / Excel
    |--------------------------------------------------------------------------
    |
    | Reproduce:
    |
    | IF(id_estacion = 0, cuenta, id_estacion)
    |
    | Pero sin mezclar texto e IDs en una sola columna SQL.
    |
    |--------------------------------------------------------------------------
    */

    public function resumen(
        int $idEstacion,
        int $year
    ) {

        $query =
            SolicitudVale::query()

            ->from(
                'op_solicitud_vale as vale'
            )

            ->where(
                'vale.id_year',
                $year
            )

            ->where(
                'vale.status',
                '!=',
                0
            );


        /*
        |--------------------------------------------------------------------------
        | Filtro estación
        |--------------------------------------------------------------------------
        */

        if ($idEstacion !== 0) {

            $query->where(
                'vale.id_estacion',
                $idEstacion
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Agrupación
        |--------------------------------------------------------------------------
        |
        | Para id_estacion = 0:
        |     agrupamos por cuenta.
        |
        | Para estaciones:
        |     agrupamos únicamente por id_estacion.
        |
        */

        $resultados =
            $query

            ->selectRaw(
                "
                    vale.id_estacion,

                    CASE
                        WHEN vale.id_estacion = 0
                        THEN vale.cuenta
                        ELSE NULL
                    END AS cuenta,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 1
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_1,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 2
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_2,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 3
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_3,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 4
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_4,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 5
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_5,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 6
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_6,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 7
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_7,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 8
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_8,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 9
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_9,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 10
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_10,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 11
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_11,

                    COALESCE(
                        SUM(
                            CASE
                                WHEN vale.id_mes = 12
                                THEN vale.monto
                                ELSE 0
                            END
                        ),
                        0
                    ) AS mes_12,

                    COALESCE(
                        SUM(
                            vale.monto
                        ),
                        0
                    ) AS total
                    "
            )

            ->groupByRaw(
                "
                    vale.id_estacion,
                    CASE
                        WHEN vale.id_estacion = 0
                        THEN vale.cuenta
                        ELSE NULL
                    END
                    "
            )

            ->orderBy(
                'vale.id_estacion'
            )

            ->get();


        /*
        |--------------------------------------------------------------------------
        | Resolver nombres de estaciones
        |--------------------------------------------------------------------------
        */

        $idsEstaciones =
            $resultados
            ->pluck(
                'id_estacion'
            )
            ->map(
                fn($id) =>
                (int) $id
            )
            ->filter(
                fn($id) =>
                $id > 0
            )
            ->unique()
            ->values();


        $estaciones =
            RhLocalidad::query()
            ->select([
                'id',
                'localidad',
            ])
            ->whereIn(
                'id',
                $idsEstaciones->all()
            )
            ->get()
            ->keyBy(
                'id'
            );


        /*
        |--------------------------------------------------------------------------
        | Nombre visible
        |--------------------------------------------------------------------------
        */

        foreach (
            $resultados
            as $resultado
        ) {

            $estacionId =
                (int) $resultado->id_estacion;


            if ($estacionId === 0) {

                $resultado->nombre =
                    (string) (
                        $resultado->cuenta
                        ?: 'S/I'
                    );

                continue;
            }


            $estacion =
                $estaciones->get(
                    $estacionId
                );


            $resultado->nombre =
                $estacion?->localidad
                ?? (
                    'Estación '
                    . $estacionId
                );
        }


        return $resultados;
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
            SolicitudVale::query()

            ->from(
                'op_solicitud_vale as vale'
            )

            ->where(
                'vale.id_year',
                $year
            )

            ->where(
                'vale.id_estacion',
                $idEstacion
            )

            ->where(
                'vale.status',
                '!=',
                0
            )

            ->selectRaw(
                '
                    vale.id_mes AS mes,

                    SUM(
                        COALESCE(
                            vale.monto,
                            0
                        )
                    ) AS total
                    '
            )

            ->groupBy(
                'vale.id_mes'
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

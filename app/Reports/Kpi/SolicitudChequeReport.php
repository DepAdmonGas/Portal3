<?php

namespace App\Reports\Kpi;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\SolicitudCheque;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class SolicitudChequeReport
{
    /*
    |--------------------------------------------------------------------------
    | Generar
    |--------------------------------------------------------------------------
    */

    public function generar(
        int $idEstacion,
        int $year
    ): void {

        $this->validarParametros(
            $idEstacion,
            $year
        );

        $nombreReporte =
            $this->resolverNombreReporte(
                $idEstacion
            );


        /*
        |--------------------------------------------------------------------------
        | General
        |--------------------------------------------------------------------------
        */

        if ($idEstacion === 0) {

            $registros =
                $this->obtenerResumenGeneral(
                    $year
                );

            $html =
                $this->htmlGeneral(
                    $registros,
                    $year
                );

            $this->renderPdf(
                $html,
                'legal',
                'landscape',
                $nombreReporte,
                $year
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Estación
        |--------------------------------------------------------------------------
        */

        $mensual =
            $this->obtenerResumenEstacion(
                $idEstacion,
                $year
            );

        $html =
            $this->htmlEstacion(
                $mensual,
                $nombreReporte,
                $year
            );

        $this->renderPdf(
            $html,
            'A4',
            'portrait',
            $nombreReporte,
            $year
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validación
    |--------------------------------------------------------------------------
    */

    private function validarParametros(
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

    private function resolverNombreReporte(
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
    | Resumen general
    |--------------------------------------------------------------------------
    |
    | Conserva la regla legacy:
    |
    | id_estacion = 8
    |      -> nombre del departamento
    |
    | cualquier otra estación
    |      -> nombre de estación
    |
    |--------------------------------------------------------------------------
    */

    private function obtenerResumenGeneral(
        int $year
    ) {

        return SolicitudCheque::query()

            ->from(
                'op_solicitud_cheque as cheque'
            )

            ->leftJoin(
                'tb_estaciones as estacion',
                'cheque.id_estacion',
                '=',
                'estacion.id'
            )

            ->leftJoin(
                'tb_puestos as puesto',
                function ($join) {

                    $join
                        ->on(
                            'cheque.depto',
                            '=',
                            'puesto.id'
                        )
                        ->where(
                            'cheque.id_estacion',
                            '=',
                            8
                        );
                }
            )

            ->where(
                'cheque.id_year',
                $year
            )

            ->where(
                'cheque.status',
                '!=',
                0
            )

            ->selectRaw(
                "
                cheque.id_estacion,

                CASE
                    WHEN cheque.id_estacion = 8
                        THEN puesto.tipo_puesto
                    ELSE estacion.nombre
                END AS nombre_estacion,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 1
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_1,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 2
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_2,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 3
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_3,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 4
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_4,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 5
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_5,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 6
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_6,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 7
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_7,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 8
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_8,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 9
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_9,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 10
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_10,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 11
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_11,

                COALESCE(
                    SUM(
                        CASE
                            WHEN cheque.id_mes = 12
                            THEN cheque.monto
                            ELSE 0
                        END
                    ),
                    0
                ) AS mes_12,

                COALESCE(
                    SUM(
                        cheque.monto
                    ),
                    0
                ) AS total
                "
            )

            ->groupBy(
                'cheque.id_estacion',
                'nombre_estacion'
            )

            ->orderByRaw(
                '
                CASE
                    WHEN cheque.id_estacion = 8
                    THEN 1
                    ELSE 0
                END,
                cheque.id_estacion ASC
                '
            )

            ->get();
    }


    /*
    |--------------------------------------------------------------------------
    | Resumen estación
    |--------------------------------------------------------------------------
    |
    | Aquí ya no necesitamos 12 SUM(CASE...).
    |
    | Agrupamos directamente por mes.
    |
    |--------------------------------------------------------------------------
    */

    private function obtenerResumenEstacion(
        int $idEstacion,
        int $year
    ): array {

        $resultados =
            SolicitudCheque::query()

            ->from(
                'op_solicitud_cheque as cheque'
            )

            ->where(
                'cheque.id_year',
                $year
            )

            ->where(
                'cheque.id_estacion',
                $idEstacion
            )

            ->where(
                'cheque.status',
                '!=',
                0
            )

            ->selectRaw(
                '
                    cheque.id_mes AS mes,
                    SUM(
                        COALESCE(
                            cheque.monto,
                            0
                        )
                    ) AS total
                    '
            )

            ->groupBy(
                'cheque.id_mes'
            )

            ->get();


        /*
        |--------------------------------------------------------------------------
        | Enero - diciembre
        |--------------------------------------------------------------------------
        */

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


    /*
    |--------------------------------------------------------------------------
    | HTML GENERAL
    |--------------------------------------------------------------------------
    */

    private function htmlGeneral(
        $registros,
        int $year
    ): string {

        $fondo =
            $this->resolverFondo(
                'Fondo1.jpg'
            );

        $styles =
            $this->stylesGeneral(
                $fondo
            );


        $html = <<<HTML
        <!DOCTYPE html>

        <html lang="es">

        <head>

            <meta charset="UTF-8">

            {$styles}

        </head>

        <body>

            <div class="content-wrapper">

                <h1 class="report-title">

                    Reporte Anual (General)

                    <br>

                    Solicitud de Cheques {$year}

                </h1>


                <table class="custom-table">

                    <thead>

                        <tr>

                            <th class="number-column">
                                No.
                            </th>

                            <th class="name-column">
                                Estación<br>o<br>Depto.
                            </th>
        HTML;


        /*
        |--------------------------------------------------------------------------
        | Meses
        |--------------------------------------------------------------------------
        */

        foreach (
            $this->meses()
            as $nombreMes
        ) {

            $html .=
                '<th>'
                . $this->escape(
                    $nombreMes
                )
                . '</th>';
        }


        $html .= '

                            <th>
                                Total
                            </th>

                        </tr>

                    </thead>

                    <tbody>
        ';


        /*
        |--------------------------------------------------------------------------
        | Sin información
        |--------------------------------------------------------------------------
        */

        if ($registros->isEmpty()) {

            $html .= '

                <tr>

                    <td
                        colspan="15"
                        class="empty-row"
                    >
                        No se encontró información
                    </td>

                </tr>
            ';
        } else {

            /*
            |--------------------------------------------------------------------------
            | Totales
            |--------------------------------------------------------------------------
            */

            $totalesMeses =
                array_fill(
                    1,
                    12,
                    0.0
                );

            $totalNeto =
                0.0;

            $numero =
                1;


            /*
            |--------------------------------------------------------------------------
            | Filas
            |--------------------------------------------------------------------------
            */

            foreach (
                $registros
                as $registro
            ) {

                $html .=
                    '<tr>';


                /*
                |--------------------------------------------------------------------------
                | No.
                |--------------------------------------------------------------------------
                */

                $html .=
                    '<th class="number-column">'
                    . $numero
                    . '</th>';


                /*
                |--------------------------------------------------------------------------
                | Estación / depto
                |--------------------------------------------------------------------------
                */

                $html .=
                    '<td class="name-column">'
                    . $this->escape(
                        (string) (
                            $registro->nombre_estacion
                            ?? 'S/I'
                        )
                    )
                    . '</td>';


                /*
                |--------------------------------------------------------------------------
                | Meses
                |--------------------------------------------------------------------------
                */

                for (
                    $mes = 1;
                    $mes <= 12;
                    $mes++
                ) {

                    $campo =
                        'mes_' . $mes;

                    $monto =
                        (float) (
                            $registro->{$campo}
                            ?? 0
                        );


                    $totalesMeses[$mes] +=
                        $monto;


                    $html .=
                        '<td class="amount">'
                        . $this->money(
                            $monto
                        )
                        . '</td>';
                }


                /*
                |--------------------------------------------------------------------------
                | Total
                |--------------------------------------------------------------------------
                */

                $total =
                    (float) $registro->total;


                $totalNeto +=
                    $total;


                $html .=
                    '<td class="amount station-total">'
                    . $this->money(
                        $total
                    )
                    . '</td>';


                $html .=
                    '</tr>';


                $numero++;
            }


            /*
            |--------------------------------------------------------------------------
            | Total Neto
            |--------------------------------------------------------------------------
            */

            $html .= '

                <tr class="total-row">

                    <th colspan="2">
                        Total Neto
                    </th>
            ';


            for (
                $mes = 1;
                $mes <= 12;
                $mes++
            ) {

                $html .=
                    '<td class="amount">'
                    . $this->money(
                        $totalesMeses[$mes]
                    )
                    . '</td>';
            }


            $html .=
                '<td class="amount">'
                . $this->money(
                    $totalNeto
                )
                . '</td>';


            $html .=
                '</tr>';
        }


        /*
        |--------------------------------------------------------------------------
        | Cierre
        |--------------------------------------------------------------------------
        */

        $html .= '

                    </tbody>

                </table>

            </div>

        </body>

        </html>
        ';


        return $html;
    }


    /*
    |--------------------------------------------------------------------------
    | HTML ESTACIÓN
    |--------------------------------------------------------------------------
    */

    private function htmlEstacion(
        array $mensual,
        string $nombreReporte,
        int $year
    ): string {

        $fondo =
            $this->resolverFondo(
                'Fondo2.jpg'
            );

        $styles =
            $this->stylesEstacion(
                $fondo
            );

        $nombre =
            $this->escape(
                $nombreReporte
            );


        $html = <<<HTML
        <!DOCTYPE html>

        <html lang="es">

        <head>

            <meta charset="UTF-8">

            {$styles}

        </head>

        <body>

            <div class="content-wrapper">

                <h1 class="report-title">

                    Reporte Anual ({$nombre})

                    <br>

                    Solicitud de Cheques {$year}

                </h1>


                <table class="custom-table station-table">

                    <thead>

                        <tr>

                            <th>
                                Mes
                            </th>

                            <th>
                                Monto
                            </th>

                        </tr>

                    </thead>

                    <tbody>
        HTML;


        /*
        |--------------------------------------------------------------------------
        | Total
        |--------------------------------------------------------------------------
        */

        $total =
            0.0;


        foreach (
            $this->meses()
            as $mes => $nombreMes
        ) {

            $monto =
                (float) (
                    $mensual[$mes]
                    ?? 0
                );

            $total +=
                $monto;


            $html .= '

                <tr>

                    <th>'
                . $this->escape(
                    $nombreMes
                )
                . '</th>

                    <td class="amount">'
                . $this->money(
                    $monto
                )
                . '</td>

                </tr>
            ';
        }


        /*
        |--------------------------------------------------------------------------
        | Total anual
        |--------------------------------------------------------------------------
        */

        $html .= '

            <tr class="station-total-row">

                <th>
                    Total Anual
                </th>

                <td class="amount">'
            . $this->money(
                $total
            )
            . '</td>

            </tr>

                    </tbody>

                </table>

            </div>

        </body>

        </html>
        ';


        return $html;
    }


    /*
    |--------------------------------------------------------------------------
    | CSS GENERAL
    |--------------------------------------------------------------------------
    */

    private function stylesGeneral(
        string $fondo
    ): string {

        $background =
            $this->backgroundCss(
                $fondo
            );


        return <<<HTML
        <style>

            @page {
                margin: 0;
            }


            html,
            body {

                margin: 0;
                padding: 0;

                width: 100%;
                height: 100%;

                {$background}
            }


            .content-wrapper {

                position: relative;

                z-index: 1;

                width: 95%;

                margin: 0 auto;

                padding-top: 38px;

                box-sizing: border-box;
            }


            .report-title {

                margin: 0 0 18px 0;

                padding: 0;

                font-family: serif;

                font-size: 21px;

                line-height: 1.18;

                font-weight: 700;

                color: #111111;
            }


            .custom-table {

                width: 100%;

                border-collapse: collapse;

                border-spacing: 0;
            }


            /*
            |--------------------------------------------------------------------------
            | Encabezado
            |--------------------------------------------------------------------------
            */

            .custom-table thead th {

                background: #215D98;

                color: #ffffff;

                padding: 6px 2px;

                border: none;

                font-family: serif;

                font-size: 7.6px;

                line-height: 1.05;

                font-weight: 700;

                text-transform: uppercase;

                text-align: center;

                vertical-align: middle;
            }


            /*
            |--------------------------------------------------------------------------
            | Body
            |--------------------------------------------------------------------------
            */

            .custom-table tbody th,
            .custom-table tbody td {

                background: #f2f2f2;

                color: #111111;

                padding: 9px 2px;

                border-bottom: 1px solid #ffffff;

                font-family: serif;

                font-size: 8.2px;

                line-height: 1.1;

                vertical-align: middle;
            }


            .number-column {

                text-align: center;
            }


            .name-column {

                text-align: left;

                white-space: normal;
            }


            .amount {

                text-align: right;

                white-space: nowrap;
            }


            .station-total {

                font-weight: 700;
            }


            /*
            |--------------------------------------------------------------------------
            | Total
            |--------------------------------------------------------------------------
            */

            .total-row th,
            .total-row td {

                background: #749ABF;

                color: #ffffff;

                font-weight: 700;
            }


            .empty-row {

                text-align: center;

                padding: 18px !important;
            }


            /*
            |--------------------------------------------------------------------------
            | Rounded
            |--------------------------------------------------------------------------
            */

            .custom-table thead th:first-child {

                border-top-left-radius: 6px;
            }


            .custom-table thead th:last-child {

                border-top-right-radius: 6px;
            }


            .custom-table tr {

                page-break-inside: avoid;
            }

        </style>
        HTML;
    }


    /*
    |--------------------------------------------------------------------------
    | CSS ESTACIÓN
    |--------------------------------------------------------------------------
    */

    private function stylesEstacion(
        string $fondo
    ): string {

        $background =
            $this->backgroundCss(
                $fondo
            );


        return <<<HTML
        <style>

            @page {
                margin: 0;
            }


            html,
            body {

                margin: 0;
                padding: 0;

                width: 100%;
                height: 100%;

                {$background}
            }


            .content-wrapper {

                position: relative;

                z-index: 1;

                width: 95%;

                margin: 0 auto;

                padding-top: 42px;

                box-sizing: border-box;
            }


            .report-title {

                margin: 0 0 17px 0;

                font-family: serif;

                font-size: 20px;

                line-height: 1.2;

                font-weight: 700;

                color: #111111;
            }


            .station-table {

                width: 100%;

                border-collapse: collapse;

                border-spacing: 0;
            }


            .station-table thead th {

                background: #215D98;

                color: #ffffff;

                padding: 8px 10px;

                border: none;

                font-family: serif;

                font-size: 8px;

                line-height: 1.1;

                font-weight: 700;

                text-align: center;

                text-transform: uppercase;
            }


            .station-table tbody th,
            .station-table tbody td {

                background: #f2f2f2;

                color: #111111;

                padding: 8px 10px;

                border-bottom: 1px solid #ffffff;

                font-family: serif;

                font-size: 9.2px;

                line-height: 1.1;
            }


            .station-table tbody th {

                text-align: center;

                font-weight: 700;
            }


            .amount {

                text-align: right;

                white-space: nowrap;
            }


            .station-total-row th,
            .station-total-row td {

                background: #749ABF;

                color: #ffffff;

                font-weight: 700;
            }


            .station-table thead th:first-child {

                border-top-left-radius: 7px;
            }


            .station-table thead th:last-child {

                border-top-right-radius: 7px;
            }

        </style>
        HTML;
    }


    /*
    |--------------------------------------------------------------------------
    | Fondo
    |--------------------------------------------------------------------------
    */

    private function backgroundCss(
        string $fondo
    ): string {

        if ($fondo === '') {

            return '';
        }


        $fondo =
            $this->escapeCssUrl(
                $fondo
            );


        return "
            background-image: url('{$fondo}');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center;
        ";
    }


    private function resolverFondo(
        string $archivo
    ): string {

        if (
            !defined(
                'RUTA_IMG_LOGOS'
            )
        ) {

            return '';
        }


        return
            rtrim(
                (string) RUTA_IMG_LOGOS,
                '/'
            )
            . '/'
            . ltrim(
                $archivo,
                '/'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    private function renderPdf(
        string $html,
        string $paper,
        string $orientation,
        string $nombreReporte,
        int $year
    ): void {

        $options =
            new Options();


        $options->set(
            'isHtml5ParserEnabled',
            true
        );


        $options->set(
            'isRemoteEnabled',
            true
        );


        $dompdf =
            new Dompdf(
                $options
            );


        $dompdf->loadHtml(
            $html,
            'UTF-8'
        );


        $dompdf->setPaper(
            $paper,
            $orientation
        );


        $dompdf->render();


        $nombreArchivo =
            'Reporte Anual de Solicitud de Cheques '
            . $nombreReporte
            . ' - '
            . $year
            . '.pdf';


        $dompdf->stream(
            $nombreArchivo,
            [
                'Attachment' =>
                true
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Meses
    |--------------------------------------------------------------------------
    */

    private function meses(): array
    {
        return [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Moneda
    |--------------------------------------------------------------------------
    */

    private function money(
        float $value
    ): string {

        return
            '$'
            . number_format(
                $value,
                2,
                '.',
                ','
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Escape
    |--------------------------------------------------------------------------
    */

    private function escape(
        string $value
    ): string {

        return htmlspecialchars(
            $value,
            ENT_QUOTES
                | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }


    private function escapeCssUrl(
        string $value
    ): string {

        return str_replace(
            [
                "'",
                "\n",
                "\r",
            ],
            [
                "\\'",
                '',
                '',
            ],
            $value
        );
    }
}

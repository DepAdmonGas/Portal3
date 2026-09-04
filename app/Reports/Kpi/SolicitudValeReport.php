<?php

namespace App\Reports\Kpi;

use Dompdf\Dompdf;
use Dompdf\Options;

class SolicitudValeReport
{
    private SolicitudValeData $data;


    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $this->data =
            new SolicitudValeData();
    }


    /*
    |--------------------------------------------------------------------------
    | Generar
    |--------------------------------------------------------------------------
    */

    public function generar(
        int $idEstacion,
        int $year
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Validar
        |--------------------------------------------------------------------------
        */

        $this->data->validar(
            $idEstacion,
            $year
        );


        /*
        |--------------------------------------------------------------------------
        | Nombre
        |--------------------------------------------------------------------------
        */

        $nombreReporte =
            $this->data->nombreReporte(
                $idEstacion
            );


        /*
        |--------------------------------------------------------------------------
        | General
        |--------------------------------------------------------------------------
        */

        if ($idEstacion === 0) {

            $registros =
                $this->data->resumen(
                    0,
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
            $this->data->mensual(
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
    | HTML General
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

                <h2 class="report-title">

                    Reporte Anual {$year}

                    <br>

                    Solicitud de Vales

                </h2>


                <table class="custom-table">

                    <thead>

                        <tr>

                            <th class="name-column">
                                Estación
                                <br>
                                o
                                <br>
                                Cuenta
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


        /*
        |--------------------------------------------------------------------------
        | Total
        |--------------------------------------------------------------------------
        */

        $html .= '

                            <th>
                                Total Anual
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
                        colspan="14"
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


            $totalGeneral =
                0.0;


            /*
            |--------------------------------------------------------------------------
            | Registros
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
                | Estación / Cuenta
                |--------------------------------------------------------------------------
                */

                $html .=
                    '<th class="name-column">'
                    . $this->escape(
                        (string) $registro->nombre
                    )
                    . '</th>';


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
                | Total anual
                |--------------------------------------------------------------------------
                */

                $total =
                    (float) $registro->total;


                $totalGeneral +=
                    $total;


                $html .=
                    '<td class="amount row-total">'
                    . $this->money(
                        $total
                    )
                    . '</td>';


                $html .=
                    '</tr>';
            }


            /*
            |--------------------------------------------------------------------------
            | Total Neto
            |--------------------------------------------------------------------------
            */

            $html .= '

                <tr class="total-row">

                    <th>
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
                    $totalGeneral
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
    | HTML Estación
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

                <h2 class="report-title">

                    Reporte Anual ({$nombre}), {$year}

                    <br>

                    Solicitud de Vales

                </h2>


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
        | Total anual
        |--------------------------------------------------------------------------
        */

        $total =
            0.0;


        /*
        |--------------------------------------------------------------------------
        | Meses
        |--------------------------------------------------------------------------
        */

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
        | Total
        |--------------------------------------------------------------------------
        */

        $html .= '

            <tr class="total-row">

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
    | CSS General
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
            | Header
            |--------------------------------------------------------------------------
            */

            .custom-table thead th {

                background: #215D98;

                color: #ffffff;

                padding: 7px 2px;

                border: none;

                font-family: serif;

                font-size: 7.6px;

                line-height: 1.05;

                font-weight: 700;

                text-align: center;

                text-transform: uppercase;

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


            .name-column {

                text-align: left;

                font-weight: 700;

                white-space: normal;
            }


            .amount {

                text-align: right;

                white-space: nowrap;
            }


            .row-total {

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


            /*
            |--------------------------------------------------------------------------
            | Empty
            |--------------------------------------------------------------------------
            */

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
    | CSS estación
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


            /*
            |--------------------------------------------------------------------------
            | Header
            |--------------------------------------------------------------------------
            */

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


            /*
            |--------------------------------------------------------------------------
            | Body
            |--------------------------------------------------------------------------
            */

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


    /*
    |--------------------------------------------------------------------------
    | Render
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
            'Reporte Anual de Solicitud de Vales '
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

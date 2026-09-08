<?php

namespace App\Reports\Kpi;

use App\Models\Operativo\AceiteLubricante;
use App\Models\Operativo\RhLocalidad;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class ResumenAceitesReport
{
    /*
    |--------------------------------------------------------------------------
    | Estaciones del reporte general
    |--------------------------------------------------------------------------
    */

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

        $configuracion =
            $this->resolverConfiguracion(
                $idEstacion
            );

        $estaciones =
            $this->obtenerEstaciones(
                $configuracion['idsEstaciones']
            );

        $importes =
            $this->obtenerImportes(
                $configuracion['idsEstaciones'],
                $year
            );

        $datos = [
            'idEstacion' =>
            $idEstacion,

            'year' =>
            $year,

            'nombreReporte' =>
            $configuracion['nombreReporte'],

            'idsEstaciones' =>
            $configuracion['idsEstaciones'],

            'estaciones' =>
            $estaciones,

            'importes' =>
            $importes,
        ];


        /*
        |--------------------------------------------------------------------------
        | Tipo de reporte
        |--------------------------------------------------------------------------
        */

        if ($idEstacion === 0) {

            $html =
                $this->htmlGeneral(
                    $datos
                );

            $paper =
                'legal';

            $orientation =
                'landscape';
        } else {

            $html =
                $this->htmlEstacion(
                    $datos
                );

            $paper =
                'A4';

            $orientation =
                'portrait';
        }


        $this->renderPdf(
            $html,
            $paper,
            $orientation,
            $configuracion['nombreReporte'],
            $year
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validar parámetros
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
    | Resolver configuración
    |--------------------------------------------------------------------------
    */

    private function resolverConfiguracion(
        int $idEstacion
    ): array {

        if ($idEstacion === 0) {

            return [
                'nombreReporte' =>
                'General',

                'idsEstaciones' =>
                self::ESTACIONES_GENERALES,
            ];
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


        return [
            'nombreReporte' =>
            (string) $estacion->localidad,

            'idsEstaciones' => [
                $idEstacion
            ],
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Obtener estaciones
    |--------------------------------------------------------------------------
    */

    private function obtenerEstaciones(
        array $idsEstaciones
    ) {

        return RhLocalidad::query()
            ->select([
                'id',
                'localidad',
            ])
            ->whereIn(
                'id',
                $idsEstaciones
            )
            ->get()
            ->keyBy(
                'id'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Obtener importes
    |--------------------------------------------------------------------------
    */

    private function obtenerImportes(
        array $idsEstaciones,
        int $year
    ): array {

        $resultados =
            AceiteLubricante::query()

            ->from(
                'op_aceites_lubricantes as aceite'
            )

            ->join(
                'op_corte_dia as dia',
                'dia.id',
                '=',
                'aceite.idreporte_dia'
            )

            ->join(
                'op_corte_mes as mes',
                'mes.id',
                '=',
                'dia.id_mes'
            )

            ->join(
                'op_corte_year as corte_year',
                'corte_year.id',
                '=',
                'mes.id_year'
            )

            /*
                |--------------------------------------------------------------------------
                | Mantener solamente aceites del reporte mensual
                |--------------------------------------------------------------------------
                */

            ->whereExists(
                function ($query) {

                    $query
                        ->selectRaw('1')

                        ->from(
                            'op_aceites_lubricantes_reporte as reporte'
                        )

                        ->whereColumn(
                            'reporte.id_mes',
                            'mes.id'
                        )

                        ->whereColumn(
                            'reporte.id_aceite',
                            'aceite.id_aceite'
                        );
                }
            )

            ->where(
                'corte_year.year',
                $year
            )

            ->whereIn(
                'corte_year.id_estacion',
                $idsEstaciones
            )

            ->selectRaw(
                '
                    corte_year.id_estacion AS id_estacion,
                    mes.mes AS mes,

                    SUM(
                        COALESCE(aceite.cantidad, 0)
                        *
                        COALESCE(aceite.precio_unitario, 0)
                    ) AS total
                    '
            )

            ->groupBy(
                'corte_year.id_estacion',
                'mes.mes'
            )

            ->get();


        /*
        |--------------------------------------------------------------------------
        | Matriz inicial
        |--------------------------------------------------------------------------
        */

        $importes = [];


        foreach ($idsEstaciones as $idEstacion) {

            for (
                $mes = 1;
                $mes <= 12;
                $mes++
            ) {

                $importes[$idEstacion][$mes] = 0.0;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Cargar resultados
        |--------------------------------------------------------------------------
        */

        foreach ($resultados as $resultado) {

            $idEstacion =
                (int) $resultado->id_estacion;

            $mes =
                (int) $resultado->mes;


            if (
                !isset(
                    $importes[$idEstacion][$mes]
                )
            ) {

                continue;
            }


            $importes[$idEstacion][$mes] =
                (float) $resultado->total;
        }


        return $importes;
    }


    /*
    |--------------------------------------------------------------------------
    | HTML GENERAL
    |--------------------------------------------------------------------------
    */

    private function htmlGeneral(
        array $datos
    ): string {

        $year =
            (int) $datos['year'];

        $idsEstaciones =
            $datos['idsEstaciones'];

        $estaciones =
            $datos['estaciones'];

        $importes =
            $datos['importes'];

        $meses =
            $this->meses();


        /*
        |--------------------------------------------------------------------------
        | Fondo
        |--------------------------------------------------------------------------
        */

        $fondo =
            $this->resolverFondo(
                'Fondo1.jpg'
            );


        /*
        |--------------------------------------------------------------------------
        | Estilos
        |--------------------------------------------------------------------------
        */

        $styles =
            $this->stylesGeneral(
                $fondo
            );


        /*
        |--------------------------------------------------------------------------
        | Inicio
        |--------------------------------------------------------------------------
        */

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

                    Reporte Anual {$year}

                    <br>

                    Resumen de Aceites

                </h1>


                <table class="custom-table">

                    <thead>

                        <tr>

                            <th class="station-column">
                                Estación
                            </th>
        HTML;


        /*
        |--------------------------------------------------------------------------
        | Meses
        |--------------------------------------------------------------------------
        */

        foreach ($meses as $nombreMes) {

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

                            <th class="total-column">
                                Total
                            </th>

                        </tr>

                    </thead>

                    <tbody>
        ';


        /*
        |--------------------------------------------------------------------------
        | Totales mensuales
        |--------------------------------------------------------------------------
        */

        $totalesMeses =
            array_fill(
                1,
                12,
                0.0
            );


        /*
        |--------------------------------------------------------------------------
        | Estaciones
        |--------------------------------------------------------------------------
        */

        foreach ($idsEstaciones as $idEstacion) {

            $estacion =
                $estaciones->get(
                    $idEstacion
                );

            $nombreEstacion =
                $estacion?->localidad
                ?? (
                    'Estación '
                    . $idEstacion
                );


            $totalEstacion =
                0.0;


            /*
            |--------------------------------------------------------------------------
            | Inicio fila
            |--------------------------------------------------------------------------
            */

            $html .=
                '<tr>';


            /*
            |--------------------------------------------------------------------------
            | Estación
            |--------------------------------------------------------------------------
            */

            $html .=
                '<th class="station-column">'
                . $this->escape(
                    (string) $nombreEstacion
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

                $importe =
                    (float) (
                        $importes[$idEstacion][$mes]
                        ?? 0
                    );


                $totalEstacion +=
                    $importe;

                $totalesMeses[$mes] +=
                    $importe;


                $html .=
                    '<td class="amount">'
                    . $this->money(
                        $importe
                    )
                    . '</td>';
            }


            /*
            |--------------------------------------------------------------------------
            | Total estación
            |--------------------------------------------------------------------------
            */

            $html .=
                '<td class="amount station-total">'
                . $this->money(
                    $totalEstacion
                )
                . '</td>';


            /*
            |--------------------------------------------------------------------------
            | Cerrar fila
            |--------------------------------------------------------------------------
            */

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

                <th class="station-column">
                    Total Neto
                </th>
        ';


        $totalGeneral =
            0.0;


        /*
        |--------------------------------------------------------------------------
        | Totales meses
        |--------------------------------------------------------------------------
        */

        for (
            $mes = 1;
            $mes <= 12;
            $mes++
        ) {

            $totalMes =
                (float) $totalesMeses[$mes];

            $totalGeneral +=
                $totalMes;


            $html .=
                '<td class="amount">'
                . $this->money(
                    $totalMes
                )
                . '</td>';
        }


        /*
        |--------------------------------------------------------------------------
        | Total general
        |--------------------------------------------------------------------------
        */

        $html .=
            '<td class="amount grand-total">'
            . $this->money(
                $totalGeneral
            )
            . '</td>';


        /*
        |--------------------------------------------------------------------------
        | Cierre
        |--------------------------------------------------------------------------
        */

        $html .= '

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
    | HTML ESTACIÓN
    |--------------------------------------------------------------------------
    */

    private function htmlEstacion(
        array $datos
    ): string {

        $year =
            (int) $datos['year'];

        $idEstacion =
            (int) $datos['idEstacion'];

        $nombreReporte =
            $this->escape(
                (string) $datos['nombreReporte']
            );

        $importes =
            $datos['importes'];

        $meses =
            $this->meses();


        /*
        |--------------------------------------------------------------------------
        | Fondo
        |--------------------------------------------------------------------------
        */

        $fondo =
            $this->resolverFondo(
                'Fondo2.jpg'
            );


        /*
        |--------------------------------------------------------------------------
        | CSS
        |--------------------------------------------------------------------------
        */

        $styles =
            $this->stylesEstacion(
                $fondo
            );


        /*
        |--------------------------------------------------------------------------
        | HTML
        |--------------------------------------------------------------------------
        */

        $html = <<<HTML
        <!DOCTYPE html>

        <html lang="es">

        <head>

            <meta charset="UTF-8">

            {$styles}

        </head>

        <body>

            <div class="content-wrapper">

                <h2 class="station-report-title">

                    Reporte Anual ({$nombreReporte}), {$year}

                    <br>

                    Resumen de Aceites

                </h2>


                <table class="custom-table station-table">

                    <thead>

                        <tr>

                            <th>
                                Mes
                            </th>

                            <th>
                                Monto (Pesos)
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

        $totalAnual =
            0.0;


        /*
        |--------------------------------------------------------------------------
        | Meses
        |--------------------------------------------------------------------------
        */

        foreach (
            $meses as
            $numeroMes => $nombreMes
        ) {

            $importe =
                (float) (
                    $importes[$idEstacion][$numeroMes]
                    ?? 0
                );


            $totalAnual +=
                $importe;


            $html .=
                '<tr>';


            $html .=
                '<th>'
                . $this->escape(
                    $nombreMes
                )
                . '</th>';


            $html .=
                '<td class="amount">'
                . $this->money(
                    $importe
                )
                . '</td>';


            $html .=
                '</tr>';
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
        ';


        $html .=
            '<td class="amount">'
            . $this->money(
                $totalAnual
            )
            . '</td>';


        /*
        |--------------------------------------------------------------------------
        | Cierre
        |--------------------------------------------------------------------------
        */

        $html .= '

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
    | ESTILOS GENERAL
    |--------------------------------------------------------------------------
    |
    | Diseñados para reproducir el reporte anterior.
    |
    */

    private function stylesGeneral(
        string $fondo = ''
    ): string {

        $background =
            $this->backgroundCss(
                $fondo
            );


        return <<<HTML
        <style>

            /*
            |--------------------------------------------------------------------------
            | Página
            |--------------------------------------------------------------------------
            |
            | Dejamos @page sin margen, como el legacy.
            |
            | El margen visual se controla desde content-wrapper.
            |
            */

            @page {
                margin: 0;
            }


            /*
            |--------------------------------------------------------------------------
            | Documento
            |--------------------------------------------------------------------------
            */

            html,
            body {

                margin:
                    0;

                padding:
                    0;

                width:
                    100%;

                height:
                    100%;

                {$background}
            }


            /*
            |--------------------------------------------------------------------------
            | Contenedor
            |--------------------------------------------------------------------------
            |
            | Esta es la clave para que se vea igual al anterior.
            |
            | El PDF original no utilizaba un margen @page.
            | Utilizaba un área interna.
            |
            */

            .content-wrapper {

                position:
                    relative;

                z-index:
                    1;

                width:
                    95%;

                margin:
                    0 auto;

                padding-top:
                    38px;

                box-sizing:
                    border-box;
            }


            /*
            |--------------------------------------------------------------------------
            | Título
            |--------------------------------------------------------------------------
            */

            .report-title {

                margin:
                    0 0 18px 0;

                padding:
                    0;

                font-family:
                    serif;

                font-size:
                    21px;

                line-height:
                    1.18;

                font-weight:
                    700;

                text-align:
                    left;

                color:
                    #111111;
            }


            /*
            |--------------------------------------------------------------------------
            | Tabla
            |--------------------------------------------------------------------------
            |
            | No usamos:
            |
            | table-layout: fixed
            | colgroup
            | porcentajes por columnas
            |
            */

            .custom-table {

                width:
                    100%;

                border-collapse:
                    collapse;

                border-spacing:
                    0;
            }


            /*
            |--------------------------------------------------------------------------
            | Header
            |--------------------------------------------------------------------------
            */

            .custom-table thead {

                background:
                    #215D98;

                color:
                    #ffffff;
            }


            .custom-table thead th {

                background:
                    #215D98;

                color:
                    #ffffff;

                /*
                |--------------------------------------------------------------------------
                | Mismo tamaño visual del anterior
                |--------------------------------------------------------------------------
                */

                padding:
                    7px 3px;

                border:
                    none;

                font-family:
                    serif;

                font-size:
                    9px;

                line-height:
                    1.05;

                font-weight:
                    700;

                text-align:
                    center;

                text-transform:
                    uppercase;

                vertical-align:
                    middle;

                white-space:
                    nowrap;
            }


            /*
            |--------------------------------------------------------------------------
            | Estación header
            |--------------------------------------------------------------------------
            */

            .custom-table thead .station-column {

                text-align:
                    center;
            }


            /*
            |--------------------------------------------------------------------------
            | Body
            |--------------------------------------------------------------------------
            */

            .custom-table tbody {

                background:
                    #f2f2f2;
            }


            .custom-table tbody th,
            .custom-table tbody td {

                background:
                    #f2f2f2;

                color:
                    #111111;

                /*
                |--------------------------------------------------------------------------
                | Filas como el PDF anterior
                |--------------------------------------------------------------------------
                */

                padding:
                    9px 3px;

                border:
                    none;

                font-family:
                    serif;

                font-size:
                    10px;

                line-height:
                    1.1;

                vertical-align:
                    middle;
            }


            /*
            |--------------------------------------------------------------------------
            | Estación
            |--------------------------------------------------------------------------
            */

            .custom-table tbody th.station-column {

                text-align:
                    center;

                font-weight:
                    700;

                white-space:
                    normal;

                padding-left:
                    4px;

                padding-right:
                    4px;
            }


            /*
            |--------------------------------------------------------------------------
            | Montos
            |--------------------------------------------------------------------------
            */

            .amount {

                text-align:
                    right;

                white-space:
                    nowrap;
            }


            /*
            |--------------------------------------------------------------------------
            | Total estación
            |--------------------------------------------------------------------------
            */

            .station-total {

                font-weight:
                    700;
            }


            /*
            |--------------------------------------------------------------------------
            | Separadores internos
            |--------------------------------------------------------------------------
            |
            | El reporte anterior tenía líneas blancas sutiles.
            |
            */

            .custom-table tbody tr {

                border-bottom:
                    1px solid #ffffff;
            }


            /*
            |--------------------------------------------------------------------------
            | Total Neto
            |--------------------------------------------------------------------------
            */

            .total-row th,
            .total-row td {

                background:
                    #749ABF;

                color:
                    #ffffff;

                font-weight:
                    700;
            }


            /*
            |--------------------------------------------------------------------------
            | Gran total
            |--------------------------------------------------------------------------
            */

            .grand-total {

                font-weight:
                    700;
            }


            /*
            |--------------------------------------------------------------------------
            | Bordes del header
            |--------------------------------------------------------------------------
            */

            .custom-table thead tr th:first-child {

                border-top-left-radius:
                    6px;
            }


            .custom-table thead tr th:last-child {

                border-top-right-radius:
                    6px;
            }


            /*
            |--------------------------------------------------------------------------
            | Una sola hoja
            |--------------------------------------------------------------------------
            */

            .custom-table tr {

                page-break-inside:
                    avoid;
            }

        </style>
        HTML;
    }


    /*
    |--------------------------------------------------------------------------
    | ESTILOS ESTACIÓN
    |--------------------------------------------------------------------------
    |
    | Reproduce el diseño anterior A4.
    |
    */

    private function stylesEstacion(
        string $fondo = ''
    ): string {

        $background =
            $this->backgroundCss(
                $fondo
            );


        return <<<HTML
        <style>

            /*
            |--------------------------------------------------------------------------
            | Página
            |--------------------------------------------------------------------------
            */

            @page {
                margin: 0;
            }


            /*
            |--------------------------------------------------------------------------
            | Documento
            |--------------------------------------------------------------------------
            */

            html,
            body {

                margin:
                    0;

                padding:
                    0;

                width:
                    100%;

                height:
                    100%;

                {$background}
            }


            /*
            |--------------------------------------------------------------------------
            | Wrapper
            |--------------------------------------------------------------------------
            |
            | El PDF anterior usa bastante aire a los lados.
            |
            */

            .content-wrapper {

                position:
                    relative;

                z-index:
                    1;

                width:
                    95%;

                margin:
                    0 auto;

                padding-top:
                    42px;

                box-sizing:
                    border-box;
            }


            /*
            |--------------------------------------------------------------------------
            | Título
            |--------------------------------------------------------------------------
            */

            .station-report-title {

                margin:
                    0 0 17px 0;

                font-family:
                    serif;

                font-size:
                    20px;

                line-height:
                    1.2;

                font-weight:
                    700;

                color:
                    #111111;
            }


            /*
            |--------------------------------------------------------------------------
            | Tabla
            |--------------------------------------------------------------------------
            */

            .station-table {

                width:
                    100%;

                border-collapse:
                    collapse;

                border-spacing:
                    0;
            }


            /*
            |--------------------------------------------------------------------------
            | Header
            |--------------------------------------------------------------------------
            */

            .station-table thead {

                background:
                    #215D98;
            }


            .station-table thead th {

                background:
                    #215D98;

                color:
                    #ffffff;

                padding:
                    8px 10px;

                border-right:
                    1px solid rgba(
                        255,
                        255,
                        255,
                        .25
                    );

                font-family:
                    serif;

                font-size:
                    10px;

                line-height:
                    1.1;

                font-weight:
                    700;

                text-align:
                    center;

                text-transform:
                    uppercase;
            }


            /*
            |--------------------------------------------------------------------------
            | Rounded header
            |--------------------------------------------------------------------------
            */

            .station-table thead th:first-child {

                border-top-left-radius:
                    7px;
            }


            .station-table thead th:last-child {

                border-top-right-radius:
                    7px;

                border-right:
                    none;
            }


            /*
            |--------------------------------------------------------------------------
            | Body
            |--------------------------------------------------------------------------
            */

            .station-table tbody {

                background:
                    #f2f2f2;
            }


            .station-table tbody th,
            .station-table tbody td {

                background:
                    #f2f2f2;

                color:
                    #111111;

                padding:
                    8px 10px;

                border-bottom:
                    1px solid #ffffff;

                font-family:
                    serif;

                font-size:
                    11px;

                line-height:
                    1.1;
            }


            /*
            |--------------------------------------------------------------------------
            | Mes
            |--------------------------------------------------------------------------
            */

            .station-table tbody th {

                text-align:
                    center;

                font-weight:
                    700;
            }


            /*
            |--------------------------------------------------------------------------
            | Monto
            |--------------------------------------------------------------------------
            */

            .station-table .amount {

                text-align:
                    right;

                white-space:
                    nowrap;
            }


            /*
            |--------------------------------------------------------------------------
            | Total anual
            |--------------------------------------------------------------------------
            */

            .station-total-row th,
            .station-total-row td {

                background:
                    #f2f2f2;

                color:
                    #111111;

                font-weight:
                    700;
            }


            /*
            |--------------------------------------------------------------------------
            | Evitar corte
            |--------------------------------------------------------------------------
            */

            .station-table tr {

                page-break-inside:
                    avoid;
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


    /*
    |--------------------------------------------------------------------------
    | Resolver fondo
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

        /*
        |--------------------------------------------------------------------------
        | Options
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | NO cambiar fuente global
        |--------------------------------------------------------------------------
        |
        | Dejamos que Dompdf utilice la fuente serif estándar.
        |
        | Es la que visualmente corresponde a los reportes anteriores.
        |
        */


        /*
        |--------------------------------------------------------------------------
        | Dompdf
        |--------------------------------------------------------------------------
        */

        $dompdf =
            new Dompdf(
                $options
            );


        /*
        |--------------------------------------------------------------------------
        | HTML
        |--------------------------------------------------------------------------
        */

        $dompdf->loadHtml(
            $html,
            'UTF-8'
        );


        /*
        |--------------------------------------------------------------------------
        | Papel
        |--------------------------------------------------------------------------
        */

        $dompdf->setPaper(
            $paper,
            $orientation
        );


        /*
        |--------------------------------------------------------------------------
        | Render
        |--------------------------------------------------------------------------
        */

        $dompdf->render();


        /*
        |--------------------------------------------------------------------------
        | Nombre
        |--------------------------------------------------------------------------
        */

        $nombreArchivo =
            'Reporte Anual de Aceites '
            . $nombreReporte
            . ' - '
            . $year
            . '.pdf';


        /*
        |--------------------------------------------------------------------------
        | Stream
        |--------------------------------------------------------------------------
        */

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
    | Escape HTML
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


    /*
    |--------------------------------------------------------------------------
    | Escape CSS
    |--------------------------------------------------------------------------
    */

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

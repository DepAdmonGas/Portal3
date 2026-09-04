<?php

namespace App\Reports\Kpi;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\VentasDia;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

class ConcentradoVentasReport
{
    /*
    |--------------------------------------------------------------------------
    | Estaciones generales
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
    | Productos
    |--------------------------------------------------------------------------
    */

    private const PRODUCTOS = [
        'G SUPER',
        'G PREMIUM',
        'G DIESEL',
    ];


    /*
    |--------------------------------------------------------------------------
    | Generar reporte
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

        $this->validarParametros(
            $idEstacion,
            $year
        );


        /*
        |--------------------------------------------------------------------------
        | Configuración
        |--------------------------------------------------------------------------
        */

        $configuracion =
            $this->resolverConfiguracion(
                $idEstacion
            );


        /*
        |--------------------------------------------------------------------------
        | Estaciones
        |--------------------------------------------------------------------------
        */

        $estaciones =
            $this->obtenerEstaciones(
                $configuracion['idsEstaciones']
            );


        /*
        |--------------------------------------------------------------------------
        | Ventas
        |--------------------------------------------------------------------------
        |
        | Toda la información del reporte se obtiene en una sola consulta.
        |
        */

        $ventas =
            $this->obtenerVentas(
                $configuracion['idsEstaciones'],
                $year
            );


        /*
        |--------------------------------------------------------------------------
        | Datos
        |--------------------------------------------------------------------------
        */

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

            'ventas' =>
            $ventas,
        ];


        /*
        |--------------------------------------------------------------------------
        | HTML
        |--------------------------------------------------------------------------
        */

        if ($idEstacion === 0) {

            $html =
                $this->htmlGeneral(
                    $datos
                );
        } else {

            $html =
                $this->htmlEstacion(
                    $datos
                );
        }


        /*
        |--------------------------------------------------------------------------
        | PDF
        |--------------------------------------------------------------------------
        */

        $this->renderPdf(
            $html,
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

        /*
        |--------------------------------------------------------------------------
        | General
        |--------------------------------------------------------------------------
        */

        if ($idEstacion === 0) {

            return [
                'nombreReporte' =>
                'General',

                'idsEstaciones' =>
                self::ESTACIONES_GENERALES,
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Estación
        |--------------------------------------------------------------------------
        */

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
    | Obtener ventas
    |--------------------------------------------------------------------------
    |
    | Sustituye todas las consultas mysqli del código legacy.
    |
    | Devuelve:
    |
    | $ventas[estacion][mes][producto]['litros']
    | $ventas[estacion][mes][producto]['pesos']
    |
    |--------------------------------------------------------------------------
    */

    private function obtenerVentas(
        array $idsEstaciones,
        int $year
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Consulta única
        |--------------------------------------------------------------------------
        */

        $resultados =
            VentasDia::query()

            ->from(
                'op_ventas_dia as venta'
            )

            ->join(
                'op_corte_dia as dia',
                'dia.id',
                '=',
                'venta.idreporte_dia'
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

            ->where(
                'corte_year.year',
                $year
            )

            ->whereIn(
                'corte_year.id_estacion',
                $idsEstaciones
            )

            ->whereIn(
                'venta.producto',
                self::PRODUCTOS
            )

            ->selectRaw(
                '
                    corte_year.id_estacion AS id_estacion,
                    mes.mes AS mes,
                    venta.producto AS producto,

                    SUM(
                        COALESCE(
                            venta.litros,
                            0
                        )
                    ) AS total_litros,

                    SUM(
                        COALESCE(
                            venta.litros,
                            0
                        )
                        *
                        COALESCE(
                            venta.precio_litro,
                            0
                        )
                    ) AS total_pesos
                    '
            )

            ->groupBy(
                'corte_year.id_estacion',
                'mes.mes',
                'venta.producto'
            )

            ->get();


        /*
        |--------------------------------------------------------------------------
        | Inicializar matriz
        |--------------------------------------------------------------------------
        */

        $ventas =
            [];


        foreach (
            $idsEstaciones
            as $idEstacion
        ) {

            for (
                $mes = 1;
                $mes <= 12;
                $mes++
            ) {

                foreach (
                    self::PRODUCTOS
                    as $producto
                ) {

                    $ventas[$idEstacion][$mes][$producto] = [
                        'litros' =>
                        0.0,

                        'pesos' =>
                        0.0,
                    ];
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Cargar resultados
        |--------------------------------------------------------------------------
        */

        foreach (
            $resultados
            as $resultado
        ) {

            $idEstacion =
                (int) $resultado->id_estacion;

            $mes =
                (int) $resultado->mes;

            $producto =
                (string) $resultado->producto;


            if (
                !isset(
                    $ventas[$idEstacion][$mes][$producto]
                )
            ) {

                continue;
            }


            $ventas[$idEstacion][$mes][$producto] = [
                'litros' =>
                (float) $resultado->total_litros,

                'pesos' =>
                (float) $resultado->total_pesos,
            ];
        }


        return $ventas;
    }


    /*
    |--------------------------------------------------------------------------
    | HTML GENERAL
    |--------------------------------------------------------------------------
    |
    | Conservamos el comportamiento del legacy:
    |
    | G SUPER
    |   página Litros
    |   página Pesos
    |
    | G PREMIUM
    |   página Litros
    |   página Pesos
    |
    | G DIESEL
    |   página Litros
    |   página Pesos
    |
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

        $ventas =
            $datos['ventas'];


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
        | CSS
        |--------------------------------------------------------------------------
        */

        $styles =
            $this->stylesGeneral(
                $fondo
            );


        /*
        |--------------------------------------------------------------------------
        | Documento
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
        HTML;


        /*
        |--------------------------------------------------------------------------
        | Páginas
        |--------------------------------------------------------------------------
        */

        $pagina =
            0;


        foreach (
            self::PRODUCTOS
            as $producto
        ) {

            /*
            |--------------------------------------------------------------------------
            | Litros
            |--------------------------------------------------------------------------
            */

            if ($pagina > 0) {

                $html .=
                    '<div class="page-break"></div>';
            }


            $html .=
                $this->paginaProductoGeneral(
                    $producto,
                    'litros',
                    $idsEstaciones,
                    $estaciones,
                    $ventas,
                    $year
                );


            $pagina++;


            /*
            |--------------------------------------------------------------------------
            | Pesos
            |--------------------------------------------------------------------------
            */

            $html .=
                '<div class="page-break"></div>';


            $html .=
                $this->paginaProductoGeneral(
                    $producto,
                    'pesos',
                    $idsEstaciones,
                    $estaciones,
                    $ventas,
                    $year
                );


            $pagina++;
        }


        /*
        |--------------------------------------------------------------------------
        | Cierre
        |--------------------------------------------------------------------------
        */

        $html .= '

        </body>

        </html>
        ';


        return $html;
    }


    /*
    |--------------------------------------------------------------------------
    | Página producto - general
    |--------------------------------------------------------------------------
    */

    private function paginaProductoGeneral(
        string $producto,
        string $tipo,
        array $idsEstaciones,
        $estaciones,
        array $ventas,
        int $year
    ): string {

        /*
        |--------------------------------------------------------------------------
        | Nombre tipo
        |--------------------------------------------------------------------------
        */

        $tipoTitulo =
            $tipo === 'litros'
            ? 'Litros'
            : 'Pesos';


        /*
        |--------------------------------------------------------------------------
        | Inicio
        |--------------------------------------------------------------------------
        */

        $productoHtml =
            $this->escape(
                $producto
            );


        $html = <<<HTML

        <div class="content-wrapper">

            <h2 class="report-title">

                Reporte Anual {$year}

                <br>

                Concentrado de Ventas ({$tipoTitulo})

                <br>

                Producto: {$productoHtml}

            </h2>


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

        $totalesMes =
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

        foreach (
            $idsEstaciones
            as $idEstacion
        ) {

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
            | Fila
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

                $valor =
                    (float) (
                        $ventas[$idEstacion][$mes][$producto][$tipo]
                        ?? 0
                    );


                $totalEstacion +=
                    $valor;

                $totalesMes[$mes] +=
                    $valor;


                $html .=
                    '<td class="amount">'
                    . $this->formatearValor(
                        $valor,
                        $tipo
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
                . $this->formatearValor(
                    $totalEstacion,
                    $tipo
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

                <th class="station-column">
                    Total Neto
                </th>
        ';


        $totalNeto =
            0.0;


        for (
            $mes = 1;
            $mes <= 12;
            $mes++
        ) {

            $valor =
                (float) $totalesMes[$mes];

            $totalNeto +=
                $valor;


            $html .=
                '<td class="amount">'
                . $this->formatearValor(
                    $valor,
                    $tipo
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
            . $this->formatearValor(
                $totalNeto,
                $tipo
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
        ';


        return $html;
    }


    /*
    |--------------------------------------------------------------------------
    | HTML ESTACIÓN
    |--------------------------------------------------------------------------
    |
    | Conservamos la estructura legacy:
    |
    | Mes
    | SUPER Litros
    | SUPER Pesos
    | PREMIUM Litros
    | PREMIUM Pesos
    | DIESEL Litros
    | DIESEL Pesos
    |
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

        $ventas =
            $datos['ventas'];


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

                <h2 class="report-title">

                    Reporte Anual ({$nombreReporte}), {$year}

                    <br>

                    Concentrado de Ventas

                </h2>


                <table class="custom-table station-table">

                    <thead>

                        <tr>

                            <th class="month-column">
                                Mes
                            </th>

                            <th>
                                G SUPER
                                <br>
                                <small>(Litros)</small>
                            </th>

                            <th>
                                G SUPER
                                <br>
                                <small>(Pesos)</small>
                            </th>

                            <th>
                                G PREMIUM
                                <br>
                                <small>(Litros)</small>
                            </th>

                            <th>
                                G PREMIUM
                                <br>
                                <small>(Pesos)</small>
                            </th>

                            <th>
                                G DIESEL
                                <br>
                                <small>(Litros)</small>
                            </th>

                            <th>
                                G DIESEL
                                <br>
                                <small>(Pesos)</small>
                            </th>

                        </tr>

                    </thead>

                    <tbody>
        HTML;


        /*
        |--------------------------------------------------------------------------
        | Totales producto
        |--------------------------------------------------------------------------
        */

        $totales =
            [];


        foreach (
            self::PRODUCTOS
            as $producto
        ) {

            $totales[$producto] = [
                'litros' =>
                0.0,

                'pesos' =>
                0.0,
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Meses
        |--------------------------------------------------------------------------
        */

        foreach (
            $this->meses()
            as $mes => $nombreMes
        ) {

            $html .=
                '<tr>';


            /*
            |--------------------------------------------------------------------------
            | Mes
            |--------------------------------------------------------------------------
            */

            $html .=
                '<th class="month-column">'
                . $this->escape(
                    $nombreMes
                )
                . '</th>';


            /*
            |--------------------------------------------------------------------------
            | Productos
            |--------------------------------------------------------------------------
            */

            foreach (
                self::PRODUCTOS
                as $producto
            ) {

                $litros =
                    (float) (
                        $ventas[$idEstacion][$mes][$producto]['litros']
                        ?? 0
                    );


                $pesos =
                    (float) (
                        $ventas[$idEstacion][$mes][$producto]['pesos']
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Totales
                |--------------------------------------------------------------------------
                */

                $totales[$producto]['litros'] +=
                    $litros;


                $totales[$producto]['pesos'] +=
                    $pesos;


                /*
                |--------------------------------------------------------------------------
                | Celdas
                |--------------------------------------------------------------------------
                */

                $html .=
                    '<td class="amount">'
                    . $this->number(
                        $litros
                    )
                    . '</td>';


                $html .=
                    '<td class="amount">'
                    . $this->money(
                        $pesos
                    )
                    . '</td>';
            }


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

                <th class="month-column">
                    Total Neto
                </th>
        ';


        foreach (
            self::PRODUCTOS
            as $producto
        ) {

            $html .=
                '<td class="amount">'
                . $this->number(
                    $totales[$producto]['litros']
                )
                . '</td>';


            $html .=
                '<td class="amount">'
                . $this->money(
                    $totales[$producto]['pesos']
                )
                . '</td>';
        }


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
    | Estilos GENERAL
    |--------------------------------------------------------------------------
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


            /*
            |--------------------------------------------------------------------------
            | Contenido
            |--------------------------------------------------------------------------
            */

            .content-wrapper {

                position: relative;

                z-index: 1;

                width: 95%;

                margin: 0 auto;

                padding-top: 38px;

                box-sizing: border-box;
            }


            /*
            |--------------------------------------------------------------------------
            | Título
            |--------------------------------------------------------------------------
            */

            .report-title {

                margin: 0 0 17px 0;

                padding: 0;

                font-family: serif;

                font-size: 19px;

                line-height: 1.18;

                font-weight: 700;

                color: #111111;
            }


            /*
            |--------------------------------------------------------------------------
            | Tabla
            |--------------------------------------------------------------------------
            */

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

                padding: 7px 3px;

                border: none;

                font-family: serif;

                font-size: 7.6px;

                line-height: 1.05;

                font-weight: 700;

                text-align: center;

                text-transform: uppercase;

                white-space: nowrap;

                vertical-align: middle;
            }


            /*
            |--------------------------------------------------------------------------
            | Cuerpo
            |--------------------------------------------------------------------------
            */

            .custom-table tbody th,
            .custom-table tbody td {

                background: #f2f2f2;

                color: #111111;

                padding: 9px 3px;

                border: none;

                font-family: serif;

                font-size: 8.2px;

                line-height: 1.1;

                vertical-align: middle;
            }


            /*
            |--------------------------------------------------------------------------
            | Estación
            |--------------------------------------------------------------------------
            */

            .station-column {

                text-align: left !important;

                font-weight: 700;

                white-space: normal;
            }


            /*
            |--------------------------------------------------------------------------
            | Números
            |--------------------------------------------------------------------------
            */

            .amount {

                text-align: right;

                white-space: nowrap;
            }


            /*
            |--------------------------------------------------------------------------
            | Total estación
            |--------------------------------------------------------------------------
            */

            .station-total {

                font-weight: 700;
            }


            /*
            |--------------------------------------------------------------------------
            | Separadores
            |--------------------------------------------------------------------------
            */

            .custom-table tbody tr {

                border-bottom: 1px solid #ffffff;
            }


            /*
            |--------------------------------------------------------------------------
            | Total Neto
            |--------------------------------------------------------------------------
            */

            .total-row th,
            .total-row td {

                background: #749ABF;

                color: #ffffff;

                font-weight: 700;
            }


            .grand-total {

                font-weight: 700;
            }


            /*
            |--------------------------------------------------------------------------
            | Bordes header
            |--------------------------------------------------------------------------
            */

            .custom-table thead th:first-child {

                border-top-left-radius: 6px;
            }


            .custom-table thead th:last-child {

                border-top-right-radius: 6px;
            }


            /*
            |--------------------------------------------------------------------------
            | Saltos
            |--------------------------------------------------------------------------
            */

            .page-break {

                page-break-before: always;
            }


            .custom-table tr {

                page-break-inside: avoid;
            }

        </style>
        HTML;
    }


    /*
    |--------------------------------------------------------------------------
    | Estilos ESTACIÓN
    |--------------------------------------------------------------------------
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


            /*
            |--------------------------------------------------------------------------
            | Contenido
            |--------------------------------------------------------------------------
            */

            .content-wrapper {

                position: relative;

                z-index: 1;

                width: 95%;

                margin: 0 auto;

                padding-top: 42px;

                box-sizing: border-box;
            }


            /*
            |--------------------------------------------------------------------------
            | Título
            |--------------------------------------------------------------------------
            */

            .report-title {

                margin: 0 0 17px 0;

                font-family: serif;

                font-size: 20px;

                line-height: 1.2;

                font-weight: 700;

                color: #111111;
            }


            /*
            |--------------------------------------------------------------------------
            | Tabla
            |--------------------------------------------------------------------------
            */

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

                padding: 8px 6px;

                border: none;

                font-family: serif;

                font-size: 8px;

                line-height: 1.15;

                font-weight: 700;

                text-align: center;

                text-transform: uppercase;

                vertical-align: middle;
            }


            .custom-table thead th small {

                color: #ffffff;

                font-size: 7px;
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

                padding: 8px 7px;

                border-bottom: 1px solid #ffffff;

                font-family: serif;

                font-size: 9px;

                line-height: 1.1;

                vertical-align: middle;
            }


            /*
            |--------------------------------------------------------------------------
            | Mes
            |--------------------------------------------------------------------------
            */

            .month-column {

                text-align: center;

                font-weight: 700;
            }


            /*
            |--------------------------------------------------------------------------
            | Importes
            |--------------------------------------------------------------------------
            */

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


            /*
            |--------------------------------------------------------------------------
            | Header rounded
            |--------------------------------------------------------------------------
            */

            .custom-table thead th:first-child {

                border-top-left-radius: 7px;
            }


            .custom-table thead th:last-child {

                border-top-right-radius: 7px;
            }


            /*
            |--------------------------------------------------------------------------
            | Evitar cortes
            |--------------------------------------------------------------------------
            */

            .custom-table tr {

                page-break-inside: avoid;
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
    | Render PDF
    |--------------------------------------------------------------------------
    */

    private function renderPdf(
        string $html,
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
        | Legal horizontal
        |--------------------------------------------------------------------------
        */

        $dompdf->setPaper(
            'legal',
            'landscape'
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
            'Reporte Anual de Ventas '
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
    | Formatear
    |--------------------------------------------------------------------------
    */

    private function formatearValor(
        float $valor,
        string $tipo
    ): string {

        return $tipo === 'pesos'
            ? $this->money($valor)
            : $this->number($valor);
    }


    /*
    |--------------------------------------------------------------------------
    | Número
    |--------------------------------------------------------------------------
    */

    private function number(
        float $value
    ): string {

        return number_format(
            $value,
            2,
            '.',
            ','
        );
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

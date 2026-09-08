<?php

namespace App\Reports\Kpi;

use Dompdf\Dompdf;
use Dompdf\Options;

class TesoreriaReport
{
    private const COLORES_PRODUCTOS = [
        'G SUPER' => '#76bd1d',
        'G PREMIUM' => '#e21683',
        'G DIESEL' => '#000000',
        'Jarras' => '#6f42c1',
    ];

    private TesoreriaData $data;
    private TesoreriaPagosData $pagosData;

    public function __construct()
    {
        $this->data =
            new TesoreriaData();


        $this->pagosData =
            new TesoreriaPagosData();
    }


    /*
    |--------------------------------------------------------------------------
    | Generar
    |--------------------------------------------------------------------------
    */

    public function generar(
        int $idEstacion,
        int $year,
        int $mes
    ): void {

        $this->data->validar(
            $idEstacion,
            $year,
            $mes
        );


        /*
        |--------------------------------------------------------------------------
        | Reporte mensual
        |--------------------------------------------------------------------------
        */

        if ($mes !== 0) {

            $this->generarMensual(
                $idEstacion,
                $year,
                $mes
            );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Reporte anual
        |--------------------------------------------------------------------------
        */

        $this->generarAnual(
            $idEstacion,
            $year
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generar mensual
    |--------------------------------------------------------------------------
    */

    private function generarMensual(
        int $idEstacion,
        int $year,
        int $mes
    ): void {

        $nombreEstacion =
            $this->data->nombreEstacion(
                $idEstacion
            );


        $dias =
            $this->data->dias(
                $idEstacion,
                $year,
                $mes
            );


        $idsDia =
            $dias
            ->pluck('id')
            ->map(
                fn($id) =>
                (int) $id
            )
            ->all();


        /*
        |--------------------------------------------------------------------------
        | Datos agrupados
        |--------------------------------------------------------------------------
        */

        $litros =
            $this->data->litrosPorDia(
                $idsDia
            );


        $creditos =
            $this->data->creditoPorDia(
                $idsDia
            );


        /*
        |--------------------------------------------------------------------------
        | Construir filas
        |--------------------------------------------------------------------------
        */

        $filas = [];


        foreach (
            $dias
            as $dia
        ) {

            $idDia =
                (int) $dia->id;


            $pagos =
                $this->pagosDia(
                    $idDia,
                    $idEstacion
                );


            $productos = [];

            $totalLitros =
                0.0;


            foreach (
                $this->data->productos()
                as $producto
            ) {

                $valor =
                    (float) (
                        $litros[$idDia][$producto]
                        ?? 0
                    );


                $productos[$producto] =
                    $valor;


                $totalLitros +=
                    $valor;
            }


            $filas[] = [

                'fecha' =>
                (string) $dia->fecha,

                'productos' =>
                $productos,

                'totalLitros' =>
                $totalLitros,

                'prosegur' =>
                $pagos['prosegur'],

                'bbva' =>
                $pagos['bbva'],

                'amex' =>
                $pagos['amex'],

                'inbursa' =>
                $pagos['inbursa'],

                'monederos' =>
                $pagos['monederos'],

                'credito' =>
                (float) (
                    $creditos[$idDia]
                    ?? 0
                ),

                /*
                |--------------------------------------------------------------------------
                | Igual al legacy:
                |
                | Crédito se muestra en su columna,
                | pero NO se suma al Total MXN.
                |--------------------------------------------------------------------------
                */

                'totalMxn' =>
                $pagos['totalMxn'],
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | HTML
        |--------------------------------------------------------------------------
        */

        $html =
            $this->htmlMensual(
                $nombreEstacion,
                $year,
                $mes,
                $filas
            );


        /*
        |--------------------------------------------------------------------------
        | Nombre
        |--------------------------------------------------------------------------
        */

        $nombreArchivo =
            $this->sanitizarNombreArchivo(
                $nombreEstacion
                    . '_Resumen_'
                    . $year
                    . '_'
                    . $this->nombreMes(
                        $mes
                    )
                    . '.pdf'
            );


        /*
        |--------------------------------------------------------------------------
        | Render
        |--------------------------------------------------------------------------
        */

        $this->render(
            $html,
            'legal',
            'portrait',
            $nombreArchivo
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generar anual
    |--------------------------------------------------------------------------
    */

    private function generarAnual(
        int $idEstacion,
        int $year
    ): void {

        $estaciones =
            $this->data->estaciones(
                $idEstacion
            );


        $paginas = [];


        foreach (
            $estaciones
            as $estacion
        ) {

            $estacion =
                (int) $estacion;


            $nombre =
                $this->data->nombreEstacion(
                    $estacion
                );


            $meses = [];


            /*
            |--------------------------------------------------------------------------
            | Enero - diciembre
            |--------------------------------------------------------------------------
            */

            for (
                $mes = 1;
                $mes <= 12;
                $mes++
            ) {

                $dias =
                    $this->data->dias(
                        $estacion,
                        $year,
                        $mes
                    );


                $idsDia =
                    $dias
                    ->pluck('id')
                    ->map(
                        fn($id) =>
                        (int) $id
                    )
                    ->all();


                /*
                |--------------------------------------------------------------------------
                | Litros
                |--------------------------------------------------------------------------
                */

                $litrosDia =
                    $this->data->litrosPorDia(
                        $idsDia
                    );


                $productos =
                    array_fill_keys(
                        $this->data->productos(),
                        0.0
                    );


                /*
                |--------------------------------------------------------------------------
                | Formas de pago
                |--------------------------------------------------------------------------
                */

                $pagosMes = [

                    'prosegur' =>
                    0.0,

                    'bbva' =>
                    0.0,

                    'amex' =>
                    0.0,

                    'inbursa' =>
                    0.0,

                    'monederos' =>
                    0.0,

                    'totalMxn' =>
                    0.0,
                ];


                /*
                |--------------------------------------------------------------------------
                | Recorrer días
                |--------------------------------------------------------------------------
                */

                foreach (
                    $dias
                    as $dia
                ) {

                    $idDia =
                        (int) $dia->id;


                    /*
                    |--------------------------------------------------------------------------
                    | Productos
                    |--------------------------------------------------------------------------
                    */

                    foreach (
                        $this->data->productos()
                        as $producto
                    ) {

                        $productos[$producto] +=
                            (float) (
                                $litrosDia[$idDia][$producto]
                                ?? 0
                            );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Pagos
                    |--------------------------------------------------------------------------
                    */

                    $pagos =
                        $this->pagosDia(
                            $idDia,
                            $estacion
                        );


                    foreach (
                        array_keys(
                            $pagosMes
                        )
                        as $campo
                    ) {

                        $pagosMes[$campo] +=
                            (float) $pagos[$campo];
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Crédito mensual
                |--------------------------------------------------------------------------
                */

                $primerDia =
                    $dias->first();


                $idMes =
                    $primerDia
                    ? (int) $primerDia->id_mes
                    : 0;


                $credito =
                    $idMes > 0
                    ? $this->data->creditoMes(
                        $idMes
                    )
                    : 0.0;


                /*
                |--------------------------------------------------------------------------
                | Registro del mes
                |--------------------------------------------------------------------------
                */

                $meses[] = [

                    'mes' =>
                    $mes,

                    'productos' =>
                    $productos,

                    'totalLitros' =>
                    array_sum(
                        $productos
                    ),

                    'prosegur' =>
                    $pagosMes['prosegur'],

                    'bbva' =>
                    $pagosMes['bbva'],

                    'amex' =>
                    $pagosMes['amex'],

                    'inbursa' =>
                    $pagosMes['inbursa'],

                    'monederos' =>
                    $pagosMes['monederos'],

                    'credito' =>
                    (float) $credito,

                    'totalMxn' =>
                    $pagosMes['totalMxn'],
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | Página estación
            |--------------------------------------------------------------------------
            */

            $paginas[] = [

                'nombre' =>
                $nombre,

                'meses' =>
                $meses,
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | HTML
        |--------------------------------------------------------------------------
        */

        $html =
            $this->htmlAnual(
                $year,
                $paginas
            );


        /*
        |--------------------------------------------------------------------------
        | Nombre archivo
        |--------------------------------------------------------------------------
        */

        if ($idEstacion === 0) {

            $nombreArchivo =
                "Resumen_{$year}.pdf";
        } else {

            $nombreArchivo =
                'Resumen_'
                . $year
                . '_'
                . $this->data->nombreEstacion(
                    $idEstacion
                )
                . '.pdf';
        }


        /*
        |--------------------------------------------------------------------------
        | PDF
        |--------------------------------------------------------------------------
        */

        $this->render(
            $html,
            'legal',
            'landscape',
            $this->sanitizarNombreArchivo(
                $nombreArchivo
            )
        );
    }


    /*
|--------------------------------------------------------------------------
| Pagos por día
|--------------------------------------------------------------------------
*/

    private function pagosDia(
        int $idDia,
        int $idEstacion
    ): array {

        /*
    |--------------------------------------------------------------------------
    | BBVA
    |--------------------------------------------------------------------------
    */

        $bbva =
            $this->pagosData
            ->tarjetasCB(
                $idDia,
                'BBVA BANCOMER SA'
            );


        /*
    |--------------------------------------------------------------------------
    | AMEX
    |--------------------------------------------------------------------------
    */

        $amex =
            $this->pagosData
            ->tarjetasCB(
                $idDia,
                'AMERICAN EXPRESS'
            );


        /*
    |--------------------------------------------------------------------------
    | INBURGAS
    |--------------------------------------------------------------------------
    |
    | El reporte legacy lo suma en Total MXN,
    | aunque no lo muestra como columna independiente.
    |
    |--------------------------------------------------------------------------
    */

        $inburgas =
            $this->pagosData
            ->tarjetasCB(
                $idDia,
                'INBURGAS'
            );


        /*
    |--------------------------------------------------------------------------
    | INBURSA
    |--------------------------------------------------------------------------
    */

        $inbursa =
            $this->pagosData
            ->tarjetasCB(
                $idDia,
                'INBURSA'
            );


        /*
    |--------------------------------------------------------------------------
    | Prosegur
    |--------------------------------------------------------------------------
    */

        $prosegur =
            $this->pagosData
            ->getTotalImporte(
                $idDia
            );


        /*
    |--------------------------------------------------------------------------
    | Monederos electrónicos
    |--------------------------------------------------------------------------
    */

        $monederos =
            0.0;


        foreach (
            [
                'TICKETCARD',
                'G500 FLETT',
                'EFECTICARD',
                'SODEXO',
                'ULTRAGAS',
                'ENERGEX',
            ]
            as $tarjeta
        ) {

            $monederos +=
                $this->pagosData
                ->tarjetasCB(
                    $idDia,
                    $tarjeta
                );
        }


        /*
    |--------------------------------------------------------------------------
    | SHELL FLEET NAVIGATOR
    |--------------------------------------------------------------------------
    |
    | El código legacy comprobaba:
    |
    | $Session_IDEstacion == 2
    |
    | Aquí usamos correctamente la estación que se está procesando.
    |
    |--------------------------------------------------------------------------
    */

        if ($idEstacion === 2) {

            $monederos +=
                $this->pagosData
                ->tarjetasCB(
                    $idDia,
                    'SHELL FLEET NAVIGATOR'
                );
        }


        /*
    |--------------------------------------------------------------------------
    | Total MXN
    |--------------------------------------------------------------------------
    |
    | Se conserva exactamente la fórmula del legacy.
    |
    | El crédito NO forma parte de este total.
    |--------------------------------------------------------------------------
    */

        $totalMxn =
            $bbva
            + $amex
            + $inburgas
            + $inbursa
            + $prosegur
            + $monederos;


        return [
            'prosegur' =>
            $prosegur,

            'bbva' =>
            $bbva,

            'amex' =>
            $amex,

            'inbursa' =>
            $inbursa,

            'monederos' =>
            $monederos,

            'totalMxn' =>
            $totalMxn,
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | HTML mensual
    |--------------------------------------------------------------------------
    */

    private function htmlMensual(
        string $nombreEstacion,
        int $year,
        int $mes,
        array $filas
    ): string {

        $fondo =
            $this->resolverFondo(
                'Fondo2.jpg'
            );


        $productos =
            $this->data->productos();


        $nombre =
            $this->escape(
                $nombreEstacion
            );


        $nombreMes =
            $this->escape(
                $this->nombreMes(
                    $mes
                )
            );


        $backgroundCss =
            $this->backgroundCss(
                $fondo
            );


        /*
        |--------------------------------------------------------------------------
        | Inicio HTML
        |--------------------------------------------------------------------------
        */

        $html = <<<HTML
        <!DOCTYPE html>

        <html lang="es">

        <head>

            <meta charset="UTF-8">

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

                    {$backgroundCss}
                }


                .content-wrapper {

                    position: relative;

                    z-index: 1;

                    width: 95%;

                    margin: 0 auto;

                    padding: 40px;

                    box-sizing: border-box;
                }


                .report-title {

                    margin: 0 0 16px 0;

                    font-family: serif;

                    font-size: 20px;

                    line-height: 1.18;

                    font-weight: 700;

                    color: #111111;
                }


                .custom-table {

                    width: 100%;

                    border-collapse: collapse;

                    border-spacing: 0;
                }


                .custom-table thead th {

                    background: #215D98;

                    color: #ffffff;

                    padding: 6px 3px;

                    border: none;

                    font-family: serif;

                    font-size: 7.8px;

                    line-height: 1.05;

                    font-weight: 700;

                    text-align: center;

                    text-transform: uppercase;

                    vertical-align: middle;
                }


                .custom-table tbody th,
                .custom-table tbody td {

                    background: #f2f2f2;

                    color: #111111;

                    padding: 7px 3px;

                    border-bottom: 1px solid #ffffff;

                    font-family: serif;

                    font-size: 8.5px;

                    line-height: 1.1;

                    vertical-align: middle;
                }


                .custom-table tbody th {

                    text-align: center;

                    font-weight: 700;
                }


                .number {

                    text-align: center;

                    white-space: nowrap;
                }


                .money {

                    text-align: right;

                    white-space: nowrap;
                }


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

        </head>


        <body>


            <div class="content-wrapper">


                <h2 class="report-title">

                    {$nombre}

                    <br>

                    RESUMEN - {$nombreMes} - {$year}

                    <br>

                    VENTAS Y FORMAS DE PAGO

                </h2>


                <table class="custom-table">

                    <thead>

                        <tr>

                            <th>
                                {$nombreMes}
                            </th>
        HTML;


        /*
        |--------------------------------------------------------------------------
        | Productos
        |--------------------------------------------------------------------------
        */

        foreach (
            $productos
            as $producto
        ) {

            $color =
                self::COLORES_PRODUCTOS[$producto]
                ?? '#215D98';


            $html .=
                '<th style="background:'
                . $color
                . '; color:#ffffff;">'
                . $this->escape(
                    (string) $producto
                )
                . '<br>(Litros)</th>';
        }


        /*
        |--------------------------------------------------------------------------
        | Encabezados restantes
        |--------------------------------------------------------------------------
        */

        $html .= <<<HTML

                            <th>
                                Total<br>(Litros)
                            </th>

                            <th>
                                Efectivo Prosegur
                            </th>

                            <th>
                                TPV<br>BBVA
                            </th>

                            <th>
                                TPV<br>AMEX
                            </th>

                            <th>
                                TPV INBURSA
                            </th>

                            <th>
                                Monedero Electrónicos
                            </th>

                            <th>
                                Clientes Crédito
                            </th>

                            <th>
                                Total<br>MXN
                            </th>

                        </tr>

                    </thead>

                    <tbody>
        HTML;


        /*
        |--------------------------------------------------------------------------
        | Sin información
        |--------------------------------------------------------------------------
        */

        if ($filas === []) {

            $colspan =
                count(
                    $productos
                )
                + 9;


            $html .=
                '<tr>'
                . '<td colspan="'
                . $colspan
                . '" class="empty-row">'
                . 'No se encontró información'
                . '</td>'
                . '</tr>';
        } else {

            /*
            |--------------------------------------------------------------------------
            | Totales
            |--------------------------------------------------------------------------
            */

            $totalesProductos =
                array_fill_keys(
                    $productos,
                    0.0
                );


            $totalLitrosMes =
                0.0;


            $totalProsegur =
                0.0;


            $totalBbva =
                0.0;


            $totalAmex =
                0.0;


            $totalInbursa =
                0.0;


            $totalMonederos =
                0.0;


            $totalCredito =
                0.0;


            $totalMxn =
                0.0;


            /*
            |--------------------------------------------------------------------------
            | Filas por día
            |--------------------------------------------------------------------------
            */

            foreach (
                $filas
                as $fila
            ) {

                $html .=
                    '<tr>';


                /*
                |--------------------------------------------------------------------------
                | Fecha
                |--------------------------------------------------------------------------
                */

                $html .=
                    '<th>'
                    . $this->escape(
                        (string) $fila['fecha']
                    )
                    . '</th>';


                /*
                |--------------------------------------------------------------------------
                | Productos
                |--------------------------------------------------------------------------
                */

                foreach (
                    $productos
                    as $producto
                ) {

                    $litros =
                        (float) (
                            $fila['productos'][$producto]
                            ?? 0
                        );


                    $totalesProductos[$producto] +=
                        $litros;


                    $html .=
                        '<td class="number">'
                        . $this->number(
                            $litros
                        )
                        . '</td>';
                }


                /*
                |--------------------------------------------------------------------------
                | Datos
                |--------------------------------------------------------------------------
                */

                $litrosDia =
                    (float) $fila['totalLitros'];


                $prosegur =
                    (float) $fila['prosegur'];


                $bbva =
                    (float) $fila['bbva'];


                $amex =
                    (float) $fila['amex'];


                $inbursa =
                    (float) $fila['inbursa'];


                $monederos =
                    (float) $fila['monederos'];


                $credito =
                    (float) $fila['credito'];


                $mxn =
                    (float) $fila['totalMxn'];


                /*
                |--------------------------------------------------------------------------
                | Acumular
                |--------------------------------------------------------------------------
                */

                $totalLitrosMes +=
                    $litrosDia;


                $totalProsegur +=
                    $prosegur;


                $totalBbva +=
                    $bbva;


                $totalAmex +=
                    $amex;


                $totalInbursa +=
                    $inbursa;


                $totalMonederos +=
                    $monederos;


                $totalCredito +=
                    $credito;


                $totalMxn +=
                    $mxn;


                /*
                |--------------------------------------------------------------------------
                | Imprimir
                |--------------------------------------------------------------------------
                */

                $html .=
                    '<td class="number" style="font-weight:bold;">'
                    . $this->number(
                        $litrosDia
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $prosegur
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $bbva
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $amex
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $inbursa
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $monederos
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $credito
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $mxn
                    )
                    . '</td>';


                $html .=
                    '</tr>';
            }


            /*
            |--------------------------------------------------------------------------
            | Total
            |--------------------------------------------------------------------------
            */

            $html .=
                '<tr class="total-row">';


            $html .=
                '<th>Total</th>';


            /*
            |--------------------------------------------------------------------------
            | Productos
            |--------------------------------------------------------------------------
            */

            foreach (
                $productos
                as $producto
            ) {

                $html .=
                    '<td class="number">'
                    . $this->number(
                        (float) $totalesProductos[$producto]
                    )
                    . '</td>';
            }


            /*
            |--------------------------------------------------------------------------
            | Totales generales
            |--------------------------------------------------------------------------
            */

            $html .=
                '<td class="number">'
                . $this->number(
                    $totalLitrosMes
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalProsegur
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalBbva
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalAmex
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalInbursa
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalMonederos
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalCredito
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalMxn
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

        $html .= <<<HTML

                    </tbody>

                </table>

            </div>


        </body>

        </html>
        HTML;


        return
            $html;
    }


    /*
    |--------------------------------------------------------------------------
    | HTML anual
    |--------------------------------------------------------------------------
    */

    private function htmlAnual(
        int $year,
        array $paginas
    ): string {

        $fondo =
            $this->resolverFondo(
                'Fondo1.jpg'
            );


        $productos =
            $this->data->productos();


        $backgroundCss =
            $this->backgroundCss(
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

                    {$backgroundCss}
                }


                .content-wrapper {

                    position: relative;

                    z-index: 1;

                    width: 95%;

                    margin: 0 auto;

                    padding: 40px;

                    box-sizing: border-box;
                }


                .page-break {

                    page-break-after: always;
                }


                .report-title {

                    margin: 0 0 18px 0;

                    font-family: serif;

                    font-size: 20px;

                    line-height: 1.18;

                    font-weight: 700;

                    color: #111111;
                }


                .custom-table {

                    width: 100%;

                    border-collapse: collapse;

                    border-spacing: 0;
                }


                .custom-table thead th {

                    background: #215D98;

                    color: #ffffff;

                    padding: 6px 3px;

                    border: none;

                    font-family: serif;

                    font-size: 8px;

                    line-height: 1.05;

                    font-weight: 700;

                    text-align: center;

                    text-transform: uppercase;

                    vertical-align: middle;
                }


                .custom-table tbody th,
                .custom-table tbody td {

                    background: #f2f2f2;

                    color: #111111;

                    padding: 8px 3px;

                    border-bottom: 1px solid #ffffff;

                    font-family: serif;

                    font-size: 8.5px;

                    line-height: 1.1;

                    vertical-align: middle;
                }


                .custom-table tbody th {

                    text-align: center;

                    font-weight: 700;
                }


                .number {

                    text-align: center;

                    white-space: nowrap;
                }


                .money {

                    text-align: right;

                    white-space: nowrap;
                }


                .total-row th,
                .total-row td {

                    background: #749ABF;

                    color: #ffffff;

                    font-weight: 700;
                }


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

        </head>


        <body>
        HTML;


        /*
        |--------------------------------------------------------------------------
        | Número de páginas
        |--------------------------------------------------------------------------
        */

        $totalPaginas =
            count(
                $paginas
            );


        /*
        |--------------------------------------------------------------------------
        | Una página por estación
        |--------------------------------------------------------------------------
        */

        foreach (
            $paginas
            as $indice => $pagina
        ) {

            $nombre =
                $this->escape(
                    (string) $pagina['nombre']
                );


            /*
            |--------------------------------------------------------------------------
            | Encabezado
            |--------------------------------------------------------------------------
            */

            $html .= <<<HTML

            <div class="content-wrapper">


                <h2 class="report-title">

                    {$nombre}

                    <br>

                    RESUMEN {$year}

                    <br>

                    VENTAS Y FORMAS DE PAGO

                </h2>


                <table class="custom-table">

                    <thead>

                        <tr>

                            <th>
                                Mes
                            </th>
            HTML;


            /*
            |--------------------------------------------------------------------------
            | Productos
            |--------------------------------------------------------------------------
            */

            foreach (
                $productos
                as $producto
            ) {

                $color =
                    self::COLORES_PRODUCTOS[$producto]
                    ?? '#215D98';


                $html .=
                    '<th style="background:'
                    . $color
                    . '; color:#ffffff;">'
                    . $this->escape(
                        (string) $producto
                    )
                    . '<br>(Litros)</th>';
            }


            /*
            |--------------------------------------------------------------------------
            | Encabezados restantes
            |--------------------------------------------------------------------------
            */

            $html .= <<<HTML

                            <th>
                                Total<br>(Litros)
                            </th>

                            <th>
                                Venta Efectivo Prosegur
                            </th>

                            <th>
                                TPV BBVA
                            </th>

                            <th>
                                TPV AMEX
                            </th>

                            <th>
                                TPV INBURSA
                            </th>

                            <th>
                                Monedero Electrónicos
                            </th>

                            <th>
                                Clientes Crédito
                            </th>

                            <th>
                                Total MXN
                            </th>

                        </tr>

                    </thead>

                    <tbody>
            HTML;


            /*
            |--------------------------------------------------------------------------
            | Totales
            |--------------------------------------------------------------------------
            */

            $totalesProductos =
                array_fill_keys(
                    $productos,
                    0.0
                );


            $totalLitrosAnual =
                0.0;


            $totalProsegurAnual =
                0.0;


            $totalBbvaAnual =
                0.0;


            $totalAmexAnual =
                0.0;


            $totalInbursaAnual =
                0.0;


            $totalMonederosAnual =
                0.0;


            $totalCreditoAnual =
                0.0;


            $totalMxnAnual =
                0.0;


            /*
            |--------------------------------------------------------------------------
            | Meses
            |--------------------------------------------------------------------------
            */

            foreach (
                $pagina['meses']
                as $registro
            ) {

                $mes =
                    (int) $registro['mes'];


                $html .=
                    '<tr>';


                /*
                |--------------------------------------------------------------------------
                | Mes
                |--------------------------------------------------------------------------
                */

                $html .=
                    '<th>'
                    . $this->escape(
                        $this->nombreMes(
                            $mes
                        )
                    )
                    . '</th>';


                /*
                |--------------------------------------------------------------------------
                | Productos
                |--------------------------------------------------------------------------
                */

                foreach (
                    $productos
                    as $producto
                ) {

                    $litros =
                        (float) (
                            $registro['productos'][$producto]
                            ?? 0
                        );


                    $totalesProductos[$producto] +=
                        $litros;


                    $html .=
                        '<td class="number">'
                        . $this->number(
                            $litros
                        )
                        . '</td>';
                }


                /*
                |--------------------------------------------------------------------------
                | Valores
                |--------------------------------------------------------------------------
                */

                $totalLitros =
                    (float) $registro['totalLitros'];


                $prosegur =
                    (float) $registro['prosegur'];


                $bbva =
                    (float) $registro['bbva'];


                $amex =
                    (float) $registro['amex'];


                $inbursa =
                    (float) $registro['inbursa'];


                $monederos =
                    (float) $registro['monederos'];


                $credito =
                    (float) $registro['credito'];


                $totalMxn =
                    (float) $registro['totalMxn'];


                /*
                |--------------------------------------------------------------------------
                | Acumular
                |--------------------------------------------------------------------------
                */

                $totalLitrosAnual +=
                    $totalLitros;


                $totalProsegurAnual +=
                    $prosegur;


                $totalBbvaAnual +=
                    $bbva;


                $totalAmexAnual +=
                    $amex;


                $totalInbursaAnual +=
                    $inbursa;


                $totalMonederosAnual +=
                    $monederos;


                $totalCreditoAnual +=
                    $credito;


                $totalMxnAnual +=
                    $totalMxn;


                /*
                |--------------------------------------------------------------------------
                | Imprimir
                |--------------------------------------------------------------------------
                */

                $html .=
                    '<td class="number" style="font-weight:bold;">'
                    . $this->number(
                        $totalLitros
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $prosegur
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $bbva
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $amex
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $inbursa
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $monederos
                    )
                    . '</td>';


                $html .=
                    '<td class="money">'
                    . $this->money(
                        $credito
                    )
                    . '</td>';


                $html .=
                    '<td class="money" style="font-weight:bold;">'
                    . $this->money(
                        $totalMxn
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

            $html .=
                '<tr class="total-row">';


            $html .=
                '<th>Total Neto</th>';


            /*
            |--------------------------------------------------------------------------
            | Productos
            |--------------------------------------------------------------------------
            */

            foreach (
                $productos
                as $producto
            ) {

                $html .=
                    '<td class="number">'
                    . $this->number(
                        (float) $totalesProductos[$producto]
                    )
                    . '</td>';
            }


            /*
            |--------------------------------------------------------------------------
            | Totales
            |--------------------------------------------------------------------------
            */

            $html .=
                '<td class="number">'
                . $this->number(
                    $totalLitrosAnual
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalProsegurAnual
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalBbvaAnual
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalAmexAnual
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalInbursaAnual
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalMonederosAnual
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalCreditoAnual
                )
                . '</td>';


            $html .=
                '<td class="money">'
                . $this->money(
                    $totalMxnAnual
                )
                . '</td>';


            $html .=
                '</tr>';


            /*
            |--------------------------------------------------------------------------
            | Cerrar página
            |--------------------------------------------------------------------------
            */

            $html .= <<<HTML

                    </tbody>

                </table>

            </div>
            HTML;


            /*
            |--------------------------------------------------------------------------
            | Salto página
            |--------------------------------------------------------------------------
            */

            if (
                $indice
                < $totalPaginas - 1
            ) {

                $html .=
                    '<div class="page-break"></div>';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Cierre documento
        |--------------------------------------------------------------------------
        */

        $html .= <<<HTML

        </body>

        </html>
        HTML;


        return
            $html;
    }


    /*
    |--------------------------------------------------------------------------
    | Nombre mes
    |--------------------------------------------------------------------------
    */

    private function nombreMes(
        int $mes
    ): string {

        return [
            1 =>
            'Enero',

            2 =>
            'Febrero',

            3 =>
            'Marzo',

            4 =>
            'Abril',

            5 =>
            'Mayo',

            6 =>
            'Junio',

            7 =>
            'Julio',

            8 =>
            'Agosto',

            9 =>
            'Septiembre',

            10 =>
            'Octubre',

            11 =>
            'Noviembre',

            12 =>
            'Diciembre',
        ][$mes]
            ?? '';
    }


    /*
    |--------------------------------------------------------------------------
    | Render PDF
    |--------------------------------------------------------------------------
    */

    private function render(
        string $html,
        string $paper,
        string $orientation,
        string $nombreArchivo
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


        $dompdf->stream(
            $nombreArchivo,
            [
                'Attachment' =>
                true,
            ]
        );
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
            str_replace(
                [
                    "'",
                    "\r",
                    "\n",
                ],
                [
                    "\\'",
                    '',
                    '',
                ],
                $fondo
            );


        return
            "background-image: url('{$fondo}');"
            . 'background-size: cover;'
            . 'background-repeat: no-repeat;'
            . 'background-position: center;';
    }


    /*
    |--------------------------------------------------------------------------
    | Formatos
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


    private function number(
        float $value
    ): string {

        return
            number_format(
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

        return
            htmlspecialchars(
                $value,
                ENT_QUOTES
                    | ENT_SUBSTITUTE,
                'UTF-8'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Nombre archivo
    |--------------------------------------------------------------------------
    */

    private function sanitizarNombreArchivo(
        string $nombre
    ): string {

        return
            str_replace(
                [
                    "\r",
                    "\n",
                    '"',
                    "'",
                ],
                [
                    '',
                    '',
                    '',
                    '',
                ],
                $nombre
            );
    }
}

<?php

namespace App\Reports\Kpi;

use App\Models\Operativo\AceiteLubricante;
use App\Models\Operativo\RhLocalidad;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ResumenAceitesExcelReport
{
    /*
    |--------------------------------------------------------------------------
    | Estaciones reporte general
    |--------------------------------------------------------------------------
    |
    | Mismas estaciones utilizadas por el reporte legacy y PDF.
    |
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
    | Columnas
    |--------------------------------------------------------------------------
    |
    | A  = No.
    | B  = Estación
    | C  = Enero
    | ...
    | N  = Diciembre
    | O  = Total
    |
    | Son 15 columnas.
    |
    */

    private const COLUMNA_INICIAL_MESES = 3;

    private const COLUMNA_FINAL_MESES = 14;

    private const COLUMNA_TOTAL = 15;


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
        | Validación
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
        | Importes
        |--------------------------------------------------------------------------
        */

        $importes =
            $this->obtenerImportes(
                $configuracion['idsEstaciones'],
                $year
            );


        /*
        |--------------------------------------------------------------------------
        | Spreadsheet
        |--------------------------------------------------------------------------
        */

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet->getActiveSheet();


        /*
        |--------------------------------------------------------------------------
        | Propiedades
        |--------------------------------------------------------------------------
        */

        $spreadsheet
            ->getProperties()
            ->setCreator('AdmonGas')
            ->setTitle(
                "Resumen de Aceites {$year}"
            )
            ->setSubject(
                'Reporte Anual de Resumen de Aceites'
            );


        /*
        |--------------------------------------------------------------------------
        | Nombre de hoja
        |--------------------------------------------------------------------------
        */

        $sheet->setTitle(
            'Reporte Anual ' . $year
        );


        /*
        |--------------------------------------------------------------------------
        | Título
        |--------------------------------------------------------------------------
        */

        $sheet->mergeCells(
            'A1:O1'
        );

        $sheet->setCellValue(
            'A1',
            "Reporte Anual {$year}"
        );


        /*
        |--------------------------------------------------------------------------
        | Subtítulo
        |--------------------------------------------------------------------------
        */

        $sheet->mergeCells(
            'A2:O2'
        );

        $sheet->setCellValue(
            'A2',
            'Resumen de Aceites'
        );


        /*
        |--------------------------------------------------------------------------
        | Estación
        |--------------------------------------------------------------------------
        */

        $sheet->mergeCells(
            'A3:O3'
        );

        $sheet->setCellValue(
            'A3',
            $idEstacion === 0
                ? 'Todas las estaciones'
                : $configuracion['nombreReporte']
        );


        /*
        |--------------------------------------------------------------------------
        | Encabezados
        |--------------------------------------------------------------------------
        */

        $filaEncabezado =
            5;

        $encabezados = [
            'No.',
            'Estación',
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
            'Total',
        ];


        $sheet->fromArray(
            $encabezados,
            null,
            "A{$filaEncabezado}"
        );


        /*
        |--------------------------------------------------------------------------
        | Totales mensuales
        |--------------------------------------------------------------------------
        */

        $totalesMensuales =
            array_fill(
                1,
                12,
                0.0
            );


        /*
        |--------------------------------------------------------------------------
        | Filas de estaciones
        |--------------------------------------------------------------------------
        */

        $filaActual =
            $filaEncabezado + 1;

        $numero =
            1;


        foreach (
            $configuracion['idsEstaciones']
            as $estacionId
        ) {

            /*
            |--------------------------------------------------------------------------
            | Estación
            |--------------------------------------------------------------------------
            */

            $estacion =
                $estaciones->get(
                    $estacionId
                );

            $nombreEstacion =
                $estacion?->localidad
                ?? (
                    'Estación '
                    . $estacionId
                );


            /*
            |--------------------------------------------------------------------------
            | Número
            |--------------------------------------------------------------------------
            */

            $sheet->setCellValue(
                "A{$filaActual}",
                $numero
            );


            /*
            |--------------------------------------------------------------------------
            | Nombre
            |--------------------------------------------------------------------------
            */

            $sheet->setCellValue(
                "B{$filaActual}",
                $nombreEstacion
            );


            /*
            |--------------------------------------------------------------------------
            | Total estación
            |--------------------------------------------------------------------------
            */

            $totalEstacion =
                0.0;


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
                        $importes[$estacionId][$mes]
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Acumulados
                |--------------------------------------------------------------------------
                */

                $totalEstacion +=
                    $importe;

                $totalesMensuales[$mes] +=
                    $importe;


                /*
                |--------------------------------------------------------------------------
                | Columna
                |--------------------------------------------------------------------------
                */

                $numeroColumna =
                    self::COLUMNA_INICIAL_MESES
                    + ($mes - 1);

                $columna =
                    Coordinate::stringFromColumnIndex(
                        $numeroColumna
                    );


                /*
                |--------------------------------------------------------------------------
                | Valor numérico real
                |--------------------------------------------------------------------------
                |
                | No enviamos "$25,000.00" como texto.
                |
                | Excel recibe 25000 y el formato de celda agrega el símbolo.
                |
                */

                $sheet->setCellValue(
                    "{$columna}{$filaActual}",
                    $importe
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Total estación
            |--------------------------------------------------------------------------
            */

            $columnaTotal =
                Coordinate::stringFromColumnIndex(
                    self::COLUMNA_TOTAL
                );

            $sheet->setCellValue(
                "{$columnaTotal}{$filaActual}",
                $totalEstacion
            );


            /*
            |--------------------------------------------------------------------------
            | Siguiente
            |--------------------------------------------------------------------------
            */

            $numero++;

            $filaActual++;
        }


        /*
        |--------------------------------------------------------------------------
        | Total Neto
        |--------------------------------------------------------------------------
        |
        | Solo para reporte general.
        |
        */

        $filaTotalNeto =
            null;


        if ($idEstacion === 0) {

            $filaTotalNeto =
                $filaActual;


            /*
            |--------------------------------------------------------------------------
            | B = Total Neto
            |--------------------------------------------------------------------------
            */

            $sheet->setCellValue(
                "B{$filaTotalNeto}",
                'Total Neto'
            );


            /*
            |--------------------------------------------------------------------------
            | Totales mensuales
            |--------------------------------------------------------------------------
            */

            for (
                $mes = 1;
                $mes <= 12;
                $mes++
            ) {

                $numeroColumna =
                    self::COLUMNA_INICIAL_MESES
                    + ($mes - 1);

                $columna =
                    Coordinate::stringFromColumnIndex(
                        $numeroColumna
                    );

                $sheet->setCellValue(
                    "{$columna}{$filaTotalNeto}",
                    $totalesMensuales[$mes]
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Total general
            |--------------------------------------------------------------------------
            */

            $columnaTotal =
                Coordinate::stringFromColumnIndex(
                    self::COLUMNA_TOTAL
                );

            $sheet->setCellValue(
                "{$columnaTotal}{$filaTotalNeto}",
                array_sum(
                    $totalesMensuales
                )
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Diseño
        |--------------------------------------------------------------------------
        */

        $this->aplicarEstilos(
            $spreadsheet,
            $filaEncabezado,
            $filaActual - 1,
            $filaTotalNeto
        );


        /*
        |--------------------------------------------------------------------------
        | Descargar
        |--------------------------------------------------------------------------
        */

        $this->descargar(
            $spreadsheet,
            $configuracion['nombreReporte'],
            $year
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validar
    |--------------------------------------------------------------------------
    */

    private function validarParametros(
        int $idEstacion,
        int $year
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Estación
        |--------------------------------------------------------------------------
        */

        if ($idEstacion < 0) {

            throw new RuntimeException(
                'La estación seleccionada no es válida.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Año
        |--------------------------------------------------------------------------
        */

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
    | Obtener importes
    |--------------------------------------------------------------------------
    */

    private function obtenerImportes(
        array $idsEstaciones,
        int $year
    ): array {

        /*
        |--------------------------------------------------------------------------
        | Una consulta para todo el reporte
        |--------------------------------------------------------------------------
        */

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
                | Solo aceites incluidos en reporte mensual
                |--------------------------------------------------------------------------
                */

            ->whereExists(
                function ($query) {

                    $query
                        ->selectRaw(
                            '1'
                        )

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

            /*
                |--------------------------------------------------------------------------
                | Año
                |--------------------------------------------------------------------------
                */

            ->where(
                'corte_year.year',
                $year
            )

            /*
                |--------------------------------------------------------------------------
                | Estaciones
                |--------------------------------------------------------------------------
                */

            ->whereIn(
                'corte_year.id_estacion',
                $idsEstaciones
            )

            /*
                |--------------------------------------------------------------------------
                | Agrupar
                |--------------------------------------------------------------------------
                */

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
        | Matriz vacía
        |--------------------------------------------------------------------------
        */

        $importes =
            [];


        foreach ($idsEstaciones as $idEstacion) {

            for (
                $mes = 1;
                $mes <= 12;
                $mes++
            ) {

                $importes[$idEstacion][$mes] =
                    0.0;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Resultados
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
    | Estilos Excel
    |--------------------------------------------------------------------------
    */

    private function aplicarEstilos(
        Spreadsheet $spreadsheet,
        int $filaEncabezado,
        int $ultimaFilaDatos,
        ?int $filaTotalNeto
    ): void {

        $sheet =
            $spreadsheet->getActiveSheet();


        /*
        |--------------------------------------------------------------------------
        | Título
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getStyle('A1')
            ->getFont()
            ->setBold(true)
            ->setSize(18);


        /*
        |--------------------------------------------------------------------------
        | Subtítulo
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getStyle('A2')
            ->getFont()
            ->setBold(true)
            ->setSize(13);


        /*
        |--------------------------------------------------------------------------
        | Contexto estación
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getStyle('A3')
            ->getFont()
            ->setSize(10)
            ->getColor()
            ->setRGB(
                '666666'
            );


        /*
        |--------------------------------------------------------------------------
        | Encabezado
        |--------------------------------------------------------------------------
        */

        $rangoEncabezado =
            "A{$filaEncabezado}:O{$filaEncabezado}";


        $sheet
            ->getStyle(
                $rangoEncabezado
            )
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setRGB(
                'FFFFFF'
            );


        $sheet
            ->getStyle(
                $rangoEncabezado
            )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setRGB(
                '215D98'
            );


        $sheet
            ->getStyle(
                $rangoEncabezado
            )
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_CENTER
            )
            ->setVertical(
                Alignment::VERTICAL_CENTER
            );


        /*
        |--------------------------------------------------------------------------
        | Alto encabezado
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getRowDimension(
                $filaEncabezado
            )
            ->setRowHeight(
                24
            );


        /*
        |--------------------------------------------------------------------------
        | Datos
        |--------------------------------------------------------------------------
        */

        if (
            $ultimaFilaDatos
            >= $filaEncabezado + 1
        ) {

            $rangoDatos =
                'A'
                . ($filaEncabezado + 1)
                . ':O'
                . $ultimaFilaDatos;


            /*
            |--------------------------------------------------------------------------
            | Bordes suaves
            |--------------------------------------------------------------------------
            */

            $sheet
                ->getStyle(
                    $rangoDatos
                )
                ->getBorders()
                ->getBottom()
                ->setBorderStyle(
                    Border::BORDER_HAIR
                )
                ->getColor()
                ->setRGB(
                    'D9D9D9'
                );


            /*
            |--------------------------------------------------------------------------
            | Vertical
            |--------------------------------------------------------------------------
            */

            $sheet
                ->getStyle(
                    $rangoDatos
                )
                ->getAlignment()
                ->setVertical(
                    Alignment::VERTICAL_CENTER
                );


            /*
            |--------------------------------------------------------------------------
            | Números derecha
            |--------------------------------------------------------------------------
            */

            $sheet
                ->getStyle(
                    'C'
                        . ($filaEncabezado + 1)
                        . ':O'
                        . $ultimaFilaDatos
                )
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_RIGHT
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Formato monetario
        |--------------------------------------------------------------------------
        |
        | Se usa formato MXN genérico.
        |
        | Excel recibe números reales.
        |
        */

        $ultimaFilaFormato =
            $filaTotalNeto
            ?? $ultimaFilaDatos;


        if (
            $ultimaFilaFormato
            >= $filaEncabezado + 1
        ) {

            $sheet
                ->getStyle(
                    'C'
                        . ($filaEncabezado + 1)
                        . ':O'
                        . $ultimaFilaFormato
                )
                ->getNumberFormat()
                ->setFormatCode(
                    '$#,##0.00'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Totales de estación
        |--------------------------------------------------------------------------
        */

        if (
            $ultimaFilaDatos
            >= $filaEncabezado + 1
        ) {

            $sheet
                ->getStyle(
                    'O'
                        . ($filaEncabezado + 1)
                        . ':O'
                        . $ultimaFilaDatos
                )
                ->getFont()
                ->setBold(
                    true
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Total Neto
        |--------------------------------------------------------------------------
        */

        if ($filaTotalNeto !== null) {

            $sheet
                ->getStyle(
                    "B{$filaTotalNeto}:O{$filaTotalNeto}"
                )
                ->getFont()
                ->setBold(
                    true
                );


            $sheet
                ->getStyle(
                    "B{$filaTotalNeto}:O{$filaTotalNeto}"
                )
                ->getFill()
                ->setFillType(
                    Fill::FILL_SOLID
                )
                ->getStartColor()
                ->setRGB(
                    'D9E7F3'
                );


            /*
            |--------------------------------------------------------------------------
            | Borde superior
            |--------------------------------------------------------------------------
            */

            $sheet
                ->getStyle(
                    "B{$filaTotalNeto}:O{$filaTotalNeto}"
                )
                ->getBorders()
                ->getTop()
                ->setBorderStyle(
                    Border::BORDER_THIN
                )
                ->getColor()
                ->setRGB(
                    '215D98'
                );


            /*
            |--------------------------------------------------------------------------
            | Total general
            |--------------------------------------------------------------------------
            */

            $sheet
                ->getStyle(
                    "O{$filaTotalNeto}"
                )
                ->getFont()
                ->setBold(
                    true
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Anchos
        |--------------------------------------------------------------------------
        |
        | Evitamos AutoSize para todas las columnas.
        |
        | En reportes con números grandes puede crear columnas enormes.
        |
        */

        $sheet
            ->getColumnDimension('A')
            ->setWidth(
                7
            );

        $sheet
            ->getColumnDimension('B')
            ->setWidth(
                24
            );


        /*
        |--------------------------------------------------------------------------
        | Meses
        |--------------------------------------------------------------------------
        */

        foreach (
            range('C', 'N')
            as $columna
        ) {

            $sheet
                ->getColumnDimension(
                    $columna
                )
                ->setWidth(
                    14
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Total
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getColumnDimension('O')
            ->setWidth(
                16
            );


        /*
        |--------------------------------------------------------------------------
        | Filas
        |--------------------------------------------------------------------------
        */

        for (
            $fila =
                $filaEncabezado + 1;

            $fila <=
                ($filaTotalNeto ?? $ultimaFilaDatos);

            $fila++
        ) {

            $sheet
                ->getRowDimension(
                    $fila
                )
                ->setRowHeight(
                    21
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Congelar encabezado
        |--------------------------------------------------------------------------
        */

        $sheet->freezePane(
            'C6'
        );


        /*
        |--------------------------------------------------------------------------
        | Autofiltro
        |--------------------------------------------------------------------------
        |
        | Solo sobre encabezados y estaciones.
        |
        */

        if (
            $ultimaFilaDatos
            >= $filaEncabezado
        ) {

            $sheet->setAutoFilter(
                "A{$filaEncabezado}:O{$ultimaFilaDatos}"
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Impresión
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getPageSetup()
            ->setOrientation(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
            );


        $sheet
            ->getPageSetup()
            ->setPaperSize(
                \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LEGAL
            );


        /*
        |--------------------------------------------------------------------------
        | Ajustar a una página de ancho
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getPageSetup()
            ->setFitToWidth(
                1
            );

        $sheet
            ->getPageSetup()
            ->setFitToHeight(
                0
            );


        /*
        |--------------------------------------------------------------------------
        | Márgenes impresión
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getPageMargins()
            ->setTop(
                0.5
            );

        $sheet
            ->getPageMargins()
            ->setBottom(
                0.5
            );

        $sheet
            ->getPageMargins()
            ->setLeft(
                0.3
            );

        $sheet
            ->getPageMargins()
            ->setRight(
                0.3
            );


        /*
        |--------------------------------------------------------------------------
        | Repetir encabezado al imprimir
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getPageSetup()
            ->setRowsToRepeatAtTopByStartAndEnd(
                $filaEncabezado,
                $filaEncabezado
            );


        /*
        |--------------------------------------------------------------------------
        | Área impresión
        |--------------------------------------------------------------------------
        */

        $ultimaFilaImpresion =
            $filaTotalNeto
            ?? $ultimaFilaDatos;

        $sheet
            ->getPageSetup()
            ->setPrintArea(
                "A1:O{$ultimaFilaImpresion}"
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Descargar
    |--------------------------------------------------------------------------
    */

    private function descargar(
        Spreadsheet $spreadsheet,
        string $nombreReporte,
        int $year
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Nombre seguro
        |--------------------------------------------------------------------------
        */

        $nombreArchivo =
            'Reporte Anual de Resumen de Aceites '
            . $nombreReporte
            . ' - '
            . $year
            . '.xlsx';


        /*
        |--------------------------------------------------------------------------
        | Evitar caracteres problemáticos en Content-Disposition
        |--------------------------------------------------------------------------
        */

        $nombreArchivo =
            str_replace(
                [
                    '"',
                    "\r",
                    "\n",
                ],
                '',
                $nombreArchivo
            );


        /*
        |--------------------------------------------------------------------------
        | Evitar corrupción del XLSX
        |--------------------------------------------------------------------------
        |
        | Un espacio, warning o HTML previo puede romper el ZIP interno
        | del archivo Excel.
        |
        */

        while (
            ob_get_level() > 0
        ) {

            ob_end_clean();
        }


        /*
        |--------------------------------------------------------------------------
        | Headers
        |--------------------------------------------------------------------------
        */

        if (headers_sent()) {

            throw new RuntimeException(
                'No fue posible generar el Excel porque la respuesta HTTP ya fue enviada.'
            );
        }


        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        header(
            'Content-Disposition: attachment; filename="'
                . $nombreArchivo
                . '"'
        );

        header(
            'Cache-Control: max-age=0'
        );

        header(
            'Cache-Control: max-age=1'
        );

        header(
            'Expires: Mon, 26 Jul 1997 05:00:00 GMT'
        );

        header(
            'Last-Modified: '
                . gmdate(
                    'D, d M Y H:i:s'
                )
                . ' GMT'
        );

        header(
            'Cache-Control: cache, must-revalidate'
        );

        header(
            'Pragma: public'
        );


        /*
        |--------------------------------------------------------------------------
        | Writer
        |--------------------------------------------------------------------------
        */

        $writer =
            new Xlsx(
                $spreadsheet
            );


        /*
        |--------------------------------------------------------------------------
        | Output
        |--------------------------------------------------------------------------
        */

        $writer->save(
            'php://output'
        );


        /*
        |--------------------------------------------------------------------------
        | Liberar memoria
        |--------------------------------------------------------------------------
        */

        $spreadsheet
            ->disconnectWorksheets();
    }
}

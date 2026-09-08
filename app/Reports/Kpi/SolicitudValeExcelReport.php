<?php

namespace App\Reports\Kpi;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class SolicitudValeExcelReport
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
        | Registros
        |--------------------------------------------------------------------------
        */

        $registros =
            $this->data->resumen(
                $idEstacion,
                $year
            );


        /*
        |--------------------------------------------------------------------------
        | Spreadsheet
        |--------------------------------------------------------------------------
        */

        $spreadsheet =
            new Spreadsheet();


        $spreadsheet
            ->getProperties()
            ->setCreator(
                'AdmonGas'
            )
            ->setTitle(
                "Solicitud de Vales {$year}"
            )
            ->setSubject(
                'Reporte Anual de Solicitud de Vales'
            );


        $sheet =
            $spreadsheet->getActiveSheet();


        $sheet->setTitle(
            'Reporte Anual ' . $year
        );


        /*
        |--------------------------------------------------------------------------
        | Encabezados
        |--------------------------------------------------------------------------
        */

        $encabezados = [
            $idEstacion === 0
                ? 'Estación/Cuenta'
                : 'Estación',

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
            'A1'
        );


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
        | Datos
        |--------------------------------------------------------------------------
        */

        $fila =
            2;


        foreach (
            $registros
            as $registro
        ) {

            $contenido = [
                $registro->nombre,
            ];


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


                $contenido[] =
                    $monto;


                $totalesMeses[$mes] +=
                    $monto;
            }


            /*
            |--------------------------------------------------------------------------
            | Total
            |--------------------------------------------------------------------------
            */

            $total =
                (float) $registro->total;


            $contenido[] =
                $total;


            $totalGeneral +=
                $total;


            /*
            |--------------------------------------------------------------------------
            | Escribir
            |--------------------------------------------------------------------------
            */

            $sheet->fromArray(
                $contenido,
                null,
                "A{$fila}"
            );


            /*
            |--------------------------------------------------------------------------
            | Total anual bold
            |--------------------------------------------------------------------------
            */

            $sheet
                ->getStyle(
                    "N{$fila}"
                )
                ->getFont()
                ->setBold(
                    true
                );


            $fila++;
        }


        /*
        |--------------------------------------------------------------------------
        | Sin datos
        |--------------------------------------------------------------------------
        */

        if ($registros->isEmpty()) {

            $sheet->mergeCells(
                'A2:N2'
            );


            $sheet->setCellValue(
                'A2',
                'No se encontró información'
            );


            $sheet
                ->getStyle('A2')
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                );


            $ultimaFila =
                2;
        } else {

            /*
            |--------------------------------------------------------------------------
            | Total Neto
            |--------------------------------------------------------------------------
            |
            | Igual al legacy: solo reporte general.
            |
            */

            if ($idEstacion === 0) {

                $contenidoTotal = [
                    'Total Neto',
                ];


                for (
                    $mes = 1;
                    $mes <= 12;
                    $mes++
                ) {

                    $contenidoTotal[] =
                        $totalesMeses[$mes];
                }


                $contenidoTotal[] =
                    $totalGeneral;


                $sheet->fromArray(
                    $contenidoTotal,
                    null,
                    "A{$fila}"
                );


                /*
                |--------------------------------------------------------------------------
                | Bold
                |--------------------------------------------------------------------------
                */

                $sheet
                    ->getStyle(
                        "A{$fila}:N{$fila}"
                    )
                    ->getFont()
                    ->setBold(
                        true
                    );


                /*
                |--------------------------------------------------------------------------
                | Fondo
                |--------------------------------------------------------------------------
                */

                $sheet
                    ->getStyle(
                        "A{$fila}:N{$fila}"
                    )
                    ->getFill()
                    ->setFillType(
                        Fill::FILL_SOLID
                    )
                    ->getStartColor()
                    ->setRGB(
                        'D9E7F3'
                    );


                $ultimaFila =
                    $fila;
            } else {

                $ultimaFila =
                    $fila - 1;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Estilos
        |--------------------------------------------------------------------------
        */

        $this->aplicarEstilos(
            $spreadsheet,
            $ultimaFila
        );


        /*
        |--------------------------------------------------------------------------
        | Descargar
        |--------------------------------------------------------------------------
        */

        $this->descargar(
            $spreadsheet,
            $nombreReporte,
            $year
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Estilos
    |--------------------------------------------------------------------------
    */

    private function aplicarEstilos(
        Spreadsheet $spreadsheet,
        int $ultimaFila
    ): void {

        $sheet =
            $spreadsheet->getActiveSheet();


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getStyle(
                'A1:N1'
            )
            ->getFont()
            ->setBold(
                true
            )
            ->getColor()
            ->setRGB(
                'FFFFFF'
            );


        $sheet
            ->getStyle(
                'A1:N1'
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
                'A1:N1'
            )
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_CENTER
            )
            ->setVertical(
                Alignment::VERTICAL_CENTER
            )
            ->setWrapText(
                true
            );


        /*
        |--------------------------------------------------------------------------
        | Moneda
        |--------------------------------------------------------------------------
        |
        | B:N:
        |
        | 12 meses + total.
        |
        */

        if ($ultimaFila >= 2) {

            $sheet
                ->getStyle(
                    "B2:N{$ultimaFila}"
                )
                ->getNumberFormat()
                ->setFormatCode(
                    NumberFormat::FORMAT_CURRENCY_USD
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Anchos
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getColumnDimension('A')
            ->setWidth(
                25
            );


        foreach (
            range('B', 'M')
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


        $sheet
            ->getColumnDimension('N')
            ->setWidth(
                17
            );


        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getRowDimension(1)
            ->setRowHeight(
                28
            );


        /*
        |--------------------------------------------------------------------------
        | Freeze
        |--------------------------------------------------------------------------
        */

        $sheet->freezePane(
            'B2'
        );


        /*
        |--------------------------------------------------------------------------
        | Filtro
        |--------------------------------------------------------------------------
        */

        if ($ultimaFila >= 2) {

            $sheet->setAutoFilter(
                'A1:N'
                    . $ultimaFila
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
        | Nombre
        |--------------------------------------------------------------------------
        */

        $nombreArchivo =
            'Reporte Anual de Solicitud de Vales '
            . $nombreReporte
            . ' - '
            . $year
            . '.xlsx';


        /*
        |--------------------------------------------------------------------------
        | Sanitizar
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
        | Limpiar buffers
        |--------------------------------------------------------------------------
        */

        while (
            ob_get_level() > 0
        ) {

            ob_end_clean();
        }


        if (headers_sent()) {

            throw new RuntimeException(
                'No fue posible generar el Excel porque la respuesta HTTP ya fue enviada.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Headers
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | Writer
        |--------------------------------------------------------------------------
        */

        $writer =
            new Xlsx(
                $spreadsheet
            );


        $writer->save(
            'php://output'
        );


        /*
        |--------------------------------------------------------------------------
        | Liberar
        |--------------------------------------------------------------------------
        */

        $spreadsheet
            ->disconnectWorksheets();
    }
}

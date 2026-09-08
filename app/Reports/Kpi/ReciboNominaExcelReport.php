<?php

namespace App\Reports\Kpi;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ReciboNominaExcelReport
{
    private ReciboNominaData $data;


    public function __construct()
    {
        $this->data =
            new ReciboNominaData();
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

        $this->data->validar(
            $idEstacion,
            $year
        );


        $nombreReporte =
            $this->data->nombreReporte(
                $idEstacion
            );


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
                "Recibos de Nómina {$year}"
            )
            ->setSubject(
                'Reporte Anual de Recibos de Nómina'
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
            'No.',
            $idEstacion === 0
                ? 'Estación/Departamento'
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
        | Filas
        |--------------------------------------------------------------------------
        */

        $fila =
            2;

        $numero =
            1;


        foreach (
            $registros
            as $registro
        ) {

            $contenido = [
                $numero,

                $registro->nombre_estacion,
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
            | Total bold
            |--------------------------------------------------------------------------
            */

            $sheet
                ->getStyle(
                    "O{$fila}"
                )
                ->getFont()
                ->setBold(
                    true
                );


            $numero++;

            $fila++;
        }


        /*
        |--------------------------------------------------------------------------
        | Sin información
        |--------------------------------------------------------------------------
        */

        if ($registros->isEmpty()) {

            $sheet->mergeCells(
                'A2:O2'
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
            */

            if ($idEstacion === 0) {

                $contenidoTotal = [
                    '',
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


                $sheet
                    ->getStyle(
                        "A{$fila}:O{$fila}"
                    )
                    ->getFont()
                    ->setBold(
                        true
                    );


                $sheet
                    ->getStyle(
                        "A{$fila}:O{$fila}"
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
                'A1:O1'
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
                'A1:O1'
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
                'A1:O1'
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
        */

        if ($ultimaFila >= 2) {

            $sheet
                ->getStyle(
                    "C2:O{$ultimaFila}"
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
                7
            );


        $sheet
            ->getColumnDimension('B')
            ->setWidth(
                25
            );


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


        $sheet
            ->getColumnDimension('O')
            ->setWidth(
                17
            );


        /*
        |--------------------------------------------------------------------------
        | Header alto
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
            'C2'
        );


        /*
        |--------------------------------------------------------------------------
        | Filtro
        |--------------------------------------------------------------------------
        */

        if ($ultimaFila >= 2) {

            $sheet->setAutoFilter(
                'A1:O'
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

        $nombreArchivo =
            'Reporte Anual de Recibos de Nomina '
            . $nombreReporte
            . ' - '
            . $year
            . '.xlsx';


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


        $writer =
            new Xlsx(
                $spreadsheet
            );


        $writer->save(
            'php://output'
        );


        $spreadsheet
            ->disconnectWorksheets();
    }
}

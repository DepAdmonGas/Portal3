<?php

namespace App\Reports\Kpi;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\SolicitudCheque;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class SolicitudChequeExcelReport
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
        | Datos
        |--------------------------------------------------------------------------
        */

        $registros =
            $this->obtenerResumen(
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
                "Solicitud de Cheques {$year}"
            )
            ->setSubject(
                'Reporte Anual de Solicitud de Cheques'
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
        | Totales mensuales
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


        /*
        |--------------------------------------------------------------------------
        | Datos
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

            /*
            |--------------------------------------------------------------------------
            | Fila
            |--------------------------------------------------------------------------
            */

            $contenido = [
                $numero,

                $registro->nombre_estacion
                    ?? 'S/I',
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


            $totalNeto +=
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
            | Total estación bold
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
        | Sin datos
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
            |
            | Igual al legacy:
            | únicamente para general.
            |
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
                    $totalNeto;


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


                /*
                |--------------------------------------------------------------------------
                | Fondo total
                |--------------------------------------------------------------------------
                */

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
    | Validar
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
    | Obtener resumen
    |--------------------------------------------------------------------------
    |
    | Conservamos exactamente la misma estructura de datos del Excel legacy.
    |
    |--------------------------------------------------------------------------
    */

    private function obtenerResumen(
        int $idEstacion,
        int $year
    ) {

        $query =
            SolicitudCheque::query()

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
            );


        /*
        |--------------------------------------------------------------------------
        | Filtro estación
        |--------------------------------------------------------------------------
        */

        if ($idEstacion !== 0) {

            $query->where(
                'cheque.id_estacion',
                $idEstacion
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Resultado
        |--------------------------------------------------------------------------
        */

        return $query

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
    | Estilos Excel
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
        | Encabezado
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
        |
        | C:O son los doce meses + Total.
        |
        | El legacy recorría C:P aunque solo existen 15 columnas A:O.
        |
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
        | Encabezado altura
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
            'Reporte Anual de Solicitud de Cheques '
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


        /*
        |--------------------------------------------------------------------------
        | Limpiar buffer
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

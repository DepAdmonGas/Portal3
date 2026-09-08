<?php

namespace App\Reports\Kpi;

use App\Models\Operativo\RhLocalidad;
use App\Models\Operativo\VentasDia;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class ConcentradoVentasExcelReport
{
    /*
    |--------------------------------------------------------------------------
    | Estaciones reporte general
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
    | Colores de productos
    |--------------------------------------------------------------------------
    */

    private const COLORES_PRODUCTOS = [
        'G SUPER' =>
        '76BD1D',

        'G PREMIUM' =>
        'E21683',

        'G DIESEL' =>
        '000000',
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
        | Ventas
        |--------------------------------------------------------------------------
        |
        | Una sola consulta para todo el Excel.
        |
        */

        $ventas =
            $this->obtenerVentas(
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


        /*
        |--------------------------------------------------------------------------
        | Propiedades
        |--------------------------------------------------------------------------
        */

        $spreadsheet
            ->getProperties()
            ->setCreator(
                'AdmonGas'
            )
            ->setTitle(
                "Concentrado de Ventas {$year}"
            )
            ->setSubject(
                'Reporte Anual de Concentrado de Ventas'
            );


        /*
        |--------------------------------------------------------------------------
        | Tipo reporte
        |--------------------------------------------------------------------------
        */

        if ($idEstacion === 0) {

            /*
            |--------------------------------------------------------------------------
            | General
            |--------------------------------------------------------------------------
            |
            | 6 hojas:
            |
            | G SUPER - Litros
            | G SUPER - Pesos
            | G PREMIUM - Litros
            | G PREMIUM - Pesos
            | G DIESEL - Litros
            | G DIESEL - Pesos
            |
            */

            $this->generarGeneral(
                $spreadsheet,
                $configuracion['idsEstaciones'],
                $estaciones,
                $ventas
            );
        } else {

            /*
            |--------------------------------------------------------------------------
            | Estación
            |--------------------------------------------------------------------------
            |
            | Una hoja con:
            |
            | Mes
            | SUPER Litros / Pesos
            | PREMIUM Litros / Pesos
            | DIESEL Litros / Pesos
            |
            */

            $this->generarEstacion(
                $spreadsheet,
                $idEstacion,
                $year,
                $ventas
            );
        }


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
    | Validar parámetros
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
    | Obtener ventas
    |--------------------------------------------------------------------------
    |
    | Resultado:
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
                | Productos
                |--------------------------------------------------------------------------
                */

            ->whereIn(
                'venta.producto',
                self::PRODUCTOS
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
        | Matriz inicial
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
    | GENERAR GENERAL
    |--------------------------------------------------------------------------
    */

    private function generarGeneral(
        Spreadsheet $spreadsheet,
        array $idsEstaciones,
        $estaciones,
        array $ventas
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Eliminar hoja automática
        |--------------------------------------------------------------------------
        */

        $spreadsheet->removeSheetByIndex(
            0
        );


        /*
        |--------------------------------------------------------------------------
        | Productos
        |--------------------------------------------------------------------------
        */

        foreach (
            self::PRODUCTOS
            as $producto
        ) {

            /*
            |--------------------------------------------------------------------------
            | Litros
            |--------------------------------------------------------------------------
            */

            $this->crearHojaGeneral(
                $spreadsheet,
                $producto,
                'litros',
                $idsEstaciones,
                $estaciones,
                $ventas
            );


            /*
            |--------------------------------------------------------------------------
            | Pesos
            |--------------------------------------------------------------------------
            */

            $this->crearHojaGeneral(
                $spreadsheet,
                $producto,
                'pesos',
                $idsEstaciones,
                $estaciones,
                $ventas
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Primera hoja activa
        |--------------------------------------------------------------------------
        */

        $spreadsheet->setActiveSheetIndex(
            0
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Crear hoja general
    |--------------------------------------------------------------------------
    */

    private function crearHojaGeneral(
        Spreadsheet $spreadsheet,
        string $producto,
        string $tipo,
        array $idsEstaciones,
        $estaciones,
        array $ventas
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Hoja
        |--------------------------------------------------------------------------
        */

        $sheet =
            $spreadsheet->createSheet();


        /*
        |--------------------------------------------------------------------------
        | Nombre
        |--------------------------------------------------------------------------
        */

        $nombreTipo =
            ucfirst(
                $tipo
            );


        $sheet->setTitle(
            $producto
                . ' - '
                . $nombreTipo
        );


        /*
        |--------------------------------------------------------------------------
        | Encabezados
        |--------------------------------------------------------------------------
        */

        $sheet->setCellValue(
            'A1',
            'Estación'
        );


        /*
        |--------------------------------------------------------------------------
        | Meses
        |--------------------------------------------------------------------------
        */

        $columna =
            2;


        foreach (
            $this->meses()
            as $nombreMes
        ) {

            $letra =
                Coordinate::stringFromColumnIndex(
                    $columna
                );


            $sheet->setCellValue(
                "{$letra}1",
                $nombreMes
            );


            $columna++;
        }


        /*
        |--------------------------------------------------------------------------
        | Total
        |--------------------------------------------------------------------------
        */

        $columnaTotal =
            Coordinate::stringFromColumnIndex(
                $columna
            );


        $sheet->setCellValue(
            "{$columnaTotal}1",
            'Total ('
                . $nombreTipo
                . ')'
        );


        /*
        |--------------------------------------------------------------------------
        | Totales mensuales
        |--------------------------------------------------------------------------
        */

        $totalesPorMes =
            array_fill(
                1,
                12,
                0.0
            );


        /*
        |--------------------------------------------------------------------------
        | Datos
        |--------------------------------------------------------------------------
        */

        $fila =
            2;


        foreach (
            $idsEstaciones
            as $idEstacion
        ) {

            /*
            |--------------------------------------------------------------------------
            | Estación
            |--------------------------------------------------------------------------
            */

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


            $sheet->setCellValue(
                "A{$fila}",
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

                /*
                |--------------------------------------------------------------------------
                | Valor
                |--------------------------------------------------------------------------
                */

                $valor =
                    (float) (
                        $ventas[$idEstacion][$mes][$producto][$tipo]
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Acumulados
                |--------------------------------------------------------------------------
                */

                $totalEstacion +=
                    $valor;


                $totalesPorMes[$mes] +=
                    $valor;


                /*
                |--------------------------------------------------------------------------
                | Columna
                |--------------------------------------------------------------------------
                */

                $numeroColumna =
                    $mes + 1;


                $letra =
                    Coordinate::stringFromColumnIndex(
                        $numeroColumna
                    );


                /*
                |--------------------------------------------------------------------------
                | Valor numérico
                |--------------------------------------------------------------------------
                */

                $sheet->setCellValueExplicit(
                    "{$letra}{$fila}",
                    $valor,
                    DataType::TYPE_NUMERIC
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Total estación
            |--------------------------------------------------------------------------
            */

            $sheet->setCellValueExplicit(
                "{$columnaTotal}{$fila}",
                $totalEstacion,
                DataType::TYPE_NUMERIC
            );


            /*
            |--------------------------------------------------------------------------
            | Total negrita
            |--------------------------------------------------------------------------
            */

            $sheet
                ->getStyle(
                    "{$columnaTotal}{$fila}"
                )
                ->getFont()
                ->setBold(
                    true
                );


            /*
            |--------------------------------------------------------------------------
            | Siguiente fila
            |--------------------------------------------------------------------------
            */

            $fila++;
        }


        /*
        |--------------------------------------------------------------------------
        | Total Neto
        |--------------------------------------------------------------------------
        */

        $filaTotal =
            $fila;


        $sheet->setCellValue(
            "A{$filaTotal}",
            'Total Neto'
        );


        /*
        |--------------------------------------------------------------------------
        | Totales por mes
        |--------------------------------------------------------------------------
        */

        for (
            $mes = 1;
            $mes <= 12;
            $mes++
        ) {

            $numeroColumna =
                $mes + 1;


            $letra =
                Coordinate::stringFromColumnIndex(
                    $numeroColumna
                );


            $sheet->setCellValueExplicit(
                "{$letra}{$filaTotal}",
                $totalesPorMes[$mes],
                DataType::TYPE_NUMERIC
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Total general
        |--------------------------------------------------------------------------
        */

        $sheet->setCellValueExplicit(
            "{$columnaTotal}{$filaTotal}",
            array_sum(
                $totalesPorMes
            ),
            DataType::TYPE_NUMERIC
        );


        /*
        |--------------------------------------------------------------------------
        | Estilos
        |--------------------------------------------------------------------------
        */

        $this->aplicarEstilosHojaGeneral(
            $sheet,
            $tipo,
            $filaTotal,
            $columnaTotal
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Estilos hoja general
    |--------------------------------------------------------------------------
    */

    private function aplicarEstilosHojaGeneral(
        $sheet,
        string $tipo,
        int $filaTotal,
        string $columnaTotal
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Encabezado
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getStyle(
                "A1:{$columnaTotal}1"
            )
            ->getFont()
            ->setBold(
                true
            );


        $sheet
            ->getStyle(
                "A1:{$columnaTotal}1"
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
        | Formato números
        |--------------------------------------------------------------------------
        */

        $formato =
            $tipo === 'litros'
            ? '#,##0.00'
            : NumberFormat::FORMAT_CURRENCY_USD;


        if ($filaTotal >= 2) {

            $sheet
                ->getStyle(
                    "B2:{$columnaTotal}{$filaTotal}"
                )
                ->getNumberFormat()
                ->setFormatCode(
                    $formato
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Total Neto
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getStyle(
                "A{$filaTotal}:{$columnaTotal}{$filaTotal}"
            )
            ->getFont()
            ->setBold(
                true
            );


        /*
        |--------------------------------------------------------------------------
        | Anchos
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getColumnDimension('A')
            ->setWidth(
                23
            );


        for (
            $columna = 2;
            $columna <= 13;
            $columna++
        ) {

            $letra =
                Coordinate::stringFromColumnIndex(
                    $columna
                );


            $sheet
                ->getColumnDimension(
                    $letra
                )
                ->setWidth(
                    14
                );
        }


        $sheet
            ->getColumnDimension(
                $columnaTotal
            )
            ->setWidth(
                17
            );


        /*
        |--------------------------------------------------------------------------
        | Congelar encabezado
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

        $sheet->setAutoFilter(
            "A1:{$columnaTotal}"
                . ($filaTotal - 1)
        );
    }


    /*
    |--------------------------------------------------------------------------
    | GENERAR ESTACIÓN
    |--------------------------------------------------------------------------
    */

    private function generarEstacion(
        Spreadsheet $spreadsheet,
        int $idEstacion,
        int $year,
        array $ventas
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Hoja existente
        |--------------------------------------------------------------------------
        */

        $sheet =
            $spreadsheet->getActiveSheet();


        /*
        |--------------------------------------------------------------------------
        | Nombre hoja
        |--------------------------------------------------------------------------
        */

        $sheet->setTitle(
            'Reporte Anual ' . $year
        );


        /*
        |--------------------------------------------------------------------------
        | Encabezado Mes
        |--------------------------------------------------------------------------
        */

        $sheet->setCellValue(
            'A1',
            'Mes'
        );


        /*
        |--------------------------------------------------------------------------
        | Productos
        |--------------------------------------------------------------------------
        */

        $columna =
            2;


        foreach (
            self::PRODUCTOS
            as $producto
        ) {

            /*
            |--------------------------------------------------------------------------
            | Litros
            |--------------------------------------------------------------------------
            */

            $columnaLitros =
                Coordinate::stringFromColumnIndex(
                    $columna
                );


            $sheet->setCellValue(
                "{$columnaLitros}1",
                $producto
                    . "\n(Litros)"
            );


            /*
            |--------------------------------------------------------------------------
            | Pesos
            |--------------------------------------------------------------------------
            */

            $columnaPesos =
                Coordinate::stringFromColumnIndex(
                    $columna + 1
                );


            $sheet->setCellValue(
                "{$columnaPesos}1",
                $producto
                    . "\n(Pesos)"
            );


            /*
            |--------------------------------------------------------------------------
            | Color producto
            |--------------------------------------------------------------------------
            */

            $color =
                self::COLORES_PRODUCTOS[$producto];


            foreach (
                [
                    $columnaLitros,
                    $columnaPesos,
                ]
                as $letra
            ) {

                $sheet
                    ->getStyle(
                        "{$letra}1"
                    )
                    ->getFill()
                    ->setFillType(
                        Fill::FILL_SOLID
                    )
                    ->getStartColor()
                    ->setRGB(
                        $color
                    );


                /*
                |--------------------------------------------------------------------------
                | Texto blanco
                |--------------------------------------------------------------------------
                |
                | En el legacy DIESEL tenía fondo negro pero no se cambiaba
                | explícitamente el color del texto.
                |
                */

                $sheet
                    ->getStyle(
                        "{$letra}1"
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
                        "{$letra}1"
                    )
                    ->getAlignment()
                    ->setWrapText(
                        true
                    )
                    ->setHorizontal(
                        Alignment::HORIZONTAL_CENTER
                    )
                    ->setVertical(
                        Alignment::VERTICAL_CENTER
                    );
            }


            $columna +=
                2;
        }


        /*
        |--------------------------------------------------------------------------
        | Mes header
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getStyle('A1')
            ->getFont()
            ->setBold(
                true
            );


        $sheet
            ->getStyle('A1')
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_CENTER
            )
            ->setVertical(
                Alignment::VERTICAL_CENTER
            );


        /*
        |--------------------------------------------------------------------------
        | Totales por producto
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
        | Datos
        |--------------------------------------------------------------------------
        */

        $fila =
            2;


        foreach (
            $this->meses()
            as $mes => $nombreMes
        ) {

            /*
            |--------------------------------------------------------------------------
            | Mes
            |--------------------------------------------------------------------------
            */

            $sheet->setCellValue(
                "A{$fila}",
                $nombreMes
            );


            /*
            |--------------------------------------------------------------------------
            | Productos
            |--------------------------------------------------------------------------
            */

            $columna =
                2;


            foreach (
                self::PRODUCTOS
                as $producto
            ) {

                /*
                |--------------------------------------------------------------------------
                | Litros
                |--------------------------------------------------------------------------
                */

                $litros =
                    (float) (
                        $ventas[$idEstacion][$mes][$producto]['litros']
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Pesos
                |--------------------------------------------------------------------------
                */

                $pesos =
                    (float) (
                        $ventas[$idEstacion][$mes][$producto]['pesos']
                        ?? 0
                    );


                /*
                |--------------------------------------------------------------------------
                | Acumulados
                |--------------------------------------------------------------------------
                */

                $totales[$producto]['litros'] +=
                    $litros;


                $totales[$producto]['pesos'] +=
                    $pesos;


                /*
                |--------------------------------------------------------------------------
                | Celda Litros
                |--------------------------------------------------------------------------
                */

                $letraLitros =
                    Coordinate::stringFromColumnIndex(
                        $columna
                    );


                $sheet->setCellValueExplicit(
                    "{$letraLitros}{$fila}",
                    $litros,
                    DataType::TYPE_NUMERIC
                );


                /*
                |--------------------------------------------------------------------------
                | Celda Pesos
                |--------------------------------------------------------------------------
                */

                $letraPesos =
                    Coordinate::stringFromColumnIndex(
                        $columna + 1
                    );


                $sheet->setCellValueExplicit(
                    "{$letraPesos}{$fila}",
                    $pesos,
                    DataType::TYPE_NUMERIC
                );


                /*
                |--------------------------------------------------------------------------
                | Siguiente producto
                |--------------------------------------------------------------------------
                */

                $columna +=
                    2;
            }


            /*
            |--------------------------------------------------------------------------
            | Siguiente mes
            |--------------------------------------------------------------------------
            */

            $fila++;
        }


        /*
        |--------------------------------------------------------------------------
        | Total Neto
        |--------------------------------------------------------------------------
        */

        $filaTotal =
            $fila;


        $sheet->setCellValue(
            "A{$filaTotal}",
            'Total Neto'
        );


        /*
        |--------------------------------------------------------------------------
        | Totales producto
        |--------------------------------------------------------------------------
        */

        $columna =
            2;


        foreach (
            self::PRODUCTOS
            as $producto
        ) {

            /*
            |--------------------------------------------------------------------------
            | Litros
            |--------------------------------------------------------------------------
            */

            $letraLitros =
                Coordinate::stringFromColumnIndex(
                    $columna
                );


            $sheet->setCellValueExplicit(
                "{$letraLitros}{$filaTotal}",
                $totales[$producto]['litros'],
                DataType::TYPE_NUMERIC
            );


            /*
            |--------------------------------------------------------------------------
            | Pesos
            |--------------------------------------------------------------------------
            */

            $letraPesos =
                Coordinate::stringFromColumnIndex(
                    $columna + 1
                );


            $sheet->setCellValueExplicit(
                "{$letraPesos}{$filaTotal}",
                $totales[$producto]['pesos'],
                DataType::TYPE_NUMERIC
            );


            $columna +=
                2;
        }


        /*
        |--------------------------------------------------------------------------
        | Estilos
        |--------------------------------------------------------------------------
        */

        $this->aplicarEstilosEstacion(
            $sheet,
            $filaTotal
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Estilos estación
    |--------------------------------------------------------------------------
    */

    private function aplicarEstilosEstacion(
        $sheet,
        int $filaTotal
    ): void {

        /*
        |--------------------------------------------------------------------------
        | Formatos
        |--------------------------------------------------------------------------
        |
        | B = SUPER Litros
        | C = SUPER Pesos
        |
        | D = PREMIUM Litros
        | E = PREMIUM Pesos
        |
        | F = DIESEL Litros
        | G = DIESEL Pesos
        |
        */

        foreach (
            [
                'B',
                'D',
                'F',
            ]
            as $columna
        ) {

            $sheet
                ->getStyle(
                    "{$columna}2:{$columna}{$filaTotal}"
                )
                ->getNumberFormat()
                ->setFormatCode(
                    '#,##0.00'
                );
        }


        foreach (
            [
                'C',
                'E',
                'G',
            ]
            as $columna
        ) {

            $sheet
                ->getStyle(
                    "{$columna}2:{$columna}{$filaTotal}"
                )
                ->getNumberFormat()
                ->setFormatCode(
                    NumberFormat::FORMAT_CURRENCY_USD
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Total Neto
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getStyle(
                "A{$filaTotal}:G{$filaTotal}"
            )
            ->getFont()
            ->setBold(
                true
            );


        /*
        |--------------------------------------------------------------------------
        | Altura encabezado
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getRowDimension(
                1
            )
            ->setRowHeight(
                32
            );


        /*
        |--------------------------------------------------------------------------
        | Anchos
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getColumnDimension('A')
            ->setWidth(
                15
            );


        foreach (
            [
                'B',
                'C',
                'D',
                'E',
                'F',
                'G',
            ]
            as $columna
        ) {

            $sheet
                ->getColumnDimension(
                    $columna
                )
                ->setWidth(
                    18
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Congelar
        |--------------------------------------------------------------------------
        */

        $sheet->freezePane(
            'B2'
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
            'Reporte Anual de Concentrado de Ventas '
            . $nombreReporte
            . ' - '
            . $year
            . '.xlsx';


        /*
        |--------------------------------------------------------------------------
        | Limpiar nombre
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


    /*
    |--------------------------------------------------------------------------
    | Meses
    |--------------------------------------------------------------------------
    */

    private function meses(): array
    {
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
        ];
    }
}

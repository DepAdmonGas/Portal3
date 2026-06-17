<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AnalisisCompraExcelService
{
public static function generar(int $idEstacion, int $idYear, int $idMes): Spreadsheet
{
$rows = AnalisisCompraService::getDatos($idEstacion, $idYear, $idMes);

$estacion = \App\Models\Estacion::find($idEstacion)?->nombre ?? '';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Analisis de Compras');

$headers = [
'TAD', 'Fecha', 'No. Factura', 'Litros factura', 'Cuenta litros',
'Merma con cuenta litros', 'Tolerancia de Merma .55%', 'Producto', 'Transporte',
'Unidad', 'Chofer', 'Importe G500 Facturado', 'Importe Transporte',
'Precio Pickup facturado', 'Precio Pemex', 'Diferencia', 'Diferencial $ vs Pemex',
'Importe merma total $', 'Merma', 'Importe Merma', 'NOTA C',
'Importe Nota', 'Factura Transporte no.', 'Monto factura',
'Total a pagar transporte', 'Status', 'PICKUP', 'PEMEX',
];

$fila = 1;

foreach ($headers as $col => $header) {
$cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . $fila;
$sheet->setCellValue($cell, $header);
}

$headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID);
$sheet->getStyle($headerRange)->getFill()->getStartColor()->setRGB('749ABF');
$sheet->getStyle($headerRange)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($headerRange)->getFont()->setBold(true);
$sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($headerRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

$totalDiferenciaPemex = 0;
$totalImporteMerma = 0;
$totalImporteNota = 0;
$totalPickup = 0;
$totalPemex = 0;

$fila = 2;

foreach ($rows as $r) {
$litrosFacturados = $r['litros_facturados'];
$precioPickup = $r['precio_pickup'];
$precioPemex = $r['precio_pemex'];

$pickup = $litrosFacturados - $precioPickup;
$pemex = $litrosFacturados - $precioPemex;
$diferenciaPemex = $pickup - $pemex;

$vals = [
$r['tad'],
$r['fecha'],
$r['no_factura'],
$r['litros_facturados'],
$r['cuenta_litros'],
$r['merma_cuenta_litros'],
$r['tolerancia'],
$r['producto'],
$r['nombre_razonsocial'],
$r['unidad'],
$r['chofer'],
$r['importe_facturado'],
$r['importe_transporte'],
$r['precio_pickup'],
$r['precio_pemex'],
$r['diferencia'],
$diferenciaPemex,
$r['importe_merma_total'],
$r['merma'],
$r['importe_merma'],
$r['notac'],
$r['importe_nota'],
$r['factura_transporte'],
$r['monto_factura'],
$r['total_pagar_transporte'],
$r['status'],
$pickup,
$pemex,
];

foreach ($vals as $col => $val) {
$cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . $fila;
$sheet->setCellValue($cell, $val);
}

$totalDiferenciaPemex += $diferenciaPemex;
$totalImporteMerma += $r['importe_merma'];
$totalImporteNota += $r['importe_nota'];
$totalPickup += $pickup;
$totalPemex += $pemex;

$fila++;
}

$totalVals = [
'', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
$totalDiferenciaPemex,
'', '',
$totalImporteMerma,
'',
$totalImporteNota,
'', '', '', '',
$totalPickup,
$totalPemex,
];

foreach ($totalVals as $col => $val) {
$cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1) . $fila;
$sheet->setCellValue($cell, $val);
}

$totalRange = 'A' . $fila . ':' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . $fila;
$sheet->getStyle($totalRange)->getFont()->setBold(true);

$ultimaFila = $fila;

$monetaryFormat = '$ #,##0.00';
$numberFormat = '#,##0.00';

$monetaryCols = [12, 13, 14, 15, 16, 17, 18, 20, 22, 24, 25, 27, 28];
$numericCols = [4, 5, 6, 7, 19];

foreach ($monetaryCols as $colIdx) {
$colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
$range = $colLetter . '2:' . $colLetter . $ultimaFila;
$sheet->getStyle($range)->getNumberFormat()->setFormatCode($monetaryFormat);
}

foreach ($numericCols as $colIdx) {
$colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
$range = $colLetter . '2:' . $colLetter . $ultimaFila;
$sheet->getStyle($range)->getNumberFormat()->setFormatCode($numberFormat);
}

foreach (range(1, count($headers)) as $col) {
$sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
}

return $spreadsheet;
}

public static function generarYDescargar(int $idEstacion, int $idYear, int $idMes): void
{
$spreadsheet = self::generar($idEstacion, $idYear, $idMes);

$estacion = \App\Models\Estacion::find($idEstacion)?->nombre ?? '';
$mesNombre = nombremes(str_pad($idMes, 2, '0', STR_PAD_LEFT));

$writer = new Xlsx($spreadsheet);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Analisis_Compras_' . $estacion . '_' . $mesNombre . '_' . $idYear . '.xlsx"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
}
}

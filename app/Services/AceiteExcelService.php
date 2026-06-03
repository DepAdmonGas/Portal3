<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class AceiteExcelService
{
public static function generar(int $idEstacion, int $idYear, int $idMes): Spreadsheet
{
$idMesDb = AceiteService::getMesId($idEstacion, $idYear, $idMes);
if (!$idMesDb) {
throw new \RuntimeException('Mes no encontrado');
}

$reporte = AceiteService::getReporte($idMesDb);
$rows = $reporte['rows'];
$totals = $reporte['totals'];

$estacion = AceiteService::getNombreEstacion($idEstacion);
$Udia = (int) date('t', mktime(0, 0, 0, $idMes, 1, $idYear));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Resumen Aceites");

// ── Enacabezado 1 ──
$fila = 1;

$sheet->setCellValue('A1', 'Concepto');
$sheet->mergeCells("A1:B1");
$sheet->setCellValue('C1', 'Pzas caja');
$sheet->setCellValue('D1', 'Precio Unitario');
$sheet->setCellValue('E1', 'Bodega');
$sheet->setCellValue('F1', 'Exhibidores');
$sheet->setCellValue('G1', 'Inventario Inicial');
$sheet->setCellValue('H1', 'Compras / Pedido');
$sheet->setCellValue('I1', 'Ventas del mes');
$sheet->setCellValue('J1', 'Inventario Final');
$sheet->setCellValue('K1', 'Inventario fisico Bodega');
$sheet->setCellValue('L1', 'Inventario fisico Exhibidores');
$sheet->setCellValue('M1', 'Inventario fisico Final');
$sheet->setCellValue('N1', 'Diferencia');
$sheet->setCellValue('O1', 'Diferencia $');
$sheet->setCellValue('P1', 'Prod. Facturados');
$sheet->setCellValue('Q1', 'Factura venta mostrador');
$sheet->setCellValue('R1', 'Fac. total');
$sheet->setCellValue('S1', 'Dif. En Facturación');

// Columnas dinámicas: cantidades por día + Total + importes por día + Total
$colInicio = 'T';
$col = $colInicio;

for ($P = 1; $P <= $Udia; $P++) {
$sheet->setCellValue($col . $fila, $P);
$col++;
}

$sheet->setCellValue($col . $fila, "Total");
$colTotalCantidades = $col;
$col++;

for ($P = 1; $P <= $Udia; $P++) {
$sheet->setCellValue($col . $fila, $P);
$col++;
}

$sheet->setCellValue($col . $fila, "Total");
$colTotalImportes = $col;
$colFinal = $col;

// ── Estilo encabezado 1 ──
$rangoEstatico = "A1:S1";
$rangoDinamico = $colInicio . $fila . ':' . $colFinal . $fila;

foreach (['A' => 'S', $colInicio => $colFinal] as $ci => $cf) {
$range = $ci . $fila . ':' . $cf . $fila;
$sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID);
$sheet->getStyle($range)->getFill()->getStartColor()->setRGB('749ABF');
$sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($range)->getFont()->setBold(true);
$sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// ── Datos ──
$filaDatos = 2;

// Totales por día (cantidad e importe) para la fila total
$totalesDiaCantidad = array_fill(1, $Udia, 0);
$totalesDiaImporte = array_fill(1, $Udia, 0);

foreach ($rows as $r) {
$idAceite = $r['id_aceite'];
$concepto = $r['concepto'] ?? '';
$precio = (float) $r['precio'];
$piezas = (int) ($r['piezas'] ?? 0);
$bodega = (int) $r['bodega'];
$exibidores = (int) $r['exibidores'];
$pedido = (int) $r['pedido'];
$invBodega = (int) $r['inventario_bodega'];
$invExibidores = (int) $r['inventario_exibidores'];
$prodFacturado = (float) $r['producto_facturado'];
$factVtaMostrador = (float) $r['factura_venta_mostrador'];

$ventasMes = (int) $r['ventas_mes'];
$inventarioI = (int) $r['inventario_inicial'];
$inventarioF = (int) $r['inventario_final_calc'];
$inventarioFinal = (int) $r['inventario_final_fisico'];
$diferencia = (int) $r['diferencia'];
$difPrecio = (float) $r['diferencia_precio'];
$factotal = (float) $r['factotal'];
$diffactura = (float) $r['diffactura'];

// Columnas fijas A-S
$sheet->setCellValue("A{$filaDatos}", $idAceite);
$sheet->setCellValue("B{$filaDatos}", $concepto);
$sheet->setCellValue("C{$filaDatos}", $piezas);
$sheet->setCellValue("D{$filaDatos}", $precio);
$sheet->setCellValue("E{$filaDatos}", $bodega);
$sheet->setCellValue("F{$filaDatos}", $exibidores);
$sheet->setCellValue("G{$filaDatos}", $inventarioI);
$sheet->setCellValue("H{$filaDatos}", $pedido);
$sheet->setCellValue("I{$filaDatos}", $ventasMes);
$sheet->setCellValue("J{$filaDatos}", $inventarioF);
$sheet->setCellValue("K{$filaDatos}", $invBodega);
$sheet->setCellValue("L{$filaDatos}", $invExibidores);
$sheet->setCellValue("M{$filaDatos}", $inventarioFinal);
$sheet->setCellValue("N{$filaDatos}", $diferencia);
$sheet->setCellValue("O{$filaDatos}", $difPrecio);
$sheet->setCellValue("P{$filaDatos}", $prodFacturado);
$sheet->setCellValue("Q{$filaDatos}", $factVtaMostrador);
$sheet->setCellValue("R{$filaDatos}", $factotal);
$sheet->setCellValue("S{$filaDatos}", $diffactura);

// Cantidades por día
$col = $colInicio;
$totalDiariaCantidad = 0;
$totalDiariaImporte = 0;

for ($d = 1; $d <= $Udia; $d++) {
$cantidad = (int) ($r['diarias'][$d]['cantidad'] ?? 0);
$importe = (float) ($r['diarias'][$d]['importe'] ?? 0);

$sheet->setCellValue($col . $filaDatos, $cantidad);
$totalesDiaCantidad[$d] += $cantidad;
$totalDiariaCantidad += $cantidad;

$col++;
}

// Total cantidad del aceite
$sheet->setCellValue($col . $filaDatos, $ventasMes);
$col++;

// Importes por día
for ($d = 1; $d <= $Udia; $d++) {
$importe = (float) ($r['diarias'][$d]['importe'] ?? 0);

$sheet->setCellValue($col . $filaDatos, $importe);
$totalesDiaImporte[$d] += $importe;
$totalDiariaImporte += $importe;

$col++;
}

// Total importe del aceite
$sheet->setCellValue($col . $filaDatos, $totalDiariaImporte);

$filaDatos++;
}

// ── Fila Total ──
$sheet->setCellValue("A{$filaDatos}", "TOTAL");
$sheet->mergeCells("A{$filaDatos}:D{$filaDatos}");

$sheet->setCellValue("E{$filaDatos}", $totals['bodega']);
$sheet->setCellValue("F{$filaDatos}", $totals['exibidores']);
$sheet->setCellValue("G{$filaDatos}", $totals['inventarioI']);
$sheet->setCellValue("H{$filaDatos}", $totals['pedido']);
$sheet->setCellValue("I{$filaDatos}", $totals['ventasM']);
$sheet->setCellValue("J{$filaDatos}", $totals['inventarioF']);
$sheet->setCellValue("K{$filaDatos}", $totals['inventario_bodega']);
$sheet->setCellValue("L{$filaDatos}", $totals['inventario_exibidores']);
$sheet->setCellValue("M{$filaDatos}", $totals['inventario_final']);
$sheet->setCellValue("N{$filaDatos}", $totals['diferencia']);
$sheet->setCellValue("O{$filaDatos}", $totals['difPrecio']);
$sheet->setCellValue("P{$filaDatos}", '');
$sheet->setCellValue("Q{$filaDatos}", '');
$sheet->setCellValue("R{$filaDatos}", '');
$sheet->setCellValue("S{$filaDatos}", '');

// Totales por día (cantidad)
$col = $colInicio;
$sumtGeneral = 0;
for ($d = 1; $d <= $Udia; $d++) {
$sheet->setCellValue($col . $filaDatos, $totalesDiaCantidad[$d]);
$sumtGeneral += $totalesDiaCantidad[$d];
$col++;
}
$sheet->setCellValue($col . $filaDatos, $sumtGeneral);
$col++;

// Totales por día (importe)
$importeTotalGeneral = 0;
for ($d = 1; $d <= $Udia; $d++) {
$sheet->setCellValue($col . $filaDatos, $totalesDiaImporte[$d]);
$importeTotalGeneral += $totalesDiaImporte[$d];
$col++;
}
$sheet->setCellValue($col . $filaDatos, $importeTotalGeneral);

// ── Ajustar columnas y alineaciòn ──
for ($col = 'A'; $col !== $colFinal; $col++) {
$sheet->getColumnDimension($col)->setAutoSize(true);
$sheet->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($col)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}
$sheet->getColumnDimension($colFinal)->setAutoSize(true);
$sheet->getStyle($colFinal)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($colFinal)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

return $spreadsheet;
}

public static function generarYDescargar(int $idEstacion, int $idYear, int $idMes): void
{
$spreadsheet = self::generar($idEstacion, $idYear, $idMes);

$estacion = AceiteService::getNombreEstacion($idEstacion);
$mesNombre = nombremes(str_pad($idMes, 2, '0', STR_PAD_LEFT));

$writer = new Xlsx($spreadsheet);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Resumen_Clientes_' . $estacion . '_' . $mesNombre . '_' . $idYear . '.xlsx"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
}

}

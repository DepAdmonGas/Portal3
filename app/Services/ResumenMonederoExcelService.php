<?php

namespace App\Services;
use App\Core\Auth;
use App\Models\Estacion;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Core\Session;

class ResumenMonederoExcelService
{
public static function generarYDescargar(int $idEstacion, int $idYear, int $idMes): void
{
$usuario = Auth::user();
$sessionUsuario = Session::get('usuario');
$esDireccionOperaciones = $usuario && $usuario->puesto && $usuario->puesto->tipo_puesto === 'Dirección de operaciones';
$verProsegur = in_array($sessionUsuario['id'] ?? 0, [19, 318]) || $esDireccionOperaciones;

$idMesDb = ResumenMonederoService::getMesId($idEstacion, $idYear, $idMes);
if (!$idMesDb) {
throw new \RuntimeException('Mes no encontrado');
}

$data = ResumenMonederoService::getData($idMesDb);
$rows = $data['rows'];
$totalKeys = [
    'g500fleet', 'bancomer', 'amex', 'inbursa', 'total_tb', 'inburgas', 'ticketcard', 'efecticard',
    'sodexo', 'ultragas', 'energex', 'shell', 'total_tarjetas', 'vale_accord', 'vale_efectivale',
    'vale_sodexo', 'si_vale', 'total_vales', 'credito_pago', 'credito_consumo', 'debito_pago',
    'debito_consumo', 'total_pago', 'total_consumo', 'billete_matutino', 'billete_vespertino',
    'billete_nocturno', 'morralla', 'deposito_bancario', 'cheque1', 'transferencia1', 'cheque2',
    'transferencia2', 'total_prosegur',
];
$totales = array_merge(array_fill_keys($totalKeys, 0), $data['totales']);

$estacion = trim((string) Estacion::whereKey($idEstacion)->value('nombre'));
if ($estacion === '') {
    throw new \RuntimeException('No se encontró el nombre de la estación');
}
if (function_exists('mb_convert_case') && defined('MB_CASE_TITLE')) {
    $estacion = mb_convert_case(mb_strtolower($estacion, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}
$estacionNombreArchivo = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $estacion);
$estacionNombreArchivo = $estacionNombreArchivo !== false ? $estacionNombreArchivo : $estacion;
$estacionNombreArchivo = preg_replace('/[^A-Za-z0-9]+/', '_', $estacionNombreArchivo);
$estacionNombreArchivo = trim((string) $estacionNombreArchivo, '_');

$spreadsheet = new Spreadsheet();
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Resumen Monedero');

// Row 1
$sheet1->setCellValue('A1', 'MONEDEROS');
$sheet1->mergeCells('A1:S1');
$sheet1->setCellValue('T1', 'CRÉDITO');
$sheet1->mergeCells('T1:U1');
$sheet1->setCellValue('V1', 'DÉBITO');
$sheet1->mergeCells('V1:W1');
$sheet1->setCellValue('X1', 'PAGOS');
$sheet1->setCellValue('Y1', 'CONSUMOS');

$sheet1->getStyle('A1:Y1')->getFill()->setFillType(Fill::FILL_SOLID);
$sheet1->getStyle('A1:Y1')->getFill()->getStartColor()->setRGB('215d98');
$sheet1->getStyle('A1:Y1')->getFont()->getColor()->setRGB('FFFFFF');

if ($verProsegur) {
$sheet1->setCellValue('Z1', '');
$sheet1->mergeCells('Z1:AI1');
$sheet1->getStyle('A1:AI1')->getFill()->setFillType(Fill::FILL_SOLID);
$sheet1->getStyle('A1:AI1')->getFill()->getStartColor()->setRGB('215d98');
$sheet1->getStyle('A1:AI1')->getFont()->getColor()->setRGB('FFFFFF');
}

// Row 2
$sheet1->setCellValue('A2', '');
$sheet1->setCellValue('B2', 'TARJETAS BANCARIAS');
$sheet1->mergeCells('B2:F2');
$sheet1->setCellValue('G2', 'TARJETAS');
$sheet1->mergeCells('G2:N2');
$sheet1->setCellValue('O2', 'VALES');
$sheet1->mergeCells('O2:S2');
$sheet1->setCellValue('T2', 'CARTERA DE CLIENTES ATIO');
$sheet1->mergeCells('T2:Y2');

$sheet1->getStyle('A2:Y2')->getFill()->setFillType(Fill::FILL_SOLID);
$sheet1->getStyle('A2:Y2')->getFill()->getStartColor()->setRGB('749ABF');
$sheet1->getStyle('A2:Y2')->getFont()->getColor()->setRGB('FFFFFF');

if ($verProsegur) {
$sheet1->setCellValue('Z2', 'PROSEGUR');
$sheet1->mergeCells('Z2:AI2');
$sheet1->getStyle('A2:AI2')->getFill()->setFillType(Fill::FILL_SOLID);
$sheet1->getStyle('A2:AI2')->getFill()->getStartColor()->setRGB('749ABF');
$sheet1->getStyle('A2:AI2')->getFont()->getColor()->setRGB('FFFFFF');
}

// Row 3
$sheet1->setCellValue('A3', 'FECHA');
$sheet1->setCellValue('B3', 'BANCOMER');
$sheet1->setCellValue('C3', 'AMEX');
$sheet1->setCellValue('D3', 'INBURGAS');
$sheet1->setCellValue('E3', 'INBURSA');
$sheet1->setCellValue('F3', 'TOTAL');
$sheet1->setCellValue('G3', 'TICKETCARD');
$sheet1->setCellValue('H3', 'TICKETCARD+');
$sheet1->setCellValue('I3', 'EFECTICARD');
$sheet1->setCellValue('J3', 'SODEXO');
$sheet1->setCellValue('K3', 'ULTRAGAS');
$sheet1->setCellValue('L3', 'ENERGEX');
$sheet1->setCellValue('M3', 'SHELL');
$sheet1->setCellValue('N3', 'TOTAL');
$sheet1->setCellValue('O3', 'VALE ACCORD');
$sheet1->setCellValue('P3', 'VALE EFECTIVALE');
$sheet1->setCellValue('Q3', 'VALE SODEXO');
$sheet1->setCellValue('R3', 'SI VALE');
$sheet1->setCellValue('S3', 'TOTAL');
$sheet1->setCellValue('T3', 'PAGOS');
$sheet1->setCellValue('U3', 'CONSUMOS');
$sheet1->setCellValue('V3', 'PAGOS');
$sheet1->setCellValue('W3', 'CONSUMOS');
$sheet1->setCellValue('X3', 'TOTAL');
$sheet1->setCellValue('Y3', 'TOTAL');

if ($verProsegur) {
$sheet1->setCellValue('Z3', 'BILLETE MATUTINO');
$sheet1->setCellValue('AA3', 'BILLETE VESPERTINO');
$sheet1->setCellValue('AB3', 'BILLETE NOCTURNO');
$sheet1->setCellValue('AC3', 'MORRALLA');
$sheet1->setCellValue('AD3', 'DEPOSITO BANCARIO');
$sheet1->setCellValue('AE3', 'CHEQUE 1');
$sheet1->setCellValue('AF3', 'TRANSFERENCIA 1');
$sheet1->setCellValue('AG3', 'CHEQUE 2');
$sheet1->setCellValue('AH3', 'TRANSFERENCIA 2');
$sheet1->setCellValue('AI3', 'TOTAL');
}

// Data rows
$rowIdx = 4;
foreach ($rows as $r) {
$sheet1->setCellValue("A$rowIdx", $r['fecha']);
$sheet1->setCellValue("B$rowIdx", $r['bancomer']);
$sheet1->setCellValue("C$rowIdx", $r['amex']);
$sheet1->setCellValue("D$rowIdx", $r['inburgas']);
$sheet1->setCellValue("E$rowIdx", $r['inbursa']);
$sheet1->setCellValue("F$rowIdx", $r['total_tb']);
$sheet1->setCellValue("G$rowIdx", $r['ticketcard']);
$sheet1->setCellValue("H$rowIdx", $r['g500fleet']);
$sheet1->setCellValue("I$rowIdx", $r['efecticard']);
$sheet1->setCellValue("J$rowIdx", $r['sodexo']);
$sheet1->setCellValue("K$rowIdx", $r['ultragas']);
$sheet1->setCellValue("L$rowIdx", $r['energex']);
$sheet1->setCellValue("M$rowIdx", $r['shell']);
$sheet1->setCellValue("N$rowIdx", $r['total_tarjetas']);
$sheet1->setCellValue("O$rowIdx", $r['vale_accord']);
$sheet1->setCellValue("P$rowIdx", $r['vale_efectivale']);
$sheet1->setCellValue("Q$rowIdx", $r['vale_sodexo']);
$sheet1->setCellValue("R$rowIdx", $r['si_vale']);
$sheet1->setCellValue("S$rowIdx", $r['total_vales']);
$sheet1->setCellValue("T$rowIdx", $r['credito_pago']);
$sheet1->setCellValue("U$rowIdx", $r['credito_consumo']);
$sheet1->setCellValue("V$rowIdx", $r['debito_pago']);
$sheet1->setCellValue("W$rowIdx", $r['debito_consumo']);
$sheet1->setCellValue("X$rowIdx", $r['total_pago']);
$sheet1->setCellValue("Y$rowIdx", $r['total_consumo']);
if ($verProsegur) {
$sheet1->setCellValue("Z$rowIdx", $r['billete_matutino']);
$sheet1->setCellValue("AA$rowIdx", $r['billete_vespertino']);
$sheet1->setCellValue("AB$rowIdx", $r['billete_nocturno']);
$sheet1->setCellValue("AC$rowIdx", $r['morralla']);
$sheet1->setCellValue("AD$rowIdx", $r['deposito_bancario']);
$sheet1->setCellValue("AE$rowIdx", $r['cheque1']);
$sheet1->setCellValue("AF$rowIdx", $r['transferencia1']);
$sheet1->setCellValue("AG$rowIdx", $r['cheque2']);
$sheet1->setCellValue("AH$rowIdx", $r['transferencia2']);
$sheet1->setCellValue("AI$rowIdx", $r['total_prosegur']);
}
$rowIdx++;
}

// Totals row
$sheet1->setCellValue("A$rowIdx", '');
$sheet1->setCellValue("B$rowIdx", $totales['bancomer']);
$sheet1->setCellValue("C$rowIdx", $totales['amex']);
$sheet1->setCellValue("D$rowIdx", $totales['inburgas']);
$sheet1->setCellValue("E$rowIdx", $totales['inbursa']);
$sheet1->setCellValue("F$rowIdx", $totales['total_tb']);
$sheet1->setCellValue("G$rowIdx", $totales['ticketcard']);
$sheet1->setCellValue("H$rowIdx", $totales['g500fleet']);
$sheet1->setCellValue("I$rowIdx", $totales['efecticard']);
$sheet1->setCellValue("J$rowIdx", $totales['sodexo']);
$sheet1->setCellValue("K$rowIdx", $totales['ultragas']);
$sheet1->setCellValue("L$rowIdx", $totales['energex']);
$sheet1->setCellValue("M$rowIdx", $totales['shell']);
$sheet1->setCellValue("N$rowIdx", $totales['total_tarjetas']);
$sheet1->setCellValue("O$rowIdx", $totales['vale_accord']);
$sheet1->setCellValue("P$rowIdx", $totales['vale_efectivale']);
$sheet1->setCellValue("Q$rowIdx", $totales['vale_sodexo']);
$sheet1->setCellValue("R$rowIdx", $totales['si_vale']);
$sheet1->setCellValue("S$rowIdx", $totales['total_vales']);
$sheet1->setCellValue("T$rowIdx", $totales['credito_pago']);
$sheet1->setCellValue("U$rowIdx", $totales['credito_consumo']);
$sheet1->setCellValue("V$rowIdx", $totales['debito_pago']);
$sheet1->setCellValue("W$rowIdx", $totales['debito_consumo']);
$sheet1->setCellValue("X$rowIdx", $totales['total_pago']);
$sheet1->setCellValue("Y$rowIdx", $totales['total_consumo']);
if ($verProsegur) {
$sheet1->setCellValue("Z$rowIdx", $totales['billete_matutino']);
$sheet1->setCellValue("AA$rowIdx", $totales['billete_vespertino']);
$sheet1->setCellValue("AB$rowIdx", $totales['billete_nocturno']);
$sheet1->setCellValue("AC$rowIdx", $totales['morralla']);
$sheet1->setCellValue("AD$rowIdx", $totales['deposito_bancario']);
$sheet1->setCellValue("AE$rowIdx", $totales['cheque1']);
$sheet1->setCellValue("AF$rowIdx", $totales['transferencia1']);
$sheet1->setCellValue("AG$rowIdx", $totales['cheque2']);
$sheet1->setCellValue("AH$rowIdx", $totales['transferencia2']);
$sheet1->setCellValue("AI$rowIdx", $totales['total_prosegur']);
}

// Keep the export columns in the same order as the on-screen table.
$lastColumn = $verProsegur ? 'AI' : 'Y';
$sheet1->getStyle("A3:{$lastColumn}3")->getFont()->setBold(true);
$sheet1->getStyle("A3:{$lastColumn}3")->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
$sheet1->getStyle("A1:{$lastColumn}2")->getAlignment()->setHorizontal('center')->setVertical('center');
$sheet1->getStyle("B4:{$lastColumn}{$rowIdx}")->getNumberFormat()->setFormatCode('$#,##0.00;[Red]-$#,##0.00');
$sheet1->getStyle("A{$rowIdx}:{$lastColumn}{$rowIdx}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet1->getStyle("A{$rowIdx}:{$lastColumn}{$rowIdx}")->getFill()->setFillType(Fill::FILL_SOLID);
$sheet1->getStyle("A{$rowIdx}:{$lastColumn}{$rowIdx}")->getFill()->getStartColor()->setRGB('343A40');
$sheet1->getStyle("A{$rowIdx}:{$lastColumn}{$rowIdx}")->getAlignment()->setVertical('center');
$sheet1->freezePane('A4');

// Column auto-size
$columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y'];
if ($verProsegur) {
$columns = array_merge($columns, ['Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI']);
}
foreach ($columns as $col) {
$sheet1->getColumnDimension($col)->setAutoSize(true);
}

$mesFormateado = nombremes(sprintf('%02d', $idMes));
$mesNombreArchivo = preg_replace('/[^A-Za-z0-9]+/', '_', $mesFormateado);
$mesNombreArchivo = trim((string) $mesNombreArchivo, '_');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Resumen_Monedero_' . $estacionNombreArchivo . '_' . $mesNombreArchivo . '_' . $idYear . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
}
}

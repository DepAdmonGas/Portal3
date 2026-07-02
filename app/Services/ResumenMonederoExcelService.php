<?php

namespace App\Services;
use App\Core\Auth;
use App\Models\Operativo\CorteYear;
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
$totales = $data['totales'];

$estacion = CorteYear::where('id_estacion', $idEstacion)
->where('year', $idYear)->first()?->estacion?->nombre ?? 'Estacion' . $idEstacion;

$spreadsheet = new Spreadsheet();
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Resumen Monedero');

// Row 1
$sheet1->setCellValue('A1', 'MONEDEROS');
$sheet1->mergeCells('A1:R1');
$sheet1->setCellValue('S1', 'CRÉDITO');
$sheet1->mergeCells('S1:T1');
$sheet1->setCellValue('U1', 'DÉBITO');
$sheet1->mergeCells('U1:V1');
$sheet1->setCellValue('W1', 'PAGOS');
$sheet1->setCellValue('X1', 'CONSUMOS');

$sheet1->getStyle('A1:X1')->getFill()->setFillType(Fill::FILL_SOLID);
$sheet1->getStyle('A1:X1')->getFill()->getStartColor()->setRGB('215d98');
$sheet1->getStyle('A1:X1')->getFont()->getColor()->setRGB('FFFFFF');

if ($verProsegur) {
$sheet1->setCellValue('Y1', '');
$sheet1->mergeCells('Y1:AH1');
$sheet1->getStyle('A1:AH1')->getFill()->setFillType(Fill::FILL_SOLID);
$sheet1->getStyle('A1:AH1')->getFill()->getStartColor()->setRGB('215d98');
$sheet1->getStyle('A1:AH1')->getFont()->getColor()->setRGB('FFFFFF');
}

// Row 2
$sheet1->setCellValue('A2', '');
$sheet1->setCellValue('B2', 'TARJETAS BANCARIAS');
$sheet1->mergeCells('B2:E2');
$sheet1->setCellValue('F2', 'TARJETAS');
$sheet1->mergeCells('F2:M2');
$sheet1->setCellValue('N2', 'VALES');
$sheet1->mergeCells('N2:R2');
$sheet1->setCellValue('S2', 'CARTERA DE CLIENTES ATIO');
$sheet1->mergeCells('S2:X2');

$sheet1->getStyle('A2:X2')->getFill()->setFillType(Fill::FILL_SOLID);
$sheet1->getStyle('A2:X2')->getFill()->getStartColor()->setRGB('749ABF');
$sheet1->getStyle('A2:X2')->getFont()->getColor()->setRGB('FFFFFF');

if ($verProsegur) {
$sheet1->setCellValue('Y2', 'PROSEGUR');
$sheet1->mergeCells('Y2:AH2');
$sheet1->getStyle('A2:AH2')->getFill()->setFillType(Fill::FILL_SOLID);
$sheet1->getStyle('A2:AH2')->getFill()->getStartColor()->setRGB('749ABF');
$sheet1->getStyle('A2:AH2')->getFont()->getColor()->setRGB('FFFFFF');
}

// Row 3
$sheet1->setCellValue('A3', 'FECHA');
$sheet1->setCellValue('B3', 'BANCOMER');
$sheet1->setCellValue('C3', 'AMEX');
$sheet1->setCellValue('D3', 'INBURSA');
$sheet1->setCellValue('E3', 'TOTAL');
$sheet1->setCellValue('F3', 'INBURGAS');
$sheet1->setCellValue('G3', 'EDENRED');
$sheet1->setCellValue('H3', 'EFECTIVALE');
$sheet1->setCellValue('I3', 'SODEXO');
$sheet1->setCellValue('J3', 'ULTRAGAS');
$sheet1->setCellValue('K3', 'ENERGEX');
$sheet1->setCellValue('L3', 'SHELL');
$sheet1->setCellValue('M3', 'TOTAL');
$sheet1->setCellValue('N3', 'VALE ACCORD');
$sheet1->setCellValue('O3', 'VALE EFECTIVALE');
$sheet1->setCellValue('P3', 'VALE SODEXO');
$sheet1->setCellValue('Q3', 'SI VALE');
$sheet1->setCellValue('R3', 'TOTAL');
$sheet1->setCellValue('S3', 'PAGOS');
$sheet1->setCellValue('T3', 'CONSUMOS');
$sheet1->setCellValue('U3', 'PAGOS');
$sheet1->setCellValue('V3', 'CONSUMOS');
$sheet1->setCellValue('W3', 'TOTAL');
$sheet1->setCellValue('X3', 'TOTAL');

if ($verProsegur) {
$sheet1->setCellValue('Y3', 'BILLETE MATUTINO');
$sheet1->setCellValue('Z3', 'BILLETE VESPERTINO');
$sheet1->setCellValue('AA3', 'BILLETE NOCTURNO');
$sheet1->setCellValue('AB3', 'MORRALLA');
$sheet1->setCellValue('AC3', 'DEPOSITO BANCARIO');
$sheet1->setCellValue('AD3', 'CHEQUE 1');
$sheet1->setCellValue('AE3', 'TRANSFERENCIA 1');
$sheet1->setCellValue('AF3', 'CHEQUE 2');
$sheet1->setCellValue('AG3', 'TRANSFERENCIA 2');
$sheet1->setCellValue('AH3', 'TOTAL');
}

// Data rows
$rowIdx = 4;
foreach ($rows as $r) {
$sheet1->setCellValue("A$rowIdx", $r['fecha']);
$sheet1->setCellValue("B$rowIdx", $r['bancomer']);
$sheet1->setCellValue("C$rowIdx", $r['amex']);
$sheet1->setCellValue("D$rowIdx", $r['inbursa']);
$sheet1->setCellValue("E$rowIdx", $r['total_tb']);
$sheet1->setCellValue("F$rowIdx", $r['inburgas']);
$sheet1->setCellValue("G$rowIdx", $r['ticketcard']);
$sheet1->setCellValue("H$rowIdx", $r['efecticard']);
$sheet1->setCellValue("I$rowIdx", $r['sodexo']);
$sheet1->setCellValue("J$rowIdx", $r['ultragas']);
$sheet1->setCellValue("K$rowIdx", $r['energex']);
$sheet1->setCellValue("L$rowIdx", $r['shell']);
$sheet1->setCellValue("M$rowIdx", $r['total_tarjetas']);
$sheet1->setCellValue("N$rowIdx", $r['vale_accord']);
$sheet1->setCellValue("O$rowIdx", $r['vale_efectivale']);
$sheet1->setCellValue("P$rowIdx", $r['vale_sodexo']);
$sheet1->setCellValue("Q$rowIdx", $r['si_vale']);
$sheet1->setCellValue("R$rowIdx", $r['total_vales']);
$sheet1->setCellValue("S$rowIdx", $r['credito_pago']);
$sheet1->setCellValue("T$rowIdx", $r['credito_consumo']);
$sheet1->setCellValue("U$rowIdx", $r['debito_pago']);
$sheet1->setCellValue("V$rowIdx", $r['debito_consumo']);
$sheet1->setCellValue("W$rowIdx", $r['total_pago']);
$sheet1->setCellValue("X$rowIdx", $r['total_consumo']);
if ($verProsegur) {
$sheet1->setCellValue("Y$rowIdx", $r['billete_matutino']);
$sheet1->setCellValue("Z$rowIdx", $r['billete_vespertino']);
$sheet1->setCellValue("AA$rowIdx", $r['billete_nocturno']);
$sheet1->setCellValue("AB$rowIdx", $r['morralla']);
$sheet1->setCellValue("AC$rowIdx", $r['deposito_bancario']);
$sheet1->setCellValue("AD$rowIdx", $r['cheque1']);
$sheet1->setCellValue("AE$rowIdx", $r['transferencia1']);
$sheet1->setCellValue("AF$rowIdx", $r['cheque2']);
$sheet1->setCellValue("AG$rowIdx", $r['transferencia2']);
$sheet1->setCellValue("AH$rowIdx", $r['total_prosegur']);
}
$rowIdx++;
}

// Totals row (same as old: A empty)
$sheet1->setCellValue("A$rowIdx", '');
$sheet1->setCellValue("B$rowIdx", $totales['bancomer']);
$sheet1->setCellValue("C$rowIdx", $totales['amex']);
$sheet1->setCellValue("D$rowIdx", $totales['inbursa']);
$sheet1->setCellValue("E$rowIdx", $totales['total_tb']);
$sheet1->setCellValue("F$rowIdx", $totales['inburgas']);
$sheet1->setCellValue("G$rowIdx", $totales['ticketcard']);
$sheet1->setCellValue("H$rowIdx", $totales['efecticard']);
$sheet1->setCellValue("I$rowIdx", $totales['sodexo']);
$sheet1->setCellValue("J$rowIdx", $totales['ultragas']);
$sheet1->setCellValue("K$rowIdx", $totales['energex']);
$sheet1->setCellValue("L$rowIdx", $totales['shell']);
$sheet1->setCellValue("M$rowIdx", $totales['total_tarjetas']);
$sheet1->setCellValue("N$rowIdx", $totales['vale_accord']);
$sheet1->setCellValue("O$rowIdx", $totales['vale_efectivale']);
$sheet1->setCellValue("P$rowIdx", $totales['vale_sodexo']);
$sheet1->setCellValue("Q$rowIdx", $totales['si_vale']);
$sheet1->setCellValue("R$rowIdx", $totales['total_vales']);
$sheet1->setCellValue("S$rowIdx", $totales['credito_pago']);
$sheet1->setCellValue("T$rowIdx", $totales['credito_consumo']);
$sheet1->setCellValue("U$rowIdx", $totales['debito_pago']);
$sheet1->setCellValue("V$rowIdx", $totales['debito_consumo']);
$sheet1->setCellValue("W$rowIdx", $totales['total_pago']);
$sheet1->setCellValue("X$rowIdx", $totales['total_consumo']);
if ($verProsegur) {
$sheet1->setCellValue("Y$rowIdx", $totales['billete_matutino']);
$sheet1->setCellValue("Z$rowIdx", $totales['billete_vespertino']);
$sheet1->setCellValue("AA$rowIdx", $totales['billete_nocturno']);
$sheet1->setCellValue("AB$rowIdx", $totales['morralla']);
$sheet1->setCellValue("AC$rowIdx", $totales['deposito_bancario']);
$sheet1->setCellValue("AD$rowIdx", $totales['cheque1']);
$sheet1->setCellValue("AE$rowIdx", $totales['transferencia1']);
$sheet1->setCellValue("AF$rowIdx", $totales['cheque2']);
$sheet1->setCellValue("AG$rowIdx", $totales['transferencia2']);
$sheet1->setCellValue("AH$rowIdx", $totales['total_prosegur']);
}

// Column auto-size
$columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X'];
if ($verProsegur) {
$columns = array_merge($columns, ['Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH']);
}
foreach ($columns as $col) {
$sheet1->getColumnDimension($col)->setAutoSize(true);
}

$mesFormateado = nombremes(sprintf('%02d', $idMes));
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Resumen_Monedero_' . $estacion . '_' . $mesFormateado . '_' . $idYear . '.xlsx"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
}
}

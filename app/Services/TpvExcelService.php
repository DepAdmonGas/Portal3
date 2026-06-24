<?php

namespace App\Services;

use App\Models\Operativo\CierreLote;
use App\Models\Estacion;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TpvExcelService
{
public static function generarYDescargar(int $idEstacion, int $idYear, int $idMes): void
{
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : '';

$empresas = [
'TICKETCARD',
'G500 FLETT',
'EFECTICARD',
'SODEXO',
'ULTRAGAS'
];

if ($idEstacion === 2 || $idEstacion === 14) {
$empresas[] = 'SHELL FLEET NAVIGATOR';
}

$spreadsheet = new Spreadsheet();
$primeraHoja = true;

foreach ($empresas as $empresa) {
if ($primeraHoja) {
$sheet = $spreadsheet->getActiveSheet();
$primeraHoja = false;
} else {
$sheet = $spreadsheet->createSheet();
}

$nombrePestana = ($empresa === 'G500 FLETT')
? 'TICKETCARD+'
: $empresa;

$sheet->setTitle(substr($nombrePestana, 0, 31));

$sheet->mergeCells('A1:C1');
$sheet->setCellValue('A1', $nombrePestana);

$sheet->getStyle('A1:C1')->getFill()
->setFillType(Fill::FILL_SOLID)
->getStartColor()->setRGB('749abf');

$sheet->getStyle('A1:C1')->getFont()
->setBold(true)
->getColor()->setRGB('FFFFFF');

$sheet->getStyle('A1:C1')->getAlignment()
->setHorizontal(Alignment::HORIZONTAL_CENTER);

$row = 2;

$sheet->setCellValue("A$row", 'Fecha');
$sheet->setCellValue("B$row", 'Importe');
$sheet->setCellValue("C$row", 'No. Tickets');

$sheet->getStyle("A$row:C$row")->getFill()
->setFillType(Fill::FILL_SOLID)
->getStartColor()->setRGB('749abf');

$sheet->getStyle("A$row:C$row")->getFont()
->setBold(true)
->getColor()->setRGB('FFFFFF');

$row++;

$query = CierreLote::join('op_corte_dia', 'op_cierre_lote.idreporte_dia', '=', 'op_corte_dia.id')
->join('op_corte_mes', 'op_corte_dia.id_mes', '=', 'op_corte_mes.id')
->join('op_corte_year', 'op_corte_mes.id_year', '=', 'op_corte_year.id')
->where('op_cierre_lote.empresa', $empresa)
->where('op_corte_year.id_estacion', $idEstacion);

if ($idYear !== 99) {
$query->where('op_corte_year.year', $idYear);
}

if ($idMes !== 99) {
$query->where('op_corte_mes.mes', $idMes);
}

$rows = $query->selectRaw('op_corte_dia.fecha, SUM(op_cierre_lote.importe) as total_importe, SUM(op_cierre_lote.ticktes) as total_tickets')
->groupBy('op_corte_dia.fecha')
->orderBy('op_corte_dia.fecha')
->get();

$TotalImporte = 0;
$TotalTicket = 0;

if ($rows->isNotEmpty()) {
foreach ($rows as $dato) {
$fecha = date('d-m-Y', strtotime($dato->fecha));
$importe = $dato->total_importe;
$tickets = $dato->total_tickets;

$sheet->setCellValue("A$row", $fecha);
$sheet->setCellValue("B$row", $importe);
$sheet->setCellValue("C$row", $tickets);

$sheet->getStyle("B$row")
->getNumberFormat()
->setFormatCode('"$"#,##0.00');

$sheet->getStyle("C$row")
->getNumberFormat()
->setFormatCode('#,##0');

$TotalImporte += $importe;
$TotalTicket += $tickets;

$row++;
}
} else {
$sheet->mergeCells("A$row:C$row");
$sheet->setCellValue("A$row", "No se encontró información");
$row++;
}

$sheet->setCellValue("A$row", 'TOTAL');
$sheet->setCellValue("B$row", $TotalImporte);
$sheet->setCellValue("C$row", $TotalTicket);

$sheet->getStyle("A$row:C$row")->getFont()->setBold(true);

$sheet->getStyle("B$row")
->getNumberFormat()
->setFormatCode('"$"#,##0.00');

$sheet->getStyle("C$row")
->getNumberFormat()
->setFormatCode('#,##0');

foreach (range('A', 'C') as $column) {
$sheet->getColumnDimension($column)->setAutoSize(true);
}

$sheet->getStyle("A1:C$row")->getAlignment()
->setVertical(Alignment::VERTICAL_CENTER);

$sheet->getStyle("A1:A$row")->getAlignment()
->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("C1:C$row")->getAlignment()
->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle("B1:B$row")->getAlignment()
->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$sheet->getStyle("A1:C$row")->getBorders()
->getAllBorders()
->setBorderStyle(Border::BORDER_THIN);
}

$spreadsheet->setActiveSheetIndex(0);

$filtro = '';

if ($idMes !== 99) {
$filtro .= '_' . nombremes(sprintf('%02d', $idMes));
}

if ($idYear !== 99) {
$filtro .= '_' . $idYear;
}

$nombreArchivo = 'Cierre_Lotes_' . $nombreES . $filtro . '.xlsx';

$writer = new Xlsx($spreadsheet);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
}
}

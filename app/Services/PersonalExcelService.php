<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PersonalExcelService
{
private const DOC_CAMPOS = [
'requisicion'      => 'RP',
'curriculum'       => 'CV',
'ine'              => 'IO',
'acta_nacimiento'  => 'AN',
'c_domicilio'      => 'CD',
'nss'              => 'CAI',
'c_estudios'       => 'CE',
'c_recomendacion'  => 'CR',
'curp'             => 'CURP',
'a_infonavit'      => 'ARI',
'rfc'              => 'CSF',
'c_antecedentes'   => 'CANP',
'contrato'         => 'Contrato',
];

public static function generar(): Spreadsheet
{
$data = ControlDocumentosPersonalService::getPersonalList();

$ctx = ModuleStationService::getContext('control-documentos-personal');
$idEstacion = $ctx['id_estacion'];
$idDepto    = $ctx['id_depto'];
$mostrarEstacion = ($idEstacion === null && $idDepto === null);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Personal Activo');

$headers = self::buildHeaders($mostrarEstacion);

$fila = 1;
foreach ($headers as $col => $header) {
$cell = Coordinate::stringFromColumnIndex($col + 1);
$sheet->setCellValue($cell . $fila, $header);
}

$lastCol = Coordinate::stringFromColumnIndex(count($headers));
self::estiloEncabezado($sheet, $fila, $lastCol);

$fila = 2;
foreach ($data as $row) {
self::escribirFila($sheet, $fila, $row, $mostrarEstacion);
$fila++;
}

$lastDataRow = $fila - 1;

for ($c = 1; $c <= count($headers); $c++) {
$colLetter = Coordinate::stringFromColumnIndex($c);
$sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

if ($lastDataRow >= 2) {
$dataRange = 'A2:' . $lastCol . $lastDataRow;

$sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle($dataRange)->getBorders()->applyFromArray([
'top'    => ['borderType' => Border::BORDER_THIN],
'bottom' => ['borderType' => Border::BORDER_THIN],
'left'   => ['borderType' => Border::BORDER_THIN],
'right'  => ['borderType' => Border::BORDER_THIN],
]);

$sdColIndex = $mostrarEstacion ? 7 : 6;
$sdCol = Coordinate::stringFromColumnIndex($sdColIndex);
$sheet->getStyle($sdCol . '2:' . $sdCol . $lastDataRow)
->getNumberFormat()
->setFormatCode('"$"#,##0.00');

$nombreColIndex = $mostrarEstacion ? 5 : 4;
$nombreCol = Coordinate::stringFromColumnIndex($nombreColIndex);
$sheet->getStyle($nombreCol . '2:' . $nombreCol . $lastDataRow)
->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

return $spreadsheet;
}

public static function generarYDescargar(): void
{
$spreadsheet = self::generar();

$writer = new Xlsx($spreadsheet);

$ctx = ModuleStationService::getContext('control-documentos-personal');
$estacion = $ctx['nombre'] ?? '';
$fecha    = date('Y-m-d');

$nombreArchivo = 'Control_Documentos_Personal';
if ($estacion) {
$nombreArchivo .= '_' . $estacion;
}
$nombreArchivo .= '_' . $fecha . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
}

private static function buildHeaders(bool $mostrarEstacion): array
{
$headers = ['#'];
if ($mostrarEstacion) {
$headers[] = 'Estación / Departamento';
}
$headers[] = 'Fecha de ingreso';
$headers[] = 'No. Colaborador';
$headers[] = 'Nombre completo';
$headers[] = 'Puesto';
$headers[] = 'SD';
foreach (self::DOC_CAMPOS as $abbr) {
$headers[] = $abbr;
}
$headers[] = 'Estatus';
return $headers;
}

private static function estiloEncabezado($sheet, int $fila, string $lastCol): void
{
$range = 'A' . $fila . ':' . $lastCol . $fila;
$sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID);
$sheet->getStyle($range)->getFill()->getStartColor()->setRGB('749ABF');
$sheet->getStyle($range)->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($range)->getFont()->setBold(true);
$sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle($range)->getBorders()->applyFromArray([
'top'    => ['borderType' => Border::BORDER_THIN],
'bottom' => ['borderType' => Border::BORDER_THIN],
'left'   => ['borderType' => Border::BORDER_THIN],
'right'  => ['borderType' => Border::BORDER_THIN],
]);
}

private static function escribirFila($sheet, int $fila, array $row, bool $mostrarEstacion): void
{
$col = 1;

$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $fila, (int)$row['id']);
$sheet->getStyle(Coordinate::stringFromColumnIndex($col - 1) . $fila)
->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

if ($mostrarEstacion) {
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $fila, $row['nombre_estacion'] ?? '');
$sheet->getStyle(Coordinate::stringFromColumnIndex($col - 1) . $fila)
->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $fila, $row['fecha_ingreso_format'] ?? '');
$sheet->getStyle(Coordinate::stringFromColumnIndex($col - 1) . $fila)
->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $fila, $row['no_colaborador'] ?? '');
$sheet->getStyle(Coordinate::stringFromColumnIndex($col - 1) . $fila)
->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $fila, $row['nombre_completo'] ?? '');

$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $fila, $row['puesto'] ?? '');
$sheet->getStyle(Coordinate::stringFromColumnIndex($col - 1) . $fila)
->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $fila, (float)($row['sd'] ?? 0));
$sheet->getStyle(Coordinate::stringFromColumnIndex($col - 1) . $fila)
->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

foreach (array_keys(self::DOC_CAMPOS) as $campo) {
$valor = !empty($row['documentos'][$campo]) ? 'Sí' : 'No';
$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $fila, $valor);
$sheet->getStyle(Coordinate::stringFromColumnIndex($col - 1) . $fila)
->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

$sheet->setCellValue(Coordinate::stringFromColumnIndex($col++) . $fila, $row['estatus'] ?? '');
$sheet->getStyle(Coordinate::stringFromColumnIndex($col - 1) . $fila)
->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}
}

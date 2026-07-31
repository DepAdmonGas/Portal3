<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\FacturaMonederoService;
use App\Services\ModuleStationService;
use App\Services\DropdownYearMesService;
use App\Models\Estacion;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Dompdf\Dompdf;
use Dompdf\Options;

class FacturaMonederoController extends BaseController
{
protected string $modulo = 'corporativo';

public function redirect()
{
$validados = DropdownYearMesService::validarYearMes(0, 0);
header('Location: /departamento-operativo/corporativo/factura-monedero/' . $validados['idYear'] . '/' . $validados['idMes']);
exit;
}

public function index(int $idYear = 0, int $idMes = 0)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = FacturaMonederoService::getPermisos();
$esMultiestacion = $permisos['multiestacion'];
$idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

$pendientesData = FacturaMonederoService::getPendientes($idYear, $idMes);

$title = 'Factura Monedero (' . nombremes($idMes) . ' ' . $idYear . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add($title, '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

if (!$this->guardModuleAccess('factura-monedero', $title, 'departamento-operativo')) {
return;
}

$yearMesTemplate = '/departamento-operativo/corporativo/factura-monedero/{year}/{mes}';

View::render('departamento-operativo/1-corporativo/factura-monedero/index', [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'multiestacion' => $esMultiestacion,
'yearMesTemplate' => $yearMesTemplate,
'moduleStationKey' => 'factura-monedero',
'puedeCrear' => $permisos['puedeCrear'],
'puedeEditar' => $permisos['puedeEditar'],
'puedeEliminar' => $permisos['puedeEliminar'],
'pendientesData' => $pendientesData,
'help' => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/factura-monedero.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/factura-monedero.actions.init.js?v=' . time(),
],
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
], 'departamento-operativo');
}

public function getData()
{
$data = $this->getExportData();

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'data' => $data]);
exit;
}

private function getExportData(): array
{
$idYear = (int)($_GET['year'] ?? 0);
$idMes = (int)($_GET['mes'] ?? 0);
$estacionFilter = isset($_GET['id_estacion']) ? (int)$_GET['id_estacion'] : null;
if ($estacionFilter !== null && $estacionFilter === 0) $estacionFilter = null;

if (!$idYear || !$idMes) {
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];
}

if ($estacionFilter === null) {
$ctx = ModuleStationService::getContext('factura-monedero');
$estacionFilter = $ctx['id_estacion'] ?? null;
}

return FacturaMonederoService::getData($idYear, $idMes, $estacionFilter);
}

public function getDetalle(int $id)
{
header('Content-Type: application/json; charset=utf-8');
try {
$detalle = FacturaMonederoService::getDetalle($id);
if (!$detalle) {
echo json_encode(['success' => false, 'message' => 'Registro no encontrado.']);
exit;
}
echo json_encode(['success' => true, 'data' => $detalle]);
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
}

public function add()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = FacturaMonederoService::getPermisos();

/*
if (!$permisos['puedeCrear']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos para agregar.']);
exit;
}
*/

$id = FacturaMonederoService::add($_POST + $_FILES);
echo json_encode(['success' => (bool)$id, 'message' => $id ? 'Registro creado exitosamente.' : 'Error al crear el registro.']);
exit;
}

public function edit()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = FacturaMonederoService::getPermisos();
/*
if (!$permisos['puedeEditar']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos para editar.']);
exit;
}
*/

$input = $_POST;
foreach (['archivo_factura', 'archivo_comprobante_pago', 'archivo_factura_xml'] as $f) {
if (isset($_FILES[$f]) && $_FILES[$f]['error'] !== UPLOAD_ERR_NO_FILE) {
$input[$f] = $_FILES[$f];
}
}
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
exit;
}

$updated = FacturaMonederoService::update($id, $input);
echo json_encode(['success' => $updated, 'message' => $updated ? 'Registro actualizado exitosamente.' : 'Error al actualizar el registro.']);
exit;
}

public function delete()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = FacturaMonederoService::getPermisos();

/*
if (!$permisos['puedeEliminar']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar.']);
exit;
}
*/

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
exit;
}

$deleted = FacturaMonederoService::delete($id);
echo json_encode(['success' => (bool)$deleted, 'message' => $deleted ? 'Registro eliminado exitosamente.' : 'Error al eliminar el registro.']);
exit;
}

public function getPendientesData()
{
header('Content-Type: application/json; charset=utf-8');
$idYear = (int)($_GET['year'] ?? 0);
$idMes = (int)($_GET['mes'] ?? 0);
$data = FacturaMonederoService::getPendientes($idYear, $idMes);
echo json_encode(['success' => true, 'data' => $data]);
exit;
}

public function getComentarios()
{
header('Content-Type: application/json; charset=utf-8');
$idFactura = (int)($_GET['id'] ?? 0);
if (!$idFactura) { echo json_encode(['success' => false, 'data' => []]); exit; }
$data = FacturaMonederoService::getComentarios($idFactura);
echo json_encode(['success' => true, 'data' => $data]);
exit;
}

public function addComentario()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = FacturaMonederoService::getPermisos();
$input = json_decode(file_get_contents('php://input'), true);
$idFactura = (int)($input['id'] ?? 0);
$comentario = trim($input['comentario'] ?? '');

if (!$idFactura || !$comentario) {
echo json_encode(['success' => false, 'message' => 'Campos requeridos']);
exit;
}

$saved = FacturaMonederoService::addComentario($idFactura, $comentario, $permisos['id_usuario']);
echo json_encode(['success' => $saved, 'message' => $saved ? 'Comentario agregado exitosamente.' : 'Error al agregar el comentario.']);
exit;
}

public function descargarExcel(int $idYear, int $idMes, int $idEstacion)
{
$data = FacturaMonederoService::getData($idYear, $idMes, $idEstacion);

$est = Estacion::find($idEstacion);
$nombreES = $est ? $est->nombre : '';
$mesNombre = nombremes($idMes);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Factura Monedero');

$headers = ['#', 'Folio', 'Fecha', 'No. Factura', 'Monto', 'Estado'];
$col = 'A';
foreach ($headers as $h) {
$sheet->setCellValue($col . '1', $h);
$sheet->getStyle($col . '1')->getFill()->setFillType(Fill::FILL_SOLID);
$sheet->getStyle($col . '1')->getFill()->getStartColor()->setRGB('215d98');
$sheet->getStyle($col . '1')->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle($col . '1')->getFont()->setBold(true);
$sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$col++;
}

$row = 2;
$num = 1;
$totalMonto = 0;

if (!empty($data)) {
foreach ($data as $d) {
$estadoTexto = $d['estado'] == 1 ? 'Finalizado' : 'Pendiente';

$sheet->setCellValue('A' . $row, $num);
$sheet->setCellValue('B' . $row, $d['folio_display'] ?? '');
$sheet->setCellValue('C' . $row, $d['fecha_creacion_format'] ?? '');
$sheet->setCellValue('D' . $row, $d['no_factura'] ?? '');
$sheet->setCellValue('E' . $row, $d['monto'] ?? 0);
$sheet->setCellValue('F' . $row, $estadoTexto);

$sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD);
$sheet->getStyle('A' . $row . ':F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

if ($d['estado'] == 1) {
$sheet->getStyle('F' . $row)->getFont()->getColor()->setRGB('008000');
} else {
$sheet->getStyle('F' . $row)->getFont()->getColor()->setRGB('CC6600');
}

$totalMonto += (float)($d['monto'] ?? 0);
$num++;
$row++;
}

$sheet->setCellValue('D' . $row, 'Total:');
$sheet->setCellValue('E' . $row, $totalMonto);
$sheet->getStyle('D' . $row . ':E' . $row)->getFont()->setBold(true);
$sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_CURRENCY_USD);
$sheet->getStyle('D' . $row . ':E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
} else {
$sheet->setCellValue('A2', 'Sin información disponible para este mes');
$sheet->mergeCells('A2:F2');
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A2')->getFont()->setItalic(true);
}

foreach (range('A', 'F') as $c) {
$sheet->getColumnDimension($c)->setAutoSize(true);
}

$filename = "Factura_Monedero_{$nombreES}_{$mesNombre}_{$idYear}.xlsx";

$writer = new Xlsx($spreadsheet);
ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
}

public function downloadPdf(int $idYear, int $idMes, int $idEstacion)
{
$data = FacturaMonederoService::getData($idYear, $idMes, $idEstacion);

$est = Estacion::find($idEstacion);
$nombreES = $est ? $est->nombre : '';
$mesNombre = nombremes($idMes);

$html = '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Factura Monedero - ' . $nombreES . '</title>
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 20px; }
h2 { text-align: center; color: #215d98; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: center; }
th { background-color: #215d98; color: white; font-weight: bold; }
.finalizado { color: green; font-weight: bold; }
.pendiente { color: #cc6600; font-weight: bold; }
.total-row { font-weight: bold; background-color: #f0f0f0; }
</style>
</head>
<body>
<h2>Factura Monedero - ' . $nombreES . ' (' . $mesNombre . ' ' . $idYear . ')</h2>
<table>
<thead>
<tr>
<th>#</th>
<th>Folio</th>
<th>Fecha</th>
<th>No. Factura</th>
<th>Monto</th>
<th>Estado</th>
</tr>
</thead>
<tbody>' . "\n";

$num = 1;
$totalMonto = 0;

if (!empty($data)) {
foreach ($data as $d) {
$estadoTexto = $d['estado'] == 1 ? 'Finalizado' : 'Pendiente';
$estadoClase = $d['estado'] == 1 ? 'finalizado' : 'pendiente';
$monto = '$' . number_format((float)($d['monto'] ?? 0), 2);

$html .= '<tr>
<td>' . $num . '</td>
<td>' . ($d['folio_display'] ?? '') . '</td>
<td>' . ($d['fecha_creacion_format'] ?? '') . '</td>
<td>' . ($d['no_factura'] ?? '') . '</td>
<td>' . $monto . '</td>
<td class="' . $estadoClase . '">' . $estadoTexto . '</td>
</tr>' . "\n";

$totalMonto += (float)($d['monto'] ?? 0);
$num++;
}

$html .= '<tr class="total-row">
<td colspan="4">Total</td>
<td colspan="2">$' . number_format($totalMonto, 2) . '</td>
</tr>' . "\n";
} else {
$html .= '<tr>
<td colspan="6" style="text-align:center;font-style:italic;">Sin informaci&oacute;n disponible para este mes</td>
</tr>' . "\n";
}

$html .= '</tbody>
</table>
</body>
</html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$filename = 'Factura_Monedero_' . $nombreES . '_' . $mesNombre . '_' . $idYear . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
}
}

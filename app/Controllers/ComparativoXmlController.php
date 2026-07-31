<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ComparativoXmlService;
use App\Services\ModuleStationService;
use App\Services\ModuloDptoOperativoService;
use App\Services\DropdownYearMesService;
use App\Models\Operativo\ComparativoExcel;
use App\Models\Operativo\ComparativoExcelSat;
use App\Models\Estacion;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ComparativoXmlController extends BaseController
{
protected string $modulo = 'corporativo';

public function redirect()
{
$idYear = date('Y');
header("Location: /departamento-operativo/corporativo/comparativo-xml/{$idYear}");
exit;
}

public function index(int $idYear)
{
$validados = DropdownYearMesService::validarYearMes($idYear, 1);
$idYear = $validados['idYear'];

$moduleCtx = ModuleStationService::getContext('comparativo-xml');
$idEstacion = $moduleCtx['id_estacion'];

if ($idEstacion) {
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') ||
ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}
}

$title = 'Comparativo XML ' . $idYear;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');
Breadcrumb::add(self::dropdownYear($idYear), '');

if (!$this->guardModuleAccess('comparativo-xml', $title, 'departamento-operativo')) {
return;
}

View::render('departamento-operativo/1-corporativo/comparativo-xml/index', [
'title' => $title,
'idYear' => $idYear,
'idEstacion' => $idEstacion ?: 0,
'moduleStationKey' => 'comparativo-xml',
'help' => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/js/core/module-station-selector.js?v=' . time(),
'https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js',
'/assets/js/departamento-operativo/1-corporativo/comparativo-xml.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');

$moduleCtx = ModuleStationService::getContext('comparativo-xml');
$idEstacion = (int)($moduleCtx['id_estacion'] ?? 0);
$idYear = (int)($_GET['year'] ?? date('Y'));
$permisos = ComparativoXmlService::getPermisos();

if (!$idEstacion || !$idYear) {
echo json_encode(['success' => false]);
exit;
}

ComparativoXmlService::validarMeses($idEstacion, $idYear);
$rows = ComparativoXmlService::getDataRows($idEstacion, $idYear);
$totales = ComparativoXmlService::getTotales($idEstacion, $idYear);
$campos = ComparativoXmlService::getActiveFields($idEstacion);
$mapaNombres = ComparativoXmlService::getMapaNombres();
$config = ComparativoXmlService::getStationConfig($idEstacion);
$editorContent = ComparativoXmlService::loadObservations($idYear, $idEstacion);
$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : "ES{$idEstacion}";
ComparativoXmlService::logAccess($idEstacion, $idYear, $permisos['id_usuario']);

echo json_encode([
'success' => true,
'rows' => $rows,
'totales' => $totales,
'campos' => $campos,
'mapaNombres' => $mapaNombres,
'config' => $config,
'canEdit' => $permisos['canEdit'],
'idYear' => $idYear,
'idEstacion' => $idEstacion,
'nombreES' => $nombreES,
'esDireccionOperaciones' => $permisos['esDireccionOperaciones'],
'idPuesto' => $permisos['id_puesto'],
'idUsuario' => $permisos['id_usuario'],
'editorContent' => $editorContent,
]);
exit;
}

public function updateCell()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$idCampo = (int)($input['idCampo'] ?? 0);
$descripcion = $input['descripcion'] ?? '';
$idTipo = (int)($input['idTipo'] ?? 0);
$idYear = (int)($input['idYear'] ?? 0);
$idEstacion = (int)($input['idEstacion'] ?? 0);
$idMes = (int)($input['idMes'] ?? 0);
$idSeccion = $input['idSeccion'] ?? '';
$idDescripcion = $input['idDescripcion'] ?? '';
$permisos = ComparativoXmlService::getPermisos();

$success = ComparativoXmlService::updateCell($idCampo, $descripcion, $idTipo, $idEstacion, $idYear, $idMes, $idSeccion, $idDescripcion, $permisos['id_usuario']);

echo json_encode(['success' => $success ? 1 : 0]);
exit;
}

public function updateSatCell()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$idCampo = (int)($input['idCampo'] ?? 0);
$descripcion = $input['descripcion'] ?? '';
$categoria = $input['categoria'] ?? '';
$idTipo = (int)($input['idTipo'] ?? 0);
$idYear = (int)($input['idYear'] ?? 0);
$idEstacion = (int)($input['idEstacion'] ?? 0);
$idMes = (int)($input['idMes'] ?? 0);
$permisos = ComparativoXmlService::getPermisos();

$success = ComparativoXmlService::updateSatCell($idCampo, $descripcion, $categoria, $idTipo, $idYear, $idMes, $idEstacion, $permisos['id_usuario']);

echo json_encode(['success' => $success ? 1 : 0]);
exit;
}

public function getSatData()
{
header('Content-Type: application/json; charset=utf-8');

$moduleCtx = ModuleStationService::getContext('comparativo-xml');
$idEstacion = (int)($moduleCtx['id_estacion'] ?? 0);
$idYear = (int)($_GET['year'] ?? date('Y'));
$permisos = ComparativoXmlService::getPermisos();

if (!$idEstacion || !$idYear) {
echo json_encode(['success' => false]);
exit;
}

ComparativoXmlService::validarSatMeses($idEstacion, $idYear);
$satData = ComparativoXmlService::getSatData($idEstacion, $idYear);

echo json_encode([
'success' => true,
'satData' => $satData,
'canEdit' => $permisos['canEdit'],
]);
exit;
}

public function addComment()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$idEstacion = (int)($input['idEstacion'] ?? 0);
$idYear = (int)($input['idYear'] ?? 0);
$idMes = (int)($input['idMes'] ?? 0);
$comentario = $input['comentario'] ?? '';
$permisos = ComparativoXmlService::getPermisos();

if (!$comentario) {
echo json_encode(['success' => false]);
exit;
}

$success = ComparativoXmlService::addComment($idEstacion, $idYear, $idMes, $permisos['id_usuario'], $comentario);
if ($success) {
ComparativoXmlService::notificarTelegram('agregar_comentario', [
'id_estacion' => $idEstacion,
'year' => $idYear,
'mes' => $idMes,
'id_usuario' => $permisos['id_usuario'],
]);
}

echo json_encode(['success' => $success ? 1 : 0]);
exit;
}

public function getComments()
{
header('Content-Type: application/json; charset=utf-8');

$idEstacion = (int)($_GET['idEstacion'] ?? 0);
$idYear = (int)($_GET['year'] ?? 0);
$idMes = (int)($_GET['mes'] ?? 0);

if (!$idEstacion || !$idYear || !$idMes) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$comments = ComparativoXmlService::getComments($idEstacion, $idYear, $idMes);

echo json_encode(['success' => true, 'data' => $comments]);
exit;
}

public function getDocuments()
{
header('Content-Type: application/json; charset=utf-8');

$idEstacion = (int)($_GET['idEstacion'] ?? 0);
$idYear = (int)($_GET['year'] ?? 0);
$idMes = (int)($_GET['mes'] ?? 0);
$permisos = ComparativoXmlService::getPermisos();

$docs = ComparativoXmlService::getDocuments($idEstacion, $idYear, $idMes);

echo json_encode(['success' => true, 'data' => $docs, 'canEdit' => $permisos['canEdit']]);
exit;
}

public function addDocument()
{
header('Content-Type: application/json; charset=utf-8');

$idEstacion = (int)($_POST['idEstacion'] ?? 0);
$idYear = (int)($_POST['year'] ?? 0);
$idMes = (int)($_POST['mes'] ?? 0);
$anexo = $_POST['Anexos'] ?? '';

if (!$idEstacion || !$idYear || !$idMes || !$anexo) {
echo json_encode(['success' => false, 'message' => 'Campos requeridos']);
exit;
}

$file = $_FILES['Archivo_file'] ?? null;
if (!$file || empty($file['tmp_name'])) {
echo json_encode(['success' => false, 'message' => 'Archivo requerido']);
exit;
}

$aleatorio = uniqid();
$originalName = $file['name'];
$safeName = $aleatorio . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
$uploadDir = __DIR__ . '/../../public/uploads/archivos/comparativo-xml/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

if (!move_uploaded_file($file['tmp_name'], $uploadDir . $safeName)) {
echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
exit;
}

$permisos = ComparativoXmlService::getPermisos();
$id = ComparativoXmlService::addDocument($idEstacion, $idYear, $idMes, $anexo, $safeName);
if ($id) {
ComparativoXmlService::notificarTelegram('agregar_documento', [
'id_estacion' => $idEstacion,
'year' => $idYear,
'mes' => $idMes,
'anexo' => $anexo,
'id_usuario' => $permisos['id_usuario'],
]);
}

echo json_encode(['success' => (bool)$id, 'message' => $id ? 'Documento agregado exitosamente.' : 'Error al subir el archivo.']);
exit;
}

public function deleteDocument()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'El documento no existe.']);
exit;
}

$deleted = ComparativoXmlService::deleteDocument($id);
if ($deleted) {
$filePath = __DIR__ . '/../../public/uploads/archivos/comparativo-xml/' . $deleted['archivo'];
if (file_exists($filePath)) unlink($filePath);

$permisos = ComparativoXmlService::getPermisos();
ComparativoXmlService::notificarTelegram('eliminar_documento', [
'id_estacion' => $deleted['id_estacion'],
'year' => $deleted['year'],
'mes' => $deleted['mes'],
'anexo' => $deleted['anexo'],
'id_usuario' => $permisos['id_usuario'],
]);
}

echo json_encode(['success' => (bool)$deleted, 'message' => $deleted ? 'Documento eliminado exitosamente.' : 'Error al eliminar el documento.']);
exit;
}

public function saveObservations()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$idYear = (int)($input['idYear'] ?? 0);
$idEstacion = (int)($input['idEstacion'] ?? 0);
$content = $input['content'] ?? '';

if (!$idYear || !$idEstacion) {
echo json_encode(['success' => false]);
exit;
}

$permisos = ComparativoXmlService::getPermisos();
$success = ComparativoXmlService::saveObservations($idYear, $idEstacion, $content);
if ($success && !empty($content)) {
ComparativoXmlService::notificarTelegram('agregar_observacion', [
'id_estacion' => $idEstacion,
'year' => $idYear,
'id_usuario' => $permisos['id_usuario'],
]);
}

echo json_encode(['success' => $success ? 1 : 0]);
exit;
}

public function getLogs()
{
header('Content-Type: application/json; charset=utf-8');

$idEstacion = (int)(!empty($_GET['id_estacion']) ? $_GET['id_estacion'] : (ModuleStationService::getContext('comparativo-xml')['id_estacion'] ?? 0));
$idYear = (int)($_GET['year'] ?? date('Y'));

if (!$idEstacion || !$idYear) {
echo json_encode(['success' => false, 'access' => [], 'edits' => [], 'sat_edits' => []]);
exit;
}

echo json_encode([
'success' => true,
'access' => ComparativoXmlService::getAccessLog($idEstacion, $idYear),
'edits' => ComparativoXmlService::getEditLog($idEstacion, $idYear),
'sat_edits' => ComparativoXmlService::getSatEditLog($idEstacion, $idYear),
]);
exit;
}

public function seguimiento(int $idYear, int $idEstacion = 0)
{
if ($idEstacion <= 0) {
$moduleCtx = ModuleStationService::getContext('comparativo-xml');
$idEstacion = (int)($moduleCtx['id_estacion'] ?? 0);
} else {
ModuleStationService::setContext('comparativo-xml', $idEstacion);
}

if (!$idEstacion || !$idYear) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : "ES{$idEstacion}";

$title = 'Seguimiento Comparativo XML ' . $idYear;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Comparativo XML', '/departamento-operativo/corporativo/comparativo-xml/' . $idYear);
Breadcrumb::add('Seguimiento', '');
Breadcrumb::add(self::dropdownYear($idYear, '/departamento-operativo/corporativo/comparativo-xml/seguimiento'), '');

View::render('departamento-operativo/1-corporativo/comparativo-xml/seguimiento', [
'title' => $title,
'idYear' => $idYear,
'idEstacion' => $idEstacion,
'nombreES' => $nombreES,
'moduleStationKey' => 'comparativo-xml',
'ocultarSelectorEstacion' => true,
'help' => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/departamento-operativo/1-corporativo/comparativo-xml.seguimiento.init.js?v=' . time(),
],
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
], 'departamento-operativo');
}

public function descargarExcel(int $idEstacion, int $idYear)
{
set_time_limit(300);
error_reporting(0);

$fields = ComparativoXmlService::getActiveFields($idEstacion);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Comparativo Excel");

$col = 'A';
$sheet->setCellValue($col++ . '1', '#');
$sheet->setCellValue($col++ . '1', 'Mes');
foreach ($fields as $f) {
$headerName = ComparativoXmlService::getMapaNombres()[$f] ?? $f;
$cell = $col . '1';
$sheet->setCellValue($cell, $headerName);
$sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('215d98');
$sheet->getStyle($cell)->getFont()->getColor()->setRGB('FFFFFF');
$col++;
}

$fila = 2;
$sums = array_fill_keys($fields, 0);
for ($idMes = 1; $idMes <= 12; $idMes++) {
$record = ComparativoExcel::where('id_estacion', $idEstacion)
->where('year', $idYear)->where('mes', $idMes)->first();
if (!$record) continue;
$col = 'A';
$sheet->setCellValue($col++ . $fila, $idMes);
$sheet->setCellValue($col++ . $fila, ComparativoXmlService::monthName($idMes));
foreach ($fields as $f) {
$val = (float)($record->$f ?? 0);
$cell = $col++ . $fila;
$sheet->setCellValue($cell, round($val, 2));
$sheet->getStyle($cell)->getNumberFormat()->setFormatCode('"$"#,##0.00');
$sums[$f] += $val;
}
$fila++;
}

$col = 'A';
$sheet->setCellValue($col++ . $fila, '');
$sheet->setCellValue($col++ . $fila, 'TOTAL');
foreach ($fields as $f) {
$cell = $col++ . $fila;
$sheet->setCellValue($cell, round($sums[$f], 2));
$sheet->getStyle($cell)->getNumberFormat()->setFormatCode('"$"#,##0.00');
}

foreach (range('A', 'Z') as $col) {
$sheet->getColumnDimension($col)->setAutoSize(true);
$sheet->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle($col)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}

$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle("Comparativo SAT");

$fila = 1;
$totalAnualSat = 0;
$totalAnualDespacho = 0;

$meses = [
1 => "ENERO", 2 => "FEBRERO", 3 => "MARZO", 4 => "ABRIL",
5 => "MAYO", 6 => "JUNIO", 7 => "JULIO", 8 => "AGOSTO",
9 => "SEPTIEMBRE", 10 => "OCTUBRE", 11 => "NOVIEMBRE", 12 => "DICIEMBRE"
];

foreach ($meses as $mes => $nombreMes) {
$sheet2->setCellValue('A' . $fila, $nombreMes);
$sheet2->mergeCells("A{$fila}:D{$fila}");
$sheet2->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(12);
$sheet2->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$fila++;

$sheet2->setCellValue("A{$fila}", 'CATEGORÍA');
$sheet2->setCellValue("B{$fila}", 'SAT');
$sheet2->setCellValue("C{$fila}", 'DESPACHO');
$sheet2->setCellValue("D{$fila}", 'DIFERENCIA');
$sheet2->getStyle("A{$fila}:D{$fila}")->getFont()->setBold(true);
$sheet2->getStyle("A{$fila}:D{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('215d98');
$sheet2->getStyle("A{$fila}:D{$fila}")->getFont()->getColor()->setRGB('FFFFFF');
$fila++;

$items = ComparativoExcelSat::where('id_estacion', $idEstacion)
->where('year', $idYear)->where('mes', $mes)->get();
$totalMesSat = 0;
$totalMesDespacho = 0;

foreach ($items as $item) {
$sat = (float)$item->sat_monto;
$desp = (float)$item->despacho_monto;
$dif = $sat - $desp;
$sheet2->setCellValue("A{$fila}", $item->categoria);
$sheet2->setCellValue("B{$fila}", round($sat, 2));
$sheet2->setCellValue("C{$fila}", round($desp, 2));
$sheet2->setCellValue("D{$fila}", round($dif, 2));
foreach (['B', 'C', 'D'] as $c) {
$sheet2->getStyle("{$c}{$fila}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
}
$totalMesSat += $sat;
$totalMesDespacho += $desp;
$fila++;
}

$sheet2->setCellValue("A{$fila}", 'TOTAL');
$sheet2->setCellValue("B{$fila}", round($totalMesSat, 2));
$sheet2->setCellValue("C{$fila}", round($totalMesDespacho, 2));
$sheet2->setCellValue("D{$fila}", round($totalMesSat - $totalMesDespacho, 2));
$sheet2->getStyle("A{$fila}:D{$fila}")->getFont()->setBold(true);
foreach (['B', 'C', 'D'] as $c) {
$sheet2->getStyle("{$c}{$fila}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
}
$fila += 2;
$totalAnualSat += $totalMesSat;
$totalAnualDespacho += $totalMesDespacho;
}

$sheet2->setCellValue("A{$fila}", 'RESUMEN ANUAL');
$sheet2->mergeCells("A{$fila}:D{$fila}");
$sheet2->getStyle("A{$fila}")->getFont()->setBold(true)->setSize(12);
$sheet2->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$fila++;

$sheet2->setCellValue("A{$fila}", 'TOTAL ANUAL');
$sheet2->setCellValue("B{$fila}", round($totalAnualSat, 2));
$sheet2->setCellValue("C{$fila}", round($totalAnualDespacho, 2));
$sheet2->setCellValue("D{$fila}", round($totalAnualSat - $totalAnualDespacho, 2));
foreach (['B', 'C', 'D'] as $c) {
$sheet2->getStyle("{$c}{$fila}")->getNumberFormat()->setFormatCode('"$"#,##0.00');
$sheet2->getStyle("{$c}{$fila}")->getFont()->setBold(true);
}

foreach (range('A', 'D') as $col) {
$sheet2->getColumnDimension($col)->setAutoSize(true);
}

$estacion = Estacion::find($idEstacion);
$nombreES = $estacion ? $estacion->nombre : "ES$idEstacion";

$writer = new Xlsx($spreadsheet);
$filename = 'Comparativo_' . $nombreES . '_' . $idYear . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
}

private static function dropdownYear(int $idYear, string $baseUrl = ''): string
{
if (!$baseUrl) {
$baseUrl = '/departamento-operativo/corporativo/comparativo-xml';
}

$yearActual = date('Y');
$yearInicio = 2025;

$html = '
<a class="dropdown-toggle breadcrumb-item active" role="button" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-calendar"></i> <span class="ms-1">' . $idYear . '</span>
</a>
<ul class="dropdown-menu animated rubberBand">';

for ($year = $yearActual; $year >= $yearInicio; $year--) {
$html .= '
<li class="pointer">
<a class="dropdown-item" href="' . $baseUrl . '/' . $year . '">
<i class="ti ti-calendar"></i> <span class="ms-1">' . $year . '</span>
</a>
</li>';
}

$html .= '</ul>';
return $html;
}
}

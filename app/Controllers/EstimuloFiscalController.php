<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\EstimuloFiscalService;
use App\Services\ModuloDptoOperativoService;

class EstimuloFiscalController extends BaseController
{
public function index()
{
$usuario = Session::get('usuario');
$idEstacion = $usuario['id_estacion'] ?? 0;

if (!$idEstacion) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') ||
ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('<span class="breadcrumb-item active">Estímulo Fiscal</span>', '');

View::render('departamento-operativo/1-corporativo/estimulo-fiscal/index', [
'title' => 'Estímulo Fiscal',
'idEstacion' => $idEstacion,
'help' => false,
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
'scripts' => [
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/departamento-operativo/1-corporativo/estimulo-fiscal.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');

$usuario = Session::get('usuario');
$idEstacion = $usuario['id_estacion'] ?? 0;

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Sin estación']);
exit;
}

$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fechaTermino = $_GET['fecha_termino'] ?? date('Y-m-t');

$resumen = EstimuloFiscalService::getResumen($idEstacion, $fechaInicio, $fechaTermino);
$pagos = EstimuloFiscalService::getListaPagos($idEstacion);
$permisos = EstimuloFiscalService::getPermisos();

echo json_encode([
'success' => true,
'resumen' => $resumen,
'pagos' => $pagos,
'permisos' => $permisos,
]);
exit;
}

public function getDetalle()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$detalle = EstimuloFiscalService::getDetalle($id);
if (!$detalle) {
echo json_encode(['success' => false]);
exit;
}

echo json_encode(['success' => true, 'data' => $detalle]);
exit;
}

public function guardar()
{
header('Content-Type: application/json; charset=utf-8');

$usuario = Session::get('usuario');
$idEstacion = $usuario['id_estacion'] ?? 0;

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Sin estación']);
exit;
}

$fechaInicio = $_POST['fecha_inicio'] ?? '';
$fechaTermino = $_POST['fecha_termino'] ?? '';

if (!$fechaInicio || !$fechaTermino) {
echo json_encode(['success' => false, 'message' => 'Fechas requeridas']);
exit;
}

$filePDF = $_FILES['EPDF_file'] ?? null;
$fileXML = $_FILES['EXML_file'] ?? null;

if (!$filePDF || empty($filePDF['tmp_name']) || !$fileXML || empty($fileXML['tmp_name'])) {
echo json_encode(['success' => false, 'message' => 'Archivos PDF y XML requeridos']);
exit;
}

$data = [
'fecha_inicio' => $fechaInicio,
'fecha_termino' => $fechaTermino,
];

$id = EstimuloFiscalService::guardar($idEstacion, $data, $filePDF, $fileXML);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'Error al guardar']);
exit;
}

$notifData = [
'id_estacion' => $idEstacion,
'fecha_inicio' => $fechaInicio,
'fecha_termino' => $fechaTermino,
];
EstimuloFiscalService::notificarTelegram('agregar', $notifData);

echo json_encode(['success' => true, 'message' => 'Comprobante agregado exitosamente.']);
exit;
}

public function editar()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$detalleExistente = EstimuloFiscalService::getDetalle($id);
if (!$detalleExistente) {
echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
exit;
}

$data = [
'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
'fecha_termino' => $_POST['fecha_termino'] ?? '',
];

if (!$data['fecha_inicio'] || !$data['fecha_termino']) {
echo json_encode(['success' => false, 'message' => 'Fechas requeridas']);
exit;
}

$files = [
'EPDF_file' => $_FILES['EPDF_file'] ?? null,
'EXML_file' => $_FILES['EXML_file'] ?? null,
'CPDF_file' => $_FILES['CPDF_file'] ?? null,
'CXML_file' => $_FILES['CXML_file'] ?? null,
];

$ok = EstimuloFiscalService::editar($id, $data, $files);
if (!$ok) {
echo json_encode(['success' => false, 'message' => 'Error al editar']);
exit;
}

$notifData = [
'id_estacion' => $detalleExistente['id_estacion'],
'fecha_inicio' => $data['fecha_inicio'],
'fecha_termino' => $data['fecha_termino'],
];
EstimuloFiscalService::notificarTelegram('editar', $notifData);

echo json_encode(['success' => true, 'message' => 'Comprobante editado exitosamente.']);
exit;
}

public function eliminar()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$detalle = EstimuloFiscalService::getDetalle($id);

$ok = EstimuloFiscalService::eliminar($id);
if (!$ok) {
echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
exit;
}

if ($detalle) {
$notifData = [
'id_estacion' => $detalle['id_estacion'],
'fecha_inicio' => $detalle['fecha_inicio'],
'fecha_termino' => $detalle['fecha_termino'],
];
EstimuloFiscalService::notificarTelegram('eliminar', $notifData);
}

echo json_encode(['success' => true, 'message' => 'Comprobante eliminado exitosamente.']);
exit;
}
}

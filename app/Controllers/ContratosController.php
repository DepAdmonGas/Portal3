<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\ContratosService;
use App\Services\ModuleStationService;
use App\Services\ModuloDptoOperativoService;

class ContratosController extends BaseController
{
protected string $modulo = 'corporativo';

public function index()
{
$contexto = $this->detectContext();
$categoria = $contexto === 'almacen' ? 'almacen' : 'Corporativo';

$moduleCtx = ModuleStationService::getContext('contratos');
$idEstacion = $moduleCtx['id_estacion'];

if ($idEstacion) {
$puedeLeer = ModuloDptoOperativoService::validaPermiso($contexto, 'leer') ||
ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}
}

$title = 'Contratos';
$label = $contexto === 'almacen' ? 'Almacén' : 'Corporativo';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add($label, '/departamento-operativo/' . $contexto);
Breadcrumb::add('<span class="breadcrumb-item active">Contratos</span>', '');

View::render('departamento-operativo/1-corporativo/contratos/index', [
'title' => $title,
'categoria' => $categoria,
'contexto' => $contexto,
'idEstacion' => $idEstacion ?: 0,
'moduleStationKey' => 'contratos',
'help' => false,
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/contratos.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');

$moduleCtx = ModuleStationService::getContext('contratos');
$idEstacion = $moduleCtx['id_estacion'];

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Sin estación']);
exit;
}

$categoria = $_GET['categoria'] ?? 'Corporativo';

$data = ContratosService::getData($idEstacion, $categoria);
$permisos = ContratosService::getPermisos();

echo json_encode([
'success' => true,
'data' => $data,
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

$detalle = ContratosService::getDetalle($id);
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

$moduleCtx = ModuleStationService::getContext('contratos');
$idEstacion = $moduleCtx['id_estacion'];

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Sin estación']);
exit;
}

$data = [
'id_estacion' => $idEstacion,
'fecha' => $_POST['fecha'] ?? '',
'descripcion' => $_POST['descripcion'] ?? '',
'objeto' => $_POST['objeto'] ?? '',
'proveedor' => $_POST['proveedor'] ?? '',
'vencimiento' => $_POST['vencimiento'] ?? null,
'firmas' => $_POST['firmas'] ?? '',
'comentario' => $_POST['comentario'] ?? '',
'categoria' => $_POST['categoria'] ?? 'Corporativo',
];

if (!$data['fecha'] || !$data['descripcion']) {
echo json_encode(['success' => false, 'message' => 'Campos requeridos faltantes']);
exit;
}

$file = $_FILES['archivo'] ?? null;

$id = ContratosService::guardar($data, $file);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'Error al guardar']);
exit;
}

$data['fecha'] = $data['fecha'] ?: date('Y-m-d');
ContratosService::notificarTelegram('agregar', $data);

echo json_encode(['success' => true, 'message' => 'Contrato agregado exitosamente.']);
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

$data = [
'fecha' => $_POST['fecha'] ?? '',
'descripcion' => $_POST['descripcion'] ?? '',
'objeto' => $_POST['objeto'] ?? '',
'proveedor' => $_POST['proveedor'] ?? '',
'vencimiento' => $_POST['vencimiento'] ?? null,
'firmas' => $_POST['firmas'] ?? '',
'comentario' => $_POST['comentario'] ?? '',
];

$detalleExistente = ContratosService::getDetalle($id);

$file = $_FILES['archivo'] ?? null;

$ok = ContratosService::editar($id, $data, $file);
if (!$ok) {
echo json_encode(['success' => false, 'message' => 'Error al editar']);
exit;
}

if ($detalleExistente) {
$notifData = [
'id_estacion' => $detalleExistente['id_estacion'],
'fecha' => $data['fecha'] ?: date('Y-m-d'),
'descripcion' => $data['descripcion'],
'categoria' => $detalleExistente['categoria'],
];
ContratosService::notificarTelegram('editar', $notifData);
}

echo json_encode(['success' => true, 'message' => 'Contrato editado exitosamente.']);
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

$detalle = ContratosService::getDetalle($id);

$ok = ContratosService::eliminar($id);
if (!$ok) {
echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
exit;
}

if ($detalle) {
$notifData = [
'id_estacion' => $detalle['id_estacion'],
'fecha' => $detalle['fecha'] ?: date('Y-m-d'),
'descripcion' => $detalle['descripcion'],
'categoria' => $detalle['categoria'],
];
ContratosService::notificarTelegram('eliminar', $notifData);
}

echo json_encode(['success' => true, 'message' => 'Contrato eliminado exitosamente.']);
exit;
}

private function detectContext(): string
{
return str_contains($_SERVER['REQUEST_URI'], '/almacen/') ? 'almacen' : 'corporativo';
}
}

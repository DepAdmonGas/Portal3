<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\SegurosService;
use App\Services\ModuleStationService;
use App\Services\ModuloDptoOperativoService;

class SegurosController extends BaseController
{
public function index()
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') ||
ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$moduleCtx = ModuleStationService::getContext('seguros');
$idEstacion = $moduleCtx['id_estacion'] ?? $moduleCtx['id_depto'] ?? 0;
$permisos = SegurosService::getPermisos();

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('<span class="breadcrumb-item active">Seguros</span>', '');

View::render('departamento-operativo/1-corporativo/seguros/index', [
'title' => 'Incidentes y Accidentes (Seguros)',
'idEstacion' => $idEstacion ?: 0,
'permisos' => $permisos,
'moduleStationKey' => 'seguros',
'help' => false,
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/seguros.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');

$moduleCtx = ModuleStationService::getContext('seguros');
$idEstacion = $moduleCtx['id_estacion'] ?? $moduleCtx['id_depto'] ?? 0;

if (!$idEstacion) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$incidencias = SegurosService::getIncidencias((int) $idEstacion);
$permisos = SegurosService::getPermisos();

echo json_encode(['success' => true, 'data' => $incidencias, 'permisos' => $permisos]);
exit;
}

public function getDetalleIncidencia()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$detalle = SegurosService::getDetalleIncidencia($id);
if (!$detalle) {
echo json_encode(['success' => false]);
exit;
}

echo json_encode(['success' => true, 'data' => $detalle]);
exit;
}

public function guardarIncidencia()
{
header('Content-Type: application/json; charset=utf-8');

$moduleCtx = ModuleStationService::getContext('seguros');
$idEstacion = $moduleCtx['id_estacion'] ?? $moduleCtx['id_depto'] ?? 0;

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Sin localidad seleccionada']);
exit;
}

$data = [
'fecha' => $_POST['fecha'] ?? '',
'hora' => $_POST['hora'] ?? '',
'asunto' => $_POST['asunto'] ?? '',
'observaciones' => $_POST['observaciones'] ?? '',
'solucion' => $_POST['solucion'] ?? '',
];

if (!$data['fecha'] || !$data['hora'] || !$data['asunto'] || !$data['observaciones'] || !$data['solucion']) {
echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
exit;
}

$file = $_FILES['Evidencia_file'] ?? null;

$id = SegurosService::guardarIncidencia((int) $idEstacion, $data, $file);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'Error al guardar']);
exit;
}

$notifData = array_merge($data, ['id_estacion' => $idEstacion, 'tipo' => 'incidencia']);
SegurosService::notificarTelegram('agregar', $notifData);

echo json_encode(['success' => true, 'message' => 'Incidencia agregada exitosamente.']);
exit;
}

public function editarIncidencia()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$data = [
'fecha' => $_POST['fecha'] ?? '',
'hora' => $_POST['hora'] ?? '',
'asunto' => $_POST['asunto'] ?? '',
'observaciones' => $_POST['observaciones'] ?? '',
'solucion' => $_POST['solucion'] ?? '',
];

if (!$data['fecha'] || !$data['hora'] || !$data['asunto'] || !$data['observaciones'] || !$data['solucion']) {
echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
exit;
}

$file = $_FILES['Evidencia_file'] ?? null;

$detalleExistente = SegurosService::getDetalleIncidencia($id);

$ok = SegurosService::editarIncidencia($id, $data, $file);
if (!$ok) {
echo json_encode(['success' => false, 'message' => 'Error al editar']);
exit;
}

if ($detalleExistente) {
$notifData = array_merge($data, ['id_estacion' => $detalleExistente['id_estacion'], 'tipo' => 'incidencia']);
SegurosService::notificarTelegram('editar', $notifData);
}

echo json_encode(['success' => true, 'message' => 'Incidencia editada exitosamente.']);
exit;
}

public function eliminarIncidencia()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$detalle = SegurosService::getDetalleIncidencia($id);

$ok = SegurosService::eliminarIncidencia($id);
if (!$ok) {
echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
exit;
}

if ($detalle) {
$notifData = [
'id_estacion' => $detalle['id_estacion'],
'fecha' => $detalle['fecha_raw'],
'tipo' => 'incidencia',
];
SegurosService::notificarTelegram('eliminar', $notifData);
}

echo json_encode(['success' => true, 'message' => 'Incidencia eliminada exitosamente.']);
exit;
}

public function getPolizas()
{
header('Content-Type: application/json; charset=utf-8');

$moduleCtx = ModuleStationService::getContext('seguros');
$idEstacion = $moduleCtx['id_estacion'] ?? $moduleCtx['id_depto'] ?? 0;

if (!$idEstacion) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$polizas = SegurosService::getPolizas((int) $idEstacion);

echo json_encode(['success' => true, 'data' => $polizas]);
exit;
}

public function getDetallePoliza()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$detalle = SegurosService::getDetallePoliza($id);
if (!$detalle) {
echo json_encode(['success' => false]);
exit;
}

echo json_encode(['success' => true, 'data' => $detalle]);
exit;
}

public function guardarPoliza()
{
header('Content-Type: application/json; charset=utf-8');

$moduleCtx = ModuleStationService::getContext('seguros');
$idEstacion = $moduleCtx['id_estacion'] ?? $moduleCtx['id_depto'] ?? 0;

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Sin localidad seleccionada']);
exit;
}

$data = [
'emision' => $_POST['emision'] ?? '',
'vencimiento' => $_POST['vencimiento'] ?? '',
];

if (!$data['emision'] || !$data['vencimiento']) {
echo json_encode(['success' => false, 'message' => 'Fechas requeridas']);
exit;
}

$file = $_FILES['Poliza_file'] ?? null;

if (!$file || empty($file['tmp_name'])) {
echo json_encode(['success' => false, 'message' => 'Archivo PDF requerido']);
exit;
}

$id = SegurosService::guardarPoliza((int) $idEstacion, $data, $file);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'Error al guardar']);
exit;
}

$notifData = array_merge($data, ['id_estacion' => $idEstacion, 'tipo' => 'poliza']);
SegurosService::notificarTelegram('agregar', $notifData);

echo json_encode(['success' => true, 'message' => 'Póliza agregada exitosamente.']);
exit;
}

public function editarPoliza()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$data = [
'emision' => $_POST['emision'] ?? '',
'vencimiento' => $_POST['vencimiento'] ?? '',
];

if (!$data['emision'] || !$data['vencimiento']) {
echo json_encode(['success' => false, 'message' => 'Fechas requeridas']);
exit;
}

$file = $_FILES['Poliza_file'] ?? null;

$detalleExistente = SegurosService::getDetallePoliza($id);

$ok = SegurosService::editarPoliza($id, $data, $file);
if (!$ok) {
echo json_encode(['success' => false, 'message' => 'Error al editar']);
exit;
}

if ($detalleExistente) {
$notifData = array_merge($data, ['id_estacion' => $detalleExistente['id_estacion'], 'tipo' => 'poliza']);
SegurosService::notificarTelegram('editar', $notifData);
}

echo json_encode(['success' => true, 'message' => 'Póliza editada exitosamente.']);
exit;
}

public function eliminarPoliza()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$detalle = SegurosService::getDetallePoliza($id);

$ok = SegurosService::eliminarPoliza($id);
if (!$ok) {
echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
exit;
}

if ($detalle) {
$notifData = [
'id_estacion' => $detalle['id_estacion'],
'emision' => $detalle['emision'],
'vencimiento' => $detalle['vencimiento'],
'tipo' => 'poliza',
];
SegurosService::notificarTelegram('eliminar', $notifData);
}

echo json_encode(['success' => true, 'message' => 'Póliza eliminada exitosamente.']);
exit;
}

public function getVencimiento()
{
header('Content-Type: application/json; charset=utf-8');

$emision = $_GET['emision'] ?? '';
if (!$emision) {
echo json_encode(['success' => false]);
exit;
}

$vencimiento = SegurosService::getVencimiento($emision);

echo json_encode(['success' => true, 'vencimiento' => $vencimiento]);
exit;
}
}

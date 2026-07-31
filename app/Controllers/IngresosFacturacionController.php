<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\IngresosFacturacionService;
use App\Services\ModuleStationService;
use App\Services\ModuloDptoOperativoService;
use App\Services\DropdownYearMesService;
use App\Models\Operativo\IngresosFacturacionArchivo;

class IngresosFacturacionController extends BaseController
{
protected string $modulo = 'corporativo';

public function redirect()
{
$year = date('Y');
header('Location: /departamento-operativo/corporativo/ingresos-facturacion/' . $year);
exit;
}

public function index($idYear)
{
$validados = DropdownYearMesService::validarYearMes($idYear, 1);
$idYear = $validados['idYear'];

$moduleCtx = ModuleStationService::getContext('ingresos-facturacion');
$idEstacion = $moduleCtx['id_estacion'];

if ($idEstacion) {
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') ||
ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}
}

$title = 'Ingresos vs Facturación ' . $idYear;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');
Breadcrumb::add(self::dropdownYear($idYear), '');

if (!$this->guardModuleAccess('ingresos-facturacion', $title, 'departamento-operativo')) {
return;
}

View::render('departamento-operativo/1-corporativo/ingresos-facturacion/index', [
'title' => $title,
'idYear' => $idYear,
'idEstacion' => $idEstacion ?: 0,
'moduleStationKey' => 'ingresos-facturacion',
'help' => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/ingresos-facturacion.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function updateCell()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$valor = (float) ($input['valor'] ?? 0);
$mes = (int) ($input['mes'] ?? 0);

if (!$id || !$mes) {
echo json_encode(['success' => false]);
exit;
}

$success = IngresosFacturacionService::updateCell($id, $valor, $mes);
echo json_encode(['success' => $success]);
exit;
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');

$idYear = (int) ($_GET['id_year'] ?? date('Y'));
$moduleCtx = ModuleStationService::getContext('ingresos-facturacion');
$idEstacion = $moduleCtx['id_estacion'];

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Sin estación']);
exit;
}

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') ||
ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'message' => 'Sin permiso']);
exit;
}

$result = IngresosFacturacionService::getDataForApi($idEstacion, $idYear);
echo json_encode($result);
exit;
}

public function getTotales()
{
header('Content-Type: application/json; charset=utf-8');

$idReporte = (int) ($_GET['id_reporte'] ?? 0);
if (!$idReporte) {
echo json_encode([]);
exit;
}

$totales = IngresosFacturacionService::getTotalesJson($idReporte);
echo json_encode($totales);
exit;
}

public function uploadFile()
{
header('Content-Type: application/json; charset=utf-8');

$idReporte = (int) ($_POST['id_reporte'] ?? 0);
if (!$idReporte || empty($_FILES['archivo'])) {
echo json_encode(['success' => false]);
exit;
}

$success = IngresosFacturacionService::saveArchivo($idReporte, $_FILES['archivo']);
if ($success) {
$usuario = Session::get('usuario');
IngresosFacturacionService::notificarAgregarArchivo(
$idReporte,
$usuario['id'] ?? 0,
$usuario['nombre'] ?? 'Desconocido',
$_FILES['archivo']['name']
);
}
echo json_encode([
'success' => $success,
'message' => $success ? 'Archivo guardado correctamente' : 'Error al guardar el archivo',
]);
exit;
}

public function listFiles()
{
header('Content-Type: application/json; charset=utf-8');

$idReporte = (int) ($_GET['id_reporte'] ?? 0);
if (!$idReporte) {
echo json_encode(['archivos' => []]);
exit;
}

$archivos = IngresosFacturacionService::getArchivos($idReporte);
echo json_encode(['archivos' => $archivos]);
exit;
}

public function deleteFile()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false]);
exit;
}

$archivoModel = IngresosFacturacionArchivo::find($id);
$archivoNombre = $archivoModel ? $archivoModel->archivo : '';
$idReporte = $archivoModel ? $archivoModel->id_year : 0;

$success = IngresosFacturacionService::deleteArchivo($id);
if ($success && $idReporte) {
$usuario = Session::get('usuario');
IngresosFacturacionService::notificarEliminarArchivo(
$idReporte,
$usuario['id'] ?? 0,
$usuario['nombre'] ?? 'Desconocido',
$archivoNombre
);
}
echo json_encode([
'success' => $success,
'message' => $success ? 'Archivo eliminado correctamente' : 'Error al eliminar el archivo',
]);
exit;
}

private static function dropdownYear(int $idYear): string
{
$yearActual = date('Y');
$yearInicio = 2020;

$html = '
<a class="dropdown-toggle breadcrumb-item active" role="button" data-bs-toggle="dropdown" aria-expanded="false">
<i class="ti ti-calendar"></i> <span class="ms-1">' . $idYear . '</span>
</a>
<ul class="dropdown-menu animated rubberBand">';

for ($year = $yearActual; $year >= $yearInicio; $year--) {
$html .= '
<li class="pointer">
<a class="dropdown-item" href="/departamento-operativo/corporativo/ingresos-facturacion/' . $year . '">
<i class="ti ti-calendar"></i> <span class="ms-1">' . $year . '</span>
</a>
</li>';
}

$html .= '</ul>';
return $html;
}
}

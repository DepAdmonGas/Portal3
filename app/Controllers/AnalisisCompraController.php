<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\DropdownYearMesService;
use App\Services\AnalisisCompraService;
use App\Services\AnalisisCompraExcelService;
use App\Services\ModuloDptoOperativoService;

class AnalisisCompraController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idEstacion = $this->estacionId();
$multiEstacion = $this->isMultiEs();

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer')
|| ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$permisos = AnalisisCompraService::getPermisos();

$rows = [];
$totals = [];

if ($idEstacion && !($multiEstacion && $idEstacion === 8)) {
$rows = AnalisisCompraService::getDatos($idEstacion, $idYear, $idMes);
$totals = AnalisisCompraService::getTotals($rows);
}

$title = 'Análisis de Compras (' . nombremes($idMes) . ' ' . $idYear . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');

$referer = $_SERVER['HTTP_REFERER'] ?? '';

$inEmbarquesChain = str_contains($referer, '/departamento-operativo/embarques/')
|| str_contains($referer, '/departamento-operativo/analisis-compra/');

if ($inEmbarquesChain) {
$vieneDeImportacion = Session::get('nav_origen_embarques') === 'importacion';
} else {
$vieneDeImportacion = str_contains($referer, '/importacion');
Session::set('nav_origen_embarques', $vieneDeImportacion ? 'importacion' : 'corporativo');
}

if ($vieneDeImportacion) {
Breadcrumb::add('Importación', '/departamento-operativo/importacion');
} else {
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
}

Breadcrumb::add('Resumen Embarques (' . nombremes($idMes) . ' ' . $idYear . ')', '/departamento-operativo/embarques/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

$yearMesTemplate = '/departamento-operativo/analisis-compra/{year}/{mes}';

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'multiestacion' => $multiEstacion,
'yearMesTemplate' => $yearMesTemplate,
'rows' => $rows,
'totals' => $totals,
'puedeEditar' => $permisos['puede_editar'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'esContabilidad' => $permisos['es_contabilidad'],
'esComercializadora' => $permisos['es_comercializadora'],
'help' => false,
'scripts' => [
'/assets/js/departamento-operativo/1-corporativo/analisis-compra.init.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/analisis-compra/index', $data, 'departamento-operativo');
}

public function updateNotac()
{
header('Content-Type: application/json; charset=utf-8');

$fecha = $_POST['fecha'] ?? '';
$factura = $_POST['factura'] ?? '';
$valor = $_POST['valor'] ?? '';

if (!$fecha || !$factura) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = AnalisisCompraService::updateNotac($fecha, $factura, $valor);
echo json_encode($result);
exit;
}

public function updateStatus()
{
header('Content-Type: application/json; charset=utf-8');

$fecha = $_POST['fecha'] ?? '';
$factura = $_POST['factura'] ?? '';
$valor = $_POST['valor'] ?? '';

if (!$fecha || !$factura) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = AnalisisCompraService::updateStatus($fecha, $factura, $valor);
echo json_encode($result);
exit;
}

public function descargarExcel($idYear, $idMes)
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer')
|| ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$idEstacion = $this->estacionId();
if (!$idEstacion || ($this->isMultiEs() && $idEstacion === 8)) {
header('Content-Type: text/html; charset=utf-8');
echo '<script>alert("Selecciona una estación antes de descargar."); window.close();</script>';
exit;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

try {
AnalisisCompraExcelService::generarYDescargar($idEstacion, $idYear, $idMes);
} catch (\Throwable $e) {
error_log('Error generando Excel análisis de compras: ' . $e->getMessage());

header('Content-Type: text/html; charset=utf-8');
echo '<script>alert("Error al generar el archivo Excel. Intente de nuevo."); window.close();</script>';
exit;
}
}
}

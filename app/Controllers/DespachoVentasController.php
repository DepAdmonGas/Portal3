<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\DespachoVentasService;
use App\Services\ModuleStationService;
use App\Services\ModuloDptoOperativoService;
use App\Services\DropdownYearMesService;

class DespachoVentasController extends BaseController
{
protected string $modulo = 'corporativo';

public function redirect()
{
$year = date('Y');
$mes = date('n');
header('Location: /departamento-operativo/corporativo/despacho-ventas/' . $year . '/' . $mes);
exit;
}

public function index($idYear, $idMes)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$moduleCtx = ModuleStationService::getContext('despacho-ventas');
$idEstacion = $moduleCtx['id_estacion'];

if ($idEstacion) {
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') ||
ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}
}

$title = 'Despacho vs Ventas, ' . nombremes($idMes) . ' ' . $idYear;
$yearMesTemplate = '/departamento-operativo/corporativo/despacho-ventas/{year}/{mes}';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('<span class="breadcrumb-item active">Despacho vs Ventas</span>', '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

if (!$this->guardModuleAccess('despacho-ventas', $title, 'departamento-operativo')) {
return;
}

View::render('departamento-operativo/1-corporativo/despacho-ventas/index', [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion ?: 0,
'moduleStationKey' => 'despacho-ventas',
'yearMesTemplate' => $yearMesTemplate,
'help' => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/despacho-ventas.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');

$idYear = (int) ($_GET['id_year'] ?? date('Y'));
$idMes = (int) ($_GET['id_mes'] ?? date('n'));

$moduleCtx = ModuleStationService::getContext('despacho-ventas');
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

$result = DespachoVentasService::getData($idEstacion, $idYear, $idMes);
echo json_encode($result);
exit;
}

public function updateCell()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$idDia = (int) ($input['id_dia'] ?? 0);
$valor = (float) ($input['valor'] ?? 0);
$despacho = (int) ($input['despacho'] ?? 0);

if (!$idDia || !$despacho) {
echo json_encode(['success' => false]);
exit;
}

$success = DespachoVentasService::updateCell($idDia, $valor, $despacho);
echo json_encode(['success' => $success]);
exit;
}
}

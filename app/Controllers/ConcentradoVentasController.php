<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\DropdownYearMesService;
use App\Services\ConcentradoVentasService;

class ConcentradoVentasController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idEstacion = $this->estacionId();
$multiEstacion = $this->isMultiEs();

if (!$idEstacion || ($multiEstacion && $idEstacion === 8)) {
$data = [
'title'    => 'Concentrado de Ventas (' . nombremes($idMes) . ' ' . $idYear . ')',
'idYear'   => $idYear,
'idMes'    => $idMes,
'idEstacion' => 0,
'multiestacion' => $multiEstacion,
'help' => false,
];
View::render('departamento-operativo/1-corporativo/concentrado-ventas/index', $data, 'departamento-operativo');
return;
}

$productosList = ConcentradoVentasService::getProductosEstacion($idEstacion);

$permisos = ConcentradoVentasService::getPermisos();

$title = 'Concentrado de Ventas, ' . nombremes($idMes) . ' ' . $idYear;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">'.$title.'</span>', '');

$colores = ['#76bd1d', '#e21683', '#000'];

$data = [
'title'      => $title,
'idYear'     => $idYear,
'idMes'      => $idMes,
'idEstacion' => $idEstacion,
'yearMesTemplate' => '/departamento-operativo/concentrado-ventas/{year}/{mes}',
'multiestacion'   => $permisos['multiestacion'],
'puedeDescargar'  => $permisos['puedeDescargar'],
'productosList'   => $productosList,
'colores'         => array_slice($colores, 0, count($productosList)),
'links'  => [
'/assets/libs/select2/dist/css/select2.min.css',
'/assets/css/select2-modal.css',
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/js/departamento-operativo/1-corporativo/actions.concentrado-ventas.init.js?v=' . time(),
],
'help' => false,
];

View::render('departamento-operativo/1-corporativo/concentrado-ventas/index', $data, 'departamento-operativo');
}

public function getData($idYear, $idMes)
{
header('Content-Type: application/json; charset=utf-8');

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idEstacion = $this->estacionId();
$multiEstacion = $this->isMultiEs();

if (!$idEstacion || ($multiEstacion && $idEstacion === 8)) {
echo json_encode(['daily' => [], 'totales' => [], 'productos' => []]);
exit;
}

$data = ConcentradoVentasService::getDataMensual($idEstacion, $idYear, $idMes);

echo json_encode($data);
exit;
}
}

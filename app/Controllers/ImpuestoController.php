<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ImpuestoService;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;

class ImpuestoController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes, $idDia)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = ImpuestoService::getPermisos();
$estado = ImpuestoService::getEstado((int) $idDia);
$fecha = ImpuestoService::getFecha((int) $idDia);
$idEstacion = $this->estacionId();

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');

$title = 'Impuestos (' . formatearFecha($fecha) . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombreMes($idMes) . ' ' . $idYear . '', '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes . '');
Breadcrumb::add('<span class="breadcrumb-item active">Impuestos (' . formatearFecha($fecha) . ')</span>', '');

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idDia' => (int) $idDia,
'estado' => $estado,
'fecha' => formatearFecha($fecha),
'multiestacion' => $permisos['multiestacion'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'idEstacion' => $idEstacion,
'ocultarSelectorEstacion' => true,
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/js/departamento-operativo/1-corporativo/actions.impuestos.init.js',
],
'help' => false
];

View::render('departamento-operativo/1-corporativo/impuestos/index', $data, 'departamento-operativo');
}

public function getData($idDia)
{
header('Content-Type: application/json; charset=utf-8');
$idReporte = (int) $idDia;

$estado = ImpuestoService::getEstado($idReporte);
$data = ImpuestoService::getData($idReporte);

echo json_encode([
'success' => true,
'estado' => $estado,
'data' => $data,
]);
exit;
}
}

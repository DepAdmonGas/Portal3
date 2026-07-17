<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\MonederoService;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;
use App\Services\ModuleStationService;

class MonederoController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes, $idDia)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = MonederoService::getPermisos();
$estado = MonederoService::getEstado((int) $idDia);
$fecha = MonederoService::getFecha((int) $idDia);
$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'] ?? $this->estacionId();

$title = 'Monedero (' . formatearFecha($fecha) . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear . '', '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes . '');
Breadcrumb::add('<span class="breadcrumb-item active">Monedero (' . formatearFecha($fecha) . ')</span>', '');

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
'moduleStationKey' => 'corte-diario',
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/actions.monedero.init.js?v=' . time(),
],
'help' => false
];

View::render('departamento-operativo/1-corporativo/monedero/index', $data, 'departamento-operativo');
}

public function getData($idDia)
{
header('Content-Type: application/json; charset=utf-8');
$idReporte = (int) $idDia;

$estado = MonederoService::getEstado($idReporte);
$data = MonederoService::getData($idReporte);

echo json_encode([
'success' => true,
'estado' => $estado,
'data' => $data,
]);
exit;
}
}

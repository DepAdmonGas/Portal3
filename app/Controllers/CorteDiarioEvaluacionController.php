<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;
use App\Services\KpiCorteDiarioService;
use App\Services\ModuleStationService;

class CorteDiarioEvaluacionController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes)
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];
$multiEstacion = $this->isMultiEs();

if (!$idEstacion) {
View::render('departamento-operativo/1-corporativo/corte-diario-evaluacion/index', [
'title' => 'Apertura de Cortes Diarios (KPI\'s), ' . $idYear,
'idEstacion' => 0,
'idYear' => $idYear,
'idMes' => $idMes,
'multiestacion' => $multiEstacion,
'moduleStationKey' => 'corte-diario',
'help' => false,
'scripts' => [],
], 'departamento-operativo');
return;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$title = 'Apertura de Cortes Diarios (KPI\'s), ' . $idYear;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');

View::render('departamento-operativo/1-corporativo/corte-diario-evaluacion/index', [
'title' => $title,
'idEstacion' => $idEstacion,
'idYear' => $idYear,
'idMes' => $idMes,
'multiestacion' => false,
'moduleStationKey' => 'corte-diario',
'help' => false,
'scripts' => [
'/assets/libs/apexcharts/dist/apexcharts.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/kpi-corte-diario.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function getData($idYear, $idMes)
{
header('Content-Type: application/json');

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'message' => 'Sin permisos']);
exit;
}

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];
if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Selecciona una estación']);
exit;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$data = KpiCorteDiarioService::getData($idEstacion, $idYear);

echo json_encode(['success' => true, 'data' => $data]);
exit;
}
}

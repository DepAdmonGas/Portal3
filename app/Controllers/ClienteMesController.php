<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Models\Operativo\ConsumosPagosResumen;
use App\Services\ClienteMesService;
use App\Services\ClienteMesExcelService;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;
use App\Core\Session;
use App\Services\ModuleStationService;

class ClienteMesController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes)
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];
$multiEstacion = $this->isMultiEs();
$permisos = ClienteMesService::getPermisos();
$esDireccionOperaciones = $permisos['es_direccion_operaciones'];
$puestoExcluido = $permisos['puesto_excluido'];

if (!$idEstacion) {
View::render('departamento-operativo/1-corporativo/clientes-mes/index', [
'title' => 'Resumen Clientes, ' . nombremes($idMes) . ' ' . $idYear,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => 0,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => false,
'esDireccionOperaciones' => $esDireccionOperaciones,
'puestoExcluido' => $puestoExcluido,
'help' => false,
], 'departamento-operativo');
return;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idReporte = ClienteMesService::getIdReporte($idEstacion, $idYear, $idMes);

if (!$idReporte) {
View::render('errors/404', [], 'departamento-operativo');
return;
}

$finalizado = ClienteMesService::estaFinalizado($idReporte);

if (!$finalizado) {
$idReporteA = ClienteMesService::getIdReporteAnterior($idEstacion, $idYear, $idMes);
ClienteMesService::calcularResumen($idReporte, $idReporteA, $idEstacion);
}

$datos = ClienteMesService::getDatos($idReporte);

$puedeFinalizar = !$finalizado && !$multiEstacion;
$puedeDescargar = $finalizado && !$puestoExcluido;

$title = 'Resumen Clientes, ' . nombremes($idMes) . ' ' . $idYear;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

if (!$this->guardModuleAccess('corte-diario', $title, 'departamento-operativo')) {
return;
}

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'idReporte' => $idReporte,
'finalizado' => $finalizado,
'multiestacion' => $multiEstacion,
'esDireccionOperaciones' => $esDireccionOperaciones,
'puestoExcluido' => $puestoExcluido,
'puedeFinalizar' => $puedeFinalizar,
'puedeDescargar' => $puedeDescargar,
'credito' => $datos['credito'],
'debito' => $datos['debito'],
'totals' => $datos['totals'],
'yearMesTemplate' => '/departamento-operativo/clientes-mes/{year}/{mes}',
'help' => false,
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/departamento-operativo/1-corporativo/clientes-mes.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/actions.clientes-mes.init.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/clientes-mes/index', $data, 'departamento-operativo');
}

public function getData($idYear, $idMes)
{
header('Content-Type: application/json');

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
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

$idReporte = ClienteMesService::getIdReporte($idEstacion, $idYear, $idMes);
$finalizado = ClienteMesService::estaFinalizado($idReporte);

if (!$finalizado && $idReporte) {
$idReporteA = ClienteMesService::getIdReporteAnterior($idEstacion, $idYear, $idMes);
ClienteMesService::calcularResumen($idReporte, $idReporteA, $idEstacion);
}

$datos = ClienteMesService::getDatos($idReporte);

echo json_encode([
'success' => true,
'credito' => $datos['credito'],
'debito' => $datos['debito'],
'totals' => $datos['totals'],
'finalizado' => $finalizado,
]);
exit;
}

public function actualizar()
{
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$idYear = (int) ($input['idYear'] ?? 0);
$idMes = (int) ($input['idMes'] ?? 0);

if (!$idYear || !$idMes) {
echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
exit;
}

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];
if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Selecciona una estación']);
exit;
}

$idReporte = ClienteMesService::getIdReporte($idEstacion, $idYear, $idMes);
$idReporteA = ClienteMesService::getIdReporteAnterior($idEstacion, $idYear, $idMes);

ClienteMesService::calcularResumen($idReporte, $idReporteA, $idEstacion);

echo json_encode(['success' => true, 'message' => 'Resumen actualizado correctamente.']);
exit;
}

public function finalizar()
{
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$idReporte = (int) ($input['idReporte'] ?? 0);

if (!$idReporte) {
echo json_encode(['success' => false, 'message' => 'ID de reporte inválido']);
exit;
}

$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

$success = ClienteMesService::finalizar($idReporte, $idUsuario);

if ($success) {
register_shutdown_function(function () use ($idReporte, $idUsuario, $nombreUsuario) {
ClienteMesService::notificarFinalizar($idReporte, $idUsuario, $nombreUsuario);
});
}

echo json_encode([
'success' => $success,
'message' => $success ? 'Resumen finalizado exitosamente.' : 'Error al finalizar el resumen.',
]);
exit;
}

public function editarSaldoInicial()
{
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$idResumen = (int) ($input['id'] ?? 0);
$saldo = (float) ($input['saldo'] ?? 0);

if (!$idResumen) {
echo json_encode(['success' => false, 'message' => 'ID inválido']);
exit;
}

$result = ClienteMesService::editarSaldoInicial($idResumen, $saldo);

if ($result['success']) {
$resumen = ConsumosPagosResumen::find($idResumen);
if ($resumen) {
$datos = ClienteMesService::getDatos($resumen->id_mes);
$result['totals'] = $datos['totals'];
}
}

echo json_encode($result);
exit;
}

public function descargarExcel($idYear, $idMes, $idEstacion)
{
ClienteMesExcelService::generarYDescargar((int) $idEstacion, (int) $idYear, (int) $idMes);
}
}

<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\TpvService;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;

class TpvController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes, $idDia)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = TpvService::getPermisos();
$estado = TpvService::getEstado((int) $idDia);
$fecha = TpvService::getFecha((int) $idDia);
$idEstacion = $this->estacionId();

$multiEstacion = $permisos['multiestacion'];

$puedeEditar = ModuloDptoOperativoService::validaPermiso('corporativo', 'editar');
$puedeCrear = ModuloDptoOperativoService::validaPermiso('corporativo', 'crear');
$puedeEliminar = ModuloDptoOperativoService::validaPermiso('corporativo', 'eliminar');
$puedeDescargar = ModuloDptoOperativoService::validaPermiso('corporativo', 'descargar');

$title = 'Cierre Lote (' . formatearFecha($fecha) . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombreMes($idMes) . ' ' . $idYear . '', '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes . '');
Breadcrumb::add('<span class="breadcrumb-item active">Cierre Lote (' . formatearFecha($fecha) . ')</span>', '');

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idDia' => (int) $idDia,
'estado' => $estado,
'fecha' => formatearFecha($fecha),
'multiestacion' => $multiEstacion,
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'idEstacion' => $idEstacion,
'puedeCrear' => $puedeCrear,
'puedeEditar' => $puedeEditar,
'puedeEliminar' => $puedeEliminar,
'puedeDescargar' => $puedeDescargar,
'empresas' => TpvService::getEmpresasPorEstacion($idEstacion),
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/js/departamento-operativo/1-corporativo/actions.tpv.init.js',
],
'help' => false
];

View::render('departamento-operativo/1-corporativo/tpv/index', $data, 'departamento-operativo');
}

public function getData($idDia)
{
header('Content-Type: application/json; charset=utf-8');
$idReporte = (int) $idDia;

$estado = TpvService::getEstado($idReporte);
$cierres = TpvService::getCierresPorEmpresa($idReporte);

echo json_encode([
'success' => true,
'estado' => $estado,
'cierres' => $cierres,
]);
exit;
}

public function crear()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'crear')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$idReporte = (int) ($input['id'] ?? 0);
$empresa = $input['empresa'] ?? '';

if (!$idReporte || empty($empresa)) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

if (TpvService::isFinalizado($idReporte)) {
echo json_encode(['success' => false, 'message' => 'El TPV está finalizado']);
exit;
}

$cierre = TpvService::crearCierre($idReporte, $empresa);

echo json_encode(['success' => true, 'data' => $cierre]);
exit;
}

public function editar()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'editar')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

if (!$id || !in_array($field, ['no_cierre_lote', 'importe', 'ticktes'])) {
echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
exit;
}

$ok = TpvService::editarCierre($id, $field, $value);
echo json_encode(['success' => $ok]);
exit;
}

public function pendiente()
{
header('Content-Type: application/json; charset=utf-8');
if (!ModuloDptoOperativoService::validaPermiso('corporativo', 'leer')) {
echo json_encode(['success' => false, 'message' => 'Permiso denegado']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$estado = (int) ($input['estado'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID requerido']);
exit;
}

$ok = TpvService::togglePendiente($id, $estado);
echo json_encode(['success' => $ok]);
exit;
}

public function getTotales($idDia)
{
header('Content-Type: application/json; charset=utf-8');
$idReporte = (int) $idDia;
$empresa = $_GET['empresa'] ?? '';

if (empty($empresa)) {
echo json_encode(['success' => false, 'message' => 'Empresa requerida']);
exit;
}

$totales = TpvService::getTotalesPorEmpresa($idReporte, $empresa);
echo json_encode(['success' => true, 'data' => $totales]);
exit;
}
}

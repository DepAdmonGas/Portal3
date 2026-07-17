<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ResumenMonederoService;
use App\Services\ResumenMonederoExcelService;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;
use App\Services\KpiResumenMonederoService;
use App\Models\Operativo\MonederoDocumento;
use App\Models\Operativo\MonederoEdi;
use App\Models\Operativo\MonederoListaDocumento;
use App\Core\Session;
use App\Services\ModuleStationService;

class ResumenMonederoController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];

if (!$idEstacion) {
$data = [
'title' => 'Resumen Monedero (' . nombremes($idMes) . ' ' . $idYear . ')',
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => 0,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => false,
'help' => false,
];

View::render('departamento-operativo/1-corporativo/resumen-monedero/index', $data, 'departamento-operativo');
return;
}

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$idMesDb = ResumenMonederoService::getMesId($idEstacion, $idYear, $idMes);

$permisos = ResumenMonederoService::getPermisos();

$title = 'Resumen Monedero (' . nombremes($idMes) . ' ' . $idYear . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

$verShell = ($idEstacion == 2 || $idEstacion == 14);

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'idMesDb' => $idMesDb,
'multiestacion' => $permisos['multiestacion'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'esCorporativo' => $permisos['es_corporativo'],
'idPuesto' => $permisos['id_puesto'],
'verProsegur' => $permisos['ver_prosegur'],
'tipoPuesto' => $permisos['tipo_puesto'],
'puedeLeer' => $permisos['puede_leer'],
'puedeCrear' => $permisos['puede_crear'],
'puedeEditar' => $permisos['puede_editar'],
'puedeEliminar' => $permisos['puede_eliminar'],
'puedeDescargar' => $permisos['puede_descargar'],
'puedeEliminarDoc' => $permisos['puede_eliminar_doc'],
'yearMesTemplate' => '/departamento-operativo/resumen-monedero/{year}/{mes}',
'verShell' => $verShell,
'colspanMetodos' => $verShell ? 19 : 18,
'colspanTarjetas' => $verShell ? 8 : 7,
'totalCols' => $verShell ? 25 : 24,
'help' => false,
'scripts' => [
'/assets/js/departamento-operativo/1-corporativo/actions.resumen-monedero.init.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/resumen-monedero/index', $data, 'departamento-operativo');
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');

$idMesDb = (int) ($_GET['id_mes'] ?? 0);
if (!$idMesDb) {
echo json_encode(['success' => false, 'message' => 'ID de mes no válido']);
exit;
}

$data = ResumenMonederoService::getData($idMesDb);
$documentos = ResumenMonederoService::getDocumentos($idMesDb);
$permisos = ResumenMonederoService::getPermisos();

echo json_encode([
'success' => true,
'rows' => $data['rows'],
'totales' => $data['totales'],
'documentos' => $documentos,
'ver_prosegur' => $permisos['ver_prosegur'],
'puede_eliminar_doc' => $permisos['puede_eliminar_doc'],
'puede_crear' => $permisos['puede_crear'],
'multiestacion' => $permisos['multiestacion'],
'id_puesto' => $permisos['id_puesto'],
]);
exit;
}

public function getDocumentos()
{
header('Content-Type: application/json; charset=utf-8');

$idMesDb = (int) ($_GET['id_mes'] ?? 0);
if (!$idMesDb) {
echo json_encode(['success' => false, 'documentos' => []]);
exit;
}

$documentos = ResumenMonederoService::getDocumentos($idMesDb);

echo json_encode(['success' => true, 'documentos' => $documentos]);
exit;
}

public function getEdi()
{
header('Content-Type: application/json; charset=utf-8');

$idDocumento = (int) ($_GET['id_documento'] ?? 0);
if (!$idDocumento) {
echo json_encode(['success' => false, 'edi' => []]);
exit;
}

$edi = ResumenMonederoService::getEdiByDocumento($idDocumento);

echo json_encode(['success' => true, 'edi' => $edi]);
exit;
}

public function getListaDocumentos()
{
header('Content-Type: application/json; charset=utf-8');

$idMonedero = (int) ($_GET['id_monedero'] ?? 0);
if (!$idMonedero) {
echo json_encode(['success' => false, 'lista' => []]);
exit;
}

$lista = ResumenMonederoService::getListaDocumentos($idMonedero);

echo json_encode(['success' => true, 'lista' => $lista]);
exit;
}

public function createDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$idMes = (int) ($_POST['id_mes'] ?? 0);
$uploadDir = ResumenMonederoService::getUploadDir();
$success = ResumenMonederoService::createDocumento($_POST, $_FILES, $uploadDir);

if ($success && $idMes) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario) {
ResumenMonederoService::notificarCreateDocumento($idMes, $idUsuario, $nombreUsuario);
});
}

echo json_encode([
'success' => $success,
'message' => $success ? 'Factura agregada exitosamente.' : 'Error al agregar la factura.',
]);
exit;
}

public function updateDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$doc = MonederoDocumento::find($id);
$idMes = $doc ? $doc->id_mes : 0;

$uploadDir = ResumenMonederoService::getUploadDir();
$success = ResumenMonederoService::updateDocumento($id, $_POST, $_FILES, $uploadDir);

if ($success && $idMes) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario) {
ResumenMonederoService::notificarUpdateDocumento($idMes, $idUsuario, $nombreUsuario);
});
}

echo json_encode([
'success' => $success,
'message' => $success ? 'Factura editada exitosamente.' : 'Error al editar la factura.',
]);
exit;
}

public function deleteDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$doc = MonederoDocumento::find($id);
$idMes = $doc ? $doc->id_mes : 0;

$success = ResumenMonederoService::deleteDocumento($id);

if ($success && $idMes) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario) {
ResumenMonederoService::notificarDeleteDocumento($idMes, $idUsuario, $nombreUsuario);
});
}

echo json_encode([
'success' => $success,
'message' => $success ? 'Factura eliminada exitosamente.' : 'Error al eliminar la factura.',
]);
exit;
}

public function createEdi()
{
header('Content-Type: application/json; charset=utf-8');

$idDocumento = (int) ($_POST['id'] ?? 0);
$complemento = $_POST['complemento'] ?? '';
$uploadDir = ResumenMonederoService::getUploadDir();

$success = ResumenMonederoService::createEdi($idDocumento, $complemento, $_FILES, $uploadDir);

if ($success && $idDocumento) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idDocumento, $idUsuario, $nombreUsuario) {
ResumenMonederoService::notificarCreateEdi($idDocumento, $idUsuario, $nombreUsuario);
});
}

echo json_encode([
'success' => $success,
'message' => $success ? 'Complemento agregado exitosamente.' : 'Error al agregar el complemento.',
]);
exit;
}

public function deleteEdi()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$edi = MonederoEdi::find($id);
$idDocumento = $edi ? $edi->id_documento : 0;

$success = ResumenMonederoService::deleteEdi($id);

if ($success && $idDocumento) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idDocumento, $idUsuario, $nombreUsuario) {
ResumenMonederoService::notificarDeleteEdi($idDocumento, $idUsuario, $nombreUsuario);
});
}

echo json_encode([
'success' => $success,
'message' => $success ? 'EDI eliminado exitosamente.' : 'Error al eliminar EDI.',
]);
exit;
}

public function createListaDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$idMonedero = (int) ($_POST['id_monedero'] ?? 0);
$descripcion = $_POST['descripcion'] ?? '';
$uploadDir = ResumenMonederoService::getListaUploadDir();

$success = ResumenMonederoService::createListaDocumento($idMonedero, $descripcion, $_FILES, $uploadDir);

if ($success && $idMonedero) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idMonedero, $idUsuario, $nombreUsuario) {
ResumenMonederoService::notificarCreateListaDocumento($idMonedero, $idUsuario, $nombreUsuario);
});
}

echo json_encode([
'success' => $success,
'message' => $success ? 'Documento agregado exitosamente.' : 'Error al agregar el documento.',
]);
exit;
}

public function deleteListaDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$record = MonederoListaDocumento::find($id);
$idMonedero = $record ? $record->id_monedero : 0;

$success = ResumenMonederoService::deleteListaDocumento($id);

if ($success && $idMonedero) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idMonedero, $idUsuario, $nombreUsuario) {
ResumenMonederoService::notificarDeleteListaDocumento($idMonedero, $idUsuario, $nombreUsuario);
});
}

echo json_encode([
'success' => $success,
'message' => $success ? 'Documento eliminado correctamente.' : 'Error al eliminar el documento.',
]);
exit;
}

public function resumenPeriodo($idYear, $idMes)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];

if (!$idEstacion) {
$data = [
'title' => 'Resumen por Periodo (' . nombremes($idMes) . ' ' . $idYear . ')',
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => 0,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => false,
'help' => false,
];
View::render('departamento-operativo/1-corporativo/resumen-monedero/periodo', $data, 'departamento-operativo');
return;
}

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$permisos = ResumenMonederoService::getPermisos();
$resultado = ResumenMonederoService::getResumenPeriodo($idEstacion, $idYear, $idMes);

$title = 'Resumen por Periodo (' . nombremes($idMes) . ' ' . $idYear . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes);
Breadcrumb::add('Resumen Monedero', '/departamento-operativo/resumen-monedero/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => $permisos['multiestacion'],
'periodos' => $resultado['periodos'],
'totales' => $resultado['totales'],
'help' => false,
'scripts' => [
'/assets/js/departamento-operativo/1-corporativo/resumen-monedero-periodo.datatable.init.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/resumen-monedero/resumen-periodo', $data, 'departamento-operativo');
}

public function resumenPeriodoData($idYear, $idMes)
{
header('Content-Type: application/json');

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];
if (!$idEstacion) {
echo json_encode(['success' => false, 'periodos' => [], 'totales' => []]);
exit;
}

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'periodos' => [], 'totales' => []]);
exit;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$resultado = ResumenMonederoService::getResumenPeriodo($idEstacion, $idYear, $idMes);

echo json_encode([
'success' => true,
'periodos' => $resultado['periodos'],
'totales' => $resultado['totales'],
]);
exit;
}

public function descargarExcel($idYear, $idMes, $idEstacion)
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
header('Content-Type: text/html; charset=utf-8');
echo '<script>alert("No tienes permisos para descargar."); window.close();</script>';
exit;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idMesDb = ResumenMonederoService::getMesId((int) $idEstacion, $idYear, $idMes);
if (!$idMesDb) {
header('Content-Type: text/html; charset=utf-8');
echo '<script>alert("No hay datos para el periodo seleccionado."); window.close();</script>';
exit;
}

try {
ResumenMonederoExcelService::generarYDescargar((int) $idEstacion, $idYear, $idMes);
} catch (\Throwable $e) {
error_log('Error generando Excel resumen monedero: ' . $e->getMessage());
header('Content-Type: text/html; charset=utf-8');
echo '<script>alert("Error al generar el archivo Excel. Intente de nuevo."); window.close();</script>';
exit;
}
}

public function kpiEvaluacion($idYear)
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];

if (!$idEstacion) {
View::render('departamento-operativo/1-corporativo/resumen-monedero/kpi-evaluacion', [
'title' => 'Evaluación Facturas de Monederos (KPI\'s), ' . $idYear,
'idEstacion' => 0,
'idYear' => $idYear,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => false,
'help' => false,
'scripts' => [],
], 'departamento-operativo');
return;
}

$validados = DropdownYearMesService::validarYearMes($idYear, 1);
$idYear = $validados['idYear'];

$permisos = ResumenMonederoService::getPermisos();

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario enero ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/1');
Breadcrumb::add('Resumen Monedero ' . $idYear, '/departamento-operativo/resumen-monedero/' . $idYear . '/1');
Breadcrumb::add('<span class="breadcrumb-item active">Evaluación Facturas de Monederos (KPI\'s), ' . $idYear . '</span>', '');

View::render('departamento-operativo/1-corporativo/resumen-monedero/kpi-evaluacion', [
'title' => 'Evaluación Facturas de Monederos (KPI\'s), ' . $idYear,
'idEstacion' => $idEstacion,
'idYear' => $idYear,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => $permisos['multiestacion'],
'help' => false,
'scripts' => [
'/assets/libs/apexcharts/dist/apexcharts.min.js',
'/assets/js/departamento-operativo/1-corporativo/kpi-resumen-monedero.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function kpiEvaluacionData($idYear)
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

$validados = DropdownYearMesService::validarYearMes($idYear, 1);
$idYear = $validados['idYear'];

$data = KpiResumenMonederoService::getTipoData($idEstacion, $idYear);

echo json_encode(['success' => true, 'data' => $data]);
exit;
}
}

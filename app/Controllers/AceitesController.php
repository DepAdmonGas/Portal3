<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Models\Operativo\AceiteDocumento;
use App\Models\Operativo\AceiteFactura;
use App\Services\AceiteService;
use App\Services\AceiteExcelService;
use App\Services\DropdownYearMesService;
use App\Services\ModuloDptoOperativoService;
use App\Services\ResumenImpuestosService;
use App\Services\KpiAceitesService;
use App\Services\ModuleStationService;

class AceitesController extends BaseController
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

if (!$idEstacion) {
$data = [
'title' => 'Resumen Aceites, ' . nombremes($idMes) . ' ' . $idYear,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => 0,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => false,
'esDireccionOperaciones' => false,
'help' => false,
];
View::render('departamento-operativo/1-corporativo/aceites-mes/index', $data, 'departamento-operativo');
return;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idMesDb = AceiteService::getMesId($idEstacion, $idYear, $idMes);
if (!$idMesDb) {
View::render('errors/404', [], 'departamento-operativo');
return;
}

AceiteService::asegurarRegistros($idMesDb, $idEstacion, $idYear, $idMes);

$permisos = AceiteService::getPermisos();
$finalizado = AceiteService::estaFinalizado($idMesDb);

$nombreEstacion = AceiteService::getNombreEstacion($idEstacion);
$diasEnMes = (int) date('t', mktime(0, 0, 0, (int) $idMes, 1, (int) $idYear));

$title = 'Resumen Aceites, ' . nombremes($idMes) . ' ' . $idYear;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">Resumen Aceites (' . nombremes($idMes) . ' ' . $idYear . ')</span>', '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idMesDb' => $idMesDb,
'idEstacion' => $idEstacion,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'nombreEstacion' => $nombreEstacion,
'diasEnMes' => $diasEnMes,
'multiestacion' => $permisos['multiestacion'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'finalizado' => $finalizado,
'puedeCrear' => $permisos['puedeCrear'],
'puedeEditar' => $permisos['puedeEditar'],
'puedeEliminar' => $permisos['puedeEliminar'],
'scripts' => [
'/assets/js/departamento-operativo/1-corporativo/actions.corte.diario.init.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/actions.aceites-mes.init.js?v=' . time(),
],
'yearMesTemplate' => '/departamento-operativo/aceites-mes/{year}/{mes}',
'help' => false
];

View::render('departamento-operativo/1-corporativo/aceites-mes/index', $data, 'departamento-operativo');
}

public function data()
{
header('Content-Type: application/json; charset=utf-8');

$idMes = (int) ($_GET['id_mes'] ?? 0);
if (!$idMes) {
echo json_encode(['success' => false, 'data' => [], 'totals' => []]);
exit;
}

$reporte = AceiteService::getReporte($idMes);

echo json_encode([
'success' => true,
'data' => $reporte['rows'],
'totals' => $reporte['totals'],
'finalizado' => AceiteService::estaFinalizado($idMes),
]);
exit;
}

public function ventasDiarias()
{
header('Content-Type: application/json; charset=utf-8');

$idMes = (int) ($_GET['id_mes'] ?? 0);
$idAceite = (int) ($_GET['id_aceite'] ?? 0);

if (!$idMes || !$idAceite) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

echo json_encode([
'success' => true,
'data' => AceiteService::getVentasDiarias($idMes, $idAceite),
]);
exit;
}

public function editarCampo()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$id = (int) ($input['id'] ?? 0);
$campo = $input['campo'] ?? '';
$valor = $input['valor'] ?? 0;
$log = (int) ($input['log'] ?? $input['log'] ?? 0);

if (!$id || !$campo) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = AceiteService::guardarCampo($id, $campo, $valor, (bool) $log);

echo json_encode([
'success' => $result !== false,
'message' => $result !== false ? 'Campo actualizado' : 'Error al actualizar',
]);
exit;
}

public function getDocumentos()
{
header('Content-Type: application/json; charset=utf-8');

$idMes = (int) ($_GET['id_mes'] ?? 0);
if (!$idMes) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

echo json_encode([
'success' => true,
'data' => AceiteService::getDocumentos($idMes),
]);
exit;
}

public function actualizarDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? 0);
$idMes = (int) ($_POST['id_mes'] ?? 0);

if (!$id || !$idMes) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = AceiteService::actualizarDocumento($id, $idMes, $_FILES);

if ($result['success']) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

$archivos = [];
foreach (['ficha_deposito', 'imagen_bodega', 'factura_venta'] as $campo) {
if (!empty($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
$archivos[] = $campo . ': ' . $_FILES[$campo]['name'];
}
}
$extra = ['archivos' => implode(', ', $archivos)];

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario, $extra) {
AceiteService::notificarActualizarDocumentoAceite($idMes, $idUsuario, $nombreUsuario, $extra);
});
}

echo json_encode($result);
exit;
}

public function uploadDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$idMes = (int) ($_POST['id_mes'] ?? 0);
if (!$idMes) {
echo json_encode(['success' => false, 'message' => 'ID de mes requerido']);
exit;
}

$result = AceiteService::subirDocumento($idMes, $_FILES);

if ($result['success']) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

$archivos = [];
foreach (['ficha_deposito', 'imagen_bodega', 'factura_venta'] as $campo) {
if (!empty($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
$archivos[] = $campo . ': ' . $_FILES[$campo]['name'];
}
}
$extra = ['archivos' => implode(', ', $archivos)];

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario, $extra) {
AceiteService::notificarSubirDocumentoAceite($idMes, $idUsuario, $nombreUsuario, $extra);
});
}

echo json_encode($result);
exit;
}

public function eliminarDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

$doc = AceiteDocumento::find($id);
$idMes = $doc ? $doc->id_mes : 0;

$result = AceiteService::eliminarDocumento($id);

if ($result['success'] && $idMes) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

$archivos = [];
foreach (['ficha_deposito', 'imagen_bodega', 'factura_venta'] as $campo) {
if ($doc && $doc->$campo) {
$archivos[] = $campo;
}
}
$extra = [
'fecha' => formatearFecha($doc->fecha) ?? '',
'archivos' => implode(', ', $archivos),
];

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario, $extra) {
AceiteService::notificarEliminarDocumentoAceite($idMes, $idUsuario, $nombreUsuario, $extra);
});
}

echo json_encode($result);
exit;
}

public function evaluarDocumento()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$campo = $input['campo'] ?? '';
$fecha = $input['fecha'] ?? date('Y-m-d');
$puntaje = (int) ($input['puntaje'] ?? 0);

if (!$id || !$campo) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = AceiteService::evaluarDocumento($id, $campo, $fecha, $puntaje);
echo json_encode($result);
exit;
}

public function getFacturas()
{
header('Content-Type: application/json; charset=utf-8');

$idMes = (int) ($_GET['id_mes'] ?? 0);
if (!$idMes) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

echo json_encode([
'success' => true,
'data' => AceiteService::getFacturas($idMes),
]);
exit;
}

public function uploadFactura()
{
header('Content-Type: application/json; charset=utf-8');

$idMes = (int) ($_POST['id_mes'] ?? 0);
$fecha = $_POST['fecha'] ?? date('Y-m-d');
$concepto = $_POST['concepto'] ?? '';

if (!$idMes || !$concepto) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = AceiteService::subirFactura($idMes, $fecha, $concepto, $_FILES['archivo'] ?? []);

if ($result['success']) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario, $concepto, $fecha) {
AceiteService::notificarSubirFacturaAceite($idMes, $idUsuario, $nombreUsuario, $concepto, $fecha);
});
}

echo json_encode($result);
exit;
}

public function eliminarFactura()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

$factura = AceiteFactura::find($id);
$idMes = $factura ? $factura->id_mes : 0;

$result = AceiteService::eliminarFactura($id);

if ($result['success'] && $idMes) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario, $factura) {
AceiteService::notificarEliminarFacturaAceite($idMes, $idUsuario, $nombreUsuario, $factura->nombre_anexo ?? '');
});
}

echo json_encode($result);
exit;
}

public function evaluarFactura()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$fecha = $input['fecha'] ?? date('Y-m-d');
$puntaje = (int) ($input['puntaje'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = AceiteService::evaluarFactura($id, $fecha, $puntaje);
echo json_encode($result);
exit;
}

public function getDiferencias()
{
header('Content-Type: application/json; charset=utf-8');

$idMes = (int) ($_GET['id_mes'] ?? 0);
if (!$idMes) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

echo json_encode([
'success' => true,
'data' => AceiteService::getDiferenciasPago($idMes),
]);
exit;
}

public function agregarDiferencia()
{
header('Content-Type: application/json; charset=utf-8');

$idAceite = (int) ($_POST['id_aceite'] ?? 0);
$idMes = (int) ($_POST['id_mes'] ?? 0);
$nombreAceite = $_POST['nombre_aceite'] ?? '';
$diferencia = (int) ($_POST['diferencia'] ?? 0);
$comentario = $_POST['comentario'] ?? '';

if (!$idAceite || !$idMes) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = AceiteService::agregarDiferenciaPago($idAceite, $idMes, $nombreAceite, $diferencia, $comentario, $_FILES['documento'] ?? []);

if ($result['success']) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario, $nombreAceite, $diferencia, $comentario) {
AceiteService::notificarAgregarDiferenciaAceite($idMes, $idUsuario, $nombreUsuario, $nombreAceite, $diferencia, $comentario);
});
}

echo json_encode($result);
exit;
}

public function actualizarDocumentoDiferencia()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID requerido']);
exit;
}

$result = AceiteService::actualizarDocumentoDiferenciaPago($id, $_FILES['documento'] ?? []);
echo json_encode($result);
exit;
}

public function finalizar()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$idMes = (int) ($input['id_mes'] ?? 0);

if (!$idMes) {
echo json_encode(['success' => false, 'message' => 'ID de mes requerido']);
exit;
}

$result = AceiteService::finalizarInventario($idMes);
echo json_encode($result);
exit;
}

public function getResumenPuntajes()
{
header('Content-Type: application/json; charset=utf-8');

$idMes = (int) ($_GET['id_mes'] ?? 0);
if (!$idMes) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

echo json_encode([
'success' => true,
'data' => AceiteService::getResumenPuntajes($idMes),
]);
exit;
}

public function importarFacturas()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$idMes = (int) ($input['id_mes'] ?? 0);
$data = $input['data'] ?? [];

if (!$idMes || empty($data)) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = AceiteService::procesarImportacionFacturas($idMes, $data);
echo json_encode($result);
exit;
}

public function resumenImpuestos($idYear, $idMes)
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];

if (!$idEstacion) {
View::render('departamento-operativo/1-corporativo/aceites-mes/resumen-impuestos', [
'title' => 'Resumen Impuestos, ' . nombremes($idMes) . ' ' . $idYear,
'idEstacion' => 0,
'idYear' => $idYear,
'idMes' => $idMes,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => false,
'esDireccionOperaciones' => false,
'help' => false,
], 'departamento-operativo');
return;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idMesDb = AceiteService::getMesId($idEstacion, $idYear, $idMes);
if (!$idMesDb) {
View::render('errors/404', [], 'departamento-operativo');
return;
}

$nombreEstacion = AceiteService::getNombreEstacion($idEstacion);
$permisos = AceiteService::getPermisos();
$data = ResumenImpuestosService::getData($idMesDb);

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes);
Breadcrumb::add('Resumen Aceites ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/aceites-mes/' . $idYear . '/' . $idMes);
Breadcrumb::add('<span class="breadcrumb-item active">Resumen Impuestos, ' . nombremes($idMes) . ' ' . $idYear . '</span>', '');

View::render('departamento-operativo/1-corporativo/aceites-mes/resumen-impuestos', [
'title' => 'Resumen Impuestos, ' . nombremes($idMes) . ' ' . $idYear,
'idEstacion' => $idEstacion,
'idYear' => $idYear,
'idMes' => $idMes,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => $permisos['multiestacion'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'help' => false,
'scripts' => [
'/assets/js/departamento-operativo/1-corporativo/resumen-impuestos.datatable.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function resumenImpuestosData($idYear, $idMes)
{
header('Content-Type: application/json');

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];
if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Selecciona una estación']);
exit;
}

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'message' => 'Sin permisos']);
exit;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idMesDb = AceiteService::getMesId($idEstacion, $idYear, $idMes);
if (!$idMesDb) {
echo json_encode(['success' => false, 'message' => 'Sin datos']);
exit;
}

$data = ResumenImpuestosService::getData($idMesDb);

echo json_encode([
'success' => true,
'items' => $data['items'],
'combustibles' => $data['subtotal_combustibles'],
'aceites_total' => $data['aceites_total'],
'aceites_sin_iva' => $data['aceites_sin_iva'],
'aceites_iva' => $data['aceites_iva'],
'total_dia' => $data['total_dia'],
'm' => $data['monederos'],
]);
exit;
}

public function kpiAceites($idYear)
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];

if (!$idEstacion) {
View::render('departamento-operativo/1-corporativo/aceites-mes/kpi-aceites', [
'title' => 'Evaluación de Aceites (KPI\'s), ' . $idYear,
'idEstacion' => 0,
'idYear' => $idYear,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'multiestacion' => false,
'esDireccionOperaciones' => false,
'help' => false,
'scripts' => [],
], 'departamento-operativo');
return;
}

$validados = DropdownYearMesService::validarYearMes($idYear, 1);
$idYear = $validados['idYear'];

$nombreEstacion = AceiteService::getNombreEstacion($idEstacion);
$permisos = AceiteService::getPermisos();
$opciones = KpiAceitesService::getOpciones();

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario enero ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/1');
Breadcrumb::add('Resumen Aceites ' . $idYear, '/departamento-operativo/aceites-mes/' . $idYear . '/1');
Breadcrumb::add('<span class="breadcrumb-item active">Evaluación de Aceites (KPI\'s), ' . $idYear . '</span>', '');

View::render('departamento-operativo/1-corporativo/aceites-mes/kpi-aceites', [
'title' => 'Evaluación de Aceites (KPI\'s), ' . $idYear,
'idEstacion' => $idEstacion,
'idYear' => $idYear,
'moduleStationKey' => 'corte-diario',
'ocultarSelectorEstacion' => true,
'nombreEstacion' => $nombreEstacion,
'opciones' => $opciones,
'multiestacion' => $permisos['multiestacion'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'help' => false,
'scripts' => [
'/assets/libs/apexcharts/dist/apexcharts.min.js',
'/assets/js/departamento-operativo/1-corporativo/kpi-aceites.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function kpiAceitesData($idYear, $tipo)
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

$tipo = (int) $tipo;
if ($tipo < 1 || $tipo > 4) {
echo json_encode(['success' => false, 'message' => 'Tipo inválido']);
exit;
}

try {
$data = KpiAceitesService::getTipoData($idEstacion, $idYear, $tipo);
echo json_encode(['success' => true, 'data' => $data]);
} catch (\Throwable $e) {
error_log('Error KPI aceites data: ' . $e->getMessage());
echo json_encode(['success' => false, 'message' => 'Error al cargar datos']);
}
exit;
}

public function listaAceites()
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$permisos = AceiteService::getPermisos();
$aceites = AceiteService::getListaAceites();

$contexto = Session::get('lista_aceites_contexto');
Session::remove('lista_aceites_contexto');
$desdeResumen = !empty($contexto);

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');

if ($desdeResumen) {
$idEstacion = (int) ($contexto['idEstacion'] ?? 0);
$idYear = (int) ($contexto['idYear'] ?? date('Y'));
$idMes = (int) ($contexto['idMes'] ?? date('n'));
Breadcrumb::add(
'Corte Diario ' . nombremes($idMes) . ' ' . $idYear,
'/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes
);
Breadcrumb::add(
'Resumen Aceites ' . nombremes($idMes) . ' ' . $idYear,
'/departamento-operativo/aceites-mes/' . $idYear . '/' . $idMes
);
}

Breadcrumb::add('<span class="breadcrumb-item active">Lista de Aceites</span>', '');

View::render('departamento-operativo/1-corporativo/lista-aceites', [
'title' => 'Lista de Aceites',
'aceites' => $aceites,
'multiestacion' => $permisos['multiestacion'],
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'ocultarSelectorEstacion' => true,
'help' => false,
'scripts' => [
'/assets/js/departamento-operativo/1-corporativo/lista-aceites.actions.init.js?v=' . time(),
],
], 'departamento-operativo');
}

public function listaAceitesData()
{
header('Content-Type: application/json');

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$aceites = AceiteService::getListaAceites();

echo json_encode(['success' => true, 'data' => $aceites]);
exit;
}

public function listaAceitesGuardar(...$params)
{
header('Content-Type: application/json');

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'message' => 'Sin permisos']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
$campo = $input['campo'] ?? '';
$valor = $input['valor'] ?? '';

if (!$id || !$campo) {
echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
exit;
}

$camposPermitidos = ['concepto', 'piezas', 'precio'];
if (!in_array($campo, $camposPermitidos)) {
echo json_encode(['success' => false, 'message' => 'Campo inválido']);
exit;
}

$resultado = AceiteService::actualizarAceite($id, $campo, $valor);
echo json_encode(['success' => $resultado]);
exit;
}

public function listaAceitesNuevo(...$params)
{
header('Content-Type: application/json');

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'message' => 'Sin permisos']);
exit;
}

$aceite = AceiteService::crearAceite();
echo json_encode(['success' => true, 'data' => $aceite]);
exit;
}

public function guardarContextoListaAceites()
{
header('Content-Type: application/json; charset=utf-8');

$idEstacion = (int) ($_POST['idEstacion'] ?? 0);
$idYear = (int) ($_POST['idYear'] ?? 0);
$idMes = (int) ($_POST['idMes'] ?? 0);

if ($idEstacion > 0 && $idYear > 0 && $idMes > 0) {
Session::set('lista_aceites_contexto', [
'idEstacion' => $idEstacion,
'idYear' => $idYear,
'idMes' => $idMes,
]);
}

echo json_encode(['success' => true]);
exit;
}

public function listaAceitesEliminar()
{
header('Content-Type: application/json');

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'message' => 'Sin permisos']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID inválido']);
exit;
}

$resultado = AceiteService::eliminarAceite($id);
echo json_encode($resultado);
exit;
}

public function descargarExcel($idYear, $idMes)
{
$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$moduleCtx = ModuleStationService::getContext('corte-diario');
$idEstacion = $moduleCtx['id_estacion'];
if (!$idEstacion) {
header('Content-Type: text/html; charset=utf-8');
echo '<script>alert("Selecciona una estación antes de descargar."); window.close();</script>';
exit;
}

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

try {
AceiteExcelService::generarYDescargar($idEstacion, $idYear, $idMes);
} catch (\Throwable $e) {
error_log('Error generando Excel aceites: ' . $e->getMessage());

header('Content-Type: text/html; charset=utf-8');
echo '<script>alert("Error al generar el archivo Excel. Intente de nuevo."); window.close();</script>';
exit;
}
}
}

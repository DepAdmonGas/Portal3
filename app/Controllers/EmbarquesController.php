<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\DropdownYearMesService;
use App\Services\EmbarquesService;
use App\Services\ModuloDptoOperativoService;

class EmbarquesController extends BaseController
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
'title' => 'Resumen Embarques (' . nombremes($idMes) . ' ' . $idYear . ')',
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => 0,
'multiestacion' => $multiEstacion,
'help' => false,
];
View::render('departamento-operativo/1-corporativo/embarques/index', $data, 'departamento-operativo');
return;
}

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
View::render('errors/403', [], 'departamento-operativo');
return;
}

$permisos = EmbarquesService::getPermisos();

$idMesDb = EmbarquesService::getMesId($idEstacion, $idYear, $idMes);

$title = 'Resumen Embarques (' . nombremes($idMes) . ' ' . $idYear . ')';
$esEncargadoAsistente = $permisos['es_encargado'] || $permisos['es_asistente'];

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
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear, '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes);
}
Breadcrumb::add('<span class="breadcrumb-item active">' . $title . '</span>', '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

$yearMesTemplate = '/departamento-operativo/embarques/{year}/{mes}';

$data = [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'multiestacion' => $permisos['multiestacion'],
'yearMesTemplate' => $yearMesTemplate,
'esDireccionOperaciones' => $permisos['es_direccion_operaciones'],
'esContabilidad' => $permisos['es_contabilidad'],
'esServicioSocial' => $permisos['es_servicio_social'],
'esComercializadora' => $permisos['es_comercializadora'],
'esEncargadoAsistente' => $esEncargadoAsistente,
'idMesDb' => $idMesDb,
'puedeAgregar' => $permisos['puede_agregar'],
'puedeEditar' => $permisos['puede_editar'],
'puedeEliminar' => $permisos['puede_eliminar'],
'puedeVerComentarios' => $permisos['puede_ver_comentarios'],
'puedeAgregarComentarios' => $permisos['puede_agregar_comentarios'],
'idUsuario' => $permisos['id_usuario'],
'puedeAnalisisCompras' => $permisos['puede_analisis_compras'],
'origen' => $vieneDeImportacion ? 'importacion' : 'corporativo',
'help' => false,
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css',
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/js/departamento-operativo/1-corporativo/embarques.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/actions.embarques.init.js?v=' . time(),
],
];

View::render('departamento-operativo/1-corporativo/embarques/index', $data, 'departamento-operativo');
}

public function getDatos($idYear, $idMes)
{
header('Content-Type: application/json; charset=utf-8');

$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$idEstacion = $this->estacionId();
$multiEstacion = $this->isMultiEs();

if (!$idEstacion || ($multiEstacion && $idEstacion === 8)) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$puedeLeer = ModuloDptoOperativoService::validaPermiso('corporativo', 'leer') || ModuloDptoOperativoService::validaPermiso('personal-general', 'leer');
if (!$puedeLeer) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$idMesDb = EmbarquesService::getMesId($idEstacion, $idYear, $idMes);
$rows = EmbarquesService::getDatos($idMesDb);
$permisos = EmbarquesService::getPermisos();

echo json_encode([
'success' => true,
'data' => $rows,
'permisos' => [
'puede_editar' => $permisos['puede_editar'],
'puede_eliminar' => $permisos['puede_eliminar'],
'puede_ver_comentarios' => $permisos['puede_ver_comentarios'],
'puede_agregar_comentarios' => $permisos['puede_agregar_comentarios'],
'puede_analisis_compras' => $permisos['puede_analisis_compras'],
],
]);
exit;
}

public function store()
{
header('Content-Type: application/json; charset=utf-8');

$result = EmbarquesService::store($_POST, $_FILES);

if ($result['success']) {
$idMes = (int) ($_POST['id_mes'] ?? 0);
if ($idMes) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';
$embarqueTxt = $_POST['embarque'] ?? '';
$documentoTxt = $_POST['documento'] ?? '';

$extra = [
'fecha' => formatearFecha($_POST['fecha']) ?? '',
'producto' => $_POST['producto'] ?? '',
'documentocv' => $_POST['documentocv'] ?? '',
'importef' => $_POST['importef'] ?? 0,
'precio_litro' => $_POST['precio_litro'] ?? 0,
'merma' => $_POST['merma'] ?? '',
'tad' => $_POST['tad'] ?? '',
'nom_transporte' => $_POST['nom_transporte'] ?? '',
'chofer' => $_POST['chofer'] ?? '',
'unidad' => $_POST['unidad'] ?? '',
];

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario, $embarqueTxt, $documentoTxt, $extra) {
EmbarquesService::notificarStoreEmb($idMes, $idUsuario, $nombreUsuario, $embarqueTxt, $documentoTxt, $extra);
});
}
}

echo json_encode($result);
exit;
}

public function update()
{
header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$embarqueRec = \App\Models\Operativo\Embarque::find($id);
$idMes = $embarqueRec ? $embarqueRec->id_mes : 0;
$embarqueTxt = $embarqueRec ? $embarqueRec->embarque : '';
$documentoTxt = $embarqueRec ? $embarqueRec->documento : '';

$result = EmbarquesService::update($id, $_POST, $_FILES);

if ($result['success'] && $idMes) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

$extra = [
'fecha' => $embarqueRec->fecha ? formatearFecha($embarqueRec->fecha->format('Y-m-d')) : (formatearFecha($_POST['fecha']) ?? ''),
'producto' => $embarqueRec->producto ?? ($_POST['producto'] ?? ''),
'documentocv' => $embarqueRec->documentocv ?? ($_POST['documentocv'] ?? ''),
'importef' => $embarqueRec->importef ?? ($_POST['importef'] ?? 0),
'precio_litro' => $embarqueRec->precio_litro ?? ($_POST['precio_litro'] ?? 0),
'merma' => $embarqueRec->merma ?? ($_POST['merma'] ?? ''),
'tad' => $embarqueRec->tad ?? ($_POST['tad'] ?? ''),
'nom_transporte' => $embarqueRec->nom_transporte ?? ($_POST['nom_transporte'] ?? ''),
'chofer' => $embarqueRec->chofer ?? ($_POST['chofer'] ?? ''),
'unidad' => $embarqueRec->unidad ?? ($_POST['unidad'] ?? ''),
];

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario, $embarqueTxt, $documentoTxt, $extra) {
EmbarquesService::notificarUpdateEmb($idMes, $idUsuario, $nombreUsuario, $embarqueTxt, $documentoTxt, $extra);
});
}

echo json_encode($result);
exit;
}

public function destroy()
{
header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);
if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no válido']);
exit;
}

$embarqueRec = \App\Models\Operativo\Embarque::find($id);
$idMes = $embarqueRec ? $embarqueRec->id_mes : 0;
$embarqueTxt = $embarqueRec ? $embarqueRec->embarque : '';

$result = EmbarquesService::destroy($id);

if ($result['success'] && $idMes) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

$extra = [
'fecha' => $embarqueRec->fecha ? formatearFecha($embarqueRec->fecha->format('Y-m-d')) : '',
'producto' => $embarqueRec->producto ?? '',
'documento' => $embarqueRec->documento ?? '',
'documentocv' => $embarqueRec->documentocv ?? '',
'importef' => $embarqueRec->importef ?? 0,
'precio_litro' => $embarqueRec->precio_litro ?? 0,
'nom_transporte' => $embarqueRec->nom_transporte ?? '',
'chofer' => $embarqueRec->chofer ?? '',
'unidad' => $embarqueRec->unidad ?? '',
];

register_shutdown_function(function () use ($idMes, $idUsuario, $nombreUsuario, $embarqueTxt, $extra) {
EmbarquesService::notificarDestroyEmb($idMes, $idUsuario, $nombreUsuario, $embarqueTxt, $extra);
});
}

echo json_encode($result);
exit;
}

public function getCatalogos()
{
header('Content-Type: application/json; charset=utf-8');
echo json_encode(EmbarquesService::getCatalogos());
exit;
}

public function getComentarios()
{
header('Content-Type: application/json; charset=utf-8');

$idEmbarque = (int) ($_GET['id_embarque'] ?? 0);
if (!$idEmbarque) {
echo json_encode(['success' => false, 'comentarios' => []]);
exit;
}

$comentarios = EmbarquesService::getComentarios($idEmbarque);
echo json_encode(['success' => true, 'comentarios' => $comentarios]);
exit;
}

public function storeComentario()
{
header('Content-Type: application/json; charset=utf-8');

$idEmbarque = (int) ($_POST['id_embarque'] ?? 0);
$comentario = $_POST['comentario'] ?? '';

$result = EmbarquesService::storeComentario($idEmbarque, $comentario);

if ($result['success'] && $idEmbarque) {
$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

EmbarquesService::notificarComentarioEmb($idEmbarque, $idUsuario, $nombreUsuario, $comentario);
}

echo json_encode($result);
exit;
}
}

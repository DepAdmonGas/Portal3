<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\OrganigramaService;
use App\Services\ModuleStationService;

class OrganigramaController extends BaseController
{
public function index()
{
$permisos = OrganigramaService::getPermisos();
$esMultiestacion = $permisos['multiestacion'];
$idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

// Station 2 organigrama exception: force empty initial state so the
// special selector (Palo Solo / Autolavado) starts without selection
$sessionUsuario = \App\Core\Session::get('usuario');
if (!$esMultiestacion && !empty($sessionUsuario['id_estacion']) && $sessionUsuario['id_estacion'] == 2) {
$idEstacion = 0;
}

$title = 'Organigrama';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Recursos Humanos', '/departamento-operativo/recursos-humanos');
Breadcrumb::add($title, '');

View::render('departamento-operativo/2-recursos-humanos/organigrama/index', [
'title' => $title,
'idEstacion' => $idEstacion,
'multiestacion' => $esMultiestacion,
'moduleStationKey' => 'organigrama',
'puedeCrear' => $permisos['puedeCrear'],
'puedeEditar' => $permisos['puedeEditar'],
'puedeEliminar' => $permisos['puedeEliminar'],
'esEncargado' => $permisos['es_encargado'],
'nombrePuesto' => $permisos['nombre_puesto'],
'help' => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/organigrama.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/2-recursos-humanos/organigrama.actions.init.js?v=' . time(),
],
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
], 'departamento-operativo');
}

private static function getEstacionId(): int
{
$idEstacion = (int)($_GET['id_estacion'] ?? 0);
if ($idEstacion) return $idEstacion;

$ctx = ModuleStationService::getContext('organigrama');
$idEstacion = (int)($ctx['id_estacion'] ?? 0);
if (!$idEstacion) {
$idEstacion = (int)($ctx['id_depto'] ?? 0);
}
if (!$idEstacion) {
$permisos = OrganigramaService::getPermisos();
$idEstacion = $permisos['id_estacion'];
}
return $idEstacion;
}

public function getVersions()
{
header('Content-Type: application/json; charset=utf-8');
$idEstacion = self::getEstacionId();
$data = OrganigramaService::getOrganigramaVersions($idEstacion);
echo json_encode(['success' => true, 'data' => $data]);
exit;
}

public function add()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = OrganigramaService::getPermisos();

$id = OrganigramaService::addVersion($_POST + $_FILES);
echo json_encode(['success' => (bool)$id, 'message' => $id ? 'Organigrama agregado correctamente.' : 'Error al agregar organigrama.']);
exit;
}

public function delete()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
exit;
}

$deleted = OrganigramaService::deleteVersion($id);
echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Organigrama eliminado correctamente.' : 'Error al eliminar organigrama.']);
exit;
}

public function getPlantilla()
{
header('Content-Type: application/json; charset=utf-8');
$idEstacion = self::getEstacionId();
if (!$idEstacion) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}
$data = OrganigramaService::getPlantilla($idEstacion);
echo json_encode(['success' => true, 'data' => $data]);
exit;
}

public function addPlantilla()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idEstacion = (int)($input['id_estacion'] ?? 0);

if (!$idEstacion) {
echo json_encode(['success' => false, 'message' => 'Estación no proporcionada.']);
exit;
}

$id = OrganigramaService::addPlantillaRow($idEstacion);
echo json_encode(['success' => (bool)$id, 'id' => $id, 'message' => $id ? 'Fila agregada.' : 'Error.']);
exit;
}

public function updatePlantilla()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$campo = $input['campo'] ?? '';
$valor = $input['valor'] ?? '';

if (!$id || !$campo) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
exit;
}

$updated = OrganigramaService::updatePlantillaRow($id, $campo, $valor);
echo json_encode(['success' => $updated, 'message' => $updated ? 'Campo modificado exitosamente.' : 'Error al modificar el campo.']);
exit;
}

public function updatePlantillaUsuario()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$idUsuario = (int)($input['id_usuario'] ?? 0);
$nombre = $input['nombre'] ?? '';

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
exit;
}

$updated = OrganigramaService::updatePlantillaUsuario($id, $idUsuario, $nombre);
echo json_encode(['success' => $updated]);
exit;
}

public function deletePlantilla()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'ID no proporcionado.']);
exit;
}

$deleted = OrganigramaService::deletePlantillaRow($id);
echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Fila eliminada correctamente.' : 'Error al eliminar fila.']);
exit;
}

public function uploadDocumento()
{
header('Content-Type: application/json; charset=utf-8');
$idPlantilla = (int)($_POST['id_plantilla'] ?? 0);
$tipo = $_POST['tipo'] ?? '';

if (!$idPlantilla || !in_array($tipo, ['perfil', 'contrato'])) {
echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
exit;
}

if (empty($_FILES['archivo'])) {
echo json_encode(['success' => false, 'message' => 'Archivo no proporcionado.']);
exit;
}

$uploaded = OrganigramaService::uploadDocumento($idPlantilla, $tipo, $_FILES['archivo']);
echo json_encode(['success' => $uploaded, 'message' => $uploaded ? 'Documento guardado correctamente.' : 'Error al subir documento.']);
exit;
}

public function deleteDocumento()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idPlantilla = (int)($input['id'] ?? $input['id_plantilla'] ?? 0);
$tipo = $_GET['tipo'] ?? ($input['tipo'] ?? '');

if (!$idPlantilla || !in_array($tipo, ['perfil', 'contrato'])) {
echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
exit;
}

$deleted = OrganigramaService::deleteDocumento($idPlantilla, $tipo);
echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Documento eliminado correctamente.' : 'Error al eliminar documento.']);
exit;
}

public function getStationInfo()
{
header('Content-Type: application/json; charset=utf-8');
$idEstacion = self::getEstacionId();
if (!$idEstacion) {
echo json_encode(['success' => false, 'data' => null]);
exit;
}
$data = OrganigramaService::getStationInfo($idEstacion);
echo json_encode(['success' => true, 'data' => $data]);
exit;
}

public function updateStationInfo()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idEstacion = (int)($input['id_estacion'] ?? 0);
$campo = $input['campo'] ?? '';
$valor = $input['valor'] ?? '';

if (!$idEstacion || !$campo) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos.']);
exit;
}

$updated = OrganigramaService::updateStationInfo($idEstacion, $campo, $valor);
echo json_encode(['success' => $updated, 'message' => $updated ? 'Datos guardados correctamente.' : 'Error al guardar datos.']);
exit;
}

public function searchPersonal()
{
header('Content-Type: application/json; charset=utf-8');
$idEstacion = (int)($_GET['id_estacion'] ?? 0);
$query = $_GET['query'] ?? '';

if (!$idEstacion) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$data = OrganigramaService::searchPersonal($idEstacion, $query);
echo json_encode(['success' => true, 'data' => $data]);
exit;
}
}

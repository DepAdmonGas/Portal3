<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\AclaracionVoucherService;
use App\Services\ModuleStationService;
use App\Services\DropdownYearMesService;
use App\Models\Estacion;
use Carbon\Carbon;

class AclaracionVoucherController extends BaseController
{
protected string $modulo = 'corporativo';

public function redirect()
{
$validados = DropdownYearMesService::validarYearMes(0, 0);
header('Location: /departamento-operativo/corporativo/aclaracion-voucher/' . $validados['idYear'] . '/' . $validados['idMes']);
exit;
}

public function index(int $idYear = 0, int $idMes = 0)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = AclaracionVoucherService::getPermisos();
$esMultiestacion = $permisos['multiestacion'];
$idEstacion = $esMultiestacion ? 0 : $permisos['id_estacion'];

$esMesActual = (Carbon::now()->year == $idYear && Carbon::now()->month == $idMes);

$title = 'Aclaración Voucher (' . nombremes($idMes) . ' ' . $idYear . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add($title, '');
Breadcrumb::add(DropdownYearMesService::dropdownMes($idYear, $idMes), '');
Breadcrumb::add(DropdownYearMesService::dropdownYearManual($idYear, $idMes), '');

if (!$this->guardModuleAccess('aclaracion-voucher', $title, 'departamento-operativo')) {
return;
}

$yearMesTemplate = '/departamento-operativo/corporativo/aclaracion-voucher/{year}/{mes}';

$pendientesData = AclaracionVoucherService::getPendientes($idYear, $idMes);

View::render('departamento-operativo/1-corporativo/aclaracion-voucher/index', [
'title' => $title,
'idYear' => $idYear,
'idMes' => $idMes,
'idEstacion' => $idEstacion,
'multiestacion' => $esMultiestacion,
'yearMesTemplate' => $yearMesTemplate,
'moduleStationKey' => 'aclaracion-voucher',
'pendientesData' => $pendientesData,
'puedeCrear' => $permisos['puedeCrear'],
'puedeEditar' => $permisos['puedeEditar'],
'puedeEliminar' => $permisos['puedeEliminar'],
'esComercializadora' => $permisos['esComercializadora'],
'esMesActual' => $esMesActual,
'help' => false,
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/aclaracion-voucher.datatable.init.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/aclaracion-voucher.actions.init.js?v=' . time(),
],
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
],
], 'departamento-operativo');
}

public function getData()
{
header('Content-Type: application/json; charset=utf-8');
try {
$idYear = (int)($_REQUEST['year'] ?? 0);
$idMes = (int)($_REQUEST['mes'] ?? 0);
$estacionFilter = isset($_REQUEST['id_estacion']) ? (int)$_REQUEST['id_estacion'] : null;
if ($estacionFilter !== null && $estacionFilter === 0) $estacionFilter = null;

if (!$idYear || !$idMes) {
echo json_encode(['success' => false, 'data' => []]);
exit;
}

$data = AclaracionVoucherService::getData($idYear, $idMes, $estacionFilter);
echo json_encode(['success' => true, 'data' => $data, '_estacion_filter' => $estacionFilter]);
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}
exit;
}

public function add()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = AclaracionVoucherService::getPermisos();
if (!$permisos['puedeCrear']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos para agregar.']);
exit;
}

$idEstacion = (int)($_POST['id_estacion'] ?? 0);
$idYear = (int)($_POST['year'] ?? 0);
$idMes = (int)($_POST['mes'] ?? 0);
$nombreTicket = $_POST['nombre_ticket'] ?? '';
$fecha = $_POST['fecha'] ?? '';
$hora = $_POST['hora'] ?? '';
$valera = $_POST['valera'] ?? '';
$importe = (float)($_POST['importe'] ?? 0);
$numeroAclaracion = $_POST['numero_aclaracion'] ?? '';

if (!$idEstacion || !$idYear || !$idMes || !$nombreTicket || !$fecha || !$hora || !$valera || !$importe || !$numeroAclaracion) {
echo json_encode(['success' => false, 'message' => 'Campos requeridos']);
exit;
}

$id = AclaracionVoucherService::add([
'id_estacion' => $idEstacion,
'year' => $idYear,
'mes' => $idMes,
'id_usuario' => $permisos['id_usuario'],
'nombre_ticket' => $nombreTicket,
'fecha' => $fecha,
'hora' => $hora,
'valera' => $valera,
'importe' => $importe,
'numero_aclaracion' => $numeroAclaracion,
'ticket_file' => $_FILES['ticket_file'] ?? null,
'voucher_file' => $_FILES['voucher_file'] ?? null,
]);

if ($id) {
AclaracionVoucherService::notificarTelegram('agregar', [
'id_estacion' => $idEstacion, 'id_usuario' => $permisos['id_usuario'],
'year' => $idYear, 'mes' => $idMes, 'nombre_ticket' => $nombreTicket,
'numero_aclaracion' => $numeroAclaracion,
]);
}

echo json_encode(['success' => (bool)$id, 'message' => $id ? 'Aclaración agregada exitosamente.' : 'Error al agregar la aclaración.']);
exit;
}

public function edit()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = AclaracionVoucherService::getPermisos();

$id = (int)($_POST['id'] ?? 0);
$nombreTicket = $_POST['nombre_ticket'] ?? '';
$fecha = $_POST['fecha'] ?? '';
$hora = $_POST['hora'] ?? '';
$valera = $_POST['valera'] ?? '';
$importe = (float)($_POST['importe'] ?? 0);
$numeroAclaracion = $_POST['numero_aclaracion'] ?? '';
$pagado = (int)($_POST['pagado'] ?? 0);

if (!$id || !$nombreTicket || !$fecha || !$hora || !$valera || !$importe || !$numeroAclaracion) {
echo json_encode(['success' => false, 'message' => 'Campos requeridos']);
exit;
}

$updated = AclaracionVoucherService::update($id, [
'nombre_ticket' => $nombreTicket, 'fecha' => $fecha, 'hora' => $hora,
'valera' => $valera, 'importe' => $importe, 'numero_aclaracion' => $numeroAclaracion,
'pagado' => $pagado,
'ticket_file' => $_FILES['ticket_file'] ?? null,
'voucher_file' => $_FILES['voucher_file'] ?? null,
]);

if ($updated) {
$record = AclaracionVoucherService::getRecord($id);
if ($record) {
AclaracionVoucherService::notificarTelegram('editar', [
'id_estacion' => $record['id_estacion'], 'id_usuario' => $permisos['id_usuario'],
'year' => $record['year'], 'mes' => $record['mes'], 'nombre_ticket' => $record['nombre_ticket'],
'numero_aclaracion' => $numeroAclaracion,
]);
}
}

echo json_encode(['success' => $updated, 'message' => $updated ? 'Aclaración editada exitosamente.' : 'Error al editar la aclaración.']);
exit;
}

public function delete()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = AclaracionVoucherService::getPermisos();
if (!$permisos['puedeEliminar']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar.']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'El registro no existe.']);
exit;
}

$deleted = AclaracionVoucherService::delete($id);
if ($deleted) {
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aclaracion-voucher/';
if ($deleted['doc_ticket'] && file_exists($uploadDir . $deleted['doc_ticket'])) unlink($uploadDir . $deleted['doc_ticket']);
if ($deleted['doc_voucher'] && file_exists($uploadDir . $deleted['doc_voucher'])) unlink($uploadDir . $deleted['doc_voucher']);

AclaracionVoucherService::notificarTelegram('eliminar', [
'id_estacion' => $deleted['id_estacion'], 'id_usuario' => $permisos['id_usuario'],
'year' => $deleted['year'], 'mes' => $deleted['mes'], 'nombre_ticket' => $deleted['nombre_ticket'],
'numero_aclaracion' => $deleted['numero_aclaracion'],
]);
}

echo json_encode(['success' => (bool)$deleted, 'message' => $deleted ? 'Aclaración eliminada exitosamente.' : 'Error al eliminar la aclaración.']);
exit;
}

public function finalizar()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = AclaracionVoucherService::getPermisos();
if (!$permisos['puedeEditar']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos para finalizar.']);
exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'El registro no existe.']);
exit;
}

$finalizado = AclaracionVoucherService::finalizar($id);
if ($finalizado) {
$record = AclaracionVoucherService::getRecord($id);
if ($record) {
AclaracionVoucherService::notificarTelegram('finalizar', [
'id_estacion' => $record['id_estacion'], 'id_usuario' => $permisos['id_usuario'],
'year' => $record['year'], 'mes' => $record['mes'], 'nombre_ticket' => $record['nombre_ticket'],
'numero_aclaracion' => $record['numero_aclaracion'],
]);
}
}
echo json_encode(['success' => $finalizado, 'message' => $finalizado ? 'Solicitud finalizada exitosamente.' : 'Error al finalizar la solicitud.']);
exit;
}

public function getComentarios()
{
header('Content-Type: application/json; charset=utf-8');
$idAclaracion = (int)($_GET['id'] ?? 0);
if (!$idAclaracion) { echo json_encode(['success' => false, 'data' => []]); exit; }
$data = AclaracionVoucherService::getComentarios($idAclaracion);
echo json_encode(['success' => true, 'data' => $data]);
exit;
}

public function addComentario()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = AclaracionVoucherService::getPermisos();
$input = json_decode(file_get_contents('php://input'), true);
$idAclaracion = (int)($input['id'] ?? 0);
$comentario = trim($input['comentario'] ?? '');

if (!$idAclaracion || !$comentario) {
echo json_encode(['success' => false, 'message' => 'Campos requeridos']);
exit;
}

$saved = AclaracionVoucherService::addComentario($idAclaracion, $comentario, $permisos['id_usuario']);
if ($saved) {
$record = AclaracionVoucherService::getRecord($idAclaracion);
if ($record) {
AclaracionVoucherService::notificarTelegram('agregar_comentario', [
'id_estacion' => $record['id_estacion'], 'id_usuario' => $permisos['id_usuario'],
'year' => $record['year'], 'mes' => $record['mes'], 'nombre_ticket' => $record['nombre_ticket'],
'numero_aclaracion' => $record['numero_aclaracion'],
]);
}
}
echo json_encode(['success' => $saved, 'message' => $saved ? 'Comentario agregado exitosamente.' : 'Error al agregar el comentario.']);
exit;
}

public function getAnexos()
{
header('Content-Type: application/json; charset=utf-8');
$idSolicitud = (int)($_GET['id'] ?? 0);
if (!$idSolicitud) { echo json_encode(['success' => false, 'data' => []]); exit; }
$data = AclaracionVoucherService::getAnexos($idSolicitud);
echo json_encode(['success' => true, 'data' => $data]);
exit;
}

public function addAnexo()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = AclaracionVoucherService::getPermisos();

/*
if (!$permisos['puedeEditar']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos.']);
exit;
}
*/

$idSolicitud = (int)($_POST['id'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');
$file = $_FILES['archivo'] ?? null;

if (!$idSolicitud || !$descripcion || !$file || $file['error'] !== 0) {
echo json_encode(['success' => false, 'message' => 'Campos requeridos']);
exit;
}

$saved = AclaracionVoucherService::addAnexo($idSolicitud, $descripcion, $file, $permisos['id_usuario']);
if ($saved) {
$record = AclaracionVoucherService::getRecord($idSolicitud);
if ($record) {
AclaracionVoucherService::notificarTelegram('agregar_anexo', [
'id_estacion' => $record['id_estacion'], 'id_usuario' => $permisos['id_usuario'],
'year' => $record['year'], 'mes' => $record['mes'], 'nombre_ticket' => $record['nombre_ticket'],
'numero_aclaracion' => $record['numero_aclaracion'],
'descripcion' => $descripcion,
]);
}
}
echo json_encode(['success' => $saved, 'message' => $saved ? 'Anexo agregado exitosamente.' : 'Error al agregar el anexo.']);
exit;
}

public function deleteAnexo()
{
header('Content-Type: application/json; charset=utf-8');
$permisos = AclaracionVoucherService::getPermisos();

/*
if (!$permisos['puedeEditar']) {
echo json_encode(['success' => false, 'message' => 'No tienes permisos.']);
exit;
}
*/

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id) {
echo json_encode(['success' => false, 'message' => 'El anexo no existe.']);
exit;
}

$deleted = AclaracionVoucherService::deleteAnexo($id);
if ($deleted) {
$uploadDir = __DIR__ . '/../../public/uploads/archivos/aclaracion-voucher/';
if ($deleted['documento'] && file_exists($uploadDir . $deleted['documento'])) unlink($uploadDir . $deleted['documento']);

$record = AclaracionVoucherService::getRecord($deleted['id_reporte']);
if ($record) {
AclaracionVoucherService::notificarTelegram('eliminar_anexo', [
'id_estacion' => $record['id_estacion'], 'id_usuario' => $permisos['id_usuario'],
'year' => $record['year'], 'mes' => $record['mes'], 'nombre_ticket' => $record['nombre_ticket'],
'numero_aclaracion' => $record['numero_aclaracion'],
'descripcion' => $deleted['descripcion'] ?: $deleted['documento'],
]);
}
}

echo json_encode(['success' => (bool)$deleted, 'message' => $deleted ? 'Anexo eliminado exitosamente.' : 'Error al eliminar el anexo.']);
exit;
}
}

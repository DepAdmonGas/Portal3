<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ClienteService;
use App\Services\DropdownYearMesService;
use App\Models\Operativo\ConsumosPago;

class ClienteController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idYear, $idMes, $idDia)
{
$validados = DropdownYearMesService::validarYearMes($idYear, $idMes);
$idYear = $validados['idYear'];
$idMes = $validados['idMes'];

$permisos = ClienteService::getPermisos();
$estado = ClienteService::getEstado((int) $idDia);
$fecha = ClienteService::getFecha((int) $idDia);
$idEstacion = $this->estacionId();

$title = 'Clientes (' . formatearFecha($fecha) . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');
Breadcrumb::add('Corte Diario ' . nombremes($idMes) . ' ' . $idYear . '', '/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes . '');
Breadcrumb::add('<span class="breadcrumb-item active">Clientes (' . formatearFecha($fecha) . ')</span>', '');

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
'puedeCrear' => $permisos['puedeCrear'],
'puedeEliminar' => $permisos['puedeEliminar'],
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css',
'/assets/css/select2-modal.css',
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/libs/select2/dist/js/select2.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/actions.clientes.init.js?v=' . time(),
],
'help' => false
];

View::render('departamento-operativo/1-corporativo/clientes/index', $data, 'departamento-operativo');
}

public function getData($idDia)
{
header('Content-Type: application/json; charset=utf-8');
$idReporte = (int) $idDia;

$estado = ClienteService::getEstado($idReporte);
$data = ClienteService::getData($idReporte);

echo json_encode([
'success' => true,
'estado' => $estado,
'data' => $data,
]);
exit;
}

public function getClientes()
{
header('Content-Type: application/json; charset=utf-8');
$idEstacion = $this->estacionId();
$clientes = ClienteService::getClientes($idEstacion);

echo json_encode([
'success' => true,
'clientes' => $clientes,
]);
exit;
}

public function agregarPago()
{
header('Content-Type: application/json; charset=utf-8');
$idReporte = (int) ($_POST['idReporte'] ?? 0);
$idCliente = (int) ($_POST['Cliente'] ?? 0);
$total = (float) ($_POST['Total'] ?? 0);
$formaPago = $_POST['FormaPago'] ?? '';
$file = $_FILES['Comprobante_file'] ?? null;

if ($idReporte <= 0 || $idCliente <= 0 || $total <= 0 || $formaPago === '') {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = ClienteService::agregarPago($idReporte, $idCliente, $total, $formaPago, $file);
if ($result) {
ClienteService::sincronizarControlgas($idReporte);
}

echo json_encode(['success' => $result]);
exit;
}

public function agregarConsumo()
{
header('Content-Type: application/json; charset=utf-8');
$idReporte = (int) ($_POST['idReporte'] ?? 0);
$idCliente = (int) ($_POST['Cliente'] ?? 0);
$total = (float) ($_POST['Total'] ?? 0);

if ($idReporte <= 0 || $idCliente <= 0 || $total <= 0) {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$result = ClienteService::agregarConsumo($idReporte, $idCliente, $total);
if ($result) {
ClienteService::sincronizarControlgas($idReporte);
}

echo json_encode(['success' => $result]);
exit;
}

public function eliminar()
{
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
$id = (int) ($data['id'] ?? 0);

if ($id <= 0) {
echo json_encode(['success' => false, 'message' => 'ID inválido']);
exit;
}

$record = ConsumosPago::find($id);
$idReporte = $record ? (int) $record->id_reportedia : 0;

$result = ClienteService::eliminarConsumoPago($id);
if ($result && $idReporte > 0) {
ClienteService::sincronizarControlgas($idReporte);
}

echo json_encode([
'success' => $result,
'message' => $result ? 'Registro eliminado exitosamente' : 'Error al eliminar el registro'
]);
exit;
}
}

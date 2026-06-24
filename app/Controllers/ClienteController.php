<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Services\ClienteService;
use App\Services\DropdownYearMesService;
use App\Models\Operativo\ConsumosPago;
use App\Core\Session;

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
'ocultarSelectorEstacion' => true,
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

try {
$result = ClienteService::agregarPago($idReporte, $idCliente, $total, $formaPago, $file);
if ($result) {
ClienteService::sincronizarControlgas($idReporte);

$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';
$clienteModel = \App\Models\Operativo\Cliente::find($idCliente);
$nombreCliente = $clienteModel ? ($clienteModel->cliente ?? '') : '';

register_shutdown_function(function () use ($idReporte, $idUsuario, $nombreUsuario, $nombreCliente, $total, $idCliente) {
ClienteService::notificarAgregarCliente($idReporte, $idUsuario, $nombreUsuario, 'pago', $nombreCliente, $total, $idCliente);
});
} else {
error_log('agregarPago: save returned false for idReporte=' . $idReporte . ' idCliente=' . $idCliente . ' total=' . $total);
}

echo json_encode(['success' => $result, 'message' => $result ? null : 'Error al guardar el pago']);
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}
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

try {
$result = ClienteService::agregarConsumo($idReporte, $idCliente, $total);
if ($result) {
ClienteService::sincronizarControlgas($idReporte);

$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';
$clienteModel = \App\Models\Operativo\Cliente::find($idCliente);
$nombreCliente = $clienteModel ? ($clienteModel->cliente ?? '') : '';

register_shutdown_function(function () use ($idReporte, $idUsuario, $nombreUsuario, $nombreCliente, $total, $idCliente) {
ClienteService::notificarAgregarCliente($idReporte, $idUsuario, $nombreUsuario, 'consumo', $nombreCliente, $total, $idCliente);
});
} else {
error_log('agregarConsumo: save returned false for idReporte=' . $idReporte . ' idCliente=' . $idCliente . ' total=' . $total);
}

echo json_encode(['success' => $result, 'message' => $result ? null : 'Error al guardar el consumo']);
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'message' => 'Error al guardar: ' . $e->getMessage()]);
}
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
$clienteModel = $record ? \App\Models\Operativo\Cliente::find($record->id_cliente) : null;
$deleteInfo = $record ? [
'id' => $record->id,
'cliente' => ($clienteModel ? $clienteModel->cliente : null) ?? 'ID:' . $record->id_cliente,
'cuenta' => $clienteModel ? ($clienteModel->cuenta ?? '') : '',
'tipo_cliente' => $clienteModel ? ($clienteModel->tipo ?? '') : '',
'rfc' => $clienteModel ? ($clienteModel->rfc ?? '') : '',
'total' => $record->total,
'tipo' => $record->tipo,
'fecha' => optional(\App\Models\Operativo\CorteDia::find($record->id_reportedia))->fecha ?? '',
] : [];

$result = ClienteService::eliminarConsumoPago($id);
if ($result && $idReporte > 0) {
ClienteService::sincronizarControlgas($idReporte);

$usuario = Session::get('usuario');
$idUsuario = $usuario['id'] ?? 0;
$nombreUsuario = $usuario['nombre'] ?? 'Desconocido';

register_shutdown_function(function () use ($idReporte, $idUsuario, $nombreUsuario, $deleteInfo) {
ClienteService::notificarEliminarCliente($idReporte, $idUsuario, $nombreUsuario, $deleteInfo);
});
}

echo json_encode([
'success' => $result,
'message' => $result ? 'Registro eliminado exitosamente' : 'Error al eliminar el registro'
]);
exit;
}
}

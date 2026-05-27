<?php
namespace App\Controllers;

use App\Core\View;
use App\Core\Breadcrumb;
use App\Core\Session;
use App\Services\ClienteService;

class ClientesListaController extends BaseController
{
protected string $modulo = 'corporativo';

public function index($idEstacion)
{
$permisos = ClienteService::getPermisos();

$idEstacion = (int) $idEstacion;

$contexto = Session::get('clientes_lista_contexto');

$idYear = (int) ($contexto['idYear'] ?? date('Y'));
$idMes = (int) ($contexto['idMes'] ?? date('n'));
$idDia = (int) ($contexto['idDia'] ?? 0);

// Obtener fecha real del registro
$fecha = ClienteService::getFecha($idDia);

// Parsear correctamente la fecha
$fechaObj = \Carbon\Carbon::parse($fecha);

$mesNombre = nombremes(str_pad($idMes, 2, '0', STR_PAD_LEFT));
$fechaFormateada = formatearFecha($fechaObj);

$title = 'Lista de Clientes';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Dirección de Operaciones', '/departamento-operativo');
Breadcrumb::add('Corporativo', '/departamento-operativo/corporativo');

Breadcrumb::add(
'Corte Diario ' . $mesNombre . ' ' . $idYear,
'/departamento-operativo/corporativo/corte-diario/' . $idYear . '/' . $idMes
);

Breadcrumb::add(
'Clientes (' . $fechaFormateada . ')',
'/departamento-operativo/clientes/' . $idYear . '/' . $idMes . '/' . $idDia
);

Breadcrumb::add('Lista de Clientes', '');

$data = [
'title' => $title,
'idEstacion' => $idEstacion,
'idDia' => $idDia,
'idYear' => $idYear,
'idMes' => $idMes,
'multiestacion' => true,
'esDireccionOperaciones' => true,
'puedeCrear' => true,
'puedeEditar' => true,
'puedeEliminar' => true,
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css',
'/assets/css/select2-modal.css',
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/libs/select2/dist/js/select2.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/departamento-operativo/1-corporativo/actions.corte.diario.init.js?v=' . time(),
'/assets/js/departamento-operativo/1-corporativo/actions.clientes.lista.init.js?v=' . time(),
],
'help' => false
];

View::render(
'departamento-operativo/1-corporativo/clientes/lista',
$data,
'departamento-operativo'
);
}

public function getLista($idEstacion)
{
header('Content-Type: application/json; charset=utf-8');
$data = ClienteService::getClientesLista((int) $idEstacion);

echo json_encode([
'success' => true,
'credito' => $data['credito'],
'debito' => $data['debito'],
]);
exit;
}

public function guardarContexto()
{
header('Content-Type: application/json; charset=utf-8');
$idYear = (int) ($_POST['idYear'] ?? 0);
$idMes = (int) ($_POST['idMes'] ?? 0);
$idDia = (int) ($_POST['idDia'] ?? 0);

if ($idYear > 0 && $idMes > 0 && $idDia > 0) {
Session::set('clientes_lista_contexto', [
'idYear' => $idYear,
'idMes' => $idMes,
'idDia' => $idDia,
]);
}

echo json_encode(['success' => true]);
exit;
}

public function crear()
{
header('Content-Type: application/json; charset=utf-8');
try {
$idEstacion = (int) ($_POST['idEstacion'] ?? $this->estacionId());
$cuenta = $_POST['Cuenta'] ?? '';
$cliente = $_POST['Cliente'] ?? '';
$tipo = $_POST['Tipo'] ?? '';
$rfc = $_POST['RFC'] ?? '';

if ($cuenta === '' || $cliente === '' || $tipo === '') {
echo json_encode(['success' => false, 'message' => 'Campos requeridos incompletos']);
exit;
}

$files = [
$_FILES['CartaCredito_file'] ?? null,
$_FILES['ActaConstitutiva_file'] ?? null,
$_FILES['ComprobanteDom_file'] ?? null,
$_FILES['Identificacion_file'] ?? null,
$_FILES['ConstanciaRFC_file'] ?? null,
$_FILES['OpinionCumplimiento_file'] ?? null,
$_FILES['PoderNotarial_file'] ?? null,
];

$result = ClienteService::crearCliente($idEstacion, $cuenta, $cliente, $tipo, $rfc, $files);
echo json_encode($result);
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
exit;
}

public function editar()
{
header('Content-Type: application/json; charset=utf-8');
try {
$idCliente = (int) ($_POST['idCliente'] ?? 0);
$cuenta = $_POST['Cuenta'] ?? '';
$cliente = $_POST['Cliente'] ?? '';
$tipo = $_POST['Tipo'] ?? '';
$rfc = $_POST['RFC'] ?? '';

if ($idCliente <= 0 || $cuenta === '' || $cliente === '' || $tipo === '') {
echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
exit;
}

$files = [
$_FILES['CartaCredito_file'] ?? null,
$_FILES['ActaConstitutiva_file'] ?? null,
$_FILES['ComprobanteDom_file'] ?? null,
$_FILES['Identificacion_file'] ?? null,
$_FILES['ConstanciaRFC_file'] ?? null,
$_FILES['OpinionCumplimiento_file'] ?? null,
$_FILES['PoderNotarial_file'] ?? null,
];

$result = ClienteService::editarCliente($idCliente, $cuenta, $cliente, $tipo, $rfc, $files);
echo json_encode($result);
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
exit;
}

public function toggle()
{
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$idCliente = (int) ($input['id'] ?? $_POST['id'] ?? 0);
$idTipo = (int) ($input['idTipo'] ?? $_POST['idTipo'] ?? 1);

if ($idCliente <= 0) {
echo json_encode(['success' => false, 'message' => 'ID inválido']);
exit;
}

$cliente = \App\Models\Operativo\Cliente::find($idCliente);
if (!$cliente) {
echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
exit;
}

if ($idTipo == 1) {
$cliente->estado = 0;
} else {
$cliente->estado = 1;
}

try {
$result = $cliente->save();
} catch (\Throwable $e) {
echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $e->getMessage()]);
exit;
}

echo json_encode([
'success' => $result,
'message' => $result ? 'Estado actualizado correctamente' : 'Error al actualizar el estado'
]);
exit;
}
}

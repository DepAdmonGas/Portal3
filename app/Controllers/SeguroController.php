<?php
namespace App\Controllers;
use App\Core\View;
use App\Core\Breadcrumb;
use App\Models\PolizaSeguro;
use App\Models\PolizaSeguroCobertura;
use App\Services\ModuloService;
use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;

class SeguroController extends BaseController{
protected string $modulo = 'seguro';
public function index(){

$title = 'Seguro';
Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'filtro_usuario' => $this->filtro_usuario,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/seguro/seguro.datatable.init.js',
'/assets/js/seguro/actions.init.js'
],
'help' => false
];

View::render('seguro/index', $data,'main');
}

//---------------------------------------//
//---------- POLIZA DE SEGURO ----------//
//-------------------------------------//
public function datatablePolizaSeguro(){

$permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');
$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar   = ModuloService::validaPermiso($this->modulo, 'eliminar');

$poliza = PolizaSeguro::orderBy('fecha_hora', 'desc')->get();

echo json_encode([
"data" => $poliza,
"permisos" => [
"descargar" => $permisoDescargar,
"editar"   => $permisoEditar,
"eliminar"   => $permisoEliminar
]
]);

exit;
}


public function createPolizaSeguro(){

header('Content-Type: application/json; charset=utf-8');

/*
if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}
*/

$file  = $_FILES['poliza'] ?? null;
$status = 0;

// CONFIG RUTA
$carpeta = __DIR__ . '../../../public/uploads/archivos/poliza-seguro/';
if (!file_exists($carpeta)) {
mkdir($carpeta, 0777, true);
}

$nombreArchivo = null;

try {

Capsule::beginTransaction();

if ($file && $file['error'] === UPLOAD_ERR_OK) {

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$nombreArchivo = uniqid('rep_') . '.' . $extension;
$rutaDestino = $carpeta . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
throw new \Exception('No se pudo guardar el archivo');
}
}

$ultimoRegistro = PolizaSeguro::orderBy('id', 'desc')->first();

if ($ultimoRegistro && $ultimoRegistro->estatus == 0) {
$ultimoRegistro->estatus = 1;
$ultimoRegistro->save();
}

PolizaSeguro::create([
'archivo'     => $nombreArchivo,
'estatus'     => $status
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Póliza registrada correctamente'
]);

} catch (\Exception $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}
}

public function deletePolizaSeguro(){

header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

/*
if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}
*/

try {

Capsule::beginTransaction();

$poliza = PolizaSeguro::find($id);

if (!$poliza) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}


$poliza->estatus = 2;
$poliza->save();

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Póliza eliminada correctamente'
]);

} catch (\Exception $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}
}

//---------------------------------------//
//---------- COBERTURA POLIZA ----------//
//-------------------------------------//

public function datatablePolizaSeguroCobertura(){

$permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');
$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar   = ModuloService::validaPermiso($this->modulo, 'eliminar');

$poliza_cobertura = PolizaSeguroCobertura::orderBy('fecha_hora', 'desc')->get();

echo json_encode([
"data" => $poliza_cobertura,
"permisos" => [
"descargar" => $permisoDescargar,
"editar"   => $permisoEditar,
"eliminar"   => $permisoEliminar
]
]);

exit;
}

public function createPolizaSeguroCobertura(){

header('Content-Type: application/json; charset=utf-8');

/*
if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}
*/

$file  = $_FILES['cobertura'] ?? null;
$status = 0;

// CONFIG RUTA
$carpeta = __DIR__ . '../../../public/uploads/archivos/poliza-seguro/';
if (!file_exists($carpeta)) {
mkdir($carpeta, 0777, true);
}

$nombreArchivo = null;

try {

Capsule::beginTransaction();

if ($file && $file['error'] === UPLOAD_ERR_OK) {

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$nombreArchivo = uniqid('rep_') . '.' . $extension;
$rutaDestino = $carpeta . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
throw new \Exception('No se pudo guardar el archivo');
}
}

$ultimoRegistro = PolizaSeguroCobertura::orderBy('id', 'desc')->first();

if ($ultimoRegistro && $ultimoRegistro->estatus == 0) {
$ultimoRegistro->estatus = 1;
$ultimoRegistro->save();
}

PolizaSeguroCobertura::create([
'archivo'     => $nombreArchivo,
'estatus'     => $status
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Cobertura de póliza registrada correctamente'
]);

} catch (\Exception $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}
}

public function deletePolizaSeguroCobertura(){

header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

/*
if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}
*/

try {

Capsule::beginTransaction();

$poliza = PolizaSeguroCobertura::find($id);

if (!$poliza) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}


$poliza->estatus = 2;
$poliza->save();

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Cobertura de póliza eliminada correctamente'
]);

} catch (\Exception $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}
}


}

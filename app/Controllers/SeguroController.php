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

private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

private function validateSeguroUpload(?array $file): array
{
if ($file === null) {
return ['valid' => false, 'message' => 'Archivo requerido.'];
}

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
return ['valid' => false, 'message' => 'El archivo no se pudo subir correctamente.'];
}

if (!isset($file['tmp_name'], $file['size'], $file['name'])
    || !is_string($file['tmp_name'])
    || !is_int($file['size'])
    || $file['size'] < 1
    || $file['size'] > self::MAX_UPLOAD_BYTES
    || !is_file($file['tmp_name'])) {
return ['valid' => false, 'message' => 'El archivo excede el tamaño permitido o es inválido.'];
}

$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = [
'pdf' => ['application/pdf'],
'jpg' => ['image/jpeg'],
'jpeg' => ['image/jpeg'],
'png' => ['image/png'],
];
if (!isset($allowed[$extension])) {
return ['valid' => false, 'message' => 'Tipo de archivo no permitido.'];
}

$mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!is_string($mime) || !in_array($mime, $allowed[$extension], true)) {
return ['valid' => false, 'message' => 'El contenido del archivo no coincide con su extensión.'];
}

return ['valid' => true, 'extension' => $extension];
}
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
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/seguro/seguro.datatable.init.js?v=' . time(),
'/assets/js/seguro/actions.init.js?v=' . time()
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
 mkdir_safe($carpeta, true);
}

$nombreArchivo = null;
$validation = $this->validateSeguroUpload($file);
if (!$validation['valid']) {
http_response_code(422);
echo json_encode(['success' => false, 'message' => $validation['message']]);
return;
}

try {

Capsule::beginTransaction();

if ($file) {
$nombreArchivo = bin2hex(random_bytes(16)) . '.' . $validation['extension'];
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
$id = sanitize_input($data['id'] ?? null, 'int');

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
 mkdir_safe($carpeta, true);
}

$nombreArchivo = null;
$validation = $this->validateSeguroUpload($file);
if (!$validation['valid']) {
http_response_code(422);
echo json_encode(['success' => false, 'message' => $validation['message']]);
return;
}

try {

Capsule::beginTransaction();

if ($file) {
$nombreArchivo = bin2hex(random_bytes(16)) . '.' . $validation['extension'];
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

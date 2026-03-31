<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\SolicitudGafetes;
use App\Core\Breadcrumb;
use App\Models\Usuario;
use App\Services\ModuloService;
use App\Core\Session;
use App\Models\Estacion;
use Illuminate\Database\Capsule\Manager as Capsule;

class GafetesController extends BaseController{
protected string $modulo = 'solicitud-gafetes';

public function index(){

$title = 'Solicitud de Gafetes';

Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');
// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/gafetes/gafetes.datatable.init.js?v=1.1',
'/assets/js/gafetes/actions.init.js?v=1.0'
],
'help' => false
];

View::render('gafetes/index', $data,'main');
}

public function datatableGafetes()
{
$permisoLeer     = ModuloService::validaPermiso($this->modulo, 'leer');
$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

$filtro_usuario = Session::get('usuario');
$idEstacion = $filtro_usuario['id_estacion'] ?? null;

$query = SolicitudGafetes::from('tb_solicitud_gafetes as g')
->leftJoin('tb_estaciones as e', 'g.id_estacion', '=', 'e.nombre')
->select('g.*', 'e.id as id_estacion_real');

// SOLO filtrar si la estación NO es 8
if (!empty($idEstacion) && (int)$idEstacion !== 8) {

$estacion = Estacion::find($idEstacion);

if ($estacion) {
$query->where('g.id_estacion', $estacion->nombre);
}

}

// ORDENAR para que aparezca el último registro
$gafetes = $query
->orderBy('g.id', 'desc')
->get();

echo json_encode([
"data" => $gafetes,
"permisos" => [
"leer"     => $permisoLeer,
"editar"   => $permisoEditar,
"eliminar" => $permisoEliminar
]
]);

exit;
}

public function createReporte()
{

header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);

if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

// DATA (multipart → usar $_POST)
$id = $_POST['id'] ?? null;
$clave = $_POST['clave'] ?? null;
$nombre_g = $_POST['nombre_g'] ?? null;
$file  = $_FILES['foto'] ?? null;
$comentarios = '';
$status = 0;

if (!$clave) {
echo json_encode([
'success' => false,
'message' => 'La clave es obligatoria'
]);
exit;
}

if (!$nombre_g) {
echo json_encode([
'success' => false,
'message' => 'El nombre es obligatorio'
]);
exit;
}

// CONFIG RUTA
$carpeta = __DIR__ . '../../../public/uploads/archivos/';
if (!file_exists($carpeta)) {
mkdir($carpeta, 0777, true);
}

$nombreArchivo = null;
try {

// SUBIR ARCHIVO (opcional)
if ($file && $file['error'] === UPLOAD_ERR_OK) {

// Validar extensión
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// nombre único
$nombreArchivo = uniqid('rep_') . '.' . $extension;

$rutaDestino = $carpeta . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
throw new \Exception('No se pudo guardar el archivo');
}
}

// Obtener el usuario
$idUsuario = $this->userId();
$datosUsuario = Usuario::find($idUsuario);

// Obtener la estacion 
$idEstacion = $this->estacionId();
$datosEstacion = Estacion::find($idEstacion);

// Obtener el max(no_reporte) por estación
$maxReporte = SolicitudGafetes::where('id_estacion', $datosEstacion->nombre)
->max('no_reporte');

$id_reporte = $maxReporte ? $maxReporte + 1 : 1;
$no_reporte = ($id == 0) ? $id_reporte : $id;

Capsule::beginTransaction();

try {
// GUARDAR EN BD
SolicitudGafetes::create([
'no_reporte' => $no_reporte,
'id_estacion' => $datosEstacion->nombre,
'usuario' => $datosUsuario->nombre,
'fecha' => date('Y-m-d'),
'clave' => $clave,  
'nombre' => $nombre_g,
'foto'  =>  $nombreArchivo,
'comentarios' => $comentarios,
'estatus' => $status,
]);


Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Reporte guardado correctamente',
'idEstacion' => $idEstacion,
'no_reporte' => $no_reporte
]);

} catch (\Throwable $e) {

// Si falla BD, borrar archivo
if ($nombreArchivo && file_exists($carpeta . $nombreArchivo)) {
unlink($carpeta . $nombreArchivo);
}

echo json_encode([
'success' => false,
'message' => $e->getMessage(),
'idEstacion' => 0,
'no_reporte' => 0
]);
}

exit;

} catch (\Exception $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage(),
'idEstacion' => 0,
'no_reporte' => 0
]);

}

}

public function deleteReporte(){
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$id) {
echo json_encode(['success' => false,'message' => 'ID requerido']);
exit;
}

// SOLICITUD
$solicitud_principal = SolicitudGafetes::find($id);
if (!$solicitud_principal) {
echo json_encode(['success' => false, 'message' => 'No. de solicitud no encontrado']);
exit;
}

try {

// Buscar registros del reporte
$solicitudes = SolicitudGafetes::where('id_estacion', $solicitud_principal->id_estacion)
->where('no_reporte', $solicitud_principal->no_reporte)
->get();

/* VALIDACIÓN CORRECTA */
if ($solicitudes->isEmpty()) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
exit;
}

// Ruta base CORREGIDA
$rutaBase = __DIR__ . '/../../../public/uploads/archivos/';

// TRANSACCIÓN
Capsule::beginTransaction();

/* RECORRER LOS REGISTROS */
foreach ($solicitudes as $solicitud) {

$rutaArchivo = $rutaBase . $solicitud->foto;

// Eliminar archivo si existe
if (!empty($solicitud->foto) && file_exists($rutaArchivo)) {
unlink($rutaArchivo);
}

/* Eliminar registro */
SolicitudGafetes::where('id', $solicitud->id)->delete();

}

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Reporte eliminado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}

}

//---------------------------------------------------//
//---------- FORMULARIO DEL NO. DE REPORTE ----------//
//---------------------------------------------------//

public function formularioReporte($idEstacion, $noReporte){

$title = 'Solicitud de Gafetes (Formulario)';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Solicitud de Gafetes', '/solicitud-gafetes');
Breadcrumb::add($title, '');
// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);


$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idEstacion' => $idEstacion,
'noReporte' => $noReporte,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/gafetes/gafetes.formulario.datatable.init.js?v=1.1',
'/assets/js/gafetes/actions.formulario.init.js?v=1.0'
],
'help' => false
];

View::render('gafetes/formulario-index', $data,'main');

}

public function datatableGafetesFormulario($idEstacion, $noReporte){

// permisos
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
$permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');
$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');

// Obtener la estacion 
$datosEstacion = Estacion::find($idEstacion);
$query = SolicitudGafetes::query();

$gafetes = $query
->where('id_estacion', $datosEstacion->nombre)
->where('no_reporte', $noReporte)
->get();

echo json_encode([
"data" => $gafetes,
"permisos" => [
"eliminar" => $permisoEliminar,
"descargar" => $permisoDescargar,
"editar"   => $permisoEditar
]
]);

exit;
}

public function createReporteFormulario(){

header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);

if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

// DATA (multipart → usar $_POST)
$no_reporte = $_POST['no_reporte'] ?? null;
$idEstacion = $_POST['idEstacion'] ?? null;
$clave = $_POST['clave'] ?? null;
$nombre_g = $_POST['nombre_g'] ?? null;
$file  = $_FILES['foto'] ?? null;
$comentarios = '';
$status = 0;

if (!$clave) {
echo json_encode([
'success' => false,
'message' => 'La clave es obligatoria'
]);
exit;
}

if (!$nombre_g) {
echo json_encode([
'success' => false,
'message' => 'El nombre es obligatorio'
]);
exit;
}

// CONFIG RUTA
$carpeta = __DIR__ . '../../../public/uploads/archivos/';
if (!file_exists($carpeta)) {
mkdir($carpeta, 0777, true);
}

$nombreArchivo = null;
try {

// SUBIR ARCHIVO (opcional)
if ($file && $file['error'] === UPLOAD_ERR_OK) {

// Validar extensión
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// nombre único
$nombreArchivo = uniqid('rep_') . '.' . $extension;

$rutaDestino = $carpeta . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
throw new \Exception('No se pudo guardar el archivo');
}
}

// Obtener el usuario
$idUsuario = $this->userId();
$datosUsuario = Usuario::find($idUsuario);

// Obtener la estacion 
$datosEstacion = Estacion::find($idEstacion);

Capsule::beginTransaction();

try {
// GUARDAR EN BD
SolicitudGafetes::create([
'no_reporte' => $no_reporte,
'id_estacion' => $datosEstacion->nombre,
'usuario' => $datosUsuario->nombre,
'fecha' => date('Y-m-d'),
'clave' => $clave,  
'nombre' => $nombre_g,
'foto'  =>  $nombreArchivo,
'comentarios' => $comentarios,
'estatus' => $status,
]);


Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Registro guardado correctamente'
]);

} catch (\Throwable $e) {

// Si falla BD, borrar archivo
if ($nombreArchivo && file_exists($carpeta . $nombreArchivo)) {
unlink($carpeta . $nombreArchivo);
}

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}

exit;

} catch (\Exception $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);

}

}




}
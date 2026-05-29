<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\SolicitudGafetes;
use App\Models\SolicitudGafetesSeguimiento;
use App\Core\Breadcrumb;
use App\Models\Usuario;
use App\Services\ModuloService;
use App\Core\Session;
use App\Models\Estacion;
use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;

class GafetesController extends BaseController{
protected string $modulo = 'solicitud-gafetes';

// ============================================================
// SECURITY: Validación segura de uploads (Vulnerabilidad #11)
// ============================================================

/**
 * Valida uploads de archivos de forma segura
 * 
 * @param array $file Array $_FILES
 * @return array ['valid' => bool, 'error' => string|null, 'filename' => string|null]
 */
private function validateFileUpload(array $file): array
{
    $response = ['valid' => false, 'error' => null, 'filename' => null];
    
    // 1. Verificar que hay archivo
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $response['error'] = 'No se recibió archivo';
        return $response;
    }
    
    // 2. Verificar error de upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['error'] = 'Error al subir archivo: código ' . $file['error'];
        return $response;
    }
    
    // 3. Validar tamaño (ej: máximo 5MB)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        $response['error'] = 'El archivo excede el tamaño máximo de 5MB';
        return $response;
    }
    
    // 4. Validar MIME type real (no solo extensión)
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        $response['error'] = 'Error al validar tipo de archivo';
        return $response;
    }
    $mimeType = $finfo->file($file['tmp_name']);
    if (!$mimeType) {
        $response['error'] = 'No se pudo determinar el tipo de archivo';
        return $response;
    }
    
    $allowedMimes = [
        'image/jpeg',
        'image/png', 
        'image/gif',
        'application/pdf',
    ];
    
    if (!in_array($mimeType, $allowedMimes)) {
        $response['error'] = 'Tipo de archivo no permitido: ' . $mimeType;
        return $response;
    }
    
    // 5. Validar extensión coincide con MIME type
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mimeToExt = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'application/pdf' => ['pdf']
    ];
    
    if (!isset($mimeToExt[$mimeType]) || !in_array($extension, $mimeToExt[$mimeType])) {
        $response['error'] = 'La extensión no corresponde con el tipo de archivo';
        return $response;
    }
    
    // 6. Generar nombre seguro
    $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '', $file['name']);
    $filename = uniqid('gafete_') . '_' . substr($safeFilename, 0, 50) . '.' . $extension;
    
    $response['valid'] = true;
    $response['filename'] = $filename;
    $response['mime'] = $mimeType;
    
    return $response;
}

//---------------------------------------------------//
//----------------- PAGINA PRINCIPAL -----------------//
//---------------------------------------------------//
public function index(){
 
$title = 'Solicitud de Gafetes';

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'filtro_usuario' => $this->filtro_usuario,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
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
    
$filtro_usuario = Session::get('usuario');
$idEstacion = $filtro_usuario['id_estacion'] ?? null;

$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
$mostrar_fila_estacion = ((int)$idEstacion === 8);

$query = SolicitudGafetes::from('tb_solicitud_gafetes as g')
->leftJoin('tb_estaciones as e', 'g.id_estacion', '=', 'e.nombre')
->select('g.*', 'e.id as id_estacion_real')
->groupBy('g.no_reporte');

// Filtro si la sesion de la estacion no es 8
if (!empty($idEstacion) && (int)$idEstacion !== 8) {
$estacion = Estacion::find($idEstacion);

if ($estacion) {
$query->where('g.id_estacion', $estacion->nombre);
}
}

$rows = $query->orderBy('g.id', 'desc')->get()->toArray();
$data = [];

$estatus = [
"titulo" => '',
"color_badge" => '',
"color_css" => '',
"color_hexa" => ''
];

foreach ($rows as $row) {
$idReporte = $row['id'];
$idEstacionReal = $row['id_estacion_real'];
$no_reporte = $row['no_reporte'];
$fecha_reporte = $row['fecha'];
$nombre_usuario = $row['usuario'];
$nombre_estacion = $row['id_estacion'];
$estatus_reporte = $row['estatus'];

$disableDetail = false;
$disabledEdit = false;
$disabledDelete = false;

if (empty($row['fecha']) ||$row['fecha'] === '0000-00-00' ||$row['fecha'] === '-0001-11-30') {
$fecha_reporte = 'S/I';
} else {
$fecha_reporte = date('Y-m-d', strtotime($row['fecha']));
}

if ($estatus_reporte == 0) {
$estatus = [
"titulo" => 'Sin atender',
"color_badge" => 'bg-danger',
"color_css" => 'text-bg-danger',
"color_hexa" => '#ffb6af'
];

}else if ($estatus_reporte == 1) {
$estatus = [
"titulo" => 'En proceso',
"color_badge" => 'bg-warning',
"color_css" => 'text-bg-warning',
"color_hexa" => '#fcfcda'
];

}else if ($estatus_reporte == 2) {
$estatus = [
"titulo" => 'Finalizado',
"color_badge" => 'bg-success',
"color_css" => 'text-bg-warning',
"color_hexa" => '#fcfcda'
];

}else if ($estatus_reporte == 3 || $estatus_reporte == 4) {
$estatus = [
"titulo" => 'Entregada',
"color_badge" => 'bg-info',
"color_css" => 'text-bg-success',
"color_hexa" => '#b0f2c2'
];

}

//---------- PERMISOS ----------
$disableDetail = ($estatus_reporte == 0);
$disabledEdit = (!$permisoEditar || in_array($estatus_reporte, [1,2,3,4]));
$disabledDelete = (!$permisoEliminar || $estatus_reporte != 0);

if (empty($nombre_estacion)) {
$disableDetail = true;
$disabledEdit = true;
$disabledDelete = true;
}

$data[] = [
"idReporte" => $idReporte,
"idEstacionReal" => $idEstacionReal,
"no_reporte" => $no_reporte,
"fecha_reporte" => $fecha_reporte,
"nombre_usuario" => $nombre_usuario,
"nombre_estacion" => $nombre_estacion,
"estatus_reporte" => $estatus_reporte,
"estatus" =>$estatus,

"permisos" => [
"disableDetail" => $disableDetail,
"disabledEdit" => $disabledEdit,
"disabledDelete" => $disabledDelete
]

];

}

echo json_encode([
"data" => $data,
"filas_mostrar" => [
"mostrar_fila_estacion" => $mostrar_fila_estacion
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

$id = sanitize_input($_POST['id'] ?? null, 'int');
$clave = sanitize_input($_POST['clave'] ?? null, 'string');
$nombre_g = sanitize_input($_POST['nombre_g'] ?? null, 'string');
$file  = $_FILES['foto'] ?? null;
$comentarios = '';
$status = 0;

// Validar campos obligatorios
$errors = validate_input($_POST, [
    'clave' => 'required|max:50',
    'nombre_g' => 'required|max:255'
]);

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

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
$carpeta = __DIR__ . '../../../public/uploads/archivos/solicitud-gafetes/';
if (!file_exists($carpeta)) {
 mkdir_safe($carpeta, true);
}

$nombreArchivo = null;
try {

// SUBIR ARCHIVO (opcional)
if ($file && $file['error'] === UPLOAD_ERR_OK) {

// SECURITY: Validar archivo de forma segura (Vulnerabilidad #11)
$validation = $this->validateFileUpload($file);

if (!$validation['valid']) {
echo json_encode([
'success' => false,
'message' => $validation['error']
]);
exit;
}

$nombreArchivo = $validation['filename'];
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
$rutaBase = __DIR__ . '/../../../public/uploads/archivos/solicitud-gafetes/';

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

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$datosEstacion = Estacion::find($idEstacion);
$title = 'Solicitud de Gafetes Formulario (' . $datosEstacion->nombre . ')';

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
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
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

public function datatableGafetesFormulario($idEstacion, $noReporte)
{
    
$permisoDescargar = ModuloService::validaPermiso($this->modulo, 'descargar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

$datosEstacion = Estacion::find($idEstacion);
$query = SolicitudGafetes::query();

$rows = $query->where('id_estacion', $datosEstacion->nombre)
->where('no_reporte', $noReporte)
->get()
->toArray();

$data = [];

$estatus = [
"titulo" => '',
"color_css" => '',
"color_hexa" => ''
];

foreach ($rows as $row) {
$idGafete = $row['id'];
$clave = $row['clave'];
$nombre_completo = $row['nombre'];
$foto_gafete = $row['foto'];

$data[] = [
"idGafete" => $idGafete,
"clave" => $clave,
"nombre_completo" => $nombre_completo,
"foto_gafete" => $foto_gafete,
"estatus" =>$estatus
];

}

echo json_encode([
"data" => $data,
"permisos" => [
"eliminar" => $permisoEliminar,
"descargar" => $permisoDescargar
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

$no_reporte = sanitize_input($_POST['no_reporte'] ?? null, 'int');
$idEstacion = sanitize_input($_POST['idEstacion'] ?? null, 'int');
$clave = sanitize_input($_POST['clave'] ?? null, 'string');
$nombre_g = sanitize_input($_POST['nombre_g'] ?? null, 'string');
$file  = $_FILES['foto'] ?? null;
$comentarios = '';
$status = 0;

// Validar campos obligatorios
$errors = validate_input($_POST, [
    'clave' => 'required|max:50',
    'nombre_g' => 'required|max:255'
]);

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

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
$carpeta = __DIR__ . '../../../public/uploads/archivos/solicitud-gafetes/';
if (!file_exists($carpeta)) {
 mkdir_safe($carpeta, true);
}

$nombreArchivo = null;
try {

// SUBIR ARCHIVO (opcional)
if ($file && $file['error'] === UPLOAD_ERR_OK) {

// SECURITY: Validar archivo de forma segura (Vulnerabilidad #11)
$validation = $this->validateFileUpload($file);

if (!$validation['valid']) {
echo json_encode([
'success' => false,
'message' => $validation['error']
]);
exit;
}

$nombreArchivo = $validation['filename'];
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

public function deleteReporteFormulario()
{
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

try {
 // Buscar registro
$registro_reporte = SolicitudGafetes::find($id);

if (!$registro_reporte) {
echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
exit;
}

// Ruta base CORREGIDA
$rutaBase = __DIR__ . '/../../../public/uploads/archivos/solicitud-gafetes/';
$rutaArchivo = $rutaBase . $registro_reporte->foto;

// TRANSACCIÓN
Capsule::beginTransaction();

// Eliminar archivo si existe
if (!empty($registro_reporte->foto) && file_exists($rutaArchivo)) {
unlink($rutaArchivo);
}

// Eliminar registro (puedes usar delete o estado = 0)
$registro_reporte->delete();
Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Registro eliminado correctamente'
]);

} catch (\Throwable $e) {
Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => 'Error al eliminar',
'error'   => $e->getMessage()
]);
}

exit;
}

//---------------------------------------------------//
//---------------- PAGINA SEGUIMIENTO ---------------//
//---------------------------------------------------//
public function formularioSeguimiento($idEstacion, $noReporte){

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$datosEstacion = Estacion::find($idEstacion);
$title = 'Detalle Solicitud de Gafetes (' . $datosEstacion->nombre . ')';

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
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/gafetes/gafetes.detalle.datatable.init.js?v=1.1',
'/assets/js/gafetes/gafetes.seguimiento.timeline.js?v=1.0',
'/assets/js/gafetes/actions.detalle.init.js?v=1.0'
],
'help' => false
];

View::render('gafetes/seguimiento-index', $data,'main');

}

public function timelineSeguimiento($idEstacion, $noReporte)
{

$seguimientos = SolicitudGafetesSeguimiento::with('usuario')
->where('id_estacion', $idEstacion)
->where('no_reporte', $noReporte)
->orderBy('seguimiento')
->get();

if ($seguimientos->isEmpty()) {

$datosEstacion = Estacion::find($idEstacion);

$solicitud = SolicitudGafetes::where('id_estacion', $datosEstacion->nombre)
->where('no_reporte', $noReporte)
->first();

if ($solicitud) {

switch ($solicitud->estatus) {
case 0:
$pasos = [0];
break;

case 1:
case 2:
$pasos = [0, 1];
break;

case 3:
case 4:
$pasos = [0, 1, 2];
break;

default:
$pasos = [];
break;
}

$seguimientos = collect($pasos)->map(function ($paso) use ($solicitud) {
return (object)[
'seguimiento' => $paso,
'fecha_hora' => $solicitud->fecha,
'usuario' => (object)[
'nombre' => 'Sin información'
]
];
});
}
}

echo json_encode([
'data' => $seguimientos->map(function($data){
return [
'seguimiento' => $data->seguimiento,
'fecha_hora' => $data->fecha_hora,
'usuario' => $data->usuario->nombre ?? 'Sistema'
];
})
]);
}

public function updateSeguimientoGafetes()
{

header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);

$idEstacion = $data['idEstacion'] ?? null;
$no_reporte = $data['no_reporte'] ?? null;
$idSeguimiento = $data['idSeguimiento'] ?? null;

if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
echo json_encode([
'success' => false,
'message' => 'Sin permisos'
]);
return;
}

if (!$idEstacion || !$no_reporte || !$idSeguimiento) {
echo json_encode([
'success' => false,
'message' => 'Datos incompletos'
]);
exit;
}

$datosEstacion = Estacion::find($idEstacion);

try {

$registro_existente = SolicitudGafetesSeguimiento::where('id_estacion', $idEstacion)
->where('no_reporte', $no_reporte)
->where('seguimiento', $idSeguimiento)
->exists();

if ($registro_existente) {
echo json_encode([
'success' => false,
'message' => 'Este seguimiento ya fue registrado'
]);
exit;
}

$ultimo_seguimiento = SolicitudGafetesSeguimiento::where('id_estacion', $idEstacion)
->where('no_reporte', $no_reporte)
->max('seguimiento');

$ultimo_seguimiento = $ultimo_seguimiento ?? 0;

if ($idSeguimiento != ($ultimo_seguimiento + 1)) {
echo json_encode([
'success' => false,
'message' => 'El seguimiento no es válido en el orden'
]);
exit;
}

Capsule::beginTransaction();

try {

SolicitudGafetesSeguimiento::create([
'id_estacion' => $idEstacion,
'no_reporte'  => $no_reporte,
'seguimiento' => $idSeguimiento,
'id_usuario'     => $this->userId()
]);

if ($idSeguimiento == 1) {
SolicitudGafetes::where('id_estacion', $datosEstacion->nombre)
->where('no_reporte', $no_reporte)
->update(['estatus' => 1]);
}else if($idSeguimiento == 2){
SolicitudGafetes::where('id_estacion', $datosEstacion->nombre)
->where('no_reporte', $no_reporte)
->update(['estatus' => 2]);
}else if($idSeguimiento == 3){
SolicitudGafetes::where('id_estacion', $datosEstacion->nombre)
->where('no_reporte', $no_reporte)
->update(['estatus' => 4]);
}

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Seguimiento guardado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}


} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}


}





}
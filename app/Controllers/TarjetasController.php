<?php
namespace App\Controllers;  
use App\Core\View;
use App\Models\SolicitudTarjetas;
use App\Models\SolicitudTarjetasImagen;
use App\Models\SolicitudTarjetasSeguimiento;
use App\Core\Breadcrumb;
use App\Models\Usuario;
use App\Services\ModuloService;
use App\Services\ModuleStationService;
use App\Core\Session;
use App\Models\Estacion;
use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;

class TarjetasController extends BaseController{
protected string $modulo = 'solicitud-tarjetas';

public function index(){

$title = 'Solicitud de Tarjetas';

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

if (!$this->guardModuleAccess('solicitud-tarjetas', $title, 'main')) {
return;
}

$permisos = ModuloService::permisosSesion($this->modulo);

// Compute pending counts per station for the selector
$pendingStations = SolicitudTarjetas::selectRaw('COUNT(*) as total, id_estacion')
->where('estatus', 0)
->groupBy('id_estacion')
->get()
->keyBy('id_estacion');

$allStationIds = ModuleStationService::getAvailableStations('solicitud-tarjetas');
$pendientesMap = [];
foreach ($allStationIds as $s) {
$count = isset($pendingStations[$s['id']]) ? (int)$pendingStations[$s['id']]->total : 0;
$pendientesMap['estacion_' . $s['id']] = $count;
}
$pendientesMap['total'] = array_sum($pendientesMap);
$pendientesData = $pendientesMap;

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'moduleStationKey' => 'solicitud-tarjetas',
'pendientesData' => $pendientesData,
'filtro_usuario' => $this->filtro_usuario,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/core/module-station-selector.js',
'/assets/js/tarjetas/tarjetas.datatable.init.js?v=' . time(),
'/assets/js/tarjetas/actions.init.js?v=' . time()
]
];

View::render('tarjetas/index', $data, 'main');
}

public function datatableTarjetas(){

$ctx = ModuleStationService::getContext('solicitud-tarjetas');
$idEstacion = $ctx['id_estacion'];

$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');
$mostrar_fila_estacion = ((int)$idEstacion === 8);

$query = SolicitudTarjetas::from('tb_solicitud_tarjetas as g')
->leftJoin('tb_estaciones as e', 'g.id_estacion', '=', 'e.nombre')
->select('g.*', 'e.id as id_estacion_real')
->groupBy('g.no_solicitud');

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
$idSolicitud = $row['id'];
$idEstacionReal = $row['id_estacion_real'];
$no_solicitud = $row['no_solicitud'];
$fecha_solicitud = $row['fecha'];
$nombre_usuario = $row['id_usuario'];
$nombre_estacion = $row['id_estacion'];
$estatus_solicitud = $row['estatus'];

$disableDetail = false;
$disabledEdit = false;
$disabledDelete = false;

if (empty($row['fecha']) || $row['fecha'] === '0000-00-00' || $row['fecha'] === '-0001-11-30') {
$fecha_solicitud = 'S/I';
} else {
$fecha_solicitud = date('Y-m-d', strtotime($row['fecha']));
}

if ($estatus_solicitud == 0) {
$estatus = [
"titulo" => 'Sin atender',
"color_badge" => 'bg-danger',
"color_css" => 'text-bg-danger',
"color_hexa" => '#ffb6af'
];

}else if ($estatus_solicitud == 1) {
$estatus = [
"titulo" => 'En proceso',
"color_badge" => 'bg-warning',
"color_css" => 'text-bg-warning',
"color_hexa" => '#fcfcda'
];

}else if ($estatus_solicitud == 2) {
$estatus = [
"titulo" => 'Finalizado',
"color_badge" => 'bg-success',
"color_css" => 'text-bg-warning',
"color_hexa" => '#fcfcda'
];

}else if ($estatus_solicitud == 3 || $estatus_solicitud == 4) {
$estatus = [
"titulo" => 'Entregada',
"color_badge" => 'bg-info',
"color_css" => 'text-bg-success',
"color_hexa" => '#b0f2c2'
];

}

//---------- PERMISOS ----------
$disableDetail = ($estatus_solicitud == 0);
$disabledEdit = (!$permisoEditar || in_array($estatus_solicitud, [1,2,3,4]));
$disabledDelete = (!$permisoEliminar || $estatus_solicitud != 0);

if (empty($nombre_estacion) || $nombre_estacion == "Administrador") {
$disableDetail = true;
$disabledEdit = true;
$disabledDelete = true;
}

$data[] = [
"idSolicitud" => $idSolicitud,
"idEstacionReal" => $idEstacionReal,
"no_solicitud" => $no_solicitud,
"fecha_solicitud" => $fecha_solicitud,
"nombre_usuario" => $nombre_usuario,
"nombre_estacion" => $nombre_estacion,
"estatus_solicitud" => $estatus_solicitud,
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
$file  = $_FILES['archivo'] ?? null;
$razon_social = sanitize_input($_POST['razon_social'] ?? null, 'string');
$nombre_usuario = sanitize_input($_POST['nombre_usuario'] ?? null, 'string');
$vehiculo = sanitize_input($_POST['vehiculo'] ?? null, 'string');
$placas = sanitize_input($_POST['placas'] ?? null, 'string');
$no_unidad = sanitize_input($_POST['no_unidad'] ?? null, 'string');
$tarjeta = sanitize_input($_POST['tarjeta'] ?? null, 'string');
$tipo_tarjeta = sanitize_input($_POST['tipo_tarjeta'] ?? null, 'string');
$comentarios = '';
$status = 0;

// Validar campos obligatorios
$errors = validate_input($_POST, [
'razon_social' => 'required|max:255',
'nombre_usuario' => 'required|max:255',
'vehiculo' => 'required|max:255',
'placas' => 'required|max:20',
'no_unidad' => 'required|max:20',
'tarjeta' => 'required|max:50',
'tipo_tarjeta' => 'required|max:50'
]);

if (!empty($errors)) {
echo json_encode(['success' => false, 'errors' => $errors]);
exit;
}

if (!$razon_social || !$nombre_usuario || !$vehiculo || !$placas || !$no_unidad || !$tarjeta || !$tipo_tarjeta) {
echo json_encode([
'success' => false,
'message' => 'Datos incompletos incompletos'
]);
exit;
}

// CONFIG RUTA
$carpeta = __DIR__ . '../../../public/uploads/archivos/solicitud-tarjetas/';
// SECURITY: BAJO #35 - Usar mkdir_safe con permisos 0755
if (!file_exists($carpeta)) {
mkdir_safe($carpeta, true);
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

// Obtener la estacion desde el contexto del módulo
$ctx = ModuleStationService::getContext('solicitud-tarjetas');
$idEstacion = $ctx['id_estacion'];
$datosEstacion = Estacion::find($idEstacion);

// Obtener el max(no_solicitud) por estación
$maxSolicitud = SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->max('no_solicitud');

$id_reporte = $maxSolicitud ? $maxSolicitud + 1 : 1;
$no_solicitud = ($id == 0) ? $id_reporte : $id;

Capsule::beginTransaction();

try {

// GUARDAR EN BD
SolicitudTarjetas::create([
'no_solicitud' => $no_solicitud,
'id_estacion' => $datosEstacion->nombre,
'id_usuario' => $datosUsuario->nombre,
'fecha' => date('Y-m-d'),
'razon_social' => $razon_social,  
'no_flotilla' => $nombre_usuario,
'vehiculo' => $vehiculo,  
'placas' => $placas,
'no_unidad' => $no_unidad,  
'tarjeta' => $tarjeta,
'tipo_tarjeta' => $tipo_tarjeta,  
'comentarios' => $comentarios,
'estatus' => $status,
]);

// 🔥 GUARDAR IMAGEN SOLO SI EXISTE
if ($nombreArchivo) {
SolicitudTarjetasImagen::create([
'ruta'         => $nombreArchivo,
'no_solicitud' => $no_solicitud,
'estacion'     => $datosEstacion->nombre,
]);
}

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Solicitud guardada correctamente',
'idEstacion' => $idEstacion,
'no_solicitud' => $no_solicitud
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
'no_solicitud' => 0
]);
}

} catch (\Exception $e) {
Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
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

$solicitud_principal = SolicitudTarjetas::find($id);
if (!$solicitud_principal) {
echo json_encode(['success' => false, 'message' => 'No. de solicitud no encontrado']);
exit;
}

try {

$solicitudes = SolicitudTarjetas::where('id_estacion', $solicitud_principal->id_estacion)
->where('no_solicitud', $solicitud_principal->no_solicitud)
->get();

if ($solicitudes->isEmpty()) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
exit;
}

$rutaBase = __DIR__ . '/../../../public/uploads/archivos/solicitud-tarjetas/';

Capsule::beginTransaction();

$imagenes = SolicitudTarjetasImagen::where('no_solicitud', $solicitud_principal->no_solicitud)
->where('estacion', $solicitud_principal->id_estacion)
->get();

foreach ($imagenes as $img) {

$rutaArchivo = $rutaBase . $img->ruta;
if (file_exists($rutaArchivo)) {
unlink($rutaArchivo);
}

SolicitudTarjetasImagen::where('id', $img->id)->delete();
}

foreach ($solicitudes as $solicitud) {
SolicitudTarjetas::where('id', $solicitud->id)->delete();
}

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Reporte e imágenes eliminados correctamente'
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
public function formularioReporte($idEstacion, $noSolicitud){

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$datosEstacion = Estacion::find($idEstacion);
$title = 'Solicitud de Tarjetas Formulario (' . $datosEstacion->nombre . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Solicitud de Tarjetas', '/solicitud-tarjetas');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idEstacion' => $idEstacion,
'noSolicitud' => $noSolicitud,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/tarjetas/tarjetas.formulario.datatable.init.js?v=' . time(),
'/assets/js/tarjetas/actions.formulario.init.js?v=' . time()
],
'help' => false
];

View::render('tarjetas/formulario-index', $data,'main');

}

public function datatableTarjetasFormulario($idEstacion, $noSolicitud)
{

// permisos
$permisoEditar = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

// Obtener la estacion 
$datosEstacion = Estacion::find($idEstacion);
$query = SolicitudTarjetas::query();

$tarjetas = $query
->where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $noSolicitud)
->get();

echo json_encode([
"data" => $tarjetas,
"permisos" => [
"editar" => $permisoEditar,
"eliminar" => $permisoEliminar
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

$no_solicitud = sanitize_input($data['no_solicitud'] ?? null, 'int');
$idEstacion = sanitize_input($data['idEstacion'] ?? null, 'int');
$razon_social = sanitize_input($data['razon_social'] ?? null, 'string');
$nombre_usuario = sanitize_input($data['nombre_usuario'] ?? null, 'string');
$vehiculo = sanitize_input($data['vehiculo'] ?? null, 'string');
$placas = sanitize_input($data['placas'] ?? null, 'string');
$no_unidad = sanitize_input($data['no_unidad'] ?? null, 'string');
$tarjeta = sanitize_input($data['tarjeta'] ?? null, 'string');
$tipo_tarjeta = sanitize_input($data['tipo_tarjeta'] ?? null, 'string');
$status = 0;

// Validar campos obligatorios
$errors = validate_input($data, [
'razon_social' => 'required|max:255',
'nombre_usuario' => 'required|max:255',
'vehiculo' => 'required|max:255',
'placas' => 'required|max:20',
'no_unidad' => 'required|max:20',
'tarjeta' => 'required|max:50',
'tipo_tarjeta' => 'required|max:50'
]);

if (!empty($errors)) {
echo json_encode(['success' => false, 'errors' => $errors]);
exit;
}

if (!$razon_social || !$nombre_usuario || !$vehiculo || !$placas || !$no_unidad || !$tarjeta || !$tipo_tarjeta) {
echo json_encode([
'success' => false,
'message' => 'Datos incompletos incompletos'
]);
exit;
}

try {

// Obtener el usuario
$idUsuario = $this->userId();
$datosUsuario = Usuario::find($idUsuario);

// Obtener la estacion 
$datosEstacion = Estacion::find($idEstacion);

Capsule::beginTransaction();

try {

// GUARDAR EN BD
SolicitudTarjetas::create([
'no_solicitud' => $no_solicitud,
'id_estacion' => $datosEstacion->nombre,
'id_usuario' => $datosUsuario->nombre,
'fecha' => date('Y-m-d'),
'razon_social' => $razon_social,  
'no_flotilla' => $nombre_usuario,
'vehiculo' => $vehiculo,  
'placas' => $placas,
'no_unidad' => $no_unidad,  
'tarjeta' => $tarjeta,
'tipo_tarjeta' => $tipo_tarjeta,  
//'comentarios' => $comentarios,
'estatus' => $status,
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Solicitud guardada correctamente'
]);



} catch (\Throwable $e) {

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}

} catch (\Exception $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);

}

}


public function updateReporteFormulario(){

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

$id = $data['id'] ?? null;
$razon_social = $data['razon_social'] ?? null;
$nombre_usuario = $data['nombre_usuario'] ?? null;
$vehiculo = $data['vehiculo'] ?? null;
$placas = $data['placas'] ?? null;
$no_unidad = $data['no_unidad'] ?? null;
$tarjeta = $data['tarjeta'] ?? null;
$tipo_tarjeta = $data['tipo_tarjeta'] ?? null;

if (!$id || !$razon_social || !$nombre_usuario || !$vehiculo || !$placas || !$no_unidad || !$tarjeta || !$tipo_tarjeta) {
echo json_encode([
'success' => false,
'message' => 'Datos incompletos incompletos'
]);
exit;
}

$registro = SolicitudTarjetas::find($id);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}

$registro->razon_social = $razon_social;
$registro->no_flotilla = $nombre_usuario;
$registro->vehiculo = $vehiculo;
$registro->placas = $placas;
$registro->no_unidad = $no_unidad;
$registro->tarjeta = $tarjeta;
$registro->tipo_tarjeta = $tipo_tarjeta;
$registro->save();

echo json_encode([
'success' => true,
'message' => 'Registro actualizado correctamente'
]);

}

public function deleteReporteFormulario(){
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
$registro_reporte = SolicitudTarjetas::find($id);

if (!$registro_reporte) {
echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
exit;
}

// TRANSACCIÓN
Capsule::beginTransaction();

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


//---------- SEGUIMIENTO DE LA SOLICITUD ----------//
public function timelineSeguimiento($idEstacion, $noReporte)
{

$seguimientos = SolicitudTarjetasSeguimiento::with('usuario')
->where('id_estacion', $idEstacion)
->where('no_reporte', $noReporte)
->orderBy('seguimiento')
->get();

if ($seguimientos->isEmpty()) {

$datosEstacion = Estacion::find($idEstacion);

$solicitud = SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $noReporte)
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

//---------- ACTUALIZAR SEGUIMIENTO DE LA SOLICITUD ----------//
public function updateSeguimientoTarjetas()
{
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);

$idEstacion = $data['idEstacion'] ?? null;
$no_reporte = $data['no_reporte'] ?? null;
$idSeguimiento = $data['idSeguimiento'] ?? null;

if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
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

$registro_existente = SolicitudTarjetasSeguimiento::where('id_estacion', $idEstacion)
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

$ultimo_seguimiento = SolicitudTarjetasSeguimiento::where('id_estacion', $idEstacion)
->where('no_reporte', $no_reporte)
->max('seguimiento');

$ultimo_seguimiento = $ultimo_seguimiento ?? 0;

/*
if ($idSeguimiento != ($ultimo_seguimiento + 1)) {
echo json_encode([
'success' => false,
'message' => 'El seguimiento no es válido en el orden'
]);
exit;
}
*/

Capsule::beginTransaction();

try {

SolicitudTarjetasSeguimiento::create([
'id_estacion' => $idEstacion,
'no_reporte'  => $no_reporte,
'seguimiento' => $idSeguimiento,
'id_usuario'     => $this->userId()
]);

if ($idSeguimiento == 1) {
SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $no_reporte)
->update(['estatus' => 1]);
}else if($idSeguimiento == 2){
SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $no_reporte)
->update(['estatus' => 2]);
}else if($idSeguimiento == 3){
SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $no_reporte)
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

//---------------------------------------------------//
//----------- FORMULARIO DEL SEGUIMIENTO -----------//
//-------------------------------------------------//
public function formularioSeguimiento($idEstacion, $noSolicitud){

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$datosEstacion = Estacion::find($idEstacion);
$title = 'Detalle Solicitud de Tarjetas (' . $datosEstacion->nombre . ')';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Solicitud de Tarjetas', '/solicitud-tarjetas');
Breadcrumb::add($title, '');
// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idEstacion' => $idEstacion,
'noSolicitud' => $noSolicitud,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/tarjetas/tarjetas.detalle.datatable.init.js',
'/assets/js/tarjetas/tarjetas.seguimiento.timeline.js?v=' . time(),
'/assets/js/tarjetas/actions.detalle.init.js?v=' . time()
],
'help' => false
];

View::render('tarjetas/seguimiento-index', $data,'main');
}

function updateComentarioTarjetas(){

header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);

$idEstacion   = $data['id_estacion'] ?? null;
$no_solicitud = $data['no_solicitud'] ?? null;
$comentarios  = $data['comentarios'] ?? null;

if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
echo json_encode([
'success' => false,
'message' => 'Sin permisos'
]);
return;
}

if (!$idEstacion || !$no_solicitud) {
echo json_encode([
'success' => false,
'message' => 'Datos incompletos'
]);
exit;
}

$datosEstacion = Estacion::find($idEstacion);

try {

$actualizados = SolicitudTarjetas::where('id_estacion', $datosEstacion->nombre)
->where('no_solicitud', $no_solicitud)
->update([
'comentarios' => $comentarios
]);

if ($actualizados === 0) {
echo json_encode([
'success' => false,
'message' => 'No se encontraron registros para actualizar'
]);
return;
}

echo json_encode([
'success' => true,
'message' => 'Comentario actualizado correctamente'
]);

} catch (\Exception $e) {

echo json_encode([
'success' => false,
'message' => 'Error al actualizar comentario',
]);
}
}


public function obtenerArchivoTarjeta($idEstacion, $noSolicitud)
{
header('Content-Type: application/json');
$datosEstacion = Estacion::find($idEstacion);
$archivo = SolicitudTarjetasImagen::where('estacion', $datosEstacion->nombre)
->where('no_solicitud', $noSolicitud)
->orderBy('id', 'desc')
->first();

echo json_encode([
'success' => true,
'archivo' => $archivo ? $archivo->ruta : null
]);
}


}
<?php
namespace App\Controllers;
use App\Core\View;
use App\Models\Modulo;
use App\Models\Puestos;
use App\Models\ModulosPuestos;
use App\Models\ModulosUsuarios;
use App\Models\ModuloDptoOperativo;
use App\Models\ModuloSubDptoOperativo;
use App\Models\ModuloPuestoDptoOperativo;
use App\Models\ModuloPuestoSubDptoOperativo;
use App\Models\ModuloUsuarioDptoOperativo;
use App\Models\ModuloUsuarioSubDptoOperativo;
use App\Core\Breadcrumb;
use App\Models\Usuario;
use App\Services\ModuloService;
use App\Core\Session;
use App\Core\Auth;
use Illuminate\Database\Capsule\Manager as Capsule;

class ConfiguracionController extends BaseController{
protected string $modulo = 'configuracion';

//----- Configuracion de Modulos 
public function index(){
$title = 'Configuración';

Breadcrumb::add('Home', '/home');
Breadcrumb::add($title, '');

$usuario = Auth::user();
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'links' =>[],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time()
],
'help' => false

];

View::render('configuracion/configuracion-index', $data,'configuracion');
}

//--------------------------------------------------------//
//---------- CONFIGURACION DE MODULOS (PORTAL) ----------//
//------------------------------------------------------//

public function modulosIndex(){

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$title = 'Módulos';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/configuracion/modulos.datatable.init.js?v=' . time(),
'/assets/js/configuracion/actions.modulos.datatable.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/configuracion-modulos-index', $data,'configuracion');
}

public function datatableModulos(){
$filtro_usuario = Session::get('usuario');
$idEstacion = $filtro_usuario['id_estacion'] ?? null;

$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

$rows = Modulo::all();
$data = [];

$estatus = [
"titulo" => '',
"color_badge" => '',
"color_css" => '',
"color_hexa" => ''
];

foreach ($rows as $row) {
$idModulo = $row['id'];
$nombre_modulo = $row['nombre'] ?: 'S/I';
$clave = $row['clave'] ?: 'S/I';
$ruta = $row['ruta'] ?: 'S/I';
$icono = $row['icono'] ?: 'ti ti-box';
$estatus = $row['activo'];

$editar = (!$permisoEditar || $estatus != 1);
$eliminar = (!$permisoEliminar || $estatus != 1);

if ($estatus == 0) {
$estatus = [
"titulo" => 'Eliminado',
"color_badge" => 'bg-danger',
"color_css" => 'text-bg-danger',
"color_hexa" => '#ffb6af'
];

}else if($estatus == 1){
$estatus = [
"titulo" => 'Activo',
"color_badge" => 'bg-success',
"color_css" => 'text-bg-success',
"color_hexa" => '#b0f2c2'
];

}

$data[] = [
"idModulo" => $idModulo,
"nombre_modulo" => $nombre_modulo,
"clave" => $clave,
"ruta" => $ruta,
"icono" => $icono,
"estatus" =>$estatus,

"permisos" => [
"disabledEdit" => $editar,
"disabledDelete" => $eliminar
]

];

}

echo json_encode([
"data" => $data
]);

exit;
}

public function createModulos()
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

$idModulo = $data['idModulo'] ?? null;
$nombre_modulo = $data['nombre_modulo'] ?? null;
$clave = $data['clave'] ?? null;
$ruta = $data['ruta'] ?? null;
$icono = $data['icono'] ?? null;
$estatus = 1;

if (!$nombre_modulo || !$clave || !$ruta) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
return;
}

try {

Capsule::beginTransaction();

try {
// GUARDAR EN BD
Modulo::create([
'nombre' => $nombre_modulo,
'clave' => $clave,
'ruta' => $ruta,
'icono' => $icono,  
'activo' => $estatus
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Reporte guardado correctamente'
]);

} catch (\Throwable $e) {

echo json_encode([
'success' => false,
'message' => $e->getMessage(),
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

public function updateModulos()
{

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

$idModulo = $data['idModulo'] ?? null;
$nombre_modulo = $data['nombre_modulo'] ?? null;
$clave = $data['clave'] ?? null;
$ruta = $data['ruta'] ?? null;
$icono = $data['icono'] ?? null;

if (!$nombre_modulo || !$clave || !$ruta) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
return;
}

$registro = Modulo::find($idModulo);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}

$registro->nombre = $nombre_modulo;
$registro->clave = $clave;
$registro->ruta = $ruta;
$registro->icono = $icono;
$registro->save();

echo json_encode([
'success' => true,
'message' => 'Registro actualizado correctamente'
]);

}

public function deleteModulos(){
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

$idModulo = $data['id'] ?? null;
$estatus = 0;

if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$idModulo) {
echo json_encode(['success' => false,'message' => 'ID requerido']);
exit;
}

$registro = Modulo::find($idModulo);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}

$registro->activo = $estatus;
$registro->save();

echo json_encode([
'success' => true,
'message' => 'Registro eliminado correctamente'
]);

}

//-----------------------------------------------//
//---------- MODULOS PUESTOS (PORTAL) ----------//
//---------------------------------------------//

public function modulosPuestosIndex(){

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$title = 'Módulos (Puestos)';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/configuracion/modulos.puestos.datatable.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/modulos-puestos-index', $data,'configuracion');
}

public function modulosPuestosFormulario($idPuesto){

$datosPuesto = Puestos::find($idPuesto);
$title = $datosPuesto->tipo_puesto;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add('Módulos (Puestos)', '/configuracion/modulos-puestos');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idPuesto' => $idPuesto,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/libs/select2/dist/js/select2.min.js',
'/assets/js/configuracion/modulos.puestos.configuracion.datatable.init.js?v=' . time(),
'/assets/js/configuracion/actions.modulos.puestos.configuracion.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/modulos-puestos-configuracion', $data,'configuracion');
}

public function datatableModulosPuestos($idPuesto){

$filtro_usuario = Session::get('usuario');
$idEstacion = $filtro_usuario['id_estacion'] ?? null;

$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

$rows = Modulo::whereHas('roles', function($query) use ($idPuesto) {
$query->where('puesto_id', $idPuesto);
})
->with(['roles' => function($query) use ($idPuesto) {
$query->where('puesto_id', $idPuesto);
}])
->get()
->toArray();
$data = [];

$estatus = [
"titulo" => '',
"color_badge" => '',
"color_css" => '',
"color_hexa" => ''
];

foreach ($rows as $row) {
$rol = $row['roles'][0] ?? null;    
$pivot = $rol['pivot'] ?? null;

$idModuloPuesto = $pivot['id'] ?? null;
$nombre_modulo = $row['nombre'];

$tbLeer = $pivot['leer'] ?? 0;
$tbCrear = $pivot['crear'] ?? 0;
$tbEditar = $pivot['editar'] ?? 0;
$tbEliminar = $pivot['eliminar'] ?? 0;
$tbDescargar = $pivot['descargar'] ?? 0;

$data[] = [
"idModuloPuesto" => $idModuloPuesto,
"nombre_modulo" => $nombre_modulo,
"tbLeer" => $tbLeer,
"tbCrear" => $tbCrear,
"tbEditar" => $tbEditar,
"tbEliminar" =>$tbEliminar,
"tbDescargar" =>$tbDescargar,

"permisos" => [
"noEdit" => $permisoEditar,
"noDelete" => $permisoEliminar
]

];
}

echo json_encode([
"data" => $data
]);

exit;
}

public function getModulosPuestos($idPuesto, $moduloActual = null)
{
header('Content-Type: application/json');

$query = Modulo::where('activo', 1);

if (!$moduloActual) {

$query->whereDoesntHave('roles', function ($q) use ($idPuesto) {
$q->where('puesto_id', $idPuesto);
});

} else {
$query->where(function ($q) use ($idPuesto, $moduloActual) {
$q->where('id', $moduloActual);
$q->orWhereDoesntHave('roles', function ($sub) use ($idPuesto) {
$sub->where('puesto_id', $idPuesto);
});

});
}

$data = $query->select('id', 'nombre')
->orderBy('nombre', 'asc')
->get();

echo json_encode($data);
exit;
}

public function getModulosPuestosDetalle($idPuestoModulo)
{
header('Content-Type: application/json');

$modulo = Modulo::whereHas('roles', function ($query) use ($idPuestoModulo) {
$query->where('roles_modulos.id', $idPuestoModulo);
})
->with(['roles' => function ($query) use ($idPuestoModulo) {
$query->where('roles_modulos.id', $idPuestoModulo);
}])
->first();

if (!$modulo) {
echo json_encode(['success' => false]);
exit;
}

$rol = $modulo->roles->first();
$detalle = [
'id'          => $rol->pivot->id,
'modulo_id'   => $modulo->id,
'modulo'      => $modulo->nombre,
'leer'        => $rol->pivot->leer,
'crear'       => $rol->pivot->crear,
'editar'      => $rol->pivot->editar,
'eliminar'    => $rol->pivot->eliminar,
'descargar'   => $rol->pivot->descargar,
];

echo json_encode([
'success' => true,
'detalle' => $detalle
]);

exit;
}

public function createModulosPuestos()
{
header('Content-Type: application/json; charset=utf-8');

if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

$idPuesto   = $_POST['idPuesto'] ?? null;
$modulo_id  = $_POST['modulo_id'] ?? null;

$leer       = (int) ($_POST['leer'] ?? 0);
$crear      = (int) ($_POST['crear'] ?? 0);
$editar     = (int) ($_POST['editar'] ?? 0);
$eliminar   = (int) ($_POST['eliminar'] ?? 0);
$descargar  = (int) ($_POST['descargar'] ?? 0);

if (!$idPuesto || !$modulo_id) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
exit;
}

try {

$exists = ModulosPuestos::where('puesto_id', $idPuesto)
->where('modulo_id', $modulo_id)
->exists();

if ($exists) {
echo json_encode([
'success' => false,
'message' => 'Este módulo ya se encuentra registrado'
]);
exit;
}

Capsule::beginTransaction();

ModulosPuestos::create([
'puesto_id' => $idPuesto,
'modulo_id' => $modulo_id,
'leer' => $leer,
'crear' => $crear,
'editar' => $editar,
'eliminar' => $eliminar,
'descargar' => $descargar
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Módulo asignado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}

}

public function updateModulosPuestos($id)
{
header('Content-Type: application/json; charset=utf-8');

if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para editar'
]);
exit;
}

$idPuesto   = $_POST['idPuesto'] ?? null;
$modulo_id  = $_POST['modulo_id'] ?? null;

$leer       = (int) ($_POST['leer'] ?? 0);
$crear      = (int) ($_POST['crear'] ?? 0);
$editar     = (int) ($_POST['editar'] ?? 0);
$eliminar   = (int) ($_POST['eliminar'] ?? 0);
$descargar  = (int) ($_POST['descargar'] ?? 0);

if (!$id || !$idPuesto || !$modulo_id) {
echo json_encode([
'success' => false,
'message' => 'Datos incompletos'
]);
exit;
}

try {

$registro = ModulosPuestos::find($id);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
exit;
}

Capsule::beginTransaction();

$registro->update([
'puesto_id' => $idPuesto,
'modulo_id' => $modulo_id,
'leer' => $leer,
'crear' => $crear,
'editar' => $editar,
'eliminar' => $eliminar,
'descargar' => $descargar
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Módulo actualizado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}
}

public function deleteModulosPuestos()
{
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
$idModuloPuesto = $data['id'] ?? null;

if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$idModuloPuesto) {
echo json_encode([
'success' => false,
'message' => 'ID no válido'
]);
exit;
}

try {

$registro = ModulosPuestos::find($idModuloPuesto);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
exit;
}

Capsule::beginTransaction();

$registro->delete();
Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Módulo eliminado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}
}

//------------------------------------------------//
//---------- MODULOS USUARIOS (PORTAL) ----------//
//----------------------------------------------//

public function modulosUsuariosIndex()
{

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$title = 'Módulos (Usuarios)';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/configuracion/modulos.usuarios.datatable.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/modulos-usuarios-index', $data,'configuracion');
}

public function modulosUsuariosFormulario($idUsuario){

$datosUsuario = Usuario::find($idUsuario);
$title = $datosUsuario->nombre;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add('Módulos (Usuario)', '/configuracion/modulos-usuarios');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idUsuario' => $idUsuario,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/libs/select2/dist/js/select2.min.js',
'/assets/js/configuracion/modulos.usuarios.configuracion.datatable.init.js?v=' . time(),
'/assets/js/configuracion/actions.modulos.usuarios.configuracion.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/modulos-usuarios-configuracion', $data,'configuracion');
}

public function datatableModulosUsuarios($idUsuario){

$filtro_usuario = Session::get('usuario');
$idEstacion = $filtro_usuario['id_estacion'] ?? null;

$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

$rows = Modulo::whereHas('usuarios', function($query) use ($idUsuario) {
$query->where('usuario_id', $idUsuario);
})
->with(['usuarios' => function($query) use ($idUsuario) {
$query->where('usuario_id', $idUsuario);
}])
->get()
->toArray();
$data = [];

$estatus = [
"titulo" => '',
"color_badge" => '',
"color_css" => '',
"color_hexa" => ''
];

foreach ($rows as $row) {
$rol = $row['usuarios'][0] ?? null;    
$pivot = $rol['pivot'] ?? null;

$idModuloUsuario = $pivot['id'] ?? null;
$nombre_modulo = $row['nombre'];

$tbLeer = $pivot['leer'] ?? 0;
$tbCrear = $pivot['crear'] ?? 0;
$tbEditar = $pivot['editar'] ?? 0;
$tbEliminar = $pivot['eliminar'] ?? 0;
$tbDescargar = $pivot['descargar'] ?? 0;

$data[] = [
"idModuloUsuario" => $idModuloUsuario,
"nombre_modulo" => $nombre_modulo,
"tbLeer" => $tbLeer,
"tbCrear" => $tbCrear,
"tbEditar" => $tbEditar,
"tbEliminar" =>$tbEliminar,
"tbDescargar" =>$tbDescargar,

"permisos" => [
"noEdit" => $permisoEditar,
"noDelete" => $permisoEliminar
]

];
}

echo json_encode([
"data" => $data
]);

exit;
}

public function getModulosUsuarios($idUsuario, $moduloActual = null)
{
header('Content-Type: application/json');

$query = Modulo::where('activo', 1);

if (!$moduloActual) {

$query->whereDoesntHave('usuarios', function ($q) use ($idUsuario) {
$q->where('usuario_id', $idUsuario);
});

} else {
$query->where(function ($q) use ($idUsuario, $moduloActual) {
$q->where('id', $moduloActual);
$q->orWhereDoesntHave('usuarios', function ($sub) use ($idUsuario) {
$sub->where('usuario_id', $idUsuario);
});

});
}

$data = $query->select('id', 'nombre')
->orderBy('nombre', 'asc')
->get();

echo json_encode($data);
exit;
}

public function getModulosUsuariosDetalle($idUsuarioModulo)
{
header('Content-Type: application/json');

$modulo = Modulo::whereHas('usuarios', function ($query) use ($idUsuarioModulo) {
$query->where('usuarios_modulos.id', $idUsuarioModulo);
})
->with(['usuarios' => function ($query) use ($idUsuarioModulo) {
$query->where('usuarios_modulos.id', $idUsuarioModulo);
}])
->first();

if (!$modulo) {
echo json_encode(['success' => false]);
exit;
}

$rol = $modulo->usuarios->first();
$detalle = [
'id'          => $rol->pivot->id,
'modulo_id'   => $modulo->id,
'modulo'      => $modulo->nombre,
'leer'        => $rol->pivot->leer,
'crear'       => $rol->pivot->crear,
'editar'      => $rol->pivot->editar,
'eliminar'    => $rol->pivot->eliminar,
'descargar'   => $rol->pivot->descargar,
];

echo json_encode([
'success' => true,
'detalle' => $detalle
]);

exit;
}

public function createModulosUsuarios()
{
header('Content-Type: application/json; charset=utf-8');

if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

$idUsuario   = $_POST['idUsuario'] ?? null;
$modulo_id  = $_POST['modulo_id'] ?? null;

$leer       = (int) ($_POST['leer'] ?? 0);
$crear      = (int) ($_POST['crear'] ?? 0);
$editar     = (int) ($_POST['editar'] ?? 0);
$eliminar   = (int) ($_POST['eliminar'] ?? 0);
$descargar  = (int) ($_POST['descargar'] ?? 0);

if (!$idUsuario || !$modulo_id) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
exit;
}

try {

$exists = ModulosUsuarios::where('usuario_id', $idUsuario)
->where('modulo_id', $modulo_id)
->exists();

if ($exists) {
echo json_encode([
'success' => false,
'message' => 'Este módulo ya se encuentra registrado'
]);
exit;
}

Capsule::beginTransaction();

ModulosUsuarios::create([
'usuario_id' => $idUsuario,
'modulo_id' => $modulo_id,
'leer' => $leer,
'crear' => $crear,
'editar' => $editar,
'eliminar' => $eliminar,
'descargar' => $descargar
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Módulo asignado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}

}

public function updateModulosUsuarios($id)
{
header('Content-Type: application/json; charset=utf-8');

if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para editar'
]);
exit;
}

$idUsuario   = $_POST['idUsuario'] ?? null;
$modulo_id  = $_POST['modulo_id'] ?? null;

$leer       = (int) ($_POST['leer'] ?? 0);
$crear      = (int) ($_POST['crear'] ?? 0);
$editar     = (int) ($_POST['editar'] ?? 0);
$eliminar   = (int) ($_POST['eliminar'] ?? 0);
$descargar  = (int) ($_POST['descargar'] ?? 0);

if (!$id || !$idUsuario || !$modulo_id) {
echo json_encode([
'success' => false,
'message' => 'Datos incompletos' 
]);
exit;
}

try {

$registro = ModulosUsuarios::find($id);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
exit;
}

Capsule::beginTransaction();

$registro->update([
'usuario_id' => $idUsuario,
'modulo_id' => $modulo_id,
'leer' => $leer,
'crear' => $crear,
'editar' => $editar,
'eliminar' => $eliminar,
'descargar' => $descargar
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Módulo actualizado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}
}

public function deleteModulosUsuarios()
{
header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);
$idModuloUsuario = $data['id'] ?? null;

if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$idModuloUsuario) {
echo json_encode([
'success' => false,
'message' => 'ID no válido'
]);
exit;
}

try {

$registro = ModulosUsuarios::find($idModuloUsuario);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
exit;
}

Capsule::beginTransaction();

$registro->delete();
Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Módulo eliminado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}
}

//------------------------------------------------------//
//------------- MODULOS (DPTO. OPERATIVO) -------------//
//----------------------------------------------------//

public function modulosDptoOperativoIndex(){

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$title = 'Módulos (Departamento Operativo)';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/configuracion/modulos.operativo.datatable.init.js?v=' . time(),
'/assets/js/configuracion/actions.modulos.operativo.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/modulos-operativo-index', $data,'configuracion');
}

public function datatableModulosDptoOperativo(){
$filtro_usuario = Session::get('usuario');
$idEstacion = $filtro_usuario['id_estacion'] ?? null;

$permisoCrear   = ModuloService::validaPermiso($this->modulo, 'crear');
$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

$rows = ModuloDptoOperativo::all();
$data = [];

$estatus = [
"titulo" => '',
"color_badge" => '',
"color_css" => '',
"color_hexa" => ''
];

foreach ($rows as $row) {
$idModulo = $row['id'];
$nombre_modulo = $row['nombre'] ?: 'S/I';
$clave = $row['clave'] ?: 'S/I';
$ruta = $row['ruta'] ?: 'S/I';
$icono = $row['icono'] ?: 'ti ti-layout-grid';
$status = $row['activo'];

$crear = (!$permisoCrear || $status != 1);
$editar = (!$permisoEditar || $status != 1);
$eliminar = (!$permisoEliminar || $status != 1);

if ($status == 0) {
$estatus = [
"titulo" => 'Eliminado',
"color_badge" => 'bg-danger',
"color_css" => 'text-bg-danger',
"color_hexa" => '#ffb6af'
];

}else if($status == 1){
$estatus = [
"titulo" => 'Activo',
"color_badge" => 'bg-success',
"color_css" => 'text-bg-success',
"color_hexa" => '#b0f2c2'
];

}

$data[] = [
"idModulo" => $idModulo,
"nombre_modulo" => $nombre_modulo,
"clave" => $clave,
"ruta" => $ruta,
"icono" => $icono,
"estatus" =>$estatus,

"permisos" => [
"disabledCreate" => $crear,
"disabledEdit" => $editar,
"disabledDelete" => $eliminar
]

];

}

echo json_encode([
"data" => $data
]);

exit;
}

public function createModulosDptoOperativo()
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

$idModulo = $data['idModulo'] ?? null;
$nombre_modulo = $data['nombre_modulo'] ?? null;
$clave = $data['clave'] ?? null;
$ruta = $data['ruta'] ?? null;
$icono = $data['icono'] ?? null;
$estatus = 1;

if (!$nombre_modulo || !$clave || !$ruta) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
return;
}

try {

Capsule::beginTransaction();

try {
// GUARDAR EN BD
ModuloDptoOperativo::create([
'nombre' => $nombre_modulo,
'clave' => $clave,
'ruta' => $ruta,
'icono' => $icono,  
'activo' => $estatus
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Reporte guardado correctamente'
]);

} catch (\Throwable $e) {

echo json_encode([
'success' => false,
'message' => $e->getMessage(),
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

public function updateModulosDptoOperativo()
{

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

$idModulo = $data['idModulo'] ?? null;
$nombre_modulo = $data['nombre_modulo'] ?? null;
$clave = $data['clave'] ?? null;
$ruta = $data['ruta'] ?? null;
$icono = $data['icono'] ?? null;

if (!$nombre_modulo || !$clave || !$ruta) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
return;
}

$registro = ModuloDptoOperativo::find($idModulo);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}

$registro->nombre = $nombre_modulo;
$registro->clave = $clave;
$registro->ruta = $ruta;
$registro->icono = $icono;
$registro->save();

echo json_encode([
'success' => true,
'message' => 'Registro actualizado correctamente'
]);

}

public function deleteModulosDptoOperativo()
{
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$idModulo = $data['id'] ?? null;
$estatus = 0;

if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$idModulo) {
echo json_encode([
'success' => false,
'message' => 'ID requerido'
]);
exit;
}

/*---------- 1) Buscar módulo ----------*/
$registro = ModuloDptoOperativo::find($idModulo);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}

/*---------- 2) Desactivar módulo ----------*/
$registro->activo = $estatus;
$registro->save();

/*---------- 3) Verificar si tiene submódulos*/
$tieneSubmodulos = ModuloSubDptoOperativo::where('id_modulo', $idModulo)->exists();

/*---------- 4) Si tiene, desactivarlos*/
if ($tieneSubmodulos) {
ModuloSubDptoOperativo::where('id_modulo',$idModulo)
->update([
'activo' => $estatus
]);

}

echo json_encode([
'success' => true,
'message' => 'Registro eliminado correctamente'
]);
}

//---------------------------------------------------------//
//------------- SUBMODULOS (DPTO. OPERATIVO) -------------//
//-------------------------------------------------------//

public function submodulosDptoOperativoIndex($idModulo){

$datosModulo = ModuloDptoOperativo::find($idModulo);
$title = $datosModulo->nombre;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add('Módulos (Departamento Operativo)', '/configuracion/modulos-operativo');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idModulo' => $idModulo,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/libs/select2/dist/js/select2.min.js',
'/assets/js/configuracion/submodulos.operativo.datatable.init.js?v=' . time(),
'/assets/js/configuracion/actions.submodulos.operativo.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/submodulos-operativo-index', $data,'configuracion');
}

public function datatableSubmodulosDptoOperativo($idModulo)
{
$filtro_usuario = Session::get('usuario');
$idEstacion = $filtro_usuario['id_estacion'] ?? null;

$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');

// Obtener todos los submódulos donde id_modulo = $idModulo
$rows = ModuloSubDptoOperativo::where('id_modulo', $idModulo)->get()->toArray();

$data = [];

$estatus = [
"titulo" => '',
"color_badge" => '',
"color_css" => '',
"color_hexa" => ''
];

foreach ($rows as $row) {
$idSubmodulo = $row['id'];
$idModulo = $row['id_modulo'];
$nombre_modulo = $row['nombre'] ?: 'S/I';
$clave = $row['clave'] ?: 'S/I';
$ruta = $row['ruta'] ?: 'S/I';
$icono = $row['icono'] ?: 'ti ti-layout-grid';
$status = $row['activo'];

$editar = (!$permisoEditar || $status != 1);
$eliminar = (!$permisoEliminar || $status != 1);

if ($status == 0) {
$estatus = [
"titulo" => 'Eliminado',
"color_badge" => 'bg-danger',
"color_css" => 'text-bg-danger',
"color_hexa" => '#ffb6af'
];

}else if($status == 1){
$estatus = [
"titulo" => 'Activo',
"color_badge" => 'bg-success',
"color_css" => 'text-bg-success',
"color_hexa" => '#b0f2c2'
];

}

$data[] = [
"idSubmodulo" => $idSubmodulo,
"id_modulo" => $idModulo,
"nombre" => $nombre_modulo,
"clave" => $clave,
"ruta" => $ruta,
"icono" => $icono,
"estatus" => $estatus,

"permisos" => [
"disabledEdit" => $editar,
"disabledDelete" => $eliminar
]
];
}

echo json_encode([
"data" => $data
]);

exit;
}

public function createSubmodulosDptoOperativo()
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

$idSubmodulo = $data['idSubmodulo'] ?? null;
// SECURITY: Sanitización de inputs (Vulnerabilidad #5)
$idModulo = sanitize_input($data['idModulo'] ?? null, 'int');
$nombre_modulo = sanitize_input($data['nombre_modulo'] ?? null, 'string');
$clave = sanitize_input($data['clave'] ?? null, 'string');
$ruta = sanitize_input($data['ruta'] ?? null, 'string');
$icono = sanitize_input($data['icono'] ?? null, 'string');
$estatus = 1;

// Validar campos obligatorios
$errors = validate_input($data, [
'nombre_modulo' => 'required|max:100',
'clave' => 'required|max:50',
'ruta' => 'required|max:255'
]);

if (!empty($errors)) {
echo json_encode(['success' => false, 'errors' => $errors]);
exit;
}

if (!$nombre_modulo || !$clave || !$ruta) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
return;
}

try {

Capsule::beginTransaction();

try {
// GUARDAR EN BD
ModuloSubDptoOperativo::create([
'id_modulo' => $idModulo,
'nombre' => $nombre_modulo,
'clave' => $clave,
'ruta' => $ruta,
'icono' => $icono,  
'activo' => $estatus
]);

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Reporte guardado correctamente'
]);

} catch (\Throwable $e) {

echo json_encode([
'success' => false,
'message' => $e->getMessage(),
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

public function updateSubmodulosDptoOperativo()
{

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

$idSubmodulo = $data['idSubmodulo'] ?? null;
$nombre_submodulo = $data['nombre_submodulo'] ?? null;
$clave = $data['clave'] ?? null;
$ruta = $data['ruta'] ?? null;
$icono = $data['icono'] ?? null;

if (!$nombre_submodulo || !$clave || !$ruta) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
return;
}

$registro = ModuloSubDptoOperativo::find($idSubmodulo);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}

$registro->nombre = $nombre_submodulo;
$registro->clave = $clave;
$registro->ruta = $ruta;
$registro->icono = $icono;
$registro->save();

echo json_encode([
'success' => true,
'message' => 'Registro actualizado correctamente'
]);

}

public function deleteSubmodulosDptoOperativo(){
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);

$idSubmodulo = $data['id'] ?? null;
$estatus = 0;

if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$idSubmodulo) {
echo json_encode(['success' => false,'message' => 'ID requerido']);
exit;
}

$registro = ModuloSubDptoOperativo::find($idSubmodulo);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}

$registro->activo = $estatus;
$registro->save();

echo json_encode([
'success' => true,
'message' => 'Registro eliminado correctamente'
]);

}

//--------------------------------------------------------//
//---------- MODULOS PUESTOS (DPTO. OPERATIVO) ----------//
//------------------------------------------------------//

public function modulosPuestosDptoOperativoIndex(){

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$title = 'Módulos Departamento Operativo (Puestos)';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/configuracion/modulos.operativo.puestos.datatable.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/modulos-operativo-puestos-index', $data,'configuracion');
}

public function modulosPuestosDptoOperativoFormulario($idPuesto){

$datosPuesto = Puestos::find($idPuesto);
$title = $datosPuesto->tipo_puesto;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add('Módulos Departamento Operativo (Puestos)', '/configuracion/modulos-operativo-puestos');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idPuesto' => $idPuesto,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/libs/select2/dist/js/select2.min.js',
'/assets/js/configuracion/modulos.operativo.puestos.configuracion.datatable.init.js?v=' . time(),
'/assets/js/configuracion/actions.modulos.operativo.puestos.configuracion.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/modulos-operativo-puestos-configuracion', $data,'configuracion');
}

public function datatableModulosPuestosDptoOperativo($idPuesto)
{
$permisoCrear   = ModuloService::validaPermiso($this->modulo, 'crear');
$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');


$modulos = ModuloDptoOperativo::whereHas(
'roles',
function ($q) use ($idPuesto) {
$q->where('id_puesto', $idPuesto);
}
)->get();

$data = [];

foreach ($modulos as $modulo) {
/** @var \App\Models\ModuloDptoOperativo $modulo */
$permisos =
$modulo->permisosDelPuesto($idPuesto);

$data[] = [

'idModuloPuesto' => $permisos->id,
'idModulo' => $modulo->id,
'nombre_modulo' =>$modulo->nombre,

'no_submodulos' => [
'total_submodulos' =>
$modulo->totalSubmodulos(),
'submodulos_activos' => $modulo->submodulosActivosPorPuesto($idPuesto)
],

'tbLeer' =>$permisos->leer ?? 0,
'tbCrear' =>$permisos->crear ?? 0,
'tbEditar' =>$permisos->editar ?? 0,
'tbEliminar' =>$permisos->eliminar ?? 0,
'tbDescargar' =>$permisos->descargar ?? 0,

"permisos" => [
"noCreate" => $permisoCrear,
"noEdit" => $permisoEditar,
"noDelete" => $permisoEliminar
]
];
}

echo json_encode([
"data" => $data
]);

exit;
}

public function getModulosDptoOperativoPuestos($idPuesto, $moduloActual = null)
{
header('Content-Type: application/json');

$query = ModuloDptoOperativo::where('activo', 1);

if (!$moduloActual) {

$query->whereDoesntHave('roles', function ($q) use ($idPuesto) {
$q->where('id_puesto', $idPuesto);
});

} else {
$query->where(function ($q) use ($idPuesto, $moduloActual) {
$q->where('id', $moduloActual);
$q->orWhereDoesntHave('roles', function ($sub) use ($idPuesto) {
$sub->where('id_puesto', $idPuesto);
});

});
}

$data = $query->select('id', 'nombre')
->orderBy('nombre', 'asc')
->get();

echo json_encode($data);
exit;
}

public function getSubmodulosDptoOperativoPuestos($idModulo, $idPuesto)
{
header('Content-Type: application/json');

try {

$query = ModuloSubDptoOperativo::where('activo', 1)
->where('id_modulo', $idModulo)
->with(['roles' => function ($q) use ($idPuesto) {
$q->where('id_puesto', $idPuesto);
}]);

$data = $query
->orderBy('nombre', 'asc')
->get()
->map(function ($sub) {

return [
'id' => $sub->id,
'nombre' => $sub->nombre,
'checked' => $sub->roles->isNotEmpty()
];

});

echo json_encode($data);

} catch (\Throwable $e) {

http_response_code(500);

echo json_encode([
'error' => true,
'message' => $e->getMessage()
]);

}

exit;
}

public function getModulosDptoOperativoPuestosDetalle($idPuestoModulo)
{
header('Content-Type: application/json');

$modulo = ModuloDptoOperativo::whereHas('roles', function ($query) use ($idPuestoModulo) {
$query->where('modulos_puestos_do.id', $idPuestoModulo);
})
->with(['roles' => function ($query) use ($idPuestoModulo) {
$query->where('modulos_puestos_do.id', $idPuestoModulo);
}])
->first();

if (!$modulo) {
echo json_encode(['success' => false]);
exit;
}

$rol = $modulo->roles->first();
$detalle = [
'id'          => $rol->pivot->id,
'modulo_id'   => $modulo->id,
'modulo'      => $modulo->nombre,
'leer'        => $rol->pivot->leer,
'crear'       => $rol->pivot->crear,
'editar'      => $rol->pivot->editar,
'eliminar'    => $rol->pivot->eliminar,
'descargar'   => $rol->pivot->descargar,
];

echo json_encode([
'success' => true,
'detalle' => $detalle
]);

exit;
}

public function createModulosDptoOperativoPuestos()
{
header('Content-Type: application/json; charset=utf-8');

if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

$idPuesto   = $_POST['idPuesto'] ?? null;
$modulo_id  = $_POST['modulo_id'] ?? null;

$leer       = (int) ($_POST['leer'] ?? 0);
$crear      = (int) ($_POST['crear'] ?? 0);
$editar     = (int) ($_POST['editar'] ?? 0);
$eliminar   = (int) ($_POST['eliminar'] ?? 0);
$descargar  = (int) ($_POST['descargar'] ?? 0);
$submodulos = $_POST['submodulos'] ?? [];

if (!$idPuesto || !$modulo_id) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
exit;
}

try {

$exists = ModuloPuestoDptoOperativo::where('id_puesto', $idPuesto)
->where('id_modulo', $modulo_id)
->exists();

if ($exists) {
echo json_encode([
'success' => false,
'message' => 'Este módulo ya se encuentra registrado'
]);
exit;
}

Capsule::beginTransaction();


/* ---------- 1) Crear módulo por puesto ----------*/
ModuloPuestoDptoOperativo::create([
'id_puesto' => $idPuesto,
'id_modulo' => $modulo_id,
'leer' => $leer,
'crear' => $crear,
'editar' => $editar,
'eliminar' => $eliminar,
'descargar' => $descargar
]);

/* ---------- 2) Insertar submódulos ----------*/
if (!empty($submodulos)) {

foreach ($submodulos as $idSub) {

$existe = ModuloPuestoSubDptoOperativo::where('id_sub_modulo',$idSub)
->where('id_puesto',$idPuesto)
->exists();

if (!$existe) {
ModuloPuestoSubDptoOperativo::create(['id_sub_modulo' => $idSub, 'id_puesto' => $idPuesto]);
}
}

}

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Módulo asignado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}

}

public function updateModulosDptoOperativoPuestos($id)
{
header('Content-Type: application/json; charset=utf-8');

if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para editar'
]);

exit;
}

$idPuesto   = $_POST['idPuesto'] ?? null;
$modulo_id  = $_POST['modulo_id'] ?? null;

$leer       = (int) ($_POST['leer'] ?? 0);
$crear      = (int) ($_POST['crear'] ?? 0);
$editar     = (int) ($_POST['editar'] ?? 0);
$eliminar   = (int) ($_POST['eliminar'] ?? 0);
$descargar  = (int) ($_POST['descargar'] ?? 0);

$submodulos = $_POST['submodulos'] ?? [];

if (!is_array($submodulos)) {
$submodulos = [];
}

if (!$idPuesto || !$modulo_id) {

echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);

exit;
}

try {

Capsule::beginTransaction();

/*---------- 1) Actualizar módulo ----------*/
ModuloPuestoDptoOperativo::where('id', $id)
->update([
'id_modulo' => $modulo_id,
'leer' => $leer,
'crear' => $crear,
'editar' => $editar,
'eliminar' => $eliminar,
'descargar' => $descargar
]);

/*---------- 2) Obtener submodulos que pertenecen a ese modulo ----------*/

$submodulosDelModulo = ModuloSubDptoOperativo::where('id_modulo', $modulo_id)
->pluck('id')
->toArray();

/*---------- 3) Obtener actuales SOLO de ese modulo ----------*/
$actuales = ModuloPuestoSubDptoOperativo::where('id_puesto',$idPuesto)
->whereIn('id_sub_modulo', $submodulosDelModulo)
->pluck('id_sub_modulo')
->toArray();

/* Determinar nuevos modulos*/
$nuevos = array_diff($submodulos, $actuales);
/*Determinar eliminados*/
$eliminados = array_diff($actuales, $submodulos);

/*---------- 4) Insertar nuevos ----------*/
foreach ($nuevos as $idSub) {
ModuloPuestoSubDptoOperativo::create([
'id_sub_modulo' => $idSub,
'id_puesto'     => $idPuesto
]);

}

/*---------- 5) Eliminar solo los de ese modulo ----------*/
if (!empty($eliminados)) {
ModuloPuestoSubDptoOperativo::where('id_puesto', $idPuesto)
->whereIn('id_sub_modulo', $eliminados)
->delete();
}

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Registro actualizado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);

}

}

public function deleteModulosDptoOperativoPuestos()
{
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$idModuloPuesto = $data['id'] ?? null;

if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$idModuloPuesto) {
echo json_encode([
'success' => false,
'message' => 'ID requerido'
]);
exit;
}

try {

/*---------- 1) Buscar registro ----------*/
$registro = ModuloPuestoDptoOperativo::find($idModuloPuesto);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}

$idPuesto = $registro->id_puesto;

/*---------- 2) Eliminar submódulos ----------*/
ModuloPuestoSubDptoOperativo::where('id_puesto', $idPuesto)
->delete();

/*---------- 3) Eliminar módulo ----------*/
$registro->delete();

echo json_encode([
'success' => true,
'message' => 'Registro eliminado correctamente'
]);

} catch (\Throwable $e) {

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);

}
}

//--------------------------------------------------------//
//---------- MODULOS USUARIO (DPTO. OPERATIVO) ----------//
//------------------------------------------------------//

public function modulosUsuariosDptoOperativoIndex(){

$datosUsuario = Auth::user();
$idPuesto = $datosUsuario->id_puesto;

$title = 'Módulos Departamento Operativo (Usuarios)';

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'utilitiesUser' =>[
'idPuestoUser' => $idPuesto
],
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/configuracion/modulos.operativo.usuarios.datatable.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/modulos-operativo-usuarios-index', $data,'configuracion');
}

public function modulosUsuariosDptoOperativoFormulario($idUsuario){

$datosUsuario = Usuario::find($idUsuario);
$title = $datosUsuario->nombre;

Breadcrumb::add('Home', '/home');
Breadcrumb::add('Configuración', '/configuracion');
Breadcrumb::add('Módulos Departamento Operativo (Puestos)', '/configuracion/modulos-operativo-usuarios');
Breadcrumb::add($title, '');

// Buscar permisos de los modulos
$permisos = ModuloService::permisosSesion($this->modulo);

$data = [
'title' => $title,
'permisos' => $permisos,
'modulo' => $this->modulo,
'idUsuario' => $idUsuario,
'links' =>[
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css',
'/assets/libs/select2/dist/css/select2.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js?v=' . time(),
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/libs/select2/dist/js/select2.full.min.js',
'/assets/libs/select2/dist/js/select2.min.js',
'/assets/js/configuracion/modulos.operativo.usuarios.configuracion.datatable.init.js?v=' . time(),
'/assets/js/configuracion/actions.modulos.operativo.usuarios.configuracion.init.js?v=' . time()
],
'help' => false
];

View::render('configuracion/modulos-operativo-usuarios-configuracion', $data,'configuracion');
}

public function datatableModulosUsuariosDptoOperativo($idUsuario)
{
$permisoCrear   = ModuloService::validaPermiso($this->modulo, 'crear');
$permisoEditar   = ModuloService::validaPermiso($this->modulo, 'editar');
$permisoEliminar = ModuloService::validaPermiso($this->modulo, 'eliminar');


$modulos = ModuloDptoOperativo::whereHas(
'usuarios',
function ($q) use ($idUsuario) {
$q->where('id_usuario', $idUsuario);
}
)->get();

$data = [];

foreach ($modulos as $modulo) {
/** @var \App\Models\ModuloDptoOperativo $modulo */
$permisos = $modulo->permisosDelUsuario($idUsuario);

$data[] = [

'idModuloUsuario' => $permisos->id,
'idModulo' => $modulo->id,
'nombre_modulo' =>$modulo->nombre,

'no_submodulos' => [
'total_submodulos' =>
$modulo->totalSubmodulos(),
'submodulos_activos' => $modulo->submodulosActivosPorUsuario($idUsuario)
],

'tbLeer' =>$permisos->leer ?? 0,
'tbCrear' =>$permisos->crear ?? 0,
'tbEditar' =>$permisos->editar ?? 0,
'tbEliminar' =>$permisos->eliminar ?? 0,
'tbDescargar' =>$permisos->descargar ?? 0,

"permisos" => [
"noCreate" => $permisoCrear,
"noEdit" => $permisoEditar,
"noDelete" => $permisoEliminar
]
];
}

echo json_encode([
"data" => $data
]);

exit;
}

public function getModulosDptoOperativoUsuarios($idUsuario, $moduloActual = null)
{
header('Content-Type: application/json');

$query = ModuloDptoOperativo::where('activo', 1);

if (!$moduloActual) {

$query->whereDoesntHave('usuarios', function ($q) use ($idUsuario) {
$q->where('id_usuario', $idUsuario);
});

} else {
$query->where(function ($q) use ($idUsuario, $moduloActual) {
$q->where('id', $moduloActual);
$q->orWhereDoesntHave('usuarios', function ($sub) use ($idUsuario) {
$sub->where('id_usuario', $idUsuario);
});

});
}

$data = $query->select('id', 'nombre')
->orderBy('nombre', 'asc')
->get();

echo json_encode($data);
exit;
}

public function getSubmodulosDptoOperativoUsuarios($idModulo, $idUsuario)
{
header('Content-Type: application/json');

try {

$query = ModuloSubDptoOperativo::where('activo', 1)
->where('id_modulo', $idModulo)
->with(['usuarios' => function ($q) use ($idUsuario) {
$q->where('id_usuario', $idUsuario);
}]);

$data = $query
->orderBy('nombre', 'asc')
->get()
->map(function ($sub) {

return [
'id' => $sub->id,
'nombre' => $sub->nombre,
'checked' => $sub->usuarios->isNotEmpty()
];

});

echo json_encode($data);

} catch (\Throwable $e) {

http_response_code(500);

echo json_encode([
'error' => true,
'message' => $e->getMessage()
]);

}

exit;
}

public function getModulosDptoOperativoUsuariosDetalle($idUsuarioModulo)
{
header('Content-Type: application/json');

$modulo = ModuloDptoOperativo::whereHas('usuarios', function ($query) use ($idUsuarioModulo) {
$query->where('modulos_usuarios_do.id', $idUsuarioModulo);
})
->with(['usuarios' => function ($query) use ($idUsuarioModulo) {
$query->where('modulos_usuarios_do.id', $idUsuarioModulo);
}])
->first();

if (!$modulo) {
echo json_encode(['success' => false]);
exit;
}

$rol = $modulo->usuarios->first();
$detalle = [
'id'          => $rol->pivot->id,
'modulo_id'   => $modulo->id,
'modulo'      => $modulo->nombre,
'leer'        => $rol->pivot->leer,
'crear'       => $rol->pivot->crear,
'editar'      => $rol->pivot->editar,
'eliminar'    => $rol->pivot->eliminar,
'descargar'   => $rol->pivot->descargar,
];

echo json_encode([
'success' => true,
'detalle' => $detalle
]);

exit;
}

public function createModulosDptoOperativoUsuarios()
{
header('Content-Type: application/json; charset=utf-8');

if (!ModuloService::validaPermiso($this->modulo, 'crear')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para crear'
]);
exit;
}

$idUsuario   = $_POST['idUsuario'] ?? null;
$modulo_id  = $_POST['modulo_id'] ?? null;

$leer       = (int) ($_POST['leer'] ?? 0);
$crear      = (int) ($_POST['crear'] ?? 0);
$editar     = (int) ($_POST['editar'] ?? 0);
$eliminar   = (int) ($_POST['eliminar'] ?? 0);
$descargar  = (int) ($_POST['descargar'] ?? 0);
$submodulos = $_POST['submodulos'] ?? [];

if (!$idUsuario || !$modulo_id) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);
exit;
}

try {

$exists = ModuloUsuarioDptoOperativo::where('id_usuario', $idUsuario)
->where('id_modulo', $modulo_id)
->exists();

if ($exists) {
echo json_encode([
'success' => false,
'message' => 'Este módulo ya se encuentra registrado'
]);
exit;
}

Capsule::beginTransaction();

/* ---------- 1) Crear módulo por puesto ----------*/
ModuloUsuarioDptoOperativo::create([
'id_usuario' => $idUsuario,
'id_modulo' => $modulo_id,
'leer' => $leer,
'crear' => $crear,
'editar' => $editar,
'eliminar' => $eliminar,
'descargar' => $descargar
]);

/* ---------- 2) Insertar submódulos ----------*/
if (!empty($submodulos)) {

foreach ($submodulos as $idSub) {

$existe = ModuloUsuarioSubDptoOperativo::where('id_sub_modulo',$idSub)
->where('id_usuario',$idUsuario)
->exists();

if (!$existe) {
ModuloUsuarioSubDptoOperativo::create(['id_sub_modulo' => $idSub, 'id_usuario' => $idUsuario]);
}
}

}

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Módulo asignado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);
}

}

public function updateModulosDptoOperativoUsuarios($id)
{
header('Content-Type: application/json; charset=utf-8');

if (!ModuloService::validaPermiso($this->modulo, 'editar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para editar'
]);

exit;
}

$idUsuario  = $_POST['idUsuario'] ?? null;
$modulo_id  = $_POST['modulo_id'] ?? null;

$leer       = (int) ($_POST['leer'] ?? 0);
$crear      = (int) ($_POST['crear'] ?? 0);
$editar     = (int) ($_POST['editar'] ?? 0);
$eliminar   = (int) ($_POST['eliminar'] ?? 0);
$descargar  = (int) ($_POST['descargar'] ?? 0);
$submodulos = $_POST['submodulos'] ?? [];

if (!is_array($submodulos)) {
$submodulos = [];
}

if (!$idUsuario || !$modulo_id) {
echo json_encode([
'success' => false,
'message' => 'Completa todos los campos obligatorios'
]);

exit;
}

try {

Capsule::beginTransaction();

/*---------- 1) Actualizar módulo ----------*/
ModuloUsuarioDptoOperativo::where('id', $id)
->update([
'id_modulo' => $modulo_id,
'leer' => $leer,
'crear' => $crear,
'editar' => $editar,
'eliminar' => $eliminar,
'descargar' => $descargar
]);

/*---------- 2) Obtener submodulos que pertenecen a ese modulo ----------*/
$submodulosDelModulo = ModuloSubDptoOperativo::where('id_modulo', $modulo_id)
->pluck('id')
->toArray();

/*---------- 3) Obtener actuales SOLO de ese modulo ----------*/
$actuales = ModuloUsuarioSubDptoOperativo::where('id_usuario', $idUsuario)
->whereIn('id_sub_modulo', $submodulosDelModulo)
->pluck('id_sub_modulo')
->toArray();

/* Determinar nuevos */
$nuevos = array_diff($submodulos, $actuales);
/* Determinar eliminados */
$eliminados = array_diff($actuales, $submodulos);

/*---------- 4) Insertar nuevos ----------*/
foreach ($nuevos as $idSub) {
ModuloUsuarioSubDptoOperativo::create([
'id_sub_modulo' => $idSub,
'id_usuario'    => $idUsuario
]);
}

/*---------- 5) Eliminar solo los de ese modulo ----------*/
if (!empty($eliminados)) {
ModuloUsuarioSubDptoOperativo::where('id_usuario', $idUsuario)
->whereIn('id_sub_modulo', $eliminados)
->delete();
}

Capsule::commit();

echo json_encode([
'success' => true,
'message' => 'Registro actualizado correctamente'
]);

} catch (\Throwable $e) {

Capsule::rollBack();

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);

}

}

public function deleteModulosDptoOperativUsuarios()
{
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$idModuloUsuario = $data['id'] ?? null;

if (!ModuloService::validaPermiso($this->modulo, 'eliminar')) {
echo json_encode([
'success' => false,
'message' => 'No tienes permiso para eliminar'
]);
exit;
}

if (!$idModuloUsuario) {
echo json_encode([
'success' => false,
'message' => 'ID requerido'
]);
exit;
}

try {

/*---------- 1) Buscar registro ----------*/
$registro = ModuloUsuarioDptoOperativo::find($idModuloUsuario);

if (!$registro) {
echo json_encode([
'success' => false,
'message' => 'Registro no encontrado'
]);
return;
}

$idUsuario = $registro->id_usuario;

/*---------- 2) Eliminar submódulos ----------*/
ModuloUsuarioSubDptoOperativo::where('id_usuario', $idUsuario)
->delete();

/*---------- 3) Eliminar módulo ----------*/
$registro->delete();

echo json_encode([
'success' => true,
'message' => 'Registro eliminado correctamente'
]);

} catch (\Throwable $e) {

echo json_encode([
'success' => false,
'message' => $e->getMessage()
]);

}
}

}


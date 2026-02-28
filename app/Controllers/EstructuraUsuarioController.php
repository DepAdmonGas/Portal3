<?php 
namespace App\Controllers;
use App\Core\View;
use App\Core\Auth;
use App\Models\Modulo;
use App\Models\Usuario;
use App\Models\UsuarioModuloEstructura;
use App\Models\UsuarioModuloPermiso;

class EstructuraUsuarioController extends BaseController{

public function index()
{
$user = Auth::user();

if (!$user) {
header('Location: /login');
exit;
}

$idUsuario = $user->id;
$idUsuario  = $user->puesto->id;

/*========== Detectar URL actual ==========*/
$urlActual = trim(
parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
'/'
);


/*========== Buscar módulo ==========*/
$modulo = Modulo::where('url', $urlActual)
->where('status', 0)
->first();

$breadcrumb = [];

if ($modulo) {

/*========== 1️⃣ Buscar estructura usuario ==========*/
$estructuraUsuario = UsuarioModuloEstructura::where('id_modulo',$modulo->id)
->where('id_usuario',$idUsuario)
->first();

/*========== 2️⃣ Si existe → usar usuario ==========*/
if ($estructuraUsuario) {
$breadcrumb = UsuarioModuloEstructura::breadcrumbCompleto($estructuraUsuario->id);

/*========== 3️⃣ Si no existe → usar puesto ==========*/
}else{

$estructuraPuesto = UsuarioModuloEstructura::where('id_modulo',$modulo->id)
->where('id_usuario',$idUsuario)
->first();

if ($estructuraPuesto) {
$breadcrumb = UsuarioModuloEstructura::breadcrumbCompleto($estructuraPuesto->id);
}
}
}

/*========== Obtener último módulo para title ==========*/
$ultimoModulo =!empty($breadcrumb)? end($breadcrumb): null;

$data = [

'title' =>$ultimoModulo->nombre_modulo ?? 'Inicio',
'breadcrumb' => $breadcrumb,

'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],

'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/sistemas/configuracion-modulos-usuario-datatable.init.js'
]

];

View::render('sistemas/configuracion-modulos-usuario-index',$data,'main'
);
}

public function indexEstructuraUsuario($idUsuario)
{
    
$usuario = Usuario::find($idUsuario);
$nombreUsuario = $usuario->nombre;

$modulosDisponibles = Modulo::obtenerTodos();

// 🔥 Traer estructura usando id_modulo_principal
$modulos = UsuarioModuloEstructura::where('id_usuario', $idUsuario)
->join('tb_modulos', 'tb_modulos.id', '=', 'tb_usuario_modulo_estructura.id_modulo')
->select(
'tb_usuario_modulo_estructura.id as id_estructura',
'tb_usuario_modulo_estructura.id_modulo',
'tb_usuario_modulo_estructura.id_modulo_principal',
'tb_modulos.nombre_modulo'
)
->orderBy('tb_modulos.nombre_modulo')
->get();

$data = [
'title' => 'Estructura por puesto (' . $nombreUsuario . ')',
'usuario' => $usuario,
'modulos' => $modulos,
'modulosDisponibles' => $modulosDisponibles,
'links' => [
'/assets/css/styles.min.css'
],
'scripts' => [
'/assets/js/vendor.min.js',
'/assets/js/modulos/actions-modulos-usuario.init.js'
]
];

View::render('sistemas/configuracion-modulos-usuario-formulario', $data, 'main');
}

/*
|--------------------------------------------------------------------------
| AGREGAR MÓDULO PRINCIPAL A UN USUARIO
|--------------------------------------------------------------------------
*/
public function createModuloUsuario()
{
header('Content-Type: application/json');

// 🔹 Obtener JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
http_response_code(400);
echo json_encode(['message' => 'Datos inválidos']);
return;
}

// 🔹 Validar campos
$idUsuario = isset($data['id_usuario']) ? (int)$data['id_usuario'] : 0;
$id_modulo_principal = isset($data['id_modulo_principal']) ? (int)$data['id_modulo_principal'] : 0;

if ($idUsuario <= 0 || $id_modulo_principal <= 0) {
http_response_code(422);
echo json_encode([
'message' => 'Error al agregar el registro, no se cuenta con un usuario o módulo seleccionado'
]);
return;
}

try {

// 🔹 Crear registro en tb_puesto_modulo_estructura
$registro = new UsuarioModuloEstructura();
$registro->id_usuario             = $idUsuario;
$registro->id_modulo             = $id_modulo_principal;
$registro->id_modulo_principal   = null;
$registro->orden                 = 0;
$registro->save();

// 🔹 Obtener el ID recién insertado
$idModuloEstructura = $registro->id;

// 🔹 Crear registro en tb_puesto_modulo_permiso
$permiso = new UsuarioModuloPermiso();
$permiso->id_modulo_estructura = $idModuloEstructura;
$permiso->ver      = 1;
$permiso->descargar = 0;
$permiso->agregar   = 0;
$permiso->editar    = 0;
$permiso->eliminar  = 0;
$permiso->save();

http_response_code(201);
echo json_encode([
'success' => true,
'id_modulo_estructura' => $idModuloEstructura
]);

} catch (\Exception $e) {
http_response_code(500);
echo json_encode([
'message' => 'Error al guardar el registro',
'error' => $e->getMessage()
]);
}
}

/*
|--------------------------------------------------------------------------
| AGREGAR SUBMÓDULO A MÓDULOS A UN USUARIO
|--------------------------------------------------------------------------
*/
public function createSubmoduloUsuario()
{
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$idUsuario          = (int) ($data['id_usuario'] ?? 0);
$idModulo          = (int) ($data['id_modulo'] ?? 0);
$idModuloPrincipal = (int) ($data['id_modulo_principal'] ?? 0); // ← ID ESTRUCTURA

if ($idUsuario <= 0 || $idModulo <= 0 || $idModuloPrincipal <= 0) {
http_response_code(422);
echo json_encode(['message' => 'Datos incompletos']);
return;
}

// 🚫 Validar duplicado
$existe = UsuarioModuloEstructura::where('id_usuario', $idUsuario)
->where('id_modulo', $idModulo)
->where('id_modulo_principal', $idModuloPrincipal)
->first();

if ($existe) {
http_response_code(409);
echo json_encode(['message' => 'El submódulo ya está asignado']);
return;
}

try {

// 🔹 Insertar submódulo
$registro = new UsuarioModuloEstructura();
$registro->id_usuario           = $idUsuario;
$registro->id_modulo           = $idModulo;
$registro->id_modulo_principal = $idModuloPrincipal;
$registro->orden               = 0;
$registro->save();

// 🔹 Obtener permisos del PADRE REAL
$permisoPadre = UsuarioModuloPermiso::where(
'id_modulo_estructura',
$idModuloPrincipal
)->first();

// 🔹 Crear permisos del submódulo
$permiso = new UsuarioModuloPermiso();
$permiso->id_modulo_estructura = $registro->id;

if ($permisoPadre) {
$permiso->ver        = $permisoPadre->ver;
$permiso->descargar = $permisoPadre->descargar;
$permiso->agregar   = $permisoPadre->agregar;
$permiso->editar    = $permisoPadre->editar;
$permiso->eliminar  = $permisoPadre->eliminar;
} else {
// fallback (caso extremo)
$permiso->ver = 1;
$permiso->descargar = 0;
$permiso->agregar = 0;
$permiso->editar = 0;
$permiso->eliminar = 0;
}

$permiso->save();

http_response_code(201);
echo json_encode([
'success' => true,
'message' => 'Submódulo agregado heredando permisos del padre'
]);

} catch (\Exception $e) {
http_response_code(500);
echo json_encode([
'message' => 'Error interno',
'error' => $e->getMessage()
]);
}
}


/*
|--------------------------------------------------------------------------
| ELIMINAR MÓDULOS O MÓDULOS A UN PUESTO
|--------------------------------------------------------------------------
*/
public function deleteSubmoduloUsuario()
{
// Leer JSON enviado por Axios
$data = json_decode(file_get_contents('php://input'), true);

$idEstructura = $data['idEstructura'] ?? null;

if (!$idEstructura) {
echo json_encode(['message' => 'ID requerido']);
exit;
}

// Buscar el registro principal
$estructura = UsuarioModuloEstructura::find($idEstructura);

if (!$estructura) {
echo json_encode(['message' => 'Registro no encontrado']);
exit;
}

$this->eliminarRecursivo($idEstructura);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
'success' => true,
'message' => 'Módulo y submódulos eliminados correctamente'
]);
exit;
}

private function eliminarRecursivo($id)
{
// Buscar hijos
$hijos = UsuarioModuloEstructura::where('id_modulo_principal', $id)->get();

// Si tiene hijos, eliminarlos primero
foreach ($hijos as $hijo) {
$this->eliminarRecursivo($hijo->id);
}

// Eliminar el actual
UsuarioModuloEstructura::where('id', $id)->delete();
}

public function detallePermisosUsuario($usuario, $estructura)
{
try {

$estructuraValida = UsuarioModuloEstructura::where('id', $estructura)
->where('id_usuario', $usuario)
->exists();

if (!$estructuraValida) {
http_response_code(404);
echo json_encode(['error' => 'Estructura no válida']);
exit;
}

$permiso = UsuarioModuloPermiso::where('id_modulo_estructura', $estructura)
->first();

header('Content-Type: application/json');

echo json_encode($permiso ?? (object)[]);
exit;

} catch (\Throwable $e) {

http_response_code(500);

echo json_encode([
'error' => $e->getMessage(),
'linea' => $e->getLine(),
'archivo' => $e->getFile()
]);
exit;
}
}


public function updatePermisosModuloUsuario($id)
{
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
http_response_code(400);
echo json_encode(['message' => 'Datos inválidos']);
return;
}

$id = (int)$id;

$ver        = isset($data['ver']) ? (int)$data['ver'] : 0;
$descargar  = isset($data['descargar']) ? (int)$data['descargar'] : 0;
$agregar    = isset($data['agregar']) ? (int)$data['agregar'] : 0;
$editar     = isset($data['editar']) ? (int)$data['editar'] : 0;
$eliminar   = isset($data['eliminar']) ? (int)$data['eliminar'] : 0;

try {

$permiso = UsuarioModuloPermiso::find($id);

if (!$permiso) {
http_response_code(404);
echo json_encode(['message' => 'Permiso no encontrado']);
return;
}

// 🔥 Actualizar este permiso
$this->actualizarPermisoRecursivo(
$permiso->id_modulo_estructura,
$ver,
$descargar,
$agregar,
$editar,
$eliminar
);

http_response_code(200);
echo json_encode([
'success' => true,
'message' => 'Permisos actualizados correctamente'
]);

} catch (\Exception $e) {

http_response_code(500);
echo json_encode([
'message' => 'Error al actualizar permisos',
'error' => $e->getMessage()
]);
}
}

private function actualizarPermisoRecursivo($idModuloEstructura, $ver, $descargar, $agregar, $editar, $eliminar)
{
// 🔹 Actualizar permiso actual
$permiso = UsuarioModuloPermiso::where('id_modulo_estructura', $idModuloEstructura)->first();

if ($permiso) {
$permiso->ver = $ver;
$permiso->descargar = $descargar;
$permiso->agregar = $agregar;
$permiso->editar = $editar;
$permiso->eliminar = $eliminar;
$permiso->save();
}

// 🔹 Buscar hijos
$hijos = UsuarioModuloEstructura::where('id_modulo_principal', $idModuloEstructura)->get();

foreach ($hijos as $hijo) {
$this->actualizarPermisoRecursivo(
$hijo->id,
$ver,
$descargar,
$agregar,
$editar,
$eliminar
);
}
}



}
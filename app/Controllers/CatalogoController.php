<?php 
namespace App\Controllers;
use App\Core\View;
use App\Core\Auth;
use App\Models\Modulo;
use App\Models\Catalogo;
use App\Models\PuestoModuloEstructura;
use App\Models\PuestoModuloPermiso;
use App\Models\UsuarioModuloEstructura;
use App\Models\UsuarioModuloPermiso;

class CatalogoController extends BaseController{

public function datatableCatalogos(){
$catalogo = Catalogo::all();
echo json_encode([
"data" => $catalogo
]);
}

public function index()
{
$user = Auth::user();

if (!$user) {
header('Location: /login');
exit;
}

$idUsuario = $user->id;
$idPuesto  = $user->puesto->id;

/*========== Detectar URL actual ==========*/
$urlActual = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),'/');

/*========== Buscar módulo ==========*/
$modulo = Modulo::where('url', $urlActual)->where('status', 0)->first();

if (!$modulo) {
header('Location: /home');
exit;
}

$breadcrumb = [];
$estructura = null;
$permisos   = null;

/* =========================================================
1️⃣ BUSCAR ESTRUCTURA USUARIO
========================================================= */

$estructuraUsuario = UsuarioModuloEstructura::where('id_modulo', $modulo->id)->where('id_usuario', $idUsuario)->first();

if ($estructuraUsuario) {

$estructura = $estructuraUsuario;
$breadcrumb = UsuarioModuloEstructura::breadcrumbCompleto($estructura->id);
$permisos = UsuarioModuloPermiso::where('id_modulo_estructura', $estructura->id)->first();

} else {

/* =========================================================
2️⃣ SI NO EXISTE → BUSCAR POR PUESTO
========================================================= */

$estructuraPuesto = PuestoModuloEstructura::where('id_modulo', $modulo->id)->where('id_puesto', $idPuesto)->first();

if ($estructuraPuesto) {
$estructura = $estructuraPuesto;
$breadcrumb = PuestoModuloEstructura::breadcrumbCompleto($estructura->id);
$permisos = PuestoModuloPermiso::where('id_modulo_estructura',$estructura->id)->first();
}
}

/* =========================================================
3️⃣ VALIDAR ESTRUCTURA
========================================================= */

if (!$estructura) {
header('Location: /home');
exit;
}

/* =========================================================
4️⃣ VALIDAR PERMISOS
========================================================= */

if (!$permisos || !$permisos->ver) {
header('Location: /home');
exit;
}

/* =========================================================
5️⃣ CONTINUAR NORMAL
========================================================= */

$ultimoModulo = !empty($breadcrumb) ? end($breadcrumb) : null;

$data = [

'title' => $ultimoModulo->nombre_modulo ?? 'Inicio',
'breadcrumb' => $breadcrumb,
'permisos' => $permisos,
'links' => [
'/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css'
],

'scripts' => [
'/assets/js/vendor.min.js',
'/assets/libs/datatables.net/js/jquery.dataTables.min.js',
'/assets/js/modulos/catalogo-modulos-datatable.init.js',
'/assets/js/modulos/catalogo-modulos-actions.init.js'
]

];

View::render(
'sistemas/catalogo-modulos-index',
$data,
'main'
);
}


public function createModuloCatalogo()
{
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['nombre_modulo']) || empty($data['url'])) {
http_response_code(422);
header('Content-Type: application/json');
echo json_encode(['message' => 'Nombre y URL son requeridos']);
exit;
}

Catalogo::create([
'nombre_modulo' => $data['nombre_modulo'],
'url' => $data['url'],
'status' => 1 // Activo por defecto
]);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
}

/* ==========================================
ACTUALIZAR MODULO
========================================== */
public function updateModuloCatalogo()
{
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['id']) || empty($data['nombre_modulo']) || empty($data['url'])) {
http_response_code(422);
header('Content-Type: application/json');
echo json_encode(['message' => 'Datos incompletos']);
exit;
}

$modulo = Catalogo::find($data['id']);

if (!$modulo) {
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['message' => 'Módulo no encontrado']);
exit;
}

$modulo->nombre_modulo = $data['nombre_modulo'];
$modulo->url = $data['url'];
$modulo->save();

header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
}

/* ==========================================
ELIMINAR MODULO
========================================== */
public function deleteModuloCatalogo()
{

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['id'])) {

http_response_code(422);
header('Content-Type: application/json');

echo json_encode([
'success' => false,
'message' => 'ID requerido'
]);

exit;

}

$modulo = Catalogo::find($data['id']);

if (!$modulo) {

http_response_code(404);
header('Content-Type: application/json');

echo json_encode([
'success' => false,
'message' => 'Módulo no encontrado'
]);

exit;

}


$modulo->status = 1;
$modulo->save();

header('Content-Type: application/json');

echo json_encode([
'success' => true,
'message' => 'Módulo eliminado correctamente'
]);

exit;

}


}
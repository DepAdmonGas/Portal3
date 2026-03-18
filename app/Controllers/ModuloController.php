<?php

namespace App\Controllers;

use App\Core\View;
use App\Core\Auth;
use App\Models\Modulo;
use App\Models\PuestoModuloEstructura;
use App\Models\UsuarioModuloEstructura;

class ModuloController extends BaseController
{
    
/*
|--------------------------------------------------------------------------
| RUTEO DINÁMICO DE MÓDULOS
|--------------------------------------------------------------------------
*/
public function RutasModulos(string $url)
{
$user = Auth::user();

if (!$user) {
header('Location: /login');
exit;
}

$idUsuario = $user->id;
$idPuesto  = $user->puesto->id;
$urlActual = trim($url, '/');

/* ---------- 1️⃣ BUSCAR MODULO ---------- */
$modulo = Modulo::where('url', $urlActual)
->where('status', 0)
->first();

if (!$modulo) {
$this->abort404();
}

/* ---------- 2️⃣ BUSCA LA ESTRUCTURA (PRIMERO POR USUARIO) ---------- */
$estructura = UsuarioModuloEstructura::where('id_modulo', $modulo->id)
->where('id_usuario', $idUsuario)
->first();

$tipo = 'usuario';

/* ---------- 3️⃣ SI NO EXISTE, BUSCA POR PUESTO ---------- */
if (!$estructura) {

$estructura = PuestoModuloEstructura::where('id_modulo', $modulo->id)
->where('id_puesto', $idPuesto)
->first();

$tipo = 'puesto';
}

if (!$estructura) {
$this->abort403();
}

/* ---------- 4️⃣ VALIDA LOS PERMISOS DEL MODULO ---------- */
if (!Modulo::usuarioTieneAcceso($estructura->id,$idUsuario,$idPuesto)) {
$this->abort403();
}

/* ---------- 5️⃣ OBTIENE LOS SUBMODULOS ---------- */
$hijos = Modulo::submodulosUsuario($estructura->id,$idUsuario,$idPuesto);

/* ---------- 6️⃣ OBTIENE BREADCRUMB ---------- */
if ($tipo === 'usuario') {
$breadcrumb = UsuarioModuloEstructura::breadcrumbCompleto($estructura->id);

} else {
$breadcrumb =PuestoModuloEstructura::breadcrumbCompleto($estructura->id);

}

/* ---------- 7️⃣ SI TIENE HIJOS, MUESTRA EL LISTADO ---------- */
if ($hijos->count() > 0) {

return View::render('modulo/index', [
'title'      => $modulo->nombre_modulo,
'modulo'     => $modulo,
'modulos'    => $hijos,
'breadcrumb' => $breadcrumb
], 'main');
}

/* ---------- 8️⃣ SI NO TIENE HIJOS, MUESTRA LA VISTA FINAL ---------- */
$vista = 'modulos/' . $modulo->url;

$rutaVista =
"../resources/views/{$vista}.php";

if (file_exists($rutaVista)) {

return View::render($vista, [

'title'      => $modulo->nombre_modulo,
'modulo'     => $modulo,
'breadcrumb' => $breadcrumb

], 'main');
}


$this->abort404();
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/
private function abort404()
{
http_response_code(404);
echo "404 - Módulo no encontrado";
exit;
}

private function abort403()
{
http_response_code(403);
echo "403 - No tienes permiso para acceder";
exit;
}
}
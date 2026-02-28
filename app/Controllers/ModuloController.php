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

/*
================================
1️⃣ Buscar módulo
================================
*/
$modulo = Modulo::where('url', $urlActual)
->where('status', 0)
->first();

if (!$modulo) {
$this->abort404();
}


/*
================================
2️⃣ Buscar estructura (USUARIO primero)
================================
*/

$estructura = UsuarioModuloEstructura::where('id_modulo', $modulo->id)
->where('id_usuario', $idUsuario)
->first();

$tipo = 'usuario';


/*
================================
3️⃣ Si no existe → buscar puesto
================================
*/

if (!$estructura) {

$estructura = PuestoModuloEstructura::where('id_modulo', $modulo->id)
->where('id_puesto', $idPuesto)
->first();

$tipo = 'puesto';
}


if (!$estructura) {
$this->abort403();
}


/*
================================
4️⃣ Validar permisos
================================
*/

if (!Modulo::usuarioTieneAcceso(
$estructura->id,
$idUsuario,
$idPuesto
)) {
$this->abort403();
}


/*
================================
5️⃣ Obtener submódulos
================================
*/

$hijos = Modulo::submodulosUsuario(
$estructura->id,
$idUsuario,
$idPuesto
);


/*
================================
6️⃣ Breadcrumb correcto
================================
*/

if ($tipo === 'usuario') {

$breadcrumb =
UsuarioModuloEstructura::breadcrumbCompleto(
$estructura->id
);

} else {

$breadcrumb =
PuestoModuloEstructura::breadcrumbCompleto(
$estructura->id
);
}


/*
================================
7️⃣ Si tiene hijos → mostrar listado
================================
*/

if ($hijos->count() > 0) {

return View::render('modulo/index', [

'title'      => $modulo->nombre_modulo,
'modulo'     => $modulo,
'modulos'    => $hijos,
'breadcrumb' => $breadcrumb

], 'main');
}


/*
================================
8️⃣ Vista final
================================
*/

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
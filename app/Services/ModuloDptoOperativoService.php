<?php

namespace App\Services;

use App\Models\Usuario;
use App\Models\ModuloDptoOperativo;

class ModuloDptoOperativoService
{

/**
 * Obtener MENÚS del departamento operativo
 * PRIORIDAD: usuario > puesto (EXCLUSIVO)
 */
public static function getMenusUsuario($usuario_id)
{
$usuario = Usuario::find($usuario_id);

if (!$usuario) {
return [];
}

$tienePermisosUsuario = ModuloDptoOperativo::whereHas('usuarios', function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
})->exists();

$moduloDptoOperativo = ModuloDptoOperativo::with([

'roles' => function ($q) use ($usuario) {
$q->where('id_puesto', $usuario->id_puesto);
},

'usuarios' => function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
},

'submenus.roles' => function ($q) use ($usuario) {
$q->where('id_puesto', $usuario->id_puesto);
},

'submenus.usuarios' => function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
}

]);


if ($tienePermisosUsuario) {
$moduloDptoOperativo->whereHas('usuarios', function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
});

} else {

$moduloDptoOperativo->whereHas('roles', function ($q) use ($usuario) {
$q->where('id_puesto', $usuario->id_puesto);
});
}

$menus = $moduloDptoOperativo->orderBy('id', 'asc')->get();

$resultado = [];

foreach ($menus as $menu) {

$permisoMenu = $tienePermisosUsuario ? $menu->usuarios->isNotEmpty()
: $menu->roles->isNotEmpty();

if (!$permisoMenu) {
continue;
}

$subMenuModulo = [];

foreach ($menu->submenus as $submenu) {

$permisoSub = $tienePermisosUsuario
? $submenu->usuarios->isNotEmpty()
: $submenu->roles->isNotEmpty();

if (!$permisoSub) {
continue;
}

$subMenuModulo[] = [
'id_sub_modulo' => $submenu->id,
'nombre' => $submenu->nombre,
'clave' => $submenu->clave,
'ruta' => $submenu->ruta,
'icono' => $submenu->icono ?: 'ti ti-layout-grid'
];
}

$resultado[] = [
'id_modulo' => $menu->id,
'nombre' => $menu->nombre,
'clave' => $menu->clave,
'ruta' => $menu->ruta,
'icono' => $menu->icono ?: 'ti ti-layout-grid',
'submenus' => $subMenuModulo
];
}

return $resultado;
}


/**
* Obtener SUBMENÚS por módulo específico
* PRIORIDAD: usuario > puesto (EXCLUSIVO)
*/
public static function getSubmenusPorModulo($usuario_id, $clave)
{
$usuario = Usuario::find($usuario_id);

if (!$usuario) {
return [];
}

$tienePermisosUsuario = ModuloDptoOperativo::whereHas('usuarios', function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
})->exists();

$menu = ModuloDptoOperativo::with([

'submenus' => function ($q) {
$q->orderBy('id', 'asc');
},

'submenus.roles' => function ($q) use ($usuario) {
$q->where('id_puesto', $usuario->id_puesto);
},

'submenus.usuarios' => function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
}

])
->where('clave', $clave)
->first();

if (!$menu) {
return [];
}

$resultado = [];

foreach ($menu->submenus as $submenu) {

$permisoSub = $tienePermisosUsuario
? $submenu->usuarios->isNotEmpty()
: $submenu->roles->isNotEmpty();

if (!$permisoSub) {
continue;
}

$resultado[] = [
'id_sub_modulo' => $submenu->id,
'nombre' => $submenu->nombre,
'clave' => $submenu->clave,
'ruta' => $submenu->ruta,
'icono' => $submenu->icono ?: 'ti ti-layout-grid'
];
}

return $resultado;
}

}
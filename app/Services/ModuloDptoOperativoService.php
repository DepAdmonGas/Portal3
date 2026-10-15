<?php

namespace App\Services;

use App\Models\Usuario;
use App\Models\ModuloDptoOperativo;

class ModuloDptoOperativoService
{

/*
|--------------------------------------------------------------------------
| RUTAS DE SUBMÓDULOS
|--------------------------------------------------------------------------
*/

/**
 * Ruta navegable de un submódulo.
 *
 * Varios submódulos necesitan el año (y el mes) al final de su ruta: la que
 * se guarda en modulos_sub_dpto_operativo no es navegable tal cual. Este
 * mapa vivía dentro de DptoOperativoController::renderModulo(); se movió
 * aquí para que el menú de cada módulo y los accesos directos de
 * /departamento-operativo generen exactamente la misma URL.
 *
 * @param array $submenu Submódulo con al menos 'clave' y 'ruta'.
 */
public static function resolverRutaSubmodulo(array $submenu)
{
$idYear = date('Y');
$idMes  = date('n');

$sufijos = [
'corte-diario'      => "/{$idYear}/{$idMes}",
'embarques'         => "/{$idYear}/{$idMes}",
'solicitud-cheque'  => "/{$idYear}/{$idMes}",
'ingresos-facturacion' => "/{$idYear}",
'despacho-ventas'   => "/{$idYear}/{$idMes}",
'comparativo-xml'   => "/{$idYear}",
'aclaracion-voucher'   => "/{$idYear}/{$idMes}",
'solicitud-vales'   => "/{$idYear}/{$idMes}",
'factura-monedero'   => "/{$idYear}/{$idMes}",
'incidencias-nomina'   => "/{$idYear}",
'bitacora-rrhh'   => "/{$idYear}/{$idMes}",
'formato-descarga-merma'         => "/{$idYear}/{$idMes}",
'analisis-compra'         => "/{$idYear}/{$idMes}",
'inventarios-diarios'         => "/{$idYear}/{$idMes}",
'recibos-nomina'         => "/{$idYear}",
'cuenta-litros'          => "/{$idYear}/{$idMes}",
'precios-diarios-combustible'          => "/{$idYear}/{$idMes}",
'orden-compra'          => "/{$idYear}/{$idMes}",
];

$sufijo = $sufijos[$submenu['clave']] ?? '';

return (string)($submenu['ruta'] ?? '') . $sufijo;
}

/* ---------- VOBTENER TODOS LOS MODULOS O UNO EN ESPECIFICO ----------*/
public static function getPermisos($usuario_id, $clave = null)
{
$usuario = Usuario::find($usuario_id);

if (!$usuario) {
return [];
}

/* ---------- VERIFICAR PRIORIDAD: USUARIO > PUESTO ----------*/
$tienePermisosUsuario = ModuloDptoOperativo::whereHas(
'usuarios',
function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
}
)->exists();

$query = ModuloDptoOperativo::with([

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


/* ---------- FILTRAR POR MODULO SI VIENE LA "CLAVE" ----------*/
if ($clave) {
$query->where('clave', $clave);
}

/* ---------- APLICAR LA PRIORIDAD ----------*/
if ($tienePermisosUsuario) {

$query->whereHas(
'usuarios',
function ($q) use ($usuario_id) {
$q->where('id_usuario', $usuario_id);
}
);

} else {

$query->whereHas(
'roles',
function ($q) use ($usuario) {
$q->where('id_puesto', $usuario->id_puesto);
}
);

}

$modulos = $query->orderBy('id', 'asc')->get();
$resultado = [];

foreach ($modulos as $modulo) {

$rol  = $modulo->roles->first();
$user = $modulo->usuarios->first();

/* ---------- SUBMENUS ----------*/
$submenus = [];

foreach ($modulo->submenus as $submenu) {

$permisoSub = $tienePermisosUsuario
? $submenu->usuarios->isNotEmpty()
: $submenu->roles->isNotEmpty();

if (!$permisoSub) {
continue;
}

$submenus[] = [

'id_sub_modulo' => $submenu->id,
'nombre' => $submenu->nombre,
'clave' => $submenu->clave,
'ruta' => $submenu->ruta,
'icono' => $submenu->icono ?: 'ti ti-layout-grid'

];
}

/* ---------- RESULTADO DEL MENU ----------*/
$resultado[$modulo->clave] = [

'id_modulo' => $modulo->id,
'nombre' => $modulo->nombre ?? 'Modulo',
'ruta'   => $modulo->ruta ?? '#',
'icono'  => $modulo->icono ?: 'ti ti-layout-grid',


/* ---------- 🔒 Permisos (usuario > puesto > 0) ----------*/
'leer' => $user && $user->pivot ? $user->pivot->leer
: ($rol && $rol->pivot ? $rol->pivot->leer : 0),

'crear' => $user && $user->pivot? $user->pivot->crear
: ($rol && $rol->pivot? $rol->pivot->crear : 0),

'editar' => $user && $user->pivot? $user->pivot->editar
: ($rol && $rol->pivot ? $rol->pivot->editar : 0),

'eliminar' => $user && $user->pivot? $user->pivot->eliminar
: ($rol && $rol->pivot ? $rol->pivot->eliminar : 0),

'descargar' => $user && $user->pivot? $user->pivot->descargar
: ($rol && $rol->pivot ? $rol->pivot->descargar : 0),

'submenus' => $submenus
];
}

return $resultado;
}

/* ---------- OBTENER SOLO UN MODULO ----------*/
public static function getPermiso($usuario_id, $clave)
{
$data = self::getPermisos($usuario_id, $clave);
return $data[$clave] ?? [];
}


/* ---------- GUARDAR PERMISOS EN SESION ----------*/
public static function guardarEnSesion($usuario_id)
{
$_SESSION['permisos_do'] = self::getPermisos($usuario_id);
}

/* ---------- OBTENER PERMISOS DESDE SESION ----------*/
public static function permisosSesion($clave = null)
{
$permisos = $_SESSION['permisos_do'] ?? [];

if ($clave) {
return $permisos[$clave] ?? [];
}

return $permisos;
}

/* ---------- HELPERS ----------*/
public static function can($clave, $accion)
{
$permisos = $_SESSION['permisos_do'][$clave] ?? [];
return !empty($permisos[$accion]);
}


/* ---------- VALIDAR LOS PERMISOS ----------*/
public static function validaPermiso($modulo, $accion)
{
$permisos = self::permisosSesion($modulo);

if (empty($permisos)) {
$usuario_id = ($_SESSION['usuario']['id'] ?? 0);
if ($usuario_id) {
self::guardarEnSesion($usuario_id);
$permisos = self::permisosSesion($modulo);
}
}

return !empty($permisos[$accion]);
}

}
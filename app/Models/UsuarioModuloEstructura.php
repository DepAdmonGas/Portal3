<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioModuloEstructura extends Model
{
protected $table = 'tb_usuario_modulo_estructura';

public $timestamps = false;

protected $fillable = [
'id_usuario',
'id_modulo',
'id_modulo_principal',
'orden'
];

/* ==========================================
OBTENER ID MODULO ESTRUCTURA
========================================== */

public static function obtenerEstructura(int $idModulo, int $idUsuario)
{
return self::where('id_modulo', $idModulo)
->where('id_usuario', $idUsuario)
->first();
}

/* =========================================
BREADCRUMB BASE
========================================= */
public static function breadcrumb($idModuloEstructura)
{
$ruta = [];

while ($idModuloEstructura) {

$estructura = self::join(
    'tb_modulos',
    'tb_modulos.id',
    '=',
    'tb_usuario_modulo_estructura.id_modulo'
)
->where('tb_usuario_modulo_estructura.id', $idModuloEstructura)
->select(
    'tb_usuario_modulo_estructura.id',
    'tb_usuario_modulo_estructura.id_modulo_principal',
    'tb_modulos.nombre_modulo',
    'tb_modulos.url'
)
->first();

if (!$estructura) {
    break;
}

array_unshift($ruta, $estructura);

$idModuloEstructura = $estructura->id_modulo_principal;
}

return $ruta;
}


/* =========================================
BREADCRUMB COMPLETO
========================================= */
public static function breadcrumbCompleto(int $idEstructura)
{
$breadcrumb = self::breadcrumb($idEstructura) ?? [];

$estructura = self::find($idEstructura);

if (!$estructura) {
return $breadcrumb;
}

$moduloActual = Modulo::find($estructura->id_modulo);

if (!$moduloActual) {
return $breadcrumb;
}

$ultimo = !empty($breadcrumb) ? end($breadcrumb) : null;

if (!$ultimo || $ultimo->url !== $moduloActual->url) {

$breadcrumb[] = (object)[
    'nombre_modulo' => $moduloActual->nombre_modulo,
    'url' => $moduloActual->url
];
}

return $breadcrumb;
}


/* =========================================
BREADCRUMB DESDE URL (PRIORIDAD USUARIO)
========================================= */
public static function breadcrumbDesdeUrl(
string $url,
int $idUsuario
)
{

$modulo = Modulo::where('url', trim($url, '/'))
->where('status', 0)
->first();

if (!$modulo) {
return [];
}

$estructura = self::where('id_modulo', $modulo->id)
->where('id_usuario', $idUsuario)
->first();

if (!$estructura) {
return [];
}

return self::breadcrumbCompleto($estructura->id);
}
}
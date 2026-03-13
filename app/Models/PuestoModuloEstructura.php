<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PuestoModuloEstructura extends Model
{
protected $table = 'tb_puesto_modulo_estructura';

public $timestamps = false;

protected $fillable = [
'id_puesto',
'id_modulo',
'id_modulo_principal',
'orden'
];

/* ==========================================
OBTENER ID MODULO ESTRUCTURA
========================================== */

public static function obtenerEstructura(int $idModulo, int $idPuesto)
{
return self::where('id_modulo', $idModulo)
->where('id_puesto', $idPuesto)
->first();
}

public static function breadcrumb($idModuloEstructura)
{
$ruta = [];

while ($idModuloEstructura) {

$estructura = self::join(
'tb_modulos',
'tb_modulos.id',
'=',
'tb_puesto_modulo_estructura.id_modulo'
)
->where('tb_puesto_modulo_estructura.id', $idModuloEstructura)
->select(
'tb_puesto_modulo_estructura.id',
'tb_puesto_modulo_estructura.id_modulo_principal',
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

// ✅ AGREGA ESTE NUEVO MÉTODO
public static function breadcrumbCompleto(int $idEstructura)
{
// Obtener breadcrumb base
$breadcrumb = self::breadcrumb($idEstructura) ?? [];

// Obtener estructura actual
$estructura = self::find($idEstructura);

if (!$estructura) {
return $breadcrumb;
}

// Obtener módulo actual
$moduloActual = Modulo::find($estructura->id_modulo);

if (!$moduloActual) {
return $breadcrumb;
}

// Obtener último elemento
$ultimo = !empty($breadcrumb) ? end($breadcrumb) : null;

// Si no está agregado, agregarlo
if (!$ultimo || $ultimo->url !== $moduloActual->url) {

$breadcrumb[] = (object)[
'nombre_modulo' => $moduloActual->nombre_modulo,
'url' => $moduloActual->url
];
}

return $breadcrumb;
}

public static function breadcrumbDesdeUrl(string $url, int $idUsuario, int $idPuesto)
{
// Buscar módulo por URL
$modulo = Modulo::where('url', trim($url, '/'))
->where('status', 0)
->first();

if (!$modulo) {
return [];
}

// Buscar estructura
$estructura = self::where('id_modulo', $modulo->id)
->where('id_puesto', $idPuesto)
->first();

if (!$estructura) {
return [];
}

// retornar breadcrumb completo
return self::breadcrumbCompleto($estructura->id);
}

}
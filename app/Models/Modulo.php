<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as DB;

class Modulo extends Model
{
protected $table = 'tb_modulos';
public $timestamps = false;

/* =========================================
OBTENER TODOS LOS MÓDULOS
========================================= */
public static function obtenerTodos()
{
return self::where('status', 0)
->orderBy('id')
->get();
}

/* =========================================
OBTENER ESTRUCTURA ACTIVA (USUARIO O PUESTO)
========================================= */
private static function obtenerEstructuraBase(int $idUsuario, int $idPuesto)
{
$estructuraUsuario = DB::table('tb_usuario_modulo_estructura')
->where('id_usuario', $idUsuario)
->count();

if ($estructuraUsuario > 0) {
return DB::table('tb_usuario_modulo_estructura as me')
->where('me.id_usuario', $idUsuario);
}

return DB::table('tb_puesto_modulo_estructura as me')
->where('me.id_puesto', $idPuesto);
}

/* =========================================
MÓDULOS RAÍZ VISIBLES (HOME)
========================================= */
public static function modulosHomeUsuario(int $idUsuario, int $idPuesto)
{
$estructura = self::obtenerEstructuraBase($idUsuario, $idPuesto);

return $estructura
->join('tb_modulos as m', 'm.id', '=', 'me.id_modulo')

// 🔹 Estructura directa del usuario
->leftJoin('tb_usuario_modulo_estructura as ume', function ($join) use ($idUsuario) {
$join->on('ume.id', '=', 'me.id')
->where('ume.id_usuario', '=', $idUsuario);
})

// 🔹 Permiso del usuario
->leftJoin('tb_usuario_modulo_permiso as ump', function ($join) {
$join->on('ump.id_modulo_estructura', '=', 'ume.id');
})

// 🔹 Permiso del puesto
->leftJoin('tb_puesto_modulo_permiso as pmp', function ($join) {
$join->on('pmp.id_modulo_estructura', '=', 'me.id');
})

->whereNull('me.id_modulo_principal')
->where('m.status', 0)

->where(function ($q) {
$q->where('ump.ver', 1)
->orWhere(function ($q2) {
$q2->whereNull('ump.ver')
->where('pmp.ver', 1);
});
})

->orderBy('m.nombre_modulo')
->select(
'm.*',
'me.id as id_modulo_estructura',
'me.id_modulo_principal'
)
->get();
}


/* =========================================
SUBMÓDULOS POR PADRE (DINÁMICO)
========================================= */
public static function submodulosUsuario(
?int $idModuloPrincipal,
int $idUsuario,
int $idPuesto
) {

$estructura = self::obtenerEstructuraBase($idUsuario, $idPuesto);

return $estructura
->join('tb_modulos as m', 'm.id', '=', 'me.id_modulo')

// 🔹 Estructura directa del usuario
->leftJoin('tb_usuario_modulo_estructura as ume', function ($join) use ($idUsuario) {
$join->on('ume.id', '=', 'me.id')
->where('ume.id_usuario', '=', $idUsuario);
})

// 🔹 Permiso del usuario
->leftJoin('tb_usuario_modulo_permiso as ump', function ($join) {
$join->on('ump.id_modulo_estructura', '=', 'ume.id');
})

// 🔹 Permiso del puesto
->leftJoin('tb_puesto_modulo_permiso as pmp', function ($join) {
$join->on('pmp.id_modulo_estructura', '=', 'me.id');
})

->where('me.id_modulo_principal', $idModuloPrincipal)
->where('m.status', 0)

->where(function ($q) {
$q->where('ump.ver', 1)
->orWhere(function ($q2) {
$q2->whereNull('ump.ver')
->where('pmp.ver', 1);
});
})

->orderBy('m.nombre_modulo')
->select(
'm.*',
'me.id as id_modulo_estructura',
'me.id_modulo_principal'
)
->get();
}


/* =========================================
VALIDAR ACCESO DIRECTO A UN MÓDULO
========================================= */
public static function usuarioTieneAcceso(
int $idModuloEstructura,
int $idUsuario,
int $idPuesto
): bool {

$estructura = self::obtenerEstructuraBase($idUsuario, $idPuesto);

$tieneEstructura = $estructura
->where('me.id', $idModuloEstructura)
->exists();

if (!$tieneEstructura) {
return false;
}

// 🔹 1️⃣ Permiso individual del usuario
$permisoUsuario = DB::table('tb_usuario_modulo_permiso as ump')
->join('tb_usuario_modulo_estructura as ume', function ($join) use ($idUsuario) {
$join->on('ume.id', '=', 'ump.id_modulo_estructura')
->where('ume.id_usuario', '=', $idUsuario);
})
->where('ump.id_modulo_estructura', $idModuloEstructura)
->value('ump.ver');

if ($permisoUsuario !== null) {
return $permisoUsuario == 1;
}

// 🔹 2️⃣ Permiso heredado del puesto
$permisoPuesto = DB::table('tb_puesto_modulo_permiso as pmp')
->join('tb_puesto_modulo_estructura as pme', function ($join) use ($idPuesto) {
$join->on('pme.id', '=', 'pmp.id_modulo_estructura')
->where('pme.id_puesto', '=', $idPuesto);
})
->where('pmp.id_modulo_estructura', $idModuloEstructura)
->value('pmp.ver');

return $permisoPuesto == 1;
}

}
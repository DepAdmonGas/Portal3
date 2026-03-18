<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PuestoModuloPermiso extends Model
{
protected $table = 'tb_puesto_modulo_permiso';
protected $primaryKey = 'id';
public $timestamps = false;

protected $fillable = [
'id_modulo_estructura',
'ver',
'descargar',
'agregar',
'editar',
'eliminar'
];

/* ==========================================
🔎 OBTENER PERMISOS POR ID_MODULO_ESTRUCTURA
========================================== */

public static function obtenerPorEstructura(int $idModuloEstructura)
{
return self::where('id_modulo_estructura', $idModuloEstructura)
->first();
}

/* ==========================================
🔎 SCOPE OPCIONAL
========================================== */

public function scopePorEstructura($query, $idEstructura)
{
return $query->where('id_modulo_estructura', $idEstructura);
}
}
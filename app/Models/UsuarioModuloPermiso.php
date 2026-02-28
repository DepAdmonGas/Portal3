<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuarioModuloPermiso extends Model
{
protected $table = 'tb_usuario_modulo_permiso';

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
RELACIONES
==========================================*/

public function estructura()
{
return $this->belongsTo(
PuestoModuloEstructura::class,
'id_modulo_estructura'
);
}

/* ==========================================
SCOPES ÚTILES
==========================================*/

public function scopePorEstructura($query, $idEstructura)
{
return $query->where('id_modulo_estructura', $idEstructura);
}
}
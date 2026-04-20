<?php

namespace App\Models;
use App\Models\Usuario;
use App\Models\Puestos;
use App\Models\ModuloPuestoSubDptoOperativo;
use App\Models\ModuloUsuarioSubDptoOperativo;


use Illuminate\Database\Eloquent\Model;

class ModuloDptoOperativo extends Model
{
protected $table = 'modulos_dpto_operativo';

protected $primaryKey = 'id';

public $incrementing = true;

protected $keyType = 'int';

public $timestamps = false;

protected $fillable = [
'nombre',
'clave',
'ruta',
'icono',
'activo'
];

protected $casts = [
'id' => 'integer',
];

public function roles()
{
return $this->belongsToMany(
Puestos::class,
'modulos_puestos_do',
'id_modulo',
'id_puesto'
)->withPivot(['id','leer','crear','editar','eliminar','descargar']);
}

public function usuarios()
{
return $this->belongsToMany(
Usuario::class,
'modulos_usuarios_do',
'id_modulo',
'id_usuario'
)->withPivot(['id','leer','crear','editar','eliminar','descargar']);
}

public function submenus()
{
return $this->hasMany(
ModuloSubDptoOperativo::class,
'id_modulo',
'id'
);
}

public function permisosDelPuesto($idPuesto)
{
$rol = $this->roles()
->wherePivot('id_puesto', $idPuesto)
->first();

return $rol?->pivot;
}

public function permisosDelUsuario($idUsuario)
{
$rol = $this->usuarios()
->wherePivot('id_usuario', $idUsuario)
->first();

return $rol?->pivot;
}


public function totalSubmodulos()
{
return $this->submenus()->where('activo', 1)->count();
}

public function submodulosActivosPorPuesto($idPuesto)
{
$idModulo = $this->id;

return ModuloPuestoSubDptoOperativo::where('id_puesto', $idPuesto)
->whereIn('id_sub_modulo', function ($q) use ($idModulo) {
$q->select('id')
->from('modulos_sub_dpto_operativo')
->where('id_modulo', $idModulo);
})
->count();
}

public function submodulosActivosPorUsuario($idUsuario)
{
$idModulo = $this->id;

return ModuloUsuarioSubDptoOperativo::where('id_usuario', $idUsuario)
->whereIn('id_sub_modulo', function ($q) use ($idModulo) {
$q->select('id')
->from('modulos_sub_dpto_operativo')
->where('id_modulo', $idModulo);
})
->count();
}

}

<?php

namespace App\Models;
use App\Models\Usuario;
use App\Models\Puestos;

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
);
}

public function usuarios()
{
return $this->belongsToMany(
Usuario::class,
'modulos_usuarios_do',
'id_modulo',
'id_usuario'
);
}


public function submenus()
{
return $this->hasMany(
ModuloSubDptoOperativo::class,
'id_modulo',
'id'
);
}


}

<?php

namespace App\Models;
use App\Models\Usuario;
use App\Models\Puestos;

use Illuminate\Database\Eloquent\Model;

class ModuloSubDptoOperativo extends Model
{
protected $table = 'modulos_sub_dpto_operativo';

protected $primaryKey = 'id';

public $timestamps = false;

protected $fillable = [
'id_modulo',
'nombre',
'clave',
'ruta',
'icono',
'activo',
];

protected $casts = [
'id' => 'int',
'id_modulo' => 'int'
];

public function roles()
{
return $this->belongsToMany(
Puestos::class,
'modulos_sub_puestos_do',
'id_sub_modulo',
'id_puesto'
);
}

public function usuarios()
{
return $this->belongsToMany(
Usuario::class,
'modulos_sub_usuarios_do',
'id_sub_modulo',
'id_usuario'
);
}

}

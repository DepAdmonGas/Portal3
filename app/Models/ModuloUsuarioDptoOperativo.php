<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuloUsuarioDptoOperativo extends Model
{
protected $table = 'modulos_usuarios_do';

protected $primaryKey = 'id';

public $timestamps = false;

protected $fillable = [
'id_modulo',
'id_usuario',
'leer',
'crear',
'editar',
'eliminar',
'descargar'
];

protected $casts = [
'id_modulo' => 'int',
'id_usuario' => 'int',
'leer' => 'int',
'crear' => 'int',
'editar' => 'int',
'eliminar' => 'int',
'descargar' => 'int'
];
}
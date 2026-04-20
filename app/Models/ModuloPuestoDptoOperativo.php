<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuloPuestoDptoOperativo extends Model
{
protected $table = 'modulos_puestos_do';

protected $primaryKey = 'id';

public $timestamps = false;

protected $fillable = [
'id_modulo',
'id_puesto',
'leer',
'crear',
'editar',
'eliminar',
'descargar'
];

protected $casts = [
'id_modulo' => 'int',
'id_puesto' => 'int',
'leer' => 'int',
'crear' => 'int',
'editar' => 'int',
'eliminar' => 'int',
'descargar' => 'int'
];
}
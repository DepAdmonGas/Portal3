<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuloUsuarioSubDptoOperativo extends Model
{
protected $table = 'modulos_sub_usuarios_do';

protected $primaryKey = 'id';

public $timestamps = false;

protected $fillable = [
'id_sub_modulo',
'id_usuario'
];

protected $casts = [
'id_sub_modulo' => 'int',
'id_usuario' => 'int'
];


}
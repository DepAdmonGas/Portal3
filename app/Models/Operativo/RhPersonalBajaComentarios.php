<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhPersonalBajaComentarios extends Model
{
protected $table = 'op_rh_personal_baja_comentarios';
protected $primaryKey = 'id';
public $incrementing = true;
protected $keyType = 'int';
public $timestamps = false;

protected $fillable = [
'id_baja',
'id_usuario',
'comentario',
'fecha_hora',
];

protected $casts = [
'id'         => 'integer',
'id_baja'    => 'integer',
'id_usuario' => 'integer',
'fecha_hora' => 'datetime',
];
}

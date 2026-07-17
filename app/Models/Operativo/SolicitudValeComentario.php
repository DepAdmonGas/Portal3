<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SolicitudValeComentario extends Model
{
protected $table = 'op_solicitud_vale_comentario';
protected $primaryKey = 'id';
public $timestamps = false;

protected $fillable = [
'id_solicitud',
'id_usuario',
'comentario',
];

protected $casts = [
'id' => 'integer',
'id_solicitud' => 'integer',
'id_usuario' => 'integer',
];
}

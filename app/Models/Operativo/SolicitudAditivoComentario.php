<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudAditivoComentario extends Model
{
    protected $table = 'op_solicitud_aditivo_comentario';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'fecha_hora',
        'id_usuario',
        'comentario'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime'
    ];
}

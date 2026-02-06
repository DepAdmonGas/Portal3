<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudChequeComentario extends Model
{
    protected $table = 'op_solicitud_cheque_comentario';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'fecha_hora',
        'id_usuario',
        'comentario'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_solicitud' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime'
    ];
}

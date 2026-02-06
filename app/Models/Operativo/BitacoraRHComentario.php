<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraRHComentario extends Model
{
    protected $table = 'op_bitacora_rrhh_comentarios';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_bitacora',
        'fecha_hora',
        'id_usuario',
        'comentario'
    ];

    protected $casts = [
        'id_bitacora' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
    ];

}

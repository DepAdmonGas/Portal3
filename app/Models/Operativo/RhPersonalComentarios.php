<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhPersonalComentarios extends Model
{
    protected $table = 'op_rh_personal_comentarios';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_personal',
        'fecha_hora',
        'id_usuario',
        'comentario'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_personal' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime'
    ];

}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmbarquesComentario extends Model
{
    protected $table = 'op_embarques_comentario';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_embarques',
        'fecha_hora',
        'id_usuario',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_embarques' => 'integer',
        'fecha_hora' => 'datetime',
        'id_usuario' => 'integer',
        'comentario' => 'string',
    ];
}

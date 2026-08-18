<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhFormatosComentario extends Model
{
    protected $table = 'op_recibo_formatos_comentarios';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formato',
        'fecha_hora',
        'id_usuario',
        'comentario'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formato' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime'
    ];
}

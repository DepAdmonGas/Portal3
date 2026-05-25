<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RefaccionesTransaccionComentarios extends Model
{
    protected $table = 'op_refacciones_transaccion_comentarios';
    protected $primaryKey = 'id_comentario';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_op_refacciones_transaccion',
        'fecha_hora',
        'id_usuario',
        'comentario'
    ];

    protected $casts = [
        'id_comentario' => 'integer',
        'id_op_refacciones_transaccion' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime'
    ];

}


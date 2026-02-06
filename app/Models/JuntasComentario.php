<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JuntasComentario extends Model
{
    protected $table = 'tb_juntas_comentario';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_junta',
        'fecha_hora',
        'id_usuario',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_junta' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
    ];
}

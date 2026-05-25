<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ControlVolumetricoComentario extends Model
{
    protected $table = 'op_control_volumetrico_comentario';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'fecha_hora',
        'id_usuario',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
        'comentario' => 'string',
    ];
}


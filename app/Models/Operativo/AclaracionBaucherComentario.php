<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class AclaracionBaucherComentario extends Model
{
    protected $table = 'op_aclaracion_baucher_comentario';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no maneja created_at ni updated_at estÃ¡ndar

    protected $fillable = [
        'id_aclaracion',
        'fecha_hora',
        'id_usuario',
        'comentario'
    ];

    protected $casts = [
        'id_aclaracion' => 'integer',
        'fecha_hora' => 'datetime',
        'id_usuario' => 'integer'
    ];
}


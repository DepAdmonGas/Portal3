<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoporteComentario extends Model
{
    protected $table = 'ds_soporte_comentarios';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No tiene created_at ni updated_at

    protected $fillable = [
        'id_ticket',
        'fecha_hora',
        'id_personal',
        'comentario',
    ];

    protected $casts = [
        'id_ticket' => 'integer',
        'id_personal' => 'integer',
        'fecha_hora' => 'datetime',
    ];

}

<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaEquiposComentario extends Model
{
    protected $table = 'op_maquinaria_equipos_comentario';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_maquinaria_equipo',
        'id_usuario',
        'comentario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_maquinaria_equipo' => 'integer',
        'id_usuario' => 'integer',
    ];
}
<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaMantenimientoComentario extends Model
{
    protected $table = 'op_maquinaria_mantenimiento_comentario';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'id_ocurrencia',
        'id_usuario',
        'comentario',
        'fecha_hora',
    ];

    protected $casts = [
        'id'               => 'integer',
        'id_mantenimiento' => 'integer',
        'id_ocurrencia'    => 'integer',
        'id_usuario'       => 'integer',
    ];
}
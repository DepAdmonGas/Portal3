<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuariosFormacionAcademica extends Model
{
    protected $table = 'tb_usuarios_formacion_academica';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'nivel',
        'detalle'
    ];

    protected $casts = [
        'id' => 'int',
        'id_usuario' => 'int'
    ];
}

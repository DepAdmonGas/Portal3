<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuariosExperienciaLaboral extends Model
{
    protected $table = 'tb_usuarios_experiencia_laboral';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'detalle'
    ];

    protected $casts = [
        'id' => 'int',
        'id_usuario' => 'int'
    ];
}

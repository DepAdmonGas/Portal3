<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuariosFamiliares extends Model
{
    protected $table = 'tb_usuarios_familiares';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'nombrecompleto',
        'parentesco',
        'domicilio',
        'telefono'
    ];

    protected $casts = [
        'id' => 'int',
        'id_usuario' => 'int'
    ];
}

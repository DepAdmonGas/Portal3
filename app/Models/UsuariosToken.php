<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuariosToken extends Model
{
    protected $table = 'tb_usuarios_token';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'herramienta',
        'token'
    ];

    protected $casts = [
        'id' => 'int',
        'id_usuario' => 'int'
    ];
}

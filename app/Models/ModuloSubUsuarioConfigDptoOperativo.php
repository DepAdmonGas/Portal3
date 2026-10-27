<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModuloSubUsuarioConfigDptoOperativo extends Model
{
    protected $table = 'modulos_sub_usuarios_config_do';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_modulo',
        'modo',
    ];

    protected $casts = [
        'id_usuario' => 'int',
        'id_modulo' => 'int',
    ];
}

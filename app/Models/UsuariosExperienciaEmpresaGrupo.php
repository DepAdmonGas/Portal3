<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuariosExperienciaEmpresaGrupo extends Model
{
    protected $table = 'tb_usuarios_experiencia_empresa_grupo';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'razon_social',
        'puesto',
        'periodo_inicio',
        'periodo_fin'
    ];

    protected $casts = [
        'id' => 'int',
        'id_usuario' => 'int',
        'periodo_inicio' => 'date',
        'periodo_fin' => 'date',
    ];
}

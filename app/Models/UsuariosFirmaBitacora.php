<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsuariosFirmaBitacora extends Model
{
    protected $table = 'tb_usuarios_firma_bitacora';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'categoria',
        'fechainicio',
        'fechatermino',
        'comentario',
        'estado'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'id_usuario' => 'int',
        'estado' => 'int',
        'fechainicio' => 'datetime',
        'fechatermino' => 'datetime'
    ];
}

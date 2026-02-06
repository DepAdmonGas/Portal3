<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncuentaEstacion extends Model
{
    protected $table = 'tb_encuentas_estacion';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'id_encuesta',
        'fechacreacion',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'id_encuesta' => 'integer',
        'fechacreacion' => 'datetime',
        'estado' => 'integer',
    ];
}

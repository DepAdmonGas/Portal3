<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluacionDesempeno extends Model
{
    protected $table = 'tb_evaluacion_desempeno';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha_hora',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
    ];
}

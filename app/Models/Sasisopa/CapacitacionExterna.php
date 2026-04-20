<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class CapacitacionExterna extends Model
{
    protected $table = 'tb_capacitacion_externa';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fechacreacion',
        'id_usuario',
        'curso',
        'fecha_programada',
        'duracion',
        'duraciondetalle',
        'instructor',
        'fecha_real',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fechacreacion' => 'datetime',
        'id_usuario' => 'integer',
        'curso' => 'string',
        'fecha_programada' => 'date',
        'duracion' => 'string',
        'duraciondetalle' => 'string',
        'instructor' => 'string',
        'fecha_real' => 'date',
    ];
}

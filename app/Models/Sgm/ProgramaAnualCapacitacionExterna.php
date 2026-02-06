<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualCapacitacionExterna extends Model
{
    protected $table = 'sgm_programa_anual_capacitacion_externa';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_personal',
        'nombre_curso',
        'tipo_capacitacion',
        'fecha_programada',
        'duracion',
        'instructor',
        'fecha_real',
        'realizadopor',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_personal' => 'integer',
        'nombre_curso' => 'string',
        'tipo_capacitacion' => 'string',
        'fecha_programada' => 'date',
        'duracion' => 'string',
        'instructor' => 'string',
        'fecha_real' => 'date',
        'realizadopor' => 'integer',
        'estado' => 'integer',
    ];
}

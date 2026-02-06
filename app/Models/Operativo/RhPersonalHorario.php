<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPersonalHorario extends Model
{
    protected $table = 'op_rh_personal_horario';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_personal',
        'horario',
        'dia',
        'hora_entrada',
        'hora_salida'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_personal' => 'integer',
        'hora_entrada' => 'datetime:H:i',
        'hora_salida' => 'datetime:H:i'
    ];

}

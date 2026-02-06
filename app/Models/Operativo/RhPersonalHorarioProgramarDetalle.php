<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPersonalHorarioProgramarDetalle extends Model
{
    protected $table = 'op_rh_personal_horario_programar_detalle';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_estacion',
        'id_personal',
        'horario',
        'dia',
        'hora_entrada',
        'hora_salida',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_estacion' => 'integer',
        'id_personal' => 'integer',
        'hora_entrada' => 'datetime:H:i:s',
        'hora_salida' => 'datetime:H:i:s',
    ];

}

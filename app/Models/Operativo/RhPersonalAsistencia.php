<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhPersonalAsistencia extends Model
{
    protected $table = 'op_rh_personal_asistencia';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_personal',
        'fecha',
        'hora_entrada',
        'hora_salida',
        'hora_entrada_sensor',
        'hora_salida_sensor',
        'retardo_minutos',
        'incidencia_dias',
        'incidencia',
        'sd'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_personal' => 'integer',
        'retardo_minutos' => 'integer',
        'incidencia_dias' => 'integer',
        'incidencia' => 'float',
        'sd' => 'float'
    ];
}


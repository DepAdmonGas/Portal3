<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhPersonalHorarioProgramar extends Model
{
    protected $table = 'op_rh_personal_horario_programar';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'estado' => 'integer',
    ];

}


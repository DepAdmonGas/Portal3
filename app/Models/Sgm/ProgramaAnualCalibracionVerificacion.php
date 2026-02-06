<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualCalibracionVerificacion extends Model
{
    protected $table = 'sgm_programa_anual_calibracion_verificacion';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_equipo',
        'fecha',
        'id_verificar',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_equipo' => 'integer',
        'fecha' => 'date',
        'id_verificar' => 'integer',
        'estado' => 'integer',
    ];
}

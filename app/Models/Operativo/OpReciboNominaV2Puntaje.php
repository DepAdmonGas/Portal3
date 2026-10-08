<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpReciboNominaV2Puntaje extends Model
{
    protected $table = 'op_recibo_nomina_v2_puntaje';
    public $timestamps = false;

    protected $fillable = [
        'year',
        'mes',
        'no_semana_quincena',
        'descripcion',
        'id_estacion',
        'actividad',
        'puntaje'
    ];
}
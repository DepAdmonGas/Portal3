<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpReciboNominaPeriodoAcuse extends Model
{
    protected $table = 'op_recibo_nomina_acuse_v2';
    public $timestamps = false;

    protected $fillable = [
        'year',
        'mes',
        'no_semana_quincena',
        'descripcion',
        'id_estacion',
        'archivo'
    ];
}
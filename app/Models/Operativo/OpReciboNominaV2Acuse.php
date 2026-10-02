<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpReciboNominaV2Acuse extends Model
{
    protected $table = 'op_recibo_nomina_v2_acuses';
    public $timestamps = false;

    protected $fillable = [
        'year',
        'mes',
        'no_semana_quincena',
        'descripcion',
        'id_estacion',
        'fecha',
        'doc_nomina_acuse'
    ];
}
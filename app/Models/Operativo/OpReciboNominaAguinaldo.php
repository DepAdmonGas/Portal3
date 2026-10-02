<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpReciboNominaAguinaldo extends Model
{
    protected $table = 'op_recibo_nomina_aguinaldo';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'year',
        'mes',
        'no_semana_quincena',
        'descripcion',
        'doc_nomina_aguinaldo',
        'status'
    ];
}
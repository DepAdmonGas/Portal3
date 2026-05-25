<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ReciboNominaV2Acuses extends Model
{
    protected $table = 'op_recibo_nomina_v2_acuses';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
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

    protected $casts = [
        'id' => 'integer',
        'year' => 'integer',
        'mes' => 'integer',
        'no_semana_quincena' => 'integer',
        'id_estacion' => 'integer'
    ];

}


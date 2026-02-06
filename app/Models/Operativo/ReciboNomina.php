<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReciboNomina extends Model
{
    protected $table = 'op_recibo_nomina';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'year',
        'mes',
        'id_personal_nomina',
        'percepciones',
        'deducciones',
        'isr',
        'isr_retenido',
        'total',
        'periodo',
        'nomina',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'year' => 'integer',
        'mes' => 'integer',
        'id_personal_nomina' => 'integer',
        'percepciones' => 'double',
        'deducciones' => 'double',
        'isr' => 'double',
        'isr_retenido' => 'double',
    ];
}

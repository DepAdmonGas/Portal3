<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class ReciboNominaV2 extends Model
{
    protected $table = 'op_recibo_nomina_v2';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // No usa created_at / updated_at

    protected $fillable = [
        'year',
        'mes',
        'no_semana_quincena',
        'descripcion',
        'id_estacion',
        'id_usuario',
        'id_puesto',
        'importe_total',
        'doc_nomina',
        'doc_nomina_firma',
        'doc_nomina_aguinaldo',
        'nomina_original',
        'prima_vacacional'
    ];

    protected $casts = [
        'id' => 'integer',
        'year' => 'integer',
        'mes' => 'integer',
        'no_semana_quincena' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'id_puesto' => 'integer',
        'importe_total' => 'double',
        'nomina_original' => 'integer',
        'prima_vacacional' => 'integer',
    ];

}


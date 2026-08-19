<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhFormatosAjusteSalarial extends Model
{
    protected $table = 'op_rh_formatos_ajuste_salarial';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formulario',
        'id_personal',
        'id_estacion',
        'salario_actual',
        'salario_ajustado',
        'fecha_aplicacion'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formulario' => 'integer',
        'id_personal' => 'integer',
        'id_estacion' => 'integer',
        'salario_actual' => 'double',
        'salario_ajustado' => 'double',
        'fecha_aplicacion' => 'date'
    ];
}

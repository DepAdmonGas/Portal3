<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhFormatosPrimaVacacional extends Model
{
    protected $table = 'op_rh_formatos_prima_vacacional';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formulario',
        'id_personal',
        'id_estacion',
        'periodo'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formulario' => 'integer',
        'id_personal' => 'integer',
        'id_estacion' => 'integer',
        'periodo' => 'integer'
    ];
}

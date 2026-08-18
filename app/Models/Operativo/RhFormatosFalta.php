<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhFormatosFalta extends Model
{
    protected $table = 'op_rh_formatos_falta';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formulario',
        'id_personal',
        'id_estacion',
        'dias_falta'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formulario' => 'integer',
        'id_personal' => 'integer',
        'id_estacion' => 'integer',
        'dias_falta' => 'date'
    ];
}

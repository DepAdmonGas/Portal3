<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhFormatosRestructuracion extends Model
{
    protected $table = 'op_rh_formatos_restructuracion';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formulario',
        'id_personal',
        'id_estacion',
        'id_estacion_cambio',
        'fecha'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formulario' => 'integer',
        'id_personal' => 'integer',
        'id_estacion' => 'integer',
        'id_estacion_cambio' => 'integer',
        'fecha' => 'date'
    ];
}

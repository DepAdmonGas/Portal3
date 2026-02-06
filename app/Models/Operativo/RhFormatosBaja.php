<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhFormatosBaja extends Model
{
    protected $table = 'op_rh_formatos_baja';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formulario',
        'id_personal',
        'id_estacion',
        'fecha_baja',
        'motivo',
        'detalle'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formulario' => 'integer',
        'id_personal' => 'integer',
        'id_estacion' => 'integer',
        'fecha_baja' => 'date'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunicacionIE extends Model
{
    protected $table = 'se_comunicacion_i_e';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'no_comunicacion',
        'fecha',
        'tema',
        'detalle',
        'encargado_comunicacion',
        'tipo_comunicacion',
        'material',
        'seguimiento',
        'dirigidoa',
        'url',
        'asistencia',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'no_comunicacion' => 'integer',
        'fecha' => 'date',
        'tema' => 'string',
        'detalle' => 'string',
        'encargado_comunicacion' => 'integer',
        'tipo_comunicacion' => 'string',
        'material' => 'string',
        'seguimiento' => 'string',
        'dirigidoa' => 'string',
        'url' => 'string',
        'asistencia' => 'integer',
    ];
}

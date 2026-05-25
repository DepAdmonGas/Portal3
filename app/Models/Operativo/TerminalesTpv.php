<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class TerminalesTpv extends Model
{
    protected $table = 'op_terminales_tpv';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'tpv',
        'no_serie',
        'modelo',
        'no_lote',
        'tipo_conexion',
        'no_afiliacion',
        'telefono',
        'estado',
        'rollos',
        'cargadores',
        'pedestales',
        'estatus_tpv',
        'no_impresiones',
        'tipo_tpv',
        'status',
    ];

    protected $casts = [
        'id'             => 'integer',
        'id_estacion'    => 'integer',
        'no_impresiones'=> 'integer',
        'status'         => 'integer',
    ];
}


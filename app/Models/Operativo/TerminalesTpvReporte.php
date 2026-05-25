<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class TerminalesTpvReporte extends Model
{
    protected $table = 'op_terminales_tpv_reporte';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_tpv',
        'fechacreacion',
        'falla',
        'atiende',
        'no_reporte',
        'dia_reporte',
        'dia_solucion',
        'costo',
        'factura',
        'serie',
        'modelo',
        'conexion',
        'observaciones',
        'status',
    ];

    protected $casts = [
        'id'           => 'integer',
        'id_tpv'       => 'integer',
        'costo'        => 'double',
        'status'       => 'integer',
        'dia_reporte'  => 'date',
        'dia_solucion' => 'date',
    ];
}


<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class VentasDiaOtros extends Model
{
    protected $table = 'op_ventas_dia_otros';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idreporte_dia',
        'concepto',
        'piezas',
        'importe',
    ];

    protected $casts = [
        'id'            => 'integer',
        'idreporte_dia' => 'integer',
        'importe'       => 'float',
    ];
}


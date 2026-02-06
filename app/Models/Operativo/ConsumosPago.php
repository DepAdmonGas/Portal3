<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumosPago extends Model
{
    protected $table = 'op_consumos_pagos';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no maneja created_at / updated_at

    protected $fillable = [
        'id_reportedia',
        'id_cliente',
        'total',
        'tipo',
        'pago',
        'comprobante'
    ];

    protected $casts = [
        'id_reportedia' => 'integer',
        'id_cliente'    => 'integer',
        'total'         => 'double'
    ];

}

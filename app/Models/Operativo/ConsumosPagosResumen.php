<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumosPagosResumen extends Model
{
    protected $table = 'op_consumos_pagos_resumen';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // No hay created_at / updated_at

    protected $fillable = [
        'id_mes',
        'id_cliente',
        'saldo_inicial',
        'consumos',
        'pagos',
        'saldo_final'
    ];

    protected $casts = [
        'id_mes'      => 'integer',
        'id_cliente'  => 'integer',
        'saldo_inicial'=> 'double',
        'consumos'     => 'double',
        'pagos'        => 'double',
        'saldo_final'  => 'double'
    ];

}

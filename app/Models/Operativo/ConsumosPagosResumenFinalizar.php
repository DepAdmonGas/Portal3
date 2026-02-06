<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumosPagosResumenFinalizar extends Model
{
    protected $table = 'op_consumos_pagos_resumen_finalizar';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no tiene created_at / updated_at

    protected $fillable = [
        'id_mes',
        'fecha'
    ];

    protected $casts = [
        'id_mes' => 'integer',
        'fecha'  => 'datetime'
    ];

}

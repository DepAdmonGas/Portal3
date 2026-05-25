<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PagoCliente extends Model
{
    protected $table = 'op_pago_clientes';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'idreporte_dia',
        'concepto',
        'importe',
        'nota'
    ];

    protected $casts = [
        'id' => 'integer',
        'idreporte_dia' => 'integer',
        'concepto' => 'string',
        'importe' => 'double',
        'nota' => 'string'
    ];
}


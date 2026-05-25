<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    protected $table = 'op_orden_compra';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'fecha',
        'year',
        'mes',
        'porcentaje_total',
        'cargo',
        'no_control',
        'iva',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'date',
        'year' => 'integer',
        'mes' => 'integer',
        'porcentaje_total' => 'integer',
        'cargo' => 'string',
        'no_control' => 'integer',
        'iva' => 'float',
        'estatus' => 'integer',
    ];
}


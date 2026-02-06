<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaLitrosDetalle extends Model
{
    protected $table = 'op_cuenta_litros_detalle';
    protected $primaryKey = 'id_detalle';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_cuenta_litros',
        'hora',
        'embarque',
        'transporte',
        'producto',
        'tanque',
        'litros',
        'descarga_neto',
        'descarga_bruto',
        'litros_c',
        'tad',
        'unidad',
        'venta_momento',
        'folio_merma',
        'comentario',
        'archivo',
    ];

    protected $casts = [
        'id_detalle' => 'integer',
        'id_cuenta_litros' => 'integer',
        'hora' => 'string',
        'embarque' => 'string',
        'transporte' => 'string',
        'producto' => 'string',
        'tanque' => 'integer',
        'litros' => 'integer',
        'descarga_neto' => 'integer',
        'descarga_bruto' => 'integer',
        'litros_c' => 'integer',
        'tad' => 'string',
        'unidad' => 'string',
        'venta_momento' => 'integer',
        'folio_merma' => 'integer',
        'comentario' => 'string',
        'archivo' => 'string',
    ];
}

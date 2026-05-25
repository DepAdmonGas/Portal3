<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class DespachoFactura extends Model
{
    protected $table = 'op_despacho_factura';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_dia',
        'litros_producto_uno',
        'litros_producto_dos',
        'litros_producto_tres',
        'pesos_producto_uno',
        'pesos_producto_dos',
        'pesos_producto_tres',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_dia' => 'integer',
        'litros_producto_uno' => 'double',
        'litros_producto_dos' => 'double',
        'litros_producto_tres' => 'double',
        'pesos_producto_uno' => 'double',
        'pesos_producto_dos' => 'integer',
        'pesos_producto_tres' => 'integer',
    ];
}


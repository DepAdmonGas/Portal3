<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraRefacturacion extends Model
{
    protected $table = 'op_orden_compra_refacturacion';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'id_estacion',
        'descripcion',
        'cantidad',
        'importe',
        'porcentaje',
        'cantidadES',
        'cantidadAl'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_ordencompra' => 'integer',
        'id_estacion' => 'integer',
        'descripcion' => 'string',
        'cantidad' => 'double',
        'importe' => 'double',
        'porcentaje' => 'double',
        'cantidadES' => 'integer',
        'cantidadAl' => 'integer'
    ];

}


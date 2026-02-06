<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraArticulo extends Model
{
    protected $table = 'op_orden_compra_articulo';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'id_proveedor',
        'concepto',
        'unidades',
        'estatus_r',
        'precio_unitario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_ordencompra' => 'integer',
        'id_proveedor' => 'integer',
        'concepto' => 'string',
        'unidades' => 'integer',
        'estatus_r' => 'string',
        'precio_unitario' => 'float',
    ];


}

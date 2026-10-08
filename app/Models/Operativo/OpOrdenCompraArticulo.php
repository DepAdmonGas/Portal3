<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpOrdenCompraArticulo extends Model
{
    protected $table = 'op_orden_compra_articulo';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'id_proveedor',
        'concepto',
        'unidades',
        'estatus_r',
        'precio_unitario'
    ];

    public function proveedor()
    {
        return $this->belongsTo(OpOrdenCompraProveedor::class, 'id_proveedor', 'id');
    }
}
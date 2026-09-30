<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OpOrdenCompraProveedor extends Model
{
    protected $table = 'op_orden_compra_proveedor';
    public $timestamps = false;

    protected $fillable = [
        'id_ordencompra',
        'razon_social',
        'direccion',
        'contacto',
        'email',
        'descuento',
        'envio_cp',
        'check_p'
    ];

    public function articulos()
    {
        return $this->hasMany(OpOrdenCompraArticulo::class, 'id_proveedor', 'id');
    }
}
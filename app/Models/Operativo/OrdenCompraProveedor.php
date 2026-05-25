<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraProveedor extends Model
{
    protected $table = 'op_orden_compra_proveedor';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
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

    protected $casts = [
        'id' => 'integer',
        'id_ordencompra' => 'integer',
        'descuento' => 'double',
        'envio_cp' => 'double',
        'check_p' => 'integer',
        'razon_social' => 'string',
        'direccion' => 'string',
        'contacto' => 'string',
        'email' => 'string',
    ];

}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoLimpiezaDetalle extends Model
{
    protected $table = 'op_pedido_limpieza_detalle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'id_producto',
        'piezas'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer',
        'id_producto' => 'integer',
        'piezas' => 'integer'
    ];
}

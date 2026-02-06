<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoPinturasDetalle extends Model
{
    protected $table = 'op_pedido_pinturas_detalle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'unidad',
        'producto',
        'piezas',
        'detalle'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer',
        'piezas' => 'integer'
    ];
}

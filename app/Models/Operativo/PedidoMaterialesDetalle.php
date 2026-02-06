<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoMaterialesDetalle extends Model
{
    protected $table = 'op_pedido_materiales_detalle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'refaccion',
        'concepto',
        'cantidad',
        'nota'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer',
        'refaccion' => 'integer'
    ];

}

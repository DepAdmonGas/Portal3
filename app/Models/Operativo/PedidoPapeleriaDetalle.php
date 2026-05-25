<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoPapeleriaDetalle extends Model
{
    protected $table = 'op_pedido_papeleria_detalle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'producto',
        'piezas'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer',
        'piezas' => 'integer',
    ];

}


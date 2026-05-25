<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoMaterialesArea extends Model
{
    protected $table = 'op_pedido_materiales_area';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'area',
        'estatus'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer',
        'estatus' => 'integer'
    ];

}


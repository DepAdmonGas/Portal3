<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoMaterialesInstalacion extends Model
{
    protected $table = 'op_pedido_materiales_instalacion';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'archivo'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer'
    ];

}

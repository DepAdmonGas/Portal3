<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoLimpiezaToken extends Model
{
    protected $table = 'op_pedido_limpieza_token';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'id_usuario',
        'fecha_creacion',
        'token'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer',
        'id_usuario' => 'integer',
        'fecha_creacion' => 'datetime',
        'token' => 'integer'
    ];
}


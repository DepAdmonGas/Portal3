<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoLimpiezaFirma extends Model
{
    protected $table = 'op_pedido_limpieza_firma';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pedido',
        'id_usuario',
        'fecha',
        'tipo_firma',
        'firma'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pedido' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime',
        'tipo_firma' => 'string',
        'firma' => 'string'
    ];
}


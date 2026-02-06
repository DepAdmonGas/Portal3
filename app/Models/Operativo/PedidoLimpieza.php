<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoLimpieza extends Model
{
    protected $table = 'op_pedido_limpieza';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // La tabla no tiene created_at ni updated_at

    protected $fillable = [
        'id_estacion',
        'id_personal',
        'fecha',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_personal' => 'integer',
        'fecha' => 'datetime',
        'status' => 'integer'
    ];
}

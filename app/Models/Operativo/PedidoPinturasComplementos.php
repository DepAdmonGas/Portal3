<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoPinturasComplementos extends Model
{
    protected $table = 'op_pedido_pinturas_complementos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // La tabla no usa created_at / updated_at

    protected $fillable = [
        'id_estacion',
        'id_personal',
        'fecha',
        'observaciones',
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

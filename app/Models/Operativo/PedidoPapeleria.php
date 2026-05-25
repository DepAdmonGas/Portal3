<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PedidoPapeleria extends Model
{
    protected $table = 'op_pedido_papeleria';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

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
        'status' => 'integer',
        'fecha' => 'datetime'
    ];

}


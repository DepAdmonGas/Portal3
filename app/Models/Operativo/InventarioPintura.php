<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class InventarioPintura extends Model
{
    protected $table = 'op_inventario_pinturas';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_producto',
        'piezas',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_producto' => 'integer',
        'piezas' => 'integer',
        'status' => 'integer',
    ];
}


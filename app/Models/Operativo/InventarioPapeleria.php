<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioPapeleria extends Model
{
    protected $table = 'op_inventario_papeleria';
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

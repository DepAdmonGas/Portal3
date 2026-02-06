<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioAceite extends Model
{
    protected $table = 'op_inventario_aceites';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'id_estacion',
        'id_aceite',
        'exhibidores',
        'bodega',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'id_estacion' => 'integer',
        'id_aceite' => 'integer',
        'exhibidores' => 'integer',
        'bodega' => 'integer',
    ];
}

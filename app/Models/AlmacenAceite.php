<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlmacenAceite extends Model
{
    protected $table = 'tb_almacen_aceites';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_aceite',
        'almacen',
        'stock_minimo',
        'id_estacion',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_aceite' => 'integer',
        'almacen' => 'integer',
        'stock_minimo' => 'integer',
        'id_estacion' => 'integer',
        'estatus' => 'integer',
    ];
}

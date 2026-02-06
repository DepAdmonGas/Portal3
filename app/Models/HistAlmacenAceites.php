<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistAlmacenAceites extends Model
{
    protected $table = 'tb_hist_almacen_aceites';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'no_reporte',
        'id_estacion',
        'id_aceite',
        'almacen',
        'stock_minimo',
        'ventas',
        'fisico_almacen',
        'fisico_islas',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'no_reporte' => 'integer',
        'id_estacion' => 'integer',
        'id_aceite' => 'integer',
        'almacen' => 'integer',
        'stock_minimo' => 'integer',
        'ventas' => 'integer',
        'fisico_almacen' => 'integer',
        'fisico_islas' => 'integer',
        'estatus' => 'integer',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentasDia extends Model
{
    protected $table = 'op_ventas_dia';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idreporte_dia',
        'producto',
        'litros',
        'jarras',
        'precio_litro',
        'ieps',
    ];

    protected $casts = [
        'id'             => 'integer',
        'idreporte_dia'  => 'integer',
        'litros'         => 'float',
        'jarras'         => 'float',
        'precio_litro'   => 'float',
        'ieps'           => 'float',
    ];
}

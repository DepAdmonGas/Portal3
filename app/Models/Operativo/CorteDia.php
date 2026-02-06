<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorteDia extends Model
{
    protected $table = 'op_corte_dia';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mes',
        'fecha',
        'ventas',
        'tpv',
        'monedero',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_mes' => 'integer',
        'fecha' => 'date',
        'ventas' => 'integer',
        'tpv' => 'integer',
        'monedero' => 'integer',
    ];
}

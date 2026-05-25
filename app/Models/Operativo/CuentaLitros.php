<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class CuentaLitros extends Model
{
    protected $table = 'op_cuenta_litros';
    protected $primaryKey = 'id_cuenta_litros';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'year',
        'mes',
        'estatus',
    ];

    protected $casts = [
        'id_cuenta_litros' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'year' => 'integer',
        'mes' => 'integer',
        'estatus' => 'integer',
    ];
}


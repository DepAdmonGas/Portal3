<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class Medicion extends Model
{
    protected $table = 'op_mediciones';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'factura',
        'neto',
        'bruto',
        'cuenta_litros',
        'proveedor',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'factura' => 'string',
        'neto' => 'double',
        'bruto' => 'double',
        'cuenta_litros' => 'double',
        'proveedor' => 'string',
    ];
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventarioAditivoHist extends Model
{
    protected $table = 'op_inventario_aditivo_hist';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'id_estacion',
        'aditivo',
        'galones',
        'detalle',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'datetime',
        'id_estacion' => 'integer',
        'aditivo' => 'string',
        'galones' => 'double',
        'detalle' => 'string',
    ];
}

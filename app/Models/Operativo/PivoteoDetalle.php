<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class PivoteoDetalle extends Model
{
    protected $table = 'op_pivoteo_detalle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_pivoteo',
        'estacion_fc',
        'destino_fc',
        'producto_fc',
        'tanque_fc',
        'factura_fc',
        'litros',
        'tad',
        'unidad',
        'chofer',
        'estacion_fn',
        'destino_fn',
        'tanque_fn',
        'factura_fn'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pivoteo' => 'integer',
        'litros' => 'double'
    ];

}


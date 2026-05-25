<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class InventariosDiariosDetalle extends Model
{
    protected $table = 'op_inventarios_diarios_detalle';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'detalle',
        'sucursal',
        'destino',
        'oct87',
        'oct91',
        'diesel',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'detalle' => 'string',
        'sucursal' => 'string',
        'destino' => 'integer',
        'oct87' => 'integer',
        'oct91' => 'integer',
        'diesel' => 'integer',
    ];
}


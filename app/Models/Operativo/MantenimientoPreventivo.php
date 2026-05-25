<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MantenimientoPreventivo extends Model
{
    protected $table = 'op_mantenimiento_preventivo';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'folio',
        'id_encargado',
        'fecha',
        'fecha2',
        'orden_servicio',
        'observaciones',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'folio' => 'integer',
        'id_encargado' => 'integer',
        'fecha' => 'date',
        'fecha2' => 'date',
        'orden_servicio' => 'string',
        'observaciones' => 'string',
        'status' => 'integer',
    ];
}


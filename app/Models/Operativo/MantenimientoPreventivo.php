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
        'tipo_mantenimiento',
        'costo',
        'observaciones',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'folio' => 'integer',
        'id_encargado' => 'integer',
        'fecha' => 'string',
        'fecha2' => 'string',
        'orden_servicio' => 'string',
        'tipo_mantenimiento' => 'integer',
        'costo' => 'float',
        'observaciones' => 'string',
        'status' => 'integer',
    ];
}


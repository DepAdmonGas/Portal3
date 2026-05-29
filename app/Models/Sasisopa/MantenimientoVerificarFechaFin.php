<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class MantenimientoVerificarFechaFin extends Model
{
    protected $table = 'po_mantenimiento_verificar_fechafin';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_verificar',
        'fechafin',
        'horafin',
        'observaciones',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_verificar' => 'integer',
        'fechafin' => 'date',
        'horafin' => 'string',
        'observaciones' => 'string',
    ];
}

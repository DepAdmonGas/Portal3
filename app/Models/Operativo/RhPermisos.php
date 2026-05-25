<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhPermisos extends Model
{
    protected $table = 'op_rh_permisos';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_personal',
        'fecha_inicio',
        'fecha_termino',
        'dias_tomados',
        'cubre_turno',
        'motivo',
        'observaciones',
        'estado',
        'estacion_cubre'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_personal' => 'integer',
        'fechacreacion' => 'datetime',
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'cubre_turno' => 'integer',
        'estado' => 'integer',
        'estacion_cubre' => 'integer'
    ];
}


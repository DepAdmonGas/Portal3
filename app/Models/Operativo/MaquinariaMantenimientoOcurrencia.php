<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class MaquinariaMantenimientoOcurrencia extends Model
{
    protected $table = 'op_maquinaria_mantenimiento_ocurrencia';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_mantenimiento',
        'fecha',
        'estado_actual',
        'costo',
        'estatus',
        'realizado_por',
        'fecha_realizado',
    ];

    protected $casts = [
        'id'               => 'integer',
        'id_mantenimiento' => 'integer',
        'costo'            => 'float',
        'estatus'          => 'integer',
        'realizado_por'    => 'integer',
    ];
}
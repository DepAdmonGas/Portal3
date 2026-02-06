<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPersonalBaja extends Model
{
    protected $table = 'op_rh_personal_baja';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_personal',
        'fecha_baja',
        'motivo',
        'detalle',
        'solucion',
        'proceso',
        'estado_proceso'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_personal' => 'integer',
        'estado_proceso' => 'integer',
        'fecha_baja' => 'date',
    ];

}

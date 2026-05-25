<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhPersonalBaja2 extends Model
{
    protected $table = 'op_rh_personal_baja_2';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'nombre',
        'puesto',
        'fecha',
        'causa',
        'motivo',
        'archivo',
        'ine'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
    ];
}


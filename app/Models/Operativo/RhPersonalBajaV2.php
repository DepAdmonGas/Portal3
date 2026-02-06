<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhPersonalBajaV2 extends Model
{
    protected $table = 'op_rh_personal_baja_v2';
    protected $primaryKey = 'id_baja';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_personal',
        'fecha_baja',
        'motivo_baja',
        'archivo_renuncia',
        'proceso',
        'status'
    ];

    protected $casts = [
        'id_baja' => 'integer',
        'id_personal' => 'integer',
        'status' => 'integer'
    ];

}

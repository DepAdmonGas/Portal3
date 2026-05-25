<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RhVacacionesPagoDetalle extends Model
{
    protected $table = 'op_rh_vacaciones_pago_detalle';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_vacaciones_pago',
        'id_personal',
        'year',
        'dias'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_vacaciones_pago' => 'integer',
        'id_personal' => 'integer',
        'year' => 'integer',
        'dias' => 'integer'
    ];
}


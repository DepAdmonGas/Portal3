<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhVacacionesPago extends Model
{
    protected $table = 'op_rh_vacaciones_pago';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'mes',
        'year',
        'estado'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'mes' => 'integer',
        'year' => 'integer',
        'estado' => 'integer'
    ];
}

<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class RequisitosLegalesMunAlcEst extends Model
{
    protected $table = 'rl_requisitos_legales_munalcest';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'mun_alc_est',
        'id_estacion',
        'disabled',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'disabled' => 'integer',
        'estado' => 'integer',
    ];
}

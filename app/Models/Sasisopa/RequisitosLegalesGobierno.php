<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisitosLegalesGobierno extends Model
{
    protected $table = 'rl_requisitos_legales_gobierno';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'gobierno',
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

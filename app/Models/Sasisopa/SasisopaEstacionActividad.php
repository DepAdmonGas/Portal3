<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SasisopaEstacionActividad extends Model
{
    protected $table = 'sa_sasisopa_estaciones_actividad';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_actividad',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_actividad' => 'integer',
    ];
}

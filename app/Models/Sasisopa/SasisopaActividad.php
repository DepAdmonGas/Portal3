<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SasisopaActividad extends Model
{
    protected $table = 'sa_sasisopa_actividades';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_sasisopa',
        'formato',
        'actividad',
        'periodicidad',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_sasisopa' => 'integer',
    ];
}

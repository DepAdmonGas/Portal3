<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SasisopaEstacion extends Model
{
    protected $table = 'sa_sasisopa_estaciones';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_sasisopa',
        'id_estacion',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_sasisopa' => 'integer',
        'id_estacion' => 'integer',
        'estado' => 'integer',
    ];

}

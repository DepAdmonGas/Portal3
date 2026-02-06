<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CambioPrecio extends Model
{
    protected $table = 'tb_cambio_precio';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fechacreacion',
        'fecha',
        'hora',
        'gsuper',
        'gpremium',
        'gdiesel',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fechacreacion' => 'datetime',
        'fecha' => 'date',
        'hora' => 'string',
        'gsuper' => 'float',
        'gpremium' => 'float',
        'gdiesel' => 'float',
        'estado' => 'integer',
    ];
}

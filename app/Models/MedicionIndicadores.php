<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicionIndicadores extends Model
{
    protected $table = 'tb_medicion_indicadores';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'fechacreacion',
        'id_estacion',
        'objeto',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'fechacreacion' => 'datetime',
        'id_estacion' => 'integer',
        'objeto' => 'integer',
    ];
}

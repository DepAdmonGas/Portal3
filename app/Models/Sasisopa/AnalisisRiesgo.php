<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class AnalisisRiesgo extends Model
{
    protected $table = 'tb_analisis_riesgo';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'descripcion',
        'documento',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'descripcion' => 'string',
        'documento' => 'string',
    ];
}

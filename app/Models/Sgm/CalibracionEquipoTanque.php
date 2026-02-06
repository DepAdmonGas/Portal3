<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibracionEquipoTanque extends Model
{
    protected $table = 'tb_calibracion_equipos_tanques';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_calibracion',
        'id_tanque',
        'resultado1',
        'resultado2',
        'resultados',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_calibracion' => 'integer',
        'id_tanque' => 'integer',
        'resultado1' => 'string',
        'resultado2' => 'string',
        'resultados' => 'string',
    ];
}

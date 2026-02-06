<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeguimientoCalibracionEquipo extends Model
{
    protected $table = 'sgm_seguimiento_calibracion_equipo';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_seguimiento',
        'respuesta_uno',
        'respuesta_dos',
        'respuesta_tres',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_seguimiento' => 'integer',
        'respuesta_uno' => 'float',
        'respuesta_dos' => 'string',
        'respuesta_tres' => 'string',
    ];
}

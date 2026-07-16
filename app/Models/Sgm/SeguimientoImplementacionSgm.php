<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class SeguimientoImplementacionSgm extends Model
{
    protected $table = 'sgm_seguimiento_implementacion_sgm';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_seguimiento',
        'respuesta_uno',
        'respuesta_dos',
        'respuesta_tres',
        'respuesta_cuatro',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_seguimiento' => 'integer',
        'respuesta_uno' => 'float',
        'respuesta_dos' => 'float',
        'respuesta_tres' => 'string',
        'respuesta_cuatro' => 'string',
    ];
}

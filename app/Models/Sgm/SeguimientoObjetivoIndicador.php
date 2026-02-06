<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeguimientoObjetivoIndicador extends Model
{
    protected $table = 'sgm_seguimiento_objetivo_indicador';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'hora',
        'lugar',
        'realizadopor',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'lugar' => 'string',
        'realizadopor' => 'integer',
        'estado' => 'integer',
    ];
}

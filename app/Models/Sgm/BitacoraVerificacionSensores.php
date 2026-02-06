<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraVerificacionSensores extends Model
{
    protected $table = 'sgm_bitacora_verificacion_sensores';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_programa',
        'fecha',
        'hora',
        'no_tanque',
        'marca',
        'capacidad',
        'producto',
        'interno_externo',
        'verificacion_movimiento',
        'metodo_nivel',
        'realizadopor',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_programa' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'no_tanque' => 'integer',
        'marca' => 'string',
        'capacidad' => 'string',
        'producto' => 'string',
        'interno_externo' => 'string',
        'verificacion_movimiento' => 'string',
        'metodo_nivel' => 'string',
        'realizadopor' => 'integer',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JuntasSolicitudCambio extends Model
{
    protected $table = 'tb_juntas_solicitud_cambio';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_junta',
        'id_usuario',
        'id_puesto',
        'motivo_cambio',
        'descripcion',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'id_personal_autorizado',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_junta' => 'integer',
        'id_usuario' => 'integer',
        'id_puesto' => 'integer',
        'estatus' => 'integer',
        'fecha' => 'date',
        'hora_inicio' => 'datetime:H:i:s',
        'hora_fin' => 'datetime:H:i:s',
    ];
}

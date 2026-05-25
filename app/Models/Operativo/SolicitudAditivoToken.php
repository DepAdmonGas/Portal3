<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SolicitudAditivoToken extends Model
{
    protected $table = 'op_solicitud_aditivo_token';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
        'fecha_creacion',
        'token'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_usuario' => 'integer',
        'fecha_creacion' => 'datetime',
        'token' => 'integer'
    ];
}


<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SolicitudAditivoFirma extends Model
{
    protected $table = 'op_solicitud_aditivo_firma';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_reporte',
        'id_usuario',
        'fecha',
        'tipo_firma',
        'firma'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_reporte' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime'
    ];
}


<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SolicitudChequeFirma extends Model
{
    protected $table = 'op_solicitud_cheque_firma';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_usuario',
        'fecha',
        'tipo_firma',
        'firma'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_solicitud' => 'integer',
        'id_usuario' => 'integer',
        'fecha' => 'datetime'
    ];
}


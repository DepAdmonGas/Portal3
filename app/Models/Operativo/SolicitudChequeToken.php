<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SolicitudChequeToken extends Model
{
    protected $table = 'op_solicitud_cheque_token';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_usuario',
        'fecha_creacion',
        'token'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_solicitud' => 'integer',
        'id_usuario' => 'integer',
        'fecha_creacion' => 'datetime',
        'token' => 'integer'
    ];
}


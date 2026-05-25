<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SolicitudValeDocumento extends Model
{
    protected $table = 'op_solicitud_vale_documento';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'nombre',
        'documento'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_solicitud' => 'integer'
    ];
}


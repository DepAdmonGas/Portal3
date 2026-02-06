<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudChequeDocumento extends Model
{
    protected $table = 'op_solicitud_cheque_documento';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'fecha',
        'nombre',
        'documento'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_solicitud' => 'integer',
        'fecha' => 'datetime'
    ];
}

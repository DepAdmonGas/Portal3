<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class SolicitudChequeTelcel extends Model
{
    protected $table = 'op_solicitud_cheque_telcel';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_year',
        'id_mes',
        'id_estacion',
        'fecha',
        'factura',
        'c_pago'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_year' => 'integer',
        'id_mes' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'datetime'
    ];
}


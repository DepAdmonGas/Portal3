<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudVale extends Model
{
    protected $table = 'op_solicitud_vale';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_year',
        'id_mes',
        'id_estacion',
        'cuenta',
        'id_usuario',
        'folio',
        'fecha',
        'hora',
        'monto',
        'moneda',
        'concepto',
        'solicitante',
        'autorizado_por',
        'metodo_autorizacion',
        'observaciones',
        'depto',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_year' => 'integer',
        'id_mes' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'folio' => 'integer',
        'fecha' => 'date',
        'hora' => 'datetime:H:i:s',
        'monto' => 'float',
        'depto' => 'integer',
        'status' => 'integer'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCheque extends Model
{
    protected $table = 'op_solicitud_cheque';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_year',
        'id_mes',
        'id_estacion',
        'fecha',
        'hora',
        'beneficiario',
        'monto',
        'moneda',
        'no_factura',
        'email',
        'concepto',
        'solicitante',
        'telefono',
        'cfdi',
        'metodo_pago',
        'forma_pago',
        'banco',
        'no_cuenta',
        'cuenta_clabe',
        'referencia',
        'observaciones',
        'depto',
        'razonsocial',
        'status'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_year' => 'integer',
        'id_mes' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
        'hora' => 'datetime:H:i:s',
        'monto' => 'float',
        'depto' => 'integer',
        'status' => 'integer'
    ];
}

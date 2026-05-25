<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class FacturaMonederosPago extends Model
{
    protected $table = 'op_factura_monederos_pago';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'year',
        'mes',
        'folio',
        'fecha_creacion',
        'no_factura',
        'monto',
        'archivo_factura',
        'archivo_comprobante_pago',
        'archivo_factura_xml',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'year' => 'integer',
        'mes' => 'integer',
        'folio' => 'integer',
        'fecha_creacion' => 'datetime',
        'no_factura' => 'string',
        'monto' => 'double',
        'archivo_factura' => 'string',
        'archivo_comprobante_pago' => 'string',
        'archivo_factura_xml' => 'string',
        'estado' => 'integer',
    ];
}


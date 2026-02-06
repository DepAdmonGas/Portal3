<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngresosFacturacionContabilidad extends Model
{
    protected $table = 'op_ingresos_facturacion_contabilidad';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_year',
        'detalle',
        'posicion',
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_year' => 'integer',
        'detalle' => 'string',
        'posicion' => 'integer',
        'enero' => 'double',
        'febrero' => 'double',
        'marzo' => 'double',
        'abril' => 'double',
        'mayo' => 'double',
        'junio' => 'double',
        'julio' => 'double',
        'agosto' => 'double',
        'septiembre' => 'double',
        'octubre' => 'double',
        'noviembre' => 'double',
        'diciembre' => 'double',
    ];
}

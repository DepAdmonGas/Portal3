<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AceiteDocumento extends Model
{
    protected $table = 'op_aceites_documento';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no usa created_at ni updated_at

    protected $fillable = [
        'id_mes',
        'fecha',
        'ficha_deposito',
        'fecha_evaluacion_ficha',
        'puntaje_ficha',
        'imagen_bodega',
        'factura_venta',
        'fecha_evaluacion_factura',
        'puntaje_factura',
    ];

    protected $casts = [
        'id_mes' => 'integer',
        'fecha' => 'date',
        'fecha_evaluacion_ficha' => 'date',
        'puntaje_ficha' => 'integer',
        'fecha_evaluacion_factura' => 'date',
        'puntaje_factura' => 'integer',
    ];

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteCrePipa extends Model
{
    protected $table = 're_reporte_cre_pipas';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id_re_producto',
        'pipa_numero',
        'volumen',
        'precio_litro',
        'costo_flete',
        'no_factura',
        'nombre_razonsocial',
        'importe_total',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_re_producto' => 'integer',
        'pipa_numero' => 'integer',
        'volumen' => 'float',
        'precio_litro' => 'float',
        'costo_flete' => 'float',
        'importe_total' => 'float',
    ];

}

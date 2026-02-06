<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DescargaTuxpa extends Model
{
    protected $table = 'op_descarga_tuxpa';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'fechahora',
        'folio',
        'id_estacion',
        'id_usuario',
        'fecha_llegada',
        'hora_llegada',
        'producto',
        'no_factura',
        'sellos',
        'inventario_inicial',
        'nice',
        'detuvo_venta',
        'inventario_final',
        'metro_contador',
        'metro_contador20',
        'merma',
        'operador',
        'transportista',
        'no_factura_remision',
        'litros',
        'precio_litro',
        'unidad',
        'cuenta_litros',
    ];

    protected $casts = [
        'id' => 'integer',
        'fechahora' => 'datetime',
        'folio' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha_llegada' => 'date',
        'hora_llegada' => 'string',
        'producto' => 'string',
        'no_factura' => 'string',
        'sellos' => 'string',
        'inventario_inicial' => 'string',
        'nice' => 'string',
        'detuvo_venta' => 'string',
        'inventario_final' => 'string',
        'metro_contador' => 'string',
        'metro_contador20' => 'string',
        'merma' => 'float',
        'operador' => 'string',
        'transportista' => 'string',
        'no_factura_remision' => 'string',
        'litros' => 'double',
        'precio_litro' => 'double',
        'unidad' => 'string',
        'cuenta_litros' => 'double',
    ];
}

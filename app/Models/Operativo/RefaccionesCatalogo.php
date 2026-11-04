<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class RefaccionesCatalogo extends Model
{
    protected $table = 'op_refacciones_catalogo';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'sku', 'tipo_articulo', 'nombre', 'descripcion', 'medida', 'modelo',
        'marca', 'categoria', 'unidad_medida', 'stock_minimo', 'costo_unitario', 'imagen',
        'activo', 'creado_por', 'creado_en', 'actualizado_en',
    ];

    protected $casts = [
        'id' => 'integer',
        'stock_minimo' => 'float',
        'costo_unitario' => 'float',
        'activo' => 'boolean',
        'creado_por' => 'integer',
    ];
}

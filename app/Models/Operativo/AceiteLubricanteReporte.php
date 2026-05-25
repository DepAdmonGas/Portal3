<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

class AceiteLubricanteReporte extends Model
{
    protected $table = 'op_aceites_lubricantes_reporte';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no usa created_at ni updated_at

    protected $fillable = [
        'id_mes',
        'id_aceite',
        'concepto',
        'precio',
        'bodega',
        'exibidores',
        'pedido',
        'inventario_bodega',
        'inventario_exibidores',
        'producto_facturado',
        'factura_venta_mostrador'
    ];

    protected $casts = [
        'id_mes' => 'integer',
        'id_aceite' => 'integer',
        'precio' => 'double',
        'bodega' => 'integer',
        'exibidores' => 'integer',
        'pedido' => 'integer',
        'inventario_bodega' => 'integer',
        'inventario_exibidores' => 'integer',
        'producto_facturado' => 'double',
        'factura_venta_mostrador' => 'double',
    ];
}


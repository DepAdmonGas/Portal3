<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PedidoLimpiezaDetalle extends Model
{
    protected $table = 'tb_pedido_limpieza_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'no_reporte',
        'id_estacion',
        'unidad',
        'producto',
        'piezas',
    ];

    protected $casts = [
        'id' => 'integer',
        'no_reporte' => 'integer',
        'id_estacion' => 'integer',
        'piezas' => 'integer',
    ];
}

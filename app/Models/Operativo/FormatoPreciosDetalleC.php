<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormatoPreciosDetalleC extends Model
{
    protected $table = 'op_formato_precios_detalle_c';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_precio',
        'producto',
        'pemex',
        'delivery_montera',
        'delivery_tuxpan',
        'delivery_vopak',
        'pickup_montera',
        'pickup_tuxpan',
        'pickup_vopak',
        'pickup_tizayuca',
        'pickup_puebla',
        'p1',
        'p2',
        'p3',
        'p4',
        'p5',
        'p6',
        'p7',
        'p8',
        'p9',
        'p10',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_precio' => 'integer',
        'producto' => 'string',
        'pemex' => 'double',
        'delivery_montera' => 'double',
        'delivery_tuxpan' => 'double',
        'delivery_vopak' => 'double',
        'pickup_montera' => 'double',
        'pickup_tuxpan' => 'double',
        'pickup_vopak' => 'double',
        'pickup_tizayuca' => 'double',
        'pickup_puebla' => 'double',
        'p1' => 'integer',
        'p2' => 'integer',
        'p3' => 'integer',
        'p4' => 'integer',
        'p5' => 'integer',
        'p6' => 'integer',
        'p7' => 'integer',
        'p8' => 'integer',
        'p9' => 'integer',
        'p10' => 'integer',
    ];
}

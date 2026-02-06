<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportePedidoLimpieza extends Model
{
    protected $table = 'tb_reporte_pedido_limpieza';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'no_reporte',
        'id_estacion',
        'id_usuario',
        'fecha_hora',
        'estatus'
    ];

    protected $casts = [
        'id' => 'int',
        'no_reporte' => 'int',
        'id_estacion' => 'int',
        'id_usuario' => 'int',
        'fecha_hora' => 'datetime',
        'estatus' => 'int'
    ];
}

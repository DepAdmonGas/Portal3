<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteRecepcion extends Model
{
    protected $table = 'tb_reporte_recepcion';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'no_reporte',
        'id_estacion',
        'id_usuario',
        'fecha_hora',
        'comentario',
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

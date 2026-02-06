<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportesAceites extends Model
{
    protected $table = 'tb_reportes_aceites';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'no_reporte',
        'id_estacion',
        'id_usuario',
        'fecha_hora',
        'recepcion_estatus',
        'venta_estatus',
        'fisico_estatus',
        'archivo',
        'estatus',
    ];

    protected $casts = [
        'id' => 'integer',
        'no_reporte' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fecha_hora' => 'datetime',
        'recepcion_estatus' => 'integer',
        'venta_estatus' => 'integer',
        'fisico_estatus' => 'integer',
        'estatus' => 'integer',
    ];
}

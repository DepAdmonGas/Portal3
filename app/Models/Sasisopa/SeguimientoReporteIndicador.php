<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class SeguimientoReporteIndicador extends Model
{
    protected $table = 'tb_seguimiento_reporte_indicador';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'capacitacion',
        'exp_cliente',
        'ventas',
        'medidas_correctivas',
        'fecha_aplicacion'
    ];

    protected $casts = [
        'id' => 'int',
        'id_estacion' => 'int',
        'id_usuario' => 'int',
        'fecha' => 'date',
        'fecha_aplicacion' => 'date'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReporteJarreo extends Model
{
    protected $table = 'tb_reporte_jarreo';

    protected $primaryKey = 'id_index';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'id_estacion',
        'id_usuario',
        'fecha_hora',
        'fuera_rango',
        'corte_pistola',
        'holograma',
        'cont_mecanico',
        'estatus'
    ];

    protected $casts = [
        'id_index' => 'int',
        'id' => 'int',
        'id_estacion' => 'int',
        'id_usuario' => 'int',
        'fecha_hora' => 'date',
        'fuera_rango' => 'int',
        'corte_pistola' => 'int',
        'holograma' => 'int',
        'cont_mecanico' => 'int',
        'estatus' => 'int'
    ];
}

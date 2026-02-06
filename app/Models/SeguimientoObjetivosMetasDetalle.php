<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeguimientoObjetivosMetasDetalle extends Model
{
    protected $table = 'tb_seguimiento_objetivos_metas_detalle';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_seguimiento',
        'fecha',
        'objetivo_meta',
        'nivel_cumplimiento',
        'medidas',
        'fecha_aplicacion'
    ];

    protected $casts = [
        'id' => 'int',
        'id_seguimiento' => 'int',
        'fecha' => 'date',
        'fecha_aplicacion' => 'date'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequisicionObraFormato12 extends Model
{
    protected $table = 'tb_requisicion_obra_formato_12';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_requisicion',
        'fecha',
        'archivo',
        'dia',
        'mes',
        'year',
        'municipio',
        'estado',
        'trabajo_realizar',
        'descripcion',
        'area',
        'fecha_inicio',
        'fecha_termino',
        'hora_inicio',
        'hora_termino',
        'prestador_servicio',
        'cprtp',
        'cteppc',
        'nombre_empresa',
        'nombre_responsable'
    ];

    protected $casts = [
        'id' => 'int',
        'id_requisicion' => 'int',
        'fecha' => 'datetime',
        'dia' => 'int',
        'year' => 'int',
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'hora_inicio' => 'datetime:H:i',
        'hora_termino' => 'datetime:H:i',
        'cprtp' => 'int',
        'cteppc' => 'int'
    ];
}

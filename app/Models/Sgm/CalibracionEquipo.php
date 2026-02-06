<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibracionEquipo extends Model
{
    protected $table = 'tb_calibracion_equipos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'folio',
        'fecha',
        'hora',
        'fecha_termino',
        'hora_termino',
        'equipo',
        'observaciones',
        'responsable_verificacion',
        'resultados',
        'categoria',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'folio' => 'integer',
        'fecha' => 'date',
        'hora' => 'string',
        'fecha_termino' => 'date',
        'hora_termino' => 'string',
        'equipo' => 'string',
        'observaciones' => 'string',
        'responsable_verificacion' => 'string',
        'resultados' => 'string',
        'categoria' => 'integer',
        'estado' => 'integer',
    ];
}

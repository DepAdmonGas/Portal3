<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarioActividad extends Model
{
    protected $table = 'tb_calendario_actividades';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_actividad',
        'folio',
        'fecha_inicio',
        'fecha_termino',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_actividad' => 'integer',
        'folio' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'estado' => 'integer',
    ];
}

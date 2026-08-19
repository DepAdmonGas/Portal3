<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class CalendarioActividadSgm extends Model
{
    protected $table = 'tb_calendario_actividades_sgm';

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
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_termino' => 'date:Y-m-d',
        'estado' => 'integer',
    ];
}

<?php

namespace App\Models\Sasisopa;

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


    public function actividad()
    {
        return $this->belongsTo(
            SasisopaActividad::class,
            'id_actividad'
        );
    }
}

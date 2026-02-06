<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarioActividadMtto extends Model
{
    protected $table = 'ds_calendario_actividades_mtto';

    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false; // La tabla no maneja created_at / updated_at

    protected $fillable = [
        'id_estacion',
        'id_periodicidad',
        'folio',
        'fecha_inicio',
        'fecha_termino',
        'estado',
    ];

    protected $casts = [
        'id_estacion' => 'integer',
        'id_periodicidad' => 'integer',
        'folio' => 'integer',
        'estado' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
    ];


}

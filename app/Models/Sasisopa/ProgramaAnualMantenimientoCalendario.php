<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualMantenimientoCalendario extends Model
{
    protected $table = 'po_programa_anual_mantenimiento_calendario';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_mantenimiento',
        'fecha'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_mantenimiento' => 'integer',
        'fecha' => 'date'
    ];
}
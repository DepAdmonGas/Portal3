<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoCorrectivo extends Model
{
    protected $table = 'po_mantenimiento_correctivo';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'folio',
        'id_estacion',
        'id_usuario',
        'fechacreacion',
        'horacreacion',
        'nombre_equipo',
        'descripcion_hallazgo',
        'descripcion_actividad',
        'herramienta',
    ];

    protected $casts = [
        'id' => 'integer',
        'folio' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'fechacreacion' => 'date',
        'horacreacion' => 'datetime:H:i:s',
    ];

}

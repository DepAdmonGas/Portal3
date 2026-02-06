<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MantenimientoVerificar extends Model
{
    protected $table = 'po_mantenimiento_verificar';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'folio',
        'id_estacion',
        'id_equipo',
        'fechacreacion',
        'horacreacion',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'folio' => 'integer',
        'id_estacion' => 'integer',
        'id_equipo' => 'integer',
        'estado' => 'integer',
        'fechacreacion' => 'date',
        'horacreacion' => 'datetime:H:i:s',
    ];


}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudGafetes extends Model
{
    protected $table = 'tb_solicitud_gafetes';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'no_reporte',
        'id_estacion',
        'usuario',
        'fecha',
        'clave',
        'nombre',
        'foto',
        'comentarios',
        'estatus'
    ];

    protected $casts = [
        'id' => 'int',
        'no_reporte' => 'int',
        'fecha' => 'date'
    ];
}

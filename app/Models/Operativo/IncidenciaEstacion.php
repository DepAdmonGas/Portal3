<?php

namespace App\Models\Operativo;

use Illuminate\Database\Eloquent\Model;

/**
 * Incidencia por estación (tabla heredada del proyecto anterior).
 *
 * id_estacion vive en el espacio de ids de tb_estaciones, igual que en el
 * módulo anterior, y se cubre con las gasolineras de numlista <= 8
 * (ids 1-7 y 14).
 */
class IncidenciaEstacion extends Model
{
    protected $table = 'op_incidencias_estaciones';

    protected $primaryKey = 'id_incidencias_estaciones';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
        'hora',
        'incidente',
        'responsable',
        'asunto',
        'comentarios',
        'archivo',
    ];

    protected $casts = [
        'id_incidencias_estaciones' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
    ];
}

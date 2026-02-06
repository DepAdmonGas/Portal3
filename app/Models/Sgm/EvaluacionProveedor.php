<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluacionProveedor extends Model
{
    protected $table = 'sgm_evaluacion_proveedores';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_orden_servicio',
        'fecha',
        'hora_inicio',
        'hora_termino',
        'nombre_proveedor',
        'no_acreditacion',
        'observaciones',
        'id_personal_evaluacion',
        'respuesta_1',
        'respuesta_2',
        'respuesta_3',
        'respuesta_4',
        'respuesta_5',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_orden_servicio' => 'integer',
        'fecha' => 'date',
        'hora_inicio' => 'string',
        'hora_termino' => 'string',
        'nombre_proveedor' => 'string',
        'no_acreditacion' => 'string',
        'observaciones' => 'string',
        'id_personal_evaluacion' => 'integer',
        'respuesta_1' => 'integer',
        'respuesta_2' => 'integer',
        'respuesta_3' => 'integer',
        'respuesta_4' => 'integer',
        'respuesta_5' => 'integer',
        'estado' => 'integer',
    ];
}

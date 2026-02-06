<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanAtencionHallazgo extends Model
{
    protected $table = 'sgm_plan_atencion_hallazgos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_auditoria',
        'fecha',
        'sitio_area',
        'responsable',
        'hallazgo',
        'analisis_causa',
        'acciones_hallazgos',
        'fecha_complimiento',
        'recursos_implementacion',
        'fecha_atencion_hallazgos',
        'responsable_sgm',
        'realizadopor',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_auditoria' => 'integer',
        'fecha' => 'date',
        'sitio_area' => 'string',
        'responsable' => 'integer',
        'hallazgo' => 'string',
        'analisis_causa' => 'string',
        'acciones_hallazgos' => 'string',
        'fecha_complimiento' => 'string',
        'recursos_implementacion' => 'string',
        'fecha_atencion_hallazgos' => 'date',
        'responsable_sgm' => 'integer',
        'realizadopor' => 'integer',
    ];
}

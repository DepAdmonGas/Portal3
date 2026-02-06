<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanAuditoriaAuditor extends Model
{
    protected $table = 'sgm_plan_auditoria_auditor';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_plan',
        'nombre',
        'area_actividad',
        'categoria',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_plan' => 'integer',
        'nombre' => 'string',
        'area_actividad' => 'string',
        'categoria' => 'string',
    ];
}

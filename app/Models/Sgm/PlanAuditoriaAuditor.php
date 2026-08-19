<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class PlanAuditoriaAuditor extends Model
{
    protected $table = 'sgm_plan_auditoria_auditor';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_plan',
        'id_usuario',
        'nombre',
        'area_actividad',
        'categoria',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_plan' => 'integer',
        'id_usuario' => 'integer',
        'nombre' => 'string',
        'area_actividad' => 'string',
        'categoria' => 'string',
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario',
            'id'
        );
    }
}

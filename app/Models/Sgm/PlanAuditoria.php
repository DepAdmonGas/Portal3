<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class PlanAuditoria extends Model
{
    protected $table = 'sgm_plan_auditoria';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_auditoria',
        'fecha',
        'nom_director',
        'ubicacion_instalacion',
        'objetivo_auditoria',
        'alcance_auditoria',
        'fecha_programada',
        'sitio',
        'metodo_auditoria',
        'ajuste_plan',
        'asignacion_recursos',
        'preparativos_logisticos',
        'acciones',
        'realizadopor',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_auditoria' => 'integer',
        'fecha' => 'date',
        'nom_director' => 'string',
        'ubicacion_instalacion' => 'string',
        'objetivo_auditoria' => 'string',
        'alcance_auditoria' => 'string',
        'fecha_programada' => 'date',
        'sitio' => 'string',
        'metodo_auditoria' => 'string',
        'ajuste_plan' => 'string',
        'asignacion_recursos' => 'string',
        'preparativos_logisticos' => 'string',
        'acciones' => 'string',
        'realizadopor' => 'integer',
    ];

    public function auditoria()
    {
        return $this->belongsTo(
            Auditoria::class,
            'id_auditoria',
            'id'
        );
    }

    public function auditores()
    {
        return $this->hasMany(
            PlanAuditoriaAuditor::class,
            'id_plan'
        );
    }
}

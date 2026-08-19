<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Estacion;

class Auditoria extends Model
{
    protected $table = 'sgm_auditoria';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'year',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'year' => 'integer',
        'estado' => 'integer',
    ];

    public function estacion()
    {
        return $this->belongsTo(
            Estacion::class,
            'id_estacion',
            'id'
        );
    }

    public function planAuditoria()
    {
        return $this->hasOne(
            PlanAuditoria::class,
            'id_auditoria'
        );
    }

    public function hallazgos()
    {
        return $this->hasOne(
            HallazgoAuditoria::class,
            'id_auditoria'
        );
    }

    public function planAtencionHallazgos()
    {
        return $this->hasOne(
            PlanAtencionHallazgo::class,
            'id_auditoria'
        );
    }
}

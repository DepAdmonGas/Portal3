<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class HallazgoAuditoria extends Model
{
    protected $table = 'sgm_hallazgo_auditoria';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_auditoria',
        'fecha',
        'fecha_ubicacion',
        'objetivo_auditoria',
        'alcance_auditoria',
        'comentarios',
        'nota',
        'motivos',
        'conclusiones',
        'lugar_fecha',
        'auditor_lider',
        'responsable_sgm',
        'realizadopor',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_auditoria' => 'integer',
        'fecha' => 'date',
        'fecha_ubicacion' => 'string',
        'objetivo_auditoria' => 'string',
        'alcance_auditoria' => 'string',
        'comentarios' => 'string',
        'nota' => 'string',
        'motivos' => 'string',
        'conclusiones' => 'string',
        'lugar_fecha' => 'string',
        'auditor_lider' => 'integer',
        'responsable_sgm' => 'integer',
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
}

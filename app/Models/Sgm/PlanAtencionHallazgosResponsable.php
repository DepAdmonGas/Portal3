<?php

namespace App\Models\Sgm;

use App\Models\Usuario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanAtencionHallazgosResponsable extends Model
{
    protected $table = 'sgm_plan_atencion_hallazgos_responsables';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'id_plan',
        'id_responsable',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_plan' => 'integer',
        'id_responsable' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            PlanAtencionHallazgo::class,
            'id_plan',
            'id'
        );
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(
            Usuario::class,
            'id_responsable',
            'id'
        );
    }
}

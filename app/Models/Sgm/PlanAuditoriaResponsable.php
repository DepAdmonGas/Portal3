<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class PlanAuditoriaResponsable extends Model
{
    protected $table = 'sgm_plan_auditoria_responsable';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

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

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_responsable',
            'id'
        );
    }
}

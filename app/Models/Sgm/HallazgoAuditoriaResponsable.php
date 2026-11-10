<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;
use App\Models\Usuario;

class HallazgoAuditoriaResponsable extends Model
{
    protected $table = 'sgm_hallazgo_auditoria_responsable';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_hallazgo',
        'id_responsable',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_hallazgo' => 'integer',
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

    public function hallazgo()
    {
        return $this->belongsTo(
            HallazgoAuditoria::class,
            'id_hallazgo',
            'id'
        );
    }
}

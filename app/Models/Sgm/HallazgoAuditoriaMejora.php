<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class HallazgoAuditoriaMejora extends Model
{
    protected $table = 'sgm_hallazgo_auditoria_mejora';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_hallazgo',
        'descripcion',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_hallazgo' => 'integer',
        'descripcion' => 'string',
    ];
}

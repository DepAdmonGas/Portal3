<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HallazgoAuditoriaAuditor extends Model
{
    protected $table = 'sgm_hallazgo_auditoria_auditor';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_hallazgo',
        'nombre',
        'rol',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_hallazgo' => 'integer',
        'nombre' => 'string',
        'rol' => 'string',
    ];
}

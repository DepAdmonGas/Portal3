<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}

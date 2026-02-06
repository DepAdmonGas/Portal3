<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HallazgoAuditoriaResultado extends Model
{
    protected $table = 'sgm_hallazgo_auditoria_resultado';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_hallazgo',
        'id_elemento',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_hallazgo' => 'integer',
        'id_elemento' => 'integer',
        'resultado' => 'string',
    ];
}

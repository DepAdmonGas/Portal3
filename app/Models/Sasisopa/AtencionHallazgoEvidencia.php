<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtencionHallazgoEvidencia extends Model
{
    protected $table = 'tb_atencion_hallazgos_evidencia';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_hallazgo',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_hallazgo' => 'integer',
        'archivo' => 'string',
    ];
}

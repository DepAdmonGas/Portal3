<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtencionHallazgo extends Model
{
    protected $table = 'tb_atencion_hallazgos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'folio',
        'fecha_auditoria',
        'no_control',
        'tipo_auditoria',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'folio' => 'integer',
        'fecha_auditoria' => 'date',
        'no_control' => 'string',
        'tipo_auditoria' => 'string',
    ];
}

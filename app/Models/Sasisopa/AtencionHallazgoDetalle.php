<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtencionHallazgoDetalle extends Model
{
    protected $table = 'tb_atencion_hallazgos_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_atencion',
        'id_sasisopa',
        'hallazgos',
        'accion',
        'fecha_implementacion',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_atencion' => 'integer',
        'id_sasisopa' => 'integer',
        'hallazgos' => 'string',
        'accion' => 'string',
        'fecha_implementacion' => 'date',
    ];
}

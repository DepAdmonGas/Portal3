<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BitacoraCalibracionEquipoDetalle extends Model
{
    protected $table = 'sgm_bitacora_calibracion_equipo_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_programa',
        'id_equipo',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_programa' => 'integer',
        'id_equipo' => 'integer',
        'resultado' => 'string',
    ];
}

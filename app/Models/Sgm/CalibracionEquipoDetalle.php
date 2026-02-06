<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibracionEquipoDetalle extends Model
{
    protected $table = 'tb_calibracion_equipos_detalle';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_calibracion',
        'categoria',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_calibracion' => 'integer',
        'categoria' => 'string',
        'resultado' => 'string',
    ];
}

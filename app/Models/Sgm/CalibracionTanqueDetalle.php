<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class CalibracionTanqueDetalle extends Model
{
    protected $table =
    'tb_calibracion_tanques_detalle';

    protected $primaryKey =
    'id';

    public $incrementing =
    true;

    protected $keyType =
    'int';

    public $timestamps =
    false;

    protected $fillable = [
        'id_calibracion',
        'id_documento',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_calibracion' => 'integer',
        'id_documento' => 'integer',
    ];
}

<?php

namespace App\Models\Sgm;

use Illuminate\Database\Eloquent\Model;

class CalibracionTanque extends Model
{
    protected $table = 'tb_calibracion_tanques';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'fecha',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'fecha' => 'date',
    ];
}

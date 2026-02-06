<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetectorHumo extends Model
{
    protected $table = 'tb_detector_humo';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'no_detector',
        'ubicacion',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'no_detector' => 'integer',
        'ubicacion' => 'string',
        'estado' => 'integer',
    ];
}

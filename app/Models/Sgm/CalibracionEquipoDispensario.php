<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibracionEquipoDispensario extends Model
{
    protected $table = 'tb_calibracion_equipos_dispensario';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_calibracion',
        'id_dispensario',
        'resultado1',
        'resultado2',
        'resultado3',
        'resultado4',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_calibracion' => 'integer',
        'id_dispensario' => 'integer',
        'resultado1' => 'string',
        'resultado2' => 'string',
        'resultado3' => 'string',
        'resultado4' => 'string',
    ];
}

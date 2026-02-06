<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapacitacionExternaPersonal extends Model
{
    protected $table = 'tb_capacitacion_externa_personal';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_capacitacion',
        'id_empleado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_capacitacion' => 'integer',
        'id_empleado' => 'integer',
    ];
}

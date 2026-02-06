<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualCapacitacionExternaPersonal extends Model
{
    protected $table = 'sgm_programa_anual_capacitacion_externa_personal';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_capacitacion',
        'id_usuario',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_capacitacion' => 'integer',
        'id_usuario' => 'integer',
    ];
}

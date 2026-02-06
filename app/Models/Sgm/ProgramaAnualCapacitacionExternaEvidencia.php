<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualCapacitacionExternaEvidencia extends Model
{
    protected $table = 'sgm_programa_anual_capacitacion_externa_evidencia';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_capacitacion',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_capacitacion' => 'integer',
        'archivo' => 'string',
    ];
}

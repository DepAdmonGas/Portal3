<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualSimulacrosEvaluacion extends Model
{
    protected $table = 'tb_programa_anual_simulacros_evaluacion';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_programa',
        'archivo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_programa' => 'integer',
    ];
}

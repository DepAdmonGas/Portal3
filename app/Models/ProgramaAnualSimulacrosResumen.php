<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualSimulacrosResumen extends Model
{
    protected $table = 'tb_programa_anual_simulacros_resumen';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_programa',
        'resumen',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_programa' => 'integer',
    ];

}

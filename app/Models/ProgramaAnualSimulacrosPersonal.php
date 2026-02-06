<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaAnualSimulacrosPersonal extends Model
{
    protected $table = 'tb_programa_anual_simulacros_personal';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_programa',
        'nombre',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_programa' => 'integer',
    ];


}

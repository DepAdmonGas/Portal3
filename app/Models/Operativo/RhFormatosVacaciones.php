<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RhFormatosVacaciones extends Model
{
    protected $table = 'op_rh_formatos_vacaciones';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'id_formulario',
        'id_usuario',
        'num_dias',
        'fecha_inicio',
        'fecha_termino',
        'fecha_regreso',
        'observaciones'
    ];

    protected $casts = [
        'id' => 'integer',
        'id_formulario' => 'integer',
        'id_usuario' => 'integer',
        'num_dias' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'fecha_regreso' => 'date'
    ];

}

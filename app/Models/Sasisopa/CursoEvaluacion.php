<?php

namespace App\Models\Sasisopa;

use Illuminate\Database\Eloquent\Model;

class CursoEvaluacion extends Model
{
    protected $table = 'tb_cursos_evaluacion';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_calendario',
        'no_pregunta',
        'resultado',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_calendario' => 'integer',
        'no_pregunta' => 'integer',
        'resultado' => 'integer',
    ];
}

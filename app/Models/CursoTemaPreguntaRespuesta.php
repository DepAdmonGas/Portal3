<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CursoTemaPreguntaRespuesta extends Model
{
    protected $table = 'tb_cursos_temas_preguntas_respuestas';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_pregunta',
        'num_respuesta',
        'titulo',
        'valor',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_pregunta' => 'integer',
        'num_respuesta' => 'integer',
        'titulo' => 'string',
        'valor' => 'integer',
    ];
}

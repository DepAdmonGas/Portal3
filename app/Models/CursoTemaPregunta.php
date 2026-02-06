<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CursoTemaPregunta extends Model
{
    protected $table = 'tb_cursos_temas_preguntas';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_tema',
        'num_pregunta',
        'titulo',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_tema' => 'integer',
        'num_pregunta' => 'integer',
        'titulo' => 'string',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CursoModulo extends Model
{
    protected $table = 'tb_cursos_modulos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'num_modulo',
        'titulo',
    ];

    protected $casts = [
        'id' => 'integer',
        'num_modulo' => 'integer',
        'titulo' => 'string',
    ];
}

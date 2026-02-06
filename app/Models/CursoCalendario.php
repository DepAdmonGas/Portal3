<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CursoCalendario extends Model
{
    protected $table = 'tb_cursos_calendario';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'fecha_programada',
        'fecha_real',
        'id_estacion',
        'id_personal',
        'id_tema',
        'resultado',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha_programada' => 'date',
        'fecha_real' => 'date',
        'id_estacion' => 'integer',
        'id_personal' => 'integer',
        'id_tema' => 'integer',
        'resultado' => 'float',
        'observaciones' => 'string',
        'estado' => 'integer',
    ];
}

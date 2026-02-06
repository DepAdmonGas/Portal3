<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Implementacionsa extends Model
{
    protected $table = 'tb_implementacionsa';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id_estacion',
        'id_usuario',
        'fecha',
        'preguntas',
        'respuestas',
        'puntos',
    ];

    protected $casts = [
        'id' => 'integer',
        'id_estacion' => 'integer',
        'id_usuario' => 'integer',
        'preguntas' => 'integer',
        'respuestas' => 'integer',
        'puntos' => 'integer',
        'fecha' => 'datetime',
    ];
}
